<?php
// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

try {
    // Check users with approver_role = 'Stan'
    $stmt = $conn->query("SELECT id, username, first_name, last_name, approver_role FROM users WHERE approver_role = 'Stan'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Users with 'Stan' as Approver Role</h2>";
    
    if (empty($users)) {
        echo "<p>No users found with 'Stan' as approver role.</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Approver Role</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['username'] . "</td>";
            echo "<td>" . $user['first_name'] . ' ' . $user['last_name'] . "</td>";
            echo "<td>" . $user['approver_role'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
