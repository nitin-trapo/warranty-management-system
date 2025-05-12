<?php
/**
 * Create SKU Cache Table
 * 
 * This script creates the sku_cache table in the database if it doesn't exist.
 */

// Include database connection
require_once __DIR__ . '/../config/database.php';

// Establish database connection
$conn = getDbConnection();

try {
    // Create sku_cache table if it doesn't exist
    $query = "CREATE TABLE IF NOT EXISTS sku_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sku VARCHAR(255) NOT NULL,
        sku_data TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX (sku),
        INDEX (created_at)
    )";
    
    $conn->exec($query);
    
    echo "SKU cache table created successfully!";
    
} catch (PDOException $e) {
    echo "Error creating SKU cache table: " . $e->getMessage();
}
?>
