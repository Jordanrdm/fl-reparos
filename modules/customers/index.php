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

// Criar Cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    try {
        $stmt = $conn->prepare("INSERT INTO customers
            (name, cpf_cnpj, phone, email, address, city, state, zipcode, notes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $_POST['name'],
            $_POST['cpf_cnpj'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['email'] ?? null,
            $_POST['address'] ?? null,
            $_POST['city'] ?? null,
            $_POST['state'] ?? null,
            $_POST['zipcode'] ?? null,
            $_POST['notes'] ?? null
        ]);
        echo "<script>alert('Cliente cadastrado com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao cadastrar: " . $e->getMessage() . "');</script>";
    }
}

// Editar Cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    try {
        $stmt = $conn->prepare("UPDATE customers
            SET name=?, cpf_cnpj=?, phone=?, email=?, address=?, city=?, state=?, zipcode=?, notes=?, updated_at=NOW()
            WHERE id=?");
        $stmt->execute([
            $_POST['name'],
            $_POST['cpf_cnpj'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['email'] ?? null,
            $_POST['address'] ?? null,
            $_POST['city'] ?? null,
            $_POST['state'] ?? null,
            $_POST['zipcode'] ?? null,
            $_POST['notes'] ?? null,
            $_POST['id']
        ]);
        echo "<script>alert('Cliente atualizado com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao atualizar: " . $e->getMessage() . "');</script>";
    }
}

// Excluir Cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    $id = (int) $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>alert('Cliente excluído com sucesso!');window.location='index.php';</script>";
    exit;
}

// =============================
// 🔍 LISTAGEM E FILTRO
// =============================
$search = $_GET['search'] ?? '';
$filter_state = $_GET['state'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (name LIKE ? OR cpf_cnpj LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_state)) {
    $where .= " AND state = ?";
    $params[] = $filter_state;
}

