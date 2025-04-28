<?php
/**
 * Add predefined categories with approvers and descriptions
 */
require_once 'config/database.php';

try {
    $conn = getDbConnection();
    
    // First, let's clear existing categories to avoid duplicates
    // Uncomment the line below if you want to clear existing categories
    // $conn->exec("TRUNCATE TABLE claim_categories");
    
    // Categories data with name, approver, and description
    $categories = [
        [
            'name' => 'Customer : Wrong Order-Accessories',
            'approver' => 'Production coordinator',
            'description' => 'Create this claim if customer order wrong accessories and we send the correct SKU',
            'sla_days' => 7
        ],
        [
            'name' => 'Customer : Wrong Order-Car Mats',
            'approver' => 'Production coordinator',
            'description' => 'Create this claim if customer order wrong car mat and we send the correct SKU',
            'sla_days' => 7
        ],
        [
            'name' => 'Extra Item : Accessories',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if we send extra accesories',
            'sla_days' => 7
        ],
        [
            'name' => 'Extra Item : Car Mats',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if we send extra car mat pieces/set',
            'sla_days' => 7
        ],
        [
            'name' => 'Internal : Technical Issue/Wrong SKU/Product Name',
            'approver' => 'Production coordinator',
            'description' => 'Involve any issue with product name/wrong sku/internal technical issue',
            'sla_days' => 7
        ],
        [
            'name' => 'Internal : Staff Miscellaneous',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if production/sales/cs did miscellaneous',
            'sla_days' => 7
        ],
        [
            'name' => 'Missing Item : Accessories',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if we miss send accesories',
            'sla_days' => 7
        ],
        [
            'name' => 'Missing Item : Car Mat',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if we miss send car mat',
            'sla_days' => 7
        ],
        [
            'name' => 'Missing Item : Wiper',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if we miss send wiper',
            'sla_days' => 7
        ],
        [
            'name' => 'Not Fit : Car Mat',
            'approver' => 'Production coordinator',
            'description' => 'If we send correct car mat sku and it is not fit/suitable choose this claim',
            'sla_days' => 7
        ],
        [
            'name' => 'Not Fit : Wiper',
            'approver' => 'Production coordinator',
            'description' => 'If we send correct wiper sku and it is not fit/suitable choose this claim',
            'sla_days' => 7
        ],
        [
            'name' => 'Others : Not Specified Issue',
            'approver' => 'Production coordinator',
            'description' => 'Choose this option is the issue not listed in other claim option',
            'sla_days' => 7
        ],
        [
            'name' => 'Production QC : Car Mats Defect',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if production team send out defect car mat',
            'sla_days' => 7
        ],
        [
            'name' => 'Product Defect : Accessories',
            'approver' => 'Production coordinator',
            'description' => 'Claim can be created if accessories received by customer have defect',
            'sla_days' => 7
        ],
        [
            'name' => 'Product Defect : Car Mat',
            'approver' => 'Production coordinator',
            'description' => 'Claim can be created if car mat received by customer have defect',
            'sla_days' => 7
        ],
        [
            'name' => 'Product Defect : Wiper Dashcam',
            'approver' => 'Production coordinator',
            'description' => 'Claim can be created if wiper/dashcam just received by customer have defect',
            'sla_days' => 7
        ],
        [
            'name' => 'Shipping Issue : Claim courier',
            'approver' => 'Finance',
            'description' => 'Create this if issue come from courier service and need to claim',
            'sla_days' => 7
        ],
        [
            'name' => 'Shipping Issue : Courier',
            'approver' => 'Production coordinator',
            'description' => 'Create this claim if issue come from courier service but no claim required',
            'sla_days' => 7
        ],
        [
            'name' => 'Warranty Claim : Accessories',
            'approver' => 'Production coordinator',
            'description' => 'Acessories warranty depends on each product specs.',
            'sla_days' => 7
        ],
        [
            'name' => 'Warranty Claim : Car Mat',
            'approver' => 'Production coordinator',
            'description' => 'Warranty cover lining, padding, antislip. Eyelet we can give for free change. Wear and tear not covered',
            'sla_days' => 7
        ],
        [
            'name' => 'Warranty Claim : Wiper Dashcam',
            'approver' => 'Stan',
            'description' => 'Warranty cover hydrophobic effect 2 years. Other reasons/special cases must be approve by team lead/direct manager. Dashcam does not cover wear and tear just manufacturing defect',
            'sla_days' => 7
        ],
        [
            'name' => 'Wrong Item : Accessories',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if we send wrong accessories - different than SKU',
            'sla_days' => 7
        ],
        [
            'name' => 'Wrong Item : Car Mat-Colour',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if we send wrong colour car mat - different than SKU',
            'sla_days' => 7
        ],
        [
            'name' => 'Wrong Item : Car Mat-SKU',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if we send wrong car mat - different than SKU',
            'sla_days' => 7
        ],
        [
            'name' => 'Wrong Item : Wiper',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if we send wrong wiper - different than SKU',
            'sla_days' => 7
        ],
        [
            'name' => 'Wrong Item : Clip',
            'approver' => 'Production coordinator',
            'description' => 'Create claim if we send wrong clip - different than SKU',
            'sla_days' => 7
        ],
        [
            'name' => 'Refund Order',
            'approver' => 'Finance',
            'description' => 'Refund cases must be reasonable. Usually approve by team lead/direct manager',
            'sla_days' => 7
        ]
    ];
    
    // Prepare the insert statement
    $stmt = $conn->prepare("INSERT INTO claim_categories (name, approver, description, sla_days) VALUES (?, ?, ?, ?)");
    
    // Counter for successful inserts
    $successCount = 0;
    
    // Insert each category
    foreach ($categories as $category) {
        try {
            // Check if category already exists
            $checkStmt = $conn->prepare("SELECT id FROM claim_categories WHERE name = ?");
            $checkStmt->execute([$category['name']]);
            
            if ($checkStmt->rowCount() > 0) {
                // Update existing category
                $updateStmt = $conn->prepare("UPDATE claim_categories SET approver = ?, description = ?, sla_days = ? WHERE name = ?");
                $updateStmt->execute([
                    $category['approver'],
                    $category['description'],
                    $category['sla_days'],
                    $category['name']
                ]);
                echo "Updated category: " . $category['name'] . "<br>";
            } else {
                // Insert new category
                $stmt->execute([
                    $category['name'],
                    $category['approver'],
                    $category['description'],
                    $category['sla_days']
                ]);
                $successCount++;
                echo "Added category: " . $category['name'] . "<br>";
            }
        } catch (Exception $e) {
            echo "Error with category '" . $category['name'] . "': " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br>Successfully added/updated " . count($categories) . " categories.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
