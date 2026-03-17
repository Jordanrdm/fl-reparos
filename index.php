<?php
/**
 * FL REPAROS - Dashboard Principal
 * Máximo: 200 linhas
 */

include_once 'config/app.php';

// Verificar se usuário está logado
requireAuth();

// Buscar estatísticas do dashboard
try {
    // Vendas de hoje
    $today = date('Y-m-d');
    $stmt = $database->query(
        "SELECT COALESCE(SUM(final_amount), 0) as total_sales 
         FROM sales 
         WHERE DATE(created_at) = ?",
        [$today]
    );
    $todaySales = $stmt->fetch()['total_sales'];

    // Caixa atual (últimos movimentos)
    $stmt = $database->query(
        "SELECT COALESCE(SUM(
            CASE 
                WHEN type IN ('sale', 'service') THEN amount 
                WHEN type = 'expense' THEN -amount 
                ELSE amount 
            END
        ), 0) as cash_balance 
        FROM cash_flow 
        WHERE DATE(created_at) = ?",
        [$today]
    );
    $currentCash = $stmt->fetch()['cash_balance'];

    // OS Abertas
    $stmt = $database->query(
        "SELECT COUNT(*) as open_orders 
         FROM service_orders 
         WHERE status IN ('open', 'in_progress')"
    );
    $openOrders = $stmt->fetch()['open_orders'];

    // Total de clientes
    $stmt = $database->query("SELECT COUNT(*) as total_customers FROM customers");
    $totalCustomers = $stmt->fetch()['total_customers'];

    // Status do caixa atual
    $stmt = $database->query(
        "SELECT id, status, opening_balance, opening_date, closing_date, closing_balance
         FROM cash_register
         WHERE user_id = ?
         ORDER BY id DESC LIMIT 1",
        [$_SESSION['user_id']]
    );
    $cashRegister = $stmt->fetch();

} catch (Exception $e) {
    // Valores padrão em caso de erro
    $todaySales = 0;
    $currentCash = 0;
    $openOrders = 0;
    $totalCustomers = 0;
}

$pageTitle = 'Dashboard';
$pageCSS = 'dashboard';
$pageJS = 'dashboard';

include 'includes/header.php';
?>

