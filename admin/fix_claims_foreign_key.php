<?php
// Script to fix the foreign key constraint in the claims table

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
    
    // First, check if claim_categories table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'claim_categories'");
    $claimCategoriesExists = ($stmt->rowCount() > 0);
    
    if (!$claimCategoriesExists) {
        echo "Error: The claim_categories table does not exist. Please create it first.\n";
        exit;
    }
    
    // Check if categories table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'categories'");
    $categoriesExists = ($stmt->rowCount() > 0);
    
    // Drop the foreign key constraint
    echo "Dropping the foreign key constraint...\n";
    $conn->exec("ALTER TABLE `claims` DROP FOREIGN KEY `claims_ibfk_1`");
    
    // Add the correct foreign key constraint
    echo "Adding the correct foreign key constraint...\n";
    $conn->exec("ALTER TABLE `claims` ADD CONSTRAINT `claims_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `claim_categories` (`id`) ON DELETE CASCADE");
    
    echo "Foreign key constraint fixed successfully.\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
