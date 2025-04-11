<?php
/**
 * Audit Logger - Helper functions to log user actions in the system
 */

// Include database connection function
require_once __DIR__ . '/../config/database.php';

/**
 * Log an action to the audit_logs table
 * 
 * @param int $userId The ID of the user performing the action
 * @param string $action The action being performed (create, update, delete, etc.)
 * @param string $entityType The type of entity being acted upon (claim, user, etc.)
 * @param int $entityId The ID of the entity being acted upon
 * @param string $details Additional details about the action
 * @return bool True if logging was successful, false otherwise
 */
function logAuditAction($userId, $action, $entityType, $entityId, $details = '') {
    $conn = getDbConnection();
    
    try {
        $query = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        return $stmt->execute([$userId, $action, $entityType, $entityId, $details, $ipAddress]);
    } catch (PDOException $e) {
        // Log error but don't disrupt the application flow
        error_log("Error logging audit action: " . $e->getMessage());
        return false;
    }
}

/**
 * Log a claim creation action
 * 
 * @param int $userId The ID of the user creating the claim
 * @param int $claimId The ID of the newly created claim
 * @param string $details Additional details about the claim
 * @return bool True if logging was successful, false otherwise
 */
function logClaimCreation($userId, $claimId, $details = '') {
    return logAuditAction($userId, 'create', 'claim', $claimId, $details);
}

/**
 * Log a claim update action
 * 
 * @param int $userId The ID of the user updating the claim
 * @param int $claimId The ID of the updated claim
 * @param string $details Details about what was updated
 * @return bool True if logging was successful, false otherwise
 */
function logClaimUpdate($userId, $claimId, $details = '') {
    return logAuditAction($userId, 'update', 'claim', $claimId, $details);
}

/**
 * Log a status change action
 * 
 * @param int $userId The ID of the user changing the status
 * @param int $claimId The ID of the claim
 * @param string $oldStatus The previous status
 * @param string $newStatus The new status
 * @return bool True if logging was successful, false otherwise
 */
function logStatusChange($userId, $claimId, $oldStatus, $newStatus) {
    $details = "Changed status from '$oldStatus' to '$newStatus'";
    return logAuditAction($userId, 'update', 'claim', $claimId, $details);
}

/**
 * Log a note creation action
 * 
 * @param int $userId The ID of the user creating the note
 * @param int $noteId The ID of the newly created note
 * @param int $claimId The ID of the claim the note belongs to
 * @return bool True if logging was successful, false otherwise
 */
function logNoteCreation($userId, $noteId, $claimId) {
    $details = "Added note to claim #$claimId";
    return logAuditAction($userId, 'create', 'claim_note', $noteId, $details);
}

/**
 * Log a media upload action
 * 
 * @param int $userId The ID of the user uploading the media
 * @param int $mediaId The ID of the uploaded media
 * @param int $claimId The ID of the claim the media belongs to
 * @param string $mediaType The type of media (image, video, etc.)
 * @return bool True if logging was successful, false otherwise
 */
function logMediaUpload($userId, $mediaId, $claimId, $mediaType) {
    $details = "Uploaded $mediaType to claim #$claimId";
    return logAuditAction($userId, 'create', 'claim_media', $mediaId, $details);
}

/**
 * Log a media deletion action
 * 
 * @param int $userId The ID of the user deleting the media
 * @param int $mediaId The ID of the deleted media
 * @param int $claimId The ID of the claim the media belonged to
 * @return bool True if logging was successful, false otherwise
 */
function logMediaDeletion($userId, $mediaId, $claimId) {
    $details = "Deleted media from claim #$claimId";
    return logAuditAction($userId, 'delete', 'claim_media', $mediaId, $details);
}

/**
 * Log a new SKU addition action
 * 
 * @param int $userId The ID of the user adding the SKU
 * @param int $claimId The ID of the claim
 * @param string $sku The SKU that was added
 * @return bool True if logging was successful, false otherwise
 */
function logNewSkuAddition($userId, $claimId, $sku) {
    $details = "Added new SKU '$sku' to claim #$claimId";
    return logAuditAction($userId, 'update', 'claim', $claimId, $details);
}

/**
 * Log a user login action
 * 
 * @param int $userId The ID of the user logging in
 * @return bool True if logging was successful, false otherwise
 */
function logUserLogin($userId) {
    return logAuditAction($userId, 'login', 'user', $userId, 'User logged in');
}

/**
 * Log a user logout action
 * 
 * @param int $userId The ID of the user logging out
 * @return bool True if logging was successful, false otherwise
 */
function logUserLogout($userId) {
    return logAuditAction($userId, 'logout', 'user', $userId, 'User logged out');
}

/**
 * Log a report generation action
 * 
 * @param int $userId The ID of the user generating the report
 * @param string $reportType The type of report generated
 * @return bool True if logging was successful, false otherwise
 */
function logReportGeneration($userId, $reportType) {
    $details = "Generated $reportType report";
    return logAuditAction($userId, 'generate', 'report', 0, $details);
}
?>
