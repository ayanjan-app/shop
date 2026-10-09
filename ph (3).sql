-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 03, 2026 at 02:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ph`
--

-- --------------------------------------------------------

--
-- Table structure for table `customer_unpaid`
--

CREATE TABLE `customer_unpaid` (
  `unpaid_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `unpaid_amount` decimal(10,2) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note` varchar(255) DEFAULT NULL,
  `paid_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_unpaid`
--

INSERT INTO `customer_unpaid` (`unpaid_id`, `sale_id`, `customer_name`, `unpaid_amount`, `sale_date`, `note`, `paid_date`) VALUES
(21, 49, 'Empty', 20.00, '2026-02-10 11:04:30', '', NULL),
(22, 50, 'Empty', 450.00, '2026-02-10 11:21:32', '', NULL),
(23, 53, 'zabi', 20.00, '2026-03-31 02:11:55', 'Quick sale', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `type` varchar(100) NOT NULL,
  `gram` varchar(50) NOT NULL,
  `manufacture_date` date NOT NULL,
  `expire_date` date NOT NULL,
  `price_in` decimal(10,2) NOT NULL,
  `price_out` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `name`, `company_name`, `type`, `gram`, `manufacture_date`, `expire_date`, `price_in`, `price_out`, `amount`) VALUES
(22, 'panadol', 'gxt', 'Tab', '20mg', '2026-02-07', '2026-02-28', 11.00, 22.00, 8.00),
(23, 'ali', 'gxt', 'Tab', '3mg', '2026-02-03', '2026-02-13', 200.00, 300.00, 0.00),
(24, 'fefol vite', 'gxt', 'Tab', '100mg', '2023-02-08', '2026-03-10', 150.00, 200.00, 0.00),
(25, 'brofine', 'TK', 'Tab', '200mg', '2026-02-04', '2026-04-30', 10.00, 20.00, 8.00),
(26, 'jampaid', 'RK', 'Tab', '500mg', '2024-01-10', '2027-06-10', 150.00, 180.00, 0.50),
(27, 'djlksajd', 'jdlksajlk', 'Tab', '100mg', '2026-03-10', '2026-03-31', 50.00, 55.00, 10.00),
(29, 'zetro', 'rt', 'Tab', '250mg', '2026-03-29', '2026-04-30', 100.00, 110.00, 9.00);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('Cash','Card','Other') DEFAULT 'Cash',
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `sale_id`, `user_id`, `payment_amount`, `payment_date`, `payment_method`, `note`) VALUES
(55, 47, 1, 250.00, '2026-02-10 10:19:28', 'Cash', ''),
(56, 48, 1, 10.00, '2026-02-10 11:02:58', 'Cash', ''),
(57, 51, 1, 180.00, '2026-03-30 13:28:14', 'Cash', 'Quick sale'),
(58, 52, 1, 180.00, '2026-03-31 01:33:13', 'Cash', 'Quick sale'),
(59, 54, 1, 300.00, '2026-03-31 05:20:03', 'Cash', 'Quick sale'),
(60, 55, 1, 160.00, '2026-04-01 04:53:10', 'Cash', 'Quick sale'),
(61, 56, 20, 180.00, '2026-04-01 05:11:18', 'Cash', 'Quick sale'),
(62, 57, 20, 20.00, '2026-04-01 12:57:55', 'Cash', 'Quick sale'),
(63, 58, 1, 110.00, '2026-04-02 02:12:23', 'Cash', 'Quick sale'),
(64, 59, 1, 40.00, '2026-04-13 05:21:02', 'Cash', 'Quick sale');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `doctor_fee` decimal(10,2) DEFAULT 0.00,
  `operation_fee` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `user_id`, `customer_name`, `doctor_fee`, `operation_fee`, `total_amount`, `sale_date`) VALUES
(47, 1, 'Empty', 0.00, 100.00, 250.00, '2026-02-10 10:19:28'),
(48, 1, 'Empty', 0.00, 0.00, 10.00, '2026-02-10 10:27:38'),
(49, 1, 'Empty', 0.00, 0.00, 20.00, '2026-02-10 11:04:30'),
(50, 1, 'Empty', 0.00, 0.00, 450.00, '2026-02-10 11:21:32'),
(51, 1, 'Empty', 0.00, 0.00, 180.00, '2026-03-30 13:28:14'),
(52, 1, 'Empty', 0.00, 0.00, 180.00, '2026-03-31 01:33:13'),
(53, 1, 'zabi', 0.00, 0.00, 20.00, '2026-03-31 02:11:55'),
(54, 1, 'Empty', 0.00, 0.00, 300.00, '2026-03-31 05:20:03'),
(55, 1, 'Empty', 0.00, 0.00, 160.00, '2026-04-01 04:53:10'),
(56, 20, 'Empty', 0.00, 0.00, 180.00, '2026-04-01 05:11:18'),
(57, 20, 'Empty', 0.00, 0.00, 20.00, '2026-04-01 12:57:55'),
(58, 1, 'Empty', 0.00, 0.00, 110.00, '2026-04-02 02:12:23'),
(59, 1, 'Empty', 0.00, 0.00, 40.00, '2026-04-13 05:21:02');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `sale_item_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_out` decimal(10,2) NOT NULL,
  `bonus_or_discount` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`sale_item_id`, `sale_id`, `medicine_id`, `quantity`, `price_out`, `bonus_or_discount`, `subtotal`) VALUES
(68, 47, 24, 1, 200.00, 50.00, 150.00),
(69, 48, 25, 1, 20.00, 10.00, 10.00),
(70, 49, 25, 1, 20.00, 0.00, 20.00),
(71, 50, 23, 2, 300.00, 0.00, 450.00),
(72, 51, 26, 1, 180.00, 0.00, 180.00),
(73, 52, 26, 1, 180.00, 0.00, 180.00),
(74, 53, 25, 1, 20.00, 0.00, 20.00),
(75, 54, 23, 1, 300.00, 0.00, 300.00),
(76, 55, 26, 1, 180.00, 20.00, 160.00),
(77, 56, 26, 1, 180.00, 0.00, 180.00),
(78, 57, 22, 1, 22.00, 2.00, 20.00),
(79, 58, 29, 1, 110.00, 0.00, 110.00),
(80, 59, 25, 2, 20.00, 0.00, 40.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'ali', '$2y$10$IfXIUfsNbrUaQ4MTbpWKUOQWrZ5EUtPOuTFPKtEo6899S1nH3GbcG', '2026-02-09 16:02:25'),
(17, 'rafi', '$2y$10$arDxVxh7vF3f6DbOgy8z2OHCiBtG5qypDMXkMfIdYZ3jO/IJ1j4m2', '2026-02-09 16:05:12'),
(18, 'sharif', '$2y$10$njEXt845elDNNonNccwaxePkLOxAiRNxCqybl5y7pTnhyvlH6KExq', '2026-02-09 18:54:43'),
(20, 'admin', '$2y$10$yU3n0djpSxy.QNvb3GmZF.H2ve3NNtn4srPQyrbtlOctTWsOKLCKG', '2026-02-20 19:23:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customer_unpaid`
--
ALTER TABLE `customer_unpaid`
  ADD PRIMARY KEY (`unpaid_id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`sale_item_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customer_unpaid`
--
ALTER TABLE `customer_unpaid`
  MODIFY `unpaid_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `sale_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer_unpaid`
--
ALTER TABLE `customer_unpaid`
  ADD CONSTRAINT `customer_unpaid_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
