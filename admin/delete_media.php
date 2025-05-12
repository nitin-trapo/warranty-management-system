<?php
/**
 * Delete Media
 * 
 * This file handles the deletion of media files associated with a claim.
 */

// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

// Check if media ID and claim ID are provided
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['claim_id']) || empty($_GET['claim_id'])) {
    // Redirect to claims page
    header('Location: ' . BASE_URL . '/admin/claims.php');
    exit;
}

$mediaId = (int)$_GET['id'];
$claimId = (int)$_GET['claim_id'];

try {
    // Get media details
    $query = "SELECT * FROM claim_media WHERE id = ? AND claim_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$mediaId, $claimId]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$media) {
        // Media not found, redirect to edit claim page
        header('Location: ' . BASE_URL . '/admin/edit_claim.php?id=' . $claimId);
        exit;
    }
    
    // Delete file from storage
    if (file_exists($media['file_path']) && is_file($media['file_path'])) {
        unlink($media['file_path']);
    }
    
    // Delete media record from database
    $deleteQuery = "DELETE FROM claim_media WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->execute([$mediaId]);
    
    // Set success message
    $_SESSION['success_message'] = 'Media deleted successfully.';
    
} catch (PDOException $e) {
    // Log error
    error_log("Error deleting media: " . $e->getMessage());
    
    // Set error message
    $_SESSION['error_message'] = 'An error occurred while deleting the media. Please try again.';
}

// Redirect back to edit claim page
header('Location: ' . BASE_URL . '/admin/edit_claim.php?id=' . $claimId);
exit;
?>
