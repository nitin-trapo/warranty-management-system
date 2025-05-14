<?php
/**
 * Delete Claim
 * 
 * This file handles the deletion of a warranty claim and all associated data.
 */

// Include database connection and configuration
require_once '../config/database.php';
require_once '../config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Establish database connection
$conn = getDbConnection();

// Check if claim ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to claims page
    header('Location: ' . BASE_URL . '/admin/claims.php');
    exit;
}

$claimId = (int)$_GET['id'];

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Get all media files associated with the claim
    $mediaQuery = "SELECT * FROM claim_media WHERE claim_id = ?";
    $stmt = $conn->prepare($mediaQuery);
    $stmt->execute([$claimId]);
    $mediaFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Delete all media files from storage
    foreach ($mediaFiles as $media) {
        if (file_exists($media['file_path']) && is_file($media['file_path'])) {
            unlink($media['file_path']);
        }
    }
    
    // Delete media records from database
    $deleteMediaQuery = "DELETE FROM claim_media WHERE claim_id = ?";
    $stmt = $conn->prepare($deleteMediaQuery);
    $stmt->execute([$claimId]);
    
    // Delete claim items
    $deleteItemsQuery = "DELETE FROM claim_items WHERE claim_id = ?";
    $stmt = $conn->prepare($deleteItemsQuery);
    $stmt->execute([$claimId]);
    
    // Delete the claim
    $deleteClaimQuery = "DELETE FROM claims WHERE id = ?";
    $stmt = $conn->prepare($deleteClaimQuery);
    $stmt->execute([$claimId]);
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    $_SESSION['success_message'] = 'Claim deleted successfully.';
    
} catch (PDOException $e) {
    // Rollback transaction
    $conn->rollBack();
    
    // Log error
    error_log("Error deleting claim: " . $e->getMessage());
    
    // Set error message
    $_SESSION['error_message'] = 'An error occurred while deleting the claim. Please try again.';
}

// Redirect to claims page
header('Location: ' . BASE_URL . '/admin/claims.php');
exit;
?>
