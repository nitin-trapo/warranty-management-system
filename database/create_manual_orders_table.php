<?php
/**
 * Create Manual Orders Table
 * 
 * This script creates the manual_orders table in the database.
 */

// Include database connection
require_once __DIR__ . '/../config/database.php';

// Establish database connection
$conn = getDbConnection();

try {
    // Create manual_orders table
    $sql = "CREATE TABLE IF NOT EXISTS manual_orders (
        id INT(11) NOT NULL AUTO_INCREMENT,
        claim_id INT(11) NOT NULL,
        document_no VARCHAR(50) NOT NULL,
        order_data LONGTEXT NOT NULL,
        api_response LONGTEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'created',
        created_by INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY (document_no),
        KEY (claim_id),
        KEY (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    // Execute query
    $conn->exec($sql);
    
    echo "Manual orders table created successfully.\n";
    
} catch (PDOException $e) {
    echo "Error creating manual orders table: " . $e->getMessage() . "\n";
}
?>
