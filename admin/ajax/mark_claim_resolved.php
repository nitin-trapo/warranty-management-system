<?php
/**
 * AJAX Mark Claim as Resolved
 * 
 * This file handles AJAX requests to mark claims as resolved
 */

// Include database connection
require_once '../../config/database.php';

// Include auth helper
require_once '../../includes/auth_helper.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is admin
if (!isAdmin()) {
    echo json_encode([
        'success' => false,
        'message' => 'Only administrators can mark claims as resolved.'
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['claim_id']) || !isset($_POST['note'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters.'
    ]);
    exit;
}

// Get parameters
$claimId = (int)$_POST['claim_id'];
$note = trim($_POST['note']);
$currentStatus = isset($_POST['current_status']) ? trim($_POST['current_status']) : '';

// Validate note
if (empty($note)) {
    echo json_encode([
        'success' => false,
        'message' => 'Resolution note cannot be empty.'
    ]);
    exit;
}

try {
    // Establish database connection
    $conn = getDbConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Get current user ID
    $userId = $_SESSION['user_id'];
    
    // Update claim status to resolved
    $updateQuery = "UPDATE claims SET status = 'resolved', updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->execute([$claimId]);
    
    // Add note with status change information
    $noteText = $note . "\n\nClaim marked as resolved by administrator. Previous status: " . 
                ucfirst(str_replace('_', ' ', $currentStatus));
    
    $insertNoteQuery = "INSERT INTO claim_notes (claim_id, note, created_by, created_at) 
                        VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($insertNoteQuery);
    $stmt->execute([$claimId, $noteText, $userId]);
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Claim has been marked as resolved successfully.'
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    // Log error
    error_log("Error marking claim as resolved: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while marking the claim as resolved. Please try again.'
    ]);
}
