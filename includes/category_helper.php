<?php
/**
 * Category Helper Functions
 * 
 * This file contains helper functions for managing categories
 * in the Warranty Management System.
 */

/**
 * Get category by ID
 * 
 * @param int $categoryId Category ID
 * @return array|null Category data or null if not found
 */
function getCategoryById($categoryId) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            SELECT id, name, description, sla_days, approver, created_at
            FROM claim_categories 
            WHERE id = :category_id
        ");
        
        $stmt->bindParam(':category_id', $categoryId);
        $stmt->execute();
        
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        return $category ?: null;
    } catch (PDOException $e) {
        error_log("Error getting category by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get approver for a category
 * 
 * @param int $categoryId Category ID
 * @return string|null Approver role or null if not found
 */
function getCategoryApprover($categoryId) {
    $category = getCategoryById($categoryId);
    
    // Add debugging
    if ($category) {
        error_log("Category found: " . json_encode($category));
    } else {
        error_log("No category found for ID: $categoryId");
    }
    
    return $category ? $category['approver'] : null;
}
?>
