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
    
    $categoryId = (int)$_POST['category_id'];
    $description = trim($_POST['description']);
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
    
    if (empty($categoryId)) {
        $errors[] = 'Claim category is required.';
    }
    
    if (empty($description)) {
        $errors[] = 'Claim description is required.';
    }
    
    if (empty($sku)) {
        $errors[] = 'Product SKU is required.';
    }
    
    // Check if a claim already exists for this order ID and SKU
    $stmt = $conn->prepare("SELECT id FROM claims WHERE order_id = ? AND sku = ?");
    $stmt->execute([$orderId, $sku]);
    
    if ($stmt->rowCount() > 0) {
        $errors[] = "A claim for this order and product already exists. Please check existing claims.";
    }
    
    // Validate photo uploads
    $photoErrors = validatePhotoUploads();
    $errors = array_merge($errors, $photoErrors);
    
    // Validate video uploads
    $videoErrors = validateVideoUploads();
    $errors = array_merge($errors, $videoErrors);
    
    // If there are validation errors, return them
    if (!empty($errors)) {
        error_log("Validation errors: " . json_encode($errors));
        echo json_encode([
            'success' => false,
            'message' => 'Please fix the following errors:',
            'errors' => $errors
        ]);
        exit;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Insert claim record
    $stmt = $conn->prepare("
        INSERT INTO claims (
            order_id, customer_name, customer_email, customer_phone, 
            category_id, sku, product_type, delivery_date, description, status, created_by
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?
        )
    ");
    
    $stmt->execute([
        $orderId, $customerName, $customerEmail, $customerPhone, 
        $categoryId, $sku, $productType, $deliveryDate, $description, $userId
    ]);
    
    $claimId = $conn->lastInsertId();
    
    // Insert claim items
    if (isset($_POST['item_sku']) && is_array($_POST['item_sku'])) {
        $stmt = $conn->prepare("
            INSERT INTO claim_items (
                claim_id, sku, product_name
            ) VALUES (
                ?, ?, ?
            )
        ");
        
        foreach ($_POST['item_sku'] as $key => $sku) {
            if (!empty($sku)) {
                $productName = $_POST['item_product_name'][$key] ?? '';
                
                $stmt->execute([
                    $claimId, $sku, $productName
                ]);
            }
        }
    }
    
    // Process photo uploads
    $photoUploadSuccess = processPhotoUploads($claimId, $conn);
    if (!$photoUploadSuccess) {
        // Rollback transaction if photo uploads fail
        $conn->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error processing photo uploads. Claim was not created.'
        ]);
        exit;
    }
    
    // Process video uploads
    $videoUploadSuccess = processVideoUploads($claimId, $conn);
    if (!$videoUploadSuccess) {
        // Rollback transaction if video uploads fail
        $conn->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error processing video uploads. Claim was not created.'
        ]);
        exit;
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
        'created_at' => date('Y-m-d H:i:s')
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
    
    if (!empty($_FILES['photos']['name'][0])) {
        $fileCount = count($_FILES['photos']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                $originalName = $_FILES['photos']['name'][$i];
                $fileSize = $_FILES['photos']['size'][$i];
                
                // Check file size
                if ($fileSize > $maxPhotoSize) {
                    $errors[] = "Photo '$originalName' exceeds the maximum size limit of 2MB.";
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
    
    if (!empty($_FILES['videos']['name'][0])) {
        $fileCount = count($_FILES['videos']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_OK) {
                $originalName = $_FILES['videos']['name'][$i];
                $fileSize = $_FILES['videos']['size'][$i];
                $fileType = $_FILES['videos']['type'][$i];
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                
                // Check file size
                if ($fileSize > $maxVideoSize) {
                    $errors[] = "Video '$originalName' exceeds the maximum size limit of 10MB.";
                }
                
                // Check file type
                if (!in_array($fileType, $allowedVideoTypes)) {
                    $errors[] = "Video '$originalName' is not in an allowed format (MP4 or MOV).";
                }
                
                // Check file extension
                if (!in_array(strtolower($extension), $allowedVideoExtensions)) {
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
 * @param PDO $conn Database connection
 * @return bool True if successful, false otherwise
 */
function processPhotoUploads($claimId, $conn) {
    if (!empty($_FILES['photos']['name'][0])) {
        $uploadDir = '../../uploads/claims/' . $claimId . '/photos/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return false;
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO claim_media (
                claim_id, file_path, file_type, original_filename, file_size
            ) VALUES (
                ?, ?, 'photo', ?, ?
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
                    
                    $stmt->execute([
                        $claimId, $relativePath, $originalName, $fileSize
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
 * @param PDO $conn Database connection
 * @return bool True if successful, false otherwise
 */
function processVideoUploads($claimId, $conn) {
    if (!empty($_FILES['videos']['name'][0])) {
        $uploadDir = '../../uploads/claims/' . $claimId . '/videos/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return false;
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO claim_media (
                claim_id, file_path, file_type, original_filename, file_size
            ) VALUES (
                ?, ?, 'video', ?, ?
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
                    
                    $stmt->execute([
                        $claimId, $relativePath, $originalName, $fileSize
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
