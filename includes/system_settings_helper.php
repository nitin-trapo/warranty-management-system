<?php
/**
 * System Settings Helper
 * 
 * This file contains helper functions for managing system settings
 * in the Warranty Management System.
 */

/**
 * Get a system setting value from the database
 * 
 * @param string $key Setting key
 * @return string|null Setting value or null if not found
 */
function getSystemSetting($key) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :key");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    } catch(PDOException $e) {
        error_log("Error getting system setting: " . $e->getMessage());
        return null;
    }
}

/**
 * Update or create a system setting in the database
 * 
 * @param string $key Setting key
 * @param string $value Setting value
 * @return bool True if successful, false otherwise
 */
function updateSystemSetting($key, $value) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        // Check if setting exists
        $stmt = $conn->prepare("SELECT id FROM system_settings WHERE setting_key = :key");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update existing setting
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = :value WHERE setting_key = :key");
        } else {
            // Insert new setting
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (:key, :value)");
        }
        
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        $stmt->execute();
        
        return true;
    } catch(PDOException $e) {
        error_log("Error updating system setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a system setting from the database
 * 
 * @param string $key Setting key
 * @return bool True if successful, false otherwise
 */
function deleteSystemSetting($key) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("DELETE FROM system_settings WHERE setting_key = :key");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        
        return true;
    } catch(PDOException $e) {
        error_log("Error deleting system setting: " . $e->getMessage());
        return false;
    }
}
