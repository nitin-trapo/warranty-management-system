<?php
/**
 * File Helper Functions
 * 
 * This file contains helper functions for handling file paths and URLs.
 */

/**
 * Convert a file path to a web-accessible URL
 * 
 * @param string $filePath The file path stored in the database
 * @return string The web-accessible URL
 */
function getFileUrl($filePath) {
    // If the path is already a URL, return it as is
    if (strpos($filePath, 'http://') === 0 || strpos($filePath, 'https://') === 0) {
        return $filePath;
    }
    
    // Extract the relative path - remove any '../' prefix
    if (strpos($filePath, '../') === 0) {
        $filePath = substr($filePath, 3);
    }
    
    // For localhost, construct the full URL with project folder
    return 'http://localhost/warranty-management-system/' . $filePath;
}
?>
