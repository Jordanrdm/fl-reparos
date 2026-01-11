<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once('../../config/database.php');
require_once('../../config/app.php');
$conn = $database->getConnection();

// Verificar permissão de visualização
requirePermission('service_orders', 'view');

// =============================
// 🔧 CRUD
// =============================

// Criar OS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    requirePermission('service_orders', 'create');
    try {
        $stmt = $conn->prepare("INSERT INTO service_orders
            (customer_id, user_id, device, status, total_cost, payment_method, installments, change_amount, entry_datetime,
             technical_report, reported_problem, customer_observations, internal_observations,
             device_powers_on, device_password,
             checklist_lens, checklist_lens_condition, checklist_back_cover, checklist_screen,
             checklist_connector, checklist_camera_front_back, checklist_face_id, checklist_sim_card,
             technician_name, attendant_name, warranty_period)
            VALUES (?, ?, ?, 'open', ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['customer_id'],
            $_SESSION['user_id'],
            $_POST['device'],
            $_POST['total_cost'] ?? 0,
            $_POST['payment_method'] ?? null,
            $_POST['installments'] ?? 1,
            $_POST['change_amount'] ?? 0,
            $_POST['technical_report'] ?? null,
            $_POST['reported_problem'] ?? null,
            $_POST['customer_observations'] ?? null,
            $_POST['internal_observations'] ?? null,
            $_POST['device_powers_on'] ?? 'sim',
            $_POST['device_password'] ?? null,
            isset($_POST['checklist_lens']) ? 1 : 0,
            $_POST['checklist_lens_condition'] ?? null,
            $_POST['checklist_back_cover'] ?? null,
            isset($_POST['checklist_screen']) ? 1 : 0,
            isset($_POST['checklist_connector']) ? 1 : 0,
            $_POST['checklist_camera_front_back'] ?? null,
            isset($_POST['checklist_face_id']) ? 1 : 0,
            isset($_POST['checklist_sim_card']) ? 1 : 0,
            $_POST['technician_name'] ?? null,
            $_POST['attendant_name'] ?? null,
            $_POST['warranty_period'] ?? '90 dias'
        ]);

        // Pegar ID da OS criada
        $osId = $conn->lastInsertId();

        // Salvar produtos utilizados (se houver)
        if (!empty($_POST['products_data'])) {
            $productsData = json_decode($_POST['products_data'], true);

            if (is_array($productsData) && count($productsData) > 0) {
                $stmtProduct = $conn->prepare("INSERT INTO service_order_items
                    (service_order_id, product_id, quantity, price, subtotal)
                    VALUES (?, ?, ?, ?, ?)");

                $stmtUpdateStock = $conn->prepare("UPDATE products
                    SET stock_quantity = stock_quantity - ?
                    WHERE id = ?");

                foreach ($productsData as $product) {
                    // Inserir item
                    $stmtProduct->execute([
                        $osId,
                        $product['id'],
                        $product['quantity'],
                        $product['price'],
                        $product['subtotal']
                    ]);

                    // Atualizar estoque
                    $stmtUpdateStock->execute([
                        $product['quantity'],
                        $product['id']
                    ]);
                }
            }
        }

        echo "<script>alert('Ordem de serviço criada com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao cadastrar: " . $e->getMessage() . "');</script>";
    }
}

// Editar OS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    requirePermission('service_orders', 'edit');
    try {
        // Buscar status anterior da OS
        $stmtOld = $conn->prepare("SELECT status, total_cost, payment_method FROM service_orders WHERE id = ?");
        $stmtOld->execute([$_POST['id']]);
        $oldOrder = $stmtOld->fetch(PDO::FETCH_ASSOC);

        // Bloquear edição se a OS já foi entregue
        if ($oldOrder['status'] === 'delivered') {
            echo "<script>alert('⚠️ Ordem de Serviço já foi ENTREGUE e não pode mais ser editada!');window.location='index.php';</script>";
            exit;
        }

        $stmt = $conn->prepare("UPDATE service_orders
            SET customer_id=?, device=?, status=?, total_cost=?, payment_method=?, installments=?, change_amount=?,
                technical_report=?, reported_problem=?, customer_observations=?, internal_observations=?,
                device_powers_on=?, device_password=?,
                checklist_lens=?, checklist_lens_condition=?, checklist_back_cover=?, checklist_screen=?,
                checklist_connector=?, checklist_camera_front_back=?, checklist_face_id=?, checklist_sim_card=?,
                technician_name=?, attendant_name=?, warranty_period=?
            WHERE id=?");
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['device'],
            $_POST['status'],
            $_POST['total_cost'] ?? 0,
            $_POST['payment_method'] ?? null,
            $_POST['installments'] ?? 1,
            $_POST['change_amount'] ?? 0,
            $_POST['technical_report'] ?? null,
            $_POST['reported_problem'] ?? null,
            $_POST['customer_observations'] ?? null,
            $_POST['internal_observations'] ?? null,
            $_POST['device_powers_on'] ?? 'sim',
            $_POST['device_password'] ?? null,
            isset($_POST['checklist_lens']) ? 1 : 0,
            $_POST['checklist_lens_condition'] ?? null,
            $_POST['checklist_back_cover'] ?? null,
            isset($_POST['checklist_screen']) ? 1 : 0,
            isset($_POST['checklist_connector']) ? 1 : 0,
            $_POST['checklist_camera_front_back'] ?? null,
            isset($_POST['checklist_face_id']) ? 1 : 0,
            isset($_POST['checklist_sim_card']) ? 1 : 0,
            $_POST['technician_name'] ?? null,
            $_POST['attendant_name'] ?? null,
            $_POST['warranty_period'] ?? '90 dias',
            $_POST['id']
        ]);

        // Se status mudou para "delivered" (Entregue), registrar no caixa
        if ($oldOrder['status'] !== 'delivered' && $_POST['status'] === 'delivered') {
            $totalCost = $_POST['total_cost'] ?? 0;
            $paymentMethod = $_POST['payment_method'] ?? 'dinheiro';
            $changeAmount = $_POST['change_amount'] ?? 0;

            // Só registra no caixa se tiver valor maior que zero
            if ($totalCost > 0) {
                // VERIFICAR SE JÁ EXISTE REGISTRO NO CAIXA PARA ESSA O.S.
                // (evita duplicação quando O.S. é reaberta e fechada novamente)
                $stmtCheck = $conn->prepare("SELECT COUNT(*) as total FROM cash_flow
                    WHERE reference_id = ? AND reference_type IN ('service_order', 'service_order_change')");
                $stmtCheck->execute([$_POST['id']]);
                $existeRegistro = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'] > 0;

                // Só registra se ainda NÃO existe registro no caixa
                if (!$existeRegistro) {
                    $description = "OS #" . $_POST['id'] . " - " . $_POST['device'] . " - " . ucfirst($paymentMethod);

                    // Se for pagamento em dinheiro com troco
                    if ($paymentMethod === 'dinheiro' && $changeAmount > 0) {
                        $valorRecebido = $totalCost + $changeAmount;

                        // Registrar ENTRADA: Valor recebido do cliente (positivo)
                        $stmtCash = $conn->prepare("INSERT INTO cash_flow
                            (user_id, type, description, amount, reference_id, reference_type, created_at)
                            VALUES (?, 'service', ?, ?, ?, 'service_order', NOW())");
                        $stmtCash->execute([
                            $_SESSION['user_id'],
                            $description . " - Valor Recebido",
                            $valorRecebido,
                            $_POST['id']
                        ]);

                        // Registrar SAÍDA: Troco devolvido (negativo como expense)
                        $stmtCash = $conn->prepare("INSERT INTO cash_flow
                            (user_id, type, description, amount, reference_id, reference_type, created_at)
                            VALUES (?, 'expense', ?, ?, ?, 'service_order_change', NOW())");
                        $stmtCash->execute([
                            $_SESSION['user_id'],
                            $description . " - Troco",
                            $changeAmount,
                            $_POST['id']
                        ]);
                    } else {
                        // Pagamento sem troco (PIX, Cartão, etc) - registra valor total
                        $stmtCash = $conn->prepare("INSERT INTO cash_flow
                            (user_id, type, description, amount, reference_id, reference_type, created_at)
                            VALUES (?, 'service', ?, ?, ?, 'service_order', NOW())");
                        $stmtCash->execute([
                            $_SESSION['user_id'],
                            $description,
                            $totalCost,
                            $_POST['id']
                        ]);
                    }
                }
            }
        }

        // Atualizar produtos utilizados (se houver)
        if (!empty($_POST['products_data'])) {
            $productsData = json_decode($_POST['products_data'], true);

            // Primeiro, remover produtos antigos e devolver ao estoque
            $stmtOldProducts = $conn->prepare("SELECT product_id, quantity FROM service_order_items WHERE service_order_id = ?");
            $stmtOldProducts->execute([$_POST['id']]);
            $oldProducts = $stmtOldProducts->fetchAll(PDO::FETCH_ASSOC);

            foreach ($oldProducts as $oldProduct) {
                // Devolver quantidade ao estoque
                $stmtReturnStock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                $stmtReturnStock->execute([$oldProduct['quantity'], $oldProduct['product_id']]);
            }

            // Remover todos os produtos antigos
            $stmtDeleteItems = $conn->prepare("DELETE FROM service_order_items WHERE service_order_id = ?");
            $stmtDeleteItems->execute([$_POST['id']]);

            // Adicionar novos produtos
            if (is_array($productsData) && count($productsData) > 0) {
                $stmtProduct = $conn->prepare("INSERT INTO service_order_items
                    (service_order_id, product_id, quantity, price, subtotal)
                    VALUES (?, ?, ?, ?, ?)");

                $stmtUpdateStock = $conn->prepare("UPDATE products
                    SET stock_quantity = stock_quantity - ?
                    WHERE id = ?");

                foreach ($productsData as $product) {
                    // Inserir item
                    $stmtProduct->execute([
                        $_POST['id'],
                        $product['id'],
                        $product['quantity'],
                        $product['price'],
                        $product['subtotal']
                    ]);

                    // Atualizar estoque
                    $stmtUpdateStock->execute([
                        $product['quantity'],
                        $product['id']
                    ]);
                }
            }
        }

        echo "<script>alert('Ordem de serviço atualizada com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao atualizar: " . $e->getMessage() . "');</script>";
    }
}

// Excluir OS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    requirePermission('service_orders', 'delete');
    $id = (int) $_POST['id'];

    // Verificar se a OS já foi entregue
    $stmtCheck = $conn->prepare("SELECT status FROM service_orders WHERE id = ?");
    $stmtCheck->execute([$id]);
    $order = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($order && $order['status'] === 'delivered') {
        echo "<script>alert('⚠️ Não é possível excluir uma OS que já foi ENTREGUE!');window.location='index.php';</script>";
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM service_orders WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>alert('Ordem de serviço excluída com sucesso!');window.location='index.php';</script>";
    exit;
}

// Desbloquear OS (requer admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'unlock') {
    // Limpar qualquer output anterior
    ob_clean();

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $osId = (int) ($_POST['os_id'] ?? 0);

    // Verificar se usuário é admin (buscar por email)
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        // Admin válido, desbloquear OS (mudar status de 'delivered' para 'completed')
        $stmtUnlock = $conn->prepare("UPDATE service_orders SET status = 'completed' WHERE id = ?");
        $stmtUnlock->execute([$osId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'OS desbloqueada com sucesso!']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Credenciais de admin inválidas!']);
    }
    exit;
}

// =============================
// 🔍 LISTAGEM E FILTRO
// =============================
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (c.name LIKE ? OR so.device LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_status)) {
    $where .= " AND so.status = ?";
    $params[] = $filter_status;
}

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
$stmtCount = $conn->prepare("
    SELECT COUNT(*) as total
    FROM service_orders so
    LEFT JOIN customers c ON so.customer_id = c.id
    $where
");
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $perPage);

// Buscar registros com paginação
$paramsWithPagination = array_merge($params, [$perPage, $offset]);
$stmt = $conn->prepare("
    SELECT so.*, c.name as customer_name, c.phone as customer_phone
    FROM service_orders so
    LEFT JOIN customers c ON so.customer_id = c.id AND c.deleted_at IS NULL
    $where
    ORDER BY so.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($paramsWithPagination);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar clientes para o formulário (apenas clientes ativos)
$customers = $conn->query("SELECT id, name, phone FROM customers WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Buscar produtos para o formulário
$products = $conn->query("SELECT id, name, sale_price as price, stock_quantity FROM products WHERE stock_quantity > 0 AND active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// =============================
// 📊 ESTATÍSTICAS
// =============================
$stats = $conn->query("
    SELECT
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'open' THEN 1 END) as awaiting,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
        COALESCE(SUM(total_cost), 0) as total_revenue
    FROM service_orders
")->fetch(PDO::FETCH_ASSOC);

// =============================
// 🎨 FUNÇÕES AUXILIARES
// =============================
function traduzirStatus($status) {
    $map = [
        'open' => 'Aguardando',
        'in_progress' => 'Em Andamento',
        'completed' => 'Concluído',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelado'
    ];
    return $map[$status] ?? ucfirst($status);
}

// formatMoney já está definido em app.php
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ordem de Serviço - FL Reparos</title>
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
.header, .search-box, .stats-box {
    background:rgba(255,255,255,0.9);
    backdrop-filter:blur(10px);
    padding:20px;
    border-radius:15px;
    box-shadow:0 8px 32px rgba(0,0,0,0.15);
    margin-bottom:25px;
}
.header {display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;}
.header h1 {font-size:1.8rem;display:flex;align-items:center;gap:10px;margin:0;}
.btn {
    border:none;border-radius:8px;padding:10px 20px;color:#fff;font-weight:500;
    cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:all .3s;
    text-decoration:none;
}
.btn-primary {background:linear-gradient(45deg,#4CAF50,#45a049);}
.btn-secondary {background:linear-gradient(45deg,#2196F3,#1976D2);}
.btn-danger {background:linear-gradient(45deg,#f44336,#d32f2f);}
.btn-warning {background:linear-gradient(45deg,#FF9800,#F57C00);}
.btn:hover {transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
.btn-sm {padding:6px 12px;font-size:0.85rem;}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
    gap:15px;
}
.stat-card {
    background:linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
    padding:15px;
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,0.1);
    text-align:center;
}
.stat-card .icon {
    font-size:2rem;
    margin-bottom:8px;
}
.stat-card .value {
    font-size:1.5rem;
    font-weight:bold;
    margin:5px 0;
}
.stat-card .label {
    color:#666;
    font-size:0.85rem;
    text-transform:uppercase;
}
.stat-card.blue .icon {color:#2196F3;}
.stat-card.orange .icon {color:#FF9800;}
.stat-card.green .icon {color:#4CAF50;}
.stat-card.purple .icon {color:#9C27B0;}

.table {
    width:100%;border-collapse:collapse;background:rgba(255,255,255,0.95);
    border-radius:15px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.1);
}
th,td {padding:12px;text-align:left;}
th {background:linear-gradient(45deg,#f8f9fa,#e9ecef);font-weight:600;}
tr:hover {background:rgba(103,58,183,0.1);}
.badge {
    padding:5px 10px;border-radius:8px;color:white;font-weight:bold;text-transform:capitalize;
    font-size:0.85rem;display:inline-block;
}
.open {background:#FF9800;color:#333;}
.in_progress {background:#2196F3;}
.completed {background:#4CAF50;}
.delivered {background:#9C27B0;}
.cancelled {background:#f44336;}

.modal {
    display:none;position:fixed;top:0;left:0;width:100%;height:100%;
    background:rgba(0,0,0,0.6);z-index:1000;backdrop-filter:blur(5px);
    overflow-y:auto;
}
.modal-content {
    background:rgba(255,255,255,0.95);margin:40px auto;padding:30px;
    border-radius:15px;max-width:700px;box-shadow:0 8px 32px rgba(0,0,0,0.2);
}
.modal-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.modal-header h2 {margin:0;display:flex;align-items:center;gap:10px;}
.close {
    background:#f44336;border:none;color:#fff;border-radius:50%;
    width:35px;height:35px;cursor:pointer;font-size:18px;transition:all .3s;
}
.close:hover {transform:rotate(90deg);background:#d32f2f;}
.form-row {display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;}
.form-group {flex:1;min-width:200px;}
.form-group label {display:block;margin-bottom:5px;font-weight:600;color:#333;}
.form-control {
    width:100%;padding:10px;border:2px solid #ddd;border-radius:8px;
    font-size:1rem;transition:border .3s;
}
.form-control:focus {border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,0.1);}
.empty {text-align:center;padding:40px;color:#777;font-style:italic;}

.filter-box {
    display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;
}
.filter-box .form-group {
    flex:1;min-width:200px;
}

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
    <div class="header">
        <h1><i class="fas fa-wrench"></i> Ordem de Serviço</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="openModal('createModal')"><i class="fas fa-plus"></i> Nova OS</button>
            <a href="../../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <div class="stats-box">
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="value"><?= $stats['total_orders'] ?></div>
                <div class="label">Total de OS</div>
            </div>
            <div class="stat-card orange">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="value"><?= $stats['awaiting'] ?></div>
                <div class="label">Aguardando</div>
            </div>
            <div class="stat-card blue">
                <div class="icon"><i class="fas fa-tools"></i></div>
                <div class="value"><?= $stats['in_progress'] ?></div>
                <div class="label">Em Andamento</div>
            </div>
            <div class="stat-card green">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="value"><?= $stats['delivered'] ?></div>
                <div class="label">Entregue</div>
            </div>
            <div class="stat-card purple">
                <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="value"><?= formatMoney($stats['total_revenue']) ?></div>
                <div class="label">Receita Total</div>
            </div>
        </div>
    </div>

    <div class="search-box">
        <form method="GET">
            <div class="filter-box">
                <div class="form-group">
                    <label>Buscar</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Cliente, aparelho...">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">Todos</option>
                        <option value="open" <?= $filter_status === 'open' ? 'selected' : '' ?>>Aguardando</option>
                        <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>Em Andamento</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Concluído</option>
                        <option value="delivered" <?= $filter_status === 'delivered' ? 'selected' : '' ?>>Entregue</option>
                        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-search"></i> Filtrar</button>
                </div>
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
                    <a href="?page=<?= $page - 1 ?>&per_page=<?= $perPage ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($filter_status) ? '&status=' . urlencode($filter_status) : '' ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>

                <span class="page-info">Página <?= $page ?> de <?= max(1, $totalPages) ?></span>

                <?php if($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&per_page=<?= $perPage ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($filter_status) ? '&status=' . urlencode($filter_status) : '' ?>" class="pagination-btn">
                        Próxima <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled">Próxima <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>OS#</th>
                <th>Cliente</th>
                <th>Aparelho</th>
                <th>Status</th>
                <th>Valor (R$)</th>
                <th>Data</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($orders)): ?>
                <tr><td colspan="7" class="empty">Nenhuma ordem de serviço cadastrada.</td></tr>
            <?php else: foreach($orders as $row): ?>
            <tr>
                <td><strong>#<?= $row['id'] ?></strong></td>
                <td>
                    <strong><?= htmlspecialchars($row['customer_name'] ?? 'N/A') ?></strong>
                    <?php if(!empty($row['customer_phone'])): ?>
                        <br><small style="color:#666;"><i class="fas fa-phone"></i> <?= htmlspecialchars($row['customer_phone']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['device']) ?></td>
                <td><span class="badge <?= $row['status'] ?>"><?= traduzirStatus($row['status']) ?></span></td>
                <td><strong><?= formatMoney($row['total_cost']) ?></strong></td>
                <td><?= date('d/m/Y', strtotime($row['entry_datetime'])) ?></td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick='printServiceOrder(<?= json_encode($row) ?>)' title="Imprimir">
                        <i class="fas fa-print"></i>
                    </button>
                    <?php if ($row['status'] !== 'delivered'): ?>
                        <?php if (hasPermission('service_orders', 'edit')): ?>
                        <button class="btn btn-warning btn-sm" onclick='openEditModal(<?= json_encode($row) ?>)' title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (hasPermission('service_orders', 'delete')): ?>
                        <form method="POST" onsubmit="return confirm('Excluir esta OS?');" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Excluir"><i class="fas fa-trash-alt"></i></button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                    <button class="btn btn-secondary btn-sm" onclick="openUnlockModal(<?= $row['id'] ?>)" title="Desbloquear OS (requer admin)">
                        <i class="fas fa-lock"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- MODAL NOVA OS -->
<div id="createModal" class="modal">
    <div class="modal-content" style="max-width:900px;">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> Nova Ordem de Serviço</h2>
            <button class="close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <!-- Dados Básicos -->
            <h3 style="margin:20px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-info-circle"></i> Dados Básicos
            </h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente *</label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">Selecione</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Aparelho *</label>
                    <input type="text" name="device" class="form-control" required placeholder="Ex: iPhone 13 Pro">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Valor do Serviço (R$)</label>
                    <input type="number" step="0.01" name="total_cost" id="create_total_cost" class="form-control" placeholder="0,00" value="0" oninput="calculateChange('create')">
                </div>
                <div class="form-group">
                    <label>Forma de Pagamento</label>
                    <select name="payment_method" id="create_payment_method" class="form-control" onchange="toggleInstallments('create')">
                        <option value="">Selecione</option>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao_credito">Cartão de Crédito</option>
                        <option value="cartao_debito">Cartão de Débito</option>
                    </select>
                </div>
                <div class="form-group" id="create_installments_field" style="display: none;">
                    <label>Parcelas</label>
                    <select name="installments" class="form-control">
                        <option value="1">1x sem juros</option>
                        <option value="2">2x sem juros</option>
                        <option value="3">3x sem juros</option>
                        <option value="4">4x sem juros</option>
                        <option value="5">5x sem juros</option>
                        <option value="6">6x sem juros</option>
                        <option value="7">7x</option>
                        <option value="8">8x</option>
                        <option value="9">9x</option>
                        <option value="10">10x</option>
                        <option value="11">11x</option>
                        <option value="12">12x</option>
                    </select>
                </div>
            </div>
            <div class="form-row" id="create_cash_payment_fields" style="display: none;">
                <div class="form-group">
                    <label>Valor Recebido (R$)</label>
                    <input type="number" step="0.01" id="create_amount_received" class="form-control" placeholder="0,00" value="0" oninput="calculateChange('create')">
                </div>
                <div class="form-group">
                    <label>Troco (R$)</label>
                    <input type="number" step="0.01" name="change_amount" id="create_change_amount" class="form-control" placeholder="0,00" value="0" readonly style="background-color: #f0f0f0;">
                </div>
            </div>

            <!-- Produtos Utilizados -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-box"></i> Produtos Utilizados no Reparo
            </h3>
            <div id="create_products_section">
                <div style="background:#f8f9ff;padding:15px;border-radius:8px;margin-bottom:15px;">
                    <div class="form-row">
                        <div class="form-group" style="flex:2;position:relative;">
                            <label>Buscar Produto</label>
                            <input type="text" id="create_product_search" class="form-control" placeholder="Digite o nome do produto..." autocomplete="off" oninput="searchProducts('create')">
                            <div id="create_product_results" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #ddd;border-radius:8px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                            <input type="hidden" id="create_selected_product_id">
                            <input type="hidden" id="create_selected_product_name">
                            <input type="hidden" id="create_selected_product_price">
                            <input type="hidden" id="create_selected_product_stock">
                        </div>
                        <div class="form-group">
                            <label>Quantidade</label>
                            <input type="number" id="create_product_quantity" class="form-control" value="1" min="1">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary" onclick="addProductToOS('create')" style="width:100%;">
                                <i class="fas fa-plus"></i> Adicionar
                            </button>
                        </div>
                    </div>
                </div>

                <div id="create_products_list"></div>
                <input type="hidden" id="create_products_data" name="products_data" value="[]">
            </div>

            <script>
            // Array de produtos disponíveis
            const availableProducts = <?= json_encode($products) ?>;

            function searchProducts(mode) {
                const searchInput = document.getElementById(mode + '_product_search');
                const resultsDiv = document.getElementById(mode + '_product_results');
                const searchTerm = searchInput.value.toLowerCase().trim();

                if (searchTerm.length < 2) {
                    resultsDiv.style.display = 'none';
                    return;
                }

                const filteredProducts = availableProducts.filter(p =>
                    p.name.toLowerCase().includes(searchTerm)
                );

                if (filteredProducts.length === 0) {
                    resultsDiv.innerHTML = '<div style="padding:10px;color:#999;">Nenhum produto encontrado</div>';
                    resultsDiv.style.display = 'block';
                    return;
                }

                let html = '';
                filteredProducts.forEach(p => {
                    html += `<div style="padding:10px;cursor:pointer;border-bottom:1px solid #f0f0f0;"
                                  onmouseover="this.style.background='#f8f9ff'"
                                  onmouseout="this.style.background='white'"
                                  onclick="selectProduct('${mode}', ${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.price}, ${p.stock_quantity})">
                        <strong>${p.name}</strong><br>
                        <small style="color:#666;">R$ ${parseFloat(p.price).toFixed(2).replace('.', ',')} - Estoque: ${p.stock_quantity}</small>
                    </div>`;
                });

                resultsDiv.innerHTML = html;
                resultsDiv.style.display = 'block';
            }

            function selectProduct(mode, id, name, price, stock) {
                document.getElementById(mode + '_product_search').value = name;
                document.getElementById(mode + '_selected_product_id').value = id;
                document.getElementById(mode + '_selected_product_name').value = name;
                document.getElementById(mode + '_selected_product_price').value = price;
                document.getElementById(mode + '_selected_product_stock').value = stock;
                document.getElementById(mode + '_product_results').style.display = 'none';
            }

            // Fechar dropdown ao clicar fora
            document.addEventListener('click', function(e) {
                const createResults = document.getElementById('create_product_results');
                const editResults = document.getElementById('edit_product_results');

                if (!e.target.closest('#create_product_search') && !e.target.closest('#create_product_results')) {
                    if (createResults) createResults.style.display = 'none';
                }

                if (!e.target.closest('#edit_product_search') && !e.target.closest('#edit_product_results')) {
                    if (editResults) editResults.style.display = 'none';
                }
            });
            </script>

            <!-- Diagnóstico -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-stethoscope"></i> Diagnóstico
            </h3>
            <div class="form-group">
                <label>Problema Relatado</label>
                <textarea name="reported_problem" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>O Aparelho Liga?</label>
                    <select name="device_powers_on" class="form-control">
                        <option value="sim">Sim</option>
                        <option value="nao">Não</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Senha/Padrão do Aparelho</label>
                    <input type="text" name="device_password" class="form-control" placeholder="Digite a senha ou desenhe o padrão">
                </div>
            </div>
            <div class="form-group">
                <label>Laudo Técnico</label>
                <textarea name="technical_report" class="form-control" rows="3"></textarea>
            </div>

            <!-- Checklist -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-clipboard-check"></i> Checklist do Aparelho
            </h3>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_lens" value="1"> Lente
                </label>
                <div class="form-group" style="margin:0;">
                    <select name="checklist_lens_condition" class="form-control">
                        <option value="">Condição da lente</option>
                        <option value="sem">Sem lente</option>
                        <option value="arranhada">Arranhada</option>
                        <option value="trincada">Trincada</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;grid-column:1/-1;">
                    <input type="text" name="checklist_back_cover" class="form-control" placeholder="Tampa traseira (trincada, detalhes...)">
                </div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_screen" value="1"> Tela Trincada
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_sim_card" value="1"> Chip
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_face_id" value="1"> Sem Face ID
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_connector" value="1"> Conector
                </label>
                <div class="form-group" style="margin:0;grid-column:1/-1;">
                    <select name="checklist_camera_front_back" class="form-control">
                        <option value="">Câmera</option>
                        <option value="frontal">Frontal</option>
                        <option value="traseira">Traseira</option>
                        <option value="ambas">Ambas</option>
                    </select>
                </div>
            </div>

            <!-- Observações -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-comment-alt"></i> Observações
            </h3>
            <div class="form-group">
                <label>Observações para o Cliente</label>
                <textarea name="customer_observations" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Observações Internas</label>
                <textarea name="internal_observations" class="form-control" rows="2" style="background:#fff9e6;"></textarea>
                <small style="color:#666;"><i class="fas fa-lock"></i> Não serão impressas</small>
            </div>

            <!-- Responsáveis -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-users"></i> Responsáveis
            </h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Técnico Responsável</label>
                    <input type="text" name="technician_name" class="form-control">
                </div>
                <div class="form-group">
                    <label>Atendente Responsável</label>
                    <input type="text" name="attendant_name" class="form-control">
                </div>
            </div>

            <!-- Garantia -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-shield-alt"></i> Garantia
            </h3>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Período de Garantia</label>
                    <select name="warranty_period" class="form-control">
                        <option value="30 dias">30 dias</option>
                        <option value="60 dias">60 dias</option>
                        <option value="90 dias" selected>90 dias (Padrão)</option>
                        <option value="3 meses">3 meses</option>
                        <option value="4 meses">4 meses</option>
                        <option value="6 meses">6 meses</option>
                        <option value="1 ano">1 ano</option>
                        <option value="Sem garantia">Sem garantia</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="button"
                            class="btn btn-secondary"
                            onclick="window.open('../settings/warranty_config.php', '_blank')"
                            style="white-space: nowrap;">
                        <i class="fas fa-cog"></i> Configurar Termos
                    </button>
                </div>
            </div>

            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR OS -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width:900px;">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Editar Ordem de Serviço</h2>
            <button class="close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <!-- Dados Básicos -->
            <h3 style="margin:20px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-info-circle"></i> Dados Básicos
            </h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente *</label>
                    <select name="customer_id" id="edit_customer_id" class="form-control" required>
                        <option value="">Selecione</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Aparelho *</label>
                    <input type="text" name="device" id="edit_device" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" id="edit_status" class="form-control" required>
                        <option value="open">Aguardando</option>
                        <option value="in_progress">Em Andamento</option>
                        <option value="completed">Concluído</option>
                        <option value="delivered">Entregue</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Valor do Serviço (R$)</label>
                    <input type="number" step="0.01" name="total_cost" id="edit_total_cost" class="form-control" oninput="calculateChange('edit')">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Forma de Pagamento</label>
                    <select name="payment_method" id="edit_payment_method" class="form-control" onchange="toggleInstallments('edit')">
                        <option value="">Selecione</option>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao_credito">Cartão de Crédito</option>
                        <option value="cartao_debito">Cartão de Débito</option>
                    </select>
                </div>
                <div class="form-group" id="edit_installments_field" style="display: none;">
                    <label>Parcelas</label>
                    <select name="installments" id="edit_installments" class="form-control">
                        <option value="1">1x sem juros</option>
                        <option value="2">2x sem juros</option>
                        <option value="3">3x sem juros</option>
                        <option value="4">4x sem juros</option>
                        <option value="5">5x sem juros</option>
                        <option value="6">6x sem juros</option>
                        <option value="7">7x</option>
                        <option value="8">8x</option>
                        <option value="9">9x</option>
                        <option value="10">10x</option>
                        <option value="11">11x</option>
                        <option value="12">12x</option>
                    </select>
                </div>
            </div>
            <div class="form-row" id="edit_cash_payment_fields" style="display: none;">
                <div class="form-group">
                    <label>Valor Recebido (R$)</label>
                    <input type="number" step="0.01" id="edit_amount_received" class="form-control" placeholder="0,00" value="0" oninput="calculateChange('edit')">
                </div>
                <div class="form-group">
                    <label>Troco (R$)</label>
                    <input type="number" step="0.01" name="change_amount" id="edit_change_amount" class="form-control" placeholder="0,00" value="0" readonly style="background-color: #f0f0f0;">
                </div>
            </div>

            <!-- Produtos Utilizados -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-box"></i> Produtos Utilizados no Reparo
            </h3>
            <div id="edit_products_section">
                <div style="background:#f8f9ff;padding:15px;border-radius:8px;margin-bottom:15px;">
                    <div class="form-row">
                        <div class="form-group" style="flex:2;position:relative;">
                            <label>Buscar Produto</label>
                            <input type="text" id="edit_product_search" class="form-control" placeholder="Digite o nome do produto..." autocomplete="off" oninput="searchProducts('edit')">
                            <div id="edit_product_results" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #ddd;border-radius:8px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                            <input type="hidden" id="edit_selected_product_id">
                            <input type="hidden" id="edit_selected_product_name">
                            <input type="hidden" id="edit_selected_product_price">
                            <input type="hidden" id="edit_selected_product_stock">
                        </div>
                        <div class="form-group">
                            <label>Quantidade</label>
                            <input type="number" id="edit_product_quantity" class="form-control" value="1" min="1">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary" onclick="addProductToOS('edit')" style="width:100%;">
                                <i class="fas fa-plus"></i> Adicionar
                            </button>
                        </div>
                    </div>
                </div>

                <div id="edit_products_list"></div>
                <input type="hidden" id="edit_products_data" name="products_data" value="[]">
            </div>

            <!-- Diagnóstico -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-stethoscope"></i> Diagnóstico
            </h3>
            <div class="form-group">
                <label>Problema Relatado</label>
                <textarea name="reported_problem" id="edit_reported_problem" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>O Aparelho Liga?</label>
                    <select name="device_powers_on" id="edit_device_powers_on" class="form-control">
                        <option value="sim">Sim</option>
                        <option value="nao">Não</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Senha/Padrão do Aparelho</label>
                    <input type="text" name="device_password" id="edit_device_password" class="form-control" placeholder="Digite a senha ou desenhe o padrão">
                </div>
            </div>
            <div class="form-group">
                <label>Laudo Técnico</label>
                <textarea name="technical_report" id="edit_technical_report" class="form-control" rows="3"></textarea>
            </div>

            <!-- Checklist -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-clipboard-check"></i> Checklist do Aparelho
            </h3>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_lens" id="edit_checklist_lens" value="1"> Lente
                </label>
                <div class="form-group" style="margin:0;">
                    <select name="checklist_lens_condition" id="edit_checklist_lens_condition" class="form-control">
                        <option value="">Condição da lente</option>
                        <option value="sem">Sem lente</option>
                        <option value="arranhada">Arranhada</option>
                        <option value="trincada">Trincada</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;grid-column:1/-1;">
                    <input type="text" name="checklist_back_cover" id="edit_checklist_back_cover" class="form-control" placeholder="Tampa traseira (trincada, detalhes...)">
                </div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_screen" id="edit_checklist_screen" value="1"> Tela Trincada
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_sim_card" id="edit_checklist_sim_card" value="1"> Chip
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_face_id" id="edit_checklist_face_id" value="1"> Sem Face ID
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_connector" id="edit_checklist_connector" value="1"> Conector
                </label>
                <div class="form-group" style="margin:0;grid-column:1/-1;">
                    <select name="checklist_camera_front_back" id="edit_checklist_camera_front_back" class="form-control">
                        <option value="">Câmera</option>
                        <option value="frontal">Frontal</option>
                        <option value="traseira">Traseira</option>
                        <option value="ambas">Ambas</option>
                    </select>
                </div>
            </div>

            <!-- Observações -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-comment-alt"></i> Observações
            </h3>
            <div class="form-group">
                <label>Observações para o Cliente</label>
                <textarea name="customer_observations" id="edit_customer_observations" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Observações Internas</label>
                <textarea name="internal_observations" id="edit_internal_observations" class="form-control" rows="2" style="background:#fff9e6;"></textarea>
                <small style="color:#666;"><i class="fas fa-lock"></i> Não serão impressas</small>
            </div>

            <!-- Responsáveis -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-users"></i> Responsáveis
            </h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Técnico Responsável</label>
                    <input type="text" name="technician_name" id="edit_technician_name" class="form-control">
                </div>
                <div class="form-group">
                    <label>Atendente Responsável</label>
                    <input type="text" name="attendant_name" id="edit_attendant_name" class="form-control">
                </div>
            </div>

            <!-- Garantia -->
            <h3 style="margin:25px 0 15px;color:#667eea;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
                <i class="fas fa-shield-alt"></i> Garantia
            </h3>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Período de Garantia</label>
                    <select name="warranty_period" id="edit_warranty_period" class="form-control">
                        <option value="30 dias">30 dias</option>
                        <option value="60 dias">60 dias</option>
                        <option value="90 dias">90 dias (Padrão)</option>
                        <option value="3 meses">3 meses</option>
                        <option value="4 meses">4 meses</option>
                        <option value="6 meses">6 meses</option>
                        <option value="1 ano">1 ano</option>
                        <option value="Sem garantia">Sem garantia</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="button"
                            class="btn btn-secondary"
                            onclick="window.open('../settings/warranty_config.php', '_blank')"
                            style="white-space: nowrap;">
                        <i class="fas fa-cog"></i> Configurar Termos
                    </button>
                </div>
            </div>

            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Atualizar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Desbloquear OS -->
<div id="unlockModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <h2 style="color:#dc3545;margin-bottom:20px;">
            <i class="fas fa-unlock"></i> Desbloquear OS
        </h2>
        <p style="color:#666;margin-bottom:20px;">
            <i class="fas fa-exclamation-triangle"></i> Apenas administradores podem desbloquear ordens de serviço entregues.
        </p>
        <form id="unlockForm" onsubmit="unlockServiceOrder(event)">
            <input type="hidden" id="unlock_os_id" value="">

            <div class="form-group">
                <label>Email do Admin</label>
                <input type="email" id="unlock_username" class="form-control" required placeholder="admin@flreparos.com" autocomplete="off">
            </div>

            <div class="form-group">
                <label>Senha</label>
                <input type="password" id="unlock_password" class="form-control" required placeholder="Digite a senha" autocomplete="off">
            </div>

            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-unlock"></i> Desbloquear
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('unlockModal')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).style.display='block';}
function closeModal(id){document.getElementById(id).style.display='none';}

function toggleInstallments(mode) {
    const paymentSelect = document.getElementById(mode + '_payment_method');
    const installmentsField = document.getElementById(mode + '_installments_field');
    const cashPaymentFields = document.getElementById(mode + '_cash_payment_fields');

    // Mostrar campo de parcelas se cartão de crédito
    if (paymentSelect.value === 'cartao_credito') {
        installmentsField.style.display = 'block';
        if (cashPaymentFields) cashPaymentFields.style.display = 'none';
    }
    // Mostrar campos de valor recebido e troco se dinheiro
    else if (paymentSelect.value === 'dinheiro') {
        installmentsField.style.display = 'none';
        if (cashPaymentFields) cashPaymentFields.style.display = 'flex';
    }
    // Esconder ambos para outras formas
    else {
        installmentsField.style.display = 'none';
        if (cashPaymentFields) cashPaymentFields.style.display = 'none';
    }
}

function calculateChange(mode) {
    const totalCost = parseFloat(document.getElementById(mode + '_total_cost').value) || 0;
    const amountReceived = parseFloat(document.getElementById(mode + '_amount_received').value) || 0;
    const changeAmount = document.getElementById(mode + '_change_amount');

    if (amountReceived >= totalCost && totalCost > 0) {
        const change = amountReceived - totalCost;
        changeAmount.value = change.toFixed(2);
    } else {
        changeAmount.value = '0.00';
    }
}

// Gerenciamento de produtos na OS
let createProducts = [];
let editProducts = [];

function addProductToOS(mode) {
    const productId = document.getElementById(mode + '_selected_product_id').value;
    const productName = document.getElementById(mode + '_selected_product_name').value;
    const price = parseFloat(document.getElementById(mode + '_selected_product_price').value);
    const stock = parseInt(document.getElementById(mode + '_selected_product_stock').value);
    const quantityInput = document.getElementById(mode + '_product_quantity');
    const quantity = parseInt(quantityInput.value);

    if (!productId) {
        alert('Selecione um produto');
        return;
    }

    if (quantity > stock) {
        alert(`Quantidade indisponível! Estoque: ${stock}`);
        return;
    }

    const product = {
        id: productId,
        name: productName,
        price: price,
        quantity: quantity,
        subtotal: price * quantity
    };

    if (mode === 'create') {
        createProducts.push(product);
    } else {
        editProducts.push(product);
    }

    updateProductsList(mode);

    // Limpar seleção
    document.getElementById(mode + '_product_search').value = '';
    document.getElementById(mode + '_selected_product_id').value = '';
    document.getElementById(mode + '_selected_product_name').value = '';
    document.getElementById(mode + '_selected_product_price').value = '';
    document.getElementById(mode + '_selected_product_stock').value = '';
    quantityInput.value = 1;
}

function removeProductFromOS(mode, index) {
    if (mode === 'create') {
        createProducts.splice(index, 1);
    } else {
        editProducts.splice(index, 1);
    }
    updateProductsList(mode);
}

function updateProductsList(mode) {
    const products = mode === 'create' ? createProducts : editProducts;
    const listDiv = document.getElementById(mode + '_products_list');
    const dataInput = document.getElementById(mode + '_products_data');

    if (products.length === 0) {
        listDiv.innerHTML = '<p style="color:#999;text-align:center;padding:20px;">Nenhum produto adicionado</p>';
        dataInput.value = '[]';
        return;
    }

    let total = 0;
    let html = '<table class="table" style="margin-top:10px;">';
    html += '<thead><tr><th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Subtotal</th><th>Ações</th></tr></thead><tbody>';

    products.forEach((p, index) => {
        total += p.subtotal;
        html += `<tr>
            <td>${p.name}</td>
            <td>${p.quantity}</td>
            <td>R$ ${p.price.toFixed(2).replace('.', ',')}</td>
            <td>R$ ${p.subtotal.toFixed(2).replace('.', ',')}</td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeProductFromOS('${mode}', ${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
    });

    html += '</tbody></table>';
    html += `<div style="text-align:right;font-weight:bold;font-size:16px;color:#667eea;margin-top:10px;">
        Total em Produtos: R$ ${total.toFixed(2).replace('.', ',')}
    </div>`;

    listDiv.innerHTML = html;
    dataInput.value = JSON.stringify(products);
}

function openEditModal(o){
    // Dados básicos
    document.getElementById('edit_id').value=o.id;
    document.getElementById('edit_customer_id').value=o.customer_id||'';
    document.getElementById('edit_device').value=o.device||'';
    document.getElementById('edit_status').value=o.status||'open';
    document.getElementById('edit_total_cost').value=o.total_cost||'';
    document.getElementById('edit_payment_method').value=o.payment_method||'';
    document.getElementById('edit_installments').value=o.installments||1;

    // Calcular valor recebido a partir do troco (se houver)
    const totalCost = parseFloat(o.total_cost) || 0;
    const changeAmount = parseFloat(o.change_amount) || 0;
    const amountReceived = totalCost + changeAmount;

    document.getElementById('edit_change_amount').value=o.change_amount||0;
    document.getElementById('edit_amount_received').value=amountReceived.toFixed(2);

    // Mostrar campo de parcelas/troco conforme forma de pagamento
    toggleInstallments('edit');

    // Diagnóstico
    document.getElementById('edit_reported_problem').value=o.reported_problem||'';
    document.getElementById('edit_device_powers_on').value=o.device_powers_on||'sim';
    document.getElementById('edit_device_password').value=o.device_password||'';
    document.getElementById('edit_technical_report').value=o.technical_report||'';

    // Checklist
    document.getElementById('edit_checklist_lens').checked=o.checklist_lens==1;
    document.getElementById('edit_checklist_lens_condition').value=o.checklist_lens_condition||'';
    document.getElementById('edit_checklist_back_cover').value=o.checklist_back_cover||'';
    document.getElementById('edit_checklist_screen').checked=o.checklist_screen==1;
    document.getElementById('edit_checklist_connector').checked=o.checklist_connector==1;
    document.getElementById('edit_checklist_camera_front_back').value=o.checklist_camera_front_back||'';
    document.getElementById('edit_checklist_face_id').checked=o.checklist_face_id==1;
    document.getElementById('edit_checklist_sim_card').checked=o.checklist_sim_card==1;

    // Observações
    document.getElementById('edit_customer_observations').value=o.customer_observations||'';
    document.getElementById('edit_internal_observations').value=o.internal_observations||'';

    // Responsáveis
    document.getElementById('edit_technician_name').value=o.technician_name||'';
    document.getElementById('edit_attendant_name').value=o.attendant_name||'';

    // Garantia
    document.getElementById('edit_warranty_period').value=o.warranty_period||'90 dias';

    // Limpar produtos e carregar produtos da OS
    editProducts = [];
    loadOSProducts(o.id);

    openModal('editModal');
}

// Carregar produtos de uma OS existente
async function loadOSProducts(osId) {
    try {
        const response = await fetch(`get_os_products.php?os_id=${osId}`);
        const data = await response.json();

        if (data.success && data.products.length > 0) {
            editProducts = data.products.map(p => ({
                id: p.product_id,
                name: p.product_name,
                price: parseFloat(p.price),
                quantity: parseInt(p.quantity),
                subtotal: parseFloat(p.subtotal)
            }));
            updateProductsList('edit');
        } else {
            updateProductsList('edit');
        }
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        updateProductsList('edit');
    }
}

function openUnlockModal(osId) {
    document.getElementById('unlock_os_id').value = osId;
    document.getElementById('unlock_username').value = '';
    document.getElementById('unlock_password').value = '';
    openModal('unlockModal');
}

async function unlockServiceOrder(event) {
    event.preventDefault();

    const osId = document.getElementById('unlock_os_id').value;
    const username = document.getElementById('unlock_username').value;
    const password = document.getElementById('unlock_password').value;

    const formData = new FormData();
    formData.append('action', 'unlock');
    formData.append('os_id', osId);
    formData.append('username', username);
    formData.append('password', password);

    try {
        const response = await fetch('index.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ ' + data.message);
            closeModal('unlockModal');
            window.location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (error) {
        alert('❌ Erro ao desbloquear OS: ' + error.message);
    }
}

function printServiceOrder(order) {
    // Buscar dados do cliente e termos de garantia
    const customerId = order.customer_id;

    Promise.all([
        fetch(`../../modules/customers/get_customer.php?id=${customerId}`).then(r => r.json()),
        fetch('get_warranty_terms.php').then(r => r.json())
    ])
    .then(([customer, warrantyTerms]) => {
        generateServiceOrderPrint(order, customer, warrantyTerms);
    })
    .catch(error => {
        console.error('Erro ao buscar dados:', error);
        alert('Erro ao carregar dados para impressão');
    });
}

function generateServiceOrderPrint(order, customer, warrantyTerms) {
    const printWindow = window.open('', '_blank');

    // Traduzir status
    const statusMap = {
        'open': 'Aguardando',
        'in_progress': 'Em Andamento',
        'completed': 'Concluído',
        'delivered': 'Entregue',
        'cancelled': 'Cancelado'
    };

    // Traduzir forma de pagamento
    const paymentMap = {
        'dinheiro': 'Dinheiro',
        'pix': 'PIX',
        'cartao_credito': 'Cartão de Crédito',
        'cartao_debito': 'Cartão de Débito'
    };

    // Formatar valor
    const totalFormatted = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(order.total_cost || 0);

    // Formatar data
    const dateFormatted = new Date(order.entry_datetime).toLocaleDateString('pt-BR');

    // Substituir [PERIODO_GARANTIA] no texto da garantia
    const warrantyPeriod = order.warranty_period || '90 dias';

    // Conteúdo de uma via (será duplicado)
    const viaContent = `
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <div class="logo">
                    <i class="fas fa-mobile-alt"></i> FL REPAROS
                </div>
                <div class="subtitle">Sistema de Gestão para Assistência Técnica</div>
                <div class="os-number">OS #${order.id}</div>
                <span class="status-badge status-${order.status}">${statusMap[order.status] || order.status}</span>
            </div>
            <div class="password-box">
                <div class="password-label">Senha/Padrão</div>
                <div class="password-text">${order.device_password || ''}</div>
                <div class="pattern-grid">
                    <div class="pattern-dot"></div>
                    <div class="pattern-dot"></div>
                    <div class="pattern-dot"></div>
                    <div class="pattern-dot"></div>
                    <div class="pattern-dot"></div>
                    <div class="pattern-dot"></div>
                    <div class="pattern-dot"></div>
                    <div class="pattern-dot"></div>
                    <div class="pattern-dot"></div>
                </div>
                <div class="password-footer">Desenhe o Padrão:</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="fas fa-user"></i> Cliente</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Nome</div>
                <div class="info-value">${customer.name || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Telefone</div>
                <div class="info-value">${customer.phone || '-'}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="fas fa-mobile-alt"></i> Equipamento</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Aparelho</div>
                <div class="info-value">${order.device || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Data de Entrada</div>
                <div class="info-value">${dateFormatted}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Liga?</div>
                <div class="info-value">${order.device_powers_on === 'sim' ? 'Sim' : 'Não'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Valor Total</div>
                <div class="info-value" style="color: #28a745; font-weight: bold;">${totalFormatted}</div>
            </div>
            ${order.payment_method ? `
            <div class="info-item">
                <div class="info-label">Forma de Pagamento</div>
                <div class="info-value">${paymentMap[order.payment_method] || order.payment_method}</div>
            </div>
            ` : ''}
            ${order.payment_method === 'cartao_credito' && order.installments > 1 ? `
            <div class="info-item">
                <div class="info-label">Parcelamento</div>
                <div class="info-value">${order.installments}x de R$ ${(order.total_cost / order.installments).toFixed(2).replace('.', ',')}</div>
            </div>
            ` : ''}
            ${order.payment_method === 'dinheiro' && order.change_amount > 0 ? `
            <div class="info-item">
                <div class="info-label">Valor Recebido</div>
                <div class="info-value">R$ ${(parseFloat(order.total_cost) + parseFloat(order.change_amount)).toFixed(2).replace('.', ',')}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Troco</div>
                <div class="info-value">R$ ${parseFloat(order.change_amount).toFixed(2).replace('.', ',')}</div>
            </div>
            ` : ''}
        </div>
    </div>

    ${order.reported_problem ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-exclamation-circle"></i> Problema</div>
        <div class="text-area">${order.reported_problem}</div>
    </div>
    ` : ''}

    ${order.technical_report ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-wrench"></i> Laudo</div>
        <div class="text-area">${order.technical_report}</div>
    </div>
    ` : ''}

    ${(order.checklist_lens || order.checklist_lens_condition || order.checklist_back_cover || order.checklist_screen || order.checklist_connector || order.checklist_camera_front_back || order.checklist_face_id || order.checklist_sim_card) ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-clipboard-check"></i> Checklist</div>
        <div class="checklist">
            ${order.checklist_lens ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Possui Lente' + (order.checklist_lens_condition ? ': ' + order.checklist_lens_condition : '') + '</div>' : ''}
            ${order.checklist_back_cover ? '<div class="checklist-item"><i class="fas fa-info-circle"></i> Tampa: ' + order.checklist_back_cover + '</div>' : ''}
            ${order.checklist_screen ? '<div class="checklist-item"><i class="fas fa-exclamation-triangle"></i> Tela Trincada</div>' : ''}
            ${order.checklist_sim_card ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Possui Chip</div>' : ''}
            ${order.checklist_face_id ? '<div class="checklist-item"><i class="fas fa-times-circle"></i> Sem Face ID</div>' : ''}
            ${order.checklist_connector ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Conector</div>' : ''}
            ${order.checklist_camera_front_back ? '<div class="checklist-item"><i class="fas fa-camera"></i> Câmera: ' + order.checklist_camera_front_back + '</div>' : ''}
        </div>
    </div>
    ` : ''}

    ${order.customer_observations ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-comment-alt"></i> Observações</div>
        <div class="text-area">${order.customer_observations}</div>
    </div>
    ` : ''}

    ${order.technician_name || order.attendant_name ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-users"></i> Responsáveis</div>
        <div class="info-grid">
            ${order.technician_name ? `
            <div class="info-item">
                <div class="info-label">Técnico</div>
                <div class="info-value">${order.technician_name}</div>
            </div>
            ` : ''}
            ${order.attendant_name ? `
            <div class="info-item">
                <div class="info-label">Atendente</div>
                <div class="info-value">${order.attendant_name}</div>
            </div>
            ` : ''}
        </div>
    </div>
    ` : ''}

    ${warrantyPeriod !== 'Sem garantia' ? `
    <div class="warranty-section">
        <div class="warranty-title">⚠️ ${warrantyTerms.title}</div>
        ${warrantyTerms.clauses.map(clause => `
        <div class="warranty-clause">
            <strong>${clause.number}.</strong> ${clause.text.replace('[PERIODO_GARANTIA]', warrantyPeriod)}
        </div>
        `).join('')}
        <div class="warranty-footer">
            ${warrantyTerms.footer}
        </div>
    </div>
    ` : ''}

    <div class="signature-area">
        <div class="signature-line">
            Assinatura do Cliente
        </div>
    </div>

    <div class="footer">
        <p><strong>FL REPAROS</strong></p>
        <p>${new Date().toLocaleDateString('pt-BR')} ${new Date().toLocaleTimeString('pt-BR')}</p>
    </div>
`;

    const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ordem de Serviço #${order.id}</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 5mm;
            }
            body { margin: 0; padding: 0; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            padding: 0;
            background: white;
            height: 100vh;
        }
        .container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            height: 100%;
            gap: 6px;
        }
        .via {
            border: 2px solid #667eea;
            padding: 6px;
            font-size: 10px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        @media print {
            .container {
                page-break-after: avoid;
                height: 100vh;
            }
            .via {
                page-break-inside: avoid;
                height: 100%;
            }
        }
        .header {
            margin-bottom: 5px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 4px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }
        .header-left {
            flex: 1;
            text-align: center;
        }
        .logo {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 2px;
        }
        .logo i {
            margin-right: 4px;
        }
        .subtitle {
            color: #666;
            font-size: 8px;
        }
        .os-number {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin: 3px 0;
        }
        .password-box {
            border: 2px solid #333;
            border-radius: 5px;
            padding: 6px;
            background: white;
            min-width: 90px;
            max-width: 90px;
        }
        .password-label {
            font-size: 8px;
            font-weight: bold;
            color: #333;
            text-align: center;
            margin-bottom: 3px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2px;
        }
        .password-text {
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            color: #333;
            min-height: 14px;
            margin-bottom: 4px;
            word-break: break-all;
        }
        .pattern-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 8px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-bottom: 3px;
        }
        .pattern-dot {
            width: 10px;
            height: 10px;
            border: 2px solid #333;
            border-radius: 50%;
            background: white;
            margin: auto;
        }
        .password-footer {
            font-size: 6px;
            color: #666;
            text-align: center;
            font-style: italic;
        }
        .section {
            margin-bottom: 5px;
        }
        .section-title {
            background: #667eea;
            color: white;
            padding: 3px 6px;
            font-weight: bold;
            margin-bottom: 3px;
            border-radius: 3px;
            font-size: 9px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 4px;
        }
        .info-grid.single-column {
            grid-template-columns: 1fr;
        }
        .info-item {
            padding: 3px 5px;
            background: #f5f5f5;
            border-left: 3px solid #667eea;
        }
        .info-label {
            font-size: 7.5px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 1px;
            font-weight: 600;
        }
        .info-value {
            font-size: 10px;
            color: #333;
            font-weight: 600;
            line-height: 1.3;
        }
        .checklist {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3px;
            margin-top: 4px;
        }
        .checklist-item {
            padding: 3px 5px;
            background: #f9f9f9;
            border-radius: 3px;
            display: flex;
            align-items: center;
            font-size: 8.5px;
        }
        .checklist-item i {
            margin-right: 3px;
            color: #28a745;
            font-size: 8.5px;
        }
        .text-area {
            background: #f9f9f9;
            padding: 5px;
            border-radius: 3px;
            min-height: 25px;
            margin-top: 4px;
            white-space: pre-wrap;
            font-size: 8.5px;
            line-height: 1.4;
        }
        .warranty-section {
            margin-top: 6px;
            padding: 5px;
            background: #fffbf0;
            border: 1.5px solid #ffc107;
            border-radius: 3px;
        }
        .warranty-title {
            font-size: 9px;
            font-weight: bold;
            color: #856404;
            text-align: center;
            margin-bottom: 3px;
        }
        .warranty-clause {
            font-size: 7px;
            line-height: 1.5;
            margin-bottom: 2px;
            text-align: justify;
        }
        .warranty-footer {
            font-size: 7px;
            text-align: center;
            margin-top: 3px;
            font-style: italic;
            color: #856404;
        }
        .signature-area {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px dashed #ccc;
        }
        .signature-line {
            border-top: 1.5px solid #333;
            margin: 8px 10px 2px;
            padding-top: 2px;
            text-align: center;
            color: #666;
            font-size: 8px;
        }
        .footer {
            text-align: center;
            margin-top: 5px;
            padding-top: 3px;
            border-top: 1.5px solid #667eea;
            color: #666;
            font-size: 7px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 8.5px;
            font-weight: bold;
        }
        .via-label {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .status-open { background: #ffc107; color: #000; }
        .status-in_progress { background: #17a2b8; color: white; }
        .status-completed { background: #28a745; color: white; }
        .status-delivered { background: #6c757d; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <!-- VIA 1 - CLIENTE -->
        <div class="via">
            <div class="via-label">📋 VIA DO CLIENTE</div>
            ${viaContent}
        </div>

        <!-- VIA 2 - LOJA -->
        <div class="via">
            <div class="via-label">🏪 VIA DA LOJA</div>
            ${viaContent}
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>
    `;

    printWindow.document.write(htmlContent);
    printWindow.document.close();

    // Aguardar carregar e imprimir
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
        }, 250);
    };
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Atalho ESC para fechar modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('createModal');
        closeModal('editModal');
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
