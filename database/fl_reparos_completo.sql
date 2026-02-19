-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: fl_reparos
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounts_receivable`
--

DROP TABLE IF EXISTS `accounts_receivable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts_receivable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `due_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `accounts_receivable_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts_receivable`
--

LOCK TABLES `accounts_receivable` WRITE;
/*!40000 ALTER TABLE `accounts_receivable` DISABLE KEYS */;
INSERT INTO `accounts_receivable` VALUES (4,1,'Teste','2025-12-10',NULL,111.00,'pending','2025-12-07 18:49:47','2025-12-07 19:09:02'),(5,5,'Teste','2025-12-12','2025-12-07',200.00,'overdue','2025-12-07 19:10:07','2025-12-26 01:07:59');
/*!40000 ALTER TABLE `accounts_receivable` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendants`
--

DROP TABLE IF EXISTS `attendants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendants`
--

LOCK TABLES `attendants` WRITE;
/*!40000 ALTER TABLE `attendants` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_flow`
--

DROP TABLE IF EXISTS `cash_flow`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_flow` (
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
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_flow`
--

LOCK TABLES `cash_flow` WRITE;
/*!40000 ALTER TABLE `cash_flow` DISABLE KEYS */;
INSERT INTO `cash_flow` VALUES (1,8,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-07 16:11:17'),(2,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-07 16:11:23'),(3,8,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-08 13:41:45'),(4,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-08 13:43:17'),(8,8,'sale','Venda #27 - Dinheiro - Cliente: Ana Costa',80.00,27,'sale','2026-02-08 20:54:53'),(9,8,'sale','Venda #28 - Cartão de Débito',80.00,28,'sale','2026-02-08 20:55:33'),(11,8,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-09 11:18:18'),(12,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-09 11:18:27'),(13,8,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-09 23:21:32'),(14,10,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-09 23:21:36'),(16,10,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-09 23:28:16'),(17,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-09 23:28:21'),(18,8,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-11 23:39:37'),(19,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-11 23:39:41'),(20,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-14 12:40:55'),(21,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-17 16:33:32'),(22,8,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-17 16:41:08'),(23,9,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-17 16:41:12'),(24,9,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-17 17:05:55'),(25,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-17 17:05:59'),(26,8,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-17 17:10:45'),(27,9,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-17 17:10:57'),(28,9,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-17 17:17:59'),(29,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-17 17:18:09'),(30,8,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-17 17:37:14'),(31,11,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-17 17:37:18'),(32,11,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-17 17:46:07'),(33,11,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-17 17:46:16'),(34,11,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-17 17:48:18'),(35,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-17 17:48:23'),(36,8,'closing','Logout do sistema',0.00,NULL,NULL,'2026-02-17 17:54:18'),(37,11,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-17 17:54:24'),(38,8,'opening','Login no sistema',0.00,NULL,NULL,'2026-02-18 23:11:25');
/*!40000 ALTER TABLE `cash_flow` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_register`
--

DROP TABLE IF EXISTS `cash_register`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_register` (
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_register`
--

LOCK TABLES `cash_register` WRITE;
/*!40000 ALTER TABLE `cash_register` DISABLE KEYS */;
INSERT INTO `cash_register` VALUES (1,8,'2026-02-08 22:58:39',NULL,0.00,NULL,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'open','2026-02-09 01:58:39','2026-02-09 01:58:39');
/*!40000 ALTER TABLE `cash_register` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Smartphones','Aparelhos celulares',1,'2025-07-29 19:10:34'),(2,'Acessórios','Capas, películas, carregadores',1,'2025-07-29 19:10:34'),(3,'Peças','Telas, baterias, conectores',1,'2025-07-29 19:10:34'),(4,'Serviços','Mão de obra para reparos',1,'2025-07-29 19:10:34');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `cpf_cnpj` varchar(18) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `neighborhood` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(2) DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Data de deleção (soft delete)',
  PRIMARY KEY (`id`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'João Silva','123.456.789-10','(85) 99999-1234',NULL,'joao@email.com','Rua das Flores, 123',NULL,'Fortaleza','CE','60000-000','Cliente preferencial','2025-07-29 20:47:50','2025-07-29 20:47:50',NULL),(2,'Maria Santos','987.654.321-00','(85) 88888-5678',NULL,'maria@email.com','Av. Principal, 456',NULL,'Fortaleza','CE','60100-000','Reparo de iPhone','2025-07-29 20:47:50','2025-07-29 20:47:50',NULL),(3,'Pedro Oliveira','111.222.333-44','(85) 77777-9999',NULL,'pedro@email.com','Rua do Comércio, 789',NULL,'Fortaleza','CE','60200-000','Cliente desde 2020','2025-07-29 20:47:50','2025-07-29 20:47:50',NULL),(4,'Ana Costa','555.666.777-88','(85) 66666-1111',NULL,'ana@email.com','Praça Central, 321',NULL,'Fortaleza','CE','60300-000','Compra acessórios','2025-07-29 20:47:50','2025-07-29 20:47:50',NULL),(5,'Carlos Ferreira','999.888.777-66','(85) 55555-2222',NULL,'carlos@email.com','Rua Nova, 654',NULL,'Fortaleza','CE','60400-000','Técnico em eletrônicos','2025-07-29 20:47:50','2025-07-29 20:47:50',NULL),(9,'Jordan Rodrigues','12345678912','85997458954',NULL,'jordan@gmail.com','',NULL,'','','','','2026-02-17 17:09:24','2026-02-17 17:09:24',NULL),(10,'Vendedor teste','00000012345','85997452323',NULL,'vendedor@gmail.com','',NULL,'','','','','2026-02-17 17:12:03','2026-02-17 17:12:03',NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deleted_sales`
--

DROP TABLE IF EXISTS `deleted_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `customer_name` varchar(200) DEFAULT NULL,
  `seller_name` varchar(200) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `final_amount` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(200) DEFAULT NULL,
  `items_json` text DEFAULT NULL,
  `deleted_by` int(11) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deleted_sales`
--

LOCK TABLES `deleted_sales` WRITE;
/*!40000 ALTER TABLE `deleted_sales` DISABLE KEYS */;
/*!40000 ALTER TABLE `deleted_sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('product','service') DEFAULT 'product',
  `name` varchar(200) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `unit` varchar(10) DEFAULT 'UN',
  `margin_percent` decimal(5,2) DEFAULT 0.00,
  `profit` decimal(10,2) DEFAULT 0.00,
  `supplier` varchar(200) DEFAULT NULL,
  `warranty` varchar(100) DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `allow_price_edit` tinyint(1) DEFAULT 1 COMMENT 'Permite editar preço na venda (0=Não, 1=Sim)',
  `stock_quantity` int(11) DEFAULT 0,
  `min_stock` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'product','iPhone 14 Pro 128GB',NULL,'IP14P128','1234567890123',1,4500.00,5200.00,'UN',15.56,700.00,NULL,NULL,NULL,1,21,2,'iPhone 14 Pro 128GB - Cor Azul',1,'2025-07-29 19:40:26','2026-02-09 01:17:31'),(2,'product','Samsung Galaxy S23',NULL,'SGS23','2345678901234',1,3200.00,3800.00,'UN',18.75,600.00,NULL,NULL,NULL,1,8,3,'Samsung Galaxy S23 256GB',0,'2025-07-29 19:40:26','2026-02-09 01:17:31'),(3,'product','Película de Vidro Temperado',NULL,'PVT001','3456789012345',2,8.50,25.00,'UN',194.12,16.50,NULL,NULL,NULL,1,46,10,'Película universal para smartphones',1,'2025-07-29 19:40:26','2026-02-09 01:17:31'),(4,'product','Capa TPU Transparente',NULL,'CTPU001','4567890123456',2,5.00,15.00,'UN',200.00,10.00,NULL,NULL,NULL,1,28,5,'Capa TPU flexível transparente',1,'2025-07-29 19:40:26','2026-02-09 01:17:31'),(5,'product','Display iPhone 12',NULL,'DIP12','5678901234567',3,180.00,280.00,'UN',55.56,100.00,NULL,NULL,NULL,1,29,2,'Display original iPhone 12',1,'2025-07-29 19:40:26','2026-02-09 01:17:31'),(6,'product','Bateria Samsung A32',NULL,'BSA32','6789012345678',3,45.00,80.00,'UN',77.78,35.00,NULL,NULL,NULL,1,12,3,'Bateria original Samsung Galaxy A32s',1,'2025-07-29 19:40:26','2026-02-09 23:22:58'),(7,'product','Kit Chaves para Celular',NULL,'KCC001','7890123456789',4,25.00,45.00,'UN',80.00,20.00,NULL,NULL,NULL,1,4,2,'Kit com 8 chaves para abertura de smartphones',1,'2025-07-29 19:40:26','2026-02-09 01:17:31'),(8,'product','Cabo Iphone',NULL,'CBIPH7','',2,14.00,35.00,'UN',150.00,21.00,NULL,NULL,NULL,1,0,2,'',1,'2025-07-30 01:59:03','2026-02-09 01:17:31'),(9,'product','Suporte Veicular',NULL,'SPV745','',2,15.00,49.99,'UN',233.27,34.99,NULL,NULL,NULL,1,4,2,'Suporte para Veiculo',1,'2025-07-30 01:59:56','2026-02-09 01:17:31'),(10,'product','teste',NULL,'000000','00000',4,100.00,120.00,'UN',20.00,20.00,NULL,NULL,NULL,1,1,1,'',0,'2025-11-16 19:56:14','2026-02-09 01:17:31'),(11,'product','teste','prod_69894cde60f0e.jpeg','123456789','123456789',2,10.00,20.00,'UN',100.00,10.00,'','','',1,10,1,'',1,'2026-02-09 02:56:30','2026-02-09 02:56:30'),(13,'service','teste',NULL,'323232','323232',4,NULL,100.00,'UN',0.00,NULL,'','','',1,999,0,'',1,'2026-02-09 23:13:48','2026-02-09 23:13:48'),(14,'product','Produto teste','prod_69906dd0877b9.jpeg','123456','123456',2,10.00,50.00,'UN',400.00,40.00,'AA','','',1,20,5,'',1,'2026-02-14 12:42:56','2026-02-14 12:42:56'),(15,'service','Serviço teste','prod_69906f09244c8.jpeg','123456','123456',4,NULL,50.00,'UN',0.00,NULL,'AA','','',1,999,0,'',1,'2026-02-14 12:48:09','2026-02-14 12:48:09'),(16,'product','Produto vendendor teste','prod_69949af807f2e.jpg','123456789','123456789',2,5.00,50.00,'UN',900.00,45.00,'','','',1,13,0,'',1,'2026-02-17 16:44:40','2026-02-17 16:55:05'),(17,'service','Serviço vendedor teste','prod_69949b66b3780.webp','123435535','123435535',4,NULL,50.00,'UN',0.00,NULL,'','','',1,999,0,'',1,'2026-02-17 16:46:30','2026-02-17 16:46:30'),(18,'product','teste vendedor','prod_69949cfe85d90.jpg','123444','123444',2,5.00,50.00,'UN',900.00,45.00,'','','',1,0,0,'',1,'2026-02-17 16:53:18','2026-02-17 16:53:18'),(19,'service','serviço teste vendedor','prod_69949d243ceee.webp','123443434','123443434',4,NULL,50.00,'UN',0.00,NULL,'','','',1,999,0,'',1,'2026-02-17 16:53:56','2026-02-17 16:53:56'),(20,'product','Geladeira','prod_6994a9119f064.jpeg','123434435','123434435',2,5.00,50.00,'UN',900.00,45.00,'','','',0,49,0,'',1,'2026-02-17 17:44:49','2026-02-17 17:58:11'),(21,'product','Padinha','prod_6994b0b487367.webp','1234576787','1234576787',2,5.00,20.00,'UN',300.00,15.00,'','','',0,0,0,'',1,'2026-02-17 18:17:24','2026-02-17 18:17:24');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_items`
--

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
INSERT INTO `sale_items` VALUES (1,1,5,2,280.00,560.00),(2,2,4,1,15.00,15.00),(3,3,5,1,280.00,280.00),(4,4,3,1,25.00,25.00),(5,5,4,1,15.00,15.00),(6,5,7,2,45.00,90.00),(7,6,1,2,5200.00,10400.00),(8,6,7,2,45.00,90.00),(9,6,3,2,25.00,50.00),(10,7,7,1,45.00,45.00),(11,8,1,1,5200.00,5200.00),(12,9,3,1,25.00,25.00),(13,10,6,1,80.00,80.00),(14,11,6,1,80.00,80.00),(15,12,6,1,80.00,80.00),(16,13,6,1,80.00,80.00),(17,14,6,1,80.00,80.00),(18,15,1,2,5200.00,10400.00),(19,16,6,1,80.00,80.00),(20,17,1,1,5200.00,5200.00),(21,18,6,1,80.00,80.00),(22,19,5,1,280.00,280.00),(23,20,6,2,80.00,160.00),(24,21,6,2,80.00,160.00),(25,22,6,1,80.00,80.00),(26,23,6,1,80.00,80.00),(27,24,7,1,45.00,45.00),(30,27,6,1,80.00,80.00),(31,28,6,1,80.00,80.00);
/*!40000 ALTER TABLE `sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(100) DEFAULT NULL,
  `installments` int(11) DEFAULT 1,
  `status` enum('completed','cancelled','pending') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,NULL,1,560.00,0.00,560.00,'Dinheiro',1,'completed',NULL,'2025-07-29 20:48:43'),(2,1,1,15.00,5.00,10.00,'Dinheiro',1,'completed',NULL,'2025-07-29 20:52:48'),(3,NULL,1,280.00,10.00,270.00,'PIX',1,'completed',NULL,'2025-07-29 21:10:05'),(4,NULL,1,25.00,2.00,23.00,'Dinheiro',1,'completed',NULL,'2025-07-29 21:23:58'),(5,NULL,1,105.00,15.00,90.00,'Dinheiro',1,'completed',NULL,'2025-07-29 21:30:53'),(6,NULL,1,10540.00,300.00,10240.00,'Dinheiro',1,'completed',NULL,'2025-07-29 21:34:32'),(7,NULL,1,45.00,0.00,45.00,'Dinheiro',1,'completed',NULL,'2025-07-30 00:32:49'),(8,NULL,1,5200.00,150.00,5050.00,'Dinheiro',1,'completed',NULL,'2025-07-30 01:51:45'),(9,NULL,1,25.00,0.00,25.00,'Dinheiro',1,'completed',NULL,'2025-07-30 01:52:49'),(10,NULL,1,80.00,0.00,80.00,'Dinheiro',1,'completed',NULL,'2025-07-30 02:01:51'),(11,NULL,1,80.00,0.00,80.00,'Dinheiro',1,'completed',NULL,'2025-07-30 02:30:36'),(12,NULL,1,80.00,50.00,30.00,'Dinheiro',1,'completed',NULL,'2025-08-16 14:27:03'),(13,NULL,1,80.00,5.00,75.00,'Dinheiro',1,'completed',NULL,'2025-08-16 14:30:27'),(14,NULL,1,80.00,0.00,80.00,'Dinheiro',1,'completed',NULL,'2025-08-16 14:44:04'),(15,NULL,1,10400.00,4.50,10395.50,'PIX',1,'completed',NULL,'2025-10-10 21:15:58'),(16,NULL,1,80.00,20.00,60.00,'Dinheiro',1,'completed',NULL,'2025-11-01 23:43:35'),(17,NULL,1,5200.00,0.00,5200.00,'Dinheiro',1,'completed',NULL,'2025-11-01 23:44:25'),(18,NULL,1,80.00,20.00,60.00,'Dinheiro',1,'completed',NULL,'2025-11-02 00:06:06'),(19,NULL,1,280.00,0.00,280.00,'Dinheiro',1,'completed',NULL,'2025-11-16 21:16:00'),(20,NULL,1,160.00,0.00,160.00,'Dinheiro',1,'completed',NULL,'2025-11-28 01:12:55'),(21,NULL,1,160.00,0.00,160.00,'PIX',1,'completed',NULL,'2025-12-09 02:55:49'),(22,NULL,1,80.00,4.00,76.00,'Dinheiro',1,'completed',NULL,'2025-12-25 22:24:36'),(23,NULL,1,80.00,0.00,80.00,'Dinheiro',1,'completed',NULL,'2026-01-01 21:23:36'),(24,NULL,1,45.00,2.25,42.75,'Dinheiro',1,'completed',NULL,'2026-01-02 17:52:37'),(27,NULL,8,80.00,0.00,80.00,'Dinheiro',1,'completed',NULL,'2026-02-08 20:54:53'),(28,1,8,80.00,0.00,80.00,'Cartão de Débito',1,'completed',NULL,'2026-02-08 20:55:33');
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_order_items`
--

DROP TABLE IF EXISTS `service_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_order_items` (
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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_order_items`
--

LOCK TABLES `service_order_items` WRITE;
/*!40000 ALTER TABLE `service_order_items` DISABLE KEYS */;
INSERT INTO `service_order_items` VALUES (2,4,6,'Bateria Samsung A32',1,10.00,10.00,'product','2026-02-09 20:32:20'),(12,5,6,'Bateria Samsung A32',1,80.00,80.00,'product','2026-02-09 23:22:58'),(13,5,13,'teste',1,100.00,100.00,'service','2026-02-09 23:22:58'),(14,5,13,'teste',1,100.00,100.00,'service','2026-02-09 23:22:58'),(15,5,13,'teste',1,100.00,100.00,'service','2026-02-09 23:22:58'),(18,7,17,'Serviço vendedor teste',1,50.00,50.00,'service','2026-02-17 16:50:54'),(19,7,16,'Produto vendendor teste',1,40.00,40.00,'product','2026-02-17 16:50:54'),(20,8,16,'Produto vendendor teste',1,30.00,30.00,'product','2026-02-17 16:55:05'),(21,8,19,'serviço teste vendedor',1,50.00,50.00,'service','2026-02-17 16:55:05'),(22,9,20,'Geladeira',1,40.00,40.00,'product','2026-02-17 17:58:11');
/*!40000 ALTER TABLE `service_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_orders`
--

DROP TABLE IF EXISTS `service_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `entry_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `exit_datetime` datetime DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `device` varchar(200) NOT NULL,
  `device_type` varchar(50) DEFAULT 'celular',
  `problem` text NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `solution` text DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('dinheiro','pix','cartao_credito','cartao_debito') DEFAULT NULL,
  `payment_methods` text DEFAULT NULL,
  `installments` int(11) DEFAULT 1,
  `status` enum('open','in_progress','completed','invoiced','delivered','cancelled') DEFAULT 'open',
  `technical_report` text DEFAULT NULL,
  `reported_problem` text DEFAULT NULL,
  `customer_observations` text DEFAULT NULL,
  `internal_observations` text DEFAULT NULL,
  `device_powers_on` enum('sim','nao') DEFAULT 'sim',
  `device_password` varchar(100) DEFAULT NULL,
  `password_pattern` varchar(50) DEFAULT NULL,
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
  `change_amount` decimal(10,2) DEFAULT 0.00,
  `deposit_amount` decimal(10,2) DEFAULT 0.00,
  `checklist_lens_condition` varchar(100) DEFAULT NULL,
  `checklist_back_cover` varchar(200) DEFAULT NULL,
  `checklist_screen` tinyint(1) DEFAULT 0,
  `checklist_connector` tinyint(1) DEFAULT 0,
  `checklist_camera_front_back` varchar(50) DEFAULT NULL,
  `checklist_face_id` tinyint(1) DEFAULT 0,
  `checklist_sim_card` tinyint(1) DEFAULT 0,
  `warranty_period` varchar(50) DEFAULT '90 dias',
  `image` varchar(255) DEFAULT NULL,
  `attendant_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `service_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `service_orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_orders`
--

LOCK TABLES `service_orders` WRITE;
/*!40000 ALTER TABLE `service_orders` DISABLE KEYS */;
INSERT INTO `service_orders` VALUES (1,4,'2026-02-08 23:31:22',NULL,8,'Iphone 15','celular','',NULL,NULL,0.00,0.00,'',NULL,1,'in_progress','','','','','sim','',NULL,0,0,0,0,0,0,0,0,0,0,'',NULL,'2026-02-09 02:31:22','2026-02-09 02:32:58',NULL,0.00,0.00,'','',0,0,'',0,0,'90 dias',NULL,''),(2,4,'2026-02-09 09:24:41',NULL,8,'Iphone 15','celular','',NULL,NULL,0.00,0.00,'dinheiro',NULL,1,'open','','','','','sim','','1,2,3,5,6,7',0,0,0,0,1,0,0,0,0,0,'',NULL,'2026-02-09 12:24:41','2026-02-09 12:24:41',NULL,0.00,0.00,'arranhada','',1,1,'frontal',0,0,'90 dias',NULL,''),(4,4,'2026-02-09 17:31:06',NULL,8,'Iphone 15','celular','',NULL,NULL,10.00,0.00,'dinheiro','[{\"method\":\"dinheiro\",\"amount\":100,\"installments\":1},{\"method\":\"cartao_credito\",\"amount\":50,\"installments\":1}]',1,'open','','ddkkkkkkkkkkkkkkkkkkkkkkkkk','ffffffffff','','sim','123456','',0,0,0,0,0,0,0,0,0,0,'',NULL,'2026-02-09 20:31:06','2026-02-09 20:32:20',NULL,0.00,0.00,'','',0,0,'',0,0,'90 dias',NULL,''),(5,4,'2026-02-09 20:09:43','2026-02-09 20:22:58',8,'Iphone 15','celular','',NULL,NULL,380.00,0.00,'dinheiro','[{\"method\":\"dinheiro\",\"amount\":200,\"installments\":1,\"change\":0},{\"method\":\"cartao_credito\",\"amount\":180,\"installments\":2,\"change\":0}]',1,'delivered','','','','','sim','','',0,0,0,0,0,0,0,0,0,0,'',NULL,'2026-02-09 23:09:43','2026-02-09 23:22:58',NULL,0.00,0.00,'','',0,0,'',0,0,'90 dias',NULL,''),(6,4,'2026-02-14 09:49:54',NULL,8,'Iphone 15','celular','',NULL,NULL,0.00,0.00,NULL,'[]',1,'open','','','','','sim','','',0,0,0,0,0,0,0,0,0,0,'',NULL,'2026-02-14 12:49:54','2026-02-14 12:49:54',NULL,0.00,0.00,'','',0,0,'',0,0,'90 dias',NULL,''),(7,5,'2026-02-17 13:50:22',NULL,9,'Iphone 15','celular','',NULL,NULL,90.00,0.00,NULL,'[]',1,'open','','','','','sim','','',0,0,0,0,0,0,0,0,0,0,'',NULL,'2026-02-17 16:50:22','2026-02-17 16:50:54',NULL,0.00,0.00,'','',0,0,'',0,0,'90 dias',NULL,''),(8,1,'2026-02-17 13:55:05',NULL,9,'Iphone 15','celular','',NULL,NULL,80.00,0.00,NULL,'[]',1,'open','','','','','sim','','',0,0,0,0,0,0,0,0,0,0,'',NULL,'2026-02-17 16:55:05','2026-02-17 16:55:05',NULL,0.00,0.00,'','',0,0,'',0,0,'90 dias',NULL,''),(9,9,'2026-02-17 14:58:11',NULL,11,'Iphone 15','celular','',NULL,NULL,40.00,0.00,NULL,'[]',1,'open','','','','','sim','','',0,0,0,0,0,0,0,0,0,0,'',NULL,'2026-02-17 17:58:11','2026-02-17 17:58:11',NULL,0.00,0.00,'','',0,0,'',0,0,'90 dias',NULL,'');
/*!40000 ALTER TABLE `service_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `technicians`
--

DROP TABLE IF EXISTS `technicians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `technicians` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `technicians`
--

LOCK TABLES `technicians` WRITE;
/*!40000 ALTER TABLE `technicians` DISABLE KEYS */;
/*!40000 ALTER TABLE `technicians` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','operator','seller','technician') DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Data de deleção (soft delete)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (8,'Administrador','admin@flreparos.com','$2y$10$PkZAF3XLBEpMjUd.ukDVDOnMOZ4nfWrhmnuggg5laolofTdgLBW2u','admin',NULL,'active',1,'2026-02-07 15:07:41','2026-02-07 15:07:41',NULL),(9,'testepermissao','testepermissao@gmail.com','$2y$10$dGFMn0l4IvrDbtd1xd05AeK75a2m11RLkEDQn8z3eJXCZAvOAdjDy','seller',NULL,'active',1,'2026-02-09 11:27:10','2026-02-09 11:27:10',NULL),(10,'gerente','gerente@gmail.com','$2y$10$M5SKeTSo1haICvs/lROL8e.aaZMjVW5DLqofrK/Q0ruXmkf9MHgne','manager',NULL,'active',1,'2026-02-09 11:29:54','2026-02-09 11:29:54',NULL),(11,'Vendedor1teste','vendedor1@gmail.com','$2y$10$Sp4s4jC5/Z78cFsvLG5mVeKnipoRKVo8KFpeVQJ92gR/ZQbVzwj4q','seller','{\"pdv\":[\"view\",\"create\",\"edit\",\"delete\"],\"service_orders\":[\"view\",\"create\"],\"products\":[\"view\",\"create\"],\"customers\":[\"view\",\"create\",\"edit\"]}','active',1,'2026-02-17 17:22:49','2026-02-17 17:26:05',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'fl_reparos'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-18 21:03:58
