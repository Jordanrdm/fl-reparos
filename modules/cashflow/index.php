<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once('../../config/database.php');
$conn = $database->getConnection();

// =============================
// 🔧 CONTROLE DE CAIXA
// =============================

// Abrir Caixa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'open') {
    try {
        // Verificar se já existe caixa aberto
        $stmt = $conn->prepare("SELECT id FROM cash_register WHERE user_id = ? AND status = 'open'");
        $stmt->execute([$_SESSION['user_id']]);

        if ($stmt->fetch()) {
            echo "<script>alert('Você já possui um caixa aberto!');window.location='index.php';</script>";
            exit;
        }

        // Abrir novo caixa
        $stmt = $conn->prepare("INSERT INTO cash_register
            (user_id, opening_date, opening_balance, status, created_at, updated_at)
            VALUES (?, NOW(), ?, 'open', NOW(), NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['opening_balance'] ?? 0
        ]);

        echo "<script>alert('Caixa aberto com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao abrir caixa: " . $e->getMessage() . "');</script>";
    }
}

// Fechar Caixa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'close') {
    try {
        $register_id = $_POST['register_id'];

        // Buscar totais do período
        $stmt = $conn->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type IN ('sale', 'entry', 'service') THEN amount ELSE 0 END), 0) as total_sales,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expenses
            FROM cash_flow cf
            WHERE cf.created_at >= (SELECT opening_date FROM cash_register WHERE id = ?)
        ");
        $stmt->execute([$register_id]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        // Buscar saldo inicial
        $stmt = $conn->prepare("SELECT opening_balance FROM cash_register WHERE id = ?");
        $stmt->execute([$register_id]);
        $opening = $stmt->fetch(PDO::FETCH_ASSOC);

        $closing_balance = $opening['opening_balance'] + $totals['total_sales'] - $totals['total_expenses'];

        // Fechar caixa
        $stmt = $conn->prepare("UPDATE cash_register
            SET closing_date = NOW(),
                closing_balance = ?,
                total_sales = ?,
                total_expenses = ?,
                total_cash = ?,
                total_card = ?,
                total_pix = ?,
                observations = ?,
                status = 'closed',
                updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([
            $closing_balance,
            $totals['total_sales'],
            $totals['total_expenses'],
            $_POST['total_cash'] ?? 0,
            $_POST['total_card'] ?? 0,
            $_POST['total_pix'] ?? 0,
            $_POST['observations'] ?? null,
            $register_id
        ]);

        echo "<script>alert('Caixa fechado com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao fechar caixa: " . $e->getMessage() . "');</script>";
    }
}

// Adicionar Movimentação Manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_movement') {
    try {
        $stmt = $conn->prepare("INSERT INTO cash_flow
            (user_id, type, description, amount, created_at)
            VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['type'],
            $_POST['description'],
            $_POST['amount']
        ]);

        echo "<script>alert('Movimentação adicionada com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao adicionar movimentação: " . $e->getMessage() . "');</script>";
    }
}

// Reabrir Caixa (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'reopen') {
    try {
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            echo "<script>alert('Apenas administradores podem reabrir caixa!');window.location='index.php';</script>";
            exit;
        }

        // Verificar se já existe caixa aberto
        $stmt = $conn->prepare("SELECT id FROM cash_register WHERE user_id = ? AND status = 'open'");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            echo "<script>alert('Já existe um caixa aberto! Feche-o primeiro.');window.location='index.php';</script>";
            exit;
        }

        $register_id = $_POST['register_id'];
        $stmt = $conn->prepare("UPDATE cash_register SET status = 'open', closing_date = NULL, closing_balance = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$register_id]);

        echo "<script>alert('Caixa reaberto com sucesso!');window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao reabrir caixa: " . $e->getMessage() . "');</script>";
    }
}

