-- Digital Fertilizer Management System Backup
-- Generated: 2026-05-02 18:46:00
-- Database: fertilizer_system

SET FOREIGN_KEY_CHECKS=0;

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
  KEY `distributed_by` (`distributed_by`),
  CONSTRAINT `distributions_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `farmers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `distributions_ibfk_2` FOREIGN KEY (`fertilizer_type_id`) REFERENCES `fertilizer_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `distributions_ibfk_3` FOREIGN KEY (`distributed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  KEY `created_by` (`created_by`),
  CONSTRAINT `farmers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `farmers` VALUES
('6', '1600881', 'Yoseph Abebe Ersedo', 'SNNP', 'Hadiya', 'Hossana', '20.00', 'DAP', 'Meher (Autumn)', '0917332199', '1777740114_id.jpg', '1777740114_farm.jpg', 'pending', '1', '2026-05-02 09:41:54', NULL);

DROP TABLE IF EXISTS `fertilizer_types`;
CREATE TABLE `fertilizer_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'kg',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `fertilizer_types` VALUES
('1', 'Urea', 'Nitrogen-based fertilizer (46% Nitrogen)', 'kg'),
('2', 'DAP', 'Diammonium Phosphate (18% Nitrogen, 46% Phosphorus)', 'kg'),
('3', 'NPK', 'Compound fertilizer (15-15-15)', 'kg'),
('4', 'Potassium', 'Potassium-based fertilizer for crop quality', 'kg'),
('5', 'Compost', 'Organic fertilizer from decomposed materials', 'kg');

DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `logs` VALUES
('1', '1', 'Distributed fertilizer', 'Type ID: 2, Quantity: 1.37 kg to farmer ID: 1', '::1', '2026-05-02 09:39:38'),
('2', '1', 'Added stock', 'Type ID: 2, Added: 333 kg', '::1', '2026-05-02 09:39:56'),
('3', '1', 'Registered farmer: 1600881', 'Yoseph Abebe Ersedo', '::1', '2026-05-02 09:41:54');

DROP TABLE IF EXISTS `stock`;
CREATE TABLE `stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fertilizer_type_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `fertilizer_type_id` (`fertilizer_type_id`),
  CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`fertilizer_type_id`) REFERENCES `fertilizer_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `stock` VALUES
('1', '1', '9950.00', '2026-05-02 08:39:25'),
('2', '2', '8291.63', '2026-05-02 09:39:56'),
('3', '3', '5000.00', '2026-05-02 08:39:25'),
('4', '4', '3000.00', '2026-05-02 08:39:25'),
('5', '5', '2000.00', '2026-05-02 08:39:25');

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` VALUES
('1', 'System Administrator', 'admin@fertilizer.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-05-02 08:39:25'),
('2', 'District Officer', 'officer@fertilizer.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', '2026-05-02 08:39:25');

SET FOREIGN_KEY_CHECKS=1;
