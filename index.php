<?php
/**
 * Index Page
 * 
 * This file redirects users to the appropriate page based on their login status.
 */

// Include required files
require_once 'config/config.php';

// Ensure timezone is set correctly
date_default_timezone_set(TIMEZONE);

require_once 'includes/auth_helper.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Redirect based on user role
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: cs_agent/dashboard.php');
    }
} else {
    // Redirect to login page
    header('Location: login.php');
}
exit;
