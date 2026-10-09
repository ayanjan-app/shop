-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ph
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
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
INSERT INTO `companies` VALUES (1,'gxt','0707843558','kabul','2026-09-29 09:36:43'),(2,'gxt','0707843558','kabul','2026-09-29 09:41:12'),(4,'aftab altaf','0707843558','W3985 County Road NNwh','2026-09-30 10:56:27');
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `company_payments`
--

DROP TABLE IF EXISTS `company_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `company_payments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company_payments`
--

LOCK TABLES `company_payments` WRITE;
/*!40000 ALTER TABLE `company_payments` DISABLE KEYS */;
INSERT INTO `company_payments` VALUES (1,1,10000.00,'2026-09-29',NULL),(2,1,10000.00,'2026-09-29',NULL),(3,1,10000.00,'2026-09-29',NULL),(5,4,50000.00,'2026-09-30',NULL);
/*!40000 ALTER TABLE `company_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `company_purchases`
--

DROP TABLE IF EXISTS `company_purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `company_purchases_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company_purchases`
--

LOCK TABLES `company_purchases` WRITE;
/*!40000 ALTER TABLE `company_purchases` DISABLE KEYS */;
INSERT INTO `company_purchases` VALUES (1,1,'brofine,',100.00,500.00,100.00,'2026-09-15','fsda'),(2,1,'brofine,',100.00,500.00,100.00,'2026-09-15','fsda'),(3,1,'brofine',2000.00,1000.00,10000.00,'2026-09-29',NULL),(4,1,'brofine',2000.00,2000.00,200000.00,'2026-09-29',NULL),(6,4,'brofine,biotin',1000.00,NULL,64000.00,'2026-09-30',NULL),(7,4,'brofine',100.00,NULL,20000.00,'2026-09-30',NULL),(8,4,NULL,NULL,NULL,2000.00,'2026-09-30',NULL);
/*!40000 ALTER TABLE `company_purchases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_unpaid`
--

DROP TABLE IF EXISTS `customer_unpaid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_unpaid` (
  `unpaid_id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `unpaid_amount` decimal(10,2) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note` varchar(255) DEFAULT NULL,
  `paid_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`unpaid_id`),
  KEY `sale_id` (`sale_id`),
  CONSTRAINT `customer_unpaid_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_unpaid`
--

