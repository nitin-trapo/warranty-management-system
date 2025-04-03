<?php
/**
 * Import Warranty Rules
 * 
 * This script imports the warranty rules table structure and sample data
 * into the warranty management system database.
 */

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// SQL statements for warranty_rules table
$sql = "
-- Table structure for warranty_rules
CREATE TABLE IF NOT EXISTS `warranty_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_type` varchar(100) NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in months',
  `coverage` text NOT NULL COMMENT 'What is covered under warranty',
  `exclusions` text NOT NULL COMMENT 'What is not covered under warranty',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `warranty_rules_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample warranty rules
INSERT INTO `warranty_rules` (`product_type`, `duration`, `coverage`, `exclusions`, `created_by`) VALUES
('Smartphones', 12, 'Manufacturing defects, Hardware failures, Battery issues (if battery capacity falls below 80%)', 'Physical damage, Water damage, Unauthorized repairs, Software issues', 1),
('Laptops', 24, 'Hardware failures, Display issues, Keyboard and touchpad malfunctions, Battery (first 12 months only)', 'Physical damage, Liquid damage, Normal wear and tear, Software issues, Consumable parts', 1),
('Home Appliances', 36, 'Motor failures, Electronic component failures, Manufacturing defects', 'Cosmetic damage, Misuse, Commercial use, Consumable parts, Installation issues', 1);
";

try {
    // Get database connection
    $conn = getDbConnection();
    
    // Execute SQL statements
    $result = $conn->exec($sql);
    
    echo "Warranty rules table created and sample data imported successfully.\n";
    
} catch (PDOException $e) {
    echo "Error importing warranty rules: " . $e->getMessage() . "\n";
}
