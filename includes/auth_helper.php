<?php
/**
 * Authentication Helper Functions
 * 
 * This file contains functions for user authentication in the Warranty Management System.
 */

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/email_helper.php';
require_once __DIR__ . '/alert_helper.php';

/**
 * Check if user exists by email
 * 
 * @param string $email User email
 * @return array|bool User data if exists, false otherwise
 */
function getUserByEmail($email) {
    try {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $user = $stmt->fetch();
        return $user ?: false;
    } catch(PDOException $e) {
        error_log("Error getting user by email: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user exists by ID
 * 
 * @param int $userId User ID
 * @return array|bool User data if exists, false otherwise
 */
function getUserById($userId) {
    try {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        $user = $stmt->fetch();
        return $user ?: false;
    } catch(PDOException $e) {
        error_log("Error getting user by ID: " . $e->getMessage());
        return false;
    }
}

/**
 * Send login OTP to user
 * 
 * @param string $email User email
 * @return array Response with status and message
 */
function sendLoginOtp($email) {
    // Check if user exists
    $user = getUserByEmail($email);
    
    if (!$user) {
        return [
            'status' => 'error',
            'message' => 'User not found with this email address.'
        ];
    }
    
    // Check if user is active
    if ($user['status'] !== 'active') {
        return [
            'status' => 'error',
            'message' => 'Your account is inactive. Please contact the administrator.'
        ];
    }
    
    // Generate OTP
    $otp = generateOtp();
    
    // Save OTP to database
    $saved = saveOtp($user['id'], $otp, 'login');
    
    if (!$saved) {
        return [
            'status' => 'error',
            'message' => 'Failed to generate OTP. Please try again.'
        ];
    }
    
    // Send OTP email
    $emailSent = sendOtpEmail($user['email'], $otp, 'login');
    
    if (!$emailSent) {
        return [
            'status' => 'error',
            'message' => 'Failed to send OTP email. Please try again.'
        ];
    }
    
    return [
        'status' => 'success',
        'message' => 'OTP has been sent to your email address.',
        'user_id' => $user['id']
    ];
}

/**
 * Verify login OTP
 * 
 * @param int $userId User ID
 * @param string $otp OTP code
 * @return array Response with status and message
 */
function verifyLoginOtp($userId, $otp) {
    // Check if user exists
    $user = getUserById($userId);
    
    if (!$user) {
        return [
            'status' => 'error',
            'message' => 'Invalid user.'
        ];
    }
    
    // Check if user is active
    if ($user['status'] !== 'active') {
        return [
            'status' => 'error',
            'message' => 'Your account is inactive. Please contact the administrator.'
        ];
    }
    
    // Verify OTP
    $isValid = verifyOtp($userId, $otp, 'login');
    
    if (!$isValid) {
        return [
            'status' => 'error',
            'message' => 'Invalid or expired OTP. Please try again.'
        ];
    }
    
    // Create user session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Log the login
    logUserActivity($user['id'], 'login', 'users', $user['id'], 'User logged in via OTP');
    
    return [
        'status' => 'success',
        'message' => 'Login successful.',
        'role' => $user['role']
    ];
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if user has admin role
 * 
 * @return bool True if user has admin role, false otherwise
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

/**
 * Check if user has CS agent role
 * 
 * @return bool True if user has CS agent role, false otherwise
 */
function isCsAgent() {
    return isLoggedIn() && $_SESSION['role'] === 'cs_agent';
}

/**
 * Logout user
 * 
 * @return void
 */
function logout() {
    // Log the logout if user is logged in
    if (isLoggedIn()) {
        logUserActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id'], 'User logged out');
    }
    
    // Destroy session
    session_unset();
    session_destroy();
}

/**
 * Log user activity
 * 
 * @param int $userId User ID
 * @param string $action Action performed
 * @param string $entityType Entity type
 * @param int $entityId Entity ID
 * @param string $details Action details
 * @return bool True if log was created successfully, false otherwise
 */
function logUserActivity($userId, $action, $entityType, $entityId, $details = '') {
    try {
        $conn = getDbConnection();
        
        // Get client IP address
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':entity_type', $entityType);
        $stmt->bindParam(':entity_id', $entityId);
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':ip_address', $ipAddress);
        
        return $stmt->execute();
    } catch(PDOException $e) {
        error_log("Error logging user activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Redirect to login page if not logged in
 * 
 * @return void
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Redirect to admin dashboard if not admin
 * 
 * @return void
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Redirect to CS agent dashboard if not CS agent
 * 
 * @return void
 */
function requireCsAgent() {
    requireLogin();
    
    if (!isCsAgent()) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Require either admin or CS agent privileges
 * 
 * @return void
 */
function requireAdminOrCsAgent() {
    requireLogin();
    
    if (!isAdmin() && !isCsAgent()) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Enforce admin-only access and redirect non-admins to dashboard
 * 
 * @return void
 */
function enforceAdminOnly() {
    requireLogin();
    
    if (!isAdmin()) {
        // Set alert message
        setAlert('danger', 'Access Denied: You do not have permission to access this page.');
        
        // Redirect to dashboard
        header('Location: dashboard.php');
        exit;
    }
}
