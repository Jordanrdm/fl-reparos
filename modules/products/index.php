<?php
/**
 * FL REPAROS - Módulo de Produtos
 * Layout moderno com permissões
 */

include_once '../../config/app.php';
include_once '../../config/database.php';
requireAuth();
requirePermission('products', 'view');

$isAdmin = isAdmin();
$canEdit = hasPermission('products', 'edit');
$canCreate = hasPermission('products', 'create');
$canDelete = hasPermission('products', 'delete');

// =============================
// 📝 PROCESSAR AÇÕES
// =============================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($canEdit || $canCreate)) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add':
                if (!$canCreate) throw new Exception('Sem permissão para criar');

                $stmt = $pdo->prepare("
                    INSERT INTO products (name, code, barcode, category_id, cost_price, sale_price,
                                        stock_quantity, min_stock, description, allow_price_edit, active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['code'],
                    $_POST['barcode'] ?? '',
                    $_POST['category_id'] ?? null,
                    $_POST['cost_price'],
                    $_POST['sale_price'],
                    $_POST['stock_quantity'] ?? 0,
                    $_POST['min_stock'] ?? 0,
                    $_POST['description'] ?? '',
                    isset($_POST['allow_price_edit']) ? 1 : 0
                ]);
                $message = '✓ Produto criado com sucesso!';
                $message_type = 'success';
                break;

            case 'edit':
                if (!$canEdit) throw new Exception('Sem permissão para editar');

                $stmt = $pdo->prepare("
                    UPDATE products SET name=?, code=?, barcode=?, category_id=?, cost_price=?, sale_price=?,
                        stock_quantity=?, min_stock=?, description=?, allow_price_edit=?, updated_at=NOW()
                    WHERE id=?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['code'],
                    $_POST['barcode'] ?? '',
                    $_POST['category_id'] ?? null,
                    $_POST['cost_price'],
                    $_POST['sale_price'],
                    $_POST['stock_quantity'] ?? 0,
                    $_POST['min_stock'] ?? 0,
                    $_POST['description'] ?? '',
                    isset($_POST['allow_price_edit']) ? 1 : 0,
                    $_POST['id']
                ]);
                $message = '✓ Produto atualizado com sucesso!';
                $message_type = 'success';
                break;

            case 'delete':
                if (!$canDelete) throw new Exception('Sem permissão para excluir');

                $stmt = $pdo->prepare("UPDATE products SET active=0, updated_at=NOW() WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $message = '✓ Produto excluído com sucesso!';
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = '✗ Erro: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// =============================
// 📊 ESTATÍSTICAS
// =============================
$stats = $pdo->query("
    SELECT
        COUNT(*) as total_products,
        COALESCE(SUM(stock_quantity), 0) as total_stock,
        COUNT(CASE WHEN stock_quantity <= min_stock THEN 1 END) as low_stock_count,
        COALESCE(SUM(stock_quantity * cost_price), 0) as total_inventory_value
    FROM products
    WHERE active = 1
")->fetch();

// =============================
// 🔍 FILTROS E BUSCA
// =============================
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$where = ["p.active = 1"];
$params = [];

if ($search) {
    $where[] = "(p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where[] = "p.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = implode(' AND ', $where);

// =============================
// 📄 PAGINAÇÃO
// =============================
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

// Validar valores permitidos de registros por página
$allowedPerPage = [10, 20, 30, 50, 100];
if (!in_array($perPage, $allowedPerPage)) {
    $perPage = 20;
}

$offset = ($page - 1) * $perPage;

// Contar total de registros
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $where_clause
");
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetch()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Buscar produtos com paginação
$paramsWithPagination = array_merge($params, [$perPage, $offset]);
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $where_clause
    ORDER BY p.name ASC
    LIMIT ? OFFSET ?
");
$stmt->execute($paramsWithPagination);
$products = $stmt->fetchAll();

// Buscar categorias
$categories = $pdo->query("SELECT * FROM categories WHERE active = 1 ORDER BY name")->fetchAll();

include '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Produtos - FL REPAROS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin:0;
            font-family:'Segoe UI', Tahoma, sans-serif;
            background:linear-gradient(135deg,#667eea,#764ba2);
            padding:20px;
            min-height:100vh;
        }
        .container {max-width:1400px;margin:auto;}

        /* Header - Igual ao de Despesas */
        .header, .stats-box, .filters-card, .table-card {
            background:rgba(255,255,255,0.9);
            backdrop-filter:blur(10px);
            padding:20px;
            border-radius:15px;
            box-shadow:0 8px 32px rgba(0,0,0,0.15);
            margin-bottom:25px;
        }
        .header {display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;}
        .header h1 {font-size:1.8rem;display:flex;align-items:center;gap:10px;margin:0;}

        /* Cards de Estatísticas */
        .stats-grid {display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;}
        .stat-card {
            background:linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
            padding:20px;
            border-radius:12px;
            box-shadow:0 4px 15px rgba(0,0,0,0.1);
            text-align:center;
        }
        .stat-card .icon {font-size:2.5rem;margin-bottom:10px;}
        .stat-card .value {font-size:1.8rem;font-weight:bold;margin:5px 0;}
        .stat-card .label {color:#666;font-size:0.9rem;text-transform:uppercase;}
        .stat-card.green .icon {color:#4CAF50;}
        .stat-card.blue .icon {color:#2196F3;}
        .stat-card.orange .icon {color:#FF9800;}
        .stat-card.purple .icon {color:#9C27B0;}

        /* Filtros */
        .filters-form {display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;}
        .filter-group {flex:1;min-width:200px;}
        .filter-group label {display:block;margin-bottom:8px;font-weight:600;color:#666;font-size:0.9rem;}
        .filter-actions {display:flex;gap:10px;}

        /* Tabela */
        .table {
            width:100%;border-collapse:collapse;background:rgba(255,255,255,0.95);
            border-radius:15px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.1);
        }
        th,td {padding:12px;text-align:left;}
        th {background:linear-gradient(45deg,#f8f9fa,#e9ecef);font-weight:600;}
        tr:hover {background:rgba(103,58,183,0.1);}
        .empty-state {text-align:center;padding:60px 20px;color:#999;}
        .empty-state i {font-size:3rem;margin-bottom:15px;display:block;}

        /* Badges */
        .badge {padding:5px 12px;border-radius:20px;font-size:0.85rem;font-weight:600;display:inline-block;}
        .badge-success {background:#d4edda;color:#155724;}
        .badge-danger {background:#f8d7da;color:#721c24;}

        /* Botões */
        .btn {
            border:none;border-radius:8px;padding:10px 20px;color:#fff;font-weight:500;
            cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:all .3s;
            text-decoration:none;
        }
        .btn-primary {background:linear-gradient(45deg,#4CAF50,#45a049);}
        .btn-secondary {background:linear-gradient(45deg,#2196F3,#1976D2);}
        .btn-danger {background:linear-gradient(45deg,#f44336,#d32f2f);}
        .btn-info {background:linear-gradient(45deg,#17a2b8,#138496);}
        .btn:hover {transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
        .btn-sm {padding:6px 12px;font-size:0.85rem;}

        /* Modal */
        .modal {display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:1000;backdrop-filter:blur(5px);}
        .modal-content {background:white;width:90%;max-width:600px;margin:50px auto;border-radius:15px;box-shadow:0 10px 40px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;}
        .modal-header {padding:20px 30px;border-bottom:2px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;}
        .modal-header h2 {margin:0;color:#333;font-size:1.5rem;}
        .btn-close {background:none;border:none;font-size:2rem;cursor:pointer;color:#999;}
        .modal-form {padding:30px;}
        .form-row {display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;}
        .form-group {margin-bottom:20px;}
        .form-group label {display:block;margin-bottom:8px;font-weight:600;color:#666;}
        .form-control {width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px;font-size:1rem;}
        .form-control:focus {outline:none;border-color:#667eea;}
        .modal-actions {display:flex;gap:10px;justify-content:flex-end;margin-top:25px;}

        /* Alerts */
        .alert {padding:15px 20px;border-radius:10px;margin-bottom:20px;}
        .alert-success {background:#d4edda;color:#155724;border-left:4px solid #28a745;}
        .alert-error {background:#f8d7da;color:#721c24;border-left:4px solid #dc3545;}
        .alert-info {background:#d1ecf1;color:#0c5460;border-left:4px solid #17a2b8;}

        .text-muted {color:#6c757d;font-size:0.85rem;}

        /* Paginação */
        .pagination-container {
            background:rgba(255,255,255,0.9);
            backdrop-filter:blur(10px);
            padding:15px 20px;
            border-radius:15px;
            box-shadow:0 8px 32px rgba(0,0,0,0.15);
            margin-bottom:20px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:15px;
        }
        .pagination-info {
            color:#666;
            font-size:0.9rem;
            font-weight:500;
        }
        .pagination-controls {
            display:flex;
            gap:20px;
            align-items:center;
            flex-wrap:wrap;
        }
        .per-page-selector {
            display:flex;
            gap:10px;
            align-items:center;
        }
        .per-page-selector label {
            color:#666;
            font-size:0.9rem;
            font-weight:500;
        }
        .per-page-selector select {
            padding:8px 12px;
            border:2px solid #ddd;
            border-radius:8px;
            background:white;
            font-size:0.9rem;
            cursor:pointer;
            transition:all 0.3s;
        }
        .per-page-selector select:hover {
            border-color:#667eea;
        }
        .per-page-selector select:focus {
            border-color:#667eea;
            outline:none;
            box-shadow:0 0 0 3px rgba(102,126,234,0.1);
        }
        .pagination-buttons {
            display:flex;
            gap:10px;
            align-items:center;
        }
        .pagination-btn {
            padding:8px 15px;
            border:2px solid #ddd;
            border-radius:8px;
            background:white;
            color:#667eea;
            text-decoration:none;
            font-weight:500;
            transition:all 0.3s;
            display:inline-flex;
            align-items:center;
            gap:5px;
            cursor:pointer;
        }
        .pagination-btn:hover:not(.disabled) {
            background:linear-gradient(45deg,#667eea,#764ba2);
            color:white;
            border-color:transparent;
            transform:translateY(-2px);
            box-shadow:0 4px 12px rgba(102,126,234,0.3);
        }
        .pagination-btn.disabled {
            background:#f5f5f5;
            color:#ccc;
            border-color:#f0f0f0;
            cursor:not-allowed;
        }
        .page-info {
            color:#666;
            font-weight:500;
            font-size:0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Cabeçalho -->
        <div class="header">
            <h1><i class="fas fa-box"></i> Produtos</h1>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="openModal('createModal')">
                    <i class="fas fa-plus"></i> Novo Produto
                </button>
                <?php endif; ?>
                <a href="../../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= $message ?>
        </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-box">
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-box"></i></div>
                    <div class="value"><?= $stats['total_products'] ?></div>
                    <div class="label">Total de Produtos</div>
                </div>
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-cubes"></i></div>
                    <div class="value"><?= number_format($stats['total_stock']) ?></div>
                    <div class="label">Itens em Estoque</div>
                </div>
                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="value"><?= $stats['low_stock_count'] ?></div>
                    <div class="label">Estoque Baixo</div>
                </div>
                <div class="stat-card purple">
                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="value"><?= formatMoney($stats['total_inventory_value']) ?></div>
                    <div class="label">Valor em Estoque</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Nome, código ou código de barras..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-tag"></i> Categoria</label>
                    <select name="category" class="form-control">
                        <option value="">Todas</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <a href="?" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Paginação -->
        <div class="pagination-container">
            <div class="pagination-info">
                Mostrando <?= min($offset + 1, $totalRecords) ?> a <?= min($offset + $perPage, $totalRecords) ?> de <?= $totalRecords ?> registros
            </div>

            <div class="pagination-controls">
                <div class="per-page-selector">
                    <label>Registros por página:</label>
                    <select onchange="changePerPage(this.value)">
                        <?php foreach($allowedPerPage as $option): ?>
                            <option value="<?= $option ?>" <?= $perPage == $option ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pagination-buttons">
                    <?php if($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&per_page=<?= $perPage ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($category_filter) ? '&category=' . urlencode($category_filter) : '' ?>" class="pagination-btn">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
                    <?php endif; ?>

                    <span class="page-info">Página <?= $page ?> de <?= max(1, $totalPages) ?></span>

                    <?php if($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&per_page=<?= $perPage ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($category_filter) ? '&category=' . urlencode($category_filter) : '' ?>" class="pagination-btn">
                            Próxima <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">Próxima <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabela -->
        <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Custo</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th>Editar Preço</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <p>Nenhum produto encontrado</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><span class="badge"><?= htmlspecialchars($product['code']) ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars($product['name']) ?></strong>
                            <?php if ($product['barcode']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($product['barcode']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                        <td><?= formatMoney($product['cost_price']) ?></td>
                        <td><strong><?= formatMoney($product['sale_price']) ?></strong></td>
                        <td>
                            <span class="badge badge-<?= $product['stock_quantity'] <= $product['min_stock'] ? 'danger' : 'success' ?>">
                                <?= $product['stock_quantity'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($product['allow_price_edit']): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> SIM</span>
                            <?php else: ?>
                            <span class="badge badge-danger"><i class="fas fa-lock"></i> NÃO</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($canEdit): ?>
                            <button class="btn btn-sm btn-info" onclick='openEditModal(<?= json_encode($product) ?>)' title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?= $product['id'] ?>)" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (!$canEdit && !$canDelete): ?>
                            <button class="btn btn-sm btn-info" onclick='openViewModal(<?= json_encode($product) ?>)' title="Visualizar">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
        </table>
    </div>

    <!-- Modal Criar -->
    <?php if ($canCreate): ?>
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Novo Produto</h2>
                <button class="btn-close" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="add">

                <div class="form-row">
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Código *</label>
                        <input type="text" name="code" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Código de Barras</label>
                        <input type="text" name="barcode" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="category_id" class="form-control">
                            <option value="">Selecione...</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Preço de Custo *</label>
                        <input type="number" name="cost_price" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Preço de Venda *</label>
                        <input type="number" name="sale_price" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Quantidade em Estoque *</label>
                        <input type="number" name="stock_quantity" class="form-control" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label>Estoque Mínimo *</label>
                        <input type="number" name="min_stock" class="form-control" min="0" value="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="allow_price_edit" value="1" checked>
                        Permitir editar preço na venda/OS
                    </label>
                    <br><small class="text-muted">Desmarque para produtos com preço fixo (ex: acessórios)</small>
                </div>

                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Editar Produto</h2>
                <button class="btn-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-row">
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Código *</label>
                        <input type="text" name="code" id="edit_code" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Código de Barras</label>
                        <input type="text" name="barcode" id="edit_barcode" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="category_id" id="edit_category_id" class="form-control">
                            <option value="">Selecione...</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Preço de Custo *</label>
                        <input type="number" name="cost_price" id="edit_cost_price" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Preço de Venda *</label>
                        <input type="number" name="sale_price" id="edit_sale_price" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Quantidade em Estoque *</label>
                        <input type="number" name="stock_quantity" id="edit_stock_quantity" class="form-control" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Estoque Mínimo *</label>
                        <input type="number" name="min_stock" id="edit_min_stock" class="form-control" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="allow_price_edit" id="edit_allow_price_edit" value="1">
                        Permitir editar preço na venda/OS
                    </label>
                    <br><small class="text-muted">Desmarque para produtos com preço fixo</small>
                </div>

                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Atualizar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Visualizar (Funcionários) -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> Detalhes do Produto</h2>
                <button class="btn-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-form">
                <div class="form-group">
                    <label>Nome</label>
                    <div id="view_name" style="padding:10px;background:#f8f9fa;border-radius:5px;"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Código</label>
                        <div id="view_code" style="padding:10px;background:#f8f9fa;border-radius:5px;"></div>
                    </div>
                    <div class="form-group">
                        <label>Código de Barras</label>
                        <div id="view_barcode" style="padding:10px;background:#f8f9fa;border-radius:5px;"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Categoria</label>
                    <div id="view_category" style="padding:10px;background:#f8f9fa;border-radius:5px;"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Preço de Venda</label>
                        <div id="view_price" style="padding:10px;background:#f8f9fa;border-radius:5px;font-weight:bold;"></div>
                    </div>
                    <div class="form-group">
                        <label>Estoque</label>
                        <div id="view_stock" style="padding:10px;background:#f8f9fa;border-radius:5px;"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Descrição</label>
                    <div id="view_description" style="padding:10px;background:#f8f9fa;border-radius:5px;min-height:60px;"></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">
                        <i class="fas fa-times"></i> Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Delete -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function openEditModal(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_code').value = product.code;
            document.getElementById('edit_barcode').value = product.barcode || '';
            document.getElementById('edit_category_id').value = product.category_id || '';
            document.getElementById('edit_cost_price').value = product.cost_price;
            document.getElementById('edit_sale_price').value = product.sale_price;
            document.getElementById('edit_stock_quantity').value = product.stock_quantity;
            document.getElementById('edit_min_stock').value = product.min_stock;
            document.getElementById('edit_allow_price_edit').checked = product.allow_price_edit == 1;
            document.getElementById('edit_description').value = product.description || '';
            openModal('editModal');
        }

        function openViewModal(product) {
            document.getElementById('view_name').textContent = product.name;
            document.getElementById('view_code').textContent = product.code;
            document.getElementById('view_barcode').textContent = product.barcode || '-';
            document.getElementById('view_category').textContent = product.category_name || '-';
            document.getElementById('view_price').textContent = 'R$ ' + parseFloat(product.sale_price).toFixed(2).replace('.', ',');
            document.getElementById('view_stock').textContent = product.stock_quantity + ' unidades';
            document.getElementById('view_description').textContent = product.description || '-';
            openModal('viewModal');
        }

        function deleteProduct(id) {
            if (confirm('Tem certeza que deseja excluir este produto?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // ESC para fechar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal('createModal');
                closeModal('editModal');
                closeModal('viewModal');
            }
        });

        // Função para mudar registros por página
        function changePerPage(perPage) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', perPage);
            urlParams.set('page', 1); // Voltar para primeira página ao mudar quantidade
            window.location.search = urlParams.toString();
        }
    </script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>
