<?php
/**
 * FL REPAROS - Login para Hospedagem
 */

session_start();

// Conectar banco usando .env
$env = parse_ini_file('.env');
$host = $env['DB_HOST'];
$username = $env['DB_USERNAME'];
$password = $env['DB_PASSWORD'];
$database = $env['DB_DATABASE'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Erro de conexão com o banco de dados. Verifique as configurações.');
}

// Verificar se já está logado
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Função para registrar tentativa de login
function logLoginAttempt($email, $success, $ip) {
    error_log("Login attempt: $email, Success: " . ($success ? 'Yes' : 'No') . ", IP: $ip");
}

$error = '';
$userIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificar token CSRF
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido. Recarregue a página.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validação básica
        if (empty($email) || empty($password)) {
            $error = 'Preencha todos os campos';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido';
        } else {
            try {
                // Buscar usuário no banco
                $stmt = $pdo->prepare("
                    SELECT id, name, email, password, role, status
                    FROM users
                    WHERE email = ? AND status = 'active'
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                // Verificar credenciais
                if ($user && password_verify($password, $user['password'])) {
                    // Login bem-sucedido
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    // Registrar login no sistema
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO cash_flow (user_id, type, description, amount) 
                            VALUES (?, 'opening', 'Login no sistema', 0)
                        ");
                        $stmt->execute([$user['id']]);
                    } catch (Exception $e) {
                        // Ignorar erro de log
                    }
                    
                    // Log de segurança
                    logLoginAttempt($email, true, $userIp);
                    
                    // Redirecionar
                    $redirectTo = $_GET['redirect'] ?? 'index.php';
                    $redirectTo = filter_var($redirectTo, FILTER_SANITIZE_URL);
                    header('Location: ' . $redirectTo);
                    exit;
                    
                } else {
                    // Login falhou
                    $error = 'Email ou senha incorretos';
                    logLoginAttempt($email, false, $userIp);
                    
                    // Delay de segurança
                    sleep(1);
                }
                
            } catch (Exception $e) {
                $error = 'Erro interno do servidor';
                error_log('Login error: ' . $e->getMessage());
            }
        }
    }
}

// Gerar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verificar se usuário admin existe, se não, criar
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@flreparos.com'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        // Criar usuário admin
        $hash = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['Administrador', 'admin@flreparos.com', $hash, 'admin', 'active']);
    }
} catch (Exception $e) {
    // Ignorar erros de criação
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FL REPAROS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .logo i {
            color: #667eea;
            margin-right: 10px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-message {
            background: linear-gradient(45deg, #ff6b6b, #ff5252);
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .security-note {
            margin-top: 20px;
            font-size: 0.8rem;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-mobile-alt"></i>
            FL REPAROS
        </div>
        <p class="subtitle">Sistema de Gestão</p>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> Email:
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    required 
                    autofocus
                    autocomplete="email"
                    placeholder="seu@email.com"
                >
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Senha:
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    autocomplete="current-password"
                    placeholder="Digite sua senha"
                >
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i>
                Entrar no Sistema
            </button>
        </form>

        <div class="security-note">
            <i class="fas fa-shield-alt"></i>
            Conexão segura • Dados protegidos
        </div>
    </div>

    <script>
        // Foco automático e navegação por teclado
        document.getElementById('email').focus();
        
        document.getElementById('email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });

        // Indicador visual de loading no botão
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.querySelector('.btn-login');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
            btn.disabled = true;
        });
    </script>
</body>
</html>