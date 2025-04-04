<?php
/**
 * AJAX Update Claim Status
 * 
 * This file handles AJAX requests for updating claim status.
 */

// Include database connection
require_once '../../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set header to JSON
header('Content-Type: application/json');

// Check if this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Check if form was submitted with required fields
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['claim_id']) || !isset($_POST['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: claim_id and status.',
        'debug' => [
            'post' => $_POST,
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
    exit;
}

// Establish database connection
$conn = getDbConnection();

$claimId = (int)$_POST['claim_id'];
$status = trim($_POST['status']);
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

// Validate status
$validStatuses = ['new', 'in_progress', 'on_hold', 'approved', 'rejected'];
if (!in_array($status, $validStatuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value.'
    ]);
    exit;
}

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Get current status
    $query = "SELECT status FROM claims WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$claimId]);
    $currentStatus = $stmt->fetchColumn();
    
    if (!$currentStatus) {
        throw new Exception("Claim not found with ID: " . $claimId);
    }
    
    // Update claim status
    $query = "UPDATE claims SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$status, $claimId]);
    
    // Add note if provided or status changed
    if (!empty($note) || $currentStatus !== $status) {
        // If note is empty but status changed, create a default note
        if (empty($note) && $currentStatus !== $status) {
            $note = "Status changed from " . ucfirst(str_replace('_', ' ', $currentStatus)) . 
                   " to " . ucfirst(str_replace('_', ' ', $status));
        }
        
        // Get current user ID (assuming user is logged in)
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1; // Default to 1 if not set
        
        // Add note to claim_notes table
        $noteQuery = "INSERT INTO claim_notes (claim_id, note, created_by) 
                      VALUES (?, ?, ?)";
        $stmt = $conn->prepare($noteQuery);
        $stmt->execute([
            $claimId,
            $note,
            $userId
        ]);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Claim status updated successfully.',
        'status' => $status,
        'note' => $note
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollBack();
    
    // Log error
    error_log("Error updating claim status: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating the claim status. Please try again.',
        'error' => $e->getMessage()
    ]);
}
?>
