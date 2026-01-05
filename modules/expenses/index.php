<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once('../../config/database.php');
$conn = $database->getConnection();

// =============================
// 🔧 CRUD
// =============================

// Criar Despesa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO expenses
            (description, type, amount, expense_date, supplier_id, payment_method, status, observations, user_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $_POST['description'],
            $_POST['type'],
            $_POST['amount'],
            $_POST['expense_date'],
            !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null,
            $_POST['payment_method'],
            $_POST['status'] ?? 'pendente',
            $_POST['observations'] ?? null,
            $_SESSION['user_id']
        ]);

        $expenseId = $conn->lastInsertId();

        // Se status é 'pago', registrar no caixa
        if (($_POST['status'] ?? 'pendente') === 'pago') {
            $stmt = $conn->prepare("INSERT INTO cash_flow
                (user_id, type, description, amount, reference_id, reference_type, created_at)
                VALUES (?, 'expense', ?, ?, ?, 'expense', NOW())");
            $stmt->execute([
                $_SESSION['user_id'],
                'Despesa: ' . $_POST['description'],
                $_POST['amount'],
                $expenseId
            ]);
        }

        $conn->commit();
        echo "<script>alert('Despesa cadastrada com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<script>alert('Erro ao cadastrar: " . $e->getMessage() . "');</script>";
    }
}

// Editar Despesa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    try {
        $conn->beginTransaction();

        // Buscar status anterior
        $stmt = $conn->prepare("SELECT status, amount, description FROM expenses WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $oldExpense = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldStatus = $oldExpense['status'];
        $newStatus = $_POST['status'];

        // Atualizar despesa
        $stmt = $conn->prepare("UPDATE expenses
            SET description=?, type=?, amount=?, expense_date=?, supplier_id=?, payment_method=?, status=?, observations=?, updated_at=NOW()
            WHERE id=?");
        $stmt->execute([
            $_POST['description'],
            $_POST['type'],
            $_POST['amount'],
            $_POST['expense_date'],
            !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null,
            $_POST['payment_method'],
            $newStatus,
            $_POST['observations'] ?? null,
            $_POST['id']
        ]);

        // Gerenciar registro no caixa
        // Caso 1: Status mudou de NÃO pago para PAGO → adicionar no caixa
        if ($oldStatus !== 'pago' && $newStatus === 'pago') {
            $stmt = $conn->prepare("INSERT INTO cash_flow
                (user_id, type, description, amount, reference_id, reference_type, created_at)
                VALUES (?, 'expense', ?, ?, ?, 'expense', NOW())");
            $stmt->execute([
                $_SESSION['user_id'],
                'Despesa: ' . $_POST['description'],
                $_POST['amount'],
                $_POST['id']
            ]);
        }
        // Caso 2: Status mudou de PAGO para NÃO pago → remover do caixa
        elseif ($oldStatus === 'pago' && $newStatus !== 'pago') {
            $stmt = $conn->prepare("DELETE FROM cash_flow WHERE reference_id = ? AND reference_type = 'expense'");
            $stmt->execute([$_POST['id']]);
        }
        // Caso 3: Status continua PAGO mas valor/descrição mudou → atualizar registro no caixa
        elseif ($oldStatus === 'pago' && $newStatus === 'pago') {
            $stmt = $conn->prepare("UPDATE cash_flow
                SET description = ?, amount = ?
                WHERE reference_id = ? AND reference_type = 'expense'");
            $stmt->execute([
                'Despesa: ' . $_POST['description'],
                $_POST['amount'],
                $_POST['id']
            ]);
        }

        $conn->commit();
        echo "<script>alert('Despesa atualizada com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<script>alert('Erro ao atualizar: " . $e->getMessage() . "');</script>";
    }
}

// Excluir Despesa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    $id = (int) $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>alert('Despesa excluída com sucesso!');window.location='index.php';</script>";
    exit;
}

// =============================
// 🔍 LISTAGEM E FILTRO
// =============================
$search = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (e.description LIKE ? OR e.observations LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_type)) {
    $where .= " AND e.type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_status)) {
    $where .= " AND e.status = ?";
    $params[] = $filter_status;
}

