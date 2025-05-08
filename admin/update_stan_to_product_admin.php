<?php
// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

// File paths
$categoriesFile = __DIR__ . '/categories.php';

// Step 1: Update the database
try {
    // Update all instances of 'Stan' to 'Product Admin' in the approver field
    $stmt = $conn->prepare("UPDATE claim_categories SET approver = 'Product Admin' WHERE approver = 'Stan'");
    $result = $stmt->execute();
    
    if ($result) {
        $rowCount = $stmt->rowCount();
        echo "<p>Database Update: Success! Updated {$rowCount} categories from 'Stan' to 'Product Admin'.</p>";
    } else {
        echo "<p>Database Update Error: Failed to update approver values.</p>";
    }
} catch (PDOException $e) {
    echo "<p>Database Error: " . $e->getMessage() . "</p>";
}

// Step 2: Update the categories.php file
try {
    // Read the file content
    $fileContent = file_get_contents($categoriesFile);
    
    if ($fileContent === false) {
        echo "<p>File Error: Could not read the categories.php file.</p>";
    } else {
        // Replace 'Stan' with 'Product Admin' in the option values
        $updatedContent = str_replace(
            '<option value="Stan">Stan</option>', 
            '<option value="Product Admin">Product Admin</option>', 
            $fileContent
        );
        
        // Write the updated content back to the file
        if (file_put_contents($categoriesFile, $updatedContent) !== false) {
            echo "<p>File Update: Successfully replaced 'Stan' with 'Product Admin' in categories.php.</p>";
        } else {
            echo "<p>File Error: Failed to write updated content to categories.php.</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>File Error: " . $e->getMessage() . "</p>";
}

echo "<p>Task completed. <a href='categories.php'>Go to Categories Management</a></p>";
?>
