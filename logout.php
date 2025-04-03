<?php
/**
 * Logout Page
 * 
 * This file handles the logout process for the Warranty Management System.
 */

// Include required files
require_once 'config/config.php';
require_once 'includes/auth_helper.php';

// Logout user
logout();

// Redirect to login page
header('Location: login.php');
exit;
