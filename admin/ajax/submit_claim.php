<?php
/**
 * AJAX Claim Submission Handler
 * 
 * This script handles AJAX submissions of warranty claims
 * and performs validation before inserting into the database.
 */

// Include required files
require_once '../../config/config.php';
require_once '../../includes/auth_helper.php';
require_once '../../includes/email_helper.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session information
error_log("Session data in AJAX handler: " . json_encode($_SESSION));

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to submit a claim. Please refresh the page and try again.'
    ]);
    exit;
}

// Include database connection
require_once '../../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Process claim submission
try {
    $conn = getDbConnection();
    
    // Get form data
    $orderId = trim($_POST['order_id']);
    
    // Check if customer information is provided
    $customerName = !empty($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
    $customerEmail = !empty($_POST['customer_email']) ? trim($_POST['customer_email']) : '';
    $customerPhone = !empty($_POST['customer_phone']) ? trim($_POST['customer_phone']) : '';
    
    // Debug log for customer information
    error_log("Customer information received: Name: '$customerName', Email: '$customerEmail', Phone: '$customerPhone'");
    error_log("POST data: " . json_encode($_POST));
    
    // Always try to get customer information from the database if available
    // This ensures we have the data even if the form submission has issues
    try {
        $stmt = $conn->prepare("SELECT customer_name, customer_email, customer_phone FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $orderData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($orderData) {
            // Use data from the database regardless of form data
            $customerName = !empty($orderData['customer_name']) ? $orderData['customer_name'] : $customerName;
            $customerEmail = !empty($orderData['customer_email']) ? $orderData['customer_email'] : $customerEmail;
            $customerPhone = !empty($orderData['customer_phone']) ? $orderData['customer_phone'] : $customerPhone;
            
            error_log("Using customer data from database: Name: '$customerName', Email: '$customerEmail', Phone: '$customerPhone'");
        }
    } catch (PDOException $e) {
        error_log("Error fetching customer data from database: " . $e->getMessage());
    }
    
    // Handle arrays for category_id and description
    $categoryIds = isset($_POST['category_id']) ? $_POST['category_id'] : [];
    $descriptions = isset($_POST['description']) ? $_POST['description'] : [];
    
    // For backward compatibility, get the first item if arrays
    $categoryId = is_array($categoryIds) && !empty($categoryIds) ? (int)$categoryIds[0] : (int)$categoryIds;
    $description = is_array($descriptions) && !empty($descriptions) ? trim($descriptions[0]) : trim($descriptions);
    
    $userId = $_SESSION['user_id'];
    $deliveryDate = isset($_POST['delivery_date']) ? trim($_POST['delivery_date']) : date('Y-m-d');
    
    // Get SKU from the first item (if available)
    $sku = '';
    $productType = '';
    if (isset($_POST['item_sku']) && is_array($_POST['item_sku']) && !empty($_POST['item_sku'][0])) {
        $sku = trim($_POST['item_sku'][0]);
        $productType = isset($_POST['item_product_type'][0]) ? trim($_POST['item_product_type'][0]) : '';
    }
    
    // Validate required data
    $errors = [];
    
    if (empty($orderId)) {
        $errors[] = 'Order ID is required.';
    }
    
    if (empty($customerName)) {
        $errors[] = 'Customer name is required.';
    }
    
    if (empty($customerEmail)) {
        $errors[] = 'Customer email is required.';
    }
    
    // Check if we have at least one item with category and description
    $hasValidItem = false;
    
    if (isset($_POST['item_sku']) && is_array($_POST['item_sku'])) {
        foreach ($_POST['item_sku'] as $key => $sku) {
            if (!empty($sku)) {
                // Check if this item has a category
                $itemCategoryId = is_array($_POST['category_id']) ? ($_POST['category_id'][$key] ?? null) : $_POST['category_id'] ?? null;
                $itemDescription = is_array($_POST['description']) ? ($_POST['description'][$key] ?? null) : $_POST['description'] ?? null;
                
                if (!empty($itemCategoryId) && !empty($itemDescription)) {
                    $hasValidItem = true;
                    break;
                }
            }
        }
    }
    
    if (!$hasValidItem) {
        if (!isset($_POST['category_id']) || (is_array($_POST['category_id']) && empty(array_filter($_POST['category_id']))) || (is_string($_POST['category_id']) && empty($_POST['category_id']))) {
            $errors[] = 'Claim category is required.';
        }
        
        if (!isset($_POST['description']) || (is_array($_POST['description']) && empty(array_filter($_POST['description']))) || (is_string($_POST['description']) && empty($_POST['description']))) {
            $errors[] = 'Claim description is required.';
        }
    }
    
    if (empty($sku)) {
        $errors[] = 'At least one item must be selected for the claim.';
    }
    
    // Validate file uploads
    $photoErrors = validatePhotoUploads();
    $videoErrors = validateVideoUploads();
    
    // Add file validation errors to the main errors array
    $errors = array_merge($errors, $photoErrors, $videoErrors);
    
    // If there are validation errors, return them
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please fix the following errors:',
            'errors' => $errors
        ]);
        exit;
    }
    
    // Check if a claim already exists for this order ID and SKU
    $stmt = $conn->prepare("
        SELECT c.id 
        FROM claims c
        JOIN claim_items ci ON c.id = ci.claim_id
        WHERE c.order_id = ? AND ci.sku = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId, $sku]);
    
    if ($stmt->rowCount() > 0) {
        $errors[] = "A claim for this order and product already exists. Please check existing claims.";
    }
    
    // If there are validation errors, return them
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please fix the following errors:',
            'errors' => $errors
        ]);
        exit;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Generate claim number based on order number
    $claimNumber = '';
    if (strpos($orderId, 'TMR-O') === 0) {
        // Extract the numeric part of the order ID
        $orderNumeric = substr($orderId, 5); // Remove "TMR-O" prefix
        $claimNumber = 'CLAIM-' . $orderNumeric;
    } else {
        // Fallback to a random claim number if order ID doesn't match expected format
        $claimNumber = 'CLAIM-' . strtoupper(substr(uniqid(), -8));
    }
    
    // Insert claim record
    $stmt = $conn->prepare("
        INSERT INTO claims (
            order_id, customer_name, customer_email, customer_phone, 
            delivery_date, status, created_by, assigned_to, claim_number
        ) VALUES (
            ?, ?, ?, ?, ?, 'new', ?, ?, ?
        )
    ");
    
    $stmt->execute([
        $orderId, $customerName, $customerEmail, $customerPhone, 
        $deliveryDate, $userId, $userId, $claimNumber
    ]);
    
    $claimId = $conn->lastInsertId();
    
    // Insert claim items
    if (isset($_POST['item_sku']) && is_array($_POST['item_sku'])) {
        $stmt = $conn->prepare("
            INSERT INTO claim_items (
                claim_id, sku, product_name, product_type, category_id, description
            ) VALUES (
                ?, ?, ?, ?, ?, ?
            )
        ");
        
        $claimItemIds = [];
        
        foreach ($_POST['item_sku'] as $key => $sku) {
            if (!empty($sku)) {
                $productName = $_POST['item_product_name'][$key] ?? '';
                $productType = $_POST['item_product_type'][$key] ?? '';
                $itemCategoryId = is_array($_POST['category_id']) ? ($_POST['category_id'][$key] ?? $categoryId) : $categoryId;
                $itemDescription = is_array($_POST['description']) ? ($_POST['description'][$key] ?? $description) : $description;
                
                $stmt->execute([
                    $claimId, $sku, $productName, $productType, $itemCategoryId, $itemDescription
                ]);
                
                $claimItemId = $conn->lastInsertId();
                $claimItemIds[$key] = $claimItemId;
            }
        }
        
        // Process photo uploads for each item
        $photoUploadSuccess = processPhotoUploads($claimId, $claimItemIds, $conn);
        if (!$photoUploadSuccess) {
            // Rollback transaction if photo uploads fail
            $conn->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Error processing photo uploads. Claim was not created.'
            ]);
            exit;
        }
        
        // Process video uploads for each item
        $videoUploadSuccess = processVideoUploads($claimId, $claimItemIds, $conn);
        if (!$videoUploadSuccess) {
            // Rollback transaction if video uploads fail
            $conn->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Error processing video uploads. Claim was not created.'
            ]);
            exit;
        }
    }
    
    // Log claim creation
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (
            user_id, action, entity_type, entity_id, details, ip_address
        ) VALUES (
            ?, ?, ?, ?, ?, ?
        )
    ");
    
    $logDetails = "Created new claim for order: $orderId";
    $stmt->execute([
        $userId, 'create', 'claims', $claimId, $logDetails, $_SERVER['REMOTE_ADDR']
    ]);
    
    // Commit transaction
    $conn->commit();
    
    // Send email notification
    try {
        error_log("===== Starting email notification process for claim ID: {$claimId} =====");
        
        // Get claim details for email
        $stmt = $conn->prepare("
            SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name, u.email as created_by_email
            FROM claims c
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$claimId]);
        $claimData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$claimData) {
            error_log("Failed to retrieve claim data for ID: {$claimId}");
            throw new Exception("Claim data not found");
        }
        
        error_log("Retrieved claim data: " . json_encode(array_intersect_key($claimData, array_flip(['id', 'order_id', 'claim_number', 'customer_email', 'created_by_name', 'created_by_email']))));
        
        // Get claim items with category names and approver information
        $stmt = $conn->prepare("
            SELECT ci.*, cc.name as category_name, cc.approver as category_approver
            FROM claim_items ci
            LEFT JOIN claim_categories cc ON ci.category_id = cc.id
            WHERE ci.claim_id = ?
        ");
        $stmt->execute([$claimId]);
        $claimItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log category and approver information for debugging
        foreach ($claimItems as $item) {
            error_log("Claim item category: {$item['category_name']}, Approver role: {$item['category_approver']}");
        }
        
        if (empty($claimItems)) {
            error_log("No claim items found for claim ID: {$claimId}");
        } else {
            error_log("Retrieved " . count($claimItems) . " claim items");
        }
        
        // Get notification recipients from settings
        $recipients = getClaimNotificationRecipients();
        error_log("Notification recipients from settings: " . json_encode($recipients));
        
        // Check if creator notification is enabled
        $notifyCreator = isCreatorNotificationEnabled();
        error_log("Notify creator setting: " . ($notifyCreator ? 'Enabled' : 'Disabled'));
        
        // Check if staff creator notification is enabled
        $notifyStaffCreator = isStaffCreatorNotificationEnabled();
        error_log("Notify staff creator setting: " . ($notifyStaffCreator ? 'Enabled' : 'Disabled'));
        
        // Send email notification
        $emailSent = sendClaimNotificationEmail($claimData, $claimItems, $recipients, $notifyCreator, $notifyStaffCreator);
        
        if ($emailSent) {
            error_log("Claim notification email sent successfully for claim ID: {$claimId}");
        } else {
            error_log("Failed to send claim notification email for claim ID: {$claimId}");
            
            // Check email configuration
            error_log("Email configuration: Host=" . MAIL_HOST . ", Port=" . MAIL_PORT . ", Username=" . MAIL_USERNAME . ", From=" . MAIL_FROM_ADDRESS);
            
            // Check if PHPMailer is available
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                error_log("PHPMailer class not found. Check if composer dependencies are installed.");
            }
        }
        
        error_log("===== Email notification process completed =====");
    } catch (Exception $e) {
        // Log error but don't affect the claim submission response
        error_log("Error sending claim notification email: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
    }
    
    // Get category name for the response
    $stmt = $conn->prepare("SELECT name FROM claim_categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $categoryName = $stmt->fetchColumn();
    
    // Prepare claim data for the response
    $claim = [
        'id' => $claimId,
        'order_id' => $orderId,
        'customer_name' => $customerName,
        'customer_email' => $customerEmail,
        'category_id' => $categoryId,
        'category_name' => $categoryName,
        'status' => 'new',
        'created_at' => date('Y-m-d H:i:s'),
        'claim_number' => $claimNumber
    ];
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Claim #$claimId created successfully.",
        'claim' => $claim
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction if an error occurs
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log error
    error_log("Error creating claim: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => "Database error: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Rollback transaction if an error occurs
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log error
    error_log("Error creating claim: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Validate photo uploads
 * 
 * @return array Array of error messages
 */
function validatePhotoUploads() {
    $errors = [];
    $maxPhotoSize = 2 * 1024 * 1024; // 2MB in bytes
    
    // Check for item-specific photos (photos_0, photos_1, etc.)
    foreach ($_FILES as $fieldName => $fileData) {
        if (strpos($fieldName, 'photos_') === 0 && !empty($fileData['name'][0])) {
            $fileCount = count($fileData['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($fileData['error'][$i] === UPLOAD_ERR_OK) {
                    $originalName = $fileData['name'][$i];
                    $fileSize = $fileData['size'][$i];
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    
                    // Check file size
                    if ($fileSize > $maxPhotoSize) {
                        $errors[] = "Photo '$originalName' exceeds the maximum size limit of 2MB.";
                    }
                    
                    // Check if it's actually an image
                    $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($extension, $allowedImageExtensions)) {
                        $errors[] = "File '$originalName' is not a valid image format. Allowed formats: JPG, JPEG, PNG, GIF.";
                    }
                } elseif ($fileData['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    // Handle upload errors
                    $originalName = $fileData['name'][$i] ?? 'Unknown file';
                    $errors[] = "Error uploading photo '$originalName': " . uploadErrorMessage($fileData['error'][$i]);
                }
            }
        }
    }
    
    // Check for general photos (backward compatibility)
    if (!empty($_FILES['photos']['name'][0])) {
        $fileCount = count($_FILES['photos']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                $originalName = $_FILES['photos']['name'][$i];
                $fileSize = $_FILES['photos']['size'][$i];
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                
                // Check file size
                if ($fileSize > $maxPhotoSize) {
                    $errors[] = "Photo '$originalName' exceeds the maximum size limit of 2MB.";
                }
                
                // Check if it's actually an image
                $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($extension, $allowedImageExtensions)) {
                    $errors[] = "File '$originalName' is not a valid image format. Allowed formats: JPG, JPEG, PNG, GIF.";
                }
            } elseif ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                // Handle upload errors
                $errors[] = "Error uploading photo '$originalName': " . uploadErrorMessage($_FILES['photos']['error'][$i]);
            }
        }
    }
    
    return $errors;
}

/**
 * Validate video uploads
 * 
 * @return array Array of error messages
 */
function validateVideoUploads() {
    $errors = [];
    $maxVideoSize = 10 * 1024 * 1024; // 10MB in bytes
    $allowedVideoTypes = ['video/mp4', 'video/quicktime']; // MP4 and MOV formats
    $allowedVideoExtensions = ['mp4', 'mov'];
    
    // Check for item-specific videos (videos_0, videos_1, etc.)
    foreach ($_FILES as $fieldName => $fileData) {
        if (strpos($fieldName, 'videos_') === 0 && !empty($fileData['name'][0])) {
            $fileCount = count($fileData['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($fileData['error'][$i] === UPLOAD_ERR_OK) {
                    $originalName = $fileData['name'][$i];
                    $fileSize = $fileData['size'][$i];
                    $fileType = $fileData['type'][$i];
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    
                    // Check file size
                    if ($fileSize > $maxVideoSize) {
                        $errors[] = "Video '$originalName' exceeds the maximum size limit of 10MB.";
                    }
                    
                    // Check file type
                    if (!in_array($fileType, $allowedVideoTypes) && !in_array($extension, $allowedVideoExtensions)) {
                        $errors[] = "Video '$originalName' is not in an allowed format (MP4 or MOV).";
                    }
                } elseif ($fileData['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    // Handle upload errors
                    $originalName = $fileData['name'][$i] ?? 'Unknown file';
                    $errors[] = "Error uploading video '$originalName': " . uploadErrorMessage($fileData['error'][$i]);
                }
            }
        }
    }
    
    // Check for general videos (backward compatibility)
    if (!empty($_FILES['videos']['name'][0])) {
        $fileCount = count($_FILES['videos']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_OK) {
                $originalName = $_FILES['videos']['name'][$i];
                $fileSize = $_FILES['videos']['size'][$i];
                $fileType = $_FILES['videos']['type'][$i];
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                
                // Check file size
                if ($fileSize > $maxVideoSize) {
                    $errors[] = "Video '$originalName' exceeds the maximum size limit of 10MB.";
                }
                
                // Check file type
                if (!in_array($fileType, $allowedVideoTypes) && !in_array($extension, $allowedVideoExtensions)) {
                    $errors[] = "Video '$originalName' is not in an allowed format (MP4 or MOV).";
                }
            } elseif ($_FILES['videos']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                // Handle upload errors
                $errors[] = "Error uploading video '$originalName': " . uploadErrorMessage($_FILES['videos']['error'][$i]);
            }
        }
    }
    
    return $errors;
}

/**
 * Process photo uploads
 * 
 * @param int $claimId Claim ID
 * @param array $claimItemIds Claim item IDs
 * @param PDO $conn Database connection
 * @return bool True if successful, false otherwise
 */
function processPhotoUploads($claimId, $claimItemIds, $conn) {
    // Check if we have item-specific photos
    $hasItemSpecificPhotos = false;
    foreach ($_FILES as $fieldName => $fileData) {
        if (strpos($fieldName, 'photos_') === 0 && !empty($fileData['name'][0])) {
            $hasItemSpecificPhotos = true;
            break;
        }
    }
    
    // If we have item-specific photos, process them
    if ($hasItemSpecificPhotos) {
        foreach ($_FILES as $fieldName => $fileData) {
            if (strpos($fieldName, 'photos_') === 0 && !empty($fileData['name'][0])) {
                // Extract the item index from the field name (photos_0, photos_1, etc.)
                $itemIndex = substr($fieldName, 7);
                
                // Get the corresponding claim item ID
                $claimItemId = isset($claimItemIds[$itemIndex]) ? $claimItemIds[$itemIndex] : null;
                
                if ($claimItemId) {
                    $uploadDir = '../../uploads/claims/' . $claimId . '/items/' . $claimItemId . '/photos/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            error_log("Failed to create directory: $uploadDir");
                            return false;
                        }
                    }
                    
                    $stmt = $conn->prepare("
                        INSERT INTO claim_media (
                            claim_id, claim_item_id, file_path, file_type, original_filename, file_size
                        ) VALUES (
                            ?, ?, ?, 'photo', ?, ?
                        )
                    ");
                    
                    $fileCount = count($fileData['name']);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($fileData['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpName = $fileData['tmp_name'][$i];
                            $originalName = $fileData['name'][$i];
                            $fileSize = $fileData['size'][$i];
                            
                            // Generate a unique filename
                            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                            $newFileName = uniqid('photo_') . '.' . $extension;
                            $filePath = $uploadDir . $newFileName;
                            
                            // Move the uploaded file
                            if (move_uploaded_file($tmpName, $filePath)) {
                                $relativePath = 'uploads/claims/' . $claimId . '/items/' . $claimItemId . '/photos/' . $newFileName;
                                
                                $stmt->execute([
                                    $claimId, $claimItemId, $relativePath, $originalName, $fileSize
                                ]);
                            } else {
                                error_log("Failed to move uploaded file from $tmpName to $filePath");
                                return false;
                            }
                        }
                    }
                }
            }
        }
    } else if (!empty($_FILES['photos']['name'][0])) {
        // Backward compatibility: process photos the old way
        $uploadDir = '../../uploads/claims/' . $claimId . '/photos/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return false;
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO claim_media (
                claim_id, claim_item_id, file_path, file_type, original_filename, file_size
            ) VALUES (
                ?, ?, ?, 'photo', ?, ?
            )
        ");
        
        $fileCount = count($_FILES['photos']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['photos']['tmp_name'][$i];
                $originalName = $_FILES['photos']['name'][$i];
                $fileSize = $_FILES['photos']['size'][$i];
                
                // Generate a unique filename
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $newFileName = uniqid('photo_') . '.' . $extension;
                $filePath = $uploadDir . $newFileName;
                
                // Move the uploaded file
                if (move_uploaded_file($tmpName, $filePath)) {
                    $relativePath = 'uploads/claims/' . $claimId . '/photos/' . $newFileName;
                    
                    // Use the first claim item ID for backward compatibility
                    $firstClaimItemId = !empty($claimItemIds) ? reset($claimItemIds) : null;
                    
                    $stmt->execute([
                        $claimId, $firstClaimItemId, $relativePath, $originalName, $fileSize
                    ]);
                } else {
                    return false;
                }
            }
        }
    }
    
    return true;
}

