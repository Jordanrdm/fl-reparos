/**
 * FL REPAROS - JavaScript Base do Sistema
 * Máximo: 200 linhas
 */

// Inicialização quando DOM carrega
document.addEventListener('DOMContentLoaded', function() {
    initializeSystem();
    setupKeyboardShortcuts();
    setupAnimations();
    setupAlerts();
    setupModuleNavigation();
});

/**
 * Inicialização do sistema
 */
function initializeSystem() {
    console.log('FL REPAROS - Sistema iniciado');
    
    // Verificar se usuário está ativo (heartbeat)
    setInterval(checkUserSession, 300000); // 5 minutos
    
    // Auto-hide alerts após 5 segundos
    setTimeout(hideAlerts, 5000);
}

/**
 * Configurar atalhos de teclado
 */
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Não executar se estiver digitando em inputs
        if (e.target.tagName === 'INPUT' || 
            e.target.tagName === 'TEXTAREA' || 
            e.target.tagName === 'SELECT') {
            return;
        }
        
        // IMPORTANTE: Só processar F2 se estivermos no dashboard
        const currentPath = window.location.pathname;
        const isOnDashboard = currentPath.includes('index.php') || currentPath === '/' || currentPath.endsWith('/');
        
        switch(e.key) {
            case 'F2':
                e.preventDefault();
                // Só redirecionar se estivermos no dashboard
                if (isOnDashboard) {
                    console.log('F2 pressionado - navegando para produtos');
                    window.location.href = 'modules/products/index.php';
                } else {
                    console.log('F2 ignorado - não estamos no dashboard');
                }
                break;
            case 'F3':
                e.preventDefault();
                if (isOnDashboard) {
                    showAlert('Módulo PDV em desenvolvimento...', 'info');
                }
                break;
            case 'F4':
                e.preventDefault();
                if (isOnDashboard) {
                    showAlert('Módulo Caixa em desenvolvimento...', 'info');
                }
                break;
            case 'F5':
                e.preventDefault();
                if (isOnDashboard) {
                    showAlert('Módulo Ordem de Serviço em desenvolvimento...', 'info');
                }
                break;
            case 'Escape':
                // ESC para voltar ao dashboard (apenas se não estivermos nele)
                if (!isOnDashboard) {
                    window.location.href = 'index.php';
                }
                break;
        }
    });
}

/**
 * Configurar navegação dos módulos
 */
function setupModuleNavigation() {
    // Configurar cliques nos cards do dashboard
    const moduleCards = document.querySelectorAll('.module-card');
    
    if (moduleCards.length >= 3) {
        // Card de Produtos (3º card - índice 2)
        const productsCard = moduleCards[2];
        
        // Remover onclick anterior se existir
        productsCard.removeAttribute('onclick');
        
        // Adicionar novo event listener
        productsCard.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Card produtos clicado');
            window.location.href = 'modules/products/index.php';
        });
        
        // Adicionar cursor pointer para indicar que é clicável
        productsCard.style.cursor = 'pointer';
    }
    
    // Configurar outros cards que já existem
    if (moduleCards.length > 0) {
        moduleCards.forEach((card, index) => {
            if (index !== 2) { // Não o card de produtos
                card.addEventListener('click', function() {
                    const moduleMap = {
                        0: 'PDV Rápido',
                        1: 'Ordem de Serviço',
                        3: 'Clientes',
                        4: 'Contas a Receber',
                        5: 'Despesas',
                        6: 'Caixa',
                        7: 'Relatórios'
                    };
                    
                    const moduleName = moduleMap[index];
                    if (moduleName) {
                        showAlert(`Módulo ${moduleName} em desenvolvimento...`, 'info');
                    }
                });
            }
        });
    }
}

/**
 * Configurar animações dos cards
 */
