<?php
/**
 * Update approver column in users table to support specific roles
 */
require_once 'config/database.php';

try {
    $conn = getDbConnection();
    
    // First check if the is_approver column exists
    $checkStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'is_approver'");
    if ($checkStmt->rowCount() > 0) {
        // Drop the is_approver column
        $conn->exec("ALTER TABLE users DROP COLUMN is_approver");
        echo "Dropped is_approver column.<br>";
    }
    
    // Check if approver_role column exists
    $checkStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'approver_role'");
    if ($checkStmt->rowCount() == 0) {
        // Add the approver_role column
        $conn->exec("ALTER TABLE users ADD COLUMN approver_role ENUM('', 'Production coordinator', 'Stan', 'Finance') NOT NULL DEFAULT '' AFTER role");
        echo "Added approver_role column with specific roles.<br>";
    } else {
        echo "approver_role column already exists.<br>";
    }
    
    echo "Database update completed successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
