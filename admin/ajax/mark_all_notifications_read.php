<?php
/**
 * Mark All Notifications as Read AJAX Endpoint
 * 
 * This file handles AJAX requests to mark all notifications as read.
 */

// Include required files
require_once '../../includes/auth_helper.php';
require_once '../../config/database.php';
require_once '../../includes/notification_helper.php';

// Require admin or CS agent privileges
requireAdminOrCsAgent();

// Set content type to JSON
header('Content-Type: application/json');

// Mark all notifications as read for the current user
$success = markAllNotificationsAsRead($_SESSION['user_id']);

// Return response
echo json_encode([
    'success' => $success,
    'message' => $success ? 'All notifications marked as read' : 'Failed to mark all notifications as read'
]);
