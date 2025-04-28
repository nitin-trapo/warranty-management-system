<?php
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

// Check if the claims and claim_categories tables exist, if not create them
try {
    // Check if claim_categories table exists
    $tableExists = false;
    $stmt = $conn->query("SHOW TABLES LIKE 'claim_categories'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
    }
    
    // Create claim_categories table if it doesn't exist
    if (!$tableExists) {
        $createTableSQL = "
            CREATE TABLE `claim_categories` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `description` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->exec($createTableSQL);
        
        // Insert default categories
        $defaultCategories = [
            ['name' => 'Hardware Defect', 'description' => 'Issues related to physical hardware components'],
            ['name' => 'Software Issue', 'description' => 'Problems with software, firmware or operating system'],
            ['name' => 'Accidental Damage', 'description' => 'Damage caused by accidents like drops or spills'],
            ['name' => 'Wear and Tear', 'description' => 'Normal degradation from regular use over time'],
            ['name' => 'DOA (Dead on Arrival)', 'description' => 'Product was non-functional when first received']
        ];
        
        $insertSQL = "INSERT INTO `claim_categories` (`name`, `description`) VALUES (?, ?)";
        $stmt = $conn->prepare($insertSQL);
        
        foreach ($defaultCategories as $category) {
            $stmt->execute([$category['name'], $category['description']]);
        }
    }
    
    // Check if claims table exists
    $tableExists = false;
    $stmt = $conn->query("SHOW TABLES LIKE 'claims'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
    }
    
    // Create claims table if it doesn't exist
    if (!$tableExists) {
        $createTableSQL = "
            CREATE TABLE `claims` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` varchar(50) NOT NULL,
                `customer_name` varchar(100) NOT NULL,
                `customer_email` varchar(100) NOT NULL,
                `customer_phone` varchar(20) DEFAULT NULL,
                `delivery_date` date NOT NULL,
                `status` enum('new','in_progress','on_hold','approved','rejected') NOT NULL DEFAULT 'new',
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
                `created_by` int(11) NOT NULL,
                `assigned_to` int(11) DEFAULT NULL,
                `claim_number` VARCHAR(50) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->exec($createTableSQL);
    }
    
    // Check if claim_items table exists
    $tableExists = false;
    $stmt = $conn->query("SHOW TABLES LIKE 'claim_items'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
    }
    
    // Create claim_items table if it doesn't exist
    if (!$tableExists) {
        $createTableSQL = "
            CREATE TABLE `claim_items` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `claim_id` int(11) NOT NULL,
                `sku` varchar(50) NOT NULL,
                `product_name` varchar(255) NOT NULL,
                `product_type` varchar(50) NOT NULL,
                `description` text NOT NULL,
                `category_id` int(11) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `claim_id` (`claim_id`),
                CONSTRAINT `claim_items_ibfk_1` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->exec($createTableSQL);
    }
    
    // Check if claim_media table exists
    $tableExists = false;
    $stmt = $conn->query("SHOW TABLES LIKE 'claim_media'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
    }
    
    // Create claim_media table if it doesn't exist
    if (!$tableExists) {
        $createTableSQL = "
            CREATE TABLE `claim_media` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `claim_id` int(11) NOT NULL,
                `file_path` varchar(255) NOT NULL,
                `file_type` enum('photo','video') NOT NULL,
                `original_filename` varchar(255) NOT NULL,
                `file_size` int(11) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `claim_id` (`claim_id`),
                CONSTRAINT `claim_media_ibfk_1` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->exec($createTableSQL);
    }
    
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
                `note` text NOT NULL,
                `status_changed` enum('yes','no') NOT NULL DEFAULT 'no',
                `old_status` varchar(50) DEFAULT NULL,
                `new_status` varchar(50) DEFAULT NULL,
                `created_by` int(11) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `claim_id` (`claim_id`),
                CONSTRAINT `claim_notes_ibfk_1` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->exec($createTableSQL);
    }
    
    // Create uploads directory if it doesn't exist
    $uploadsDir = '../uploads/claims';
    if (!file_exists($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    // Check if claim_number column exists in claims table, if not add it
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM `claims` LIKE 'claim_number'");
        if ($stmt->rowCount() === 0) {
            // Add claim_number column
            $alterTableSQL = "ALTER TABLE `claims` ADD COLUMN `claim_number` VARCHAR(50) NULL AFTER `created_by`";
            $conn->exec($alterTableSQL);
        }
    } catch (PDOException $e) {
        // Log error
        error_log("Error checking/adding claim_number column: " . $e->getMessage());
    }
} catch (PDOException $e) {
    // Log error
    error_log("Error creating tables: " . $e->getMessage());
}

// Initialize variables
$orderDetails = null;
$orderError = null;
$successMessage = null;
$errorMessage = null;

// Process order lookup
if (isset($_POST['action']) && $_POST['action'] === 'lookup_order') {
    $orderId = trim($_POST['order_id']);
    
    if (!empty($orderId)) {
        // Get order details from ODIN API
        $orderData = getOrderDetails($orderId);
        
        if ($orderData) {
            // Format order details for display
            $orderDetails = formatOrderDetails($orderData);
        } else {
            $orderError = "Order not found or API error occurred. Please check the order ID and try again.";
        }
    } else {
        $orderError = "Please enter a valid order ID.";
    }
}

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

// Get claims from database with all associated items
$claims = [];

// Use the same query for both admins and CS agents to show all claims
$claimsQuery = "SELECT c.id, c.order_id, c.customer_name, c.customer_email, c.status, 
                   c.created_at, c.updated_at, c.created_by, c.claim_number, c.assigned_to,
                   cc.name as category_name, cc.sla_days 
            FROM claims c
            LEFT JOIN claim_items ci ON c.id = ci.claim_id
            LEFT JOIN claim_categories cc ON ci.category_id = cc.id
            GROUP BY c.id
            ORDER BY c.created_at DESC";
$stmt = $conn->prepare($claimsQuery);
$stmt->execute();
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get items for each claim
$claimItems = [];
if (!empty($claims)) {
    $claimIds = array_column($claims, 'id');
    $placeholders = str_repeat('?,', count($claimIds) - 1) . '?';
    
    $itemsQuery = "SELECT claim_id, sku, product_name, product_type, description, category_id
                  FROM claim_items 
                  WHERE claim_id IN ($placeholders)
                  ORDER BY claim_id, id";
    $stmt = $conn->prepare($itemsQuery);
    $stmt->execute($claimIds);
    
    while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $claimItems[$item['claim_id']][] = $item;
    }
}

// Get categories for dropdown
$categoriesQuery = "SELECT id, name FROM claim_categories ORDER BY name";
$stmt = $conn->prepare($categoriesQuery);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX request for order lookup
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Set content type to JSON
    header('Content-Type: application/json');
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($input['order_id']) || empty($input['order_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Order ID is required'
        ]);
        exit;
    }
    
    $orderId = trim($input['order_id']);
    
    // Create log directory if it doesn't exist
    $logDir = '../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log file path
    $logFile = $logDir . '/api_requests.log';
    
    // Log the request
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Order lookup request: " . $orderId . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    try {
        // Get ODIN API authentication data
        $authData = getOdinApiAuth();
        
        if (!$authData) {
            // Log authentication failure
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] ODIN API authentication failed\n";
            file_put_contents($logFile, $errorMessage, FILE_APPEND);
            
            echo json_encode([
                'success' => false,
                'message' => 'Failed to authenticate with ODIN API'
            ]);
            exit;
        }
        
        // Get order details
        $orderDetails = getOrderDetails($orderId);
        
        // Log the API response
        $responseLog = "[" . date('Y-m-d H:i:s') . "] ODIN API response for order {$orderId}:\n";
        $responseLog .= json_encode($orderDetails, JSON_PRETTY_PRINT) . "\n\n";
        file_put_contents($logFile, $responseLog, FILE_APPEND);
        
        // Check if order details were retrieved successfully
        if (!$orderDetails || isset($orderDetails['error'])) {
            $errorMessage = isset($orderDetails['error']) ? $orderDetails['error'] : 'Failed to retrieve order details';
            
            // Log the error
            $errorLog = "[" . date('Y-m-d H:i:s') . "] Order lookup error: {$errorMessage}\n";
            file_put_contents($logFile, $errorLog, FILE_APPEND);
            
            echo json_encode([
                'success' => false,
                'message' => $errorMessage,
                'raw_response' => json_encode($orderDetails)
            ]);
            exit;
        }
        
        // Debug log the structure
        $debugLog = "[" . date('Y-m-d H:i:s') . "] Order details structure:\n";
        $debugLog .= "Keys: " . implode(", ", array_keys($orderDetails)) . "\n";
        $debugLog .= "Items count: " . (isset($orderDetails['items']) ? count($orderDetails['items']) : 'none') . "\n\n";
        file_put_contents($logFile, $debugLog, FILE_APPEND);
        
        // Generate HTML for item selection
        $itemsHtml = '';
        if (!empty($orderDetails['items'])) {
            $itemsHtml .= '<div class="form-group"><label>Select Items for Warranty Claim:</label>';
            $itemsHtml .= '<div class="item-selection">';
            
            foreach ($orderDetails['items'] as $index => $item) {
                $sku = isset($item['sku']) ? $item['sku'] : '';
                $productName = isset($item['product_name']) ? $item['product_name'] : '';
                $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
                
                // Determine product type and warranty period based on SKU
                $productType = determineProductType($sku);
                $warrantyPeriod = getWarrantyPeriod($productType);
                $warrantyEndDate = calculateWarrantyEndDate($orderDetails['order_date'], $warrantyPeriod);
                $isWarrantyValid = isWarrantyValid($warrantyEndDate);
                
                $disabled = !$isWarrantyValid ? 'disabled' : '';
                $warningClass = !$isWarrantyValid ? 'text-danger' : 'text-success';
                $warningText = !$isWarrantyValid ? 'Warranty Expired' : 'Warranty Valid';
                
                $itemsHtml .= '<div class="form-check mb-2 p-2 border rounded item-container">';
                $itemsHtml .= '<input type="checkbox" class="form-check-input item-checkbox" name="claim_items[]" value="' . $index . '" id="item_' . $index . '" ' . $disabled . ' onchange="updateSelectedItems()">';
                $itemsHtml .= '<label class="form-check-label" for="item_' . $index . '">';
                $itemsHtml .= '<strong>' . htmlspecialchars($productName) . '</strong> (SKU: ' . htmlspecialchars($sku) . ')';
                $itemsHtml .= '</label>';
                $itemsHtml .= '<div class="item-details">';
                $itemsHtml .= '<small>Product Type: ' . htmlspecialchars($productType) . '</small><br>';
                $itemsHtml .= '<small>Warranty Period: ' . $warrantyPeriod . ' months</small><br>';
                $itemsHtml .= '<small>Warranty End Date: ' . $warrantyEndDate . '</small><br>';
                $itemsHtml .= '<small class="' . $warningClass . '"><strong>' . $warningText . '</strong></small>';
                $itemsHtml .= '</div>';
                $itemsHtml .= '</div>';
            }
            
            $itemsHtml .= '</div></div>';
        } else {
            // Log if no items found
            $noItemsLog = "[" . date('Y-m-d H:i:s') . "] No items found in order details\n";
            file_put_contents($logFile, $noItemsLog, FILE_APPEND);
        }
        
        // Process order details for the response
        $processedOrder = [
            'order_id' => $orderId,
            'order_date' => isset($orderDetails['order_date']) ? $orderDetails['order_date'] : date('Y-m-d'),
            'customer_name' => isset($orderDetails['customer_name']) ? $orderDetails['customer_name'] : '',
            'customer_email' => isset($orderDetails['customer_email']) ? $orderDetails['customer_email'] : '',
            'customer_phone' => isset($orderDetails['customer_phone']) ? $orderDetails['customer_phone'] : '',
            'items' => []
        ];
        
        // Process order items
        if (isset($orderDetails['items']) && is_array($orderDetails['items'])) {
            foreach ($orderDetails['items'] as $item) {
                $processedOrder['items'][] = [
                    'sku' => isset($item['sku']) ? $item['sku'] : '',
                    'product_name' => isset($item['product_name']) ? $item['product_name'] : '',
                    'quantity' => isset($item['quantity']) ? $item['quantity'] : 1
                ];
            }
        }
        
        // Log the processed order
        $processedLog = "[" . date('Y-m-d H:i:s') . "] Processed order details:\n";
        $processedLog .= json_encode($processedOrder, JSON_PRETTY_PRINT) . "\n\n";
        file_put_contents($logFile, $processedLog, FILE_APPEND);
        
        // Return successful response with order details and item selection HTML
        echo json_encode([
            'success' => true,
            'message' => 'Order details retrieved successfully',
            'order' => $processedOrder,
            'items_html' => $itemsHtml,
            'raw_response' => json_encode($orderDetails)
        ]);
        exit;
        
    } catch (Exception $e) {
        // Log the exception
        $errorMessage = "[" . date('Y-m-d H:i:s') . "] Exception: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $errorMessage, FILE_APPEND);
        
        // Return error response
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred: ' . $e->getMessage()
        ]);
        exit;
    }
}

?>

<div class="page-title">
    <h1>Claims Management</h1>
    <div class="button-container">
        <button type="button" class="btn btn-primary add-claim-btn" data-bs-toggle="modal" data-bs-target="#addClaimModal">
            <i class="fas fa-plus me-1"></i> New Claim
        </button>
    </div>
</div>

<?php if (isset($successMessage)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($successMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($errorMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Claims List -->
<div class="card mb-4">
    <div class="card-header py-3">
        <h6 class="mb-0">Warranty Claims</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="claimsTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                <thead class="table-light">
                    <tr>
                        <th>Claim #</th>
                        <th>Order ID</th>
                        <th>Products & Categories</th>
                        <th>SLA</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Assignment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($claims)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-3">No claims found.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($claims as $claim): ?>
                    <tr data-claim-id="<?php echo $claim['id']; ?>">
                        <td data-sort="<?php echo $claim['id']; ?>">
                            <?php if (!empty($claim['claim_number'])): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($claim['claim_number']); ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No Number</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($claim['order_id']); ?></td>
                        <td>
                            <?php if (isset($claimItems[$claim['id']])): ?>
                                <button type="button" class="btn btn-sm btn-primary view-products-btn" 
                                        data-bs-toggle="tooltip" 
                                        data-bs-html="true"
                                        data-bs-title="<?php 
                                            $tooltipContent = '<div class=\'product-tooltip\'>';
                                            foreach ($claimItems[$claim['id']] as $item) {
                                                // Get category name for this item
                                                $catStmt = $conn->prepare("SELECT name FROM claim_categories WHERE id = ?");
                                                $catStmt->execute([$item['category_id']]);
                                                $categoryName = $catStmt->fetchColumn() ?: 'N/A';
                                                
                                                $tooltipContent .= '<div class=\'product-item\'>';
                                                $tooltipContent .= '<strong>' . htmlspecialchars($item['sku']) . '</strong><br>';
                                                $tooltipContent .= '<span>' . htmlspecialchars($item['product_name']) . '</span><br>';
                                                $tooltipContent .= '<span class=\'badge bg-secondary\'>' . htmlspecialchars($categoryName) . '</span>';
                                                $tooltipContent .= '</div>';
                                                $tooltipContent .= '<hr class=\'my-1\'>';
                                            }
                                            $tooltipContent = rtrim($tooltipContent, '<hr class=\'my-1\'>');
                                            $tooltipContent .= '</div>';
                                            echo htmlspecialchars($tooltipContent);
                                        ?>">
                                    <i class="fas fa-box me-1"></i> <?php echo count($claimItems[$claim['id']]); ?>
                                </button>
                            <?php else: ?>
                                <span class="badge bg-light text-dark">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            // Calculate SLA deadline
                            $createdDate = new DateTime($claim['created_at']);
                            $slaDays = (int)$claim['sla_days'];
                            $deadline = clone $createdDate;
                            $deadline->modify("+{$slaDays} days");
                            
                            // Get current date
                            $currentDate = new DateTime();
                            
                            // Check if claim is resolved (rejected or resolved status)
                            $isResolved = in_array($claim['status'], ['rejected', 'resolved']);
                            
                            if ($isResolved) {
                                echo '<span class="badge bg-success">Resolved</span>';
                            } else {
                                // Calculate days remaining
                                $interval = $currentDate->diff($deadline);
                                $daysRemaining = $interval->invert ? -$interval->days : $interval->days;
                                
                                if ($daysRemaining < 0) {
                                    // SLA breached
                                    echo '<span class="badge bg-danger">Breached (' . abs($daysRemaining) . ' days)</span>';
                                } else if ($daysRemaining == 0) {
                                    // Due today
                                    echo '<span class="badge bg-warning">Due Today</span>';
                                } else {
                                    // Within SLA
                                    echo '<span class="badge bg-info">' . $daysRemaining . ' days left</span>';
                                }
                                
                                // Display deadline date
                                echo '<br><small class="text-muted">Due: ' . $deadline->format('M d, Y') . '</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                echo match($claim['status']) {
                                    'new' => 'info',
                                    'in_progress' => 'primary',
                                    'on_hold' => 'warning',
                                    'approved' => 'primary',
                                    'rejected' => 'danger',
                                    'resolved' => 'success',
                                    default => 'secondary'
                                };
                            ?>">
                                <?php 
                                if ($claim['status'] === 'in_progress' || $claim['status'] === 'approved') {
                                    echo 'In Progress';
                                } else if ($claim['status'] === 'resolved') {
                                    echo 'Resolved';
                                } else {
                                    echo ucfirst(str_replace('_', ' ', $claim['status']));
                                }
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="small">
                                <div><?php echo date('M d, Y', strtotime($claim['created_at'])); ?></div>
                                <div class="text-muted"><?php echo date('h:i A', strtotime($claim['created_at'])); ?></div>
                            </div>
                        </td>
                        <td>
                            <?php if ($claim['assigned_to']): ?>
                                <?php
                                // Get assigned user's name
                                $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                                $stmt->execute([$claim['assigned_to']]);
                                $assignedUser = $stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($assignedUser['first_name'] . ' ' . $assignedUser['last_name']); ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view_claim.php?id=<?php echo $claim['id']; ?>" class="btn btn-outline-primary" title="View Claim">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_claim.php?id=<?php echo $claim['id']; ?>" class="btn btn-outline-secondary" title="Edit Claim">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                <?php if ($claim['status'] === 'in_progress' || $claim['status'] === 'approved'): ?>
                                <button type="button" class="btn btn-outline-success mark-resolved-btn" 
                                        data-id="<?php echo $claim['id']; ?>"
                                        data-status="<?php echo $claim['status']; ?>"
                                        title="Mark as Resolved">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-info quick-assign-claim" 
                                        data-id="<?php echo $claim['id']; ?>"
                                        data-claim-number="<?php echo !empty($claim['claim_number']) ? htmlspecialchars($claim['claim_number']) : ''; ?>"
                                        data-order="<?php echo htmlspecialchars($claim['order_id']); ?>"
                                        title="Assign Claim">
                                    <i class="fas fa-user-check"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger delete-claim" 
                                        data-id="<?php echo $claim['id']; ?>"
                                        data-order="<?php echo htmlspecialchars($claim['order_id']); ?>"
                                        title="Delete Claim">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Mark Resolved Modal -->
<div class="modal fade" id="markResolvedModal" tabindex="-1" aria-labelledby="markResolvedModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="markResolvedModalLabel">Mark Claim as Resolved</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="markResolvedForm">
                    <input type="hidden" id="resolved_claim_id" name="claim_id">
                    <input type="hidden" id="resolved_current_status" name="current_status">
                    <div class="mb-3">
                        <label for="resolution_note" class="form-label">Resolution Note</label>
                        <textarea class="form-control" id="resolution_note" name="note" rows="4" required placeholder="Please provide details about the resolution..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmResolveBtn">
                    <i class="fas fa-check-circle me-1"></i> Mark as Resolved
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Claim Modal -->
<div class="modal fade" id="deleteClaimModal" tabindex="-1" aria-labelledby="deleteClaimModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteClaimModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the claim for order <strong id="delete_order_id"></strong>?</p>
                <p class="text-danger">This action cannot be undone. All claim details, photos, and videos will be permanently deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Claim</a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Assign Claim Modal -->
<div class="modal fade" id="quickAssignModal" tabindex="-1" aria-labelledby="quickAssignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickAssignModalLabel">Assign Claim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="quickAssignAlert" class="alert" style="display: none;"></div>
                
                <form id="quickAssignForm">
                    <input type="hidden" id="quick_claim_id" name="claim_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Claim Information</label>
                        <div class="form-control bg-light" id="claimInfoDisplay"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_agent_id" class="form-label">Select User</label>
                        <select class="form-select" id="quick_agent_id" name="agent_id" required>
                            <option value="">-- Select User --</option>
                            <?php
                            // Get all active users
                            try {
                                $stmt = $conn->prepare("
                                    SELECT id, username, first_name, last_name, role
                                    FROM users
                                    WHERE status = 'active'
                                    ORDER BY role, first_name, last_name
                                ");
                                $stmt->execute();
                                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Group users by role
                                $usersByRole = [];
                                foreach ($users as $user) {
                                    $roleName = ucfirst(str_replace('_', ' ', $user['role']));
                                    if (!isset($usersByRole[$roleName])) {
                                        $usersByRole[$roleName] = [];
                                    }
                                    $usersByRole[$roleName][] = $user;
                                }
                                
                                // Output users grouped by role
                                foreach ($usersByRole as $roleName => $roleUsers) {
                                    echo '<optgroup label="' . htmlspecialchars($roleName) . '">';
                                    
                                    foreach ($roleUsers as $user) {
                                        // Format the role for display
                                        $roleDisplay = ucfirst(str_replace('_', ' ', $user['role']));
                                        
                                        // Add a badge-like indicator for approvers
                                        $roleBadge = '';
                                        if ($user['role'] === 'approver') {
                                            $roleBadge = ' <span style="color: #ff7700; font-weight: bold;">[Approver]</span>';
                                        }
                                        
                                        echo '<option value="' . $user['id'] . '">' . 
                                            htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')') . 
                                            $roleBadge .
                                            '</option>';
                                    }
                                    
                                    echo '</optgroup>';
                                }
                            } catch (PDOException $e) {
                                // Log error
                                error_log("Error retrieving users: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_notes" class="form-label">Assignment Notes (Optional)</label>
                        <textarea class="form-control" id="quick_notes" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmAssignBtn">Assign Claim</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Claim Modal -->
<div class="modal fade" id="addClaimModal" tabindex="-1" aria-labelledby="addClaimModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addClaimModalLabel">Create New Warranty Claim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                <!-- Order Lookup Form -->
                <div id="orderLookupSection">
                    <form id="orderLookupForm">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Enter the order ID to look up and create a warranty claim.
                        </div>
                        
                        <div id="orderLookupError" class="alert alert-danger" style="display:none;"></div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="order_id_lookup" class="form-label">Order ID</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="order_id_lookup" name="order_id" required 
                                           placeholder="Enter order ID (e.g., TMR-O332590)">
                                    <button type="button" id="lookupOrderBtn" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i> <span id="lookupBtnText">Lookup</span>
                                    </button>
                                </div>
                                <small class="text-muted">Example: TMR-O332590</small>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div id="orderDetailsDiv" style="display:none;">
                    <div class="alert alert-success mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Order Found: <span id="order_id_display"></span></h6>
                                <p class="mb-0 small">Order Date: <span id="order_date"></span></p>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="changeOrderBtn">
                                Change Order
                            </button>
                        </div>
                    </div>
                    
                    <!-- Customer Information -->
                    <h6 class="border-bottom pb-2 mb-3">Customer Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Customer Name (Display Only)</label>
                            <p class="form-control-static" id="customer_name_display"></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Email Address (Display Only)</label>
                            <p class="form-control-static" id="customer_email_display"></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Phone Number (Display Only)</label>
                            <p class="form-control-static" id="customer_phone_display"></p>
                        </div>
                    </div>
                    
                    <!-- Customer Information Input Fields -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <label for="customer_name_input" class="form-label fw-bold">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="customer_name" id="customer_name_input" placeholder="Enter customer name" required>
                            <div class="invalid-feedback">Customer name is required.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="customer_email_input" class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="customer_email" id="customer_email_input" placeholder="Enter customer email" required>
                            <div class="invalid-feedback">Customer email is required.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="customer_phone_input" class="form-label fw-bold">Phone Number</label>
                            <input type="text" class="form-control" name="customer_phone" id="customer_phone_input" placeholder="Enter customer phone">
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <h6 class="border-bottom pb-2 mb-3">Order Items</h6>
                    <div id="item_selection" class="mb-4">
                        <!-- Items will be populated here by JavaScript -->
                    </div>
                    
                    <div class="alert alert-warning mb-4" id="noItemsWarning" style="display:none;">
                        <i class="fas fa-exclamation-triangle me-2"></i> Please select at least one item for the warranty claim.
                    </div>
                
                    <!-- Claim Form -->
                    <div id="claim_form_container">
                        <!-- Error Container -->
                        <div id="error_container" class="mb-4" style="display:none;"></div>
                        
                        <form method="POST" action="claims.php" id="claimForm" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="submit_claim">
                            <input type="hidden" name="order_id" id="claim_order_id">
                            <?php include 'includes/claim_form_fields.php'; ?>
                            
                            <!-- Selected Items Container -->
                            <div id="selected_items_container" class="mb-4"></div>
                            
                            <!-- File Upload Errors Container -->
                            <div id="file-upload-errors" class="alert alert-danger mb-3" style="display:none;"></div>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="submitClaimBtn">Submit Claim</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include footer -->
<?php require_once 'includes/footer.php'; ?>

<!-- Include claim submission script -->
<script src="js/claim-submission.js"></script>
<script src="js/file-validation.js"></script>
<script>
    // Set this to true to enable AJAX form submission
    window.useAjaxSubmission = true;
    
    // Pass categories to JavaScript for form generation
    window.claimCategories = <?php echo json_encode($categories); ?>;
    
    // Debug function to log events
    function debugLog(message, data) {
        console.log(`[DEBUG] ${message}`, data || '');
    }
    
    // Global function to update selected items - called directly from checkbox onchange
    function updateSelectedItems() {
        debugLog('updateSelectedItems called');
        
        // Get all checkboxes
        const checkboxes = document.querySelectorAll('.item-checkbox');
        console.log('Total checkboxes found:', checkboxes.length);
        
        // Count checked checkboxes
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        console.log('Checked checkboxes:', checkedCount);
        
        // Update UI based on selection
        const container = document.getElementById('selected_items_container');
        if (!container) {
            console.error('Selected items container not found');
            return;
        }
        
        // Clear container
        container.innerHTML = '';
        
        // If no items selected, show warning
        if (checkedCount === 0) {
            container.innerHTML = '<div class="alert alert-warning">Please select at least one item to configure claim details.</div>';
            return;
        }
        
        // Get selected items
        const selectedItems = Array.from(checkboxes).filter(cb => cb.checked);
        
        // Get categories
        const categories = window.claimCategories || [];
        
        // Get order ID for claim number
        const orderId = document.getElementById('claim_order_id').value;
        const orderNum = orderId.replace(/[^\d]/g, '');
        
        // Create form for each selected item
        selectedItems.forEach(function(item, index) {
            const sku = item.getAttribute('data-sku');
            const productName = item.getAttribute('data-product-name');
            const productType = item.getAttribute('data-product-type');
            
            console.log('Generating form for item:', sku, productName);
            
            // Generate claim number
            const skuPrefix = sku ? sku.substring(0, 4).toUpperCase() : 'ITEM';
            const claimNum = 'CLAIM-' + orderNum + '-' + skuPrefix;
            
            // Create item form
            const itemForm = document.createElement('div');
            itemForm.className = 'item-form bg-light p-3 mb-3 border rounded';
            itemForm.innerHTML = `
                <h5 class="border-bottom pb-2 mb-3 text-primary">Item: ${productName}</h5>
                <input type="hidden" name="item_sku[]" value="${sku}">
                <input type="hidden" name="item_product_name[]" value="${productName}">
                <input type="hidden" name="item_product_type[]" value="${productType}">
                <input type="hidden" name="claim_number[]" value="${claimNum}">
                <input type="hidden" name="selected_item_index[]" value="${item.value}">
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <p><strong>SKU:</strong> ${sku} <span class="mx-3">|</span> <strong>Product Type:</strong> ${productType || 'N/A'}</p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Claim Category</label>
                        <select class="form-select" name="category_id[]" required>
                            <option value="">Select Category</option>
                            ${categories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Description</label>
                        <textarea class="form-control" name="description[]" rows="3" required placeholder="Describe the issue..."></textarea>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Photos</label>
                        <input type="file" class="form-control" name="photos_${index}[]" multiple accept="image/*">
                        <div class="form-text">Max size: 2MB per image</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Videos</label>
                        <input type="file" class="form-control" name="videos_${index}[]" multiple accept="video/mp4,video/quicktime">
                        <div class="form-text">Max size: 10MB (MP4/MOV only)</div>
                    </div>
                </div>
            `;
            
            container.appendChild(itemForm);
        });
        
        // Add a hidden field to indicate items were selected
        const itemSelectedFlag = document.createElement('input');
        itemSelectedFlag.type = 'hidden';
        itemSelectedFlag.name = 'items_selected';
        itemSelectedFlag.value = 'true';
        container.appendChild(itemSelectedFlag);
    }
    
    // Direct implementation of item checkbox handling
    document.addEventListener('DOMContentLoaded', function() {
        debugLog('DOM fully loaded, setting up event handlers');
        
        // Direct submit button handler - this will bypass any other handlers
        const directSubmitBtn = document.getElementById('submitClaimBtn');
        if (directSubmitBtn) {
            debugLog('Setting up direct click handler for submit button');
            directSubmitBtn.onclick = function(e) {
                e.preventDefault();
                debugLog('Submit button clicked via direct handler');
                
                // Check if items are selected
                const selectedItems = document.querySelectorAll('.item-checkbox:checked');
                const itemForms = document.querySelectorAll('.item-form');
                
                debugLog('Item selection check', {
                    'Selected checkboxes': selectedItems.length,
                    'Item forms': itemForms.length
                });
                
                if (selectedItems.length === 0 && itemForms.length === 0) {
                    alert('Please select at least one item for the warranty claim.');
                    return false;
                }
                
                // If items are selected but no forms, generate them
                if (selectedItems.length > 0 && itemForms.length === 0) {
                    updateSelectedItems();
                }
                
                // Validate file uploads
                const MAX_PHOTO_SIZE = 2 * 1024 * 1024; // 2MB
                const MAX_VIDEO_SIZE = 10 * 1024 * 1024; // 10MB
                const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'mov'];
                
                // Get all file inputs
                const photoInputs = document.querySelectorAll('input[type="file"][name^="photos_"]');
                const videoInputs = document.querySelectorAll('input[type="file"][name^="videos_"]');
                
                let errors = [];
                
                // Validate photos
                photoInputs.forEach(input => {
                    if (input.files.length > 0) {
                        for (let i = 0; i < input.files.length; i++) {
                            const file = input.files[i];
                            if (file.size > MAX_PHOTO_SIZE) {
                                errors.push(`Photo "${file.name}" exceeds the maximum size limit of 2MB.`);
                            }
                        }
                    }
                });
                
                // Validate videos
                videoInputs.forEach(input => {
                    if (input.files.length > 0) {
                        for (let i = 0; i < input.files.length; i++) {
                            const file = input.files[i];
                            if (file.size > MAX_VIDEO_SIZE) {
                                errors.push(`Video "${file.name}" exceeds the maximum size limit of 10MB.`);
                            }
                            
                            const extension = file.name.split('.').pop().toLowerCase();
                            if (!ALLOWED_VIDEO_EXTENSIONS.includes(extension)) {
                                errors.push(`Video "${file.name}" is not in an allowed format (MP4/MOV only).`);
                            }
                        }
                    }
                });
                
                // Display errors if any
                if (errors.length > 0) {
                    const errorContainer = document.getElementById('file-upload-errors');
                    errorContainer.innerHTML = '<strong>File Upload Errors:</strong><ul>' + 
                        errors.map(error => `<li>${error}</li>`).join('') + 
                        '</ul>';
                    errorContainer.style.display = 'block';
                    errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
                
                // Clear any previous errors
                const errorContainer = document.getElementById('file-upload-errors');
                errorContainer.style.display = 'none';
                
                // Submit the form via AJAX
                debugLog('All validations passed, submitting form via AJAX');
                
                // Get form and create FormData object
                const form = document.getElementById('claimForm');
                const formData = new FormData(form);
                
                // Ensure required fields are included
                const customerName = document.getElementById('customer_name_input') || document.querySelector('[name="customer_name"]');
                const customerEmail = document.getElementById('customer_email_input') || document.querySelector('[name="customer_email"]');
                const customerPhone = document.getElementById('customer_phone_input') || document.querySelector('[name="customer_phone"]');
                const deliveryDateInput = document.querySelector('[name="delivery_date"]');
                const orderIdInput = document.getElementById('claim_order_id');
                
                debugLog('Form fields found', {
                    customerName: customerName ? 'Found' : 'Not found',
                    customerEmail: customerEmail ? 'Found' : 'Not found',
                    customerPhone: customerPhone ? 'Found' : 'Not found',
                    deliveryDate: deliveryDateInput ? 'Found' : 'Not found',
                    orderId: orderIdInput ? 'Found' : 'Not found'
                });
                
                // Add customer info to formData if not already present
                if (customerName && !formData.has('customer_name')) {
                    formData.append('customer_name', customerName.value || 'Customer');
                } else if (!formData.has('customer_name')) {
                    formData.append('customer_name', 'Customer');
                }
                
                if (customerEmail && !formData.has('customer_email')) {
                    formData.append('customer_email', customerEmail.value || 'customer@example.com');
                } else if (!formData.has('customer_email')) {
                    formData.append('customer_email', 'customer@example.com');
                }
                
                if (customerPhone && !formData.has('customer_phone')) {
                    formData.append('customer_phone', customerPhone.value || '');
                }
                
                // Add delivery date if not already present
                if (deliveryDateInput && !formData.has('delivery_date')) {
                    formData.append('delivery_date', deliveryDateInput.value || '');
                } else if (!formData.has('delivery_date')) {
                    // Use current date as fallback
                    const today = new Date();
                    const dateStr = today.toISOString().split('T')[0];
                    formData.append('delivery_date', dateStr);
                }
                
                // Ensure order_id is included
                if (orderIdInput && !formData.has('order_id')) {
                    formData.append('order_id', orderIdInput.value);
                }
                
                // Add action if not already present
                if (!formData.has('action')) {
                    formData.append('action', 'submit_claim');
                }
                
                // Debug form data
                debugLog('Form data entries:');
                for (let pair of formData.entries()) {
                    debugLog(' - ' + pair[0] + ': ' + pair[1]);
                }
                
                // Show loading state
                const submitBtn = document.getElementById('submitClaimBtn');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
                submitBtn.disabled = true;
                
                // Send AJAX request
                $.ajax({
                    url: 'ajax/submit_claim.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        // Reset button
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                        
                        if (response.success) {
                            // Close modal
                            $('#addClaimModal').modal('hide');
                            
                            // Show success message
                            alert(response.message || 'Claim submitted successfully!');
                            
                            // Reload page to show new claim
                            window.location.reload();
                        } else {
                            // Show error message
                            const errorContainer = document.getElementById('file-upload-errors');
                            
                            // Check if we have detailed errors
                            if (response.errors && Array.isArray(response.errors) && response.errors.length > 0) {
                                // Format detailed errors as a list
                                let errorHtml = '<strong>Error:</strong> ' + (response.message || 'Failed to submit claim.') + '<ul class="mt-2 mb-0">';
                                
                                response.errors.forEach(function(error) {
                                    errorHtml += '<li>' + error + '</li>';
                                });
                                
                                errorHtml += '</ul>';
                                errorContainer.innerHTML = errorHtml;
                            } else {
                                // Simple error message
                                errorContainer.innerHTML = '<strong>Error:</strong> ' + (response.message || 'Failed to submit claim. Please try again.');
                            }
                            
                            errorContainer.style.display = 'block';
                            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    },
                    error: function(xhr, status, error) {
                        // Reset button
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                        
                        // Try to parse response as JSON
                        let errorMessage = 'Failed to submit claim. Please try again.';
                        let errorDetails = [];
                        
                        try {
                            const result = JSON.parse(xhr.responseText);
                            if (result.message) {
                                errorMessage = result.message;
                            }
                            
                            // Check for detailed errors
                            if (result.errors && Array.isArray(result.errors) && result.errors.length > 0) {
                                errorDetails = result.errors;
                            }
                        } catch (e) {
                            // Use default error message
                            if (xhr.status === 404) {
                                errorMessage = 'Submission endpoint not found. Please contact the administrator.';
                            } else if (xhr.status === 500) {
                                errorMessage = 'Server error. Please try again later.';
                            }
                        }
                        
                        // Show error message
                        const errorContainer = document.getElementById('file-upload-errors');
                        
                        if (errorDetails.length > 0) {
                            // Format detailed errors as a list
                            let errorHtml = '<strong>Error:</strong> ' + errorMessage + '<ul class="mt-2 mb-0">';
                            
                            errorDetails.forEach(function(error) {
                                errorHtml += '<li>' + error + '</li>';
                            });
                            
                            errorHtml += '</ul>';
                            errorContainer.innerHTML = errorHtml;
                        } else {
                            // Simple error message
                            errorContainer.innerHTML = '<strong>Error:</strong> ' + errorMessage;
                        }
                        
                        errorContainer.style.display = 'block';
                        errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
                
                return false;
            };
        }
        
        // Add event delegation for checkbox clicks
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('item-checkbox')) {
                console.log('Checkbox clicked:', e.target.id);
                
                // Highlight selected item
                const card = e.target.closest('.card');
                if (card) {
                    if (e.target.checked) {
                        card.classList.add('border-primary');
                    } else {
                        card.classList.remove('border-primary');
                    }
                }
            }
        });
    });

</script>

<script>
    $(document).ready(function() {
        // Mark as Resolved button click handler
        $(document).on('click', '.mark-resolved-btn', function() {
            const claimId = $(this).data('id');
            const currentStatus = $(this).data('status');
            
            // Set values in the modal form
            $('#resolved_claim_id').val(claimId);
            $('#resolved_current_status').val(currentStatus);
            
            // Show the modal
            $('#markResolvedModal').modal('show');
        });
        
        // Confirm resolve button click handler
        $('#confirmResolveBtn').on('click', function() {
            const form = $('#markResolvedForm');
            const claimId = $('#resolved_claim_id').val();
            const note = $('#resolution_note').val();
            
            // Validate form
            if (!note.trim()) {
                alert('Please provide a resolution note.');
                return;
            }
            
            // Disable button and show loading state
            const btn = $(this);
            const originalText = btn.html();
            btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
            btn.prop('disabled', true);
            
            // Send AJAX request to mark claim as resolved
            $.ajax({
                url: 'ajax/mark_claim_resolved.php',
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        alert(response.message || 'Claim marked as resolved successfully.');
                        
                        // Reload the page to reflect changes
                        location.reload();
                    } else {
                        // Show error message
                        alert(response.message || 'An error occurred while marking the claim as resolved.');
                        
                        // Reset button state
                        btn.html(originalText);
                        btn.prop('disabled', false);
                    }
                },
                error: function() {
                    // Show error message
                    alert('An error occurred while marking the claim as resolved. Please try again.');
                    
                    // Reset button state
                    btn.html(originalText);
                    btn.prop('disabled', false);
                }
            });
        });
        
        // Initialize DataTable for claims with proper sorting
        if ($('#claimsTable').length > 0) {
            // Destroy existing DataTable if it exists
            if ($.fn.DataTable.isDataTable('#claimsTable')) {
                $('#claimsTable').DataTable().destroy();
            }
            
            // Initialize with proper sorting
            $('#claimsTable').DataTable({
                responsive: true,
                order: [[0, 'desc']], // Sort by Claim ID column (1st column, index 0) in descending order
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                },
                stateSave: false, // Disable state saving to ensure default sorting is always applied
                autoWidth: false, // Disable auto-width to allow manual column width control
                scrollX: false, // Disable horizontal scrolling
                "columnDefs": [
                    { "orderable": false, "targets": -1 }, // Disable sorting on action column
                    { "type": "num", "targets": 0 },  // Ensure numeric sorting for claim ID column
                    { "width": "10%", "targets": 0 }, // Claim #
                    { "width": "10%", "targets": 1 }, // Order ID
                    { "width": "10%", "targets": 2 }, // Products & Categories
                    { "width": "15%", "targets": 3 }, // SLA
                    { "width": "15%", "targets": 4 }, // Status
                    { "width": "15%", "targets": 5 }, // Created At
                    { "width": "10%", "targets": 6 }, // Assignment
                    { "width": "15%", "targets": 7 }  // Actions (last column)
                ],
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });
        }
    });
</script>

<script>
    $(document).ready(function() {
        // Delete claim modal
        $(document).on('click', '.delete-claim', function() {
            const claimId = $(this).data('id');
            const orderId = $(this).data('order');
            
            $('#delete_order_id').text(orderId);
            $('#confirmDeleteBtn').attr('href', 'delete_claim.php?id=' + claimId);
            
            $('#deleteClaimModal').modal('show');
            
            // Debug
            console.log('Delete claim clicked:', { claimId, orderId });
            console.log('Delete button href:', $('#confirmDeleteBtn').attr('href'));
        });

        // Quick assign claim modal
        $(document).on('click', '.quick-assign-claim', function() {
            const claimId = $(this).data('id');
            const claimNumber = $(this).data('claim-number');
            const orderId = $(this).data('order');
            
            $('#quick_claim_id').val(claimId);
            $('#claimInfoDisplay').html(`Claim #${claimNumber} for Order ${orderId}`);
            
            $('#quickAssignModal').modal('show');
            
            // Debug
            console.log('Quick assign claim clicked:', { claimId, claimNumber, orderId });
            
            // Log for debugging
            console.log('Quick assign modal opened for claim:', { claimId, claimNumber, orderId });
        });
        
        // Wait a short moment to ensure DataTable is initialized by other scripts
        setTimeout(function() {
            // Check if DataTable is already initialized
            if ($.fn.DataTable.isDataTable('#claimsTable')) {
                // Get the DataTable instance and update its order
                var table = $('#claimsTable').DataTable();
                table.order([0, 'desc']).draw();
                console.log('Claims table sorting updated to show latest claims first');
            }
        }, 100);
    });