function setupAnimations() {
    const cards = document.querySelectorAll('.module-card, .stat-card');
    
    cards.forEach(card => {
        // Animação ao passar mouse
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
        
        // Efeito de clique
        card.addEventListener('mousedown', function() {
            this.style.transform = 'translateY(-4px) scale(0.98)';
        });
        
        card.addEventListener('mouseup', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
    });
}

/**
 * Gerenciar alertas
 */
function setupAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Tornar alerts clicáveis para fechar
        alert.style.cursor = 'pointer';
        alert.title = 'Clique para fechar';
        
        alert.addEventListener('click', function() {
            hideAlert(this);
        });
    });
}

/**
 * Esconder alerta específico
 */
function hideAlert(alertElement) {
    alertElement.style.opacity = '0';
    alertElement.style.transform = 'translateY(-10px)';
    
    setTimeout(() => {
        if (alertElement.parentNode) {
            alertElement.parentNode.removeChild(alertElement);
        }
    }, 300);
}

/**
 * Esconder todos os alertas
 */
function hideAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => hideAlert(alert), Math.random() * 1000);
    });
}

/**
 * Verificar sessão do usuário
 */
function checkUserSession() {
    fetch('api/check-session.php')
        .then(response => response.json())
        .then(data => {
            if (!data.authenticated) {
                showAlert('Sessão expirada. Redirecionando...', 'warning');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 2000);
            }
        })
        .catch(error => {
            console.warn('Erro ao verificar sessão:', error);
        });
}

/**
 * Sistema de Notificações Customizadas FL Reparos
 */

// Verificar flash messages do sessionStorage ao carregar
document.addEventListener('DOMContentLoaded', function() {
    const flash = sessionStorage.getItem('fl_flash');
    if (flash) {
        sessionStorage.removeItem('fl_flash');
        try {
            const d = JSON.parse(flash);
            setTimeout(() => showAlert(d.msg, d.type || 'info'), 300);
        } catch(e) {}
    }
});

function showAlert(message, type = 'info', duration = 4000) {
    const icons = {
        success: 'fas fa-check-circle',
        error:   'fas fa-times-circle',
        warning: 'fas fa-exclamation-triangle',
        info:    'fas fa-info-circle'
    };
    const gradients = {
        success: 'linear-gradient(135deg,#00b894,#00a381)',
        error:   'linear-gradient(135deg,#e74c3c,#c0392b)',
        warning: 'linear-gradient(135deg,#FF9800,#F57C00)',
        info:    'linear-gradient(135deg,#667eea,#764ba2)'
    };

    let container = document.getElementById('fl-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'fl-toast-container';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:999999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const bg = gradients[type] || gradients.info;
    const icon = icons[type] || icons.info;

    toast.style.cssText = `background:${bg};color:white;padding:12px 14px 12px 16px;border-radius:10px;display:flex;align-items:center;gap:10px;min-width:280px;max-width:400px;box-shadow:0 6px 24px rgba(0,0,0,0.25);pointer-events:all;cursor:pointer;transform:translateX(120%);transition:transform 0.35s cubic-bezier(0.34,1.56,0.64,1),opacity 0.35s ease;opacity:0;position:relative;overflow:hidden;`;

    toast.innerHTML = `
        <i class="${icon}" style="font-size:20px;flex-shrink:0;"></i>
        <span style="flex:1;font-size:13px;font-weight:500;line-height:1.4;">${message}</span>
        <button onclick="event.stopPropagation();this.closest('[data-fl-toast]').remove();" style="background:rgba(255,255,255,0.25);border:none;color:white;border-radius:50%;width:22px;height:22px;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;line-height:1;">&times;</button>
        <div style="position:absolute;bottom:0;left:0;height:3px;background:rgba(255,255,255,0.45);animation:fl-progress ${duration}ms linear forwards;"></div>
    `;
    toast.setAttribute('data-fl-toast', '1');

    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.style.transform = 'translateX(0)';
        toast.style.opacity = '1';
    });

    const hide = () => {
        toast.style.transform = 'translateX(120%)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 350);
    };
    toast.addEventListener('click', hide);
    setTimeout(hide, duration);
}

