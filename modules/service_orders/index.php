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

// Criar OS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    try {
        $stmt = $conn->prepare("INSERT INTO service_orders
            (customer_id, user_id, device, status, total_cost, payment_method, installments, entry_datetime,
             technical_report, reported_problem, customer_observations, internal_observations,
             device_powers_on,
             checklist_case, checklist_screen_protector, checklist_camera, checklist_housing,
             checklist_lens, checklist_face_id, checklist_sim_card, checklist_battery,
             checklist_charger, checklist_headphones,
             technician_name, attendant_name)
            VALUES (?, ?, ?, 'open', ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['customer_id'],
            $_SESSION['user_id'],
            $_POST['device'],
            $_POST['total_cost'] ?? 0,
            $_POST['payment_method'] ?? null,
            $_POST['installments'] ?? 1,
            $_POST['technical_report'] ?? null,
            $_POST['reported_problem'] ?? null,
            $_POST['customer_observations'] ?? null,
            $_POST['internal_observations'] ?? null,
            $_POST['device_powers_on'] ?? 'sim',
            isset($_POST['checklist_case']) ? 1 : 0,
            isset($_POST['checklist_screen_protector']) ? 1 : 0,
            isset($_POST['checklist_camera']) ? 1 : 0,
            isset($_POST['checklist_housing']) ? 1 : 0,
            isset($_POST['checklist_lens']) ? 1 : 0,
            isset($_POST['checklist_face_id']) ? 1 : 0,
            isset($_POST['checklist_sim_card']) ? 1 : 0,
            isset($_POST['checklist_battery']) ? 1 : 0,
            isset($_POST['checklist_charger']) ? 1 : 0,
            isset($_POST['checklist_headphones']) ? 1 : 0,
            $_POST['technician_name'] ?? null,
            $_POST['attendant_name'] ?? null
        ]);
        echo "<script>alert('Ordem de serviço criada com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao cadastrar: " . $e->getMessage() . "');</script>";
    }
}

// Editar OS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    try {
        $stmt = $conn->prepare("UPDATE service_orders
            SET customer_id=?, device=?, status=?, total_cost=?, payment_method=?, installments=?,
                technical_report=?, reported_problem=?, customer_observations=?, internal_observations=?,
                device_powers_on=?,
                checklist_case=?, checklist_screen_protector=?, checklist_camera=?, checklist_housing=?,
                checklist_lens=?, checklist_face_id=?, checklist_sim_card=?, checklist_battery=?,
                checklist_charger=?, checklist_headphones=?,
                technician_name=?, attendant_name=?
            WHERE id=?");
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['device'],
            $_POST['status'],
            $_POST['total_cost'] ?? 0,
            $_POST['payment_method'] ?? null,
            $_POST['installments'] ?? 1,
            $_POST['technical_report'] ?? null,
            $_POST['reported_problem'] ?? null,
            $_POST['customer_observations'] ?? null,
            $_POST['internal_observations'] ?? null,
            $_POST['device_powers_on'] ?? 'sim',
            isset($_POST['checklist_case']) ? 1 : 0,
            isset($_POST['checklist_screen_protector']) ? 1 : 0,
            isset($_POST['checklist_camera']) ? 1 : 0,
            isset($_POST['checklist_housing']) ? 1 : 0,
            isset($_POST['checklist_lens']) ? 1 : 0,
            isset($_POST['checklist_face_id']) ? 1 : 0,
            isset($_POST['checklist_sim_card']) ? 1 : 0,
            isset($_POST['checklist_battery']) ? 1 : 0,
            isset($_POST['checklist_charger']) ? 1 : 0,
            isset($_POST['checklist_headphones']) ? 1 : 0,
            $_POST['technician_name'] ?? null,
            $_POST['attendant_name'] ?? null,
            $_POST['id']
        ]);
        echo "<script>alert('Ordem de serviço atualizada com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao atualizar: " . $e->getMessage() . "');</script>";
    }
}

// Excluir OS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    $id = (int) $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM service_orders WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>alert('Ordem de serviço excluída com sucesso!');window.location='index.php';</script>";
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

