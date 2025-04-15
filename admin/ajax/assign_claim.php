<?php
/**
 * Assign Claim AJAX Endpoint
 * 
 * This file handles AJAX requests to assign a claim to a CS agent.
 */

// Include required files
require_once '../../includes/auth_helper.php';
require_once '../../config/database.php';
require_once '../../includes/notification_helper.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log request data
error_log("Assign claim request received: " . json_encode($_POST));

// Enforce admin-only access
enforceAdminOnly();

// Set content type to JSON
header('Content-Type: application/json');

// Check if required parameters are provided
if (!isset($_POST['claim_id']) || !isset($_POST['agent_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$claimIdentifier = trim($_POST['claim_id']);
$agentId = (int) $_POST['agent_id'];
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Validate agent ID
if ($agentId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid agent ID'
    ]);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Get agent details
    $stmt = $conn->prepare("
        SELECT username, first_name, last_name 
        FROM users 
        WHERE id = :agent_id AND role = 'cs_agent' AND status = 'active'
    ");
    $stmt->bindParam(':agent_id', $agentId);
    $stmt->execute();
    
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        echo json_encode([
            'success' => false,
            'message' => 'Selected agent not found or is inactive'
        ]);
        exit;
    }
    
    // Debug log
    error_log("Agent found: " . json_encode($agent));
    
    // Determine if claim_id is numeric (internal ID) or a claim number
    $claimId = null;
    $claimNumber = null;

    // Remove # if present
    $claimIdentifier = str_replace('#', '', $claimIdentifier);
    
    if (is_numeric($claimIdentifier)) {
        $claimId = (int) $claimIdentifier;
        
        // Debug log
        error_log("Searching for claim with ID: " . $claimId);
        
        $stmt = $conn->prepare("
            SELECT id, claim_number, order_id, customer_name, status, assigned_to
            FROM claims 
            WHERE id = :claim_id
        ");
        $stmt->bindParam(':claim_id', $claimId);
    } else {
        $claimNumber = $claimIdentifier;
        
        // Debug log
        error_log("Searching for claim with number: " . $claimNumber);
        
        $stmt = $conn->prepare("
            SELECT id, claim_number, order_id, customer_name, status, assigned_to
            FROM claims 
            WHERE claim_number = :claim_number
        ");
        $stmt->bindParam(':claim_number', $claimNumber);
    }
    
    $stmt->execute();
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug log
    error_log("Claim search result: " . ($claim ? json_encode($claim) : "Not found"));
    
    if (!$claim) {
        echo json_encode([
            'success' => false,
            'message' => 'Claim not found. Please check the claim ID or number and try again.'
        ]);
        exit;
    }
    
    // Check if claim is already assigned to the same agent
    if ($claim['assigned_to'] == $agentId) {
        echo json_encode([
            'success' => false,
            'message' => 'Claim is already assigned to this agent'
        ]);
        exit;
    }
    
    // Get previous agent details if any
    $previousAgentName = 'None';
    if ($claim['assigned_to']) {
        $stmt = $conn->prepare("
            SELECT CONCAT(first_name, ' ', last_name) as agent_name
            FROM users 
            WHERE id = :user_id
        ");
        $stmt->bindParam(':user_id', $claim['assigned_to']);
        $stmt->execute();
        $previousAgent = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($previousAgent) {
            $previousAgentName = $previousAgent['agent_name'];
        }
    }
    
    // Update claim assignment
    $stmt = $conn->prepare("
        UPDATE claims 
        SET assigned_to = :agent_id,
            updated_at = NOW()
        WHERE id = :claim_id
    ");
    $stmt->bindParam(':agent_id', $agentId);
    $stmt->bindParam(':claim_id', $claim['id']);
    $success = $stmt->execute();
    
    if (!$success) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to assign claim'
        ]);
        exit;
    }
    
    // Add assignment note if provided
    if (!empty($notes)) {
        // Check if claim_notes table exists
        $tableExists = false;
        $stmt = $conn->query("SHOW TABLES LIKE 'claim_notes'");
        if ($stmt->rowCount() > 0) {
            $tableExists = true;
        }
        
        // Create claim_notes table if it doesn't exist
        if (!$tableExists) {
            $createTableSQL = "
                CREATE TABLE `claim_notes` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `claim_id` int(11) NOT NULL,
                    `created_by` int(11) NOT NULL,
                    `note` text NOT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `claim_id` (`claim_id`),
                    KEY `created_by` (`created_by`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            
            $conn->exec($createTableSQL);
        }
        
        $stmt = $conn->prepare("
            INSERT INTO claim_notes (claim_id, created_by, note, created_at)
            VALUES (:claim_id, :created_by, :note, NOW())
        ");
        $stmt->bindParam(':claim_id', $claim['id']);
        $stmt->bindParam(':created_by', $_SESSION['user_id']);
        $stmt->bindParam(':note', $notes);
        $stmt->execute();
    }
    
    // Create notification for the assigned agent
    $claimIdentifier = !empty($claim['claim_number']) ? $claim['claim_number'] : "#" . $claim['id'];
    $notificationMessage = "You have been assigned to claim {$claimIdentifier} for order {$claim['order_id']}";

    // Check if notifications table exists
    $tableExists = false;
    $stmt = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
    }

    // Create notifications table if it doesn't exist
    if (!$tableExists) {
        $createTableSQL = "
        CREATE TABLE notifications (
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
        
        $conn->exec($createTableSQL);
    }

    // Add notification directly if helper function is not working
    try {
        // Try using the helper function first
        $success = addNotification('info', $notificationMessage, $agentId, "view_claim.php?id={$claim['id']}");
        
        // If it fails, add notification directly
        if (!$success) {
            $stmt = $conn->prepare("
                INSERT INTO notifications (type, message, user_id, link, created_at)
                VALUES (:type, :message, :user_id, :link, NOW())
            ");
            
            $type = 'info';
            $link = "view_claim.php?id={$claim['id']}";
            
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':message', $notificationMessage);
            $stmt->bindParam(':user_id', $agentId);
            $stmt->bindParam(':link', $link);
            
            $stmt->execute();
        }
        
        // Create notification for admin (activity log)
        $adminUser = $_SESSION['user_id'];
        $agentName = $agent['first_name'] . ' ' . $agent['last_name'];
        $adminNotificationMessage = "Claim {$claimIdentifier} reassigned from {$previousAgentName} to {$agentName}";
        
        // Try using the helper function first
        $success = addNotification('success', $adminNotificationMessage, 0, "view_claim.php?id={$claim['id']}");
        
        // If it fails, add notification directly
        if (!$success) {
            $stmt = $conn->prepare("
                INSERT INTO notifications (type, message, user_id, link, created_at)
                VALUES (:type, :message, :user_id, :link, NOW())
            ");
            
            $type = 'success';
            $link = "view_claim.php?id={$claim['id']}";
            $userId = 0; // 0 means all users
            
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':message', $adminNotificationMessage);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':link', $link);
            
            $stmt->execute();
        }
    } catch (Exception $e) {
        // Log error but don't stop execution
        error_log("Error creating notifications: " . $e->getMessage());
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Claim {$claimIdentifier} successfully assigned to {$agentName}",
        'claim_id' => $claim['id'],
        'agent_name' => $agentName
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Error assigning claim: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
