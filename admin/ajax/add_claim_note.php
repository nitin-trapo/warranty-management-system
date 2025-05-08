<?php
/**
 * AJAX Add Claim Note
 * 
 * This file handles AJAX requests to add notes to claims
 */

// Include database connection
require_once '../../config/database.php';

// Include email helper
require_once '../../includes/email_helper.php';

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
    
    // Get current user info for notification
    $userQuery = "SELECT username, email FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute([$userId]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    $taggerName = $currentUser['username'] ?? 'System';
    
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
    
    // Format note text for display with highlighted tagged users
    $formattedNote = $noteData['note'];
    // Log the original note
    error_log("Original note: " . $formattedNote);
    
    // Escape HTML to prevent XSS
    $formattedNote = htmlspecialchars($formattedNote);
    error_log("After htmlspecialchars: " . $formattedNote);
    
    // Replace @username with highlighted version using the EXACT format requested
    $formattedNote = preg_replace('/@([a-zA-Z0-9._]+)/', '<span class="badge bg-info text-dark">@$1</span>', $formattedNote);
    error_log("After highlighting tags: " . $formattedNote);
    
    // Convert newlines to <br> tags
    $formattedNote = nl2br($formattedNote);
    error_log("After nl2br: " . $formattedNote);
    
    // Add the formatted note to the note data
    $noteData['formatted_note'] = $formattedNote;
    error_log("Final formatted note added to response: " . $formattedNote);
    
    // Also add a plain text version for debugging
    $noteData['note_text'] = $noteData['note'];
    
    // Add a debug flag to indicate this is the new version
    $noteData['debug_version'] = 'v2';
    
    // Process tagged users
    $taggedUsers = [];
    
    // Extract @username mentions from the note
    preg_match_all('/@([\w.]+)/', $note, $matches);
    
    if (!empty($matches[1])) {
        // Get unique usernames
        $mentionedUsernames = array_unique($matches[1]);
        
        // Get claim data for notification
        $claimQuery = "SELECT c.*, u.username as created_by_name 
                      FROM claims c 
                      LEFT JOIN users u ON c.created_by = u.id 
                      WHERE c.id = ?";
        $claimStmt = $conn->prepare($claimQuery);
        $claimStmt->execute([$claimId]);
        $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);
        
        // Look up each mentioned user
        $placeholders = implode(',', array_fill(0, count($mentionedUsernames), '?'));
        $userQuery = "SELECT id, username, email FROM users WHERE username IN ($placeholders)";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->execute($mentionedUsernames);
        $taggedUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process tagged users for UI display only
        if (!empty($taggedUsers) && !empty($claim)) {
            // Keep the @ symbols in the note for email notification
            // but we'll format them differently in the email_helper.php
            $cleanNote = $note;
            
            // Email settings are now properly configured from email_config.php
            // Set to true to disable emails for testing
            $disableEmails = false;
            
            if (!$disableEmails) {
                // Store notification in database to prevent duplicates
                // Check if we've already sent a notification for this note
                $checkQuery = "SELECT id FROM claim_note_notifications WHERE note_id = ?";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->execute([$noteId]);
                
                if ($checkStmt->rowCount() === 0) {
                    // No notification has been sent for this note yet
                    try {
                        // Begin transaction to ensure data consistency
                        $conn->beginTransaction();
                        
                        // Insert notification record first to prevent duplicates
                        $insertNotificationQuery = "INSERT INTO claim_note_notifications 
                                                  (note_id, sent_at, recipients) 
                                                  VALUES (?, NOW(), ?)";
                        $insertStmt = $conn->prepare($insertNotificationQuery);
                        $insertStmt->execute([
                            $noteId,
                            json_encode(array_column($taggedUsers, 'username'))
                        ]);
                        
                        // Send the notification
                        $emailResult = sendTaggedUserNotification($taggedUsers, $claim, $cleanNote, $taggerName);
                        
                        // Commit the transaction
                        $conn->commit();
                    } catch (Exception $e) {
                        // If anything goes wrong, roll back the transaction
                        $conn->rollBack();
                        error_log("Error sending notification: " . $e->getMessage());
                    }
                }
            }
        }
    }
    
    // Return success response with force_reload flag
    echo json_encode([
        'success' => true,
        'message' => 'Note added successfully.' . 
                    (!empty($taggedUsers) ? ' Tagged users have been notified.' : ''),
        'note' => $noteData,
        'tagged_users' => $taggedUsers,
        'force_reload' => true // Add this flag to force a page reload
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
