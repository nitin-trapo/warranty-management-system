<?php
/**
 * Mark Notification as Read AJAX Endpoint
 * 
 * This file handles AJAX requests to mark a notification as read.
 */

// Include required files
require_once '../../includes/auth_helper.php';
require_once '../../config/database.php';
require_once '../../includes/notification_helper.php';

// Require admin or CS agent privileges
requireAdminOrCsAgent();

// Set content type to JSON
header('Content-Type: application/json');

// Check if notification ID is provided
if (!isset($_POST['notification_id']) || !is_numeric($_POST['notification_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid notification ID'
    ]);
    exit;
}

$notificationId = (int) $_POST['notification_id'];

// Mark notification as read
$success = markNotificationAsRead($notificationId);

// Return response
echo json_encode([
    'success' => $success,
    'message' => $success ? 'Notification marked as read' : 'Failed to mark notification as read'
]);
