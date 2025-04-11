<?php
/**
 * Database Update Script
 * 
 * This script adds the new_skus column to the claim_notes table
 */

// Include database connection
require_once '../config/database.php';

try {
    // Establish database connection
    $conn = getDbConnection();
    
    // Add new_skus column to claim_notes table if it doesn't exist
    $checkColumnQuery = "SHOW COLUMNS FROM claim_notes LIKE 'new_skus'";
    $stmt = $conn->query($checkColumnQuery);
    
    if ($stmt->rowCount() == 0) {
        // Column doesn't exist, add it
        $alterTableQuery = "ALTER TABLE claim_notes ADD COLUMN new_skus VARCHAR(255) DEFAULT NULL AFTER note";
        $conn->exec($alterTableQuery);
        echo "Successfully added new_skus column to claim_notes table.";
    } else {
        echo "The new_skus column already exists in claim_notes table.";
    }
    
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