$stmt = $conn->prepare("
    SELECT * FROM customers
    $where
    ORDER BY name ASC
");
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================
// 📊 ESTATÍSTICAS
// =============================
$total_customers = $conn->query("SELECT COUNT(*) as total FROM customers")->fetch(PDO::FETCH_ASSOC)['total'];

// Total de clientes com compras
$stmt = $conn->query("SELECT COUNT(DISTINCT customer_id) as total FROM sales WHERE customer_id IS NOT NULL");
$customers_with_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de clientes com OS
$stmt = $conn->query("SELECT COUNT(DISTINCT customer_id) as total FROM service_orders WHERE customer_id IS NOT NULL");
$customers_with_os = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Clientes cadastrados este mês
$stmt = $conn->query("SELECT COUNT(*) as total FROM customers WHERE MONTH(created_at) = MONTH(CURRENT_DATE) AND YEAR(created_at) = YEAR(CURRENT_DATE)");
$customers_this_month = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Buscar estados únicos para filtro
$states = $conn->query("SELECT DISTINCT state FROM customers WHERE state IS NOT NULL AND state != '' ORDER BY state ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clientes - FL Reparos</title>
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
.header, .stats-box, .search-box, .table-box {
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
.stat-card.blue .icon {color:#2196F3;}
.stat-card.green .icon {color:#4CAF50;}
.stat-card.purple .icon {color:#9C27B0;}
.stat-card.orange .icon {color:#FF9800;}

.filter-box {
    display:flex;
    gap:15px;
    align-items:flex-end;
    flex-wrap:wrap;
}
.form-group {
    flex:1;
    min-width:200px;
}
.form-group label {display:block;margin-bottom:5px;font-weight:600;color:#333;}
.form-control {
    width:100%;padding:10px;border:2px solid #ddd;border-radius:8px;
    font-size:1rem;transition:border .3s;
}
.form-control:focus {border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,0.1);}

.table {
    width:100%;border-collapse:collapse;background:rgba(255,255,255,0.95);
    border-radius:15px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.1);
}
th,td {padding:12px;text-align:left;}
th {background:linear-gradient(45deg,#f8f9fa,#e9ecef);font-weight:600;}
tr:hover {background:rgba(103,58,183,0.1);}
.empty {text-align:center;padding:40px;color:#777;font-style:italic;}

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
textarea.form-control {
    resize:vertical;
    min-height:80px;
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-address-book"></i> Clientes</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="openModal('createModal')"><i class="fas fa-plus"></i> Novo Cliente</button>
            <a href="import.php" class="btn" style="background:#28a745;color:white;"><i class="fas fa-file-excel"></i> Importar Planilha</a>
            <a href="../../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <div class="stats-box">
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="value"><?= $total_customers ?></div>
                <div class="label">Total de Clientes</div>
            </div>
            <div class="stat-card green">
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="value"><?= $customers_with_sales ?></div>
                <div class="label">Com Compras</div>
            </div>
            <div class="stat-card purple">
                <div class="icon"><i class="fas fa-wrench"></i></div>
                <div class="value"><?= $customers_with_os ?></div>
                <div class="label">Com OS</div>
            </div>
            <div class="stat-card orange">
                <div class="icon"><i class="fas fa-calendar-plus"></i></div>
                <div class="value"><?= $customers_this_month ?></div>
                <div class="label">Novos Este Mês</div>
            </div>
        </div>
    </div>

    <div class="search-box">
        <form method="GET">
            <div class="filter-box">
                <div class="form-group">
                    <label>Buscar</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Nome, CPF, telefone ou email...">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="state" class="form-control">
                        <option value="">Todos</option>
                        <?php foreach($states as $s): ?>
                            <option value="<?= htmlspecialchars($s['state']) ?>" <?= $filter_state === $s['state'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['state']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-search"></i> Filtrar</button>
                </div>
                <?php if(!empty($search) || !empty($filter_state)): ?>
                <div class="form-group">
                    <a href="index.php" class="btn btn-secondary" style="width:100%;"><i class="fas fa-redo"></i> Limpar</a>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-box">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>CPF/CNPJ</th>
                    <th>Telefone</th>
                    <th>Email</th>
                    <th>Cidade/UF</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($customers)): ?>
                    <tr><td colspan="7" class="empty">Nenhum cliente encontrado.</td></tr>
                <?php else: foreach($customers as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                    <td><?= htmlspecialchars($row['cpf_cnpj'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                    <td>
                        <?php if($row['city'] || $row['state']): ?>
                            <?= htmlspecialchars($row['city'] ?? '') ?><?= $row['city'] && $row['state'] ? '/' : '' ?><?= htmlspecialchars($row['state'] ?? '') ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($row) ?>)' title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" onsubmit="return confirm('Excluir este cliente?');" style="display:inline;">
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
</div>

<!-- MODAL NOVO CLIENTE -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Novo Cliente</h2>
            <button class="close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Nome Completo *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Nome completo do cliente">
                </div>
                <div class="form-group">
                    <label>CPF/CNPJ</label>
                    <input type="text" name="cpf_cnpj" class="form-control" placeholder="000.000.000-00">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="phone" class="form-control" placeholder="(00) 00000-0000">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="email@exemplo.com">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Endereço</label>
                    <input type="text" name="address" class="form-control" placeholder="Rua, número, complemento">
                </div>
                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" name="zipcode" class="form-control" placeholder="00000-000">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Cidade</label>
                    <input type="text" name="city" class="form-control" placeholder="Nome da cidade">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="state" class="form-control">
                        <option value="">Selecione</option>
                        <option value="AC">AC</option><option value="AL">AL</option><option value="AP">AP</option>
                        <option value="AM">AM</option><option value="BA">BA</option><option value="CE">CE</option>
                        <option value="DF">DF</option><option value="ES">ES</option><option value="GO">GO</option>
                        <option value="MA">MA</option><option value="MT">MT</option><option value="MS">MS</option>
                        <option value="MG">MG</option><option value="PA">PA</option><option value="PB">PB</option>
                        <option value="PR">PR</option><option value="PE">PE</option><option value="PI">PI</option>
                        <option value="RJ">RJ</option><option value="RN">RN</option><option value="RS">RS</option>
                        <option value="RO">RO</option><option value="RR">RR</option><option value="SC">SC</option>
                        <option value="SP">SP</option><option value="SE">SE</option><option value="TO">TO</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1 1 100%;">
                    <label>Observações</label>
                    <textarea name="notes" class="form-control" placeholder="Anotações sobre o cliente..."></textarea>
                </div>
            </div>
            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR CLIENTE -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-edit"></i> Editar Cliente</h2>
            <button class="close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Nome Completo *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>CPF/CNPJ</label>
                    <input type="text" name="cpf_cnpj" id="edit_cpf_cnpj" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Endereço</label>
                    <input type="text" name="address" id="edit_address" class="form-control">
                </div>
                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" name="zipcode" id="edit_zipcode" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Cidade</label>
                    <input type="text" name="city" id="edit_city" class="form-control">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="state" id="edit_state" class="form-control">
                        <option value="">Selecione</option>
                        <option value="AC">AC</option><option value="AL">AL</option><option value="AP">AP</option>
                        <option value="AM">AM</option><option value="BA">BA</option><option value="CE">CE</option>
                        <option value="DF">DF</option><option value="ES">ES</option><option value="GO">GO</option>
                        <option value="MA">MA</option><option value="MT">MT</option><option value="MS">MS</option>
                        <option value="MG">MG</option><option value="PA">PA</option><option value="PB">PB</option>
                        <option value="PR">PR</option><option value="PE">PE</option><option value="PI">PI</option>
                        <option value="RJ">RJ</option><option value="RN">RN</option><option value="RS">RS</option>
                        <option value="RO">RO</option><option value="RR">RR</option><option value="SC">SC</option>
                        <option value="SP">SP</option><option value="SE">SE</option><option value="TO">TO</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1 1 100%;">
                    <label>Observações</label>
                    <textarea name="notes" id="edit_notes" class="form-control"></textarea>
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
    document.getElementById('edit_id').value=o.id||'';
    document.getElementById('edit_name').value=o.name||'';
    document.getElementById('edit_cpf_cnpj').value=o.cpf_cnpj||'';
    document.getElementById('edit_phone').value=o.phone||'';
    document.getElementById('edit_email').value=o.email||'';
    document.getElementById('edit_address').value=o.address||'';
    document.getElementById('edit_city').value=o.city||'';
    document.getElementById('edit_state').value=o.state||'';
    document.getElementById('edit_zipcode').value=o.zipcode||'';
    document.getElementById('edit_notes').value=o.notes||'';
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
