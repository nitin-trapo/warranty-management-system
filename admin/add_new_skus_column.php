<?php
/**
 * Add New SKUs Column
 * 
 * This script adds the new_skus column to the claim_notes table.
 */

// Include database connection
require_once '../config/database.php';

try {
    // Establish database connection
    $conn = getDbConnection();
    
    // Check if the column already exists
    $stmt = $conn->query("SHOW COLUMNS FROM claim_notes LIKE 'new_skus'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        echo "The 'new_skus' column already exists in the claim_notes table.";
    } else {
        // Add the new_skus column
        $sql = "ALTER TABLE claim_notes ADD COLUMN new_skus VARCHAR(255) DEFAULT NULL AFTER note";
        $conn->exec($sql);
        echo "Successfully added 'new_skus' column to the claim_notes table.";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
