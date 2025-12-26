<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once('../../config/database.php');
$conn = $database->getConnection();

// =============================
// 📅 FILTROS
// =============================
$report_type = $_GET['type'] ?? 'sales';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Primeiro dia do mês
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Hoje
$period_type = $_GET['period'] ?? 'custom'; // custom, today, week, month, year

// Ajustar datas conforme período selecionado
if ($period_type === 'today') {
    $date_from = $date_to = date('Y-m-d');
} elseif ($period_type === 'week') {
    $date_from = date('Y-m-d', strtotime('-7 days'));
    $date_to = date('Y-m-d');
} elseif ($period_type === 'month') {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-t');
} elseif ($period_type === 'year') {
    $date_from = date('Y-01-01');
    $date_to = date('Y-12-31');
}

// =============================
// 📊 RELATÓRIO DE VENDAS
// =============================
if ($report_type === 'sales') {
    $stmt = $conn->prepare("
        SELECT
            s.*,
            c.name as customer_name,
            u.name as user_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totalizadores
    $total_sales = array_sum(array_column($data, 'final_amount'));
    $total_discount = array_sum(array_column($data, 'discount'));
    $avg_ticket = count($data) > 0 ? $total_sales / count($data) : 0;
}

// =============================
// 💰 RELATÓRIO DE FLUXO DE CAIXA
// =============================
elseif ($report_type === 'cashflow') {
    $stmt = $conn->prepare("
        SELECT
            cf.*,
            u.name as user_name
        FROM cash_flow cf
        LEFT JOIN users u ON cf.user_id = u.id
        WHERE DATE(cf.created_at) BETWEEN ? AND ?
        ORDER BY cf.created_at DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totalizadores
    $total_entries = 0;
    $total_exits = 0;
    foreach ($data as $row) {
        if (in_array($row['type'], ['sale', 'entry'])) {
            $total_entries += $row['amount'];
        } else {
            $total_exits += $row['amount'];
        }
    }
    $balance = $total_entries - $total_exits;
}

// =============================
// 📝 RELATÓRIO DE DESPESAS
// =============================
elseif ($report_type === 'expenses') {
    $stmt = $conn->prepare("
        SELECT
            e.*,
            u.name as user_name
        FROM expenses e
        LEFT JOIN users u ON e.user_id = u.id
        WHERE DATE(e.expense_date) BETWEEN ? AND ?
        ORDER BY e.expense_date DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totalizadores
    $total_paid = 0;
    $total_pending = 0;
    $total_fixed = 0;
    $total_variable = 0;
    foreach ($data as $row) {
        if ($row['status'] === 'pago') {
            $total_paid += $row['amount'];
        } else {
            $total_pending += $row['amount'];
        }
        if ($row['type'] === 'fixa') {
            $total_fixed += $row['amount'];
        } elseif ($row['type'] === 'variavel') {
            $total_variable += $row['amount'];
        }
    }
    $total_expenses = $total_paid + $total_pending;
}

// =============================
// 🔧 RELATÓRIO DE ORDENS DE SERVIÇO
// =============================
elseif ($report_type === 'service_orders') {
    $stmt = $conn->prepare("
        SELECT
            so.*,
            c.name as customer_name,
            u.name as user_name
        FROM service_orders so
        LEFT JOIN customers c ON so.customer_id = c.id
        LEFT JOIN users u ON so.user_id = u.id
        WHERE DATE(so.entry_date) BETWEEN ? AND ?
        ORDER BY so.entry_date DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totalizadores
    $total_os = count($data);
    $total_open = count(array_filter($data, fn($os) => in_array($os['status'], ['open', 'in_progress'])));
    $total_closed = count(array_filter($data, fn($os) => $os['status'] === 'closed'));
    $total_value = array_sum(array_column($data, 'total_cost'));
}

// =============================
// 💳 RELATÓRIO DE CONTAS A RECEBER
// =============================
elseif ($report_type === 'receivables') {
    $stmt = $conn->prepare("
        SELECT
            ar.*,
            c.name as customer_name
        FROM accounts_receivable ar
        LEFT JOIN customers c ON ar.customer_id = c.id
        WHERE DATE(ar.due_date) BETWEEN ? AND ?
        ORDER BY ar.due_date DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totalizadores
    $total_paid = 0;
    $total_pending = 0;
    $total_overdue = 0;
    foreach ($data as $row) {
        if ($row['status'] === 'paid') {
            $total_paid += $row['amount'];
        } elseif ($row['status'] === 'pending') {
            $total_pending += $row['amount'];
        } elseif ($row['status'] === 'overdue') {
            $total_overdue += $row['amount'];
        }
    }
    $total_receivables = $total_paid + $total_pending + $total_overdue;
}

// =============================
// 📈 RELATÓRIO CONSOLIDADO
// =============================
elseif ($report_type === 'consolidated') {
    // Vendas
    $stmt = $conn->prepare("SELECT COALESCE(SUM(final_amount), 0) as total FROM sales WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $total_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Despesas
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE DATE(expense_date) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Contas a Receber
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM accounts_receivable WHERE status = 'paid' AND DATE(payment_date) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $total_received = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // OS
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM service_orders WHERE DATE(entry_date) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $total_os = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $balance = $total_sales + $total_received - $total_expenses;
    $data = [];
}

// =============================
// 🎨 FUNÇÕES AUXILIARES
// =============================
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

function traduzirStatus($status) {
    $map = [
        'pending' => 'Pendente',
        'paid' => 'Pago',
        'overdue' => 'Atrasado',
        'open' => 'Aberta',
        'in_progress' => 'Em Andamento',
        'closed' => 'Fechada',
        'pago' => 'Pago',
        'pendente' => 'Pendente'
    ];
    return $map[$status] ?? ucfirst($status);
}

function traduzirTipo($type) {
    $map = [
        'fixa' => 'Fixa',
        'variavel' => 'Variável',
        'fornecedor' => 'Fornecedor',
        'sale' => 'Venda',
        'entry' => 'Entrada',
        'expense' => 'Despesa'
    ];
    return $map[$type] ?? ucfirst($type);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relatórios - FL Reparos</title>
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
.header, .filters-box, .stats-box, .report-box {
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

.report-tabs {
    display:flex;
    gap:10px;
    margin-bottom:20px;
    flex-wrap:wrap;
}
.tab {
    padding:12px 20px;
    border-radius:8px;
    background:rgba(255,255,255,0.7);
    cursor:pointer;
    transition:all .3s;
    display:flex;
    align-items:center;
    gap:8px;
    font-weight:500;
}
.tab:hover {background:rgba(255,255,255,0.9);transform:translateY(-2px);}
.tab.active {
    background:linear-gradient(45deg,#667eea,#764ba2);
    color:white;
}

.filter-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
    gap:15px;
    margin-bottom:15px;
}
.form-group {margin-bottom:15px;}
.form-group label {display:block;margin-bottom:5px;font-weight:600;color:#333;}
.form-control {
    width:100%;padding:10px;border:2px solid #ddd;border-radius:8px;
    font-size:1rem;transition:border .3s;
}
.form-control:focus {border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,0.1);}

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
.stat-card.blue .icon {color:#2196F3;}
.stat-card.red .icon {color:#f44336;}
.stat-card.orange .icon {color:#FF9800;}
.stat-card.purple .icon {color:#9C27B0;}

.table {
    width:100%;border-collapse:collapse;background:rgba(255,255,255,0.95);
    border-radius:15px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.1);
}
th,td {padding:12px;text-align:left;font-size:0.9rem;}
th {background:linear-gradient(45deg,#f8f9fa,#e9ecef);font-weight:600;}
tr:hover {background:rgba(103,58,183,0.1);}
.badge {
    padding:5px 10px;border-radius:8px;color:white;font-weight:bold;
    font-size:0.85rem;display:inline-block;
}
.badge.paid, .badge.pago {background:#4CAF50;}
.badge.pending, .badge.pendente {background:#FF9800;color:#333;}
.badge.overdue {background:#f44336;}
.badge.open {background:#2196F3;}
.badge.in_progress {background:#FF9800;}
.badge.closed {background:#4CAF50;}
.empty {text-align:center;padding:40px;color:#777;font-style:italic;}

.print-btn {
    position:fixed;
    bottom:30px;
    right:30px;
    width:60px;
    height:60px;
    border-radius:50%;
    background:linear-gradient(45deg,#4CAF50,#45a049);
    color:white;
    border:none;
    font-size:1.5rem;
    cursor:pointer;
    box-shadow:0 4px 15px rgba(0,0,0,0.3);
    transition:all .3s;
}
.print-btn:hover {
    transform:scale(1.1);
    box-shadow:0 6px 20px rgba(0,0,0,0.4);
}

@media print {
    body {background:white;padding:20px;}
    .header, .filters-box {display:none;}
    .print-btn {display:none;}
    .btn {display:none;}
    .stats-box, .report-box {
        box-shadow:none;
        border:1px solid #ddd;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-chart-bar"></i> Relatórios</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="../../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <div class="filters-box">
        <div class="report-tabs">
            <div class="tab <?= $report_type === 'consolidated' ? 'active' : '' ?>" onclick="location.href='?type=consolidated&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>'">
                <i class="fas fa-chart-pie"></i> Consolidado
            </div>
            <div class="tab <?= $report_type === 'sales' ? 'active' : '' ?>" onclick="location.href='?type=sales&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>'">
                <i class="fas fa-shopping-cart"></i> Vendas
            </div>
            <div class="tab <?= $report_type === 'cashflow' ? 'active' : '' ?>" onclick="location.href='?type=cashflow&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>'">
                <i class="fas fa-exchange-alt"></i> Fluxo de Caixa
            </div>
            <div class="tab <?= $report_type === 'expenses' ? 'active' : '' ?>" onclick="location.href='?type=expenses&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>'">
                <i class="fas fa-receipt"></i> Despesas
            </div>
            <div class="tab <?= $report_type === 'service_orders' ? 'active' : '' ?>" onclick="location.href='?type=service_orders&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>'">
                <i class="fas fa-wrench"></i> Ordens de Serviço
            </div>
            <div class="tab <?= $report_type === 'receivables' ? 'active' : '' ?>" onclick="location.href='?type=receivables&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>'">
                <i class="fas fa-credit-card"></i> Contas a Receber
            </div>
        </div>

        <form method="GET" style="margin-top:20px;">
            <input type="hidden" name="type" value="<?= htmlspecialchars($report_type) ?>">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Período Rápido</label>
                    <select name="period" class="form-control" onchange="this.form.submit()">
                        <option value="custom" <?= $period_type === 'custom' ? 'selected' : '' ?>>Personalizado</option>
                        <option value="today" <?= $period_type === 'today' ? 'selected' : '' ?>>Hoje</option>
                        <option value="week" <?= $period_type === 'week' ? 'selected' : '' ?>>Últimos 7 dias</option>
                        <option value="month" <?= $period_type === 'month' ? 'selected' : '' ?>>Mês Atual</option>
                        <option value="year" <?= $period_type === 'year' ? 'selected' : '' ?>>Ano Atual</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data Inicial</label>
                    <input type="date" name="date_from" value="<?= $date_from ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label>Data Final</label>
                    <input type="date" name="date_to" value="<?= $date_to ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-search"></i> Filtrar</button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($report_type === 'consolidated'): ?>
        <div class="stats-box">
            <h2 style="margin-bottom:20px;"><i class="fas fa-chart-pie"></i> Resumo Consolidado - <?= formatDate($date_from) ?> a <?= formatDate($date_to) ?></h2>
            <div class="stats-grid">
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="value">R$ <?= number_format($total_sales, 2, ',', '.') ?></div>
                    <div class="label">Total Vendas</div>
                </div>
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="value">R$ <?= number_format($total_received, 2, ',', '.') ?></div>
                    <div class="label">Total Recebido</div>
                </div>
                <div class="stat-card red">
                    <div class="icon"><i class="fas fa-receipt"></i></div>
                    <div class="value">R$ <?= number_format($total_expenses, 2, ',', '.') ?></div>
                    <div class="label">Total Despesas</div>
                </div>
                <div class="stat-card <?= $balance >= 0 ? 'green' : 'red' ?>">
                    <div class="icon"><i class="fas fa-wallet"></i></div>
                    <div class="value">R$ <?= number_format($balance, 2, ',', '.') ?></div>
                    <div class="label">Saldo Líquido</div>
                </div>
                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-wrench"></i></div>
                    <div class="value"><?= $total_os ?></div>
                    <div class="label">Ordens de Serviço</div>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'sales'): ?>
        <div class="stats-box">
            <div class="stats-grid">
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="value">R$ <?= number_format($total_sales, 2, ',', '.') ?></div>
                    <div class="label">Total em Vendas</div>
                </div>
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="value"><?= count($data) ?></div>
                    <div class="label">Número de Vendas</div>
                </div>
                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-ticket-alt"></i></div>
                    <div class="value">R$ <?= number_format($avg_ticket, 2, ',', '.') ?></div>
                    <div class="label">Ticket Médio</div>
                </div>
                <div class="stat-card red">
                    <div class="icon"><i class="fas fa-tag"></i></div>
                    <div class="value">R$ <?= number_format($total_discount, 2, ',', '.') ?></div>
                    <div class="label">Total Descontos</div>
                </div>
            </div>
        </div>

        <div class="report-box">
            <h2 style="margin-bottom:20px;"><i class="fas fa-list"></i> Detalhamento de Vendas</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Desconto</th>
                        <th>Valor Final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($data)): ?>
                        <tr><td colspan="6" class="empty">Nenhuma venda encontrada no período.</td></tr>
                    <?php else: foreach($data as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= formatDateTime($row['created_at']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name'] ?? 'Não informado') ?></td>
                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                        <td>R$ <?= number_format($row['discount'], 2, ',', '.') ?></td>
                        <td><strong>R$ <?= number_format($row['final_amount'], 2, ',', '.') ?></strong></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($report_type === 'cashflow'): ?>
        <div class="stats-box">
            <div class="stats-grid">
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-arrow-up"></i></div>
                    <div class="value">R$ <?= number_format($total_entries, 2, ',', '.') ?></div>
                    <div class="label">Total Entradas</div>
                </div>
                <div class="stat-card red">
                    <div class="icon"><i class="fas fa-arrow-down"></i></div>
                    <div class="value">R$ <?= number_format($total_exits, 2, ',', '.') ?></div>
                    <div class="label">Total Saídas</div>
                </div>
                <div class="stat-card <?= $balance >= 0 ? 'blue' : 'red' ?>">
                    <div class="icon"><i class="fas fa-wallet"></i></div>
                    <div class="value">R$ <?= number_format($balance, 2, ',', '.') ?></div>
                    <div class="label">Saldo</div>
                </div>
            </div>
        </div>

        <div class="report-box">
            <h2 style="margin-bottom:20px;"><i class="fas fa-exchange-alt"></i> Movimentações de Caixa</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Usuário</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($data)): ?>
                        <tr><td colspan="5" class="empty">Nenhuma movimentação encontrada no período.</td></tr>
                    <?php else: foreach($data as $row): ?>
                    <tr>
                        <td><?= formatDateTime($row['created_at']) ?></td>
                        <td><span class="badge <?= $row['type'] ?>"><?= traduzirTipo($row['type']) ?></span></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                        <td>
                            <?php if (in_array($row['type'], ['sale', 'entry'])): ?>
                                <strong style="color:#4CAF50;">+ R$ <?= number_format($row['amount'], 2, ',', '.') ?></strong>
                            <?php else: ?>
                                <strong style="color:#f44336;">- R$ <?= number_format($row['amount'], 2, ',', '.') ?></strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($report_type === 'expenses'): ?>
        <div class="stats-box">
            <div class="stats-grid">
                <div class="stat-card red">
                    <div class="icon"><i class="fas fa-receipt"></i></div>
                    <div class="value">R$ <?= number_format($total_expenses, 2, ',', '.') ?></div>
                    <div class="label">Total Despesas</div>
                </div>
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="value">R$ <?= number_format($total_paid, 2, ',', '.') ?></div>
                    <div class="label">Total Pago</div>
                </div>
                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <div class="value">R$ <?= number_format($total_pending, 2, ',', '.') ?></div>
                    <div class="label">Total Pendente</div>
                </div>
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-sync"></i></div>
                    <div class="value">R$ <?= number_format($total_fixed, 2, ',', '.') ?></div>
                    <div class="label">Despesas Fixas</div>
                </div>
                <div class="stat-card purple">
                    <div class="icon"><i class="fas fa-random"></i></div>
                    <div class="value">R$ <?= number_format($total_variable, 2, ',', '.') ?></div>
                    <div class="label">Despesas Variáveis</div>
                </div>
            </div>
        </div>

        <div class="report-box">
            <h2 style="margin-bottom:20px;"><i class="fas fa-list"></i> Detalhamento de Despesas</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th>Pagamento</th>
                        <th>Status</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($data)): ?>
                        <tr><td colspan="6" class="empty">Nenhuma despesa encontrada no período.</td></tr>
                    <?php else: foreach($data as $row): ?>
                    <tr>
                        <td><?= formatDate($row['expense_date']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= traduzirTipo($row['type']) ?></td>
                        <td><?= ucfirst($row['payment_method']) ?></td>
                        <td><span class="badge <?= $row['status'] ?>"><?= traduzirStatus($row['status']) ?></span></td>
                        <td><strong>R$ <?= number_format($row['amount'], 2, ',', '.') ?></strong></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($report_type === 'service_orders'): ?>
        <div class="stats-box">
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-wrench"></i></div>
                    <div class="value"><?= $total_os ?></div>
                    <div class="label">Total OS</div>
                </div>
                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <div class="value"><?= $total_open ?></div>
                    <div class="label">Em Aberto</div>
                </div>
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="value"><?= $total_closed ?></div>
                    <div class="label">Finalizadas</div>
                </div>
                <div class="stat-card purple">
                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="value">R$ <?= number_format($total_value, 2, ',', '.') ?></div>
                    <div class="label">Valor Total</div>
                </div>
            </div>
        </div>

        <div class="report-box">
            <h2 style="margin-bottom:20px;"><i class="fas fa-list"></i> Detalhamento de Ordens de Serviço</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data Entrada</th>
                        <th>Cliente</th>
                        <th>Equipamento</th>
                        <th>Status</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($data)): ?>
                        <tr><td colspan="6" class="empty">Nenhuma OS encontrada no período.</td></tr>
                    <?php else: foreach($data as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= formatDate($row['entry_date']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td><?= htmlspecialchars($row['device']) ?></td>
                        <td><span class="badge <?= $row['status'] ?>"><?= traduzirStatus($row['status']) ?></span></td>
                        <td><strong>R$ <?= number_format($row['total_cost'], 2, ',', '.') ?></strong></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($report_type === 'receivables'): ?>
        <div class="stats-box">
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-credit-card"></i></div>
                    <div class="value">R$ <?= number_format($total_receivables, 2, ',', '.') ?></div>
                    <div class="label">Total a Receber</div>
                </div>
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="value">R$ <?= number_format($total_paid, 2, ',', '.') ?></div>
                    <div class="label">Pago</div>
                </div>
                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <div class="value">R$ <?= number_format($total_pending, 2, ',', '.') ?></div>
                    <div class="label">Pendente</div>
                </div>
                <div class="stat-card red">
                    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="value">R$ <?= number_format($total_overdue, 2, ',', '.') ?></div>
                    <div class="label">Atrasado</div>
                </div>
            </div>
        </div>

        <div class="report-box">
            <h2 style="margin-bottom:20px;"><i class="fas fa-list"></i> Detalhamento de Contas a Receber</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Descrição</th>
                        <th>Vencimento</th>
                        <th>Pagamento</th>
                        <th>Status</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($data)): ?>
                        <tr><td colspan="6" class="empty">Nenhuma conta encontrada no período.</td></tr>
                    <?php else: foreach($data as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= formatDate($row['due_date']) ?></td>
                        <td><?= $row['payment_date'] ? formatDate($row['payment_date']) : '-' ?></td>
                        <td><span class="badge <?= $row['status'] ?>"><?= traduzirStatus($row['status']) ?></span></td>
                        <td><strong>R$ <?= number_format($row['amount'], 2, ',', '.') ?></strong></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<button class="print-btn" onclick="window.print()" title="Imprimir Relatório">
    <i class="fas fa-print"></i>
</button>

</body>
</html>
