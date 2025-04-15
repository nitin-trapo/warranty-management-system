<?php
/**
 * Template Helper Functions
 * 
 * This file contains helper functions for rendering templates
 * in the Warranty Management System.
 */

/**
 * Render a template file with the given variables
 * 
 * @param string $templatePath Path to the template file
 * @param array $variables Variables to pass to the template
 * @return string Rendered template content
 */
function renderTemplate($templatePath, $variables = []) {
    // Check if template file exists
    if (!file_exists($templatePath)) {
        error_log("Template file not found: $templatePath");
        return '';
    }
    
    // Extract variables to make them available in the template
    extract($variables);
    
    // Start output buffering
    ob_start();
    
    // Include the template file
    include $templatePath;
    
    // Get the buffer contents and clean the buffer
    $content = ob_get_clean();
    
    return $content;
}

/**
 * Get the full path to an email template
 * 
 * @param string $templateName Template name without extension
 * @return string Full path to the template file
 */
function getEmailTemplatePath($templateName) {
    $templatesDir = defined('EMAIL_TEMPLATES_DIR') ? EMAIL_TEMPLATES_DIR : __DIR__ . '/../templates/emails';
    return $templatesDir . '/' . $templateName . '.php';
}

/**
 * Render an email template with the given variables
 * 
 * @param string $templateName Template name without extension
 * @param array $variables Variables to pass to the template
 * @return string Rendered email content
 */
function renderEmailTemplate($templateName, $variables = []) {
    $templatePath = getEmailTemplatePath($templateName);
    return renderTemplate($templatePath, $variables);
}
