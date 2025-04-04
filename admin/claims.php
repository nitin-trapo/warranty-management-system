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
                `category_id` int(11) NOT NULL,
                `sku` varchar(50) NOT NULL,
                `product_type` varchar(50) NOT NULL,
                `delivery_date` date NOT NULL,
                `description` text NOT NULL,
                `status` enum('new','in_progress','on_hold','approved','rejected') NOT NULL DEFAULT 'new',
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
                `created_by` int(11) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `category_id` (`category_id`),
                CONSTRAINT `claims_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `claim_categories` (`id`) ON DELETE CASCADE
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
        $customerPhone = trim($_POST['customer_phone']);
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
        
        // Check if a claim already exists for this order ID and SKU
        $stmt = $conn->prepare("SELECT id FROM claims WHERE order_id = ? AND sku = ?");
        $stmt->execute([$orderId, $sku]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("A claim for this order and product already exists. Please check existing claims.");
        }
        
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
        if (!empty($_FILES['photos']['name'][0])) {
            $uploadDir = '../uploads/claims/' . $claimId . '/photos/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO claim_media (
                    claim_id, file_path, file_type, original_filename, file_size
                ) VALUES (
                    ?, ?, 'photo', ?, ?
                )
            ");
            
            $fileCount = count($_FILES['photos']['name']);
            $maxPhotoSize = 2 * 1024 * 1024; // 2MB in bytes
            $photoErrors = [];
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['photos']['tmp_name'][$i];
                    $originalName = $_FILES['photos']['name'][$i];
                    $fileSize = $_FILES['photos']['size'][$i];
                    $fileType = $_FILES['photos']['type'][$i];
                    
                    // Check file size
                    if ($fileSize > $maxPhotoSize) {
                        $photoErrors[] = "Photo '$originalName' exceeds the maximum size limit of 2MB.";
                        continue;
                    }
                    
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
                    }
                }
            }
            
            if (!empty($photoErrors)) {
                throw new Exception(implode('<br>', $photoErrors));
            }
        }
        
        // Process video uploads
        if (!empty($_FILES['videos']['name'][0])) {
            $uploadDir = '../uploads/claims/' . $claimId . '/videos/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO claim_media (
                    claim_id, file_path, file_type, original_filename, file_size
                ) VALUES (
                    ?, ?, 'video', ?, ?
                )
            ");
            
            $fileCount = count($_FILES['videos']['name']);
            $maxVideoSize = 10 * 1024 * 1024; // 10MB in bytes
            $allowedVideoTypes = ['video/mp4', 'video/quicktime']; // MP4 and MOV formats
            $allowedVideoExtensions = ['mp4', 'mov'];
            $videoErrors = [];
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['videos']['tmp_name'][$i];
                    $originalName = $_FILES['videos']['name'][$i];
                    $fileSize = $_FILES['videos']['size'][$i];
                    $fileType = $_FILES['videos']['type'][$i];
                    
                    // Check file size
                    if ($fileSize > $maxVideoSize) {
                        $videoErrors[] = "Video '$originalName' exceeds the maximum size limit of 10MB.";
                        continue;
                    }
                    
                    // Check file type
                    if (!in_array($fileType, $allowedVideoTypes)) {
                        $videoErrors[] = "Video '$originalName' is not in an allowed format (MP4 or MOV).";
                        continue;
                    }
                    
                    // Check file extension
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    if (!in_array($extension, $allowedVideoExtensions)) {
                        $videoErrors[] = "Video '$originalName' is not in an allowed format (MP4 or MOV).";
                        continue;
                    }
                    
                    // Generate a unique filename
                    $newFileName = uniqid('video_') . '.' . $extension;
                    $filePath = $uploadDir . $newFileName;
                    
                    // Move the uploaded file
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $relativePath = 'uploads/claims/' . $claimId . '/videos/' . $newFileName;
                        
                        $stmt->execute([
                            $claimId, $relativePath, $originalName, $fileSize
                        ]);
                    }
                }
            }
            
            if (!empty($videoErrors)) {
                throw new Exception(implode('<br>', $videoErrors));
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
        
        // Set success message
        $successMessage = "Claim #$claimId created successfully.";
        
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

// Get claims from database
$claims = [];
$claimsQuery = "SELECT c.id, c.order_id, c.customer_name, c.customer_email, c.description, c.status, 
                       c.sku, c.created_at, c.updated_at, c.created_by, c.category_id,
                       cc.name as category_name, cc.sla_days 
                FROM claims c
                LEFT JOIN claim_categories cc ON c.category_id = cc.id
                ORDER BY c.id DESC";
$stmt = $conn->prepare($claimsQuery);
$stmt->execute();
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                $itemsHtml .= '<input type="checkbox" class="form-check-input item-checkbox" name="claim_items[]" value="' . $index . '" id="item_' . $index . '" ' . $disabled . '>';
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
            <table class="table table-hover table-striped" id="claimsTable" width="100%" cellspacing="0">
                <thead class="table-light">
                    <tr>
                        <th>Claim ID</th>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>SLA</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($claims)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-3">No claims found.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($claims as $claim): ?>
                    <tr data-claim-id="<?php echo $claim['id']; ?>">
                        <td>#<?php echo $claim['id']; ?></td>
                        <td><?php echo htmlspecialchars($claim['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($claim['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($claim['sku']); ?></td>
                        <td><?php echo htmlspecialchars($claim['category_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php
                            // Calculate SLA deadline
                            $createdDate = new DateTime($claim['created_at']);
                            $slaDays = (int)$claim['sla_days'];
                            $deadline = clone $createdDate;
                            $deadline->modify("+{$slaDays} days");
                            
                            // Get current date
                            $currentDate = new DateTime();
                            
                            // Check if claim is resolved (approved or rejected)
                            $isResolved = in_array($claim['status'], ['approved', 'rejected']);
                            
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
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    default => 'secondary'
                                };
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $claim['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y h:i A', strtotime($claim['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view_claim.php?id=<?php echo $claim['id']; ?>" class="btn btn-outline-primary" title="View Claim">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_claim.php?id=<?php echo $claim['id']; ?>" class="btn btn-outline-secondary" title="Edit Claim">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-outline-secondary update-status" 
                                        data-id="<?php echo $claim['id']; ?>"
                                        data-status="<?php echo $claim['status']; ?>"
                                        title="Update Status">
                                    <i class="fas fa-tasks"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger delete-claim" 
                                        data-id="<?php echo $claim['id']; ?>"
                                        data-order="<?php echo htmlspecialchars($claim['order_id']); ?>"
                                        title="Delete Claim">
                                    <i class="fas fa-trash"></i>
                                </button>
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
                            
                            <!-- Claim Details -->
                            <h6 class="border-bottom pb-2 mb-3">Claim Details</h6>
                            <div class="row mb-4">
                                <div class="col-md-4 mb-3">
                                    <label for="category_id" class="form-label fw-bold">Claim Category</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label for="description" class="form-label fw-bold">Claim Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required
                                          placeholder="Describe the issue or reason for the warranty claim"></textarea>
                                </div>
                            </div>
                            
                            <!-- Media Upload Section -->
                            <h6 class="border-bottom pb-2 mb-3">Supporting Media</h6>
                            
                            <!-- File Upload Errors Container -->
                            <div id="file-upload-errors" class="alert alert-danger mb-3" style="display:none;"></div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="photos" class="form-label fw-bold">Upload Photos</label>
                                    <input type="file" class="form-control" id="photos" name="photos[]" multiple accept="image/*">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle text-primary me-1"></i> Max size: <strong>2MB</strong> per image. Supported formats: JPG, PNG, GIF.
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="videos" class="form-label fw-bold">Upload Videos</label>
                                    <input type="file" class="form-control" id="videos" name="videos[]" multiple accept="video/mp4,video/quicktime">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle text-primary me-1"></i> Max size: <strong>10MB</strong> per video. Supported formats: <strong>MP4, MOV only</strong>.
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i> Supporting media helps us better understand the issue and process your claim faster.
                                    </div>
                                </div>
                            </div>
                            
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

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel">Update Claim Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="updateStatusForm">
                <input type="hidden" name="claim_id" id="status_claim_id" value="">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="claim_status" class="form-label">Status</label>
                        <select class="form-select" id="claim_status" name="status" required>
                            <option value="new">New</option>
                            <option value="in_progress">In Progress</option>
                            <option value="on_hold">On Hold</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status_note" class="form-label">Note (Optional)</label>
                        <textarea class="form-control" id="status_note" name="note" rows="3" placeholder="Add a note about this status change..."></textarea>
                        <div class="form-text">If left empty, a default note about the status change will be added.</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary update-status-btn">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Claim Modal -->
<div class="modal fade" id="deleteClaimModal" tabindex="-1" aria-labelledby="deleteClaimModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteClaimModalLabel">Delete Claim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this claim? This action cannot be undone.</p>
                <p><strong>Claim Details:</strong></p>
                <ul>
                    <li>Order ID: <span id="delete_order_id"></span></li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Claim</a>
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
</script>

<!-- Initialize DataTable -->
<script>
    $(document).ready(function() {
        $('#claimsTable').DataTable({
            responsive: true,
            stateSave: true,
            "columnDefs": [
                { "orderable": false, "targets": -1 } // Disable sorting on action column
            ],
            "order": [[ 0, "desc" ]], // Sort by ID descending by default
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
            "language": {
                "lengthMenu": "Show _MENU_ claims per page",
                "zeroRecords": "No claims found",
                "info": "Showing page _PAGE_ of _PAGES_",
                "infoEmpty": "No claims available",
                "infoFiltered": "(filtered from _MAX_ total claims)"
            }
        });
        
        // Update status modal
        $('.update-status').on('click', function() {
            const claimId = $(this).data('id');
            const status = $(this).data('status');
            
            // Set values in the form
            $('#status_claim_id').val(claimId);
            $('#claim_status').val(status);
            
            console.log("Opening modal for claim ID:", claimId, "with status:", status);
            console.log("Form values set:", {
                claim_id: $('#status_claim_id').val(),
                status: $('#claim_status').val()
            });
            
            $('#updateStatusModal').modal('show');
        });
        
        // Delete claim modal
        $('.delete-claim').on('click', function() {
            const claimId = $(this).data('id');
            const orderId = $(this).data('order');
            
            $('#delete_order_id').text(orderId);
            $('#confirmDeleteBtn').attr('href', 'delete_claim.php?id=' + claimId);
            
            $('#deleteClaimModal').modal('show');
        });
        
        // Display session messages
        <?php if (isset($_SESSION['success_message'])): ?>
            showAlert('success', '<?php echo addslashes($_SESSION['success_message']); ?>');
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            showAlert('danger', '<?php echo addslashes($_SESSION['error_message']); ?>');
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        // Function to show alert
        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            $('.page-title').after(alertHtml);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        }
        
        // Handle update status button click
        $('.update-status-btn').on('click', function() {
            updateClaimStatus();
        });
        
        // Function to update claim status
        function updateClaimStatus() {
            const modal = $('#updateStatusModal');
            const claimId = $('#status_claim_id').val();
            const status = $('#claim_status').val();
            const note = $('#status_note').val();
            
            // Create FormData object
            const formData = new FormData();
            formData.append('claim_id', claimId);
            formData.append('status', status);
            formData.append('note', note);
            
            // Log the data for debugging
            console.log("Claim ID:", claimId);
            console.log("Status:", status);
            console.log("Note:", note);
            
            // Show loading state
            const submitBtn = modal.find('.update-status-btn');
            const originalBtnText = submitBtn.html();
            submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');
            submitBtn.prop('disabled', true);
            
            // Make sure we have a claim ID
            if (!claimId) {
                showAlert('danger', 'No claim ID provided. Please try again.');
                submitBtn.html(originalBtnText);
                submitBtn.prop('disabled', false);
                return;
            }
            
            $.ajax({
                url: 'ajax/update_claim_status_ajax.php',
                type: 'POST',
                data: {
                    claim_id: claimId,
                    status: status,
                    note: note
                },
                traditional: true,
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    console.log("Success Response:", response);
                    
                    // Hide modal
                    modal.modal('hide');
                    
                    // Reset form
                    $('#updateStatusForm')[0].reset();
                    
                    if (response.success) {
                        // Update status in the table
                        const newStatus = response.status;
                        updateStatusInTable(claimId, newStatus);
                        
                        // Show success message
                        showAlert('success', response.message);
                    } else {
                        // Show error message
                        showAlert('danger', response.message || 'An error occurred while updating the status.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.log("Response Text:", xhr.responseText);
                    
                    try {
                        // Try to parse the response as JSON
                        const errorResponse = JSON.parse(xhr.responseText);
                        showAlert('danger', errorResponse.message || 'An error occurred while updating the status.');
                    } catch (e) {
                        // If parsing fails, show a generic error
                        showAlert('danger', 'An error occurred while updating the status. Please try again.');
                    }
                },
                complete: function() {
                    // Reset button state
                    submitBtn.html(originalBtnText);
                    submitBtn.prop('disabled', false);
                }
            });
        }
        
        // Function to update status in the table
        function updateStatusInTable(claimId, newStatus) {
            const statusText = getStatusText(newStatus);
            const statusClass = getStatusClass(newStatus);
            
            // Find the row with the claim ID
            const row = $(`tr[data-claim-id="${claimId}"]`);
            if (row.length) {
                // Update the status badge
                const statusCell = row.find('td:nth-child(7)'); // Status is in the 7th column (index 7)
                statusCell.html(`<span class="badge ${statusClass}">${statusText}</span>`);
                
                // Update the data attribute for the update status button
                row.find('.update-status').data('status', newStatus);
                
                console.log("Updated status for claim ID:", claimId, "to:", newStatus);
                console.log("Status cell:", statusCell);
            } else {
                console.error("Could not find row with claim ID:", claimId);
            }
        }
        
        // Function to get status text
        function getStatusText(status) {
            switch (status) {
                case 'new': return 'New';
                case 'in_progress': return 'In Progress';
                case 'on_hold': return 'On Hold';
                case 'approved': return 'Approved';
                case 'rejected': return 'Rejected';
                default: return 'Unknown';
            }
        }
        
        // Function to get status badge class
        function getStatusClass(status) {
            switch (status) {
                case 'new': return 'bg-primary';
                case 'in_progress': return 'bg-info';
                case 'on_hold': return 'bg-warning';
                case 'approved': return 'bg-success';
                case 'rejected': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }
    });
</script>

<style>
    /* DataTable styling */
    .dataTables_wrapper .dataTables_length, 
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 15px;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 6px 12px;
        margin-left: 5px;
    }
    
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 6px 12px;
        margin: 0 5px;
    }
    
    .dataTables_wrapper .dataTables_info {
        padding-top: 10px;
    }
    
    .dataTables_wrapper .dataTables_paginate {
        padding-top: 10px;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 5px 10px;
        margin-left: 5px;
        border-radius: 4px;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: var(--primary-color);
        color: white !important;
        border: 1px solid var(--primary-color);
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--accent-color);
        color: white !important;
        border: 1px solid var(--accent-color);
    }
    
    /* Table styling */
    #claimsTable {
        border-collapse: separate;
        border-spacing: 0;
    }
    
    #claimsTable th {
        font-weight: 600;
        padding: 12px 15px;
    }
    
    #claimsTable td {
        padding: 12px 15px;
        vertical-align: middle;
    }
    
    /* Button styling */
    .btn-group-sm > .btn {
        padding: 0.25rem 0.5rem;
    }
    
    /* Fix for page title and button layout */
    .page-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .page-title h1 {
        margin-bottom: 0;
    }
    
    .button-container {
        min-width: 150px;
        text-align: right;
    }
    
    .add-claim-btn {
        min-width: 140px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--primary-color);
        background-color: var(--primary-color);
        color: white;
        font-weight: 500;
        padding: 0.375rem 0.75rem;
        border-radius: 0.25rem;
        transition: none;
    }
    
    .add-claim-btn:hover,
    .add-claim-btn:focus,
    .add-claim-btn:active {
        background-color: var(--secondary-color) !important;
        border-color: var(--secondary-color) !important;
        color: white !important;
        box-shadow: none !important;
        transform: none !important;
    }
</style>