/**
 * Process video uploads
 * 
 * @param int $claimId Claim ID
 * @param array $claimItemIds Claim item IDs
 * @param PDO $conn Database connection
 * @return bool True if successful, false otherwise
 */
function processVideoUploads($claimId, $claimItemIds, $conn) {
    // Check if we have item-specific videos
    $hasItemSpecificVideos = false;
    foreach ($_FILES as $fieldName => $fileData) {
        if (strpos($fieldName, 'videos_') === 0 && !empty($fileData['name'][0])) {
            $hasItemSpecificVideos = true;
            break;
        }
    }
    
    // If we have item-specific videos, process them
    if ($hasItemSpecificVideos) {
        foreach ($_FILES as $fieldName => $fileData) {
            if (strpos($fieldName, 'videos_') === 0 && !empty($fileData['name'][0])) {
                // Extract the item index from the field name (videos_0, videos_1, etc.)
                $itemIndex = substr($fieldName, 7);
                
                // Get the corresponding claim item ID
                $claimItemId = isset($claimItemIds[$itemIndex]) ? $claimItemIds[$itemIndex] : null;
                
                if ($claimItemId) {
                    $uploadDir = '../../uploads/claims/' . $claimId . '/items/' . $claimItemId . '/videos/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            error_log("Failed to create directory: $uploadDir");
                            return false;
                        }
                    }
                    
                    $stmt = $conn->prepare("
                        INSERT INTO claim_media (
                            claim_id, claim_item_id, file_path, file_type, original_filename, file_size
                        ) VALUES (
                            ?, ?, ?, 'video', ?, ?
                        )
                    ");
                    
                    $fileCount = count($fileData['name']);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($fileData['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpName = $fileData['tmp_name'][$i];
                            $originalName = $fileData['name'][$i];
                            $fileSize = $fileData['size'][$i];
                            
                            // Generate a unique filename
                            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                            $newFileName = uniqid('video_') . '.' . $extension;
                            $filePath = $uploadDir . $newFileName;
                            
                            // Move the uploaded file
                            if (move_uploaded_file($tmpName, $filePath)) {
                                $relativePath = 'uploads/claims/' . $claimId . '/items/' . $claimItemId . '/videos/' . $newFileName;
                                
                                $stmt->execute([
                                    $claimId, $claimItemId, $relativePath, $originalName, $fileSize
                                ]);
                            } else {
                                error_log("Failed to move uploaded file from $tmpName to $filePath");
                                return false;
                            }
                        }
                    }
                }
            }
        }
    } else if (!empty($_FILES['videos']['name'][0])) {
        // Backward compatibility: process videos the old way
        $uploadDir = '../../uploads/claims/' . $claimId . '/videos/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return false;
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO claim_media (
                claim_id, claim_item_id, file_path, file_type, original_filename, file_size
            ) VALUES (
                ?, ?, ?, 'video', ?, ?
            )
        ");
        
        $fileCount = count($_FILES['videos']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['videos']['tmp_name'][$i];
                $originalName = $_FILES['videos']['name'][$i];
                $fileSize = $_FILES['videos']['size'][$i];
                
                // Generate a unique filename
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $newFileName = uniqid('video_') . '.' . $extension;
                $filePath = $uploadDir . $newFileName;
                
                // Move the uploaded file
                if (move_uploaded_file($tmpName, $filePath)) {
                    $relativePath = 'uploads/claims/' . $claimId . '/videos/' . $newFileName;
                    
                    // Use the first claim item ID for backward compatibility
                    $firstClaimItemId = !empty($claimItemIds) ? reset($claimItemIds) : null;
                    
                    $stmt->execute([
                        $claimId, $firstClaimItemId, $relativePath, $originalName, $fileSize
                    ]);
                } else {
                    return false;
                }
            }
        }
    }
    
    return true;
}

/**
 * Get upload error message
 * 
 * @param int $errorCode PHP upload error code
 * @return string Error message
 */
function uploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded.";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk.";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload.";
        default:
            return "Unknown upload error.";
    }
}
?>
