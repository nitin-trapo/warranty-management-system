<?php
/**
 * Helper functions for Warranty Management System
 * Contains utility functions used throughout the application
 */

// Define paths if not already defined
if (!defined('LOGS_DIR')) {
    define('LOGS_DIR', __DIR__ . '/../logs');
}

if (!defined('UPLOADS_DIR')) {
    define('UPLOADS_DIR', __DIR__ . '/../uploads');
}

/**
 * Sanitize user input
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is an admin
 * 
 * @return bool True if user is an admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Redirect to a URL
 * 
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Get all claim categories
 * 
 * @return array Array of categories
 */
function getClaimCategories() {
    // This function requires database connection
    // If you're using a different database connection method, adjust accordingly
    global $conn;
    
    if (isset($conn)) {
        $result = $conn->query("SELECT * FROM claim_categories ORDER BY name");
        
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    return [];
}

/**
 * Get claim status label
 * 
 * @param string $status Status code
 * @return string Status label
 */
function getClaimStatusLabel($status) {
    $labels = [
        'new' => 'New',
        'in_progress' => 'In Progress',
        'on_hold' => 'On Hold',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed'
    ];
    
    return isset($labels[$status]) ? $labels[$status] : 'Unknown';
}

/**
 * Get claim status badge class
 * 
 * @param string $status Status code
 * @return string Badge class
 */
function getClaimStatusBadgeClass($status) {
    $classes = [
        'new' => 'bg-info',
        'in_progress' => 'bg-primary',
        'on_hold' => 'bg-warning',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'completed' => 'bg-secondary'
    ];
    
    return isset($classes[$status]) ? $classes[$status] : 'bg-secondary';
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return '';
    }
    
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

/**
 * Generate a unique reference number
 * 
 * @param string $prefix Prefix for reference number
 * @return string Reference number
 */
function generateReferenceNumber($prefix = 'WC') {
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    return $prefix . '-' . $timestamp . '-' . $random;
}

/**
 * Log message to file
 * 
 * @param string $message Message to log
 * @param string $type Log type (info, error, debug)
 * @param string $file Log file name
 */
function logMessage($message, $type = 'info', $file = 'system.log') {
    $logDir = LOGS_DIR;
    
    // Create log directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/' . $file;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$type}] {$message}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Get user by ID
 * 
 * @param int $userId User ID
 * @return array|null User data or null if not found
 */
function getUserById($userId) {
    // This function requires database connection
    // If you're using a different database connection method, adjust accordingly
    global $conn;
    
    if (isset($conn)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }
    
    return null;
}

/**
 * Get claim by ID
 * 
 * @param int $claimId Claim ID
 * @return array|null Claim data or null if not found
 */
function getClaimById($claimId) {
    // This function requires database connection
    // If you're using a different database connection method, adjust accordingly
    global $conn;
    
    if (isset($conn)) {
        $stmt = $conn->prepare("SELECT * FROM claims WHERE id = ?");
        $stmt->bind_param("i", $claimId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }
    
    return null;
}

/**
 * Get claim items by claim ID
 * 
 * @param int $claimId Claim ID
 * @return array Array of claim items
 */
function getClaimItems($claimId) {
    // This function requires database connection
    // If you're using a different database connection method, adjust accordingly
    global $conn;
    
    if (isset($conn)) {
        $stmt = $conn->prepare("SELECT * FROM claim_items WHERE claim_id = ?");
        $stmt->bind_param("i", $claimId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    return [];
}

/**
 * Create logs directory if it doesn't exist
 */
function ensureLogsDirectory() {
    $logDir = LOGS_DIR;
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
}

/**
 * Create uploads directory if it doesn't exist
 */
function ensureUploadsDirectory() {
    $uploadsDir = UPLOADS_DIR;
    
    if (!file_exists($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
}

// Ensure logs and uploads directories exist
ensureLogsDirectory();
ensureUploadsDirectory();
?>
