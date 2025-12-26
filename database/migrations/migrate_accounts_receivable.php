<?php
require_once __DIR__ . '/../../config/database.php';


try {
    $db = new Database();
    $conn = $db->connect();

    $sql = file_get_contents(__DIR__ . '/create_accounts_receivable_table.sql');
    $conn->exec($sql);

    echo "✅ Tabela 'accounts_receivable' criada com sucesso!";
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela: " . $e->getMessage();
}