// =============================
// 🔍 BUSCAR CAIXA ATUAL
// =============================
$stmt = $conn->prepare("
    SELECT * FROM cash_register
    WHERE user_id = ? AND status = 'open'
    ORDER BY opening_date DESC LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$current_register = $stmt->fetch(PDO::FETCH_ASSOC);

// =============================
// 📊 MOVIMENTAÇÕES DO DIA
// =============================
$movements = [];
if ($current_register) {
    $stmt = $conn->prepare("
        SELECT cf.*, u.name as user_name
        FROM cash_flow cf
        LEFT JOIN users u ON cf.user_id = u.id
        WHERE cf.created_at >= ?
        ORDER BY cf.created_at DESC
    ");
    $stmt->execute([$current_register['opening_date']]);
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =============================
// 📈 TOTAIS DO CAIXA ATUAL
// =============================
$total_entries = 0;
$total_exits = 0;
$current_balance = $current_register ? $current_register['opening_balance'] : 0;

if ($current_register) {
    foreach ($movements as $mov) {
        if (in_array($mov['type'], ['sale', 'entry', 'service'])) {
            $total_entries += $mov['amount'];
        } else if ($mov['type'] === 'expense') {
            $total_exits += $mov['amount'];
        }
    }
    $current_balance = $current_register['opening_balance'] + $total_entries - $total_exits;
}

// =============================
// 💳 DETALHAMENTO POR FORMA DE PAGAMENTO
// =============================
$payment_breakdown = [];
if ($current_register) {
    $stmt = $conn->prepare("
        SELECT s.payment_method, COUNT(*) as qty, SUM(s.final_amount) as total
        FROM sales s
        WHERE s.status = 'completed' AND s.created_at >= ?
        GROUP BY s.payment_method
        ORDER BY total DESC
    ");
    $stmt->execute([$current_register['opening_date']]);
    $payment_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =============================
// 📜 HISTÓRICO DE CAIXAS
// =============================
$stmt = $conn->prepare("
    SELECT cr.*, u.name as user_name
    FROM cash_register cr
    LEFT JOIN users u ON cr.user_id = u.id
    WHERE cr.status = 'closed'
    ORDER BY cr.closing_date DESC
    LIMIT 20
");
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================
// 🎨 FUNÇÕES AUXILIARES
// =============================
function traduzirTipo($type) {
    $map = [
        'sale' => 'Venda',
        'service' => 'Serviço',
        'entry' => 'Entrada',
        'expense' => 'Despesa',
        'opening' => 'Abertura',
        'closing' => 'Fechamento'
    ];
    return $map[$type] ?? ucfirst($type);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Abertura/Fechamento de Caixa - FL Reparos</title>
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
.header, .cash-status, .movements-box, .history-box {
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

.status-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
    gap:20px;
    margin-top:20px;
}
.status-card {
    background:linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
    padding:20px;
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,0.1);
    text-align:center;
}
.status-card .icon {
    font-size:2.5rem;
    margin-bottom:10px;
}
.status-card .value {
    font-size:1.8rem;
    font-weight:bold;
    margin:5px 0;
}
.status-card .label {
    color:#666;
    font-size:0.9rem;
    text-transform:uppercase;
}
.status-card.green .icon {color:#4CAF50;}
.status-card.blue .icon {color:#2196F3;}
.status-card.red .icon {color:#f44336;}
.status-card.orange .icon {color:#FF9800;}

.cash-closed {
    text-align:center;
    padding:40px;
    color:#666;
}
.cash-closed i {
    font-size:4rem;
    color:#ccc;
    margin-bottom:20px;
}

.table {
    width:100%;border-collapse:collapse;background:rgba(255,255,255,0.95);
    border-radius:15px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.1);
}
th,td {padding:12px;text-align:left;}
th {background:linear-gradient(45deg,#f8f9fa,#e9ecef);font-weight:600;}
tr:hover {background:rgba(103,58,183,0.1);}
.badge {
    padding:5px 10px;border-radius:8px;color:white;font-weight:bold;
    font-size:0.85rem;display:inline-block;
}
.badge.sale, .badge.entry, .badge.service {background:#4CAF50;}
.badge.expense {background:#f44336;}
.badge.opening {background:#2196F3;}
.badge.closing {background:#9C27B0;}

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
.form-group {flex:1;min-width:200px;}
.form-group label {display:block;margin-bottom:5px;font-weight:600;color:#333;}
.form-control {
    width:100%;padding:10px;border:2px solid #ddd;border-radius:8px;
    font-size:1rem;transition:border .3s;
}
.form-control:focus {border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,0.1);}
.empty {text-align:center;padding:40px;color:#777;font-style:italic;}

.alert {
    padding:15px 20px;
    border-radius:8px;
    margin-bottom:20px;
    display:flex;
    align-items:center;
    gap:10px;
}
.alert-success {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.alert-warning {background:#fff3cd;color:#856404;border:1px solid #ffeeba;}
.alert-danger {background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-cash-register"></i> Abertura/Fechamento de Caixa</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <?php if (!$current_register): ?>
                <button class="btn btn-primary" onclick="openModal('openCashModal')"><i class="fas fa-unlock"></i> Abrir Caixa</button>
            <?php else: ?>
                <button class="btn btn-warning" onclick="openModal('addMovementModal')"><i class="fas fa-plus"></i> Adicionar Movimentação</button>
                <button class="btn btn-danger" onclick="openModal('closeCashModal')"><i class="fas fa-lock"></i> Fechar Caixa</button>
            <?php endif; ?>
            <a href="../../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if ($current_register): ?>
        <div class="cash-status">
            <div class="alert alert-success">
                <i class="fas fa-check-circle" style="font-size:1.5rem;"></i>
                <div>
                    <strong>Caixa Aberto</strong> -
                    Aberto em <?= date('d/m/Y \à\s H:i', strtotime($current_register['opening_date'])) ?>
                </div>
            </div>

            <div class="status-grid">
                <div class="status-card blue">
                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="value">R$ <?= number_format($current_register['opening_balance'], 2, ',', '.') ?></div>
                    <div class="label">Saldo Inicial</div>
                </div>
                <div class="status-card green">
                    <div class="icon"><i class="fas fa-arrow-up"></i></div>
                    <div class="value">R$ <?= number_format($total_entries, 2, ',', '.') ?></div>
                    <div class="label">Total Entradas</div>
                </div>
                <div class="status-card red">
                    <div class="icon"><i class="fas fa-arrow-down"></i></div>
                    <div class="value">R$ <?= number_format($total_exits, 2, ',', '.') ?></div>
                    <div class="label">Total Saídas</div>
                </div>
                <div class="status-card orange">
                    <div class="icon"><i class="fas fa-wallet"></i></div>
                    <div class="value">R$ <?= number_format($current_balance, 2, ',', '.') ?></div>
                    <div class="label">Saldo Atual</div>
                </div>
            </div>
        </div>

        <?php if (!empty($payment_breakdown)): ?>
        <div class="movements-box" style="margin-bottom:20px;">
            <h2 style="margin-bottom:20px;"><i class="fas fa-credit-card"></i> Vendas por Forma de Pagamento</h2>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:15px;">
                <?php foreach ($payment_breakdown as $pb): ?>
                <div style="background:linear-gradient(135deg, #e8f8f5, #d5f5e3); padding:15px; border-radius:10px; text-align:center;">
                    <div style="font-size:13px; color:#666; margin-bottom:5px;"><?= htmlspecialchars($pb['payment_method'] ?: 'Não informado') ?></div>
                    <div style="font-size:22px; font-weight:bold; color:#00b894;">R$ <?= number_format($pb['total'], 2, ',', '.') ?></div>
                    <div style="font-size:11px; color:#999; margin-top:3px;"><?= $pb['qty'] ?> venda(s)</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="movements-box">
            <h2 style="margin-bottom:20px;"><i class="fas fa-exchange-alt"></i> Movimentações do Caixa</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($movements)): ?>
                        <tr><td colspan="4" class="empty">Nenhuma movimentação registrada.</td></tr>
                    <?php else: foreach($movements as $mov): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($mov['created_at'])) ?></td>
                        <td><span class="badge <?= $mov['type'] ?>"><?= traduzirTipo($mov['type']) ?></span></td>
                        <td><?= htmlspecialchars($mov['description']) ?></td>
                        <td>
                            <?php if (in_array($mov['type'], ['sale', 'entry', 'service'])): ?>
                                <strong style="color:#4CAF50;">+ R$ <?= number_format($mov['amount'], 2, ',', '.') ?></strong>
                            <?php else: ?>
                                <strong style="color:#f44336;">- R$ <?= number_format($mov['amount'], 2, ',', '.') ?></strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="cash-status">
            <div class="cash-closed">
                <i class="fas fa-lock"></i>
                <h2>Caixa Fechado</h2>
                <p>Clique em "Abrir Caixa" para iniciar um novo período de movimentações.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="history-box">
        <h2 style="margin-bottom:20px;"><i class="fas fa-history"></i> Histórico de Fechamentos</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Período</th>
                    <th>Usuário</th>
                    <th>Saldo Inicial</th>
                    <th>Total Vendas</th>
                    <th>Total Despesas</th>
                    <th>Saldo Final</th>
                    <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?><th>Ações</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($history)): ?>
                    <tr><td colspan="7" class="empty">Nenhum fechamento de caixa registrado.</td></tr>
                <?php else: foreach($history as $h): ?>
                <tr>
                    <td>
                        <?= date('d/m/Y H:i', strtotime($h['opening_date'])) ?><br>
                        <small style="color:#666;">até <?= date('d/m/Y H:i', strtotime($h['closing_date'])) ?></small>
                    </td>
                    <td><?= htmlspecialchars($h['user_name']) ?></td>
                    <td>R$ <?= number_format($h['opening_balance'], 2, ',', '.') ?></td>
                    <td style="color:#4CAF50;font-weight:bold;">R$ <?= number_format($h['total_sales'], 2, ',', '.') ?></td>
                    <td style="color:#f44336;font-weight:bold;">R$ <?= number_format($h['total_expenses'], 2, ',', '.') ?></td>
                    <td style="font-weight:bold;">R$ <?= number_format($h['closing_balance'], 2, ',', '.') ?></td>
                    <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja reabrir este caixa?')">
                            <input type="hidden" name="action" value="reopen">
                            <input type="hidden" name="register_id" value="<?= $h['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="background:#ff7675; color:white; border:none; padding:5px 10px; border-radius:5px; cursor:pointer;" title="Reabrir caixa">
                                <i class="fas fa-lock-open"></i>
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ABRIR CAIXA -->
<div id="openCashModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-unlock"></i> Abrir Caixa</h2>
            <button class="close" onclick="closeModal('openCashModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="open">
            <div class="form-group">
                <label>Saldo Inicial (R$) *</label>
                <input type="number" step="0.01" name="opening_balance" class="form-control" required placeholder="0,00" value="0">
            </div>
            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-unlock"></i> Abrir Caixa</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('openCashModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL FECHAR CAIXA -->
<div id="closeCashModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-lock"></i> Fechar Caixa</h2>
            <button class="close" onclick="closeModal('closeCashModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="close">
            <input type="hidden" name="register_id" value="<?= $current_register['id'] ?? '' ?>">

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>Informe os totais por forma de pagamento para conferência do caixa.</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Total em Dinheiro (R$)</label>
                    <input type="number" step="0.01" name="total_cash" class="form-control" placeholder="0,00" value="0">
                </div>
                <div class="form-group">
                    <label>Total em Cartão (R$)</label>
                    <input type="number" step="0.01" name="total_card" class="form-control" placeholder="0,00" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Total em PIX (R$)</label>
                    <input type="number" step="0.01" name="total_pix" class="form-control" placeholder="0,00" value="0">
                </div>
            </div>
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observations" class="form-control" rows="3" placeholder="Informações adicionais sobre o fechamento..."></textarea>
            </div>

            <div class="alert alert-danger">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Saldo Final Calculado:</strong> R$ <?= number_format($current_balance, 2, ',', '.') ?>
                </div>
            </div>

            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja fechar o caixa?')">
                    <i class="fas fa-lock"></i> Fechar Caixa
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('closeCashModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ADICIONAR MOVIMENTAÇÃO -->
<div id="addMovementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus"></i> Adicionar Movimentação</h2>
            <button class="close" onclick="closeModal('addMovementModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_movement">
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="type" class="form-control" required>
                        <option value="">Selecione</option>
                        <option value="entry">Entrada</option>
                        <option value="expense">Saída/Despesa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Valor (R$) *</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0,00">
                </div>
            </div>
            <div class="form-group">
                <label>Descrição *</label>
                <input type="text" name="description" class="form-control" required placeholder="Descrição da movimentação...">
            </div>
            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addMovementModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).style.display='block';}
function closeModal(id){document.getElementById(id).style.display='none';}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Atalho ESC para fechar modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('openCashModal');
        closeModal('closeCashModal');
        closeModal('addMovementModal');
    }
});
</script>

</body>
</html>
