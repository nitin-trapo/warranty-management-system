<?php
// Simple script to add customer_phone column to claims table

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
    $stmt = $conn->query("SHOW COLUMNS FROM `claims` LIKE 'customer_phone'");
    $columnExists = ($stmt->rowCount() > 0);
    
    if ($columnExists) {
        echo "The 'customer_phone' column already exists in the claims table.";
    } else {
        // Add the column
        $sql = "ALTER TABLE `claims` ADD COLUMN `customer_phone` varchar(20) DEFAULT NULL AFTER `customer_email`";
        $conn->exec($sql);
        echo "Successfully added 'customer_phone' column to the claims table.";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
