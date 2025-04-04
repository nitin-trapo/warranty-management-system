<?php
// Set header to JSON
header('Content-Type: application/json');

// Echo back all POST data
echo json_encode([
    'success' => true,
    'message' => 'Test successful',
    'post_data' => $_POST,
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'headers' => [
        'x_requested_with' => isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : 'Not Set'
    ]
]);
?>
