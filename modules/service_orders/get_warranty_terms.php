<?php
/**
 * Retorna os termos de garantia configurados
 */

header('Content-Type: application/json');

$warrantyConfig = require('../../config/warranty_terms.php');

echo json_encode($warrantyConfig, JSON_UNESCAPED_UNICODE);
