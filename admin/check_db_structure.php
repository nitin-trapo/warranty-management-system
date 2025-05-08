<?php
// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

// Check table structure
try {
    $stmt = $conn->query("DESCRIBE claim_categories");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>claim_categories Table Structure</h2>";
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
    
    // Check current values
    $stmt = $conn->query("SELECT id, name, approver FROM claim_categories");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Categories with Approvers</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Approver</th></tr>";
    
    foreach ($categories as $category) {
        echo "<tr>";
        echo "<td>" . $category['id'] . "</td>";
        echo "<td>" . $category['name'] . "</td>";
        echo "<td>" . $category['approver'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
