<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once('../../config/database.php');
require_once('../../config/app.php');
$conn = $database->getConnection();

// Apenas admin pode acessar
requireAdmin();

// =============================
// EXPORTAR CSV
// =============================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where = buildWhere();
    $sql = "SELECT l.*, DATE_FORMAT(l.created_at, '%d/%m/%Y %H:%i:%s') as created_fmt
            FROM activity_logs l
            WHERE " . $where['clause'] . "
            ORDER BY l.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($where['params']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Data/Hora', 'Usuário', 'Ação', 'Módulo', 'ID', 'Descrição', 'IP'], ';');
    $actionLabels = ['create' => 'Criar', 'update' => 'Editar', 'delete' => 'Excluir'];
    $moduleLabels = ['products' => 'Produtos', 'customers' => 'Clientes', 'service_orders' => 'OS', 'sales' => 'Vendas', 'users' => 'Usuários'];
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['created_fmt'],
            $r['user_name'],
            $actionLabels[$r['action']] ?? $r['action'],
            $moduleLabels[$r['module']] ?? $r['module'],
            $r['record_id'],
            $r['description'],
            $r['ip_address']
        ], ';');
    }
    fclose($out);
    exit;
}

function buildWhere() {
    global $module_filter, $action_filter, $user_filter, $date_from, $date_to;
    $clauses = ['1=1'];
    $params = [];
    if ($module_filter) { $clauses[] = 'l.module = ?'; $params[] = $module_filter; }
    if ($action_filter) { $clauses[] = 'l.action = ?'; $params[] = $action_filter; }
    if ($user_filter)   { $clauses[] = 'l.user_name LIKE ?'; $params[] = "%$user_filter%"; }
    if ($date_from)     { $clauses[] = 'DATE(l.created_at) >= ?'; $params[] = $date_from; }
    if ($date_to)       { $clauses[] = 'DATE(l.created_at) <= ?'; $params[] = $date_to; }
    return ['clause' => implode(' AND ', $clauses), 'params' => $params];
}

// =============================
// FILTROS
// =============================
$module_filter = $_GET['module'] ?? '';
$action_filter = $_GET['action'] ?? '';
$user_filter   = $_GET['user'] ?? '';
$date_from     = $_GET['date_from'] ?? date('Y-m-01');
$date_to       = $_GET['date_to'] ?? date('Y-m-d');

// Paginação
$page = max(1, (int)($_GET['page'] ?? 1));
$allowedPerPage = [15, 30, 50, 100];
$perPage = in_array((int)($_GET['per_page'] ?? 30), $allowedPerPage) ? (int)($_GET['per_page'] ?? 30) : 30;
$offset = ($page - 1) * $perPage;

$where = buildWhere();

$stmtCount = $conn->prepare("SELECT COUNT(*) FROM activity_logs l WHERE " . $where['clause']);
$stmtCount->execute($where['params']);
$total = $stmtCount->fetchColumn();
$totalPages = ceil($total / $perPage);

