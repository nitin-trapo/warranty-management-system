<?php
/**
 * Fix Claims Table created_by Foreign Key Constraint
 * 
 * This script checks and fixes the created_by foreign key constraint in the claims table
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
    
    // Check if created_by column exists in claims table
    $stmt = $conn->query("SHOW COLUMNS FROM `claims` LIKE 'created_by'");
    $createdByExists = ($stmt->rowCount() > 0);
    
    if (!$createdByExists) {
        echo "The 'created_by' column doesn't exist in the claims table. Adding it...\n";
        
        // Add created_by column
        $conn->exec("ALTER TABLE `claims` ADD COLUMN `created_by` int(11) DEFAULT NULL AFTER `status`");
        echo "Added 'created_by' column to claims table.\n";
    } else {
        echo "The 'created_by' column exists in the claims table.\n";
    }
    
    // Check if foreign key constraint exists
    $stmt = $conn->query("
        SELECT * FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = '$dbname'
        AND TABLE_NAME = 'claims'
        AND COLUMN_NAME = 'created_by'
        AND REFERENCED_TABLE_NAME = 'users'
    ");
    $constraintExists = ($stmt->rowCount() > 0);
    
    if ($constraintExists) {
        // Get constraint name
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $constraintName = $row['CONSTRAINT_NAME'];
        
        echo "Foreign key constraint '$constraintName' exists. Dropping it...\n";
        
        // Drop the constraint
        $conn->exec("ALTER TABLE `claims` DROP FOREIGN KEY `$constraintName`");
        echo "Dropped foreign key constraint.\n";
    }
    
    // Check if users table exists and has records
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = ($stmt->rowCount() > 0);
    
    if (!$usersTableExists) {
        echo "The 'users' table doesn't exist. Cannot add foreign key constraint.\n";
        exit;
    }
    
    // Check if users table has records
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    
    if ($userCount == 0) {
        echo "The 'users' table has no records. Cannot add foreign key constraint.\n";
        exit;
    }
    
    // Get the first user ID to use as default
    $stmt = $conn->query("SELECT id FROM users LIMIT 1");
    $defaultUserId = $stmt->fetchColumn();
    
    // Update any NULL created_by values to the default user ID
    $conn->exec("UPDATE `claims` SET `created_by` = $defaultUserId WHERE `created_by` IS NULL");
    echo "Updated NULL created_by values to user ID $defaultUserId.\n";
    
    // Add the foreign key constraint
    $conn->exec("ALTER TABLE `claims` ADD CONSTRAINT `claims_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)");
    echo "Added foreign key constraint 'claims_ibfk_2' to created_by column.\n";
    
    echo "\nClaims table created_by foreign key constraint fixed successfully.";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
