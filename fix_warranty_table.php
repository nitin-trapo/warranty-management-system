<?php
/**
 * Fix Warranty Rules Table
 * 
 * This script checks the warranty_rules table structure and adds missing columns.
 */

// Include database configuration
require_once __DIR__ . '/config/database.php';

try {
    // Get database connection
    $conn = getDbConnection();
    
    // Check if table exists
    $tableExists = false;
    $stmt = $conn->query("SHOW TABLES LIKE 'warranty_rules'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
        echo "Table warranty_rules exists.\n";
    } else {
        echo "Table warranty_rules does not exist. Creating it...\n";
    }
    
    // If table doesn't exist, create it with the correct structure
    if (!$tableExists) {
        $createTableSQL = "
            CREATE TABLE `warranty_rules` (
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
        ";
        
        $conn->exec($createTableSQL);
        echo "Table warranty_rules created successfully.\n";
    } else {
        // Table exists, check its structure
        $stmt = $conn->query("DESCRIBE warranty_rules");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Current columns: " . implode(", ", $columns) . "\n";
        
        // Check for missing columns and add them
        $requiredColumns = [
            'id', 'product_type', 'duration', 'coverage', 'exclusions', 
            'created_by', 'created_at', 'updated_at'
        ];
        
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $columns)) {
                echo "Missing column: $column. Adding it...\n";
                
                // Add the missing column based on its type
                switch ($column) {
                    case 'duration':
                        $conn->exec("ALTER TABLE warranty_rules ADD COLUMN duration int(11) NOT NULL COMMENT 'Duration in months' AFTER product_type");
                        break;
                    case 'coverage':
                        $conn->exec("ALTER TABLE warranty_rules ADD COLUMN coverage text NOT NULL COMMENT 'What is covered under warranty' AFTER duration");
                        break;
                    case 'exclusions':
                        $conn->exec("ALTER TABLE warranty_rules ADD COLUMN exclusions text NOT NULL COMMENT 'What is not covered under warranty' AFTER coverage");
                        break;
                    case 'created_by':
                        $conn->exec("ALTER TABLE warranty_rules ADD COLUMN created_by int(11) NOT NULL AFTER exclusions");
                        break;
                    case 'created_at':
                        $conn->exec("ALTER TABLE warranty_rules ADD COLUMN created_at timestamp NOT NULL DEFAULT current_timestamp() AFTER created_by");
                        break;
                    case 'updated_at':
                        $conn->exec("ALTER TABLE warranty_rules ADD COLUMN updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER created_at");
                        break;
                }
                
                echo "Column $column added successfully.\n";
            }
        }
        
        // Check if foreign key exists
        $stmt = $conn->query("SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'warranty_rules' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users'");
        if ($stmt->rowCount() == 0) {
            echo "Foreign key constraint missing. Adding it...\n";
            
            // First add index if it doesn't exist
            $conn->exec("ALTER TABLE warranty_rules ADD INDEX IF NOT EXISTS (created_by)");
            
            // Then add foreign key
            $conn->exec("ALTER TABLE warranty_rules ADD CONSTRAINT warranty_rules_ibfk_1 FOREIGN KEY (created_by) REFERENCES users(id)");
            
            echo "Foreign key constraint added successfully.\n";
        }
    }
    
    // Check if there's any data in the table
    $stmt = $conn->query("SELECT COUNT(*) FROM warranty_rules");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "No data found in warranty_rules table. Adding sample data...\n";
        
        // Insert sample data
        $sampleDataSQL = "
            INSERT INTO `warranty_rules` (`product_type`, `duration`, `coverage`, `exclusions`, `created_by`) VALUES
            ('Smartphones', 12, 'Manufacturing defects, Hardware failures, Battery issues (if battery capacity falls below 80%)', 'Physical damage, Water damage, Unauthorized repairs, Software issues', 1),
            ('Laptops', 24, 'Hardware failures, Display issues, Keyboard and touchpad malfunctions, Battery (first 12 months only)', 'Physical damage, Liquid damage, Normal wear and tear, Software issues, Consumable parts', 1),
            ('Home Appliances', 36, 'Motor failures, Electronic component failures, Manufacturing defects', 'Cosmetic damage, Misuse, Commercial use, Consumable parts, Installation issues', 1);
        ";
        
        $conn->exec($sampleDataSQL);
        echo "Sample data added successfully.\n";
    } else {
        echo "Data already exists in warranty_rules table. Count: $count\n";
    }
    
    echo "\nWarranty rules table has been fixed successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
