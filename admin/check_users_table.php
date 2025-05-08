<?php
// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

try {
    // Check the structure of the users table
    $stmt = $conn->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Users Table Structure</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Check the user with ID 5 in detail
    $stmt = $conn->query("SELECT * FROM users WHERE id = 5");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>User ID 5 Details</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    
    foreach ($user as $field => $value) {
        echo "<tr>";
        echo "<td>" . $field . "</td>";
        echo "<td>" . ($value !== null ? $value : 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Try a direct SQL update and check if it works
    $stmt = $conn->exec("UPDATE users SET approver_role = 'Product Admin' WHERE id = 5");
    echo "<p>Direct SQL update executed. Rows affected: " . $stmt . "</p>";
    
    // Check the user again after update
    $stmt = $conn->query("SELECT approver_role FROM users WHERE id = 5");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>After update, user ID 5 approver_role: " . ($user['approver_role'] !== null ? $user['approver_role'] : 'NULL') . "</p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
