<?php
/**
 * Update Claim Status
 * 
 * This file handles updating the status of a warranty claim.
 */

// Include database connection
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Establish database connection
$conn = getDbConnection();

// Debug: Log request data
error_log("Update Claim Status Request: " . json_encode($_POST));
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("X-Requested-With: " . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : 'Not Set'));

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['claim_id']) || !isset($_POST['status'])) {
    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: claim_id and status.',
            'debug' => [
                'post' => $_POST,
                'method' => $_SERVER['REQUEST_METHOD']
            ]
        ]);
        exit;
    } else {
        // Redirect to claims page
        header('Location: ' . BASE_URL . '/admin/claims.php');
        exit;
    }
}

$claimId = (int)$_POST['claim_id'];
$status = trim($_POST['status']);
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

// Validate status
$validStatuses = ['new', 'in_progress', 'on_hold', 'approved', 'rejected'];
if (!in_array($status, $validStatuses)) {
    $_SESSION['error_message'] = 'Invalid status value.';
    header('Location: ' . BASE_URL . '/admin/claims.php');
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
    
    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Set the response format based on the request type
    if ($isAjax) {
        header('Content-Type: application/json');
        
        // Return JSON response
        echo json_encode([
            'success' => true,
            'message' => 'Claim status updated successfully.',
            'status' => $status,
            'note' => isset($note) ? $note : null
        ]);
    } else {
        // Set success message
        $_SESSION['success_message'] = 'Claim status updated successfully.';
        
        // Redirect back to claims page for regular form submissions
        header('Location: ' . BASE_URL . '/admin/claims.php');
    }
    exit;
} catch (PDOException $e) {
    // Rollback transaction
    $conn->rollBack();
    
    // Log error
    error_log("Error updating claim status: " . $e->getMessage());
    
    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Set the response format based on the request type
    if ($isAjax) {
        header('Content-Type: application/json');
        
        // Return JSON response
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while updating the claim status. Please try again.',
            'error' => $e->getMessage()
        ]);
    } else {
        // Set error message
        $_SESSION['error_message'] = 'An error occurred while updating the claim status. Please try again.';
        
        // Redirect back to claims page for regular form submissions
        header('Location: ' . BASE_URL . '/admin/claims.php');
    }
    exit;
}
?>
