<?php
/**
 * Test Assign Claim
 * 
 * This file tests the claim assignment functionality.
 */

// Include required files
require_once '../../includes/auth_helper.php';
require_once '../../config/database.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get database connection
    $conn = getDbConnection();
    
    // Check if claims table exists and has the assigned_to field
    $stmt = $conn->query("SHOW COLUMNS FROM claims LIKE 'assigned_to'");
    $hasAssignedToField = ($stmt->rowCount() > 0);
    
    // Check if users table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    $hasUsersTable = ($stmt->rowCount() > 0);
    
    // Get a list of CS agents
    $csAgents = [];
    if ($hasUsersTable) {
        $stmt = $conn->prepare("
            SELECT id, username, first_name, last_name
            FROM users
            WHERE role = 'cs_agent' AND status = 'active'
        ");
        $stmt->execute();
        $csAgents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get a list of claims
    $claims = [];
    $stmt = $conn->query("SELECT id, order_id, claim_number, assigned_to FROM claims LIMIT 5");
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return test results
    echo json_encode([
        'success' => true,
        'message' => 'Test completed successfully',
        'data' => [
            'database_connected' => true,
            'has_assigned_to_field' => $hasAssignedToField,
            'has_users_table' => $hasUsersTable,
            'cs_agents_count' => count($csAgents),
            'claims_count' => count($claims),
            'sample_claims' => $claims,
            'sample_agents' => $csAgents
        ]
    ]);
    
} catch (PDOException $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
