<?php
/**
 * FL REPAROS - Logout do Sistema
 * Máximo: 50 linhas
 */

include_once 'config/app.php';

// Registrar logout no log (opcional)
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    try {
        $database->query(
            "INSERT INTO cash_flow (user_id, type, description, amount) VALUES (?, 'closing', 'Logout do sistema', 0)",
            [$currentUser['id']]
        );
    } catch (Exception $e) {
        // Ignorar erro de log
    }
}

// Destruir sessão
session_start();
session_destroy();

// Limpar cookies se houver
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Redirecionar para login
redirect('login.php');
?>