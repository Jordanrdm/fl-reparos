<?php
/**
 * FL REPAROS - Header do Sistema
 * Máximo: 100 linhas
 */

// Incluir configurações se não foi incluído ainda
if (!defined('APP_NAME')) {
    include_once __DIR__ . '/../config/app.php';
}

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/main.css">
    
    <!-- CSS específico da página -->
    <?php if (isset($pageCSS)): ?>
        <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/<?php echo $pageCSS; ?>.css">
    <?php endif; ?>
    
    <style>
        /* Reset básico */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        /* Header fixo */
        .header-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }
        
        .logo i {
            margin-right: 10px;
            color: #667eea;
        }
        
        .nav-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #666;
        }

        .user-info i {
            font-size: 1.8rem;
            color: #667eea;
        }

        .user-role-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .user-role-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .role-admin {
            background: linear-gradient(45deg, #4CAF50, #45a049);
        }

        .role-admin:hover {
            background: linear-gradient(45deg, #45a049, #4CAF50);
        }

        .role-manager {
            background: linear-gradient(45deg, #2196F3, #1976D2);
        }

        .role-manager:hover {
            background: linear-gradient(45deg, #1976D2, #2196F3);
        }

        .role-seller {
            background: linear-gradient(45deg, #9C27B0, #7B1FA2);
        }

        .role-seller:hover {
            background: linear-gradient(45deg, #7B1FA2, #9C27B0);
        }

        .btn-logout {
            background: linear-gradient(45deg, #ff6b6b, #ff5252);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Header de Navegação -->
    <header class="header-nav">
        <div class="nav-container">
            <a href="<?php echo APP_URL; ?>/index.php" class="logo">
                <i class="fas fa-mobile-alt"></i>
                <?php echo APP_NAME; ?>
            </a>
            
            <?php if (isLoggedIn()): ?>
            <div class="nav-user">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span class="user-role-badge role-<?php echo $currentUser['role']; ?>">
                        <?php echo getRoleName($currentUser['role']); ?>
                    </span>
                </div>
                <a href="<?php echo APP_URL; ?>/logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- Conteúdo Principal -->
    <main class="main-content"><?php
// Flash Messages
$flashMessages = getFlashMessages();
if (!empty($flashMessages)):
    foreach ($flashMessages as $type => $message): ?>
        <div class="alert alert-<?php echo $type; ?>" style="margin: 20px; padding: 15px; border-radius: 8px; background: rgba(255,255,255,0.9);">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endforeach;
endif;
?>