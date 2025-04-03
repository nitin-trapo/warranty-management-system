<?php
/**
 * Admin Index Page
 * 
 * This file redirects users to the login page if not logged in,
 * or to the admin dashboard if already logged in as admin.
 */

// Include required files
require_once '../config/config.php';
require_once '../includes/auth_helper.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Check if user is admin
    if (isAdmin()) {
        // Redirect to admin dashboard
        header('Location: dashboard.php');
    } else {
        // Redirect to CS agent dashboard (not admin)
        header('Location: ../cs_agent/dashboard.php');
    }
} else {
    // Redirect to login page
    header('Location: ../login.php');
}
exit;
