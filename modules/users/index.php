<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once('../../config/database.php');
require_once('../../config/permissions.php');
require_once('../../config/app.php');

// Apenas admin pode acessar
requireAdmin();

$conn = $database->getConnection();

// =============================
// 🔧 CRUD
// =============================

// Criar Usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    try {
        // Verificar se email já existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetch()) {
            echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Este email já está cadastrado!',type:'error'}));window.location='index.php';</script>";
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO users
            (name, email, password, role, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            password_hash($_POST['password'], PASSWORD_DEFAULT),
            $_POST['role'],
            $_POST['status'] ?? 'active'
        ]);
        $newUserId = $conn->lastInsertId();
        logActivity('create', 'users', $newUserId,
            "Usuário '{$_POST['name']}' cadastrado — Email: {$_POST['email']}, Perfil: {$_POST['role']}"
        );
        echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Usuário cadastrado com sucesso!',type:'success'}));window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>document.addEventListener('DOMContentLoaded',function(){ showAlert('Erro ao cadastrar: " . addslashes($e->getMessage()) . "','error'); });</script>";
    }
}

// Editar Usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    try {
        // Buscar dados atuais para o log
        $oldUser = $conn->prepare("SELECT name, email, role, status FROM users WHERE id=?");
        $oldUser->execute([$_POST['id']]);
        $oldUserData = $oldUser->fetch(PDO::FETCH_ASSOC);

        // Não permitir alterar próprio perfil de admin para evitar lock-out
        if ($_POST['id'] == $_SESSION['user_id'] && $_POST['role'] !== 'admin') {
            echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Você não pode alterar seu próprio perfil de administrador!',type:'error'}));window.location='index.php';</script>";
            exit;
        }

        if (!empty($_POST['password'])) {
            // Atualizar com nova senha
            $stmt = $conn->prepare("UPDATE users
                SET name=?, email=?, password=?, role=?, status=?, updated_at=NOW()
                WHERE id=?");
            $stmt->execute([
                $_POST['name'],
                $_POST['email'],
                password_hash($_POST['password'], PASSWORD_DEFAULT),
                $_POST['role'],
                $_POST['status'],
                $_POST['id']
            ]);
        } else {
            // Atualizar sem alterar senha
            $stmt = $conn->prepare("UPDATE users
                SET name=?, email=?, role=?, status=?, updated_at=NOW()
                WHERE id=?");
            $stmt->execute([
                $_POST['name'],
                $_POST['email'],
                $_POST['role'],
                $_POST['status'],
                $_POST['id']
            ]);
        }
        $passChanged = !empty($_POST['password']) ? ', senha alterada' : '';
        logActivity('update', 'users', (int)$_POST['id'],
            "Usuário '{$_POST['name']}' editado$passChanged",
            $oldUserData ? ['nome' => $oldUserData['name'], 'email' => $oldUserData['email'], 'perfil' => $oldUserData['role'], 'status' => $oldUserData['status']] : null,
            ['nome' => $_POST['name'], 'email' => $_POST['email'], 'perfil' => $_POST['role'], 'status' => $_POST['status']]
        );
        echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Usuário atualizado com sucesso!',type:'success'}));window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>document.addEventListener('DOMContentLoaded',function(){ showAlert('Erro ao atualizar: " . addslashes($e->getMessage()) . "','error'); });</script>";
    }
}

// Excluir Usuário (Soft Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    try {
        $id = (int) $_POST['id'];

        // Não permitir deletar a si mesmo
        if ($id == $_SESSION['user_id']) {
            echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Você não pode deletar seu próprio usuário!',type:'error'}));window.location='index.php';</script>";
            exit;
        }

        // Buscar nome antes de excluir
        $delUser = $conn->prepare("SELECT name, email, role FROM users WHERE id=?");
        $delUser->execute([$id]);
        $delUserData = $delUser->fetch(PDO::FETCH_ASSOC);

        // Soft delete: marca como deletado ao invés de remover do banco
        $stmt = $conn->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        logActivity('delete', 'users', $id,
            "Usuário '{$delUserData['name']}' excluído — Email: {$delUserData['email']}, Perfil: {$delUserData['role']}"
        );
        echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Usuário excluído com sucesso!',type:'success'}));window.location='index.php';</script>";
        exit;

    } catch (PDOException $e) {
        $errorMsg = "Erro ao excluir usuário: " . htmlspecialchars($e->getMessage());
        echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'" . addslashes($errorMsg) . "',type:'error'}));window.location='index.php';</script>";
        exit;
    }
}