$paramsPage = array_merge($where['params'], [$perPage, $offset]);
$stmt = $conn->prepare(
    "SELECT l.*, DATE_FORMAT(l.created_at, '%d/%m/%Y %H:%i:%s') as created_fmt
     FROM activity_logs l
     WHERE " . $where['clause'] . "
     ORDER BY l.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($paramsPage);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar usuários distintos para o filtro
$users = $conn->query("SELECT DISTINCT user_name FROM activity_logs ORDER BY user_name")->fetchAll(PDO::FETCH_COLUMN);

$actionLabels = ['create' => 'Criar', 'update' => 'Editar', 'delete' => 'Excluir'];
$actionColors = ['create' => '#4CAF50', 'update' => '#2196F3', 'delete' => '#f44336'];
$actionIcons  = ['create' => 'fa-plus-circle', 'update' => 'fa-edit', 'delete' => 'fa-trash'];
$moduleLabels = ['products' => 'Produtos', 'customers' => 'Clientes', 'service_orders' => 'Ordens de Serviço', 'sales' => 'Vendas', 'users' => 'Usuários'];
$moduleIcons  = ['products' => 'fa-box', 'customers' => 'fa-user', 'service_orders' => 'fa-tools', 'sales' => 'fa-shopping-cart', 'users' => 'fa-users-cog'];

function buildUrl($extra = []) {
    $params = array_merge([
        'module'    => $_GET['module'] ?? '',
        'action'    => $_GET['action'] ?? '',
        'user'      => $_GET['user'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to'] ?? '',
        'per_page'  => $_GET['per_page'] ?? 30,
        'page'      => $_GET['page'] ?? 1,
    ], $extra);
    return '?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Logs de Atividade - FL REPAROS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: auto; }
        .card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            padding: 20px;
            margin-bottom: 20px;
        }
        .header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .header h1 { font-size: 1.8rem; display: flex; align-items: center; gap: 10px; color: #333; }
        .btn {
            border: none; border-radius: 8px; padding: 10px 18px; color: #fff; font-weight: 500;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            transition: all .3s; font-size: 0.9rem;
        }
        .btn-secondary { background: linear-gradient(45deg, #2196F3, #1976D2); }
        .btn-success   { background: linear-gradient(45deg, #4CAF50, #388E3C); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }

        /* Filtros */
        .filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; min-width: 130px; }
        .filter-group label { font-size: 0.8rem; font-weight: 600; color: #666; text-transform: uppercase; }
        .filter-group select, .filter-group input {
            padding: 8px 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.9rem;
            background: white; transition: border-color .2s;
        }
        .filter-group select:focus, .filter-group input:focus { outline: none; border-color: #667eea; }
        .filter-actions { display: flex; gap: 8px; align-items: flex-end; }

        /* Tabela */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 14px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: linear-gradient(45deg, #f8f9fa, #e9ecef); font-weight: 600; font-size: 0.85rem; color: #555; text-transform: uppercase; }
        tr:hover { background: rgba(102,126,234,0.05); }
        .empty { text-align: center; padding: 60px; color: #aaa; }
        .empty i { font-size: 3rem; display: block; margin-bottom: 10px; }

        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;
        }
        .badge-create { background: #d4edda; color: #155724; }
        .badge-update { background: #cce5ff; color: #004085; }
        .badge-delete { background: #f8d7da; color: #721c24; }
        .badge-module { background: rgba(102,126,234,0.12); color: #4a3a9c; }

        /* Paginação */
        .pagination-container {
            background:rgba(255,255,255,0.95); backdrop-filter:blur(10px);
            padding:15px 20px; border-radius:15px; box-shadow:0 8px 32px rgba(0,0,0,0.15);
            margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;
        }
        .pagination-info { color:#666; font-size:0.9rem; font-weight:500; }
        .pagination-controls { display:flex; gap:20px; align-items:center; flex-wrap:wrap; }
        .per-page-selector { display:flex; gap:10px; align-items:center; }
        .per-page-selector label { color:#666; font-size:0.9rem; font-weight:500; }
        .per-page-selector select {
            padding:8px 12px; border:2px solid #ddd; border-radius:8px; background:white;
            font-size:0.9rem; cursor:pointer; transition:all 0.3s;
        }
        .per-page-selector select:hover, .per-page-selector select:focus { border-color:#667eea; outline:none; }
        .pagination-buttons { display:flex; gap:10px; align-items:center; }
        .pagination-btn {
            padding:8px 15px; border:2px solid #ddd; border-radius:8px; background:white;
            color:#667eea; text-decoration:none; font-weight:500; transition:all 0.3s;
            display:inline-flex; align-items:center; gap:5px; cursor:pointer;
        }
        .pagination-btn:hover:not(.disabled) {
            background:linear-gradient(45deg,#667eea,#764ba2); color:white;
            border-color:transparent; transform:translateY(-2px); box-shadow:0 4px 12px rgba(102,126,234,0.3);
        }
        .pagination-btn.disabled { background:#f5f5f5; color:#ccc; border-color:#f0f0f0; cursor:not-allowed; }
        .page-info { color:#666; font-weight:500; font-size:0.9rem; }

        /* Modal */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; backdrop-filter: blur(4px); }
        .modal-content {
            background: white; max-width: 700px; width: 95%; margin: 40px auto;
            border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); max-height: 85vh; overflow-y: auto;
        }
        .modal-header {
            padding: 18px 24px; border-bottom: 2px solid #f0f0f0;
            display: flex; justify-content: space-between; align-items: center;
            background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 15px 15px 0 0;
        }
        .modal-header h3 { color: white; font-size: 1.1rem; }
        .btn-close { background: none; border: none; color: white; font-size: 1.8rem; cursor: pointer; line-height: 1; }
        .modal-body { padding: 24px; }
        .diff-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .diff-table th { background: #f8f9fa; padding: 8px 12px; text-align: left; font-size: 0.85rem; color: #555; border-bottom: 2px solid #e0e0e0; }
        .diff-table th.th-old { background: #fff0f0; color: #c0392b; }
        .diff-table th.th-new { background: #f0fff4; color: #27ae60; }
        .diff-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; }
        .diff-table td.col-old { background: #fff7f7; color: #888; }
        .diff-table td.col-new { background: #f7fff9; color: #888; }
        .diff-old { background: #ffe0e0 !important; color: #c0392b !important; font-weight: 600; }
        .diff-new { background: #d4f5e2 !important; color: #1e8449 !important; font-weight: 600; }
        .info-block { background: #f8f9fa; border-radius: 8px; padding: 12px; margin-bottom: 12px; font-size: 0.9rem; line-height: 1.6; }
        .info-block strong { color: #333; }

        /* Stats */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 0; }
        .stat { text-align: center; padding: 15px; border-radius: 10px; }
        .stat .num { font-size: 1.8rem; font-weight: bold; }
        .stat .lbl { font-size: 0.8rem; color: #666; text-transform: uppercase; margin-top: 3px; }
        .stat-total { background: linear-gradient(135deg, #667eea22, #764ba222); }
        .stat-create { background: #d4edda; }
        .stat-update { background: #cce5ff; }
        .stat-delete { background: #f8d7da; }

        .clickable-row { cursor: pointer; }
        .clickable-row:hover td { background: rgba(102,126,234,0.08) !important; }
        .desc-cell { max-width: 350px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .has-diff { color: #667eea; font-size: 0.75rem; display: block; margin-top: 2px; }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="card header">
        <h1><i class="fas fa-history" style="color:#667eea;"></i> Logs de Atividade</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="<?= buildUrl(['export' => 'csv', 'page' => '']) ?>" class="btn btn-success">
                <i class="fas fa-file-csv"></i> Exportar CSV
            </a>
            <a href="../../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <!-- Estatísticas do período -->
    <?php
    $stmtStats = $conn->prepare("SELECT
        COUNT(*) as total,
        SUM(action='create') as creates,
        SUM(action='update') as updates,
        SUM(action='delete') as deletes
        FROM activity_logs l WHERE " . $where['clause']);
    $stmtStats->execute($where['params']);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    ?>
    <div class="card">
        <div class="stats">
            <div class="stat stat-total">
                <div class="num" style="color:#667eea;"><?= $stats['total'] ?></div>
                <div class="lbl">Total no período</div>
            </div>
            <div class="stat stat-create">
                <div class="num" style="color:#155724;"><?= $stats['creates'] ?></div>
                <div class="lbl">Criações</div>
            </div>
            <div class="stat stat-update">
                <div class="num" style="color:#004085;"><?= $stats['updates'] ?></div>
                <div class="lbl">Edições</div>
            </div>
            <div class="stat stat-delete">
                <div class="num" style="color:#721c24;"><?= $stats['deletes'] ?></div>
                <div class="lbl">Exclusões</div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card">
        <form method="GET" class="filters">
            <div class="filter-group">
                <label>Módulo</label>
                <select name="module">
                    <option value="">Todos</option>
                    <?php foreach ($moduleLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $module_filter === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Ação</label>
                <select name="action">
                    <option value="">Todas</option>
                    <option value="create" <?= $action_filter === 'create' ? 'selected' : '' ?>>Criar</option>
                    <option value="update" <?= $action_filter === 'update' ? 'selected' : '' ?>>Editar</option>
                    <option value="delete" <?= $action_filter === 'delete' ? 'selected' : '' ?>>Excluir</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Usuário</label>
                <input type="text" name="user" value="<?= htmlspecialchars($user_filter) ?>" placeholder="Nome do usuário" list="users-list">
                <datalist id="users-list">
                    <?php foreach ($users as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="filter-group">
                <label>De</label>
                <input type="date" name="date_from" value="<?= $date_from ?>">
            </div>
            <div class="filter-group">
                <label>Até</label>
                <input type="date" name="date_to" value="<?= $date_to ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filtrar</button>
                <a href="?" class="btn" style="background:#6c757d;"><i class="fas fa-times"></i> Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="card" style="padding:0;overflow:hidden;">
        <?php if (empty($logs)): ?>
        <div class="empty">
            <i class="fas fa-history"></i>
            <p>Nenhum registro encontrado para os filtros selecionados.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Usuário</th>
                    <th>Ação</th>
                    <th>Módulo</th>
                    <th>Descrição</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <?php
                $hasDiff = !empty($log['old_values']) || !empty($log['new_values']);
                $rowAttr = $hasDiff ? 'class="clickable-row" onclick="openDiff(' . $log['id'] . ')"' : '';
            ?>
            <tr <?= $rowAttr ?> title="<?= $hasDiff ? 'Clique para ver o que foi alterado' : '' ?>">
                <td style="white-space:nowrap;font-size:0.85rem;color:#555;"><?= $log['created_fmt'] ?></td>
                <td><strong><?= htmlspecialchars($log['user_name']) ?></strong></td>
                <td>
                    <span class="badge badge-<?= $log['action'] ?>">
                        <i class="fas <?= $actionIcons[$log['action']] ?? 'fa-circle' ?>"></i>
                        <?= $actionLabels[$log['action']] ?? $log['action'] ?>
                    </span>
                </td>
                <td>
                    <span class="badge badge-module">
                        <i class="fas <?= $moduleIcons[$log['module']] ?? 'fa-database' ?>"></i>
                        <?= $moduleLabels[$log['module']] ?? $log['module'] ?>
                    </span>
                </td>
                <td>
                    <div class="desc-cell"><?= htmlspecialchars($log['description']) ?></div>
                    <?php if ($hasDiff): ?>
                    <span class="has-diff"><i class="fas fa-search-plus"></i> Ver alterações</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:0.8rem;color:#888;"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Paginação -->
    <?php if ($total > 0): ?>
    <div class="pagination-container">
        <div class="pagination-info">
            Mostrando <?= min($offset+1, $total) ?> a <?= min($offset+$perPage, $total) ?> de <?= $total ?> registros
        </div>
        <div class="pagination-controls">
            <div class="per-page-selector">
                <label>Registros por página:</label>
                <select onchange="changePerPage(this.value)">
                    <?php foreach ($allowedPerPage as $opt): ?>
                    <option value="<?= $opt ?>" <?= $perPage == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pagination-buttons">
                <?php if ($page > 1): ?>
                <a href="<?= buildUrl(['page' => $page-1]) ?>" class="pagination-btn"><i class="fas fa-chevron-left"></i> Anterior</a>
                <?php else: ?>
                <span class="pagination-btn disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>

                <span class="page-info">Página <?= $page ?> de <?= max(1, $totalPages) ?></span>

                <?php if ($page < $totalPages): ?>
                <a href="<?= buildUrl(['page' => $page+1]) ?>" class="pagination-btn">Próxima <i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                <span class="pagination-btn disabled">Próxima <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de Diff -->
<div id="diffModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt"></i> <span id="diffTitle">Alterações Realizadas</span></h3>
            <button class="btn-close" onclick="closeDiff()">&times;</button>
        </div>
        <div class="modal-body" id="diffBody">
            <p style="color:#aaa;text-align:center;">Carregando...</p>
        </div>
    </div>
</div>

<script>
// Armazenar dados dos logs para o modal (sem AJAX extra)
const logsData = <?php
    $logsForJs = [];
    foreach ($logs as $l) {
        $logsForJs[$l['id']] = [
            'description' => $l['description'],
            'action'      => $l['action'],
            'module'      => $l['module'],
            'user_name'   => $l['user_name'],
            'created_fmt' => $l['created_fmt'],
            'ip_address'  => $l['ip_address'] ?? '',
            'old_values'  => $l['old_values'] ? json_decode($l['old_values'], true) : null,
            'new_values'  => $l['new_values'] ? json_decode($l['new_values'], true) : null,
        ];
    }
    echo json_encode($logsForJs);
?>;

const actionLabels = {create:'Criar', update:'Editar', delete:'Excluir'};
const moduleLabels = {products:'Produtos', customers:'Clientes', service_orders:'Ordens de Serviço', sales:'Vendas', users:'Usuários'};
const actionColors = {create:'#155724', update:'#004085', delete:'#721c24'};

function openDiff(id) {
    const log = logsData[id];
    if (!log) return;

    document.getElementById('diffTitle').textContent =
        (moduleLabels[log.module] || log.module) + ' — ' + (actionLabels[log.action] || log.action);

    let html = `<div class="info-block">
        <strong>Descrição:</strong> ${escHtml(log.description)}<br>
        <strong>Usuário:</strong> ${escHtml(log.user_name)} &nbsp;|&nbsp;
        <strong>Data:</strong> ${escHtml(log.created_fmt)} &nbsp;|&nbsp;
        <strong>IP:</strong> ${escHtml(log.ip_address)}
    </div>`;

    if (log.old_values && log.new_values) {
        const allKeys = [...new Set([...Object.keys(log.old_values), ...Object.keys(log.new_values)])];
        html += `<h4 style="margin-bottom:10px;color:#333;">Alterações (antes → depois):</h4>
        <table class="diff-table">
            <thead><tr><th>Campo</th><th class="th-old">🔴 Antes</th><th class="th-new">🟢 Depois</th></tr></thead>
            <tbody>`;
        allKeys.forEach(key => {
            const oldVal = log.old_values[key] ?? '—';
            const newVal = log.new_values[key] ?? '—';
            const changed = String(oldVal) !== String(newVal);
            html += `<tr>
                <td><strong>${escHtml(key)}</strong></td>
                <td class="col-old ${changed ? 'diff-old' : ''}">${escHtml(String(oldVal))}</td>
                <td class="col-new ${changed ? 'diff-new' : ''}">${escHtml(String(newVal))}</td>
            </tr>`;
        });
        html += `</tbody></table>`;
    } else if (log.old_values) {
        html += `<h4 style="margin-bottom:10px;color:#333;">Dados antes da exclusão:</h4>
        <table class="diff-table"><thead><tr><th>Campo</th><th>Valor</th></tr></thead><tbody>`;
        Object.entries(log.old_values).forEach(([k, v]) => {
            html += `<tr><td><strong>${escHtml(k)}</strong></td><td>${escHtml(String(v ?? ''))}</td></tr>`;
        });
        html += `</tbody></table>`;
    } else if (log.new_values) {
        html += `<h4 style="margin-bottom:10px;color:#333;">Dados registrados:</h4>
        <table class="diff-table"><thead><tr><th>Campo</th><th>Valor</th></tr></thead><tbody>`;
        Object.entries(log.new_values).forEach(([k, v]) => {
            html += `<tr><td><strong>${escHtml(k)}</strong></td><td>${escHtml(String(v ?? ''))}</td></tr>`;
        });
        html += `</tbody></table>`;
    }

    document.getElementById('diffBody').innerHTML = html;
    document.getElementById('diffModal').style.display = 'block';
}

function closeDiff() {
    document.getElementById('diffModal').style.display = 'none';
}

function escHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

window.onclick = e => { if (e.target.id === 'diffModal') closeDiff(); };
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDiff(); });

function changePerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', val);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}
</script>
</body>
</html>
