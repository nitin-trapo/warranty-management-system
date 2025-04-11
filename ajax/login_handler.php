<?php
/**
 * Login AJAX Handler
 * 
 * This file handles AJAX requests for the login process.
 */

// Include required files
require_once '../config/config.php';
require_once '../includes/auth_helper.php';

// Set header to JSON
header('Content-Type: application/json');

// Initialize response array
$response = [
    'status' => 'error',
    'message' => 'Invalid request'
];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get action from request
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Handle different actions
    switch ($action) {
        case 'send_otp':
            // Get email from request
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            
            // Validate email
            if (empty($email)) {
                $response = [
                    'status' => 'error',
                    'message' => 'Please enter your email address.'
                ];
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response = [
                    'status' => 'error',
                    'message' => 'Please enter a valid email address.'
                ];
            } else {
                // Send OTP
                $result = sendLoginOtp($email);
                
                // Return result
                $response = $result;
                
                // Add email to response for client-side use
                if ($result['status'] === 'success') {
                    $response['email'] = $email;
                }
            }
            break;
            
        case 'verify_otp':
            // Get data from request
            $userId = isset($_POST['user_id']) ? $_POST['user_id'] : '';
            $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
            
            // Validate OTP
            if (empty($otp)) {
                $response = [
                    'status' => 'error',
                    'message' => 'Please enter the OTP sent to your email.'
                ];
            } else {
                // Verify OTP
                $result = verifyLoginOtp($userId, $otp);
                
                // Return result
                $response = $result;
                
                // Add redirect URL to response for client-side redirect
                if ($result['status'] === 'success') {
                    $response['redirect'] = ($result['role'] === 'admin') 
                        ? 'admin/dashboard.php' 
                        : 'admin/dashboard.php';
                }
            }
            break;
            
        case 'resend_otp':
            // Get data from request
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            
            // Validate email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response = [
                    'status' => 'error',
                    'message' => 'Invalid email address.'
                ];
            } else {
                // Send OTP again
                $result = sendLoginOtp($email);
                
                // Return result
                $response = $result;
            }
            break;
            
        default:
            $response = [
                'status' => 'error',
                'message' => 'Invalid action.'
            ];
            break;
    }
}

// Return JSON response
echo json_encode($response);
