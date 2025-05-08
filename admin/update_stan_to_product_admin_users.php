<?php
// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

// File paths
$usersFile = __DIR__ . '/users.php';

// Step 1: Update the database
try {
    // Update all users with approver_role = 'Stan' to 'Product Admin'
    $stmt = $conn->prepare("UPDATE users SET approver_role = 'Product Admin' WHERE approver_role = 'Stan'");
    $result = $stmt->execute();
    
    if ($result) {
        $rowCount = $stmt->rowCount();
        echo "<p>Database Update: Success! Updated {$rowCount} users from 'Stan' to 'Product Admin' as approver role.</p>";
        
        // Show updated users
        if ($rowCount > 0) {
            $stmt = $conn->query("SELECT id, username, first_name, last_name, approver_role FROM users WHERE approver_role = 'Product Admin'");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Updated Users</h3>";
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
    } else {
        echo "<p>Database Update Error: Failed to update approver role values.</p>";
    }
} catch (PDOException $e) {
    echo "<p>Database Error: " . $e->getMessage() . "</p>";
}

// Step 2: Update the users.php file
try {
    // Read the file content
    $fileContent = file_get_contents($usersFile);
    
    if ($fileContent === false) {
        echo "<p>File Error: Could not read the users.php file.</p>";
    } else {
        // Replace 'Stan' with 'Product Admin' in the option values
        $updatedContent = str_replace(
            '<option value="Stan">Stan</option>', 
            '<option value="Product Admin">Product Admin</option>', 
            $fileContent
        );
        
        // Write the updated content back to the file
        if (file_put_contents($usersFile, $updatedContent) !== false) {
            echo "<p>File Update: Successfully replaced 'Stan' with 'Product Admin' in users.php.</p>";
        } else {
            echo "<p>File Error: Failed to write updated content to users.php.</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>File Error: " . $e->getMessage() . "</p>";
}

echo "<p>Task completed. <a href='users.php'>Go to Users Management</a></p>";
?>
