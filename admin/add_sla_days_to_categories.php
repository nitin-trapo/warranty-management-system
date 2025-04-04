<?php
/**
 * Add SLA Days Column to Claim Categories Table
 * 
 * This script adds the sla_days column to the claim_categories table
 * to specify the number of days within which claims in each category should be resolved
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
    $stmt = $conn->query("SHOW COLUMNS FROM `claim_categories` LIKE 'sla_days'");
    $columnExists = ($stmt->rowCount() > 0);
    
    if ($columnExists) {
        echo "The 'sla_days' column already exists in the claim_categories table.";
    } else {
        // Add the column
        $sql = "ALTER TABLE `claim_categories` ADD COLUMN `sla_days` int(11) DEFAULT 7 AFTER `description`";
        $conn->exec($sql);
        echo "Successfully added 'sla_days' column to the claim_categories table.";
        
        // Update existing categories with default SLA days
        $defaultSLADays = [
            'Hardware Defect' => 5,
            'Software Issue' => 3,
            'Accidental Damage' => 7,
            'Wear and Tear' => 10,
            'DOA (Dead on Arrival)' => 2
        ];
        
        foreach ($defaultSLADays as $categoryName => $slaDays) {
            $stmt = $conn->prepare("UPDATE `claim_categories` SET `sla_days` = ? WHERE `name` = ?");
            $stmt->execute([$slaDays, $categoryName]);
        }
        
        echo "\nUpdated existing categories with default SLA days values.";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
