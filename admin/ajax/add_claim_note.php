<?php
/**
 * AJAX Add Claim Note
 * 
 * This file handles AJAX requests to add notes to claims
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
$newSkus = isset($_POST['new_skus']) ? trim($_POST['new_skus']) : '';

// Validate note
if (empty($note)) {
    echo json_encode([
        'success' => false,
        'message' => 'Note cannot be empty.'
    ]);
    exit;
}

try {
    // Establish database connection
    $conn = getDbConnection();
    
    // Get current user ID (assuming user is logged in)
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1; // Default to 1 if not set
    
    // Add note to claim_notes table
    $query = "INSERT INTO claim_notes (claim_id, note, new_skus, created_by, created_at) 
              VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->execute([$claimId, $note, $newSkus, $userId]);
    
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
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Note added successfully.',
        'note' => $noteData
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Error adding claim note: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while adding the note. Please try again.'
    ]);
}