LOCK TABLES `customer_unpaid` WRITE;
/*!40000 ALTER TABLE `customer_unpaid` DISABLE KEYS */;
INSERT INTO `customer_unpaid` VALUES (21,49,'Empty',20.00,'2026-02-10 11:04:30','',NULL),(22,50,'Empty',450.00,'2026-02-10 11:21:32','',NULL),(23,53,'zabi',10.00,'2026-09-30 11:09:21','Quick sale',NULL),(26,74,'Empty',20.00,'2026-09-29 07:03:10','Quick sale',NULL),(27,75,'Empty',30.00,'2026-09-29 07:03:22','Quick sale',NULL),(28,76,'Empty',152.00,'2026-09-30 07:14:27','Quick sale',NULL);
/*!40000 ALTER TABLE `customer_unpaid` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,200.00,'food','ali','2026-09-29'),(2,300.00,'karaya','ali','2026-09-28'),(3,40.00,'dodi','rafi','2026-09-30'),(4,100.00,'dodi','rafi','2026-09-30');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicines`
--

DROP TABLE IF EXISTS `medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `type` varchar(100) NOT NULL,
  `gram` varchar(50) NOT NULL,
  `manufacture_date` date NOT NULL,
  `expire_date` date NOT NULL,
  `price_in` decimal(10,2) NOT NULL,
  `price_out` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicines`
--

LOCK TABLES `medicines` WRITE;
/*!40000 ALTER TABLE `medicines` DISABLE KEYS */;
INSERT INTO `medicines` VALUES (22,'panadol','gxt','Tab','20mg','2026-02-07','2026-02-28',11.00,22.00,5.00),(23,'ali','gxt','Tab','3mg','2026-02-03','2026-02-13',200.00,300.00,0.00),(24,'fefol vite','gxt','Tab','100mg','2023-02-08','2026-03-10',150.00,200.00,0.00),(25,'brofine','TK','Tab','200mg','2026-02-04','2026-04-30',10.00,20.00,3.00),(26,'jampaid','RK','Tab','500mg','2024-01-10','2027-06-10',150.00,180.00,0.50),(29,'zetro','rt','Tab','250mg','2026-03-29','2026-10-14',100.00,110.00,98.00),(31,'bioten','dsoi','Tab','3mg','2026-08-10','2027-07-28',10.00,20.00,192.00),(32,'offtamol','gxt','Syp','100mg','2026-09-15','2027-10-28',25.00,30.00,143.00),(34,'panadol','gxt','creem','10mg','2026-09-29','2026-10-22',30.00,40.00,99.00);
/*!40000 ALTER TABLE `medicines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('Cash','Card','Other') DEFAULT 'Cash',
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `sale_id` (`sale_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (55,47,1,250.00,'2026-02-10 10:19:28','Cash',''),(56,48,1,10.00,'2026-02-10 11:02:58','Cash',''),(57,51,1,180.00,'2026-03-30 13:28:14','Cash','Quick sale'),(58,52,1,180.00,'2026-03-31 01:33:13','Cash','Quick sale'),(59,54,1,300.00,'2026-03-31 05:20:03','Cash','Quick sale'),(60,55,1,160.00,'2026-04-01 04:53:10','Cash','Quick sale'),(61,56,20,180.00,'2026-04-01 05:11:18','Cash','Quick sale'),(62,57,20,20.00,'2026-04-01 12:57:55','Cash','Quick sale'),(63,58,1,110.00,'2026-04-02 02:12:23','Cash','Quick sale'),(64,59,1,40.00,'2026-04-13 05:21:02','Cash','Quick sale'),(65,60,20,40.00,'2026-06-30 09:14:24','Cash','Quick sale'),(66,61,1,110.00,'2026-08-22 02:39:55','Cash','Quick sale'),(67,62,1,110.00,'2026-08-23 05:27:20','Cash','Quick sale'),(68,63,1,110.00,'2026-08-25 02:01:04','Cash','Quick sale'),(69,64,1,110.00,'2026-08-26 05:06:05','Cash','Quick sale'),(70,65,1,110.00,'2026-08-26 14:00:28','Cash','Quick sale'),(71,66,1,110.00,'2026-08-27 02:24:10','Cash','Quick sale'),(72,67,1,110.00,'2026-08-27 05:45:35','Cash','Quick sale'),(73,68,1,110.00,'2026-08-29 05:02:56','Cash','Quick sale'),(74,69,1,110.00,'2026-08-30 13:19:46','Cash','Quick sale'),(75,70,1,20.00,'2026-09-28 10:08:34','Cash',''),(76,71,1,50.00,'2026-09-28 12:20:50','Cash','Quick sale'),(77,72,1,70.00,'2026-09-28 12:26:40','Cash','Quick sale'),(78,73,1,500.00,'2026-09-28 12:28:28','Cash',''),(79,73,1,1000.00,'2026-09-28 12:28:57','Cash',''),(80,76,1,200.00,'2026-09-30 07:14:27','Cash','Quick sale'),(81,53,1,10.00,'2026-09-30 11:09:21','Cash',''),(82,77,1,40.00,'2026-10-01 03:49:25','Cash','Quick sale'),(83,78,1,100.00,'2026-10-01 10:59:58','Cash','Quick sale'),(84,78,1,60.00,'2026-10-01 11:00:05','Cash',''),(85,79,1,70.00,'2026-10-01 11:00:42','Cash','Quick sale'),(86,80,1,40.00,'2026-10-03 07:51:33','Cash',''),(87,81,1,40.00,'2026-10-03 07:53:27','Cash','Quick sale');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_items` (
  `sale_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_out` decimal(10,2) NOT NULL,
  `bonus_or_discount` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`sale_item_id`),
  KEY `sale_id` (`sale_id`),
  KEY `medicine_id` (`medicine_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_items`
--

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
INSERT INTO `sale_items` VALUES (68,47,24,1,200.00,50.00,150.00),(69,48,25,1,20.00,10.00,10.00),(70,49,25,1,20.00,0.00,20.00),(71,50,23,2,300.00,0.00,450.00),(72,51,26,1,180.00,0.00,180.00),(73,52,26,1,180.00,0.00,180.00),(74,53,25,1,20.00,0.00,20.00),(75,54,23,1,300.00,0.00,300.00),(76,55,26,1,180.00,20.00,160.00),(77,56,26,1,180.00,0.00,180.00),(78,57,22,1,22.00,2.00,20.00),(79,58,29,1,110.00,0.00,110.00),(80,59,25,2,20.00,0.00,40.00),(81,60,22,2,22.00,2.00,40.00),(82,61,29,1,110.00,0.00,110.00),(83,62,29,1,110.00,0.00,110.00),(84,63,29,1,110.00,0.00,110.00),(85,64,29,1,110.00,0.00,110.00),(86,65,29,1,110.00,0.00,110.00),(87,66,29,1,110.00,0.00,110.00),(88,67,29,1,110.00,0.00,110.00),(89,68,29,1,110.00,0.00,110.00),(92,70,31,1,20.00,0.00,20.00),(93,71,32,2,30.00,5.00,50.00),(94,72,32,1,30.00,0.00,30.00),(95,72,25,1,20.00,0.00,20.00),(96,72,31,1,20.00,0.00,20.00),(97,73,32,50,30.00,0.00,1500.00),(98,74,31,1,20.00,0.00,20.00),(99,75,32,1,30.00,0.00,30.00),(100,76,31,1,20.00,0.00,20.00),(101,76,29,1,110.00,0.00,110.00),(102,76,22,1,22.00,0.00,22.00),(103,76,25,1,20.00,0.00,20.00),(104,76,32,1,30.00,0.00,30.00),(105,76,31,1,20.00,0.00,20.00),(106,76,29,1,110.00,0.00,110.00),(107,76,25,1,20.00,0.00,20.00),(108,77,34,1,40.00,0.00,40.00),(109,78,31,1,20.00,0.00,20.00),(110,78,32,1,30.00,0.00,30.00),(111,78,29,1,110.00,0.00,110.00),(112,79,31,1,20.00,0.00,20.00),(113,79,31,1,20.00,0.00,20.00),(114,79,32,1,30.00,0.00,30.00),(115,80,25,1,40.00,0.00,40.00),(116,81,25,1,40.00,0.00,40.00);
/*!40000 ALTER TABLE `sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `doctor_fee` decimal(10,2) DEFAULT 0.00,
  `operation_fee` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sale_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (47,1,'Empty',0.00,100.00,250.00,'2026-02-10 10:19:28'),(48,1,'Empty',0.00,0.00,10.00,'2026-02-10 10:27:38'),(49,1,'Empty',0.00,0.00,20.00,'2026-02-10 11:04:30'),(50,1,'Empty',0.00,0.00,450.00,'2026-02-10 11:21:32'),(51,1,'Empty',0.00,0.00,180.00,'2026-03-30 13:28:14'),(52,1,'Empty',0.00,0.00,180.00,'2026-03-31 01:33:13'),(53,1,'zabi',0.00,0.00,20.00,'2026-03-31 02:11:55'),(54,1,'Empty',0.00,0.00,300.00,'2026-03-31 05:20:03'),(55,1,'Empty',0.00,0.00,160.00,'2026-04-01 04:53:10'),(56,20,'Empty',0.00,0.00,180.00,'2026-04-01 05:11:18'),(57,20,'Empty',0.00,0.00,20.00,'2026-04-01 12:57:55'),(58,1,'Empty',0.00,0.00,110.00,'2026-04-02 02:12:23'),(59,1,'Empty',0.00,0.00,40.00,'2026-04-13 05:21:02'),(60,20,'Empty',0.00,0.00,40.00,'2026-06-30 09:14:24'),(61,1,'Empty',0.00,0.00,110.00,'2026-08-22 02:39:55'),(62,1,'Empty',0.00,0.00,110.00,'2026-08-23 05:27:20'),(63,1,'Empty',0.00,0.00,110.00,'2026-08-25 02:01:04'),(64,1,'Empty',0.00,0.00,110.00,'2026-08-26 05:06:05'),(65,1,'Empty',0.00,0.00,110.00,'2026-08-26 14:00:28'),(66,1,'Empty',0.00,0.00,110.00,'2026-08-27 02:24:10'),(67,1,'Empty',0.00,0.00,110.00,'2026-08-27 05:45:35'),(68,1,'Empty',0.00,0.00,110.00,'2026-08-29 05:02:56'),(69,1,'Empty',0.00,0.00,0.00,'2026-08-30 13:19:00'),(70,1,'rafi',0.00,0.00,20.00,'2026-09-28 10:07:54'),(71,1,'Empty',0.00,0.00,50.00,'2026-09-28 12:20:50'),(72,1,'Empty',0.00,0.00,70.00,'2026-09-28 12:26:40'),(73,1,'zabi',0.00,0.00,1500.00,'2026-09-28 12:27:48'),(74,1,'Empty',0.00,0.00,20.00,'2026-09-29 07:03:10'),(75,1,'Empty',0.00,0.00,30.00,'2026-09-29 07:03:22'),(76,1,'Empty',0.00,0.00,352.00,'2026-09-30 07:14:27'),(77,1,'Empty',0.00,0.00,40.00,'2026-10-01 03:49:25'),(78,1,'Empty',0.00,0.00,160.00,'2026-10-01 10:59:58'),(79,1,'Empty',0.00,0.00,70.00,'2026-10-01 11:00:42'),(80,1,'Empty',0.00,0.00,40.00,'2026-10-03 07:45:21'),(81,1,'Empty',0.00,0.00,40.00,'2026-10-03 07:53:27');
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'ali','$2y$10$IfXIUfsNbrUaQ4MTbpWKUOQWrZ5EUtPOuTFPKtEo6899S1nH3GbcG','2026-02-09 16:02:25'),(17,'rafi','$2y$10$arDxVxh7vF3f6DbOgy8z2OHCiBtG5qypDMXkMfIdYZ3jO/IJ1j4m2','2026-02-09 16:05:12'),(18,'sharif','$2y$10$njEXt845elDNNonNccwaxePkLOxAiRNxCqybl5y7pTnhyvlH6KExq','2026-02-09 18:54:43'),(20,'admin','$2y$10$yU3n0djpSxy.QNvb3GmZF.H2ve3NNtn4srPQyrbtlOctTWsOKLCKG','2026-02-20 19:23:15'),(22,'ahm','$2y$10$JhK5tByk4rXrePpTFoEoz.WMhUHtITvrOe.g7a9kXRY2se37GGwFG','2026-10-04 18:47:52');
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

-- Dump completed on 2026-10-09 21:32:22
