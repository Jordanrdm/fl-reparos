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
 * Mostrar alerta dinâmico
 */
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `message ${type}`;
    alertDiv.innerHTML = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';
    alertDiv.style.opacity = '0';
    alertDiv.style.transform = 'translateY(-20px)';
    alertDiv.style.transition = 'all 0.3s ease';
    alertDiv.style.padding = '15px';
    alertDiv.style.borderRadius = '8px';
    alertDiv.style.cursor = 'pointer';
    
    // Estilo baseado no tipo
    if (type === 'success') {
        alertDiv.style.background = '#d4edda';
        alertDiv.style.color = '#155724';
        alertDiv.style.border = '1px solid #c3e6cb';
    } else if (type === 'error') {
        alertDiv.style.background = '#f8d7da';
        alertDiv.style.color = '#721c24';
        alertDiv.style.border = '1px solid #f5c6cb';
    } else if (type === 'warning') {
        alertDiv.style.background = '#fff3cd';
        alertDiv.style.color = '#856404';
        alertDiv.style.border = '1px solid #ffeaa7';
    } else {
        alertDiv.style.background = '#d1ecf1';
        alertDiv.style.color = '#0c5460';
        alertDiv.style.border = '1px solid #bee5eb';
    }
    
    document.body.appendChild(alertDiv);
    
    // Animação de entrada
    setTimeout(() => {
        alertDiv.style.opacity = '1';
        alertDiv.style.transform = 'translateY(0)';
    }, 100);
    
    // Auto-hide após 4 segundos
    setTimeout(() => hideAlert(alertDiv), 4000);
    
    // Click para fechar
    alertDiv.addEventListener('click', () => hideAlert(alertDiv));
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
    formatMoney,
    formatDate,
    validateCPF,
    maskCPF,
    maskPhone
};