<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

require_once('../../config/database.php');
require_once('../../config/app.php');
$conn = $database->getConnection();
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

// ── AJAX: detalhamento de vendas por forma de pagamento ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_payment_detail') {
    header('Content-Type: application/json');
    $method   = $_POST['method'] ?? '';
    $from     = $_POST['from']   ?? date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT s.id, s.final_amount, s.created_at, c.name as customer_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.status = 'completed'
          AND s.payment_method = ?
          AND s.created_at >= ?
        ORDER BY s.created_at ASC
    ");
    $stmt->execute([$method, $from]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── AÇÕES POST ────────────────────────────────────────────────────────────────

// Abrir Caixa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'open') {
    $stmt = $conn->prepare("SELECT id FROM cash_register WHERE user_id = ? AND status = 'open'");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetch()) {
        echo "<script>sessionStorage.setItem('fl_flash',JSON.stringify({msg:'Você já possui um caixa aberto!',type:'warning'}));location='index.php';</script>"; exit;
    }
    $stmt = $conn->prepare("INSERT INTO cash_register (user_id,opening_date,opening_balance,status,created_at,updated_at) VALUES (?,NOW(),?,'open',NOW(),NOW())");
    $stmt->execute([$_SESSION['user_id'], $_POST['opening_balance'] ?? 0]);
    echo "<script>sessionStorage.setItem('fl_flash',JSON.stringify({msg:'Caixa aberto com sucesso!',type:'success'}));location='index.php';</script>"; exit;
}

// Fechar Caixa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close') {
    $rid = (int)$_POST['register_id'];
    $stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN type IN('sale','entry','service') THEN amount ELSE 0 END),0) as te, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) as tx FROM cash_flow WHERE created_at>=(SELECT opening_date FROM cash_register WHERE id=?)");
    $stmt->execute([$rid]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $conn->prepare("SELECT opening_balance FROM cash_register WHERE id=?");
    $stmt->execute([$rid]);
    $ob = $stmt->fetchColumn();
    $closing = $ob + $t['te'] - $t['tx'];
    $stmt = $conn->prepare("UPDATE cash_register SET closing_date=NOW(),closing_balance=?,total_sales=?,total_expenses=?,total_cash=?,total_debit_card=?,total_credit_card=?,total_pix=?,observations=?,status='closed',updated_at=NOW() WHERE id=?");
    $stmt->execute([$closing, $t['te'], $t['tx'], $_POST['total_cash']??0, $_POST['total_debit']??0, $_POST['total_credit']??0, $_POST['total_pix']??0, $_POST['observations']??null, $rid]);
    echo "<script>sessionStorage.setItem('fl_flash',JSON.stringify({msg:'Caixa fechado com sucesso!',type:'success'}));location='index.php';</script>"; exit;
}

// Lançar Movimentação Manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_movement') {
    $stmt = $conn->prepare("INSERT INTO cash_flow (user_id,type,description,amount,created_at) VALUES (?,?,?,?,NOW())");
    $stmt->execute([$_SESSION['user_id'], $_POST['type'], $_POST['description'], $_POST['amount']]);
    echo "<script>sessionStorage.setItem('fl_flash',JSON.stringify({msg:'Lançamento registrado com sucesso!',type:'success'}));location='index.php';</script>"; exit;
}

// Reabrir Caixa (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reopen') {
    if (!$isAdmin) { echo "<script>sessionStorage.setItem('fl_flash',JSON.stringify({msg:'Apenas administradores podem reabrir o caixa!',type:'error'}));location='index.php';</script>"; exit; }
    $stmt = $conn->prepare("SELECT id FROM cash_register WHERE user_id=? AND status='open'");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetch()) { echo "<script>sessionStorage.setItem('fl_flash',JSON.stringify({msg:'Já existe um caixa aberto! Feche-o primeiro.',type:'warning'}));location='index.php';</script>"; exit; }
    $stmt = $conn->prepare("UPDATE cash_register SET status='open',closing_date=NULL,closing_balance=NULL,updated_at=NOW() WHERE id=?");
    $stmt->execute([(int)$_POST['register_id']]);
    echo "<script>sessionStorage.setItem('fl_flash',JSON.stringify({msg:'Caixa reaberto com sucesso!',type:'success'}));location='index.php';</script>"; exit;
}

// ── DADOS ─────────────────────────────────────────────────────────────────────

