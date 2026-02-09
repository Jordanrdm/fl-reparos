<?php
/**
 * FL REPAROS - Sistema de Permissões por Perfil
 * Define o que cada perfil pode acessar e fazer
 */

// Configuração de permissões por perfil
$PERMISSIONS = [
    'admin' => [
        'pdv' => ['view', 'create', 'edit', 'delete'],
        'service_orders' => ['view', 'create', 'edit', 'delete'],
        'products' => ['view', 'create', 'edit', 'delete'],
        'customers' => ['view', 'create', 'edit', 'delete'],
        'expenses' => ['view', 'create', 'edit', 'delete'],
        'cashflow' => ['view', 'create', 'edit', 'delete'],
        'accounts_receivable' => ['view', 'create', 'edit', 'delete'],
        'reports' => ['view'],
        'users' => ['view', 'create', 'edit', 'delete'],
    ],
    'manager' => [
        'pdv' => ['view', 'create', 'edit'],
        'service_orders' => ['view', 'create', 'edit'],
        'products' => ['view', 'create', 'edit'],
        'customers' => ['view', 'create', 'edit'],
        'cashflow' => ['view', 'create'],
        'accounts_receivable' => ['view', 'create', 'edit'],
        'reports' => ['view'],
    ],
    'seller' => [
        'pdv' => ['view', 'create', 'edit', 'delete'],  // Pode fazer vendas completas
        'service_orders' => ['view', 'create', 'edit'],  // Pode criar e editar OS (sem deletar)
        'products' => ['view', 'create'],  // Pode visualizar e cadastrar produtos (sem editar/deletar)
        'customers' => ['view', 'create', 'edit'],  // Pode visualizar, criar e editar clientes (sem deletar)
    ]
];

// Módulos visíveis no dashboard por perfil
$VISIBLE_MODULES = [
    'admin' => ['pdv', 'service_orders', 'products', 'customers', 'accounts_receivable', 'expenses', 'cashflow', 'reports', 'users'],
    'manager' => ['pdv', 'service_orders', 'products', 'customers', 'accounts_receivable', 'cashflow', 'reports'],
    'seller' => ['pdv', 'service_orders', 'products', 'customers']
];

/**
 * Verifica se o usuário tem permissão para uma ação em um módulo
 *
 * @param string $module Nome do módulo (ex: 'customers', 'products')
 * @param string $action Ação desejada ('view', 'create', 'edit', 'delete')
 * @return bool
 */
function hasPermission($module, $action = 'view') {
    global $PERMISSIONS;

    if (!isset($_SESSION['user_role'])) {
        return false;
    }

    $role = $_SESSION['user_role'];

    // Admin sempre tem todas as permissões
    if ($role === 'admin') {
        return true;
    }

    // Verificar permissões personalizadas do usuário (prioridade sobre o perfil)
    if (!empty($_SESSION['user_permissions'])) {
        $custom = $_SESSION['user_permissions'];
        if (isset($custom[$module])) {
            return in_array($action, $custom[$module]);
        }
        // Se o módulo não está nas permissões customizadas, o usuário NÃO tem acesso
        return false;
    }

    // Fallback: permissões padrão do perfil
    if (!isset($PERMISSIONS[$role][$module])) {
        return false;
    }

    return in_array($action, $PERMISSIONS[$role][$module]);
}

/**
 * Verifica se o módulo deve ser visível no dashboard
 *
 * @param string $module Nome do módulo
 * @return bool
 */
function canViewModule($module) {
    global $VISIBLE_MODULES;

    if (!isset($_SESSION['user_role'])) {
        return false;
    }

    $role = $_SESSION['user_role'];

    // Admin sempre vê tudo
    if ($role === 'admin') {
        return true;
    }

    // Se tem permissões customizadas, verifica se o módulo está lá
    if (!empty($_SESSION['user_permissions'])) {
        return isset($_SESSION['user_permissions'][$module]);
    }

    return in_array($module, $VISIBLE_MODULES[$role] ?? []);
}

/**
 * Requer permissão ou redireciona com erro
 *
 * @param string $module Nome do módulo
 * @param string $action Ação desejada
 */
function requirePermission($module, $action = 'view') {
    if (!hasPermission($module, $action)) {
        header('Location: ../../index.php?error=access_denied');
        exit;
    }
}

/**
 * Verifica se usuário é admin
 *
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Requer perfil de admin ou redireciona
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ../../index.php?error=admin_only');
        exit;
    }
}

/**
 * Retorna o nome amigável do perfil
 *
 * @param string $role
 * @return string
 */
function getRoleName($role) {
    $roles = [
        'admin' => 'Administrador',
        'manager' => 'Gerente',
        'seller' => 'Vendedor'
    ];
    return $roles[$role] ?? 'Desconhecido';
}

/**
 * Retorna a cor do badge do perfil
 *
 * @param string $role
 * @return string
 */
function getRoleColor($role) {
    $colors = [
        'admin' => '#4CAF50',      // Verde
        'manager' => '#2196F3',    // Azul
        'seller' => '#9C27B0'      // Roxo
    ];
    return $colors[$role] ?? '#999';
}
