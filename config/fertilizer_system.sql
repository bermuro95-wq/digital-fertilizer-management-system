-- phpMyAdmin SQL Dump
-- Digital Fertilizer Registration and Distribution System
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Create Database
-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `fertilizer_system`;
USE `fertilizer_system`;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','officer') NOT NULL DEFAULT 'officer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `fertilizer_types`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `fertilizer_types`;
CREATE TABLE `fertilizer_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'kg',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `farmers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `farmers`;
CREATE TABLE `farmers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `region` varchar(100) NOT NULL,
  `district` varchar(100) NOT NULL,
  `kebele` varchar(100) NOT NULL,
  `farm_size` decimal(10,2) NOT NULL,
  `crop_type` varchar(100) NOT NULL,
  `harvest_season` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `id_proof` varchar(255) DEFAULT NULL,
  `farm_photo` varchar(255) DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `farmer_id` (`farmer_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `stock`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `stock`;
CREATE TABLE `stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fertilizer_type_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `fertilizer_type_id` (`fertilizer_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `distributions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `distributions`;
CREATE TABLE `distributions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` int(11) NOT NULL,
  `fertilizer_type_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `distribution_date` date NOT NULL,
  `season` varchar(50) DEFAULT NULL,
  `distributed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `farmer_id` (`farmer_id`),
  KEY `fertilizer_type_id` (`fertilizer_type_id`),
  KEY `distributed_by` (`distributed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Add foreign key constraints
-- --------------------------------------------------------

ALTER TABLE `farmers`
  ADD CONSTRAINT `farmers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `stock`
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`fertilizer_type_id`) REFERENCES `fertilizer_types` (`id`) ON DELETE CASCADE;

ALTER TABLE `distributions`
  ADD CONSTRAINT `distributions_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `farmers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `distributions_ibfk_2` FOREIGN KEY (`fertilizer_type_id`) REFERENCES `fertilizer_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `distributions_ibfk_3` FOREIGN KEY (`distributed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- --------------------------------------------------------
-- Insert default data
-- --------------------------------------------------------

-- Insert fertilizer types
INSERT INTO `fertilizer_types` (`id`, `name`, `description`) VALUES
(1, 'Urea', 'Nitrogen-based fertilizer (46% Nitrogen)'),
(2, 'DAP', 'Diammonium Phosphate (18% Nitrogen, 46% Phosphorus)'),
(3, 'NPK', 'Compound fertilizer (15-15-15)'),
(4, 'Potassium', 'Potassium-based fertilizer for crop quality'),
(5, 'Compost', 'Organic fertilizer from decomposed materials');

-- Insert stock levels
INSERT INTO `stock` (`fertilizer_type_id`, `quantity`) VALUES
(1, 10000.00),
(2, 8000.00),
(3, 5000.00),
(4, 3000.00),
(5, 2000.00);

-- Insert admin user (Password: admin123)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'System Administrator', 'admin@fertilizer.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert officer user (Password: officer123)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(2, 'District Officer', 'officer@fertilizer.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer');

-- Insert sample farmers
INSERT INTO `farmers` (`id`, `farmer_id`, `full_name`, `region`, `district`, `kebele`, `farm_size`, `crop_type`, `harvest_season`, `phone`, `verification_status`, `created_by`) VALUES
(1, 'FARM001', 'Tadesse Mekonnen', 'Oromia', 'Bako', 'Guduru', 5.50, 'Maize', 'Meher (Autumn)', '0912345678', 'verified', 1),
(2, 'FARM002', 'Alemitu Bekele', 'Amhara', 'Debre Tabor', 'Farta', 3.20, 'Teff', 'Belg (Spring)', '0923456789', 'verified', 1),
(3, 'FARM003', 'Girma Assefa', 'SNNPR', 'Wolayta', 'Sodo', 4.80, 'Wheat', 'Meher (Autumn)', '0934567890', 'pending', 1),
(4, 'FARM004', 'Worknesh Demeke', 'Oromia', 'Shashemene', 'Kersa', 2.50, 'Barley', 'Belg (Spring)', '0945678901', 'verified', 2),
(5, 'FARM005', 'Desta Alemu', 'Amhara', 'Gondar', 'Dembia', 6.00, 'Maize', 'Meher (Autumn)', '0956789012', 'rejected', 1);

-- Insert sample distributions
INSERT INTO `distributions` (`farmer_id`, `fertilizer_type_id`, `quantity`, `distribution_date`, `season`, `distributed_by`) VALUES
(1, 1, 50.00, '2025-01-15', 'Meher (Autumn)', 1),
(2, 2, 40.00, '2025-01-10', 'Belg (Spring)', 1),
(4, 1, 30.00, '2025-01-05', 'Belg (Spring)', 2);

-- Update stock after distributions
UPDATE `stock` SET `quantity` = 9950.00 WHERE `fertilizer_type_id` = 1;
UPDATE `stock` SET `quantity` = 7960.00 WHERE `fertilizer_type_id` = 2;

-- Insert sample logs
INSERT INTO `logs` (`user_id`, `action`, `details`, `ip_address`) VALUES
(1, 'System Initialization', 'Database setup and initial data insertion completed', '127.0.0.1'),
(1, 'User Login', 'Admin user logged in for first time', '127.0.0.1');

COMMIT;

-- Display completion message
SELECT 'Database setup completed successfully!' AS Status;
SELECT COUNT(*) AS TotalUsers FROM users;
SELECT COUNT(*) AS TotalFarmers FROM farmers;
SELECT COUNT(*) AS TotalFertilizerTypes FROM fertilizer_types;