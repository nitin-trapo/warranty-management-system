<?php
/**
 * Import Categories Script
 * 
 * This script imports warranty claim categories from a predefined list
 * into the claim_categories table.
 */

// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

// Categories to import (from the provided list)
$categories = [
    'Clip::Specific SKU',
    'Customer::Wrong Order::Car Mats',
    'Customer::Wrong Order::Accessories',
    'Extra Item::Accessories',
    'Extra Item::Car Mats',
    'Measurement Not Fit::Car Mats',
    'Missing Item::Accessories',
    'Missing Item::Car Mats',
    'QC Issues::Car Mats',
    'Received Product Defect::Accessories::Wipers',
    'Received Product Defect::Accessories::Others',
    'Received Product Defect::Car Mats',
    'Shipping Issue::Internal Consignment',
    'Warranty Claim::Accessories::Others',
    'Warranty Claim::Accessories::Wipers',
    'Warranty Claim::Car Mats',
    'Wrong Item::Car Mats::Colour',
    'Wrong Item::Car Mats::SKU',
    'Wrong Item::Wipers',
    'Others',
    'Shipping Issue::Courier',
    'Internal Backend Issue::Wrong SKU or Naming'
];

// Count variables
$inserted = 0;
$skipped = 0;
$errors = [];

// Insert categories
foreach ($categories as $categoryName) {
    try {
        // Check if category already exists
        $stmt = $conn->prepare("SELECT id FROM claim_categories WHERE name = ?");
        $stmt->execute([$categoryName]);
        
        if ($stmt->rowCount() > 0) {
            $skipped++;
            continue; // Skip if already exists
        }
        
        // Insert new category
        $stmt = $conn->prepare("INSERT INTO claim_categories (name) VALUES (?)");
        $result = $stmt->execute([$categoryName]);
        
        if ($result) {
            $inserted++;
        } else {
            $errors[] = "Failed to insert category: $categoryName";
        }
    } catch (PDOException $e) {
        $errors[] = "Error processing category '$categoryName': " . $e->getMessage();
    }
}

// Output results
echo "<html><head><title>Import Categories</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .warning { color: orange; }
    .error { color: red; }
    .container { max-width: 800px; margin: 0 auto; }
    a { display: inline-block; margin-top: 20px; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
</style>";
echo "</head><body><div class='container'>";
echo "<h1>Import Categories Results</h1>";

echo "<p><strong>Total categories processed:</strong> " . count($categories) . "</p>";
echo "<p class='success'><strong>Categories inserted:</strong> $inserted</p>";
echo "<p class='warning'><strong>Categories skipped (already exist):</strong> $skipped</p>";

if (!empty($errors)) {
    echo "<h2 class='error'>Errors:</h2>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li class='error'>$error</li>";
    }
    echo "</ul>";
}

echo "<a href='categories.php'>Return to Categories Management</a>";
echo "</div></body></html>";
?>
