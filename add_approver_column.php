<?php
/**
 * Add approver column to claim_categories table
 */
require_once 'config/database.php';

try {
    $conn = getDbConnection();
    $sql = "ALTER TABLE claim_categories ADD COLUMN approver VARCHAR(50) DEFAULT NULL AFTER sla_days";
    $conn->exec($sql);
    echo "Approver column added successfully to claim_categories table.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
