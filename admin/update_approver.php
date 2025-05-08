<?php
// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

try {
    // Update all instances of 'Stan' to 'Product Admin' in the approver field
    $stmt = $conn->prepare("UPDATE claim_categories SET approver = 'Product Admin' WHERE approver = 'Stan'");
    $result = $stmt->execute();
    
    if ($result) {
        $rowCount = $stmt->rowCount();
        echo "<p>Success! Updated {$rowCount} categories from 'Stan' to 'Product Admin'.</p>";
    } else {
        echo "<p>Error: Failed to update approver values.</p>";
    }
    
    // Verify the changes
    $stmt = $conn->query("SELECT id, name, approver FROM claim_categories WHERE approver = 'Product Admin'");
    $updatedCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($updatedCategories)) {
        echo "<h2>Updated Categories</h2>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Approver</th></tr>";
        
        foreach ($updatedCategories as $category) {
            echo "<tr>";
            echo "<td>" . $category['id'] . "</td>";
            echo "<td>" . $category['name'] . "</td>";
            echo "<td>" . $category['approver'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No categories found with 'Product Admin' as approver after update.</p>";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
