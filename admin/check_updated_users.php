<?php
// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

try {
    // Check all users with their approver roles
    $stmt = $conn->query("SELECT id, username, first_name, last_name, approver_role FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>All Users with Approver Roles</h2>";
    
    if (empty($users)) {
        echo "<p>No users found.</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Approver Role</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['username'] . "</td>";
            echo "<td>" . $user['first_name'] . ' ' . $user['last_name'] . "</td>";
            echo "<td>" . ($user['approver_role'] ? $user['approver_role'] : 'None') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Check if any users still have 'Stan' as approver_role
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE approver_role = 'Stan'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Number of users with 'Stan' as approver_role: " . $result['count'] . "</p>";
    
    // Check if any users have 'Product Admin' as approver_role
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE approver_role = 'Product Admin'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Number of users with 'Product Admin' as approver_role: " . $result['count'] . "</p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
