<?php
/**
 * User Helper Functions
 * 
 * This file contains helper functions for managing users
 * in the Warranty Management System.
 */

/**
 * Get users by approver role
 * 
 * @param string $approverRole The approver role to search for
 * @return array Array of users with the specified approver role
 */
function getUsersByApproverRole($approverRole) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            SELECT id, username, email, first_name, last_name, approver_role
            FROM users 
            WHERE approver_role = :approver_role
            AND status = 'active'
        ");
        
        $stmt->bindParam(':approver_role', $approverRole);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting users by approver role: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user details by ID
 * 
 * @param int $userId User ID
 * @return array|null User data or null if not found
 */
function getUserDetailsById($userId) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            SELECT id, username, email, first_name, last_name, role, approver_role, status
            FROM users 
            WHERE id = :user_id
        ");
        
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (PDOException $e) {
        error_log("Error getting user by ID: " . $e->getMessage());
        return null;
    }
}
?>
