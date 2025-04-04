<?php
/**
 * AJAX Update Claim Status
 * 
 * This file handles AJAX requests to update claim status and add notes
 */

// Include database connection
require_once '../../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['claim_id']) || !isset($_POST['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters.'
    ]);
    exit;
}

// Get parameters
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
    // Establish database connection
    $conn = getDbConnection();
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Get current status
    $query = "SELECT status FROM claims WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$claimId]);
    $currentStatus = $stmt->fetchColumn();
    
    // If status hasn't changed and no note provided, return error
    if ($currentStatus === $status && empty($note)) {
        echo json_encode([
            'success' => false,
            'message' => 'Status is already set to ' . ucfirst(str_replace('_', ' ', $status)) . '. No changes made.'
        ]);
        exit;
    }
    
    // Update claim status
    $query = "UPDATE claims SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$status, $claimId]);
    
    $noteData = null;
    
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
        $noteQuery = "INSERT INTO claim_notes (claim_id, note, status_changed, old_status, new_status, created_by, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($noteQuery);
        $stmt->execute([
            $claimId,
            $note,
            ($currentStatus !== $status) ? 'yes' : 'no',
            $currentStatus,
            $status,
            $userId
        ]);
        
        // Get the inserted note ID
        $noteId = $conn->lastInsertId();
        
        // Get the note data
        $noteDataQuery = "SELECT cn.*, u.username as created_by_name 
                         FROM claim_notes cn
                         LEFT JOIN users u ON cn.created_by = u.id
                         WHERE cn.id = ?";
        $stmt = $conn->prepare($noteDataQuery);
        $stmt->execute([$noteId]);
        $noteData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Claim status updated successfully.',
        'status' => $status,
        'note' => $noteData
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    // Log error
    error_log("Error updating claim status: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating the claim status. Please try again.'
    ]);
}
