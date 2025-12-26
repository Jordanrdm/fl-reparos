<?php
/**
 * FL REPAROS - Configurações da Aplicação
 * Compatível com todos os ambientes
 * Máximo: 150 linhas
 */

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações gerais
define('APP_NAME', 'FL REPAROS');
define('APP_VERSION', '1.0.0');
define('APP_URL', getBaseUrl());

// Configurações de segurança
define('SESSION_TIMEOUT', 3600); // 1 hora
define('CSRF_TOKEN_NAME', '_token');

// Configurações de upload
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Timezone
date_default_timezone_set('America/Sao_Paulo');

/**
 * Detecta URL base automaticamente
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove barras duplas
    $path = str_replace('//', '/', $path);
    if ($path === '/' || $path === '\\') {
        $path = '';
    }
    
    return $protocol . '://' . $host . $path;
}

/**
 * Inclui arquivo se existir
 */
function includeIfExists($file) {
    $fullPath = __DIR__ . '/../' . $file;
    if (file_exists($fullPath)) {
        include_once $fullPath;
        return true;
    }
    return false;
}

/**
 * Redireciona para URL
 */
function redirect($url) {
    if (strpos($url, 'http') !== 0) {
        $url = APP_URL . '/' . ltrim($url, '/');
    }
    header("Location: $url");
    exit;
}

/**
 * Sanitiza entrada do usuário
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Formatar moeda brasileira
 */
function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Formatar data brasileira
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Gerar token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verificar token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Verificar se usuário está logado
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obter dados do usuário logado
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'role' => $_SESSION['user_role'] ?? 'user'
    ];
}

// Incluir sistema de permissões
$permissionsFile = __DIR__ . '/permissions.php';
if (file_exists($permissionsFile)) {
    include_once $permissionsFile;
}

/**
 * Logout do usuário
 */
function logout() {
    session_destroy();
    redirect('login.php');
}

/**
 * Middleware de autenticação
 */
function requireAuth() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

/**
 * Exibir alertas Flash
 */
function flashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessages() {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// Auto-include do banco de dados
$dbFile = __DIR__ . '/database.php';
if (file_exists($dbFile)) {
    include_once $dbFile;
} else {
    die('Erro: Arquivo de configuração do banco não encontrado em: ' . $dbFile);
}
?>