// =============================
// 🔐 SALVAR PERMISSÕES
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_permissions') {
    try {
        $userId = (int)$_POST['user_id'];
        $modules = [
            'pdv' => ['view', 'create', 'edit', 'delete'],
            'service_orders' => ['view', 'create', 'edit', 'delete'],
            'products' => ['view', 'create', 'edit', 'delete'],
            'customers' => ['view', 'create', 'edit', 'delete'],
            'expenses' => ['view', 'create', 'edit', 'delete'],
            'cashflow' => ['view', 'create', 'edit', 'delete'],
            'accounts_receivable' => ['view', 'create', 'edit', 'delete'],
            'reports' => ['view'],
        ];

        $useCustom = isset($_POST['use_custom']) && $_POST['use_custom'] == '1';

        if ($useCustom) {
            $permissions = [];
            foreach ($modules as $mod => $actions) {
                $modPerms = [];
                foreach ($actions as $act) {
                    if (isset($_POST['perm_' . $mod . '_' . $act])) {
                        $modPerms[] = $act;
                    }
                }
                if (!empty($modPerms)) {
                    $permissions[$mod] = $modPerms;
                }
            }
            $permJson = json_encode($permissions);
        } else {
            $permJson = null;
        }

        $stmt = $conn->prepare("UPDATE users SET permissions = ? WHERE id = ?");
        $stmt->execute([$permJson, $userId]);

        echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Permissões salvas com sucesso! O usuário precisa fazer login novamente para aplicar.',type:'success'}));window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>document.addEventListener('DOMContentLoaded',function(){ showAlert('Erro ao salvar permissões: " . addslashes($e->getMessage()) . "','error'); });</script>";
    }
}

// =============================
// 🔧 CRUD Técnicos
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_technician') {
    $stmt = $conn->prepare("INSERT INTO technicians (name, phone, specialty) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['name'], $_POST['phone'] ?? null, $_POST['specialty'] ?? null]);
    echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Técnico cadastrado com sucesso!',type:'success'}));window.location='index.php?tab=technicians';</script>";
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_technician') {
    $conn->prepare("UPDATE technicians SET active = 0 WHERE id = ?")->execute([$_POST['id']]);
    echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Técnico removido!',type:'success'}));window.location='index.php?tab=technicians';</script>";
    exit;
}

// =============================
// 🔧 CRUD Atendentes
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_attendant') {
    $stmt = $conn->prepare("INSERT INTO attendants (name, phone) VALUES (?, ?)");
    $stmt->execute([$_POST['name'], $_POST['phone'] ?? null]);
    echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Atendente cadastrado com sucesso!',type:'success'}));window.location='index.php?tab=attendants';</script>";
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_attendant') {
    $conn->prepare("UPDATE attendants SET active = 0 WHERE id = ?")->execute([$_POST['id']]);
    echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Atendente removido!',type:'success'}));window.location='index.php?tab=attendants';</script>";
    exit;
}

// =============================
// 🔍 LISTAGEM E FILTRO
// =============================
$search = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? '';
$filter_status = $_GET['status'] ?? '';
$where = "WHERE deleted_at IS NULL";  // Filtrar apenas usuários ativos (não deletados)
$params = [];

if (!empty($search)) {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_role)) {
    $where .= " AND role = ?";
    $params[] = $filter_role;
}

if (!empty($filter_status)) {
    $where .= " AND status = ?";
    $params[] = $filter_status;
}

