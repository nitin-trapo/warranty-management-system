<?php
/**
 * Create Notifications Table
 * 
 * This script creates the notifications table in the database.
 */

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include database configuration
require_once ROOT_PATH . '/config/database.php';

try {
    // Get database connection
    $conn = getDbConnection();
    
    // Create notifications table
    $sql = "
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('info', 'success', 'warning', 'danger') NOT NULL DEFAULT 'info',
        message TEXT NOT NULL,
        user_id INT NOT NULL DEFAULT 0 COMMENT '0 means notification for all users',
        link VARCHAR(255) DEFAULT NULL,
        is_read BOOLEAN NOT NULL DEFAULT FALSE,
        read_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (is_read)
    );
    ";
    
    // Execute query
    $conn->exec($sql);
    
    echo "Notifications table created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating notifications table: " . $e->getMessage() . "\n";
}
