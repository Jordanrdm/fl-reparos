<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

require_once('../../config/database.php');
$conn = $database->getConnection();

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID não fornecido']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, name, email, phone, address FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch();

    if ($customer) {
        header('Content-Type: application/json');
        echo json_encode($customer);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente não encontrado']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar cliente']);
}
