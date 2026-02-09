<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

require_once('../../config/database.php');
$conn = $database->getConnection();

$osId = (int) ($_GET['os_id'] ?? 0);

if ($osId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            soi.type,
            soi.product_id,
            soi.description,
            soi.quantity,
            soi.unit_price,
            soi.total_price,
            p.name as product_name
        FROM service_order_items soi
        LEFT JOIN products p ON soi.product_id = p.id
        WHERE soi.service_order_id = ?
        ORDER BY soi.id ASC
    ");

    $stmt->execute([$osId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'products' => $items
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar itens: ' . $e->getMessage()
    ]);
}