$stmt = $conn->prepare("
    SELECT so.*, c.name as customer_name, c.phone as customer_phone
    FROM service_orders so
    LEFT JOIN customers c ON so.customer_id = c.id
    $where
    ORDER BY so.id DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar clientes para o formulário
$customers = $conn->query("SELECT id, name, phone FROM customers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

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

function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}
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
                    <button class="btn btn-warning btn-sm" onclick='openEditModal(<?= json_encode($row) ?>)' title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" onsubmit="return confirm('Excluir esta OS?');" style="display:inline;">
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
                    <label>Valor (R$)</label>
                    <input type="number" step="0.01" name="total_cost" class="form-control" placeholder="0,00" value="0">
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
                    <input type="checkbox" name="checklist_case" value="1"> Capa
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_screen_protector" value="1"> Película
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_camera" value="1"> Câmera
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_housing" value="1"> Carcaça
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_lens" value="1"> Lente
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_face_id" value="1"> Face ID
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_sim_card" value="1"> Chip
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_battery" value="1"> Bateria
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_charger" value="1"> Carregador
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_headphones" value="1"> Fone
                </label>
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
                    <label>Valor (R$)</label>
                    <input type="number" step="0.01" name="total_cost" id="edit_total_cost" class="form-control">
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
                    <input type="checkbox" name="checklist_case" id="edit_checklist_case" value="1"> Capa
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_screen_protector" id="edit_checklist_screen_protector" value="1"> Película
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_camera" id="edit_checklist_camera" value="1"> Câmera
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_housing" id="edit_checklist_housing" value="1"> Carcaça
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_lens" id="edit_checklist_lens" value="1"> Lente
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_face_id" id="edit_checklist_face_id" value="1"> Face ID
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_sim_card" id="edit_checklist_sim_card" value="1"> Chip
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_battery" id="edit_checklist_battery" value="1"> Bateria
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_charger" id="edit_checklist_charger" value="1"> Carregador
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="checklist_headphones" id="edit_checklist_headphones" value="1"> Fone
                </label>
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

function toggleInstallments(mode) {
    const paymentSelect = document.getElementById(mode + '_payment_method');
    const installmentsField = document.getElementById(mode + '_installments_field');

    if (paymentSelect.value === 'cartao_credito') {
        installmentsField.style.display = 'block';
    } else {
        installmentsField.style.display = 'none';
    }
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

    // Mostrar campo de parcelas se for cartão de crédito
    toggleInstallments('edit');

    // Diagnóstico
    document.getElementById('edit_reported_problem').value=o.reported_problem||'';
    document.getElementById('edit_device_powers_on').value=o.device_powers_on||'sim';
    document.getElementById('edit_technical_report').value=o.technical_report||'';

    // Checklist
    document.getElementById('edit_checklist_case').checked=o.checklist_case==1;
    document.getElementById('edit_checklist_screen_protector').checked=o.checklist_screen_protector==1;
    document.getElementById('edit_checklist_camera').checked=o.checklist_camera==1;
    document.getElementById('edit_checklist_housing').checked=o.checklist_housing==1;
    document.getElementById('edit_checklist_lens').checked=o.checklist_lens==1;
    document.getElementById('edit_checklist_face_id').checked=o.checklist_face_id==1;
    document.getElementById('edit_checklist_sim_card').checked=o.checklist_sim_card==1;
    document.getElementById('edit_checklist_battery').checked=o.checklist_battery==1;
    document.getElementById('edit_checklist_charger').checked=o.checklist_charger==1;
    document.getElementById('edit_checklist_headphones').checked=o.checklist_headphones==1;

    // Observações
    document.getElementById('edit_customer_observations').value=o.customer_observations||'';
    document.getElementById('edit_internal_observations').value=o.internal_observations||'';

    // Responsáveis
    document.getElementById('edit_technician_name').value=o.technician_name||'';
    document.getElementById('edit_attendant_name').value=o.attendant_name||'';

    openModal('editModal');
}

function printServiceOrder(order) {
    // Buscar dados do cliente
    const customerId = order.customer_id;

    fetch(`../../modules/customers/get_customer.php?id=${customerId}`)
        .then(response => response.json())
        .then(customer => {
            generateServiceOrderPrint(order, customer);
        })
        .catch(error => {
            console.error('Erro ao buscar cliente:', error);
            alert('Erro ao carregar dados do cliente');
        });
}

function generateServiceOrderPrint(order, customer) {
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

    const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ordem de Serviço #${order.id}</title>
    <style>
        @media print {
            @page { margin: 20mm; }
            body { margin: 0; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
        }
        .logo {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        .logo i {
            margin-right: 10px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .os-number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 20px 0 10px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background: #667eea;
            color: white;
            padding: 8px 12px;
            font-weight: bold;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .info-item {
            padding: 10px;
            background: #f5f5f5;
            border-left: 3px solid #667eea;
        }
        .info-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .info-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }
        .checklist {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 10px;
        }
        .checklist-item {
            padding: 8px;
            background: #f9f9f9;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        .checklist-item i {
            margin-right: 8px;
            color: #28a745;
        }
        .text-area {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 4px;
            min-height: 80px;
            margin-top: 8px;
            white-space: pre-wrap;
        }
        .signature-area {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 2px dashed #ccc;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin: 40px 20px 5px;
            padding-top: 5px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #667eea;
            color: #666;
            font-size: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-open { background: #ffc107; color: #000; }
        .status-in_progress { background: #17a2b8; color: white; }
        .status-completed { background: #28a745; color: white; }
        .status-delivered { background: #6c757d; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <i class="fas fa-mobile-alt"></i> FL REPAROS
        </div>
        <div class="subtitle">Sistema de Gestão para Assistência Técnica</div>
        <div class="os-number">OS #${order.id}</div>
        <span class="status-badge status-${order.status}">${statusMap[order.status] || order.status}</span>
    </div>

    <div class="section">
        <div class="section-title"><i class="fas fa-user"></i> Dados do Cliente</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Nome</div>
                <div class="info-value">${customer.name || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Telefone</div>
                <div class="info-value">${customer.phone || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value">${customer.email || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Endereço</div>
                <div class="info-value">${customer.address || '-'}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="fas fa-mobile-alt"></i> Dados do Equipamento</div>
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
                <div class="info-label">O Aparelho Liga?</div>
                <div class="info-value">${order.device_powers_on === 'sim' ? 'Sim' : 'Não'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Valor do Serviço</div>
                <div class="info-value" style="color: #28a745;">${totalFormatted}</div>
            </div>
            ${order.payment_method ? `
            <div class="info-item">
                <div class="info-label">Forma de Pagamento</div>
                <div class="info-value">${paymentMap[order.payment_method] || order.payment_method}${order.payment_method === 'cartao_credito' && order.installments > 1 ? ' - ' + order.installments + 'x de R$ ' + (order.total_cost / order.installments).toFixed(2).replace('.', ',') : ''}</div>
            </div>
            ` : ''}
        </div>
    </div>

    ${order.reported_problem ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-exclamation-circle"></i> Problema Relatado</div>
        <div class="text-area">${order.reported_problem}</div>
    </div>
    ` : ''}

    ${order.technical_report ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-wrench"></i> Laudo Técnico</div>
        <div class="text-area">${order.technical_report}</div>
    </div>
    ` : ''}

    <div class="section">
        <div class="section-title"><i class="fas fa-clipboard-check"></i> Checklist do Aparelho</div>
        <div class="checklist">
            ${order.checklist_case ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Capa</div>' : ''}
            ${order.checklist_screen_protector ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Película</div>' : ''}
            ${order.checklist_camera ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Câmera</div>' : ''}
            ${order.checklist_housing ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Carcaça</div>' : ''}
            ${order.checklist_lens ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Lente</div>' : ''}
            ${order.checklist_face_id ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Face ID</div>' : ''}
            ${order.checklist_sim_card ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Chip</div>' : ''}
            ${order.checklist_battery ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Bateria</div>' : ''}
            ${order.checklist_charger ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Carregador</div>' : ''}
            ${order.checklist_headphones ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Fone</div>' : ''}
        </div>
    </div>

    ${order.customer_observations ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-comment-alt"></i> Observações para o Cliente</div>
        <div class="text-area">${order.customer_observations}</div>
    </div>
    ` : ''}

    ${order.technician_name || order.attendant_name ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-users"></i> Responsáveis</div>
        <div class="info-grid">
            ${order.technician_name ? `
            <div class="info-item">
                <div class="info-label">Técnico Responsável</div>
                <div class="info-value">${order.technician_name}</div>
            </div>
            ` : ''}
            ${order.attendant_name ? `
            <div class="info-item">
                <div class="info-label">Atendente Responsável</div>
                <div class="info-value">${order.attendant_name}</div>
            </div>
            ` : ''}
        </div>
    </div>
    ` : ''}

    <div class="signature-area">
        <div class="signature-line">
            Assinatura do Cliente
        </div>
    </div>

    <div class="footer">
        <p><strong>FL REPAROS</strong> - Sistema de Gestão para Assistência Técnica</p>
        <p>Documento gerado em ${new Date().toLocaleString('pt-BR')}</p>
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
</script>

</body>
</html>
