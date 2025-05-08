<?php
// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

try {
    // Update the user with ID 5 (stan) to have approver_role = 'Product Admin'
    $stmt = $conn->prepare("UPDATE users SET approver_role = 'Product Admin' WHERE id = 5");
    $result = $stmt->execute();
    
    if ($result) {
        $rowCount = $stmt->rowCount();
        echo "<p>Database Update: Success! Updated user ID 5 (Stan) to have 'Product Admin' as approver role.</p>";
    } else {
        echo "<p>Database Update Error: Failed to update approver role for user ID 5.</p>";
    }
    
    // Verify the changes
    $stmt = $conn->prepare("SELECT id, username, first_name, last_name, approver_role FROM users WHERE id = 5");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Updated User</h3>";
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
