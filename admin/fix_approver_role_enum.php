<?php
// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

try {
    // Step 1: Alter the table to change the ENUM values for approver_role
    $alterQuery = "ALTER TABLE users MODIFY COLUMN approver_role ENUM('','Production coordinator','Product Admin','Finance') NOT NULL DEFAULT ''";
    $conn->exec($alterQuery);
    echo "<p>Database Structure Update: Successfully modified the approver_role ENUM to include 'Product Admin' instead of 'Stan'.</p>";
    
    // Step 2: Update any users that had 'Stan' as approver_role to now have 'Product Admin'
    // Note: This won't find any because the ENUM change would have reset those values
    $stmt = $conn->prepare("UPDATE users SET approver_role = 'Product Admin' WHERE username = 'stan'");
    $result = $stmt->execute();
    $rowCount = $stmt->rowCount();
    
    echo "<p>Database Value Update: Updated {$rowCount} users to have 'Product Admin' as approver role.</p>";
    
    // Check the updated structure
    $stmt = $conn->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'approver_role') {
            echo "<p>New approver_role definition: " . $column['Type'] . "</p>";
        }
    }
    
    // Check user with ID 5
    $stmt = $conn->query("SELECT id, username, first_name, last_name, approver_role FROM users WHERE id = 5");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>User ID 5 After Update</h3>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Approver Role</th></tr>";
    
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . $user['username'] . "</td>";
    echo "<td>" . $user['first_name'] . ' ' . $user['last_name'] . "</td>";
    echo "<td>" . ($user['approver_role'] ? $user['approver_role'] : 'None') . "</td>";
    echo "</tr>";
    
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p>Database Error: " . $e->getMessage() . "</p>";
}

echo "<p>Task completed. <a href='users.php'>Go to Users Management</a></p>";
?>