$stmt = $conn->prepare("
    SELECT e.*, u.name AS user_name
    FROM expenses e
    LEFT JOIN users u ON e.user_id = u.id
    $where
    ORDER BY e.expense_date DESC, e.id DESC
");
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar usuários/fornecedores
$suppliers = $conn->query("SELECT id, name FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// =============================
// 📊 ESTATÍSTICAS
// =============================
$total_pago = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE status = 'pago'")->fetch(PDO::FETCH_ASSOC)['total'];
$total_pendente = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE status = 'pendente'")->fetch(PDO::FETCH_ASSOC)['total'];
$total_mes = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE MONTH(expense_date) = MONTH(CURRENT_DATE) AND YEAR(expense_date) = YEAR(CURRENT_DATE)")->fetch(PDO::FETCH_ASSOC)['total'];

// =============================
// 🎨 FUNÇÕES AUXILIARES
// =============================
function traduzirTipo($type) {
    $map = [
        'fixa' => 'Fixa',
        'variavel' => 'Variável',
        'fornecedor' => 'Fornecedor'
    ];
    return $map[$type] ?? ucfirst($type);
}

function traduzirStatus($status) {
    $map = [
        'pago' => 'Pago',
        'pendente' => 'Pendente'
    ];
    return $map[$status] ?? ucfirst($status);
}

function traduzirPagamento($method) {
    $map = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'PIX',
        'cartao' => 'Cartão',
        'boleto' => 'Boleto'
    ];
    return $map[$method] ?? ucfirst($method);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Despesas - FL Reparos</title>
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
    grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
    gap:20px;
}
.stat-card {
    background:linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
    padding:20px;
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,0.1);
    text-align:center;
}
.stat-card .icon {
    font-size:2.5rem;
    margin-bottom:10px;
}
.stat-card .value {
    font-size:1.8rem;
    font-weight:bold;
    margin:5px 0;
}
.stat-card .label {
    color:#666;
    font-size:0.9rem;
    text-transform:uppercase;
}
.stat-card.green .icon {color:#4CAF50;}
.stat-card.orange .icon {color:#FF9800;}
.stat-card.blue .icon {color:#2196F3;}

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
.pendente {background:#FF9800;color:#333;}
.pago {background:#4CAF50;}
.fixa {background:#2196F3;}
.variavel {background:#9C27B0;}
.fornecedor {background:#FF5722;}

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
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-receipt"></i> Despesas</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="openModal('createModal')"><i class="fas fa-plus"></i> Nova Despesa</button>
            <a href="../../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <div class="stats-box">
        <div class="stats-grid">
            <div class="stat-card green">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="value">R$ <?= number_format($total_pago, 2, ',', '.') ?></div>
                <div class="label">Total Pago</div>
            </div>
            <div class="stat-card orange">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="value">R$ <?= number_format($total_pendente, 2, ',', '.') ?></div>
                <div class="label">Total Pendente</div>
            </div>
            <div class="stat-card blue">
                <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="value">R$ <?= number_format($total_mes, 2, ',', '.') ?></div>
                <div class="label">Total do Mês</div>
            </div>
        </div>
    </div>

    <div class="search-box">
        <form method="GET">
            <div class="filter-box">
                <div class="form-group">
                    <label>Buscar</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Descrição ou observações...">
                </div>
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="type" class="form-control">
                        <option value="">Todos</option>
                        <option value="fixa" <?= $filter_type === 'fixa' ? 'selected' : '' ?>>Fixa</option>
                        <option value="variavel" <?= $filter_type === 'variavel' ? 'selected' : '' ?>>Variável</option>
                        <option value="fornecedor" <?= $filter_type === 'fornecedor' ? 'selected' : '' ?>>Fornecedor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">Todos</option>
                        <option value="pago" <?= $filter_status === 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="pendente" <?= $filter_status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-search"></i> Filtrar</button>
                </div>
            </div>
        </form>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Descrição</th>
                <th>Tipo</th>
                <th>Valor (R$)</th>
                <th>Data</th>
                <th>Pagamento</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($expenses)): ?>
                <tr><td colspan="8" class="empty">Nenhuma despesa cadastrada.</td></tr>
            <?php else: foreach($expenses as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td>
                    <strong><?= htmlspecialchars($row['description']) ?></strong>
                    <?php if(!empty($row['observations'])): ?>
                        <br><small style="color:#666;"><?= htmlspecialchars($row['observations']) ?></small>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= $row['type'] ?>"><?= traduzirTipo($row['type']) ?></span></td>
                <td><strong>R$ <?= number_format($row['amount'], 2, ',', '.') ?></strong></td>
                <td><?= date('d/m/Y', strtotime($row['expense_date'])) ?></td>
                <td><?= traduzirPagamento($row['payment_method']) ?></td>
                <td><span class="badge <?= $row['status'] ?>"><?= traduzirStatus($row['status']) ?></span></td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($row) ?>)' title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" onsubmit="return confirm('Excluir esta despesa?');" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Excluir"><i class="fas fa-trash-alt"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- MODAL NOVA DESPESA -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> Nova Despesa</h2>
            <button class="close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Descrição *</label>
                    <input type="text" name="description" class="form-control" required placeholder="Ex: Conta de luz">
                </div>
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="type" class="form-control" required>
                        <option value="">Selecione</option>
                        <option value="fixa">Fixa</option>
                        <option value="variavel">Variável</option>
                        <option value="fornecedor">Fornecedor</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Valor (R$) *</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0,00">
                </div>
                <div class="form-group">
                    <label>Data da Despesa *</label>
                    <input type="date" name="expense_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Forma de Pagamento *</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="">Selecione</option>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão</option>
                        <option value="boleto">Boleto</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" class="form-control">
                        <option value="pendente">Pendente</option>
                        <option value="pago">Pago</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Fornecedor/Usuário (opcional)</label>
                    <select name="supplier_id" class="form-control">
                        <option value="">Selecione</option>
                        <?php foreach($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1 1 100%;">
                    <label>Observações</label>
                    <textarea name="observations" class="form-control" rows="3" placeholder="Informações adicionais..."></textarea>
                </div>
            </div>
            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR DESPESA -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Editar Despesa</h2>
            <button class="close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Descrição *</label>
                    <input type="text" name="description" id="edit_description" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="type" id="edit_type" class="form-control" required>
                        <option value="fixa">Fixa</option>
                        <option value="variavel">Variável</option>
                        <option value="fornecedor">Fornecedor</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Valor (R$) *</label>
                    <input type="number" step="0.01" name="amount" id="edit_amount" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Data da Despesa *</label>
                    <input type="date" name="expense_date" id="edit_expense_date" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Forma de Pagamento *</label>
                    <select name="payment_method" id="edit_payment_method" class="form-control" required>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão</option>
                        <option value="boleto">Boleto</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="pendente">Pendente</option>
                        <option value="pago">Pago</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Fornecedor/Usuário (opcional)</label>
                    <select name="supplier_id" id="edit_supplier_id" class="form-control">
                        <option value="">Selecione</option>
                        <?php foreach($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1 1 100%;">
                    <label>Observações</label>
                    <textarea name="observations" id="edit_observations" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Atualizar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).style.display='block';}
function closeModal(id){document.getElementById(id).style.display='none';}

function openEditModal(o){
    document.getElementById('edit_id').value=o.id;
    document.getElementById('edit_description').value=o.description||'';
    document.getElementById('edit_type').value=o.type||'';
    document.getElementById('edit_amount').value=o.amount||'';
    document.getElementById('edit_expense_date').value=o.expense_date||'';
    document.getElementById('edit_payment_method').value=o.payment_method||'';
    document.getElementById('edit_status').value=o.status||'pendente';
    document.getElementById('edit_supplier_id').value=o.supplier_id||'';
    document.getElementById('edit_observations').value=o.observations||'';
    openModal('editModal');
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
</script>

</body>
</html>
