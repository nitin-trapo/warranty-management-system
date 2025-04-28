<?php
/**
 * Add is_approver column to users table
 */
require_once 'config/database.php';

try {
    $conn = getDbConnection();
    $sql = "ALTER TABLE users ADD COLUMN is_approver TINYINT(1) NOT NULL DEFAULT 0 AFTER role";
    $conn->exec($sql);
    echo "Approver column added successfully to users table.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
