<?php
/**
 * Add Missing Fields to Claims Table
 * 
 * This script adds product_type and delivery_date columns to the claims table
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
    
    // Check if product_type column exists
    $stmt = $conn->query("SHOW COLUMNS FROM `claims` LIKE 'product_type'");
    $productTypeExists = ($stmt->rowCount() > 0);
    
    if (!$productTypeExists) {
        // Add product_type column
        $sql = "ALTER TABLE `claims` ADD COLUMN `product_type` varchar(50) DEFAULT NULL AFTER `sku`";
        $conn->exec($sql);
        echo "Successfully added 'product_type' column to the claims table.\n";
    } else {
        echo "The 'product_type' column already exists in the claims table.\n";
    }
    
    // Check if delivery_date column exists
    $stmt = $conn->query("SHOW COLUMNS FROM `claims` LIKE 'delivery_date'");
    $deliveryDateExists = ($stmt->rowCount() > 0);
    
    if (!$deliveryDateExists) {
        // Add delivery_date column
        $sql = "ALTER TABLE `claims` ADD COLUMN `delivery_date` date DEFAULT NULL AFTER `product_type`";
        $conn->exec($sql);
        echo "Successfully added 'delivery_date' column to the claims table.\n";
    } else {
        echo "The 'delivery_date' column already exists in the claims table.\n";
    }
    
    echo "\nClaims table structure update completed successfully.";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
