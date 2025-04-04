<?php
/**
 * Alert Helper
 * 
 * This file contains functions for handling alert messages across the application.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Set an alert message in the session
 * 
 * @param string $type The type of alert (success, danger, warning, info)
 * @param string $message The message to display
 * @return void
 */
function setAlert($type, $message) {
    $_SESSION['alert_type'] = $type;
    $_SESSION['alert_message'] = $message;
}

/**
 * Set a success alert message
 * 
 * @param string $message The message to display
 * @return void
 */
function setSuccessAlert($message) {
    setAlert('success', $message);
}

/**
 * Set an error alert message
 * 
 * @param string $message The message to display
 * @return void
 */
function setErrorAlert($message) {
    setAlert('danger', $message);
}

/**
 * Set a warning alert message
 * 
 * @param string $message The message to display
 * @return void
 */
function setWarningAlert($message) {
    setAlert('warning', $message);
}

/**
 * Set an info alert message
 * 
 * @param string $message The message to display
 * @return void
 */
function setInfoAlert($message) {
    setAlert('info', $message);
}

/**
 * Display alert message if exists and clear it from session
 * 
 * @return string HTML for the alert message or empty string if no alert
 */
function displayAlert() {
    $html = '';
    
    if (isset($_SESSION['alert_type']) && isset($_SESSION['alert_message'])) {
        $type = $_SESSION['alert_type'];
        $message = $_SESSION['alert_message'];
        
        $html = '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
        $html .= $message;
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';
        
        // Clear the alert from session
        unset($_SESSION['alert_type']);
        unset($_SESSION['alert_message']);
    }
    
    return $html;
}

/**
 * Check if an alert exists in the session
 * 
 * @return boolean True if an alert exists, false otherwise
 */
function hasAlert() {
    return isset($_SESSION['alert_type']) && isset($_SESSION['alert_message']);
}

/**
 * Check if a success alert exists in the session
 * 
 * @return boolean True if a success alert exists, false otherwise
 */
function hasSuccessAlert() {
    return isset($_SESSION['alert_type']) && $_SESSION['alert_type'] === 'success';
}

/**
 * Check if an error alert exists in the session
 * 
 * @return boolean True if an error alert exists, false otherwise
 */
function hasErrorAlert() {
    return isset($_SESSION['alert_type']) && $_SESSION['alert_type'] === 'danger';
}

/**
 * Check if a warning alert exists in the session
 * 
 * @return boolean True if a warning alert exists, false otherwise
 */
function hasWarningAlert() {
    return isset($_SESSION['alert_type']) && $_SESSION['alert_type'] === 'warning';
}

/**
 * Check if an info alert exists in the session
 * 
 * @return boolean True if an info alert exists, false otherwise
 */
function hasInfoAlert() {
    return isset($_SESSION['alert_type']) && $_SESSION['alert_type'] === 'info';
}

/**
 * Get the alert message from the session
 * 
 * @return string The alert message or empty string if no alert
 */
function getAlertMessage() {
    return isset($_SESSION['alert_message']) ? $_SESSION['alert_message'] : '';
}

/**
 * Get the success alert message from the session
 * 
 * @return string The success alert message or empty string if no success alert
 */
function getSuccessAlert() {
    return hasSuccessAlert() ? $_SESSION['alert_message'] : '';
}

/**
 * Get the error alert message from the session
 * 
 * @return string The error alert message or empty string if no error alert
 */
function getErrorAlert() {
    return hasErrorAlert() ? $_SESSION['alert_message'] : '';
}

/**
 * Get the warning alert message from the session
 * 
 * @return string The warning alert message or empty string if no warning alert
 */
function getWarningAlert() {
    return hasWarningAlert() ? $_SESSION['alert_message'] : '';
}

/**
 * Get the info alert message from the session
 * 
 * @return string The info alert message or empty string if no info alert
 */
function getInfoAlert() {
    return hasInfoAlert() ? $_SESSION['alert_message'] : '';
}
?>
