<?php
/**
 * Main Configuration
 * 
 * This file contains the main configuration settings for the Warranty Management System.
 */

// Application settings
define('APP_NAME', 'Warranty Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/warranty-management-system');
define('TIMEZONE', 'Asia/Kuala_Lumpur'); // Malaysia (Kuala Lumpur) timezone

// Set default timezone
date_default_timezone_set(TIMEZONE);

// Session settings
define('SESSION_NAME', 'wms_session');
define('SESSION_LIFETIME', 86400); // 24 hours in seconds

// Path settings
define('ROOT_PATH', dirname(__DIR__));
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ATTACHMENTS_PATH', UPLOADS_PATH . '/attachments');
define('LOGS_DIR', ROOT_PATH . '/logs');

// Ensure upload directories exist
if (!file_exists(UPLOADS_PATH)) {
    mkdir(UPLOADS_PATH, 0755, true);
}
if (!file_exists(ATTACHMENTS_PATH)) {
    mkdir(ATTACHMENTS_PATH, 0755, true);
}
if (!file_exists(LOGS_DIR)) {
    mkdir(LOGS_DIR, 0755, true);
}

// Error reporting settings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once __DIR__ . '/database.php';

// Include API configuration
require_once __DIR__ . '/api_config.php';

// Include email configuration
require_once __DIR__ . '/email_config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
?>
