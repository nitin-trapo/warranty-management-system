<?php
/**
 * Update Claims Table
 * 
 * This script adds the customer_phone column to the claims table if it doesn't exist
 */

// Include database connection
require_once '../config/database.php';

// Set content type to plain text for better readability
header('Content-Type: text/plain');

echo "Starting claims table update...\n\n";

try {
    // Establish database connection
    $conn = getDbConnection();
    
    // Check if the customer_phone column exists
    $columnExists = false;
    $stmt = $conn->query("SHOW COLUMNS FROM `claims` LIKE 'customer_phone'");
    if ($stmt->rowCount() > 0) {
        $columnExists = true;
    }
    
    if ($columnExists) {
        echo "The 'customer_phone' column already exists in the claims table.\n";
    } else {
        // Add the customer_phone column
        $alterTableSQL = "ALTER TABLE `claims` ADD COLUMN `customer_phone` varchar(20) DEFAULT NULL AFTER `customer_email`";
        $conn->exec($alterTableSQL);
        
        echo "Successfully added 'customer_phone' column to the claims table.\n";
    }
    
    echo "\nClaims table update completed successfully.";
    
} catch (PDOException $e) {
    echo "Error updating claims table: " . $e->getMessage() . "\n";
    error_log("Error updating claims table: " . $e->getMessage());
}
?>