$stmt = $conn->prepare("
    SELECT * FROM users
    $where
    ORDER BY name ASC
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================
// 📊 ESTATÍSTICAS
// =============================
$total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC)['total'];
$total_admins = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin' AND deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC)['total'];
$total_managers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'manager' AND deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC)['total'];
$total_sellers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'seller' AND deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC)['total'];
$total_active = $conn->query("SELECT COUNT(*) as total FROM users WHERE status = 'active' AND deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC)['total'];

// Buscar técnicos e atendentes
$technicians = $conn->query("SELECT * FROM technicians WHERE active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$attendants_list = $conn->query("SELECT * FROM attendants WHERE active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$activeTab = $_GET['tab'] ?? 'users';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuários - FL Reparos</title>
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
    grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
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
.stat-card.cyan .icon {color:#00BCD4;}

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

.badge {
    padding:6px 12px;
    border-radius:20px;
    color:white;
    font-weight:600;
    font-size:0.85rem;
    display:inline-block;
}
.badge-admin {background:#4CAF50;}
.badge-manager {background:#2196F3;}
.badge-seller {background:#9C27B0;}
.badge-active {background:#4CAF50;}
.badge-inactive {background:#999;}

.modal {
    display:none;position:fixed;top:0;left:0;width:100%;height:100%;
    background:rgba(0,0,0,0.6);z-index:1000;backdrop-filter:blur(5px);
    overflow-y:auto;
}
.modal-content {
    background:rgba(255,255,255,0.95);margin:40px auto;padding:30px;
    border-radius:15px;max-width:600px;box-shadow:0 8px 32px rgba(0,0,0,0.2);
}
.modal-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.modal-header h2 {margin:0;display:flex;align-items:center;gap:10px;}
.close {
    background:#f44336;border:none;color:#fff;border-radius:50%;
    width:35px;height:35px;cursor:pointer;font-size:18px;transition:all .3s;
}
.close:hover {transform:rotate(90deg);background:#d32f2f;}
.form-row {display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;}

.alert {
    padding:15px 20px;
    border-radius:8px;
    margin-bottom:15px;
}
.alert-info {
    background:#d1ecf1;
    color:#0c5460;
    border:1px solid #bee5eb;
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-users-cog"></i> Gerenciar Usuários</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <?php if($activeTab === 'users'): ?>
                <button class="btn btn-primary" onclick="openModal('createModal')"><i class="fas fa-user-plus"></i> Novo Usuário</button>
            <?php elseif($activeTab === 'technicians'): ?>
                <button class="btn btn-primary" onclick="openModal('createTechModal')"><i class="fas fa-user-plus"></i> Novo Técnico</button>
            <?php elseif($activeTab === 'attendants'): ?>
                <button class="btn btn-primary" onclick="openModal('createAttModal')"><i class="fas fa-user-plus"></i> Novo Atendente</button>
            <?php endif; ?>
            <a href="../../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <!-- Abas -->
    <div style="display:flex;gap:10px;margin-bottom:20px;">
        <a href="?tab=users" class="btn <?= $activeTab === 'users' ? 'btn-primary' : 'btn-secondary' ?>" style="<?= $activeTab === 'users' ? '' : 'opacity:0.7;' ?>">
            <i class="fas fa-users"></i> Usuários
        </a>
        <a href="?tab=technicians" class="btn <?= $activeTab === 'technicians' ? 'btn-primary' : 'btn-secondary' ?>" style="<?= $activeTab === 'technicians' ? '' : 'opacity:0.7;' ?>">
            <i class="fas fa-tools"></i> Técnicos (<?= count($technicians) ?>)
        </a>
        <a href="?tab=attendants" class="btn <?= $activeTab === 'attendants' ? 'btn-primary' : 'btn-secondary' ?>" style="<?= $activeTab === 'attendants' ? '' : 'opacity:0.7;' ?>">
            <i class="fas fa-headset"></i> Atendentes (<?= count($attendants_list) ?>)
        </a>
    </div>

    <?php if($activeTab === 'users'): ?>
    <div class="stats-box">
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="value"><?= $total_users ?></div>
                <div class="label">Total Usuários</div>
            </div>
            <div class="stat-card green">
                <div class="icon"><i class="fas fa-user-shield"></i></div>
                <div class="value"><?= $total_admins ?></div>
                <div class="label">Administradores</div>
            </div>
            <div class="stat-card purple">
                <div class="icon"><i class="fas fa-user-tie"></i></div>
                <div class="value"><?= $total_managers ?></div>
                <div class="label">Gerentes</div>
            </div>
            <div class="stat-card purple">
                <div class="icon"><i class="fas fa-user"></i></div>
                <div class="value"><?= $total_sellers ?></div>
                <div class="label">Vendedores</div>
            </div>
            <div class="stat-card cyan">
                <div class="icon"><i class="fas fa-user-check"></i></div>
                <div class="value"><?= $total_active ?></div>
                <div class="label">Ativos</div>
            </div>
        </div>
    </div>

    <div class="search-box">
        <form method="GET">
            <div class="filter-box">
                <div class="form-group">
                    <label>Buscar</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Nome ou email...">
                </div>
                <div class="form-group">
                    <label>Perfil</label>
                    <select name="role" class="form-control">
                        <option value="">Todos</option>
                        <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="manager" <?= $filter_role === 'manager' ? 'selected' : '' ?>>Gerente</option>
                        <option value="seller" <?= $filter_role === 'seller' ? 'selected' : '' ?>>Vendedor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">Todos</option>
                        <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-search"></i> Filtrar</button>
                </div>
                <?php if(!empty($search) || !empty($filter_role) || !empty($filter_status)): ?>
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
                    <th>Email</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($users)): ?>
                    <tr><td colspan="7" class="empty">Nenhum usuário encontrado.</td></tr>
                <?php else: foreach($users as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($row['name']) ?></strong>
                        <?php if($row['id'] == $_SESSION['user_id']): ?>
                            <span style="color:#4CAF50;font-size:0.8rem;">(Você)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><span class="badge badge-<?= $row['role'] ?>"><?= getRoleName($row['role']) ?></span></td>
                    <td><span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] === 'active' ? 'Ativo' : 'Inativo' ?></span></td>
                    <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($row) ?>)' title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if($row['role'] !== 'admin'): ?>
                        <button class="btn btn-sm" style="background:#667eea;color:white;border:none;padding:5px 8px;border-radius:5px;cursor:pointer;" onclick='openPermissionsModal(<?= json_encode($row) ?>)' title="Permissões">
                            <i class="fas fa-key"></i>
                        </button>
                        <?php endif; ?>
                        <?php if($row['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" onsubmit="return false;" style="display:inline;" id="deleteUserForm_<?= $row['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="button" class="btn btn-danger btn-sm" title="Excluir" onclick="showConfirm('Excluir este usuário?','Excluir','Excluir','Cancelar','danger').then(ok=>{if(ok)document.getElementById('deleteUserForm_<?= $row['id'] ?>').submit();})"><i class="fas fa-trash-alt"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; // fim tab users ?>

    <?php if($activeTab === 'technicians'): ?>
    <!-- SEÇÃO TÉCNICOS -->
    <div class="table-box">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Telefone</th>
                    <th>Especialidade</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($technicians)): ?>
                    <tr><td colspan="5" class="empty">Nenhum técnico cadastrado.</td></tr>
                <?php else: foreach($technicians as $tech): ?>
                <tr>
                    <td><?= $tech['id'] ?></td>
                    <td><strong><?= htmlspecialchars($tech['name']) ?></strong></td>
                    <td><?= htmlspecialchars($tech['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($tech['specialty'] ?? '-') ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return false;" id="deleteTechForm_<?= $tech['id'] ?>">
                            <input type="hidden" name="action" value="delete_technician">
                            <input type="hidden" name="id" value="<?= $tech['id'] ?>">
                            <button type="button" class="btn btn-danger btn-sm" onclick="showConfirm('Remover este técnico?','Remover','Remover','Cancelar','danger').then(ok=>{if(ok)document.getElementById('deleteTechForm_<?= $tech['id'] ?>').submit();})"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if($activeTab === 'attendants'): ?>
    <!-- SEÇÃO ATENDENTES -->
    <div class="table-box">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Telefone</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($attendants_list)): ?>
                    <tr><td colspan="4" class="empty">Nenhum atendente cadastrado.</td></tr>
                <?php else: foreach($attendants_list as $att): ?>
                <tr>
                    <td><?= $att['id'] ?></td>
                    <td><strong><?= htmlspecialchars($att['name']) ?></strong></td>
                    <td><?= htmlspecialchars($att['phone'] ?? '-') ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return false;" id="deleteAttForm_<?= $att['id'] ?>">
                            <input type="hidden" name="action" value="delete_attendant">
                            <input type="hidden" name="id" value="<?= $att['id'] ?>">
                            <button type="button" class="btn btn-danger btn-sm" onclick="showConfirm('Remover este atendente?','Remover','Remover','Cancelar','danger').then(ok=>{if(ok)document.getElementById('deleteAttForm_<?= $att['id'] ?>').submit();})"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL NOVO TÉCNICO -->
<div id="createTechModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-tools"></i> Novo Técnico</h2>
            <button class="close" onclick="closeModal('createTechModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_technician">
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Nome Completo *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Nome do técnico">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="phone" class="form-control" placeholder="(00) 00000-0000">
                </div>
                <div class="form-group">
                    <label>Especialidade</label>
                    <input type="text" name="specialty" class="form-control" placeholder="Ex: Telas, Baterias, Placas...">
                </div>
            </div>
            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createTechModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL NOVO ATENDENTE -->
<div id="createAttModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-headset"></i> Novo Atendente</h2>
            <button class="close" onclick="closeModal('createAttModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_attendant">
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Nome Completo *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Nome do atendente">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="phone" class="form-control" placeholder="(00) 00000-0000">
                </div>
            </div>
            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createAttModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL NOVO USUÁRIO -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Novo Usuário</h2>
            <button class="close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Perfis:</strong> Admin (acesso total), Gerente (operações), Vendedor (vendas e atendimento)
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Nome Completo *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Nome do usuário">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required placeholder="email@exemplo.com">
                </div>
                <div class="form-group">
                    <label>Senha *</label>
                    <input type="password" name="password" class="form-control" required placeholder="Mínimo 6 caracteres" minlength="6">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Perfil *</label>
                    <select name="role" class="form-control" required>
                        <option value="">Selecione</option>
                        <option value="admin">🔴 Administrador (Acesso Total)</option>
                        <option value="manager">🔵 Gerente (Operações)</option>
                        <option value="seller">🟣 Vendedor (Vendas/OS)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" class="form-control">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>
            </div>
            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR USUÁRIO -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-edit"></i> Editar Usuário</h2>
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
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Nova Senha (deixe vazio para manter)</label>
                    <input type="password" name="password" class="form-control" placeholder="Deixe vazio para não alterar" minlength="6">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Perfil *</label>
                    <select name="role" id="edit_role" class="form-control" required>
                        <option value="admin">🔴 Administrador</option>
                        <option value="manager">🔵 Gerente</option>
                        <option value="seller">🟣 Vendedor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>
            </div>
            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Atualizar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Permissões -->
<div id="permissionsModal" class="modal">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header" style="background:linear-gradient(135deg, #667eea, #764ba2);">
            <h2 style="color:white;"><i class="fas fa-key"></i> Permissões - <span id="perm_user_name"></span></h2>
            <button class="close" onclick="closeModal('permissionsModal')" style="color:white;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_permissions">
            <input type="hidden" name="user_id" id="perm_user_id">

            <div style="padding:20px;">
                <div style="margin-bottom:15px;padding:12px;background:#fff3cd;border-radius:8px;border-left:4px solid #ffc107;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:bold;">
                        <input type="checkbox" name="use_custom" id="perm_use_custom" value="1" onchange="toggleCustomPerms()">
                        Usar permissões personalizadas (sobrepõe o perfil padrão)
                    </label>
                </div>

                <div id="perm_grid" style="opacity:0.5;pointer-events:none;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th style="padding:10px;text-align:left;border-bottom:2px solid #dee2e6;">Módulo</th>
                                <th style="padding:10px;text-align:center;border-bottom:2px solid #dee2e6;">Ver</th>
                                <th style="padding:10px;text-align:center;border-bottom:2px solid #dee2e6;">Criar</th>
                                <th style="padding:10px;text-align:center;border-bottom:2px solid #dee2e6;">Editar</th>
                                <th style="padding:10px;text-align:center;border-bottom:2px solid #dee2e6;">Excluir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $moduleNames = [
                                'pdv' => 'PDV Rápido',
                                'service_orders' => 'Ordens de Serviço',
                                'products' => 'Produtos',
                                'customers' => 'Clientes',
                                'expenses' => 'Despesas',
                                'cashflow' => 'Fluxo de Caixa',
                                'accounts_receivable' => 'Contas a Receber',
                                'reports' => 'Relatórios',
                            ];
                            $moduleActions = [
                                'pdv' => ['view', 'create', 'edit', 'delete'],
                                'service_orders' => ['view', 'create', 'edit', 'delete'],
                                'products' => ['view', 'create', 'edit', 'delete'],
                                'customers' => ['view', 'create', 'edit', 'delete'],
                                'expenses' => ['view', 'create', 'edit', 'delete'],
                                'cashflow' => ['view', 'create', 'edit', 'delete'],
                                'accounts_receivable' => ['view', 'create', 'edit', 'delete'],
                                'reports' => ['view'],
                            ];
                            foreach ($moduleNames as $mod => $label):
                                $actions = $moduleActions[$mod];
                            ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:8px 10px;font-weight:bold;"><?= $label ?></td>
                                <?php foreach (['view', 'create', 'edit', 'delete'] as $act): ?>
                                <td style="padding:8px 10px;text-align:center;">
                                    <?php if (in_array($act, $actions)): ?>
                                    <input type="checkbox" name="perm_<?= $mod ?>_<?= $act ?>" id="perm_<?= $mod ?>_<?= $act ?>" value="1" style="width:18px;height:18px;cursor:pointer;">
                                    <?php else: ?>
                                    <span style="color:#ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="text-align:right;padding:15px 20px;border-top:1px solid #eee;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Permissões</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('permissionsModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script src="../../assets/js/main.js"></script>
<script>
function openModal(id){document.getElementById(id).style.display='block';}
function closeModal(id){document.getElementById(id).style.display='none';}

function openEditModal(o){
    document.getElementById('edit_id').value=o.id||'';
    document.getElementById('edit_name').value=o.name||'';
    document.getElementById('edit_email').value=o.email||'';
    document.getElementById('edit_role').value=o.role||'operator';
    document.getElementById('edit_status').value=o.status||'active';
    openModal('editModal');
}

function openPermissionsModal(user) {
    document.getElementById('perm_user_id').value = user.id;
    document.getElementById('perm_user_name').textContent = user.name;

    const perms = user.permissions ? JSON.parse(user.permissions) : null;
    const useCustom = document.getElementById('perm_use_custom');

    // Desmarcar todos os checkboxes primeiro
    document.querySelectorAll('#perm_grid input[type="checkbox"]').forEach(cb => cb.checked = false);

    if (perms) {
        useCustom.checked = true;
        // Marcar permissões existentes
        for (const mod in perms) {
            perms[mod].forEach(act => {
                const cb = document.getElementById('perm_' + mod + '_' + act);
                if (cb) cb.checked = true;
            });
        }
    } else {
        useCustom.checked = false;
        // Preencher com permissões padrão do perfil
        const defaults = <?= json_encode($PERMISSIONS) ?>;
        const rolePerms = defaults[user.role] || {};
        for (const mod in rolePerms) {
            rolePerms[mod].forEach(act => {
                const cb = document.getElementById('perm_' + mod + '_' + act);
                if (cb) cb.checked = true;
            });
        }
    }

    toggleCustomPerms();
    openModal('permissionsModal');
}

function toggleCustomPerms() {
    const grid = document.getElementById('perm_grid');
    const checked = document.getElementById('perm_use_custom').checked;
    grid.style.opacity = checked ? '1' : '0.5';
    grid.style.pointerEvents = checked ? 'auto' : 'none';
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
        closeModal('permissionsModal');
    }
});
</script>

</body>
</html>
