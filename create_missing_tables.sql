-- Criar tabelas que faltaram no FL REPAROS
-- Desabilitar verificação de foreign keys temporariamente
SET FOREIGN_KEY_CHECKS=0;

-- Tabela cash_flow
CREATE TABLE IF NOT EXISTS `cash_flow` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('opening','sale','service','expense','closing') NOT NULL,
  `description` varchar(200) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  CONSTRAINT `cash_flow_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela cash_register
CREATE TABLE IF NOT EXISTS `cash_register` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `opening_date` datetime NOT NULL,
  `closing_date` datetime DEFAULT NULL,
  `opening_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `closing_balance` decimal(10,2) DEFAULT NULL,
  `total_sales` decimal(10,2) DEFAULT 0.00,
  `total_expenses` decimal(10,2) DEFAULT 0.00,
  `total_cash` decimal(10,2) DEFAULT 0.00,
  `total_pix` decimal(10,2) DEFAULT 0.00,
  `total_debit_card` decimal(10,2) DEFAULT 0.00,
  `total_credit_card` decimal(10,2) DEFAULT 0.00,
  `observations` text DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `cash_register_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela expenses
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(255) NOT NULL,
  `type` enum('fixa','variavel','fornecedor') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `payment_method` enum('dinheiro','pix','cartao','boleto') NOT NULL,
  `status` enum('pago','pendente') DEFAULT 'pendente',
  `observations` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela service_orders
CREATE TABLE IF NOT EXISTS `service_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `entry_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `exit_datetime` datetime DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `device` varchar(200) NOT NULL,
  `problem` text NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `solution` text DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('dinheiro','pix','cartao_credito','cartao_debito') DEFAULT NULL,
  `installments` int(11) DEFAULT 1,
  `status` enum('open','in_progress','completed','delivered','cancelled') DEFAULT 'open',
  `technical_report` text DEFAULT NULL,
  `reported_problem` text DEFAULT NULL,
  `customer_observations` text DEFAULT NULL,
  `internal_observations` text DEFAULT NULL,
  `device_powers_on` enum('sim','nao') DEFAULT 'sim',
  `device_password` varchar(100) DEFAULT NULL,
  `checklist_case` tinyint(1) DEFAULT 0,
  `checklist_screen_protector` tinyint(1) DEFAULT 0,
  `checklist_camera` tinyint(1) DEFAULT 0,
  `checklist_housing` tinyint(1) DEFAULT 0,
  `checklist_lens` tinyint(1) DEFAULT 0,
  `checklist_battery` tinyint(1) DEFAULT 0,
  `checklist_buttons` tinyint(1) DEFAULT 0,
  `checklist_chip_tray` tinyint(1) DEFAULT 0,
  `checklist_charger` tinyint(1) DEFAULT 0,
  `checklist_headphones` tinyint(1) DEFAULT 0,
  `technician_name` varchar(100) DEFAULT NULL,
  `assistant_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `warranty_expiration` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `service_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `service_orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela service_order_items
CREATE TABLE IF NOT EXISTS `service_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `type` enum('product','service') DEFAULT 'product',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `service_order_id` (`service_order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `service_order_items_ibfk_1` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reabilitar verificação de foreign keys
SET FOREIGN_KEY_CHECKS=1;

SELECT 'Tabelas criadas com sucesso!' as status;