function showConfirm(message, title = 'Confirmar', confirmText = 'Confirmar', cancelText = 'Cancelar', type = 'warning') {
    return new Promise(resolve => {
        const iconMap = {
            warning: { icon: 'fas fa-exclamation-triangle', color: '#FF9800' },
            danger:  { icon: 'fas fa-trash-alt',            color: '#e74c3c' },
            info:    { icon: 'fas fa-question-circle',      color: '#667eea' },
            success: { icon: 'fas fa-check-circle',         color: '#00b894' }
        };
        const t = iconMap[type] || iconMap.warning;

        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.55);z-index:9999999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(3px);';

        overlay.innerHTML = `
            <div style="background:white;border-radius:16px;padding:28px 24px;max-width:420px;width:92%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:fl-confirm-in 0.25s cubic-bezier(0.34,1.56,0.64,1);">
                <div style="width:60px;height:60px;border-radius:50%;background:${t.color}22;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                    <i class="${t.icon}" style="font-size:26px;color:${t.color};"></i>
                </div>
                <h3 style="margin:0 0 8px;font-size:17px;color:#2d3436;font-weight:700;">${title}</h3>
                <p style="margin:0 0 22px;font-size:13px;color:#636e72;line-height:1.6;">${message}</p>
                <div style="display:flex;gap:12px;justify-content:center;">
                    <button id="fl-cancel" style="padding:9px 28px;border:1.5px solid #dfe6e9;background:white;border-radius:8px;cursor:pointer;font-size:13px;color:#636e72;font-weight:500;transition:all 0.2s;">${cancelText}</button>
                    <button id="fl-ok" style="padding:9px 28px;border:none;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;box-shadow:0 4px 15px rgba(102,126,234,0.4);">${confirmText}</button>
                </div>
            </div>`;

        document.body.appendChild(overlay);

        const close = (result) => { overlay.remove(); resolve(result); };
        overlay.querySelector('#fl-ok').addEventListener('click', () => close(true));
        overlay.querySelector('#fl-cancel').addEventListener('click', () => close(false));
        overlay.addEventListener('click', e => { if (e.target === overlay) close(false); });
        document.addEventListener('keydown', function esc(e) {
            if (e.key === 'Escape') { document.removeEventListener('keydown', esc); close(false); }
        });
    });
}

/**
 * Formatar moeda brasileira
 */
function formatMoney(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

/**
 * Formatar data brasileira
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

/**
 * Validar CPF
 */
function validateCPF(cpf) {
    cpf = cpf.replace(/[^\d]+/g, '');
    
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
        return false;
    }
    
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(cpf.charAt(i)) * (10 - i);
    }
    
    let remainder = (sum * 10) % 11;
    if (remainder === 10 || remainder === 11) remainder = 0;
    if (remainder !== parseInt(cpf.charAt(9))) return false;
    
    sum = 0;
    for (let i = 0; i < 10; i++) {
        sum += parseInt(cpf.charAt(i)) * (11 - i);
    }
    
    remainder = (sum * 10) % 11;
    if (remainder === 10 || remainder === 11) remainder = 0;
    
    return remainder === parseInt(cpf.charAt(10));
}

/**
 * Máscara para CPF
 */
function maskCPF(value) {
    return value
        .replace(/\D/g, '')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})/, '$1-$2')
        .replace(/(-\d{2})\d+?$/, '$1');
}

/**
 * Máscara para telefone
 */
function maskPhone(value) {
    return value
        .replace(/\D/g, '')
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{4})(\d)/, '$1-$2')
        .replace(/(\d{4})-(\d)(\d{4})/, '$1$2-$3')
        .replace(/(-\d{4})\d+?$/, '$1');
}

// Expor funções globalmente
window.FL_REPAROS = {
    showAlert,
    showConfirm,
    formatMoney,
    formatDate,
    validateCPF,
    maskCPF,
    maskPhone
};