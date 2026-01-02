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
) ENGINE=InnoDB AUTO_INCREMENT=158 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_flow`
--

LOCK TABLES `cash_flow` WRITE;
/*!40000 ALTER TABLE `cash_flow` DISABLE KEYS */;
INSERT INTO `cash_flow` VALUES (1,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-07-29 19:23:08'),(2,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-07-29 19:23:15'),(3,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-07-29 19:23:23'),(4,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-07-29 20:22:35'),(5,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-07-29 20:37:25'),(6,1,'','Venda #1 - dinheiro',560.00,1,'sale','2025-07-29 20:48:43'),(7,1,'','Venda #2 - dinheiro',10.00,2,'sale','2025-07-29 20:52:48'),(8,1,'','Venda #3 - pix - Cliente: João Silva',270.00,3,'sale','2025-07-29 21:10:05'),(9,1,'','Venda #4 - cartao_debito - Cliente: João Silva',23.00,4,'sale','2025-07-29 21:23:58'),(10,1,'','Venda #5 - dinheiro - Cliente: João Silva',90.00,5,'sale','2025-07-29 21:30:53'),(11,1,'','Venda #6 - dinheiro - Cliente: João Silva',10240.00,6,'sale','2025-07-29 21:34:32'),(12,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-07-29 21:41:00'),(13,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-07-30 00:31:38'),(14,1,'','Venda #7 - dinheiro - Cliente: João Silva',45.00,7,'sale','2025-07-30 00:32:49'),(15,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-07-30 00:33:39'),(16,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-07-30 01:40:15'),(17,1,'','Venda #8 - Múltiplos: Dinheiro: R$ 4.000,00, Dinheiro: R$ 1.050,00 - Cliente: João Silva',5050.00,8,'sale','2025-07-30 01:51:45'),(18,1,'','Venda #9 - Múltiplos: Dinheiro: R$ 15,00, Cartão de Crédito: R$ 10,00',25.00,9,'sale','2025-07-30 01:52:49'),(19,1,'','Venda #10 - Múltiplos: Dinheiro: R$ 50,00, Cartão de Crédito: R$ 30,00',80.00,10,'sale','2025-07-30 02:01:51'),(20,1,'','Venda #11 - Dinheiro',80.00,11,'sale','2025-07-30 02:30:36'),(21,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-07-30 02:35:16'),(22,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-07-30 02:35:22'),(23,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-08-16 13:18:28'),(24,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-08-16 13:46:57'),(25,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-08-16 13:49:17'),(26,1,'','Venda #12 - Múltiplos: Dinheiro: R$ 20,00, Cartão de Crédito: R$ 10,00',30.00,12,'sale','2025-08-16 14:27:03'),(27,1,'','Venda #13 - Múltiplos: Dinheiro: R$ 45,00, PIX: R$ 10,00, Cartão de Débito: R$ 20,00 - Cliente: João Silva',75.00,13,'sale','2025-08-16 14:30:27'),(28,1,'','Venda #14 - Múltiplos: Dinheiro: R$ 50,00, Cartão de Crédito: R$ 30,00 - Cliente: João Silva',80.00,14,'sale','2025-08-16 14:44:04'),(29,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-08-28 18:12:12'),(30,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-08-31 22:27:30'),(31,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-10-09 22:15:01'),(32,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-10-09 22:22:03'),(33,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-10-10 21:11:26'),(34,1,'','Venda #15 - PIX',10395.50,15,'sale','2025-10-10 21:15:58'),(35,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-10-24 12:44:30'),(36,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-10-25 21:48:29'),(37,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-10-25 21:50:18'),(38,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-01 23:31:56'),(39,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-01 23:35:19'),(40,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-01 23:35:28'),(41,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-01 23:40:28'),(42,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-01 23:41:01'),(43,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-01 23:41:53'),(44,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-01 23:42:04'),(45,1,'','Venda #16 - Dinheiro - Cliente: João Silva',60.00,16,'sale','2025-11-01 23:43:35'),(46,1,'','Venda #17 - Dinheiro',5200.00,17,'sale','2025-11-01 23:44:25'),(47,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-01 23:46:46'),(48,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-01 23:50:03'),(49,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-02 00:04:12'),(50,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-02 00:04:21'),(51,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-02 00:04:49'),(52,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-02 00:05:22'),(53,1,'','Venda #18 - Dinheiro - Cliente: João Silva',60.00,18,'sale','2025-11-02 00:06:06'),(54,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-02 00:08:25'),(55,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-02 00:08:47'),(56,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-02 00:09:54'),(57,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-02 00:10:03'),(58,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-16 16:37:15'),(59,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-16 17:27:52'),(60,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-16 17:28:05'),(61,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-16 19:31:56'),(62,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-16 19:32:04'),(63,1,'','Venda #19 - Dinheiro - Cliente: João Silva',280.00,19,'sale','2025-11-16 21:16:00'),(64,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-16 21:18:14'),(65,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-16 21:18:23'),(66,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-22 10:38:57'),(67,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-22 11:19:41'),(68,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-22 11:19:50'),(69,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-28 01:06:28'),(70,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-28 01:07:27'),(71,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-28 01:08:11'),(72,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-11-28 01:11:39'),(73,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-11-28 01:11:53'),(74,1,'','Venda #20 - Dinheiro - Cliente: João Silva',160.00,20,'sale','2025-11-28 01:12:55'),(75,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-07 19:04:50'),(76,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-09 02:51:02'),(77,1,'','Venda #21 - PIX - Cliente: João Silva',160.00,21,'sale','2025-12-09 02:55:49'),(78,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-09 03:07:07'),(79,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-09 03:07:16'),(80,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-12 23:28:26'),(81,1,'expense','Teste',228.00,NULL,NULL,'2025-12-13 00:40:50'),(82,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-13 01:40:09'),(83,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-13 01:40:19'),(84,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-13 01:40:25'),(85,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-13 01:40:57'),(86,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-13 01:42:06'),(87,2,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-13 01:42:19'),(88,2,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-13 01:42:58'),(89,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-13 01:43:53'),(90,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-13 01:44:58'),(91,3,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-13 01:45:09'),(92,3,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-13 01:51:59'),(93,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-13 01:52:08'),(94,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-13 02:22:40'),(95,2,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-13 02:22:55'),(96,2,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-13 02:23:29'),(97,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-22 17:13:48'),(98,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 16:28:21'),(99,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 16:35:05'),(100,4,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 16:35:10'),(101,4,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 16:40:20'),(102,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 16:40:26'),(103,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 16:42:21'),(104,4,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 16:42:31'),(105,4,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 16:43:08'),(106,4,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 16:43:19'),(107,4,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 16:43:21'),(108,4,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 16:46:16'),(109,4,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 16:52:47'),(110,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 16:53:01'),(111,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 16:54:47'),(112,2,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 16:54:56'),(113,2,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 16:55:25'),(114,4,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 16:55:37'),(115,4,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 16:59:23'),(116,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 16:59:33'),(117,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 17:04:00'),(118,4,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 17:04:07'),(119,4,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 17:05:43'),(120,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 17:05:52'),(121,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 17:06:55'),(122,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 17:07:04'),(123,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 17:45:17'),(124,3,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 17:45:27'),(125,3,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 17:45:51'),(126,4,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 17:46:49'),(127,4,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 17:51:26'),(128,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 17:51:34'),(129,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 18:01:05'),(130,4,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 18:01:14'),(131,4,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 18:01:27'),(132,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 18:01:37'),(133,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 18:30:23'),(134,5,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 18:30:28'),(135,5,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 18:30:53'),(136,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 18:30:59'),(137,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 19:08:19'),(138,5,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 19:08:41'),(139,5,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-25 22:21:28'),(140,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-25 22:21:34'),(141,1,'','Venda #22 - Dinheiro - Cliente: Cliente 1',76.00,22,'sale','2025-12-25 22:24:36'),(142,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-26 03:38:09'),(143,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-26 03:52:58'),(144,1,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-26 03:55:52'),(145,6,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-26 03:55:57'),(146,6,'closing','Logout do sistema',0.00,NULL,NULL,'2025-12-26 03:59:10'),(147,1,'opening','Login no sistema',0.00,NULL,NULL,'2025-12-26 04:07:36'),(148,1,'opening','Login no sistema',0.00,NULL,NULL,'2026-01-01 20:40:33'),(149,1,'closing','Logout do sistema',0.00,NULL,NULL,'2026-01-01 20:58:50'),(150,1,'opening','Login no sistema',0.00,NULL,NULL,'2026-01-01 20:59:00'),(151,1,'','Venda #23 - Dinheiro - Cliente: Ana Costa',80.00,23,'sale','2026-01-01 21:23:36'),(152,1,'closing','Logout do sistema',0.00,NULL,NULL,'2026-01-02 17:20:29'),(153,6,'opening','Login no sistema',0.00,NULL,NULL,'2026-01-02 17:20:34'),(154,6,'closing','Logout do sistema',0.00,NULL,NULL,'2026-01-02 17:21:04'),(155,1,'opening','Login no sistema',0.00,NULL,NULL,'2026-01-02 17:21:09'),(156,1,'','Venda #24 - Dinheiro - Cliente: Ana Costa',42.75,24,'sale','2026-01-02 17:52:37'),(157,1,'expense','moto',40.00,NULL,NULL,'2026-01-02 17:55:19');
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
  `total_card` decimal(10,2) DEFAULT 0.00,
  `total_pix` decimal(10,2) DEFAULT 0.00,
  `observations` text DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_opening_date` (`opening_date`),
  KEY `idx_user_status` (`user_id`,`status`),
  CONSTRAINT `fk_cash_register_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_register`
