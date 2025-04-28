<?php
/**
 * This is a fixed version of the claims.php file
 * Copy the contents of this file to replace your existing claims.php file
 */

/**
 * Claims Management
 * 
 * This file allows administrators to manage warranty claims including
 * creating new claims, viewing existing claims, and updating claim status.
 */

// Set page title
$pageTitle = 'Claims Management';

// Include header
require_once 'includes/header.php';

// Include database connection
require_once '../config/database.php';

// Include ODIN API helper
require_once '../includes/odin_api_helper.php';

// Include email helper
require_once '../includes/email_helper.php';

// Include user helper
require_once '../includes/user_helper.php';

// Include category helper
require_once '../includes/category_helper.php';

// Establish database connection
$conn = getDbConnection();

// Process claim submission
if (isset($_POST['action']) && $_POST['action'] === 'submit_claim') {
    try {
        $conn = getDbConnection();
        
        // Get form data
        $orderId = trim($_POST['order_id']);
        $customerName = trim($_POST['customer_name']);
        $customerEmail = trim($_POST['customer_email']);
        $customerPhone = isset($_POST['customer_phone']) ? trim($_POST['customer_phone']) : '';
        $deliveryDate = $_POST['delivery_date'];
        $userId = $_SESSION['user_id'];
        
        // Get arrays of item-specific data
        $itemSkus = $_POST['item_sku'] ?? [];
        $itemProductNames = $_POST['item_product_name'] ?? [];
        $itemProductTypes = $_POST['item_product_type'] ?? [];
        $categoryIds = $_POST['category_id'] ?? [];
        $descriptions = $_POST['description'] ?? [];
        $claimNumbers = $_POST['claim_number'] ?? [];
        
        // Process multiple items
        $insertedClaimIds = [];
        
        // Start transaction
        $conn->beginTransaction();
        
        for ($i = 0; $i < count($itemSkus); $i++) {
            $sku = $itemSkus[$i];
            $productName = $itemProductNames[$i];
            $productType = $itemProductTypes[$i];
            $categoryId = $categoryIds[$i];
            $description = $descriptions[$i];
            $claimNumber = $claimNumbers[$i] ?? 'CLAIM-' . strtoupper(substr(uniqid(), -6));
            
            // Insert claim record
            $insertClaimSQL = "
                INSERT INTO `claims` (
                    `order_id`, `customer_name`, `customer_email`, `customer_phone`, 
                    `delivery_date`, `created_by`, `assigned_to`, `claim_number`
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?
                )
            ";
            
            $stmt = $conn->prepare($insertClaimSQL);
            $stmt->execute([
                $orderId, $customerName, $customerEmail, $customerPhone,
                $deliveryDate, $userId, $userId, $claimNumber
            ]);
            
            $claimId = $conn->lastInsertId();
            $insertedClaimIds[] = $claimId;
            
            // Insert claim item
            $insertItemSQL = "
                INSERT INTO `claim_items` (`claim_id`, `sku`, `product_name`, `product_type`, `description`, `category_id`)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $conn->prepare($insertItemSQL);
            $stmt->execute([$claimId, $sku, $productName, $productType, $description, $categoryId]);
            
            // Process photos for this item
            $photoField = "photos_" . $i;
            if (!empty($_FILES[$photoField]['name'][0])) {
                $uploadDir = '../uploads/claims/' . $claimId . '/photos/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $photoCount = count($_FILES[$photoField]['name']);
                
                for ($j = 0; $j < $photoCount; $j++) {
                    if ($_FILES[$photoField]['error'][$j] === 0) {
                        $fileName = basename($_FILES[$photoField]['name'][$j]);
                        $fileSize = $_FILES[$photoField]['size'][$j];
                        $targetFile = $uploadDir . uniqid() . '_' . $fileName;
                        
                        // Move uploaded file
                        if (move_uploaded_file($_FILES[$photoField]['tmp_name'][$j], $targetFile)) {
                            // Insert into claim_media table
                            $insertMediaSQL = "
                                INSERT INTO `claim_media` (`claim_id`, `file_path`, `file_type`, `original_filename`, `file_size`)
                                VALUES (?, ?, ?, ?, ?)
                            ";
                            
                            $stmt = $conn->prepare($insertMediaSQL);
                            $stmt->execute([$claimId, $targetFile, 'photo', $fileName, $fileSize]);
                        }
                    }
                }
            }
            
            // Process videos for this item
            $videoField = "videos_" . $i;
            if (!empty($_FILES[$videoField]['name'][0])) {
                $uploadDir = '../uploads/claims/' . $claimId . '/videos/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $videoCount = count($_FILES[$videoField]['name']);
                
                for ($j = 0; $j < $videoCount; $j++) {
                    if ($_FILES[$videoField]['error'][$j] === 0) {
                        $fileName = basename($_FILES[$videoField]['name'][$j]);
                        $fileSize = $_FILES[$videoField]['size'][$j];
                        $targetFile = $uploadDir . uniqid() . '_' . $fileName;
                        
                        // Move uploaded file
                        if (move_uploaded_file($_FILES[$videoField]['tmp_name'][$j], $targetFile)) {
                            // Insert into claim_media table
                            $insertMediaSQL = "
                                INSERT INTO `claim_media` (`claim_id`, `file_path`, `file_type`, `original_filename`, `file_size`)
                                VALUES (?, ?, ?, ?, ?)
                            ";
                            
                            $stmt = $conn->prepare($insertMediaSQL);
                            $stmt->execute([$claimId, $targetFile, 'video', $fileName, $fileSize]);
                        }
                    }
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message with the number of claims created
        $claimCount = count($insertedClaimIds);
        $_SESSION['success_message'] = "Successfully created {$claimCount} warranty claim(s) for order {$orderId}.";
        
        // Send email notifications to approvers based on category
        foreach ($insertedClaimIds as $index => $claimId) {
            try {
                // Get the category ID for this claim
                $categoryId = $categoryIds[$index];
                
                // Get category details including approver
                $categoryStmt = $conn->prepare("SELECT * FROM claim_categories WHERE id = ?");
                $categoryStmt->execute([$categoryId]);
                $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$category) {
                    error_log("Category not found for ID: $categoryId");
                    continue;
                }
                
                $approverRole = $category['approver'] ?? null;
                error_log("Category ID: $categoryId, Name: {$category['name']}, Approver Role: " . ($approverRole ?: 'None'));
                
                if (empty($approverRole)) {
                    error_log("No approver role set for category ID: $categoryId");
                    continue;
                }
                
                // Get users with this approver role
                $approverStmt = $conn->prepare("SELECT id, username, email, first_name, last_name, approver_role FROM users WHERE approver_role = ? AND status = 'active'");
                $approverStmt->execute([$approverRole]);
                $approvers = $approverStmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Found " . count($approvers) . " approvers for role: $approverRole");
                error_log("Approvers: " . json_encode($approvers));
                
                if (!empty($approvers)) {
                    // Get claim details for email
                    $claimStmt = $conn->prepare("SELECT c.*, u.email as created_by_email, u.first_name as created_by_first_name, u.last_name as created_by_last_name 
                                               FROM claims c 
                                               LEFT JOIN users u ON c.created_by = u.id 
                                               WHERE c.id = ?");
                    $claimStmt->execute([$claimId]);
                    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get claim items
                    $itemsStmt = $conn->prepare("SELECT ci.*, cc.name as category_name 
                                              FROM claim_items ci 
                                              LEFT JOIN claim_categories cc ON ci.category_id = cc.id 
                                              WHERE ci.claim_id = ?");
                    $itemsStmt->execute([$claimId]);
                    $claimItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Prepare email recipients
                    $recipients = [];
                    foreach ($approvers as $approver) {
                        if (!empty($approver['email'])) {
                            $recipients[] = $approver['email'];
                            error_log("Added recipient: {$approver['email']} (Approver Role: {$approver['approver_role']})");
                        } else {
                            error_log("Approver without email: " . json_encode($approver));
                        }
                    }
                    error_log("Final recipients list: " . json_encode($recipients));
                    
                    // Get the creator's user details
                    $creatorUser = getUserDetailsById($userId);
                    if ($creatorUser) {
                        $claim['created_by_name'] = $creatorUser['first_name'] . ' ' . $creatorUser['last_name'];
                        $claim['created_by_email'] = $creatorUser['email'];
                    }
                    
                    // Add category approver information to claim data for email template
                    $claim['category_approver'] = $approverRole;
                    
                    // Send notification email
                    if (!empty($recipients)) {
                        error_log("Sending email notification for claim ID: $claimId to recipients: " . json_encode($recipients));
                        $emailResult = sendClaimNotificationEmail($claim, $claimItems, $recipients, true, true);
                        error_log("Email notification result: " . ($emailResult ? 'Success' : 'Failed'));
                    } else {
                        error_log("No recipients found for claim ID: $claimId with approver role: $approverRole");
                    }
                }
            } catch (Exception $e) {
                error_log("Error processing notification for claim ID $claimId: " . $e->getMessage());
            }
        }
        
    } catch (PDOException $e) {
        // Log error
        error_log("Error creating claim: " . $e->getMessage());
        
        // Set error message
        $errorMessage = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        // Log error
        error_log("Error creating claim: " . $e->getMessage());
        
        // Set error message
        $errorMessage = $e->getMessage();
    }
}
?>
