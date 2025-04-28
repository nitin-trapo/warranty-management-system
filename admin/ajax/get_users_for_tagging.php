<?php
/**
 * AJAX Get Users for Tagging
 * 
 * This file returns a list of users that can be tagged in claim notes
 */

// Include database connection
require_once '../../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Establish database connection
    $conn = getDbConnection();
    
    // Get current user ID
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    
    // Get active users (exclude the current user)
    $query = "SELECT id, username, email, role 
              FROM users 
              WHERE status = 'active' AND id != ? 
              ORDER BY username";
    $stmt = $conn->prepare($query);
    $stmt->execute([$currentUserId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Error getting users for tagging: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while retrieving users.',
        'error' => $e->getMessage()
    ]);
}