<div class="container">

    <!-- Header do Dashboard -->
    <div class="dashboard-header">
        <h1><i class="fas fa-tachometer-alt"></i> Painel de Controle</h1>
        <p>Visão geral do sistema - <?php echo formatDate(date('Y-m-d')); ?></p>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card" style="position:relative;">
            <div class="stat-icon sales">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value" id="val-sales"><?php echo formatMoney($todaySales); ?></div>
            <div class="stat-label" style="display:flex;align-items:center;justify-content:center;gap:6px;">
                Vendas Hoje
                <button onclick="toggleValue('sales')" id="eye-sales" title="Mostrar/Ocultar" style="background:none;border:none;cursor:pointer;color:#999;font-size:14px;padding:0;line-height:1;"><i class="fas fa-eye"></i></button>
            </div>
            <?php if(isAdmin()): ?>
                <button type="button" id="btn-zero-sales" title="Zerar exibição de vendas de hoje" onclick="zeroDisplay('sales')" style="position:absolute;top:10px;right:10px;background:#f44336;color:#fff;border:none;border-radius:50%;width:30px;height:30px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-trash-alt"></i>
                </button>
            <?php endif; ?>
        </div>

        <div class="stat-card" style="position:relative;">
            <div class="stat-icon money">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-value" id="val-cash"><?php echo formatMoney($currentCash); ?></div>
            <div class="stat-label" style="display:flex;align-items:center;justify-content:center;gap:6px;">
                Caixa Atual
                <button onclick="toggleValue('cash')" id="eye-cash" title="Mostrar/Ocultar" style="background:none;border:none;cursor:pointer;color:#999;font-size:14px;padding:0;line-height:1;"><i class="fas fa-eye"></i></button>
            </div>
            <?php
            $caixaStatus = $cashRegister['status'] ?? null;
            $caixaColor  = $caixaStatus === 'open'  ? '#00b894' : ($caixaStatus === 'closed' ? '#e74c3c' : '#aaa');
            $caixaLabel  = $caixaStatus === 'open'  ? 'Aberto'  : ($caixaStatus === 'closed' ? 'Fechado' : 'Sem caixa');
            $caixaIcon   = $caixaStatus === 'open'  ? 'fa-lock-open' : 'fa-lock';
            ?>
            <div style="margin-top:6px;">
                <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $caixaColor ?>22;color:<?= $caixaColor ?>;border:1px solid <?= $caixaColor ?>55;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;">
                    <i class="fas <?= $caixaIcon ?>"></i> <?= $caixaLabel ?>
                </span>
            </div>
            <?php if(isAdmin()): ?>
                <button type="button" id="btn-zero-cash" title="Zerar exibição do caixa atual" onclick="zeroDisplay('cash')" style="position:absolute;top:10px;right:10px;background:#f44336;color:#fff;border:none;border-radius:50%;width:30px;height:30px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-trash-alt"></i>
                </button>
            <?php endif; ?>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orders">
                <i class="fas fa-tools"></i>
            </div>
            <div class="stat-value"><?php echo $openOrders; ?></div>
            <div class="stat-label">OS Abertas</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon clients">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo $totalCustomers; ?></div>
            <div class="stat-label">Clientes</div>
        </div>
    </div>

    <!-- Grid de Módulos -->
    <div class="modules-grid">
        <?php if(canViewModule('pdv')): ?>
        <div class="module-card" onclick="location.href='modules/pdv/'">
            <div class="keyboard-shortcut">F3</div>
            <div class="module-icon pdv">
                <i class="fas fa-cash-register"></i>
            </div>
            <div class="module-title">PDV Rápido</div>
            <div class="module-description">Tela de vendas completa com desconto, formas de pagamento e impressão de cupom</div>
        </div>
        <?php endif; ?>

        <?php if(canViewModule('service_orders')): ?>
        <div class="module-card" onclick="location.href='modules/service_orders/index.php'">
            <div class="keyboard-shortcut">F5</div>
            <div class="module-icon os">
                <i class="fas fa-wrench"></i>
            </div>
            <div class="module-title">Ordem de Serviço</div>
            <div class="module-description">Controle completo de OS: abertura, acompanhamento e finalização de serviços</div>
        </div>
        <?php endif; ?>

        <?php if(canViewModule('products')): ?>
        <div class="module-card" onclick="location.href='modules/products/'">
            <div class="keyboard-shortcut">F2</div>
            <div class="module-icon products">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <div class="module-title">Produtos</div>
            <div class="module-description">Cadastro de produtos, controle de estoque, códigos de barra e categorias</div>
        </div>
        <?php endif; ?>

        <?php if(canViewModule('customers')): ?>
        <div class="module-card" onclick="location.href='modules/customers/'">
            <div class="module-icon clients">
                <i class="fas fa-address-book"></i>
            </div>
            <div class="module-title">Clientes</div>
            <div class="module-description">Cadastro completo de clientes com histórico de compras e serviços</div>
        </div>
        <?php endif; ?>

        <?php if(canViewModule('accounts_receivable')): ?>
        <div class="module-card" onclick="location.href='modules/accounts_receivable/index.php'">
            <div class="module-icon finance">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="module-title">Contas a Receber</div>
            <div class="module-description">Controle de parcelas, vendas a prazo e relatórios de recebimento</div>
        </div>
        <?php endif; ?>

        <?php if(canViewModule('expenses')): ?>
        <div class="module-card" onclick="location.href='modules/expenses/index.php'">
            <div class="module-icon expenses">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="module-title">Despesas</div>
            <div class="module-description">Lançamento de despesas fixas, variáveis e compras de fornecedores</div>
        </div>
        <?php endif; ?>

        <?php if(canViewModule('cashflow')): ?>
        <div class="module-card" onclick="location.href='modules/cashflow/index.php'">
            <div class="keyboard-shortcut">F4</div>
            <div class="module-icon cashflow">
                <i class="fas fa-cash-register"></i>
            </div>
            <div class="module-title">Abertura/Fechamento</div>
            <div class="module-description">Controle diário de caixa com relatório de movimentação</div>
        </div>
        <?php endif; ?>

        <?php if(canViewModule('reports')): ?>
        <div class="module-card" onclick="location.href='modules/reports/index.php'">
            <div class="module-icon reports">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="module-title">Relatórios</div>
            <div class="module-description">Fluxo de caixa, vendas por período e exportação para PDF/Excel</div>
        </div>
        <?php endif; ?>

        <?php if(canViewModule('users')): ?>
        <div class="module-card" onclick="location.href='modules/users/index.php'">
            <div class="module-icon users">
                <i class="fas fa-users-cog"></i>
            </div>
            <div class="module-title">Usuários</div>
            <div class="module-description">Gerenciar usuários e permissões do sistema</div>
        </div>
        <?php endif; ?>

        <?php if(isAdmin()): ?>
        <div class="module-card" onclick="location.href='modules/logs/index.php'">
            <div class="module-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                <i class="fas fa-history"></i>
            </div>
            <div class="module-title">Logs de Atividade</div>
            <div class="module-description">Histórico completo de alterações feitas no sistema</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Botão Flutuante -->
