<?php
/**
 * Delete Media AJAX Handler
 * 
 * This file handles AJAX requests to delete claim media files.
 */

// Include database connection
require_once '../../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Check if media ID is provided
if (!isset($_POST['media_id']) || !is_numeric($_POST['media_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Media ID is required.'
    ]);
    exit;
}

// Get media ID
$mediaId = (int)$_POST['media_id'];

try {
    // Establish database connection
    $conn = getDbConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Get media details
    $mediaQuery = "SELECT * FROM claim_media WHERE id = ?";
    $stmt = $conn->prepare($mediaQuery);
    $stmt->execute([$mediaId]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$media) {
        throw new Exception("Media not found.");
    }
    
    // Get file path
    $filePath = $media['file_path'];
    
    // Construct server path
    $serverPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $filePath;
    
    // Delete file from server if it exists
    if (file_exists($serverPath)) {
        if (!unlink($serverPath)) {
            throw new Exception("Failed to delete file from server.");
        }
    }
    
    // Delete media record from database
    $deleteQuery = "DELETE FROM claim_media WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->execute([$mediaId]);
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Media deleted successfully.'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log error
    error_log("Error deleting media: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
