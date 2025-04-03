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
                `description` text NOT NULL,
                `status` enum('new','in_progress','on_hold','approved','rejected') NOT NULL DEFAULT 'new',
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
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
        
        // Insert claim record
        $stmt = $conn->prepare("
            INSERT INTO claims (
                order_id, customer_name, customer_email, customer_phone, 
                category_id, description, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, 'new'
            )
        ");
        
        $stmt->execute([
            $orderId, $customerName, $customerEmail, $customerPhone, 
            $categoryId, $description
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
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['photos']['tmp_name'][$i];
                    $originalName = $_FILES['photos']['name'][$i];
                    $fileSize = $_FILES['photos']['size'][$i];
                    $fileType = $_FILES['photos']['type'][$i];
                    
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
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['videos']['tmp_name'][$i];
                    $originalName = $_FILES['videos']['name'][$i];
                    $fileSize = $_FILES['videos']['size'][$i];
                    $fileType = $_FILES['videos']['type'][$i];
                    
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
                    }
                }
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
    }
}

// Get claims from database
$claims = [];
$claimsQuery = "SELECT c.id, c.order_id, c.customer_name, c.customer_email, c.description, c.status, 
                       c.created_at, cc.name as category_name
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
                        <th>Category</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($claims)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-3">No claims found.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($claims as $claim): ?>
                    <tr>
                        <td>#<?php echo $claim['id']; ?></td>
                        <td><?php echo htmlspecialchars($claim['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($claim['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($claim['category_name'] ?? 'N/A'); ?></td>
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
                        <td><?php echo date('M d, Y', strtotime($claim['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view_claim.php?id=<?php echo $claim['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-outline-secondary update-status" 
                                        data-id="<?php echo $claim['id']; ?>"
                                        data-status="<?php echo $claim['status']; ?>">
                                    <i class="fas fa-edit"></i>
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
                            <label class="form-label fw-bold">Customer Name</label>
                            <p class="form-control-static" id="customer_name_display"></p>
                            <input type="hidden" name="customer_name" id="customer_name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Email Address</label>
                            <p class="form-control-static" id="customer_email_display"></p>
                            <input type="hidden" name="customer_email" id="customer_email">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Phone Number</label>
                            <p class="form-control-static" id="customer_phone_display"></p>
                            <input type="hidden" name="customer_phone" id="customer_phone">
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
                        <form method="POST" action="" id="claimForm" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="submit_claim">
                            <input type="hidden" name="order_id" id="claim_order_id">
                            
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
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="photos" class="form-label fw-bold">Upload Photos</label>
                                    <input type="file" class="form-control" id="photos" name="photos[]" multiple accept="image/*">
                                    <div class="form-text">You can select multiple photos (JPG, PNG, etc.)</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="videos" class="form-label fw-bold">Upload Videos</label>
                                    <input type="file" class="form-control" id="videos" name="videos[]" multiple accept="video/*">
                                    <div class="form-text">You can select multiple videos (MP4, MOV, etc.)</div>
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
            <form method="POST" action="" id="updateStatusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="claim_id" id="status_claim_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">Update Claim Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
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
                        <label for="status_note" class="form-label">Note</label>
                        <textarea class="form-control" id="status_note" name="note" rows="3" 
                                  placeholder="Add a note about this status change (optional)"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include footer -->
<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Content Loaded');
        
        // Get elements
        const lookupButton = document.getElementById('lookupOrderBtn');
        const lookupBtnText = document.getElementById('lookupBtnText');
        const errorDiv = document.getElementById('orderLookupError');
        const orderDetailsDiv = document.getElementById('orderDetailsDiv');
        const changeOrderBtn = document.getElementById('changeOrderBtn');
        
        // Order lookup
        if (lookupButton) {
            console.log('Adding event listener to lookup button');
            lookupButton.addEventListener('click', function() {
                console.log('Lookup button clicked');
                const orderId = document.getElementById('order_id_lookup').value.trim();
                
                if (!orderId) {
                    errorDiv.style.display = 'block';
                    errorDiv.textContent = 'Please enter an order ID';
                    return;
                }
                
                // Disable button and show loading
                lookupButton.disabled = true;
                lookupBtnText.textContent = "Loading...";
                errorDiv.style.display = 'none';
                
                console.log('Making AJAX request to:', 'ajax/order_lookup.php');
                
                // Make AJAX request
                fetch('ajax/order_lookup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ order_id: orderId })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('AJAX response:', data);
                    
                    // Reset button text
                    lookupButton.disabled = false;
                    lookupBtnText.textContent = "Lookup";
                    
                    if (data.success) {
                        // Display order details
                        orderDetailsDiv.style.display = 'block';
                        
                        // Fill in order details
                        document.getElementById('order_id_display').textContent = data.order.order_id;
                        document.getElementById('customer_name_display').textContent = data.order.customer_name;
                        document.getElementById('customer_email_display').textContent = data.order.customer_email;
                        document.getElementById('customer_phone_display').textContent = data.order.customer_phone;
                        document.getElementById('order_date').textContent = data.order.order_date_display || data.order.order_date;
                        
                        // Set hidden input values
                        document.getElementById('claim_order_id').value = data.order.order_id;
                        document.getElementById('customer_name').value = data.order.customer_name;
                        document.getElementById('customer_email').value = data.order.customer_email;
                        document.getElementById('customer_phone').value = data.order.customer_phone;
                        
                        // Hide lookup section
                        document.getElementById('orderLookupSection').style.display = 'none';
                        
                        // Display item selection
                        const itemSelectionDiv = document.getElementById('item_selection');
                        itemSelectionDiv.innerHTML = data.items_html;
                        
                        // Add event listeners to checkboxes
                        const checkboxes = document.querySelectorAll('.item-checkbox');
                        checkboxes.forEach(checkbox => {
                            checkbox.addEventListener('change', function() {
                                validateItemSelection();
                            });
                        });
                    } else {
                        // Display error message
                        errorDiv.style.display = 'block';
                        errorDiv.textContent = data.message || 'An error occurred while fetching order details. Please try again.';
                        
                        // Hide order details
                        orderDetailsDiv.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Reset button text
                    lookupButton.disabled = false;
                    lookupBtnText.textContent = "Lookup";
                    
                    // Display error message
                    errorDiv.style.display = 'block';
                    errorDiv.textContent = 'An error occurred while fetching order details. Please try again.';
                    
                    // Hide order details
                    orderDetailsDiv.style.display = 'none';
                });
            });
        }
        
        // Change Order button
        if (changeOrderBtn) {
            changeOrderBtn.addEventListener('click', function() {
                // Show lookup section
                document.getElementById('orderLookupSection').style.display = 'block';
                
                // Hide order details
                orderDetailsDiv.style.display = 'none';
                
                // Hide claim form
                document.getElementById('claim_form_container').style.display = 'none';
            });
        }
        
        // Form submission
        const claimForm = document.getElementById('claimForm');
        if (claimForm) {
            claimForm.addEventListener('submit', function(e) {
                // Validate that at least one item is selected
                if (!validateItemSelection()) {
                    e.preventDefault();
                    document.getElementById('noItemsWarning').style.display = 'block';
                    return false;
                }
                
                document.getElementById('noItemsWarning').style.display = 'none';
                return true;
            });
        }
        
        // Validate that at least one item is selected
        function validateItemSelection() {
            const checkboxes = document.querySelectorAll('.item-checkbox:checked');
            return checkboxes.length > 0;
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
