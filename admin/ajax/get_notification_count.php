<?php
/**
 * Get Notification Count AJAX Endpoint
 * 
 * This file handles AJAX requests to get the count of unread notifications.
 */

// Include required files
require_once '../../includes/auth_helper.php';
require_once '../../config/database.php';
require_once '../../includes/notification_helper.php';

// Require admin or CS agent privileges
requireAdminOrCsAgent();

// Set content type to JSON
header('Content-Type: application/json');

// Get notification count for the current user
$count = getNotificationCount($_SESSION['user_id']);

// Return response
echo json_encode([
    'success' => true,
    'count' => $count
]);
