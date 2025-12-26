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

// Criar Conta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    try {
        $stmt = $conn->prepare("INSERT INTO accounts_receivable 
            (customer_id, description, amount, due_date, payment_date, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['description'],
            $_POST['amount'],
            $_POST['due_date'],
            !empty($_POST['payment_date']) ? $_POST['payment_date'] : null,
            $_POST['status'] ?? 'pending'
        ]);
        echo "<script>alert('Conta cadastrada com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao cadastrar: " . $e->getMessage() . "');</script>";
    }
}

// Editar Conta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    try {
        $stmt = $conn->prepare("UPDATE accounts_receivable 
            SET customer_id=?, description=?, amount=?, due_date=?, payment_date=?, status=?, updated_at=NOW() 
            WHERE id=?");
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['description'],
            $_POST['amount'],
            $_POST['due_date'],
            !empty($_POST['payment_date']) ? $_POST['payment_date'] : null,
            $_POST['status'],
            $_POST['id']
        ]);
        echo "<script>alert('Conta atualizada com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao atualizar: " . $e->getMessage() . "');</script>";
    }
}

// Excluir Conta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    $id = (int) $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM accounts_receivable WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>alert('Conta excluída com sucesso!');window.location='index.php';</script>";
    exit;
}

// =============================
// 🔍 LISTAGEM E FILTRO
// =============================
$search = $_GET['search'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (c.name LIKE ? OR a.description LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

$stmt = $conn->prepare("
    SELECT a.*, c.name AS customer_name 
    FROM accounts_receivable a 
    LEFT JOIN customers c ON a.customer_id = c.id 
    $where
    ORDER BY a.id DESC
");
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$clients = $conn->query("SELECT id, name FROM customers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// =============================
// 🎨 STATUS
// =============================
function traduzirStatus($status) {
    $map = [
        'pending' => 'Pendente',
        'paid' => 'Pago',
        'overdue' => 'Atrasado'
    ];
    return $map[$status] ?? ucfirst($status);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contas a Receber - FL Reparos</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body {
    margin:0;
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:linear-gradient(135deg,#667eea,#764ba2);
    padding:20px;
    min-height:100vh;
}
.container {max-width:1300px;margin:auto;}
.header, .search-box {
    background:rgba(255,255,255,0.9);
    backdrop-filter:blur(10px);
    padding:20px;
    border-radius:15px;
    box-shadow:0 8px 32px rgba(0,0,0,0.15);
    margin-bottom:25px;
}
.header {display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;}
.header h1 {font-size:1.8rem;display:flex;align-items:center;gap:10px;}
.btn {
    border:none;border-radius:8px;padding:10px 20px;color:#fff;font-weight:500;
    cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:all .3s;
}
.btn-primary {background:linear-gradient(45deg,#4CAF50,#45a049);}
.btn-secondary {background:linear-gradient(45deg,#2196F3,#1976D2);}
.btn-danger {background:linear-gradient(45deg,#f44336,#d32f2f);}
.btn:hover {transform:translateY(-2px);}
.table {
    width:100%;border-collapse:collapse;background:rgba(255,255,255,0.95);
    border-radius:15px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.1);
}
th,td {padding:12px;text-align:left;}
th {background:linear-gradient(45deg,#f8f9fa,#e9ecef);}
tr:hover {background:rgba(103,58,183,0.1);}
.badge {
    padding:5px 10px;border-radius:8px;color:white;font-weight:bold;text-transform:capitalize;
}
.pending {background:#FFC107;color:#333;}
.paid {background:#4CAF50;}
.overdue {background:#F44336;}
.modal {
    display:none;position:fixed;top:0;left:0;width:100%;height:100%;
    background:rgba(0,0,0,0.6);z-index:1000;backdrop-filter:blur(5px);
}
.modal-content {
    background:rgba(255,255,255,0.95);margin:60px auto;padding:25px;
    border-radius:15px;max-width:600px;box-shadow:0 8px 32px rgba(0,0,0,0.2);
}
.modal-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.close {
    background:#f44336;border:none;color:#fff;border-radius:50%;
    width:35px;height:35px;cursor:pointer;font-size:18px;
}
.form-row {display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;}
.form-group {flex:1;min-width:200px;}
.form-control {width:100%;padding:10px;border:2px solid #ccc;border-radius:8px;}
.form-control:focus {border-color:#667eea;outline:none;}
.empty {text-align:center;padding:40px;color:#777;}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-money-bill-wave"></i> Contas a Receber</h1>
        <div>
            <button class="btn btn-primary" onclick="openModal('createModal')"><i class="fas fa-plus"></i> Nova Conta</button>
            <a href="../../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <div class="search-box">
        <form method="GET">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Buscar por cliente ou descrição...">
            <button type="submit" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-search"></i> Buscar</button>
        </form>
    </div>

    <table class="table">
        <thead>
            <tr><th>ID</th><th>Cliente</th><th>Descrição</th><th>Valor (R$)</th><th>Vencimento</th><th>Status</th><th>Ações</th></tr>
        </thead>
        <tbody>
            <?php if(empty($accounts)): ?>
                <tr><td colspan="7" class="empty">Nenhuma conta cadastrada.</td></tr>
            <?php else: foreach($accounts as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['customer_name'] ?? 'Não informado') ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td>R$ <?= number_format($row['amount'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($row['due_date']) ?></td>
                <td><span class="badge <?= $row['status'] ?>"><?= traduzirStatus($row['status']) ?></span></td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($row) ?>)'><i class="fas fa-edit"></i></button>
                    <form method="POST" onsubmit="return confirm('Excluir esta conta?');" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash-alt"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- MODAL NOVA -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> Nova Conta</h2>
            <button class="close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente</label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">Selecione</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Descrição</label>
                    <input type="text" name="description" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Valor (R$)</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Vencimento</label>
                    <input type="date" name="due_date" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Data do Pagamento</label>
                    <input type="date" name="payment_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="pending">Pendente</option>
                        <option value="paid">Pago</option>
                        <option value="overdue">Atrasado</option>
                    </select>
                </div>
            </div>
            <div style="text-align:right;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Editar Conta</h2>
            <button class="close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente</label>
                    <select name="customer_id" id="edit_customer" class="form-control" required>
                        <option value="">Selecione</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Descrição</label>
                    <input type="text" name="description" id="edit_description" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Valor (R$)</label>
                    <input type="number" step="0.01" name="amount" id="edit_amount" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Vencimento</label>
                    <input type="date" name="due_date" id="edit_due_date" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Data do Pagamento</label>
                    <input type="date" name="payment_date" id="edit_payment_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="pending">Pendente</option>
                        <option value="paid">Pago</option>
                        <option value="overdue">Atrasado</option>
                    </select>
                </div>
            </div>
            <div style="text-align:right;">
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
    document.getElementById('edit_customer').value=o.customer_id||'';
    document.getElementById('edit_description').value=o.description||'';
    document.getElementById('edit_amount').value=o.amount||'';
    document.getElementById('edit_due_date').value=o.due_date||'';
    document.getElementById('edit_payment_date').value=o.payment_date||'';
    document.getElementById('edit_status').value=o.status||'pending';
    openModal('editModal');
}
</script>

</body>
</html>
