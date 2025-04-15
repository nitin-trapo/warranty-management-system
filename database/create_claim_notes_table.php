<?php
/**
 * Create Claim Notes Table
 * 
 * This script creates the claim_notes table in the database.
 */

// Include database connection
require_once __DIR__ . '/../config/database.php';

try {
    // Get database connection
    $conn = getDbConnection();
    
    // Check if claim_notes table exists
    $tableExists = false;
    $stmt = $conn->query("SHOW TABLES LIKE 'claim_notes'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
    }
    
    // Create claim_notes table if it doesn't exist
    if (!$tableExists) {
        $createTableSQL = "
            CREATE TABLE `claim_notes` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `claim_id` int(11) NOT NULL,
                `created_by` int(11) NOT NULL,
                `note` text NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `claim_id` (`claim_id`),
                KEY `created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $conn->exec($createTableSQL);
        echo "claim_notes table created successfully.";
    } else {
        echo "claim_notes table already exists.";
    }
} catch (PDOException $e) {
    echo "Error creating claim_notes table: " . $e->getMessage();
}
?>
