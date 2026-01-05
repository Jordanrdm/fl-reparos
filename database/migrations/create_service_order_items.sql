-- =========================================================
-- Migration: Criar tabela service_order_items
-- Data: 2026-01-04
-- Objetivo: Vincular produtos do estoque às ordens de serviço
-- =========================================================

CREATE TABLE IF NOT EXISTS service_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL COMMENT 'Preço unitário no momento do uso',
    subtotal DECIMAL(10,2) NOT NULL COMMENT 'quantity * price',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (service_order_id) REFERENCES service_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),

    INDEX idx_service_order (service_order_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificar resultado
SELECT 'Tabela service_order_items criada com sucesso!' as status;
DESCRIBE service_order_items;