<div class="quick-actions">
    <button class="fab" onclick="location.href='modules/pdv/'" title="Venda Rápida (F3)">
        <i class="fas fa-plus"></i>
    </button>
</div>

<script>
const _hidden = {};
const _realValues = {
    sales: '<?= addslashes(formatMoney($todaySales)) ?>',
    cash:  '<?= addslashes(formatMoney($currentCash)) ?>'
};

function toggleValue(key) {
    const valEl = document.getElementById('val-' + key);
    const eyeEl = document.getElementById('eye-' + key);
    if (!valEl || !eyeEl) return;

    _hidden[key] = !_hidden[key];

    if (_hidden[key]) {
        valEl.textContent = '••••••';
        valEl.style.letterSpacing = '4px';
        eyeEl.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        valEl.textContent = _realValues[key];
        valEl.style.letterSpacing = '';
        eyeEl.innerHTML = '<i class="fas fa-eye"></i>';
    }
    try { sessionStorage.setItem('fl_hide_' + key, _hidden[key] ? '1' : '0'); } catch(e) {}
}

function zeroDisplay(key) {
    const labels = { sales: 'Vendas de Hoje', cash: 'Caixa Atual' };
    showConfirm(
        'Deseja zerar a exibição de <strong>' + labels[key] + '</strong> no dashboard?\n\nOs registros reais permanecem inalterados.',
        'Zerar exibição',
        'Zerar', 'Cancelar', 'danger'
    ).then(ok => {
        if (!ok) return;
        _realValues[key] = 'R$ 0,00';
        _hidden[key] = false;
        try { sessionStorage.setItem('fl_zero_' + key, '1'); sessionStorage.removeItem('fl_hide_' + key); } catch(e) {}
        const valEl = document.getElementById('val-' + key);
        const eyeEl = document.getElementById('eye-' + key);
        if (valEl) { valEl.textContent = 'R$ 0,00'; valEl.style.letterSpacing = ''; }
        if (eyeEl) { eyeEl.innerHTML = '<i class="fas fa-eye"></i>'; }
        showAlert(labels[key] + ' zerado no dashboard.', 'success');
    });
}

// Restaurar estado ao carregar
document.addEventListener('DOMContentLoaded', function() {
    // Limpar zeros de dias anteriores
    const today = '<?= date('Y-m-d') ?>';
    try {
        if (sessionStorage.getItem('fl_zero_date') !== today) {
            sessionStorage.removeItem('fl_zero_sales');
            sessionStorage.removeItem('fl_zero_cash');
            sessionStorage.setItem('fl_zero_date', today);
        }
    } catch(e) {}

    ['sales','cash'].forEach(function(key) {
        try {
            const zeroed = sessionStorage.getItem('fl_zero_' + key) === '1';
            const hidden = sessionStorage.getItem('fl_hide_' + key) === '1';
            const valEl = document.getElementById('val-' + key);
            const eyeEl = document.getElementById('eye-' + key);
            if (!valEl || !eyeEl) return;
            if (zeroed) {
                _realValues[key] = 'R$ 0,00';
                valEl.textContent = 'R$ 0,00';
                valEl.style.letterSpacing = '';
                eyeEl.innerHTML = '<i class="fas fa-eye"></i>';
            } else if (hidden) {
                _hidden[key] = true;
                valEl.textContent = '••••••';
                valEl.style.letterSpacing = '4px';
                eyeEl.innerHTML = '<i class="fas fa-eye-slash"></i>';
            }
        } catch(e) {}
    });
});

document.addEventListener('keydown', function(e) {
    // Ignorar se estiver digitando em input/textarea
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
    switch (e.key) {
        case 'F2': e.preventDefault(); location.href = 'modules/products/'; break;
        case 'F3': e.preventDefault(); location.href = 'modules/pdv/'; break;
        case 'F4': e.preventDefault(); location.href = 'modules/cashflow/index.php'; break;
        case 'F5': e.preventDefault(); location.href = 'modules/service_orders/index.php'; break;
    }
});
</script>

<?php include 'includes/footer.php'; ?>