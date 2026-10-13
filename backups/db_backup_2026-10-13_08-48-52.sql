-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: shop
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
-- Table structure for table `client_payments`
--

DROP TABLE IF EXISTS `client_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `hawala_number` varchar(100) DEFAULT NULL,
  `currency` enum('USD','AFN') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `receipt_picture` varchar(255) DEFAULT NULL,
  `notebook_picture` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `sale_id` (`sale_id`),
  CONSTRAINT `client_payments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `client_payments_ibfk_2` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_payments`
--

LOCK TABLES `client_payments` WRITE;
/*!40000 ALTER TABLE `client_payments` DISABLE KEYS */;
INSERT INTO `client_payments` VALUES (1,2,2,NULL,'USD',100.00,'2026-10-10','uploads/payments/receipt_1791628321_41b6dd10.png','uploads/payments/notebook_1791628321_3ffe79a1.png','','2026-10-10 10:32:01'),(2,2,2,'1212','USD',2000.00,'2026-10-10','uploads/payments/receipt_1791628733_1f034b9a.png',NULL,'','2026-10-10 10:38:53'),(3,2,1,'','AFN',5000.00,'2026-10-10','uploads/payments/receipt_1791629846_98266ce8.png',NULL,'','2026-10-10 10:57:26'),(4,2,3,'','AFN',300.00,'2026-10-11',NULL,NULL,'','2026-10-11 09:04:16'),(5,2,2,'','USD',300.00,'2026-10-11',NULL,NULL,'','2026-10-11 09:04:53'),(8,15,4,'1716','AFN',20000.00,'2026-10-13','uploads/payments/receipt_1791873782_eaa8042d.png',NULL,'','2026-10-13 06:43:02');
/*!40000 ALTER TABLE `client_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (2,'علی جان','uploads/clients/client_1791619217_2f0a01d0.png','4343','کابل ارزان قیمت','لوگر','دا مشتری ماته خان معرفی کری','2026-10-10 07:48:47'),(14,'رفیع الله','uploads/clients/client_1791873356_56e343d5.png','0707843558','کابل','لوگر','','2026-10-13 06:35:56'),(15,'حبیب الله غزنی',NULL,'070 784 3558','خیر کوت','غزنی','','2026-10-13 06:39:25');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` decimal(10,2) NOT NULL,
  `used_for` varchar(255) NOT NULL,
  `person` varchar(100) NOT NULL,
  `expense_date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (7,5000.00,'کور','احمد','2026-10-10'),(8,1000.00,'کور','احمد','2026-10-10'),(9,20000.00,'کور','احمد','2026-10-13');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `invoice_date` date NOT NULL,
  `currency` enum('USD','AFN') NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `invoice_picture` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,2,'554','2026-10-10','AFN',20000.00,'uploads/sales/invoice_1791628221_38c0326d.png','','2026-10-10 10:30:21'),(2,2,'444','2026-10-10','USD',3000.00,'uploads/sales/invoice_1791628268_c7df7cab.png','edssfds','2026-10-10 10:31:08'),(3,2,'332','2026-10-11','AFN',2000.00,NULL,'','2026-10-11 07:28:32'),(4,15,'14653','2026-10-13','AFN',4466.00,'uploads/sales/invoice_1791873684_0b3ddb40.png','','2026-10-13 06:41:24');
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sarafe`
--

DROP TABLE IF EXISTS `sarafe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sarafe` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `date` date NOT NULL DEFAULT curdate(),
  `money_type` varchar(50) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sarafe`
--

LOCK TABLES `sarafe` WRITE;
/*!40000 ALTER TABLE `sarafe` DISABLE KEYS */;
INSERT INTO `sarafe` VALUES (1,'قلم  گتابجه',200.00,'2026-10-10','ډالر','fdsafds','2026-10-10 09:13:32'),(4,'قلم  گتابجه',200.00,'2026-10-10','ډالر','fdsaf','2026-10-10 09:30:26'),(5,'قلم  گتابجه',2000.00,'2026-10-11','افغاني','200','2026-10-11 11:50:56'),(6,'قلم  گتابجه',100.00,'2026-10-11','چیني','dsf','2026-10-11 11:51:05'),(7,'gjb',20000.00,'2026-10-13','افغاني','','2026-10-13 06:46:16'),(8,'قلم  گتابجه',400.00,'2026-10-13','ډالر','','2026-10-13 06:48:42');
/*!40000 ALTER TABLE `sarafe` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'ali','$2y$10$IfXIUfsNbrUaQ4MTbpWKUOQWrZ5EUtPOuTFPKtEo6899S1nH3GbcG','2026-02-09 16:02:25'),(18,'sharif','$2y$10$njEXt845elDNNonNccwaxePkLOxAiRNxCqybl5y7pTnhyvlH6KExq','2026-02-09 18:54:43'),(20,'admin','$2y$10$yU3n0djpSxy.QNvb3GmZF.H2ve3NNtn4srPQyrbtlOctTWsOKLCKG','2026-02-20 19:23:15'),(0,'rafi','$2y$10$EXMxTHbUCA3q5y76McCBM.qZ3YPfMnZ9P4Y/pLSB/d8LZXEna2SU6','2026-10-09 21:31:31');
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

-- Dump completed on 2026-10-13 11:18:52
