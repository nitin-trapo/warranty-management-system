<?php
/**
 * Add SKU Column to Claims Table
 * 
 * This script adds the SKU column to the claims table for better tracking and reporting
 */

// Database connection parameters
$host = 'localhost';
$dbname = 'warranty_management_system';
$username = 'root';
$password = '';

try {
    // Connect to database
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n";
    
    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM `claims` LIKE 'sku'");
    $columnExists = ($stmt->rowCount() > 0);
    
    if ($columnExists) {
        echo "The 'sku' column already exists in the claims table.";
    } else {
        // Add the column
        $sql = "ALTER TABLE `claims` ADD COLUMN `sku` varchar(50) DEFAULT NULL AFTER `category_id`";
        $conn->exec($sql);
        echo "Successfully added 'sku' column to the claims table.";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
