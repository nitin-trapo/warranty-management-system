<?php
/**
 * Add Claim Note
 * 
 * This file handles adding notes to warranty claims
 */

// Start session
session_start();

// Include database connection
require_once '../includes/db_connect.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['claim_id']) || !isset($_POST['note'])) {
    // Redirect to claims page
    header('Location: ' . BASE_URL . '/admin/claims.php');
    exit;
}

$claimId = (int)$_POST['claim_id'];
$note = trim($_POST['note']);

// Validate note
if (empty($note)) {
    $_SESSION['error_message'] = 'Note cannot be empty.';
    header('Location: ' . BASE_URL . '/admin/view_claim.php?id=' . $claimId);
    exit;
}

try {
    // Get current user ID (assuming user is logged in)
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1; // Default to 1 if not set
    
    // Add note to claim_notes table
    $query = "INSERT INTO claim_notes (claim_id, note, status_changed, created_by) 
              VALUES (?, ?, 'no', ?)";
    $stmt = $conn->prepare($query);
    $stmt->execute([$claimId, $note, $userId]);
    
    // Set success message
    $_SESSION['success_message'] = 'Note added successfully.';
    
} catch (PDOException $e) {
    // Log error
    error_log("Error adding claim note: " . $e->getMessage());
    
    // Set error message
    $_SESSION['error_message'] = 'An error occurred while adding the note. Please try again.';
}

// Redirect back to view claim page
header('Location: ' . BASE_URL . '/admin/view_claim.php?id=' . $claimId);
exit;
