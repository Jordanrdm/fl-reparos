<?php
// modules/pdv/index.php - VERSÃO COM PAGAMENTOS MELHORADOS
session_start();

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Carregar configurações e conectar ao banco
$envFile = '../../.env';
$env = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
}

try {
    $pdo = new PDO(
        'mysql:host=' . $env['DB_HOST'] . ';dbname=' . $env['DB_DATABASE'] . ';charset=utf8mb4',
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Incluir funções auxiliares
require_once '../../config/app.php';

// Função para converter nome do método de pagamento
function getPaymentMethodName($method) {
    $methods = [
        'dinheiro' => 'Dinheiro',
        'cartao_debito' => 'Cartão de Débito',
        'cartao_credito' => 'Cartão de Crédito',
        'pix' => 'PIX',
        'transferencia' => 'Transferência'
    ];
    return $methods[$method] ?? $method;
}

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'search_product':
            $search = trim($_POST['search']);
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.active = 1 
                AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)
                AND p.stock_quantity > 0
                ORDER BY p.name ASC 
                LIMIT 10
            ");
            $searchParam = "%$search%";
            $stmt->execute([$searchParam, $searchParam, $searchParam]);
            echo json_encode($stmt->fetchAll());
            exit;
            
        case 'get_all_products':
            try {
                $stmt = $pdo->prepare("
                    SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.active = 1 
                    ORDER BY p.name ASC 
                    LIMIT 50
                ");
                $stmt->execute();
                echo json_encode($stmt->fetchAll());
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'validate_discount_password':
            $password = $_POST['password'] ?? '';
            $email = $_POST['username'] ?? ''; // Frontend envia como 'username' mas é email

            // Validar credenciais de forma segura
            $stmt = $pdo->prepare("SELECT id, role, password FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password']) && ($user['role'] === 'admin' || $user['role'] === 'manager')) {
                echo json_encode(['success' => true, 'role' => $user['role']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Credenciais inválidas ou sem permissão']);
            }
            exit;

        case 'finalize_sale':
            try {
                $pdo->beginTransaction();

                $items = json_decode($_POST['items'], true);
                $customer_name = sanitize($_POST['customer_name'] ?? '');
                $discount = (float)$_POST['discount'];
                $payments = json_decode($_POST['payments'], true);
                $total_amount = (float)$_POST['total_amount'];
                $final_amount = (float)$_POST['final_amount'];
                $change_amount = (float)($_POST['change_amount'] ?? 0);

                // Preparar descrição dos pagamentos para o banco
                $payment_description = '';
                if (count($payments) > 1) {
                    $payment_methods = array_map(function($p) {
                        return getPaymentMethodName($p['method']) . ': R$ ' . number_format($p['amount'], 2, ',', '.');
                    }, $payments);
                    $payment_description = 'Múltiplos: ' . implode(', ', $payment_methods);
                } else {
                    $payment_description = getPaymentMethodName($payments[0]['method']);
                }

                // Inserir venda
                $stmt = $pdo->prepare("
                    INSERT INTO sales (customer_id, user_id, total_amount, discount, final_amount, payment_method, status, created_at)
                    VALUES (NULL, ?, ?, ?, ?, ?, 'completed', NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $total_amount, $discount, $final_amount, $payment_description]);
                $sale_id = $pdo->lastInsertId();

                // Inserir itens da venda e atualizar estoque
                foreach ($items as $item) {
                    // Inserir item
                    $stmt = $pdo->prepare("
                        INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $sale_id,
                        $item['id'],
                        $item['quantity'],
                        $item['price'],
                        $item['total']
                    ]);

                    // Atualizar estoque
                    $stmt = $pdo->prepare("
                        UPDATE products SET stock_quantity = stock_quantity - ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$item['quantity'], $item['id']]);
                }

                // Registrar no fluxo de caixa
                $customer_info = $customer_name ? " - Cliente: $customer_name" : "";
                $description = "Venda #$sale_id - $payment_description$customer_info";

                // Se tem troco, registrar valor recebido e troco separadamente
                if ($change_amount > 0) {
                    $valor_recebido = $final_amount + $change_amount;

                    // ENTRADA: Valor recebido do cliente
                    $stmt = $pdo->prepare("
                        INSERT INTO cash_flow (user_id, type, description, amount, reference_id, reference_type, created_at)
                        VALUES (?, 'sale', ?, ?, ?, 'sale', NOW())
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $description . " - Valor Recebido",
                        $valor_recebido,
                        $sale_id
                    ]);

                    // SAÍDA: Troco devolvido
                    $stmt = $pdo->prepare("
                        INSERT INTO cash_flow (user_id, type, description, amount, reference_id, reference_type, created_at)
                        VALUES (?, 'expense', ?, ?, ?, 'sale_change', NOW())
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $description . " - Troco",
                        $change_amount,
                        $sale_id
                    ]);
                } else {
                    // Sem troco, registra valor total normalmente
                    $stmt = $pdo->prepare("
                        INSERT INTO cash_flow (user_id, type, description, amount, reference_id, reference_type, created_at)
                        VALUES (?, 'sale', ?, ?, ?, 'sale', NOW())
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $description,
                        $final_amount,
                        $sale_id
                    ]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'sale_id' => $sale_id]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

// Buscar clientes para autocomplete (SEM coluna active)
try {
    $stmt = $pdo->prepare("SELECT id, name, cpf_cnpj, phone FROM customers ORDER BY name ASC LIMIT 100");
    $stmt->execute();
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Se der erro na tabela customers, criar array vazio
    $customers = [];
    error_log("Erro ao buscar customers: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDV Rápido - FL REPAROS</title>
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
            padding: 15px;
        }

        .pdv-container {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
            height: calc(100vh - 30px);
        }

        .left-panel {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .pdv-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pdv-title {
            color: #333;
            font-size: 1.6rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .search-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .search-header h3 {
            margin: 0;
            color: #333;
        }

        .search-input {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            border: 3px solid #e0e0e0;
            border-radius: 10px;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .products-results {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            flex: 1;
            overflow-y: auto;
        }

        .product-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .product-card:hover {
            background: #e9ecef;
            border-color: #4CAF50;
            transform: translateY(-2px);
        }

        .product-name {
            font-weight: bold;
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }

        .product-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            font-size: 14px;
            color: #666;
        }

        .right-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 30px);
            overflow-y: auto;
        }

        .cart-header {
            text-align: center;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 8px;
        }

        .cart-header h2 {
            font-size: 18px;
            margin: 0;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 15px;
        }

        .cart-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            border-left: 4px solid #4CAF50;
        }

        .cart-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .cart-item-name {
            font-weight: bold;
            color: #333;
        }

        .remove-item {
            background: #f44336;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 12px;
        }

        .cart-item-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #666;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .qty-btn {
            background: #2196F3;
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }

        .cart-summary {
            border-top: 2px solid #e0e0e0;
            padding-top: 8px;
            margin-top: 8px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .summary-total {
            font-weight: bold;
            font-size: 16px;
            color: #4CAF50;
            border-top: 1px solid #e0e0e0;
            padding-top: 6px;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 6px;
        }

        .form-group label {
            display: block;
            margin-bottom: 3px;
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 6px 8px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(45deg, #2196F3, #1976D2);
            color: white;
        }

        .btn-success {
            background: linear-gradient(45deg, #FF9800, #F57C00);
            color: white;
            font-size: 15px;
            padding: 8px 16px;
            width: 100%;
            margin-top: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empty-cart {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-cart i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
            color: #ccc;
        }

        @media (max-width: 1024px) {
            .pdv-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .right-panel {
                order: -1;
            }
        }

        /* Responsividade para telas menores */
        @media (max-width: 480px) {
            .payment-input-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .payment-method-select,
            .payment-amount-input,
            .add-payment-btn-simple {
                width: 100%;
                margin-bottom: 8px;
            }
            
            .add-payment-btn-simple {
                min-width: auto;
            }
        }

        .low-stock {
            color: #f44336;
            font-weight: bold;
        }

        .no-stock {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .autocomplete {
            position: relative;
        }

        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
        }

        .autocomplete-suggestion {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        .autocomplete-suggestion:hover {
            background: #f5f5f5;
        }

        /* ===== NOVAS MELHORIAS PARA PAGAMENTOS ===== */

        /* Container principal dos pagamentos */
        .payment-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 10px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
            width: 100%;
            box-sizing: border-box;
            overflow: hidden; /* Garante que nada saia fora */
        }

        .payment-form:focus-within {
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        /* Input de adicionar pagamento */
        .payment-input-row {
            display: flex;
            gap: 6px;
            margin-bottom: 6px;
            align-items: center;
            flex-wrap: wrap;
        }

        .payment-method-select {
            flex: 2;
            padding: 6px 8px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .payment-amount-input {
            flex: 1;
            min-width: 100px;
            padding: 6px 8px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-align: right;
            font-size: 14px;
            font-weight: bold;
            transition: border-color 0.3s ease;
        }

        .payment-method-select:focus,
        .payment-amount-input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .add-payment-btn {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .add-payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .add-payment-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Dica de pagamento */
        .payment-hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
            margin-bottom: 4px;
        }

        /* Lista de pagamentos adicionados */
        .payments-list {
            margin-top: 8px;
            margin-bottom: 8px;
        }

        .payments-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            padding-bottom: 6px;
            border-bottom: 1px solid #ddd;
        }

        .payments-list-title {
            font-weight: bold;
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .clear-payments-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .clear-payments-btn:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        /* Item individual de pagamento */
        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 10px;
            background: white;
            border-radius: 6px;
            margin-bottom: 4px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .payment-item:hover {
            border-color: #4CAF50;
            transform: translateX(3px);
        }

        .payment-method-name {
            font-weight: 600;
            color: #333;
            font-size: 12px;
        }

        .payment-amount-display {
            color: #4CAF50;
            font-weight: bold;
            font-size: 12px;
        }

        .remove-payment-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .remove-payment-btn:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        /* Total pago */
        .total-paid-display {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 2px solid #4CAF50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
            color: #4CAF50;
            font-size: 13px;
        }

        /* Valor restante */
        .remaining-amount-display {
            background: #fff3cd;
            color: #856404;
            padding: 6px 10px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 12px;
            border: 1px solid #ffeaa7;
        }

        .remaining-amount-display.complete {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .remaining-amount-display.overpaid {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* Animações */
        .payment-item-enter {
            animation: slideInRight 0.3s ease;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Toast personalizado para pagamentos */
        .toast-alert.payment {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        /* Estados especiais */
        .payment-form.multiple-payments {
            border-color: #4CAF50;
            background: #f0f8f0;
        }

        .payment-form.payment-complete {
            border-color: #28a745;
            background: #d4edda;
        }

        /* Modal styles remain the same... */
        .print-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .print-modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .print-modal-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .print-modal-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.5rem;
        }

        .print-modal-header .success-icon {
            font-size: 48px;
            color: #4CAF50;
            margin-bottom: 15px;
        }

        .sale-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .sale-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .sale-summary-total {
            font-weight: bold;
            font-size: 16px;
            color: #4CAF50;
            border-top: 1px solid #ddd;
            padding-top: 8px;
            margin-top: 8px;
        }

        .print-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-print {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            font-size: 16px;
            padding: 12px 24px;
        }

        .btn-no-print {
            background: linear-gradient(45deg, #6c757d, #545b62);
            color: white;
            font-size: 16px;
            padding: 12px 24px;
        }

        .confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .confirm-modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: confirmSlideIn 0.3s ease;
            text-align: center;
        }

        @keyframes confirmSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .confirm-modal-header {
            margin-bottom: 20px;
        }

        .confirm-modal-header h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1.3rem;
        }

        .confirm-modal-message {
            color: #666;
            font-size: 16px;
            margin-bottom: 25px;
            line-height: 1.4;
        }

        .confirm-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .confirm-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            min-width: 100px;
        }

        .confirm-btn-primary {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
        }

        .confirm-btn-secondary {
            background: linear-gradient(45deg, #6c757d, #545b62);
            color: white;
        }

        .confirm-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .toast-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            animation: toastSlideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            cursor: pointer;
        }

        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes toastSlideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        .toast-alert.success {
            background: rgba(76, 175, 80, 0.9);
            color: white;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .toast-alert.error {
            background: rgba(244, 67, 54, 0.9);
            color: white;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .toast-alert.info {
            background: rgba(33, 150, 243, 0.9);
            color: white;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }

        .toast-alert.warning {
            background: rgba(255, 152, 0, 0.9);
            color: white;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }

        .toast-icon {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="pdv-container">
        <!-- Painel Esquerdo -->
        <div class="left-panel">
            <!-- Header -->
            <div class="pdv-header">
                <h1 class="pdv-title">
                    <i class="fas fa-cash-register"></i>
                    PDV Rápido
                </h1>
                <a href="../../index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>

            <!-- Busca de Produtos -->
            <div class="search-section">
                <div class="search-header">
                    <h3>
                        <i class="fas fa-search"></i> Buscar Produto
                    </h3>
                    <button id="showAllProducts" class="btn btn-secondary" style="padding: 8px 15px; font-size: 12px;">
                        <i class="fas fa-list"></i> Ver Todos
                    </button>
                </div>
                <input type="text" id="productSearch" class="search-input" 
                       placeholder="Digite o nome, código ou código de barras..." 
                       autocomplete="off">
            </div>

            <!-- Resultados da Busca -->
            <div class="products-results">
                <h3 style="margin-bottom: 15px; color: #333;" id="resultsTitle">
                    <i class="fas fa-list"></i> Produtos Encontrados
                </h3>
                <div id="searchResults">
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px; display: block; color: #ccc;"></i>
                        Digite acima para buscar produtos
                        <br><br>
                        <button onclick="loadAllProducts()" class="btn btn-secondary" style="font-size: 14px;">
                            <i class="fas fa-list"></i> Ver Todos os Produtos
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Painel Direito - Carrinho -->
        <div class="right-panel">
            <div class="cart-header">
                <h2><i class="fas fa-shopping-cart"></i> Carrinho de Vendas</h2>
            </div>

            <!-- Itens do Carrinho -->
            <div class="cart-items" id="cartItems">
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Carrinho Vazio</h3>
                    <p>Busque e adicione produtos ao carrinho</p>
                </div>
            </div>

            <!-- Cliente -->
            <div class="form-group">
                <label>Cliente (Opcional):</label>
                <div class="autocomplete">
                    <input type="text" id="customerSearch" class="form-control" 
                           placeholder="Digite o nome do cliente..." autocomplete="off">
                    <div id="customerSuggestions" class="autocomplete-suggestions" style="display: none;"></div>
                </div>
                <input type="hidden" id="customerId" value="">
            </div>

            <!-- Desconto -->
            <div class="form-group">
                <label>Desconto (%):</label>
                <input type="number" id="discountPercent" class="form-control"
                       placeholder="0" min="0" max="100" step="0.1"
                       onfocus="if(this.value=='0') this.value=''"
                       onblur="handleDiscountChange()">
                <small style="color: #666; display: block; margin-top: 2px; font-size: 11px;">
                    <i class="fas fa-info-circle"></i> Desconto acima de 5% requer senha de gerente/admin
                </small>
            </div>

            <!-- ===== SEÇÃO DE FORMAS DE PAGAMENTO SIMPLIFICADA ===== -->
            <div class="form-group">
                <label>Forma de Pagamento:</label>
                <div class="payment-form" id="paymentForm">
                    
                    <!-- Modo Simples (Padrão) -->
                    <div id="simplePaymentMode">
                        <div class="payment-input-row">
                            <select id="mainPaymentMethod" class="payment-method-select">
                                <option value="dinheiro">Dinheiro</option>
                                <option value="cartao_debito">Cartão de Débito</option>
                                <option value="cartao_credito">Cartão de Crédito</option>
                                <option value="pix">PIX</option>
                                <option value="transferencia">Transferência</option>
                            </select>
                            <button type="button" id="addAnotherPaymentBtn" class="add-payment-btn-simple"
                                    style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); color: #667eea; border: 2px solid rgba(102, 126, 234, 0.3); padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; min-width: 130px; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08); transition: all 0.3s ease;">
                                <i class="fas fa-plus"></i> Adicionar outra
                            </button>
                        </div>
                    </div>

                    <!-- Modo Múltiplo (Aparece depois) -->
                    <div id="multiplePaymentMode" style="display: none;">
                        <div class="payment-input-row">
                            <select id="multiPaymentMethod" class="payment-method-select">
                                <option value="dinheiro">Dinheiro</option>
                                <option value="cartao_debito">Cartão de Débito</option>
                                <option value="cartao_credito">Cartão de Crédito</option>
                                <option value="pix">PIX</option>
                                <option value="transferencia">Transferência</option>
                            </select>
                            <select id="pdvInstallments" class="payment-method-select" style="display: none; width: auto; max-width: 80px; flex: none;">
                                <option value="1">1x</option>
                                <option value="2">2x</option>
                                <option value="3">3x</option>
                                <option value="4">4x</option>
                                <option value="5">5x</option>
                                <option value="6">6x</option>
                                <option value="7">7x</option>
                                <option value="8">8x</option>
                                <option value="9">9x</option>
                                <option value="10">10x</option>
                                <option value="11">11x</option>
                                <option value="12">12x</option>
                            </select>
                            <input type="number" id="multiPaymentAmount" class="payment-amount-input"
                                   placeholder="Digite o valor..." min="0.01" step="0.01"
                                   style="text-align: center !important; padding: 6px 8px !important;">
                            <span id="installmentValueDisplay" style="display: none; color: #28a745; font-weight: 600; font-size: 12px; white-space: nowrap;"></span>
                        </div>
                        <div class="payment-hint">
                            <span class="blinking-bulb">💡</span> Digite o valor da forma de pagamento e pressione <kbd>Enter</kbd>
                        </div>
                    </div>

                    <!-- Valor restante -->
                    <div id="remainingAmountDisplay" class="remaining-amount-display" style="display: none;">
                        Valor restante: R$ <span id="remainingValue">0,00</span>
                    </div>

                    <!-- Lista de pagamentos -->
                    <div id="paymentsList" class="payments-list" style="display: none;">
                        <div class="payments-list-header">
                            <span class="payments-list-title">Formas de Pagamento:</span>
                            <button type="button" id="clearAllPayments" class="clear-payments-btn">
                                <i class="fas fa-trash"></i> Limpar
                            </button>
                        </div>
                        <div id="paymentsContainer"></div>
                        <div class="total-paid-display">
                            <span>Total Pago:</span>
                            <span>R$ <span id="totalPaidAmount">0,00</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumo -->
            <div class="cart-summary">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">R$ 0,00</span>
                </div>
                <div class="summary-row">
                    <span>Desconto:</span>
                    <span id="discountDisplay">R$ 0,00</span>
                </div>
                <div class="summary-row summary-total">
                    <span>TOTAL:</span>
                    <span id="total">R$ 0,00</span>
                </div>
                <div class="summary-row" id="changeRow" style="display: none; color: #28a745; font-weight: 600; font-size: 1.1em; margin-top: 10px; padding-top: 10px; border-top: 2px solid #e0e0e0;">
                    <span><i class="fas fa-hand-holding-usd"></i> TROCO:</span>
                    <span id="changeAmount">R$ 0,00</span>
                </div>
            </div>

            <!-- Finalizar Venda -->
            <button id="finalizeSale" class="btn btn-success" disabled>
                <i class="fas fa-check"></i> Finalizar Venda
            </button>
        </div>
    </div>

    <!-- Modal de Validação de Senha para Desconto -->
    <div id="discountPasswordModal" class="confirm-modal" style="display: none;">
        <div class="confirm-modal-content">
            <div class="confirm-modal-header">
                <h3><i class="fas fa-lock"></i> Autorização Necessária</h3>
            </div>
            <div class="confirm-modal-message">
                <p style="margin-bottom: 15px;">Este desconto requer autorização de gerente ou administrador.</p>
                <div style="text-align: left;">
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label style="display: block; margin-bottom: 5px;">Email:</label>
                        <input type="email" id="discountAuthUsername" class="form-control"
                               placeholder="Digite seu email" autocomplete="off" style="width: 100%;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display: block; margin-bottom: 5px;">Senha:</label>
                        <input type="password" id="discountAuthPassword" class="form-control"
                               placeholder="Digite sua senha" autocomplete="off" style="width: 100%;">
                    </div>
                </div>
            </div>
            <div class="confirm-modal-actions">
                <button class="confirm-btn confirm-btn-primary" id="discountAuthConfirm">
                    <i class="fas fa-check"></i> Autorizar
                </button>
                <button class="confirm-btn confirm-btn-secondary" id="discountAuthCancel">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div id="confirmModal" class="confirm-modal">
        <div class="confirm-modal-content">
            <div class="confirm-modal-header">
                <h3 id="confirmTitle">Confirmar Ação</h3>
            </div>
            <div class="confirm-modal-message" id="confirmMessage">
                Tem certeza que deseja continuar?
            </div>
            <div class="confirm-modal-actions">
                <button class="confirm-btn confirm-btn-primary" id="confirmOk">OK</button>
                <button class="confirm-btn confirm-btn-secondary" id="confirmCancel">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal de Impressão -->
    <div id="printModal" class="print-modal">
        <div class="print-modal-content">
            <div class="print-modal-header">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Venda Finalizada!</h2>
                <p style="margin: 0; color: #666;">Venda #<span id="modalSaleId"></span> realizada com sucesso</p>
            </div>

            <div class="sale-summary" id="modalSaleSummary">
                <!-- Será preenchido via JavaScript -->
            </div>

            <div class="print-actions">
                <button class="btn btn-print" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Imprimir Cupom
                </button>
                <button class="btn btn-no-print" onclick="closePrintModal()">
                    <i class="fas fa-times"></i> Não Imprimir
                </button>
            </div>
        </div>
    </div>

    <script>
        // Estado do carrinho e pagamentos
        let cart = [];
        let customers = <?php echo json_encode($customers); ?>;
        let selectedCustomer = null;
        let payments = []; // Array para múltiplas formas de pagamento
        let discountAuthorized = false; // Flag para controle de autorização de desconto
        let lastValidDiscount = 0; // Último desconto válido

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            updateCartDisplay();
            loadAllProducts();
        });

        function setupEventListeners() {
            // Busca de produtos
            const searchInput = document.getElementById('productSearch');
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => searchProducts(query), 300);
                } else if (query.length === 0) {
                    loadAllProducts();
                }
            });

            // Botão "Ver Todos"
            document.getElementById('showAllProducts').addEventListener('click', function() {
                document.getElementById('productSearch').value = '';
                loadAllProducts();
            });

            // Autocomplete de clientes
            setupCustomerAutocomplete();

            // ===== EVENT LISTENERS PARA PAGAMENTOS (SIMPLIFICADOS) =====
            
            // Botão para adicionar outra forma (modo simples → múltiplo)
            document.getElementById('addAnotherPaymentBtn').addEventListener('click', switchToMultipleMode);
            
            // Limpar todos os pagamentos
            document.getElementById('clearAllPayments').addEventListener('click', clearAllPayments);
            
            // Auto-completar valor e mostrar parcelas quando selecionar forma de pagamento
            document.getElementById('multiPaymentMethod').addEventListener('change', function() {
                togglePdvInstallments();
                autoFillAmount();
            });
            
            // Enter no campo de valor para adicionar
            document.getElementById('multiPaymentAmount').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addPayment();
                }
            });
            
            // Atualizar display quando digitar valor
            document.getElementById('multiPaymentAmount').addEventListener('input', function() {
                updatePaymentDisplay();
                updateInstallmentDisplay();
            });

            // Atualizar valor da parcela quando mudar o número de parcelas
            document.getElementById('pdvInstallments').addEventListener('change', updateInstallmentDisplay);

            // Desconto - removido evento input (será tratado no blur)
            // document.getElementById('discount').addEventListener('input', updateCartDisplay);

            // Finalizar venda
            document.getElementById('finalizeSale').addEventListener('click', finalizeSale);

            // Atalhos de teclado
            document.addEventListener('keydown', function(e) {
                if (e.key === 'F3') {
                    e.preventDefault();
                    document.getElementById('productSearch').focus();
                } else if (e.key === 'F9' && !document.getElementById('finalizeSale').disabled) {
                    e.preventDefault();
                    finalizeSale();
                } else if (e.key === 'Escape') {
                    const printModal = document.getElementById('printModal');
                    if (printModal.style.display === 'block') {
                        closePrintModal();
                    } else {
                        document.getElementById('productSearch').focus();
                    }
                } else if (e.key === 'F12') {
                    e.preventDefault();
                    loadAllProducts();
                } else if (e.key === 'F8') {
                    e.preventDefault();
                    // Se estiver no modo múltiplo, focar no campo valor
                    if (document.getElementById('multiplePaymentMode').style.display !== 'none') {
                        document.getElementById('multiPaymentAmount').focus();
                    } else {
                        // Se estiver no modo simples, ativar modo múltiplo
                        document.getElementById('addAnotherPaymentBtn').click();
                    }
                }
            });
        }

        // ===== SISTEMA DE VALIDAÇÃO DE DESCONTO =====

        async function handleDiscountChange() {
            const discountInput = document.getElementById('discountPercent');
            const discountPercentage = parseFloat(discountInput.value) || 0;

            if (discountPercentage === 0) {
                lastValidDiscount = 0;
                discountAuthorized = false;
                updateCartDisplay();
                return;
            }

            const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
            if (subtotal === 0) {
                showAlert('Adicione produtos ao carrinho primeiro!', 'error');
                discountInput.value = '0';
                return;
            }

            // Validar se a porcentagem não é maior que 100%
            if (discountPercentage > 100) {
                showAlert('Desconto não pode ser maior que 100%!', 'error');
                discountInput.value = lastValidDiscount.toFixed(1);
                return;
            }

            // Se desconto <= 5%, autorizar automaticamente
            if (discountPercentage <= 5) {
                lastValidDiscount = discountPercentage;
                discountAuthorized = true;
                updateCartDisplay();
                showAlert(`Desconto de ${discountPercentage.toFixed(1)}% aplicado`, 'success');
                return;
            }

            // Se desconto > 5%, pedir senha
            const authorized = await requestDiscountAuthorization(discountPercentage);

            if (authorized) {
                lastValidDiscount = discountPercentage;
                discountAuthorized = true;
                updateCartDisplay();
                showAlert(`Desconto de ${discountPercentage.toFixed(1)}% autorizado!`, 'success');
            } else {
                // Reverter para último desconto válido
                discountInput.value = lastValidDiscount.toFixed(1);
                updateCartDisplay();
            }
        }

        function requestDiscountAuthorization(percentage) {
            return new Promise((resolve) => {
                const modal = document.getElementById('discountPasswordModal');
                const usernameInput = document.getElementById('discountAuthUsername');
                const passwordInput = document.getElementById('discountAuthPassword');
                const confirmBtn = document.getElementById('discountAuthConfirm');
                const cancelBtn = document.getElementById('discountAuthCancel');

                // Mostrar modal
                modal.style.display = 'block';
                usernameInput.value = '';
                passwordInput.value = '';
                usernameInput.focus();

                // Handler para confirmar
                const handleConfirm = async () => {
                    const username = usernameInput.value.trim();
                    const password = passwordInput.value;

                    if (!username || !password) {
                        showAlert('Preencha usuário e senha!', 'error');
                        return;
                    }

                    // Desabilitar botão durante validação
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validando...';

                    try {
                        const formData = new FormData();
                        formData.append('action', 'validate_discount_password');
                        formData.append('username', username);
                        formData.append('password', password);

                        const response = await fetch('index.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            showAlert(`Autorizado por ${result.role === 'admin' ? 'Administrador' : 'Gerente'}`, 'success');
                            cleanup();
                            resolve(true);
                        } else {
                            showAlert(result.message || 'Credenciais inválidas', 'error');
                            confirmBtn.disabled = false;
                            confirmBtn.innerHTML = '<i class="fas fa-check"></i> Autorizar';
                            passwordInput.value = '';
                            passwordInput.focus();
                        }
                    } catch (error) {
                        console.error('Erro ao validar senha:', error);
                        showAlert('Erro ao validar credenciais', 'error');
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = '<i class="fas fa-check"></i> Autorizar';
                    }
                };

                // Handler para cancelar
                const handleCancel = () => {
                    showAlert('Desconto não autorizado', 'warning');
                    cleanup();
                    resolve(false);
                };

                // Handler para Enter
                const handleKeyPress = (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        handleConfirm();
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        handleCancel();
                    }
                };

                // Função de limpeza
                const cleanup = () => {
                    modal.style.display = 'none';
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-check"></i> Autorizar';
                    usernameInput.removeEventListener('keypress', handleKeyPress);
                    passwordInput.removeEventListener('keypress', handleKeyPress);
                    confirmBtn.removeEventListener('click', handleConfirm);
                    cancelBtn.removeEventListener('click', handleCancel);
                };

                // Adicionar event listeners
                usernameInput.addEventListener('keypress', handleKeyPress);
                passwordInput.addEventListener('keypress', handleKeyPress);
                confirmBtn.addEventListener('click', handleConfirm);
                cancelBtn.addEventListener('click', handleCancel);
            });
        }

        // ===== NOVAS FUNÇÕES PARA PAGAMENTOS MELHORADOS =====

        function switchToMultipleMode() {
            const total = getCurrentTotal();
            
            if (total <= 0) {
                showAlert('Adicione produtos ao carrinho primeiro!', 'error');
                return;
            }
            
            // NÃO adicionar primeira forma automaticamente
            // Apenas trocar para o modo múltiplo
            
            // Esconder modo simples e mostrar modo múltiplo
            document.getElementById('simplePaymentMode').style.display = 'none';
            document.getElementById('multiplePaymentMode').style.display = 'block';
            
            // Atualizar displays
            updatePaymentsList();
            updatePaymentFormState();
            
            // Focar no campo de valor com o total já preenchido
            const amountInput = document.getElementById('multiPaymentAmount');
            amountInput.value = total.toFixed(2);
            amountInput.focus();
            amountInput.select(); // Selecionar tudo para facilitar edição
            
            showAlert('Agora digite o valor de cada forma e pressione Enter!', 'info');
        }

        function switchToSimpleMode() {
            // Voltar ao modo simples
            document.getElementById('simplePaymentMode').style.display = 'block';
            document.getElementById('multiplePaymentMode').style.display = 'none';
            
            // Esconder o display de valor restante
            document.getElementById('remainingAmountDisplay').style.display = 'none';
            
            // Limpar pagamentos
            payments = [];
            updatePaymentsList();
            updatePaymentFormState();
        }

        function autoFillAmount() {
            const remaining = getRemainingAmount();
            const amountInput = document.getElementById('multiPaymentAmount');
            
            // Se há valor restante, preencher automaticamente
            if (remaining > 0) {
                amountInput.value = remaining.toFixed(2);
                amountInput.select(); // Selecionar para facilitar edição
            }
        }

        function togglePdvInstallments() {
            const methodSelect = document.getElementById('multiPaymentMethod');
            const installmentsSelect = document.getElementById('pdvInstallments');
            const installmentDisplay = document.getElementById('installmentValueDisplay');

            if (methodSelect.value === 'cartao_credito') {
                installmentsSelect.style.display = 'inline-block';
                updateInstallmentDisplay();
            } else {
                installmentsSelect.style.display = 'none';
                installmentDisplay.style.display = 'none';
                installmentsSelect.value = '1';
            }
        }

        function updateInstallmentDisplay() {
            const amountInput = document.getElementById('multiPaymentAmount');
            const installmentsSelect = document.getElementById('pdvInstallments');
            const installmentDisplay = document.getElementById('installmentValueDisplay');
            const methodSelect = document.getElementById('multiPaymentMethod');

            if (methodSelect.value !== 'cartao_credito') {
                installmentDisplay.style.display = 'none';
                return;
            }

            const amount = parseFloat(amountInput.value);
            const installments = parseInt(installmentsSelect.value) || 1;

            if (amount > 0 && installments > 1) {
                const installmentValue = amount / installments;
                installmentDisplay.textContent = `${installments}x de R$ ${installmentValue.toFixed(2).replace('.', ',')}`;
                installmentDisplay.style.display = 'inline-block';
            } else {
                installmentDisplay.style.display = 'none';
            }
        }

        function updatePaymentDisplay() {
            updateRemainingDisplay();
            updateChangeDisplay();
        }

        function addPayment() {
            const methodSelect = document.getElementById('multiPaymentMethod');
            const amountInput = document.getElementById('multiPaymentAmount');
            const installmentsSelect = document.getElementById('pdvInstallments');

            const method = methodSelect.value;
            const amount = parseFloat(amountInput.value);
            const installments = parseInt(installmentsSelect.value) || 1;
            
            // Validações
            if (!amount || amount <= 0) {
                showAlert('Digite um valor válido!', 'error');
                amountInput.focus();
                return;
            }

            const remaining = getRemainingAmount();
            // Validar apenas se o valor é negativo (pagar menos que zero)
            // Permitir valor maior para dar troco
            if (amount > remaining + 100.00 && payments.length > 0) {
                // Avisar se estiver muito acima (possível erro de digitação)
                const confirmed = confirm(`Você está adicionando R$ ${amount.toFixed(2).replace('.', ',')} mas o restante é R$ ${remaining.toFixed(2).replace('.', ',')}. Confirma?`);
                if (!confirmed) {
                    amountInput.focus();
                    return;
                }
            }
            
            // Adicionar pagamento
            payments.push({
                method: method,
                amount: amount,
                installments: method === 'cartao_credito' ? installments : 1
            });

            // Limpar campos
            amountInput.value = '';
            installmentsSelect.value = '1';
            installmentsSelect.style.display = 'none';
            document.getElementById('installmentValueDisplay').style.display = 'none';
            methodSelect.value = 'dinheiro'; // Resetar para dinheiro após adicionar

            // Atualizar display
            updatePaymentsList();
            updatePaymentFormState();
            
            // Mostrar alert
            const methodName = getPaymentMethodName(method);
            showAlert(`${methodName}: R$ ${amount.toFixed(2).replace('.', ',')} adicionado!`, 'success');
            
            // Se ainda há valor restante, preencher próximo valor e focar
            if (getRemainingAmount() > 0) {
                autoFillAmount();
                methodSelect.focus();
            } else {
                // Se completou o pagamento, focar no botão finalizar
                document.getElementById('finalizeSale').focus();
                showAlert('✅ Pagamento completo! Pode finalizar a venda.', 'success');
            }
        }

        function removePayment(index) {
            const payment = payments[index];
            const methodName = getPaymentMethodName(payment.method);
            
            payments.splice(index, 1);
            
            // Se não há mais pagamentos, voltar ao modo simples
            if (payments.length === 0) {
                switchToSimpleMode();
                showAlert('Todas as formas removidas! Voltando ao modo simples.', 'info');
            } else {
                updatePaymentsList();
                updatePaymentFormState();
                showAlert(`${methodName} removido!`, 'warning');
            }
        }

        function clearAllPayments() {
            if (payments.length > 0) {
                payments = [];
                switchToSimpleMode(); // Voltar ao modo simples
                showAlert('Todas as formas de pagamento foram removidas!', 'warning');
            }
        }

        function updatePaymentsList() {
            const container = document.getElementById('paymentsContainer');
            const listDiv = document.getElementById('paymentsList');
            const totalPaidSpan = document.getElementById('totalPaidAmount');
            
            if (payments.length === 0) {
                listDiv.style.display = 'none';
                return;
            }
            
            listDiv.style.display = 'block';
            
            const html = payments.map((payment, index) => {
                let displayText = getPaymentMethodName(payment.method);
                if (payment.method === 'cartao_credito' && payment.installments > 1) {
                    const installmentValue = payment.amount / payment.installments;
                    displayText += ` (${payment.installments}x de R$ ${installmentValue.toFixed(2).replace('.', ',')})`;
                }
                return `
                <div class="payment-item payment-item-enter">
                    <span class="payment-method-name">${displayText}</span>
                    <span class="payment-amount-display">R$ ${payment.amount.toFixed(2).replace('.', ',')}</span>
                    <button class="remove-payment-btn" onclick="removePayment(${index})" title="Remover">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                `;
            }).join('');
            
            container.innerHTML = html;
            
            const totalPaid = getTotalPaid();
            totalPaidSpan.textContent = totalPaid.toFixed(2).replace('.', ',');
            
            updateRemainingDisplay();
        }

        function updateRemainingDisplay() {
            const remainingDiv = document.getElementById('remainingAmountDisplay');
            const remainingSpan = document.getElementById('remainingValue');
            const remaining = getRemainingAmount();

            // Se está no modo simples ou não há pagamentos, esconder
            if (payments.length === 0 || document.getElementById('multiplePaymentMode').style.display === 'none') {
                remainingDiv.style.display = 'none';
                return;
            }

            // Se tem apenas 1 pagamento e pagou a mais (tem troco), esconder o "valor restante"
            // porque o troco já vai aparecer abaixo
            if (payments.length === 1 && remaining < 0) {
                remainingDiv.style.display = 'none';
                updateChangeDisplay();
                return;
            }

            if (payments.length > 0 || remaining < getCurrentTotal()) {
                remainingDiv.style.display = 'block';
                remainingSpan.textContent = remaining.toFixed(2).replace('.', ',');

                // Atualizar classe baseado no status
                remainingDiv.className = 'remaining-amount-display';
                if (Math.abs(remaining) < 0.01) {
                    remainingDiv.classList.add('complete');
                } else if (remaining < 0) {
                    remainingDiv.classList.add('overpaid');
                }
            } else {
                remainingDiv.style.display = 'none';
            }

            // Atualizar troco
            updateChangeDisplay();
        }

        function updateChangeDisplay() {
            const changeRow = document.getElementById('changeRow');
            const changeAmount = document.getElementById('changeAmount');
            const totalPaid = getTotalPaid();
            const currentTotal = getCurrentTotal();
            const change = totalPaid - currentTotal;

            // Mostrar troco apenas se o valor pago for maior que o total
            if (change > 0.01) {
                changeRow.style.display = 'flex';
                changeAmount.textContent = 'R$ ' + change.toFixed(2).replace('.', ',');
            } else {
                changeRow.style.display = 'none';
            }
        }

        function updatePaymentFormState() {
            const form = document.getElementById('paymentForm');
            const addBtn = document.getElementById('addPaymentBtn');
            const remaining = getRemainingAmount();
            
            // Atualizar classe do formulário
            form.className = 'payment-form';
            if (payments.length > 0) {
                form.classList.add('multiple-payments');
            }
            if (Math.abs(remaining) < 0.01) {
                form.classList.add('payment-complete');
            }
            
            // Desabilitar botão se pagamento está completo
            addBtn.disabled = Math.abs(remaining) < 0.01;
            
            updateRemainingDisplay();
        }

        function getRemainingAmount() {
            return getCurrentTotal() - getTotalPaid();
        }

        function getTotalPaid() {
            return payments.reduce((sum, payment) => sum + payment.amount, 0);
        }

        function getCurrentTotal() {
            const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
            const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
            const discount = (subtotal * discountPercent) / 100;
            return Math.max(0, subtotal - discount);
        }

        function getPaymentMethodName(method) {
            const methods = {
                'dinheiro': 'Dinheiro',
                'cartao_debito': 'Cartão de Débito',
                'cartao_credito': 'Cartão de Crédito',
                'pix': 'PIX',
                'transferencia': 'Transferência'
            };
            return methods[method] || method;
        }

        // ===== FUNÇÕES EXISTENTES MANTIDAS =====

        function loadAllProducts() {
            const formData = new FormData();
            formData.append('action', 'get_all_products');

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(products => {
                if (products.error) {
                    showAlert('Erro ao carregar produtos: ' + products.error, 'error');
                    return;
                }
                displaySearchResults(products);
                
                // Atualizar título
                document.getElementById('resultsTitle').innerHTML = 
                    '<i class="fas fa-list"></i> Todos os Produtos (' + products.length + ')';
            })
            .catch(error => {
                console.error('Erro ao carregar produtos:', error);
                showAlert('Erro ao carregar produtos', 'error');
            });
        }

        function searchProducts(query) {
            const formData = new FormData();
            formData.append('action', 'search_product');
            formData.append('search', query);

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(products => {
                displaySearchResults(products);
                
                // Atualizar título
                document.getElementById('resultsTitle').innerHTML = 
                    '<i class="fas fa-search"></i> Resultados da Busca (' + products.length + ')';
            })
            .catch(error => {
                console.error('Erro na busca:', error);
                showAlert('Erro ao buscar produtos', 'error');
            });
        }

        function displaySearchResults(products) {
            const resultsDiv = document.getElementById('searchResults');
            
            if (products.length === 0) {
                resultsDiv.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px; display: block; color: #ccc;"></i>
                        Nenhum produto encontrado
                    </div>
                `;
                return;
            }

            const html = products.map(product => {
                const stockClass = product.stock_quantity <= 0 ? 'no-stock' : 
                                 product.stock_quantity <= product.min_stock ? 'low-stock' : '';
                
                return `
                    <div class="product-card ${stockClass}" onclick="addToCart(${JSON.stringify(product).replace(/"/g, '&quot;')})">
                        <div class="product-name">${product.name}</div>
                        <div class="product-info">
                            <span><strong>Código:</strong> ${product.code}</span>
                            <span><strong>Preço:</strong> R$ ${parseFloat(product.sale_price).toFixed(2).replace('.', ',')}</span>
                            <span class="${stockClass}"><strong>Estoque:</strong> ${product.stock_quantity}</span>
                        </div>
                    </div>
                `;
            }).join('');

            resultsDiv.innerHTML = html;
        }

        function addToCart(product) {
            if (product.stock_quantity <= 0) {
                showAlert('Produto sem estoque!', 'error');
                return;
            }

            // Verificar se produto já está no carrinho
            const existingItem = cart.find(item => item.id === product.id);
            
            if (existingItem) {
                if (existingItem.quantity < product.stock_quantity) {
                    const oldQuantity = existingItem.quantity;
                    existingItem.quantity++;
                    existingItem.total = existingItem.quantity * existingItem.price;
                    
                    showAlert(`${product.name} - Quantidade: ${oldQuantity} → ${existingItem.quantity}`, 'success');
                } else {
                    showAlert('Quantidade máxima em estoque atingida!', 'error');
                    return;
                }
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.sale_price),
                    quantity: 1,
                    total: parseFloat(product.sale_price),
                    stock: product.stock_quantity,
                    allow_price_edit: product.allow_price_edit || 0
                });

                showAlert(`${product.name} adicionado ao carrinho!`, 'success');
            }

            updateCartDisplay();
            
            // Limpar busca
            document.getElementById('productSearch').value = '';
            loadAllProducts();
            document.getElementById('productSearch').focus();
        }

        function removeFromCart(productId) {
            const item = cart.find(item => item.id === productId);
            const itemName = item ? item.name : 'Produto';
            
            cart = cart.filter(item => item.id !== productId);
            updateCartDisplay();
            
            showAlert(`${itemName} removido do carrinho!`, 'warning');
        }

        function updateQuantity(productId, change) {
            const item = cart.find(item => item.id === productId);
            if (!item) return;

            const newQuantity = item.quantity + change;

            if (newQuantity <= 0) {
                removeFromCart(productId);
                return;
            }

            if (newQuantity > item.stock) {
                showAlert('Quantidade máxima em estoque atingida!', 'error');
                return;
            }

            const oldQuantity = item.quantity;
            item.quantity = newQuantity;
            item.total = item.quantity * item.price;
            updateCartDisplay();

            if (change > 0) {
                showAlert(`Quantidade aumentada: ${item.name} (${oldQuantity} → ${newQuantity})`, 'info');
            } else {
                showAlert(`Quantidade diminuída: ${item.name} (${oldQuantity} → ${newQuantity})`, 'info');
            }
        }

        function updateItemPrice(productId, newPrice) {
            const item = cart.find(item => item.id === productId);
            if (!item) return;

            if (!item.allow_price_edit) {
                showAlert('Este produto não permite edição de preço!', 'error');
                return;
            }

            const price = parseFloat(newPrice);
            if (isNaN(price) || price < 0) {
                showAlert('Preço inválido!', 'error');
                updateCartDisplay();
                return;
            }

            const oldPrice = item.price;
            item.price = price;
            item.total = item.quantity * item.price;
            updateCartDisplay();

            if (price !== oldPrice) {
                showAlert(`Preço atualizado: ${item.name} (R$ ${oldPrice.toFixed(2)} → R$ ${price.toFixed(2)})`, 'success');
            }
        }

        function updateCartDisplay() {
            const cartItemsDiv = document.getElementById('cartItems');
            const finalizeSaleBtn = document.getElementById('finalizeSale');

            if (cart.length === 0) {
                cartItemsDiv.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Carrinho Vazio</h3>
                        <p>Busque e adicione produtos ao carrinho</p>
                    </div>
                `;
                finalizeSaleBtn.disabled = true;
                updateSummary(0, 0, 0);
                return;
            }

            const html = cart.map(item => {
                // Determinar se o preço pode ser editado
                const priceDisplay = item.allow_price_edit == 1
                    ? `<input type="number"
                              value="${item.price.toFixed(2)}"
                              onblur="updateItemPrice(${item.id}, this.value)"
                              onkeypress="if(event.key==='Enter') this.blur()"
                              step="0.01"
                              min="0"
                              style="width: 80px; padding: 4px; border: 2px solid #4CAF50; border-radius: 4px; text-align: right; font-weight: bold;"
                              title="Preço editável - clique para alterar">`
                    : `<span style="color: #666;" title="Preço bloqueado - não editável">
                         <i class="fas fa-lock" style="font-size: 10px; margin-right: 3px;"></i>
                         R$ ${item.price.toFixed(2).replace('.', ',')}
                       </span>`;

                return `
                    <div class="cart-item">
                        <div class="cart-item-header">
                            <span class="cart-item-name">${item.name}</span>
                            <button class="remove-item" onclick="removeFromCart(${item.id})" title="Remover">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="cart-item-details">
                            <div class="quantity-controls">
                                <button class="qty-btn" onclick="updateQuantity(${item.id}, -1)">-</button>
                                <span style="margin: 0 8px; font-weight: bold;">${item.quantity}</span>
                                <button class="qty-btn" onclick="updateQuantity(${item.id}, 1)">+</button>
                            </div>
                            ${priceDisplay}
                            <span style="font-weight: bold;">R$ ${item.total.toFixed(2).replace('.', ',')}</span>
                        </div>
                    </div>
                `;
            }).join('');

            cartItemsDiv.innerHTML = html;
            finalizeSaleBtn.disabled = false;

            // Calcular totais
            const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
            const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
            const discount = (subtotal * discountPercent) / 100;
            const total = Math.max(0, subtotal - discount);

            updateSummary(subtotal, discount, total);
            updatePaymentFormState(); // Atualizar estado dos pagamentos
        }

        function updateSummary(subtotal, discount, total) {
            document.getElementById('subtotal').textContent = `R$ ${subtotal.toFixed(2).replace('.', ',')}`;
            document.getElementById('discountDisplay').textContent = `R$ ${discount.toFixed(2).replace('.', ',')}`;
            document.getElementById('total').textContent = `R$ ${total.toFixed(2).replace('.', ',')}`;

            // Atualizar troco quando o total mudar
            updateChangeDisplay();
        }

        function setupCustomerAutocomplete() {
            const input = document.getElementById('customerSearch');
            const suggestions = document.getElementById('customerSuggestions');

            input.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();

                if (query.length < 1) {
                    suggestions.style.display = 'none';
                    return;
                }

                const filtered = customers.filter(customer => 
                    customer.name.toLowerCase().includes(query) ||
                    customer.cpf_cnpj.includes(query) ||
                    customer.phone.includes(query)
                );

                if (filtered.length > 0) {
                    const html = filtered.map(customer => `
                        <div class="autocomplete-suggestion" onclick="selectCustomer(${customer.id}, '${customer.name}')">
                            <strong>${customer.name}</strong><br>
                            <small>${customer.cpf_cnpj} - ${customer.phone}</small>
                        </div>
                    `).join('');
                    
                    suggestions.innerHTML = html;
                    suggestions.style.display = 'block';
                } else {
                    suggestions.style.display = 'none';
                }
            });

            // Fechar sugestões ao clicar fora
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.style.display = 'none';
                }
            });
        }

        function selectCustomer(id, name) {
            document.getElementById('customerSearch').value = name;
            document.getElementById('customerId').value = id;
            document.getElementById('customerSuggestions').style.display = 'none';
            selectedCustomer = id;
            
            showAlert(`Cliente selecionado: ${name}`, 'info');
        }

        async function finalizeSale() {
            if (cart.length === 0) {
                showAlert('Carrinho vazio!', 'error');
                return;
            }

            const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
            const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
            const discount = (subtotal * discountPercent) / 100;
            const total = Math.max(0, subtotal - discount);
            const customerId = document.getElementById('customerId').value;

            if (total <= 0) {
                showAlert('Total da venda deve ser maior que zero!', 'error');
                return;
            }

            // Verificar formas de pagamento
            let finalPayments = [];
            
            if (payments.length > 0) {
                // Múltiplas formas de pagamento
                const totalPaid = getTotalPaid();

                // Permitir valor maior (com troco) ou exato
                // Bloquear apenas se pago MENOS que o total
                if (totalPaid < total - 0.01) {
                    showAlert(`Valor pago (R$ ${totalPaid.toFixed(2).replace('.', ',')}) é menor que o total da venda!`, 'error');
                    return;
                }

                finalPayments = payments;
            } else {
                // Forma única de pagamento (modo simples)
                const paymentMethod = document.getElementById('mainPaymentMethod').value;
                finalPayments = [{
                    method: paymentMethod,
                    amount: total
                }];
            }

            const confirmed = await showConfirmDialog('Finalizar Venda', `Confirma a venda de R$ ${total.toFixed(2).replace('.', ',')}?`, 'Finalizar', 'Cancelar');

            if (confirmed) {
                // Calcular troco se houver
                const totalPaid = finalPayments.reduce((sum, p) => sum + parseFloat(p.amount), 0);
                const changeAmount = Math.max(0, totalPaid - total);

                const formData = new FormData();
                formData.append('action', 'finalize_sale');
                formData.append('items', JSON.stringify(cart));
                formData.append('customer_name', document.getElementById('customerSearch').value);
                formData.append('discount', discount);
                formData.append('payments', JSON.stringify(finalPayments));
                formData.append('total_amount', subtotal);
                formData.append('final_amount', total);
                formData.append('change_amount', changeAmount);

                // Desabilitar botão
                document.getElementById('finalizeSale').disabled = true;
                document.getElementById('finalizeSale').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        try {
                            showPrintModal(data.sale_id, subtotal, discount, total, finalPayments, customerId, cart);
                        } catch (error) {
                            console.error('Erro ao abrir modal:', error);
                            // Se falhar ao abrir modal, limpar carrinho diretamente
                            resetSale();
                            showAlert('✅ Venda finalizada com sucesso! (ID: ' + data.sale_id + ')', 'success');
                        }
                    } else {
                        throw new Error(data.error || 'Erro desconhecido');
                    }
                })
                .catch(error => {
                    console.error('Erro ao finalizar venda:', error);
                    showAlert('Erro ao finalizar venda: ' + error.message, 'error');
                })
                .finally(() => {
                    // Reabilitar botão
                    document.getElementById('finalizeSale').disabled = false;
                    document.getElementById('finalizeSale').innerHTML = '<i class="fas fa-check"></i> Finalizar Venda';
                });
            }
        }

        function showPrintModal(saleId, subtotal, discount, total, finalPayments, customerId, cartItems) {
            // Verificar se elementos do modal existem
            const modalSaleId = document.getElementById('modalSaleId');
            const modalSummary = document.getElementById('modalSaleSummary');
            const printModal = document.getElementById('printModal');

            if (!modalSaleId || !modalSummary || !printModal) {
                console.error('Elementos do modal não encontrados');
                throw new Error('Modal de impressão não encontrado');
            }

            // Preencher dados do modal
            modalSaleId.textContent = saleId;

            const customerInfo = customerId ?
                customers.find(c => c.id == customerId) : null;

            // Gerar lista de pagamentos para exibir
            const paymentsText = finalPayments.map(p =>
                `${getPaymentMethodName(p.method)}: R$ ${p.amount.toFixed(2).replace('.', ',')}`
            ).join('<br>');

            const summaryHTML = `
                ${customerInfo ? `<div class="sale-summary-row"><span>Cliente:</span><span>${customerInfo.name}</span></div>` : ''}
                <div class="sale-summary-row"><span>Forma(s) de Pagamento:</span><span>${paymentsText}</span></div>
                <div class="sale-summary-row"><span>Subtotal:</span><span>R$ ${subtotal.toFixed(2).replace('.', ',')}</span></div>
                <div class="sale-summary-row"><span>Desconto:</span><span>R$ ${discount.toFixed(2).replace('.', ',')}</span></div>
                <div class="sale-summary-row sale-summary-total"><span>TOTAL:</span><span>R$ ${total.toFixed(2).replace('.', ',')}</span></div>
            `;

            modalSummary.innerHTML = summaryHTML;

            // Armazenar dados para impressão (usar cartItems passado como parâmetro)
            window.currentSaleData = {
                saleId,
                subtotal,
                discount,
                total,
                payments: finalPayments,
                customerInfo,
                items: cartItems || []
            };

            // Mostrar modal
            printModal.style.display = 'block';

            // Limpar carrinho imediatamente após abrir modal (venda já foi salva)
            setTimeout(() => {
                resetSale();
            }, 500);
        }

        function printReceipt() {
            // Usar dados armazenados
            const data = window.currentSaleData;
            if (!data) {
                showAlert('Erro: Dados da venda não encontrados!', 'error');
                return;
            }
            
            const customerName = data.customerInfo ? data.customerInfo.name : 'Cliente não informado';
            
            // Gerar lista de pagamentos para o cupom
            const paymentsHtml = data.payments.map(p => {
                let paymentText = getPaymentMethodName(p.method);
                if (p.method === 'cartao_credito' && p.installments > 1) {
                    const installmentValue = p.amount / p.installments;
                    paymentText += ` (${p.installments}x de R$ ${installmentValue.toFixed(2).replace('.', ',')})`;
                }
                return `
                <div class="row">
                    <span>${paymentText}:</span>
                    <span>R$ ${p.amount.toFixed(2).replace('.', ',')}</span>
                </div>
                `;
            }).join('');
            
            // Template do cupom
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Cupom de Venda #${data.saleId}</title>
                    <style>
                        body { 
                            font-family: 'Courier New', monospace; 
                            font-size: 12px; 
                            max-width: 300px; 
                            margin: 0 auto; 
                            padding: 10px;
                        }
                        .header { text-align: center; margin-bottom: 20px; }
                        .header h1 { margin: 0; font-size: 18px; }
                        .line { border-top: 1px dashed #000; margin: 10px 0; }
                        .row { display: flex; justify-content: space-between; margin: 5px 0; }
                        .total { font-weight: bold; font-size: 14px; }
                        .center { text-align: center; }
                        .items { margin: 15px 0; }
                        .item { margin: 8px 0; }
                        .item-name { font-weight: bold; }
                        .item-details { font-size: 11px; color: #666; }
                        .payments-section { margin: 10px 0; }
                        .payments-title { font-weight: bold; margin-bottom: 5px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>FL REPAROS</h1>
                        <p>Sistema de Gestão</p>
                        <p>CUPOM NÃO FISCAL</p>
                    </div>
                    
                    <div class="line"></div>
                    
                    <div class="row">
                        <span>Venda #:</span>
                        <span>${data.saleId}</span>
                    </div>
                    <div class="row">
                        <span>Data:</span>
                        <span>${new Date().toLocaleString('pt-BR')}</span>
                    </div>
                    <div class="row">
                        <span>Cliente:</span>
                        <span>${customerName}</span>
                    </div>
                    <div class="row">
                        <span>Atendente:</span>
                        <span>Usuario #<?php echo $_SESSION['user_id']; ?></span>
                    </div>
                    
                    <div class="line"></div>
                    
                    <div class="items">
                        <div style="font-weight: bold; margin-bottom: 10px;">ITENS:</div>
                        ${data.items.map(item => `
                            <div class="item">
                                <div class="item-name">${item.name}</div>
                                <div class="item-details">
                                    ${item.quantity}x R$ ${item.price.toFixed(2).replace('.', ',')} = R$ ${item.total.toFixed(2).replace('.', ',')}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="line"></div>
                    
                    <div class="row">
                        <span>Subtotal:</span>
                        <span>R$ ${data.subtotal.toFixed(2).replace('.', ',')}</span>
                    </div>
                    ${data.discount > 0 ? `
                    <div class="row">
                        <span>Desconto:</span>
                        <span>R$ ${data.discount.toFixed(2).replace('.', ',')}</span>
                    </div>
                    ` : ''}
                    <div class="row total">
                        <span>TOTAL:</span>
                        <span>R$ ${data.total.toFixed(2).replace('.', ',')}</span>
                    </div>
                    
                    <div class="line"></div>
                    
                    <div class="payments-section">
                        <div class="payments-title">PAGAMENTO:</div>
                        ${paymentsHtml}
                    </div>
                    
                    <div class="line"></div>
                    
                    <div class="center" style="margin-top: 20px;">
                        <p>Obrigado pela preferência!</p>
                        <p>Volte sempre!</p>
                        <br>
                        <small>Sistema FL REPAROS v1.0</small>
                    </div>
                </body>
                </html>
            `;
            
            // Abrir janela de impressão
            const printWindow = window.open('', '_blank', 'width=400,height=600');
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Aguardar carregamento e imprimir
            printWindow.onload = function() {
                setTimeout(() => {
                    printWindow.print();
                    printWindow.close();
                }, 500);
            };
            
            // Fechar modal após iniciar impressão
            setTimeout(() => {
                showAlert('Cupom enviado para impressão!', 'success');
                closePrintModal();
            }, 1000);
        }

        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';

            // Limpar carrinho e resetar PDV
            setTimeout(() => {
                resetSale();
                showAlert('✅ Nova venda iniciada! PDV limpo e pronto.', 'success');
            }, 100);
        }

        function resetSale() {
            // Limpar arrays
            cart = [];
            payments = [];

            // Limpar campos do formulário (com verificação de null)
            const customerSearch = document.getElementById('customerSearch');
            const customerId = document.getElementById('customerId');
            const discountPercent = document.getElementById('discountPercent');
            const mainPaymentMethod = document.getElementById('mainPaymentMethod');
            const multiPaymentAmount = document.getElementById('multiPaymentAmount');
            const productSearch = document.getElementById('productSearch');
            const finalizeSale = document.getElementById('finalizeSale');

            if (customerSearch) customerSearch.value = '';
            if (customerId) customerId.value = '';
            if (discountPercent) discountPercent.value = '0';
            if (mainPaymentMethod) mainPaymentMethod.value = 'dinheiro';
            if (multiPaymentAmount) multiPaymentAmount.value = '';

            selectedCustomer = null;

            // Resetar controle de desconto
            discountAuthorized = false;
            lastValidDiscount = 0;

            // Voltar ao modo simples (com proteção contra erro)
            try {
                if (typeof switchToSimpleMode === 'function') {
                    switchToSimpleMode();
                }
            } catch (e) {
                console.error('Erro em switchToSimpleMode:', e);
            }

            // Atualizar todas as exibições (com proteção contra erro)
            try {
                if (typeof updateCartDisplay === 'function') {
                    updateCartDisplay();
                }
            } catch (e) {
                console.error('Erro em updateCartDisplay:', e);
            }

            try {
                if (typeof updatePaymentsList === 'function') {
                    updatePaymentsList();
                }
            } catch (e) {
                console.error('Erro em updatePaymentsList:', e);
            }

            try {
                if (typeof updatePaymentFormState === 'function') {
                    updatePaymentFormState();
                }
            } catch (e) {
                console.error('Erro em updatePaymentFormState:', e);
            }

            // Limpar busca de produtos e recarregar lista
            if (productSearch) {
                productSearch.value = '';
            }

            try {
                if (typeof loadAllProducts === 'function') {
                    loadAllProducts();
                }
            } catch (e) {
                console.error('Erro em loadAllProducts:', e);
            }

            // Focar no campo de busca
            if (productSearch) {
                productSearch.focus();
            }

            // Garantir que botão finalizar está desabilitado
            if (finalizeSale) {
                finalizeSale.disabled = true;
            }
        }

        function showAlert(message, type) {
            // Remover alertas existentes
            const existingAlerts = document.querySelectorAll('.toast-alert');
            existingAlerts.forEach(alert => alert.remove());

            const alert = document.createElement('div');
            alert.className = `toast-alert ${type}`;
            
            // Ícones para cada tipo
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle', 
                info: 'fas fa-info-circle',
                warning: 'fas fa-exclamation-triangle'
            };
            
            // Ícones específicos para ações do carrinho
            const actionIcons = {
                'adicionado': 'fas fa-cart-plus',
                'removido': 'fas fa-cart-arrow-down',
                'aumentada': 'fas fa-plus-circle',
                'diminuída': 'fas fa-minus-circle',
                'selecionado': 'fas fa-user-check',
                'enviado': 'fas fa-print',
                'foram removidas': 'fas fa-broom'
            };
            
            // Detectar ícone específico baseado na mensagem
            let iconClass = icons[type] || icons.info;
            for (const [keyword, icon] of Object.entries(actionIcons)) {
                if (message.toLowerCase().includes(keyword)) {
                    iconClass = icon;
                    break;
                }
            }
            
            alert.innerHTML = `
                <i class="toast-icon ${iconClass}"></i>
                <span>${message}</span>
            `;

            document.body.appendChild(alert);

            // Auto-hide após 4 segundos
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.animation = 'toastSlideOut 0.3s ease';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 4000);
            
            // Click para fechar
            alert.addEventListener('click', () => {
                alert.style.animation = 'toastSlideOut 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            });
        }

        // Função para mostrar modal de confirmação
        function showConfirmDialog(title, message, okText = 'OK', cancelText = 'Cancelar') {
            return new Promise((resolve) => {
                const modal = document.getElementById('confirmModal');
                const titleEl = document.getElementById('confirmTitle');
                const messageEl = document.getElementById('confirmMessage');
                const okBtn = document.getElementById('confirmOk');
                const cancelBtn = document.getElementById('confirmCancel');
                
                // Verificar se elementos existem
                if (!modal || !titleEl || !messageEl || !okBtn || !cancelBtn) {
                    console.error('Elementos do modal não encontrados');
                    resolve(window.confirm(message)); // Fallback para confirm nativo
                    return;
                }
                
                titleEl.textContent = title;
                messageEl.textContent = message;
                okBtn.textContent = okText;
                cancelBtn.textContent = cancelText;
                
                modal.style.display = 'block';
                
                // Função para limpar e fechar
                const closeModal = (result) => {
                    modal.style.display = 'none';
                    document.removeEventListener('keydown', escHandler);
                    resolve(result);
                };
                
                // Handler para ESC
                const escHandler = (e) => {
                    if (e.key === 'Escape') {
                        closeModal(false);
                    }
                };
                
                // Limpar listeners anteriores e adicionar novos
                const newOkBtn = okBtn.cloneNode(true);
                const newCancelBtn = cancelBtn.cloneNode(true);
                okBtn.parentNode.replaceChild(newOkBtn, okBtn);
                cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
                
                newOkBtn.addEventListener('click', () => closeModal(true));
                newCancelBtn.addEventListener('click', () => closeModal(false));
                document.addEventListener('keydown', escHandler);
            });
        }

        // Foco inicial
        document.getElementById('productSearch').focus();

        console.log('🛒 PDV Rápido com Pagamentos Melhorados carregado!');
        console.log('⌨️ Atalhos: F3=Buscar, F8=Pagamento, F9=Finalizar, F12=Ver Todos, ESC=Foco/Fechar Modal');
        console.log('💰 Novo: Link + Enter, sem botões, fluxo natural');
    </script>
</body>
</html>