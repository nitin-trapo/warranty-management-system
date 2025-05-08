<?php
/**
 * AJAX handler for storing verified SKUs in session
 * This file processes AJAX requests for storing verified SKUs and returns JSON responses
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['verified_skus']) || !is_array($input['verified_skus'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Verified SKUs are required'
    ]);
    exit;
}

// Store verified SKUs in session
$_SESSION['verified_skus'] = $input['verified_skus'];

// Return successful response
echo json_encode([
    'success' => true,
    'message' => 'Verified SKUs stored in session',
    'count' => count($input['verified_skus'])
]);
?>
