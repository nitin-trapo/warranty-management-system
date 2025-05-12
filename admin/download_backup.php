<?php
/**
 * Download Backup File
 * 
 * This file handles the download of database backup files.
 */

// Include required files
require_once '../includes/auth_helper.php';

// Enforce admin-only access
enforceAdminOnly();

// Validate file parameter
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('Location: ' . BASE_URL . '/admin/settings.php?tab=backup');
    exit;
}

// Get file name and sanitize it
$fileName = basename($_GET['file']);

// Prevent directory traversal
if (strpos($fileName, '..') !== false || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
    header('Location: ' . BASE_URL . '/admin/settings.php?tab=backup&error=invalid_file');
    exit;
}

// Set backup directory
$backupDir = '../database/backups';
$filePath = $backupDir . '/' . $fileName;

// Check if file exists
if (!file_exists($filePath)) {
    header('Location: ' . BASE_URL . '/admin/settings.php?tab=backup&error=file_not_found');
    exit;
}

// Get file information
$fileSize = filesize($filePath);
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Validate file extension (only allow zip files)
if ($fileExtension !== 'zip') {
    header('Location: ' . BASE_URL . '/admin/settings.php?tab=backup&error=invalid_extension');
    exit;
}

// Set appropriate headers for file download
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Clear output buffer
ob_clean();
flush();

// Read file and output to browser
readfile($filePath);
exit;
?>