$stmt = $conn->prepare("SELECT * FROM cash_register WHERE user_id=? AND status='open' ORDER BY opening_date DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$reg = $stmt->fetch(PDO::FETCH_ASSOC);

$movements = [];
$total_entries = 0;
$total_exits   = 0;
$current_balance = $reg ? $reg['opening_balance'] : 0;
$payment_breakdown = [];

if ($reg) {
    $stmt = $conn->prepare("SELECT cf.*, u.name as user_name FROM cash_flow cf LEFT JOIN users u ON cf.user_id=u.id WHERE cf.created_at>=? ORDER BY cf.created_at DESC");
    $stmt->execute([$reg['opening_date']]);
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($movements as $m) {
        if (in_array($m['type'], ['sale','entry','service'])) $total_entries += $m['amount'];
        elseif ($m['type'] === 'expense') $total_exits += $m['amount'];
    }
    $current_balance = $reg['opening_balance'] + $total_entries - $total_exits;

    // Filtra movimentações para exibição (remove login/logout do sistema)
    $movements = array_values(array_filter($movements, fn($m) => !in_array($m['type'], ['opening','closing'])));

    // Recebimentos de vendas por forma de pagamento
    $stmt = $conn->prepare("SELECT s.payment_method, COUNT(*) as qty, SUM(s.final_amount) as total FROM sales s WHERE s.status='completed' AND s.created_at>=? GROUP BY s.payment_method ORDER BY total DESC");
    $stmt->execute([$reg['opening_date']]);
    $payment_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Histórico (caixas fechados) – somente admin vê todos, outros veem os próprios
if ($isAdmin) {
    $stmt = $conn->prepare("SELECT cr.*, u.name as user_name FROM cash_register cr LEFT JOIN users u ON cr.user_id=u.id WHERE cr.status='closed' ORDER BY cr.closing_date DESC LIMIT 30");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT cr.*, u.name as user_name FROM cash_register cr LEFT JOIN users u ON cr.user_id=u.id WHERE cr.user_id=? AND cr.status='closed' ORDER BY cr.closing_date DESC LIMIT 30");
    $stmt->execute([$_SESSION['user_id']]);
}
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

function traduzirTipo($t){ return ['sale'=>'Venda','service'=>'Serviço','entry'=>'Entrada','expense'=>'Despesa','opening'=>'Abertura','closing'=>'Fechamento'][$t] ?? ucfirst($t); }
function fmtMoney($v){ return 'R$ '.number_format($v,2,',','.'); }
function payLabel($m){ return ['dinheiro'=>'Dinheiro','pix'=>'PIX','cartao_credito'=>'Cartão Crédito','cartao_debito'=>'Cartão Débito','transferencia'=>'Transferência','a_vista'=>'À Vista'][$m] ?? ucfirst($m); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Abertura/Fechamento de Caixa - FL Reparos</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Tahoma,sans-serif;background:linear-gradient(135deg,#667eea,#764ba2);padding:20px;min-height:100vh;}
.wrap{max-width:1300px;margin:auto;display:flex;flex-direction:column;gap:18px;}

/* Card base */
.card{background:rgba(255,255,255,0.97);border-radius:14px;box-shadow:0 6px 28px rgba(0,0,0,0.13);padding:20px;}

/* Header */
.top-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
.top-header h1{font-size:1.5rem;display:flex;align-items:center;gap:10px;color:#333;}
.ctrl-num{font-size:13px;color:#888;font-weight:600;background:#f0f0f0;padding:4px 12px;border-radius:20px;}
.btn-bar{display:flex;gap:8px;flex-wrap:wrap;}

/* Botões */
.btn{border:none;border-radius:8px;padding:9px 16px;color:#fff;font-weight:600;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:all .25s;text-decoration:none;}
.btn:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(0,0,0,0.2);}
.btn-green {background:linear-gradient(45deg,#00b894,#00a381);}
.btn-blue  {background:linear-gradient(45deg,#2196F3,#1976D2);}
.btn-red   {background:linear-gradient(45deg,#e74c3c,#c0392b);}
.btn-orange{background:linear-gradient(45deg,#FF9800,#F57C00);}
.btn-purple{background:linear-gradient(45deg,#667eea,#764ba2);}
.btn-gray  {background:linear-gradient(45deg,#636e72,#2d3436);}
.btn-sm    {padding:6px 12px;font-size:12px;}

/* Status bar */
.status-bar{display:flex;align-items:center;gap:24px;flex-wrap:wrap;padding:14px 20px;border-radius:10px;}
.status-bar.open  {background:#e8f8f5;border:1.5px solid #00b89455;}
.status-bar.closed{background:#ffeef0;border:1.5px solid #e74c3c55;}
.status-bar.none  {background:#f8f9fa;border:1.5px solid #ddd;}
.status-info{display:flex;flex-direction:column;}
.status-info small{font-size:11px;color:#888;font-weight:600;text-transform:uppercase;}
.status-info strong{font-size:14px;color:#333;}
.situacao-badge{padding:6px 18px;border-radius:20px;font-weight:700;font-size:13px;letter-spacing:.5px;}
.situacao-badge.open  {background:#00b894;color:#fff;}
.situacao-badge.closed{background:#e74c3c;color:#fff;}
.situacao-badge.none  {background:#aaa;color:#fff;}

/* Grid principal: esquerda + direita */
.main-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:800px){.main-grid{grid-template-columns:1fr;}}

.section-title{font-size:14px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;display:flex;align-items:center;gap:8px;}

/* Movimentação */
.mov-table{width:100%;border-collapse:collapse;}
.mov-table td{padding:9px 12px;font-size:13px;border-bottom:1px solid #f0f0f0;}
.mov-table td:last-child{text-align:right;font-weight:700;}
.mov-table .sep td{background:#f8f9fa;font-weight:700;font-size:13px;padding:10px 12px;}
.mov-total td{background:#e8f8f5;font-size:14px;font-weight:700;border-top:2px solid #00b894;}
.mov-total.red td{background:#ffeef0;border-top-color:#e74c3c;}

/* Recebimentos */
.pay-row{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-radius:8px;margin-bottom:6px;background:#f8f9ff;border:1px solid #e8eaf6;cursor:pointer;transition:all .2s;}
.pay-row:hover{background:#ede7f6;border-color:#667eea;}
.pay-row .pay-name{font-size:13px;font-weight:600;color:#333;display:flex;align-items:center;gap:8px;}
.pay-row .pay-val{font-size:15px;font-weight:700;color:#667eea;}
.pay-row .pay-qty{font-size:11px;color:#999;margin-left:6px;}

/* Tabela movimentações (admin) */
.tbl{width:100%;border-collapse:collapse;font-size:13px;}
.tbl th{background:linear-gradient(45deg,#f8f9fa,#e9ecef);padding:10px 12px;text-align:left;font-weight:700;color:#555;}
.tbl td{padding:10px 12px;border-bottom:1px solid #f0f0f0;color:#444;}
.tbl tr:hover td{background:#f8f9ff;}
.badge{padding:4px 10px;border-radius:6px;color:#fff;font-size:12px;font-weight:600;}
.badge.sale,.badge.entry,.badge.service{background:#00b894;}
.badge.expense{background:#e74c3c;}
.badge.opening{background:#2196F3;}
.badge.closing{background:#9C27B0;}

/* Modal */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;backdrop-filter:blur(3px);overflow-y:auto;padding:20px;}
.modal-box{background:#fff;border-radius:16px;max-width:560px;margin:auto;padding:28px;box-shadow:0 16px 48px rgba(0,0,0,.25);animation:mdIn .2s ease;}
.modal-box.wide{max-width:780px;}
@keyframes mdIn{from{transform:scale(.85);opacity:0}to{transform:scale(1);opacity:1}}
.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.modal-head h2{margin:0;font-size:16px;display:flex;align-items:center;gap:9px;}
.btn-close{background:#e74c3c;border:none;color:#fff;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:all .3s;}
.btn-close:hover{transform:rotate(90deg);}
.form-group{margin-bottom:14px;}
.form-group label{display:block;margin-bottom:5px;font-weight:600;font-size:13px;color:#444;}
.form-control{width:100%;padding:10px 12px;border:2px solid #ddd;border-radius:8px;font-size:14px;transition:border .3s;}
.form-control:focus{border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,.1);}
.form-row{display:flex;gap:14px;flex-wrap:wrap;}
.form-row .form-group{flex:1;min-width:140px;}
.info-box{padding:12px 16px;border-radius:8px;font-size:13px;display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.info-box.warn{background:#fff8e1;border:1px solid #ffe082;color:#795548;}
.info-box.info{background:#e8f5e9;border:1px solid #a5d6a7;color:#2e7d32;}

/* Histórico */
.hist-tbl{width:100%;border-collapse:collapse;font-size:13px;}
.hist-tbl th{background:linear-gradient(45deg,#f8f9fa,#e9ecef);padding:10px 12px;text-align:left;font-weight:700;color:#555;}
.hist-tbl td{padding:10px 12px;border-bottom:1px solid #f0f0f0;}
.hist-tbl tr:hover td{background:#f8f9ff;}

.empty{text-align:center;padding:32px;color:#aaa;font-style:italic;}

/* Paginação (mesmo padrão do módulo Produtos) */
.pagination-container{background:rgba(255,255,255,0.9);backdrop-filter:blur(10px);padding:12px 16px;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,0.08);margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
.pagination-info{color:#666;font-size:0.9rem;font-weight:500;}
.pagination-controls{display:flex;gap:16px;align-items:center;}
.pagination-buttons{display:flex;gap:8px;align-items:center;}
.pagination-btn{padding:7px 14px;border:2px solid #ddd;border-radius:8px;background:white;color:#667eea;text-decoration:none;font-weight:500;transition:all .3s;display:inline-flex;align-items:center;gap:5px;cursor:pointer;font-size:13px;}
.pagination-btn:hover:not(.disabled){background:linear-gradient(45deg,#667eea,#764ba2);color:white;border-color:transparent;transform:translateY(-2px);box-shadow:0 4px 12px rgba(102,126,234,0.3);}
.pagination-btn.disabled{background:#f5f5f5;color:#ccc;border-color:#f0f0f0;cursor:not-allowed;}
.page-info{color:#666;font-weight:500;font-size:0.9rem;}
</style>
</head>
<body>
<div class="wrap">

    <!-- ── HEADER ─────────────────────────────────────────────────── -->
    <div class="card top-header">
        <div style="display:flex;align-items:center;gap:14px;">
            <h1><i class="fas fa-cash-register" style="color:#667eea;"></i> Abertura/Fechamento de Caixa</h1>
            <?php if ($reg): ?>
                <span class="ctrl-num">Nº <?= str_pad($reg['id'],4,'0',STR_PAD_LEFT) ?></span>
            <?php endif; ?>
        </div>
        <div class="btn-bar">
            <?php if (!$reg): ?>
                <button class="btn btn-green" onclick="openModal('mdOpen')"><i class="fas fa-unlock"></i> Abrir Novo Caixa</button>
            <?php else: ?>
                <button class="btn btn-orange" onclick="openModal('mdMovement')"><i class="fas fa-plus"></i> Lançar Valor</button>
                <button class="btn btn-red"    onclick="openModal('mdClose')"><i class="fas fa-lock"></i> Fechar Caixa</button>
            <?php endif; ?>
            <button class="btn btn-blue" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Atualizar</button>
            <a href="../../index.php" class="btn btn-gray"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <!-- ── STATUS BAR ─────────────────────────────────────────────── -->
    <div class="card">
        <?php if ($reg): ?>
        <div class="status-bar open">
            <div class="status-info">
                <small>Data Abertura</small>
                <strong><?= date('d/m/Y H:i:s', strtotime($reg['opening_date'])) ?></strong>
            </div>
            <div>
                <span class="situacao-badge open"><i class="fas fa-lock-open"></i> Aberto</span>
            </div>
            <div class="status-info">
                <small>Data Fechamento</small>
                <strong style="color:#aaa;">—</strong>
            </div>
            <div class="status-info" style="margin-left:auto;">
                <small>Operador</small>
                <strong><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></strong>
            </div>
        </div>
        <?php elseif (!empty($history)): ?>
        <?php $last = $history[0]; ?>
        <div class="status-bar closed">
            <div class="status-info">
                <small>Data Abertura</small>
                <strong><?= date('d/m/Y H:i:s', strtotime($last['opening_date'])) ?></strong>
            </div>
            <div>
                <span class="situacao-badge closed"><i class="fas fa-lock"></i> Fechado</span>
            </div>
            <div class="status-info">
                <small>Data Fechamento</small>
                <strong><?= date('d/m/Y H:i:s', strtotime($last['closing_date'])) ?></strong>
            </div>
        </div>
        <?php else: ?>
        <div class="status-bar none">
            <span class="situacao-badge none"><i class="fas fa-minus-circle"></i> Nenhum caixa registrado</span>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($reg): ?>
    <!-- ── GRID PRINCIPAL ─────────────────────────────────────────── -->
    <div class="main-grid">

        <!-- Movimentação -->
        <div class="card">
            <div class="section-title"><i class="fas fa-exchange-alt" style="color:#667eea;"></i> Movimentação</div>
            <table class="mov-table">
                <tr>
                    <td>Caixa Inicial/Reforço</td>
                    <td style="color:#2196F3;"><?= fmtMoney($reg['opening_balance']) ?></td>
                </tr>
                <?php
                $manualEntries  = array_sum(array_column(array_filter($movements, fn($m) => $m['type']==='entry'),  'amount'));
                $manualExpenses = array_sum(array_column(array_filter($movements, fn($m) => $m['type']==='expense'), 'amount'));
                $salesTotal     = array_sum(array_column(array_filter($movements, fn($m) => $m['type']==='sale'),    'amount'));
                $serviceTotal   = array_sum(array_column(array_filter($movements, fn($m) => $m['type']==='service'),'amount'));
                ?>
                <tr><td>Vendas PDV</td><td style="color:#00b894;"><?= fmtMoney($salesTotal) ?></td></tr>
                <tr><td>Serviços</td><td style="color:#00b894;"><?= fmtMoney($serviceTotal) ?></td></tr>
                <tr><td>Entradas Manuais</td><td style="color:#00b894;"><?= fmtMoney($manualEntries) ?></td></tr>
                <tr class="sep"><td colspan="2" style="color:#e74c3c;"><i class="fas fa-arrow-down"></i> Saídas</td></tr>
                <tr><td>Despesas / Saídas</td><td style="color:#e74c3c;"><?= fmtMoney($manualExpenses) ?></td></tr>
                <tr class="mov-total">
                    <td><i class="fas fa-arrow-up"></i> Total Entradas</td>
                    <td style="color:#00b894;"><?= fmtMoney($total_entries) ?></td>
                </tr>
                <tr class="mov-total red">
                    <td><i class="fas fa-arrow-down"></i> Total Saídas</td>
                    <td style="color:#e74c3c;"><?= fmtMoney($total_exits) ?></td>
                </tr>
                <tr style="background:#667eea10;">
                    <td style="font-weight:700;font-size:14px;padding:12px;">Saldo em Caixa</td>
                    <td style="font-weight:700;font-size:16px;color:#667eea;padding:12px;"><?= fmtMoney($current_balance) ?></td>
                </tr>
            </table>
        </div>

        <!-- Recebimentos de Vendas -->
        <div class="card">
            <div class="section-title"><i class="fas fa-credit-card" style="color:#667eea;"></i> Recebimentos de Vendas</div>
            <?php if (empty($payment_breakdown)): ?>
                <p class="empty">Nenhuma venda registrada neste período.</p>
            <?php else: ?>
                <?php
                $payIcons = ['dinheiro'=>'fa-money-bill-wave','pix'=>'fa-qrcode','cartao_credito'=>'fa-credit-card','cartao_debito'=>'fa-credit-card','transferencia'=>'fa-exchange-alt','a_vista'=>'fa-money-bill'];
                foreach ($payment_breakdown as $pb):
                    $icon = $payIcons[$pb['payment_method']] ?? 'fa-coins';
                ?>
                <div class="pay-row" onclick="showPaymentDetail('<?= htmlspecialchars($pb['payment_method']) ?>','<?= htmlspecialchars(payLabel($pb['payment_method'])) ?>','<?= $reg['opening_date'] ?>')" title="Clique para ver detalhes das vendas">
                    <div class="pay-name">
                        <i class="fas <?= $icon ?>" style="color:#667eea;width:18px;text-align:center;"></i>
                        <?= payLabel($pb['payment_method']) ?>
                        <span class="pay-qty"><?= $pb['qty'] ?> venda(s)</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="pay-val"><?= fmtMoney($pb['total']) ?></span>
                        <i class="fas fa-search" style="color:#aaa;font-size:12px;"></i>
                    </div>
                </div>
                <?php endforeach; ?>
                <div style="display:flex;justify-content:space-between;padding:12px;background:#f0f0ff;border-radius:8px;margin-top:8px;font-weight:700;font-size:14px;">
                    <span>Total Recebido</span>
                    <span style="color:#667eea;"><?= fmtMoney(array_sum(array_column($payment_breakdown,'total'))) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── MOVIMENTAÇÕES DETALHADAS (admin) ───────────────────────── -->
    <?php if ($isAdmin): ?>
    <div class="card">
        <div class="section-title"><i class="fas fa-list-alt" style="color:#667eea;"></i> Movimentações Detalhadas <span style="font-size:11px;background:#667eea22;color:#667eea;padding:2px 8px;border-radius:10px;margin-left:6px;">Admin</span></div>
        <table class="tbl" id="movTbl">
            <thead>
                <tr><th>Data/Hora</th><th>Tipo</th><th>Descrição</th><th style="text-align:right;">Valor</th></tr>
            </thead>
            <tbody id="movTblBody">
                <tr><td colspan="4" class="empty">Carregando...</td></tr>
            </tbody>
        </table>
        <div id="movPagination"></div>
    </div>
    <script>
    (function(){
        var _movData = <?= json_encode(array_map(function($m){
            return [
                date('d/m/Y H:i', strtotime($m['created_at'])),
                $m['type'],
                htmlspecialchars($m['description'] ?? ''),
                $m['amount'],
                in_array($m['type'],['sale','entry','service']) ? 1 : 0
            ];
        }, $movements)) ?>;

        var _typeLabels = {sale:'Venda',service:'Serviço',entry:'Entrada',expense:'Despesa'};
        var _page = 1, _pageSize = 15;

        function render(){
            var total = _movData.length;
            var totalPages = Math.max(1, Math.ceil(total / _pageSize));
            if (_page > totalPages) _page = totalPages;
            var start = (_page - 1) * _pageSize;
            var slice = _movData.slice(start, start + _pageSize);
            var tbody = document.getElementById('movTblBody');
            if (!total) {
                tbody.innerHTML = '<tr><td colspan="4" class="empty">Nenhuma movimentação registrada.</td></tr>';
                document.getElementById('movPagination').innerHTML = '';
                return;
            }
            tbody.innerHTML = slice.map(function(m){
                var isEntry = m[4];
                var color = isEntry ? '#00b894' : '#e74c3c';
                var signal = isEntry ? '+' : '-';
                var label = _typeLabels[m[1]] || m[1];
                var amt = parseFloat(m[3]).toFixed(2).replace('.',',');
                return '<tr>' +
                    '<td>'+m[0]+'</td>' +
                    '<td><span class="badge '+m[1]+'">'+label+'</span></td>' +
                    '<td>'+m[2]+'</td>' +
                    '<td style="text-align:right;font-weight:700;color:'+color+';">'+signal+' R$ '+amt+'</td>' +
                '</tr>';
            }).join('');

            var from = start + 1, to = Math.min(start + _pageSize, total);
            var prevDis = _page <= 1 ? ' disabled' : '';
            var nextDis = _page >= totalPages ? ' disabled' : '';
            document.getElementById('movPagination').innerHTML =
                '<div class="pagination-container">' +
                    '<span class="pagination-info">Mostrando '+from+' a '+to+' de '+total+' movimentações</span>' +
                    '<div class="pagination-controls">' +
                        '<div class="pagination-buttons">' +
                            '<button class="pagination-btn'+prevDis+'" onclick="if('+(_page>1?1:0)+')_movGoPage('+(_page-1)+')"><i class="fas fa-chevron-left"></i> Anterior</button>' +
                            '<span class="page-info">Página '+_page+' de '+totalPages+'</span>' +
                            '<button class="pagination-btn'+nextDis+'" onclick="if('+(_page<totalPages?1:0)+')_movGoPage('+(_page+1)+')">Próxima <i class="fas fa-chevron-right"></i></button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        }

        window._movGoPage = function(p){ _page = p; render(); document.getElementById('movTbl').scrollIntoView({behavior:'smooth',block:'nearest'}); };
        render();
    })();
    </script>
    <?php endif; ?>

    <?php else: ?>
    <!-- caixa fechado -->
    <div class="card" style="text-align:center;padding:50px;">
        <i class="fas fa-lock" style="font-size:4rem;color:#ddd;display:block;margin-bottom:16px;"></i>
        <h2 style="color:#999;margin-bottom:10px;">Caixa Fechado</h2>
        <p style="color:#bbb;margin-bottom:24px;">Clique em "Abrir Novo Caixa" para iniciar as movimentações do dia.</p>
        <button class="btn btn-green" onclick="openModal('mdOpen')"><i class="fas fa-unlock"></i> Abrir Novo Caixa</button>
    </div>
    <?php endif; ?>

    <!-- ── HISTÓRICO ──────────────────────────────────────────────── -->
    <div class="card">
        <div class="section-title"><i class="fas fa-history" style="color:#667eea;"></i> Histórico de Fechamentos</div>
        <table class="hist-tbl">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Abertura</th>
                    <th>Fechamento</th>
                    <?php if($isAdmin): ?><th>Operador</th><?php endif; ?>
                    <th>Saldo Inicial</th>
                    <th>Total Vendas</th>
                    <th>Total Despesas</th>
                    <th>Saldo Final</th>
                    <?php if($isAdmin): ?><th>Ações</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="9" class="empty">Nenhum fechamento registrado.</td></tr>
                <?php else: foreach ($history as $h): ?>
                <tr>
                    <td><strong><?= str_pad($h['id'],4,'0',STR_PAD_LEFT) ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($h['opening_date'])) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($h['closing_date'])) ?></td>
                    <?php if($isAdmin): ?><td><?= htmlspecialchars($h['user_name']) ?></td><?php endif; ?>
                    <td><?= fmtMoney($h['opening_balance']) ?></td>
                    <td style="color:#00b894;font-weight:700;"><?= fmtMoney($h['total_sales']) ?></td>
                    <td style="color:#e74c3c;font-weight:700;"><?= fmtMoney($h['total_expenses']) ?></td>
                    <td style="font-weight:700;"><?= fmtMoney($h['closing_balance']) ?></td>
                    <?php if($isAdmin): ?>
                    <td>
                        <form method="POST" id="rfForm_<?= $h['id'] ?>" onsubmit="return false;">
                            <input type="hidden" name="action" value="reopen">
                            <input type="hidden" name="register_id" value="<?= $h['id'] ?>">
                            <button type="button" class="btn btn-sm btn-orange" title="Reabrir" onclick="showConfirm('Reabrir o caixa Nº <?= str_pad($h['id'],4,'0',STR_PAD_LEFT) ?>?','Reabrir Caixa','Reabrir','Cancelar','warning').then(ok=>{if(ok)document.getElementById('rfForm_<?= $h['id'] ?>').submit();})">
                                <i class="fas fa-lock-open"></i> Reabrir
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- /wrap -->

<!-- ── MODAL ABRIR CAIXA ─────────────────────────────────────────── -->
<div id="mdOpen" class="modal">
    <div class="modal-box">
        <div class="modal-head">
            <h2><i class="fas fa-unlock" style="color:#00b894;"></i> Abrir Novo Caixa</h2>
            <button class="btn-close" onclick="closeModal('mdOpen')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="open">
            <div class="form-group">
                <label>Saldo Inicial / Caixa Inicial (R$)</label>
                <input type="number" step="0.01" name="opening_balance" class="form-control" value="0" placeholder="0,00" required autofocus>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
                <button type="button" class="btn btn-gray" onclick="closeModal('mdOpen')"><i class="fas fa-times"></i> Cancelar</button>
                <button type="submit" class="btn btn-green"><i class="fas fa-unlock"></i> Abrir Caixa</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL LANÇAR VALOR ─────────────────────────────────────────── -->
<div id="mdMovement" class="modal">
    <div class="modal-box">
        <div class="modal-head">
            <h2><i class="fas fa-plus" style="color:#FF9800;"></i> Lançar Valor no Caixa</h2>
            <button class="btn-close" onclick="closeModal('mdMovement')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_movement">
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="type" class="form-control" required>
                        <option value="">Selecione</option>
                        <option value="entry">Entrada / Reforço</option>
                        <option value="expense">Saída / Despesa / Sangria</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Valor (R$) *</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0,00" min="0.01">
                </div>
            </div>
            <div class="form-group">
                <label>Descrição *</label>
                <input type="text" name="description" class="form-control" required placeholder="Ex: Sangria, Reforço de troco, Pagamento fornecedor...">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
                <button type="button" class="btn btn-gray" onclick="closeModal('mdMovement')"><i class="fas fa-times"></i> Cancelar</button>
                <button type="submit" class="btn btn-orange"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL FECHAR CAIXA ─────────────────────────────────────────── -->
<div id="mdClose" class="modal">
    <div class="modal-box">
        <div class="modal-head">
            <h2><i class="fas fa-lock" style="color:#e74c3c;"></i> Fechar Caixa</h2>
            <button class="btn-close" onclick="closeModal('mdClose')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="close">
            <input type="hidden" name="register_id" value="<?= $reg['id'] ?? '' ?>">

            <div class="info-box info">
                <i class="fas fa-wallet" style="font-size:1.3rem;"></i>
                <div><strong>Saldo calculado do sistema: <?= fmtMoney($current_balance) ?></strong><br><small>Confira os valores físicos por forma de pagamento abaixo.</small></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Total em Dinheiro (R$)</label>
                    <input type="number" step="0.01" name="total_cash" class="form-control" placeholder="0,00" value="0">
                </div>
                <div class="form-group">
                    <label>Total em PIX (R$)</label>
                    <input type="number" step="0.01" name="total_pix" class="form-control" placeholder="0,00" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Cartão Débito (R$)</label>
                    <input type="number" step="0.01" name="total_debit" class="form-control" placeholder="0,00" value="0">
                </div>
                <div class="form-group">
                    <label>Cartão Crédito (R$)</label>
                    <input type="number" step="0.01" name="total_credit" class="form-control" placeholder="0,00" value="0">
                </div>
            </div>
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observations" class="form-control" rows="2" placeholder="Informações adicionais sobre o fechamento..."></textarea>
            </div>

            <div class="info-box warn">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Esta ação irá fechar o caixa. Confirme os valores antes de prosseguir.</span>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px;">
                <button type="button" class="btn btn-gray" onclick="closeModal('mdClose')"><i class="fas fa-times"></i> Cancelar</button>
                <button type="button" class="btn btn-red" onclick="showConfirm('Confirma o fechamento do caixa?','Fechar Caixa','Fechar','Cancelar','warning').then(ok=>{if(ok)this.closest('form').submit();})">
                    <i class="fas fa-lock"></i> Fechar Caixa
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL DETALHAMENTO PAGAMENTO ──────────────────────────────── -->
<div id="mdPayDetail" class="modal">
    <div class="modal-box wide">
        <div class="modal-head">
            <h2 id="mdPayDetailTitle"><i class="fas fa-search" style="color:#667eea;"></i> Detalhes das Vendas</h2>
            <button class="btn-close" onclick="closeModal('mdPayDetail')">&times;</button>
        </div>
        <div id="mdPayDetailBody" style="min-height:100px;">
            <div style="text-align:center;padding:40px;color:#aaa;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i></div>
        </div>
    </div>
</div>

<script src="../../assets/js/main.js"></script>
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; document.getElementById(id).style.alignItems = 'flex-start'; document.getElementById(id).style.paddingTop = '40px'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.addEventListener('click', e => { if (e.target.classList.contains('modal')) closeModal(e.target.id); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal').forEach(m => m.style.display = 'none'); });

function showPaymentDetail(method, label, fromDate) {
    document.getElementById('mdPayDetailTitle').innerHTML = `<i class="fas fa-search" style="color:#667eea;"></i> Detalhes — ${label}`;
    document.getElementById('mdPayDetailBody').innerHTML = '<div style="text-align:center;padding:40px;color:#aaa;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i></div>';
    openModal('mdPayDetail');

    const fd = new FormData();
    fd.append('action', 'get_payment_detail');
    fd.append('method', method);
    fd.append('from', fromDate);

    fetch('index.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(sales => {
            if (!sales.length) {
                document.getElementById('mdPayDetailBody').innerHTML = '<p style="text-align:center;color:#aaa;padding:30px;">Nenhuma venda encontrada.</p>';
                return;
            }
            let total = 0;
            let rows = sales.map(s => {
                total += parseFloat(s.final_amount);
                return `<tr>
                    <td><strong>#${String(s.id).padStart(6,'0')}</strong></td>
                    <td>${s.customer_name ? s.customer_name : '<span style="color:#ccc;">—</span>'}</td>
                    <td>${new Date(s.created_at).toLocaleString('pt-BR')}</td>
                    <td style="text-align:right;font-weight:700;color:#667eea;">R$ ${parseFloat(s.final_amount).toFixed(2).replace('.',',')}</td>
                </tr>`;
            }).join('');

            document.getElementById('mdPayDetailBody').innerHTML = `
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:#555;">Nº Venda</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:#555;">Cliente</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:#555;">Data/Hora</th>
                            <th style="padding:10px 12px;text-align:right;font-weight:700;color:#555;">Total</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                    <tfoot>
                        <tr style="background:#667eea12;">
                            <td colspan="3" style="padding:12px;font-weight:700;">Total — ${sales.length} venda(s)</td>
                            <td style="padding:12px;text-align:right;font-weight:700;color:#667eea;font-size:15px;">R$ ${total.toFixed(2).replace('.',',')}</td>
                        </tr>
                    </tfoot>
                </table>`;
        })
        .catch(() => { document.getElementById('mdPayDetailBody').innerHTML = '<p style="color:red;padding:20px;">Erro ao carregar detalhes.</p>'; });
}
</script>
</body>
</html>
