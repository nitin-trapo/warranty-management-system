<?php
/**
 * Create Test Notification AJAX Endpoint
 * 
 * This file creates a test notification for the current user.
 */

// Include required files
require_once '../../includes/auth_helper.php';
require_once '../../config/database.php';
require_once '../../includes/notification_helper.php';

// Require admin or CS agent privileges
requireAdminOrCsAgent();

// Set content type to JSON
header('Content-Type: application/json');

// Create test notification
$notificationTypes = ['info', 'success', 'warning', 'danger'];
$type = $notificationTypes[array_rand($notificationTypes)];

$messages = [
    'New claim has been submitted',
    'A claim has been assigned to you',
    'Claim status has been updated',
    'New customer feedback received',
    'System maintenance scheduled'
];
$message = $messages[array_rand($messages)];

$links = [
    'dashboard.php',
    'claims.php',
    'view_claim.php?id=1',
    'profile.php',
    ''
];
$link = $links[array_rand($links)];

// Add notification
$success = addNotification($type, $message, $_SESSION['user_id'], $link);

// Return response
echo json_encode([
    'success' => $success,
    'message' => $success ? 'Test notification created successfully' : 'Failed to create test notification',
    'redirect' => 'notifications.php'
]);

// Redirect if not AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header('Location: ../notifications.php');
    exit;
}
?>