--

LOCK TABLES `cash_register` WRITE;
/*!40000 ALTER TABLE `cash_register` DISABLE KEYS */;
INSERT INTO `cash_register` VALUES (1,1,'2025-12-12 21:39:44','2025-12-26 00:33:32',1000.00,772.00,0.00,228.00,0.00,0.00,0.00,'','closed','2025-12-13 00:39:44','2025-12-26 03:33:32'),(2,1,'2026-01-02 14:54:07','2026-01-02 14:55:49',1000.00,960.00,0.00,40.00,0.00,0.00,0.00,'','closed','2026-01-02 17:54:07','2026-01-02 17:55:49');
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
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(2) DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'João Silva','123.456.789-10','(85) 99999-1234','joao@email.com','Rua das Flores, 123','Fortaleza','CE','60000-000','Cliente preferencial','2025-07-29 20:47:50','2025-07-29 20:47:50'),(2,'Maria Santos','987.654.321-00','(85) 88888-5678','maria@email.com','Av. Principal, 456','Fortaleza','CE','60100-000','Reparo de iPhone','2025-07-29 20:47:50','2025-07-29 20:47:50'),(3,'Pedro Oliveira','111.222.333-44','(85) 77777-9999','pedro@email.com','Rua do Comércio, 789','Fortaleza','CE','60200-000','Cliente desde 2020','2025-07-29 20:47:50','2025-07-29 20:47:50'),(4,'Ana Costa','555.666.777-88','(85) 66666-1111','ana@email.com','Praça Central, 321','Fortaleza','CE','60300-000','Compra acessórios','2025-07-29 20:47:50','2025-07-29 20:47:50'),(5,'Carlos Ferreira','999.888.777-66','(85) 55555-2222','carlos@email.com','Rua Nova, 654','Fortaleza','CE','60400-000','Técnico em eletrônicos','2025-07-29 20:47:50','2025-07-29 20:47:50');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
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
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_expenses_supplier` (`supplier_id`),
  KEY `fk_expenses_user` (`user_id`),
  CONSTRAINT `fk_expenses_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expenses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,'Teste','fixa',100.00,'2025-12-13',1,'pix','pago','teste 1',1,'2025-12-13 00:23:15','2025-12-13 00:23:41'),(2,'Teste','variavel',222.00,'2025-12-13',1,'cartao','pago','eeeeee',1,'2025-12-13 00:24:03','2025-12-26 03:02:03'),(3,'Teste','fixa',222.00,'2025-12-26',NULL,'dinheiro','pendente','',1,'2025-12-26 03:13:45','2025-12-26 03:13:45');
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
  `name` varchar(200) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `sale_price` decimal(10,2) DEFAULT 0.00,
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'iPhone 14 Pro 128GB','IP14P128','1234567890123',1,4500.00,5200.00,1,22,2,'iPhone 14 Pro 128GB - Cor Azul',1,'2025-07-29 19:40:26','2025-11-01 23:44:25'),(2,'Samsung Galaxy S23','SGS23','2345678901234',1,3200.00,3800.00,1,8,3,'Samsung Galaxy S23 256GB',0,'2025-07-29 19:40:26','2025-07-29 20:40:29'),(3,'Película de Vidro Temperado','PVT001','3456789012345',2,8.50,25.00,1,46,10,'Película universal para smartphones',1,'2025-07-29 19:40:26','2025-07-30 01:52:49'),(4,'Capa TPU Transparente','CTPU001','4567890123456',2,5.00,15.00,1,28,5,'Capa TPU flexível transparente',1,'2025-07-29 19:40:26','2025-07-29 21:30:53'),(5,'Display iPhone 12','DIP12','5678901234567',3,180.00,280.00,1,29,2,'Display original iPhone 12',1,'2025-07-29 19:40:26','2025-11-16 21:16:00'),(6,'Bateria Samsung A32','BSA32','6789012345678',3,45.00,80.00,1,17,3,'Bateria original Samsung Galaxy A32s',1,'2025-07-29 19:40:26','2026-01-01 21:23:36'),(7,'Kit Chaves para Celular','KCC001','7890123456789',4,25.00,45.00,1,4,2,'Kit com 8 chaves para abertura de smartphones',1,'2025-07-29 19:40:26','2026-01-02 17:52:37'),(8,'Cabo Iphone','CBIPH7','',2,14.00,35.00,1,0,2,'',1,'2025-07-30 01:59:03','2025-07-30 01:59:03'),(9,'Suporte Veicular','SPV745','',2,15.00,49.99,1,4,2,'Suporte para Veiculo',1,'2025-07-30 01:59:56','2025-08-16 13:27:51'),(10,'teste','000000','00000',4,100.00,120.00,1,1,1,'',0,'2025-11-16 19:56:14','2025-11-16 19:56:29');
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
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_items`
--

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
INSERT INTO `sale_items` VALUES (1,1,5,2,280.00,560.00),(2,2,4,1,15.00,15.00),(3,3,5,1,280.00,280.00),(4,4,3,1,25.00,25.00),(5,5,4,1,15.00,15.00),(6,5,7,2,45.00,90.00),(7,6,1,2,5200.00,10400.00),(8,6,7,2,45.00,90.00),(9,6,3,2,25.00,50.00),(10,7,7,1,45.00,45.00),(11,8,1,1,5200.00,5200.00),(12,9,3,1,25.00,25.00),(13,10,6,1,80.00,80.00),(14,11,6,1,80.00,80.00),(15,12,6,1,80.00,80.00),(16,13,6,1,80.00,80.00),(17,14,6,1,80.00,80.00),(18,15,1,2,5200.00,10400.00),(19,16,6,1,80.00,80.00),(20,17,1,1,5200.00,5200.00),(21,18,6,1,80.00,80.00),(22,19,5,1,280.00,280.00),(23,20,6,2,80.00,160.00),(24,21,6,2,80.00,160.00),(25,22,6,1,80.00,80.00),(26,23,6,1,80.00,80.00),(27,24,7,1,45.00,45.00);
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
  `payment_method` enum('cash','card','pix','installment') DEFAULT 'cash',
  `installments` int(11) DEFAULT 1,
  `status` enum('completed','cancelled','pending') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,NULL,1,560.00,0.00,560.00,'',1,'completed',NULL,'2025-07-29 20:48:43'),(2,1,1,15.00,5.00,10.00,'',1,'completed',NULL,'2025-07-29 20:52:48'),(3,NULL,1,280.00,10.00,270.00,'pix',1,'completed',NULL,'2025-07-29 21:10:05'),(4,NULL,1,25.00,2.00,23.00,'',1,'completed',NULL,'2025-07-29 21:23:58'),(5,NULL,1,105.00,15.00,90.00,'',1,'completed',NULL,'2025-07-29 21:30:53'),(6,NULL,1,10540.00,300.00,10240.00,'',1,'completed',NULL,'2025-07-29 21:34:32'),(7,NULL,1,45.00,0.00,45.00,'',1,'completed',NULL,'2025-07-30 00:32:49'),(8,NULL,1,5200.00,150.00,5050.00,'',1,'completed',NULL,'2025-07-30 01:51:45'),(9,NULL,1,25.00,0.00,25.00,'',1,'completed',NULL,'2025-07-30 01:52:49'),(10,NULL,1,80.00,0.00,80.00,'',1,'completed',NULL,'2025-07-30 02:01:51'),(11,NULL,1,80.00,0.00,80.00,'',1,'completed',NULL,'2025-07-30 02:30:36'),(12,NULL,1,80.00,50.00,30.00,'',1,'completed',NULL,'2025-08-16 14:27:03'),(13,NULL,1,80.00,5.00,75.00,'',1,'completed',NULL,'2025-08-16 14:30:27'),(14,NULL,1,80.00,0.00,80.00,'',1,'completed',NULL,'2025-08-16 14:44:04'),(15,NULL,1,10400.00,4.50,10395.50,'pix',1,'completed',NULL,'2025-10-10 21:15:58'),(16,NULL,1,80.00,20.00,60.00,'',1,'completed',NULL,'2025-11-01 23:43:35'),(17,NULL,1,5200.00,0.00,5200.00,'',1,'completed',NULL,'2025-11-01 23:44:25'),(18,NULL,1,80.00,20.00,60.00,'',1,'completed',NULL,'2025-11-02 00:06:06'),(19,NULL,1,280.00,0.00,280.00,'',1,'completed',NULL,'2025-11-16 21:16:00'),(20,NULL,1,160.00,0.00,160.00,'',1,'completed',NULL,'2025-11-28 01:12:55'),(21,NULL,1,160.00,0.00,160.00,'pix',1,'completed',NULL,'2025-12-09 02:55:49'),(22,NULL,1,80.00,4.00,76.00,'',1,'completed',NULL,'2025-12-25 22:24:36'),(23,NULL,1,80.00,0.00,80.00,'',1,'completed',NULL,'2026-01-01 21:23:36'),(24,NULL,1,45.00,2.25,42.75,'',1,'completed',NULL,'2026-01-02 17:52:37');
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
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
  `problem` text NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `solution` text DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('dinheiro','pix','cartao_credito','cartao_debito') DEFAULT NULL COMMENT 'Forma de pagamento',
  `installments` int(11) DEFAULT 1 COMMENT 'N·mero de parcelas (cartÒo crÚdito)',
  `status` enum('open','in_progress','completed','delivered','cancelled') DEFAULT 'open',
  `technical_report` text DEFAULT NULL,
  `reported_problem` text DEFAULT NULL,
  `customer_observations` text DEFAULT NULL,
  `internal_observations` text DEFAULT NULL,
  `device_powers_on` enum('sim','nao') DEFAULT 'sim',
  `checklist_case` tinyint(1) DEFAULT 0 COMMENT 'Capa',
  `checklist_screen_protector` tinyint(1) DEFAULT 0 COMMENT 'Película',
  `checklist_camera` tinyint(1) DEFAULT 0 COMMENT 'Câmera',
  `checklist_housing` tinyint(1) DEFAULT 0 COMMENT 'Carcaça',
  `checklist_lens` tinyint(1) DEFAULT 0 COMMENT 'Lente',
  `checklist_face_id` tinyint(1) DEFAULT 0 COMMENT 'Face ID',
  `checklist_sim_card` tinyint(1) DEFAULT 0 COMMENT 'Chip',
  `checklist_battery` tinyint(1) DEFAULT 0 COMMENT 'Bateria',
  `checklist_charger` tinyint(1) DEFAULT 0 COMMENT 'Carregador',
  `checklist_headphones` tinyint(1) DEFAULT 0 COMMENT 'Fone',
  `technician_name` varchar(255) DEFAULT NULL,
  `attendant_name` varchar(255) DEFAULT NULL,
  `entry_date` timestamp NULL DEFAULT current_timestamp(),
  `delivery_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `service_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `service_orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_orders`
--

LOCK TABLES `service_orders` WRITE;
/*!40000 ALTER TABLE `service_orders` DISABLE KEYS */;
INSERT INTO `service_orders` VALUES (3,4,'2025-12-25 13:19:44',NULL,1,'Iphone 12','Display quebrado','tela trincada','Troca do display',450.00,NULL,1,'open',NULL,NULL,NULL,NULL,'sim',0,0,0,0,0,0,0,0,0,0,NULL,NULL,'2025-11-16 20:30:16',NULL,NULL),(4,1,'2025-12-25 13:19:44',NULL,1,'Iphone 12','Bateria','Bateria com defeito','Troca da bateria',300.00,NULL,1,'in_progress',NULL,NULL,NULL,NULL,'sim',0,0,0,0,0,0,0,0,0,0,NULL,NULL,'2025-11-16 21:10:02',NULL,NULL),(5,3,'2025-12-25 13:19:44',NULL,1,'Iphone 12','Bateria','Defeito na bateria','Troca da bateria',300.00,'cartao_credito',1,'in_progress','','tezfmbkpnfgbiok','digvhdfuvhuo','','sim',1,1,1,1,1,1,1,0,1,0,'Jordan','Lua','2025-11-16 21:11:48',NULL,NULL),(10,2,'2025-12-26 00:13:18',NULL,1,'Iphone 13','',NULL,NULL,3333.00,'dinheiro',1,'in_progress','','','','','sim',0,0,0,0,0,0,0,0,0,0,'','','2025-12-26 03:13:18',NULL,NULL);
/*!40000 ALTER TABLE `service_orders` ENABLE KEYS */;
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
  `status` enum('active','inactive') DEFAULT 'active',
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Administrador','admin@flreparos.com','$2y$10$0mkPHYUVTpPxhmmGgx6oP.jyXRNDRrG1QaiBysTEc0YVuKVgIvmpW','admin','active',1,'2025-07-29 19:10:34','2025-07-29 19:22:34'),(2,'Jordan','jordan@gmail.com','$2y$10$GRPTb1oCzZrqryrdwz.goOXjvNQmM.bWThAoYyzeA4g1WRjtyxQqu','manager','active',1,'2025-12-13 01:41:33','2025-12-13 01:41:33'),(3,'Wesley','wesley@gmail.com','$2y$10$9ZCQOYGDOLUYqtGo70piVOiTX3veow/fkaVcl0xTayI06tti7WkYm','seller','active',1,'2025-12-13 01:44:40','2025-12-26 03:54:22'),(4,'operador1','operador@gmail.com','$2y$10$gkxACKfZju2dFGmm8oOD8.MKh7RKOg07lpmYyQ/dT7ZYYPzbv9y8C','seller','active',1,'2025-12-25 16:34:49','2025-12-26 03:54:14'),(5,'teste','stefany@gmail.com','$2y$10$vh1.SXWB4ExEFr194eyaQOIeLS4s/3ymVY/0VowhP4UTIWP1diM0q','seller','active',1,'2025-12-25 18:30:11','2025-12-25 18:30:11'),(6,'Atila Costa','atila@gmail.com','$2y$10$VAlAM8XmHKf9gerH0FUYmO5gGDT1iDXp5oCsFSboxMhzedTQcu0V.','seller','active',1,'2025-12-26 03:54:52','2025-12-26 03:54:52'),(7,'Dyego','dyego@gmail.com','$2y$10$4cNEna4OgsXg8xUjw4dTh.dM/Jh0s.9tTJV1tVVatoFgq.TSPVP8i','manager','active',1,'2025-12-26 03:55:35','2025-12-26 03:55:35');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-02 15:11:03