</script>

<script>
    $(document).ready(function() {
        // Initialize tooltips with custom options
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipOptions = {
            trigger: 'click',
            html: true,
            placement: 'right',
            container: 'body',
            customClass: 'product-tooltip-container'
        };
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl, tooltipOptions));
        
        // Close tooltips when clicking elsewhere
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.view-products-btn, .tooltip').length) {
                $('.tooltip').remove();
            }
        });
        
        // Prevent multiple tooltips
        $('.view-products-btn').on('click', function() {
            $('.tooltip').not($(this).data('bs-tooltip')).remove();
        });
    });
</script>

<style>
    /* DataTable styling */
    .dataTables_wrapper .dataTables_length, 
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 15px;
    }
    
    .dataTables_wrapper .dataTables_info, 
    .dataTables_wrapper .dataTables_paginate {
        margin-top: 15px;
    }
    
    /* Table styling */
    #claimsTable {
        border-collapse: separate;
        border-spacing: 0;
        width: 100% !important;
    }
    
    #claimsTable th {
        background-color: #f8f9fa;
        font-weight: 600;
        padding: 12px 15px;
        border-bottom: 2px solid #dee2e6;
    }
    
    #claimsTable td {
        padding: 12px 15px;
        vertical-align: middle;
        border-bottom: 1px solid #e9ecef;
    }
    
    /* Status badges */
    .badge {
        font-size: 0.8rem;
        font-weight: 500;
        padding: 0.35em 0.65em;
    }
    
    /* Product button styling */
    .view-products-btn {
        width: 50px;
        height: 30px;
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    
    .view-products-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    /* Custom tooltip styling */
    .product-tooltip-container {
        max-width: 350px !important;
    }
    
    .tooltip {
        opacity: 1 !important;
    }
    
    .tooltip-inner {
        max-width: 350px;
        padding: 15px;
        text-align: left;
        background-color: #fff !important;
        color: #212529 !important;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .bs-tooltip-auto[data-popper-placement^=right] .tooltip-arrow::before, 
    .bs-tooltip-end .tooltip-arrow::before {
        border-right-color: #dee2e6 !important;
    }
    
    .bs-tooltip-auto[data-popper-placement^=left] .tooltip-arrow::before, 
    .bs-tooltip-start .tooltip-arrow::before {
        border-left-color: #dee2e6 !important;
    }
    
    .bs-tooltip-auto[data-popper-placement^=top] .tooltip-arrow::before, 
    .bs-tooltip-top .tooltip-arrow::before {
        border-top-color: #dee2e6 !important;
    }
    
    .bs-tooltip-auto[data-popper-placement^=bottom] .tooltip-arrow::before, 
    .bs-tooltip-bottom .tooltip-arrow::before {
        border-bottom-color: #dee2e6 !important;
    }
    
    /* Product tooltip content styling */
    .product-tooltip .product-item {
        margin-bottom: 10px;
        padding-bottom: 5px;
    }
    
    .product-tooltip .product-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .product-tooltip strong {
        display: block;
        margin-bottom: 3px;
        color: #0d6efd;
    }
    
    .product-tooltip .badge {
        display: inline-block;
        margin-top: 5px;
    }
    
    .product-tooltip hr {
        margin: 8px 0;
        opacity: 0.2;
    }
    
    /* Action buttons styling */
    .btn-group-sm > .btn {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        margin-right: 2px;
    }
    
    /* Truncate long text in table cells */
    td {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    /* Assignment badge styling */
    td .badge.bg-primary {
        background-color: #0d6efd !important;
    }
    
    td .badge.bg-secondary {
        background-color: #6c757d !important;
    }
</style>

<script>
    $(document).ready(function() {
        // Quick assign claim modal
        $(document).on('click', '.quick-assign-claim', function() {
            const claimId = $(this).data('id');
            const claimNumber = $(this).data('claim-number');
            const orderId = $(this).data('order');
            
            $('#quick_claim_id').val(claimId);
            
            // Display claim info with proper formatting
            let displayText = `Claim #${claimId}`;
            if (claimNumber) {
                displayText += ` (${claimNumber})`;
            }
            displayText += ` for Order ${orderId}`;
            
            $('#claimInfoDisplay').html(displayText);
            
            // Reset form and alerts
            $('#quick_agent_id').val('');
            $('#quick_notes').val('');
            $('#quickAssignAlert').hide();
            
            $('#quickAssignModal').modal('show');
            
            // Log for debugging
            console.log('Quick assign modal opened for claim:', { claimId, claimNumber, orderId });
            
            // Log for debugging
            console.log('Quick assign modal opened for claim:', { claimId, claimNumber, orderId });
        });
        
        // Handle confirm assign button click
        $('#confirmAssignBtn').on('click', function() {
            const claimId = $('#quick_claim_id').val();
            const agentId = $('#quick_agent_id').val();
            const notes = $('#quick_notes').val();
            
            // Validate form
            if (!agentId) {
                showQuickAssignAlert('danger', 'Please select a CS agent');
                return;
            }
            
            // Disable button and show loading state
            const assignBtn = $(this);
            const originalBtnText = assignBtn.html();
            assignBtn.html('<i class="fas fa-spinner fa-spin me-1"></i> Assigning...');
            assignBtn.prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: 'ajax/assign_claim.php',
                type: 'POST',
                data: {
                    claim_id: claimId,
                    agent_id: agentId,
                    notes: notes
                },
                dataType: 'json',
                success: function(response) {
                    // Reset button
                    assignBtn.html(originalBtnText);
                    assignBtn.prop('disabled', false);
                    
                    if (response.success) {
                        // Show success message
                        showQuickAssignAlert('success', response.message);
                        
                        // Refresh the page after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        showQuickAssignAlert('danger', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    // Reset button
                    assignBtn.html(originalBtnText);
                    assignBtn.prop('disabled', false);
                    
                    // Show error message
                    showQuickAssignAlert('danger', 'An error occurred while assigning the claim. Please try again.');
                    console.error('AJAX Error:', error);
                }
            });
        });
        
        // Function to show quick assign alert
        function showQuickAssignAlert(type, message) {
            const alertEl = $('#quickAssignAlert');
            alertEl.removeClass('alert-success alert-danger alert-warning alert-info')
                  .addClass('alert-' + type)
                  .html(message)
                  .show();
        }
        
        // Wait a short moment to ensure DataTable is initialized by other scripts
        setTimeout(function() {
            // Check if DataTable is already initialized
            if ($.fn.DataTable.isDataTable('#claimsTable')) {
                // Get the DataTable instance and update its order
                var table = $('#claimsTable').DataTable();
                table.order([0, 'desc']).draw();
                console.log('Claims table sorting updated to show latest claims first');
            }
        }, 100);
    });
</script>

<script>
    $(document).ready(function() {
        // Initialize tooltips with custom options
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipOptions = {
            trigger: 'click',
            html: true,
            placement: 'right',
            container: 'body',
            customClass: 'product-tooltip-container'
        };
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl, tooltipOptions));
        
        // Close tooltips when clicking elsewhere
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.view-products-btn, .tooltip').length) {
                $('.tooltip').remove();
            }
        });
        
        // Prevent multiple tooltips
        $('.view-products-btn').on('click', function() {
            $('.tooltip').not($(this).data('bs-tooltip')).remove();
        });
    });
</script>
