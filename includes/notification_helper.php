<?php
/**
 * Notification Helper Functions
 * 
 * This file contains functions for managing notifications in the Warranty Management System.
 */

/**
 * Add a new notification
 * 
 * @param string $type Notification type (info, success, warning, danger)
 * @param string $message Notification message
 * @param int $user_id User ID (0 for all users)
 * @param string $link Optional link to redirect when clicked
 * @return bool True if notification was added successfully, false otherwise
 */
function addNotification($type, $message, $user_id = 0, $link = '') {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (type, message, user_id, link, created_at)
            VALUES (:type, :message, :user_id, :link, NOW())
        ");
        
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':link', $link);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error adding notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notifications for a user
 * 
 * @param int $user_id User ID
 * @param int $limit Maximum number of notifications to return
 * @return array Array of notifications
 */
function getUnreadNotifications($user_id, $limit = 10) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            WHERE (user_id = :user_id OR user_id = 0)
            AND is_read = 0
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get notification count for a user
 * 
 * @param int $user_id User ID
 * @return int Number of unread notifications
 */
function getNotificationCount($user_id) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM notifications 
            WHERE (user_id = :user_id OR user_id = 0)
            AND is_read = 0
        ");
        
        $stmt->bindParam(':user_id', $user_id);
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark a notification as read
 * 
 * @param int $notification_id Notification ID
 * @return bool True if notification was marked as read successfully, false otherwise
 */
function markNotificationAsRead($notification_id) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW()
            WHERE id = :notification_id
        ");
        
        $stmt->bindParam(':notification_id', $notification_id);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $user_id User ID
 * @return bool True if notifications were marked as read successfully, false otherwise
 */
function markAllNotificationsAsRead($user_id) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW()
            WHERE (user_id = :user_id OR user_id = 0)
            AND is_read = 0
        ");
        
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete old notifications
 * 
 * @param int $days Number of days to keep notifications
 * @return bool True if old notifications were deleted successfully, false otherwise
 */
function deleteOldNotifications($days = 30) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error deleting old notifications: " . $e->getMessage());
        return false;
    }
}
