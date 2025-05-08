<?php
/**
 * Edit Claim
 * 
 * This file allows administrators to edit an existing warranty claim.
 */

// Set page title
$pageTitle = 'Edit Claim';

// Include database connection
require_once '../config/database.php';

// Include file helper
require_once '../includes/file_helper.php';

// Establish database connection
$conn = getDbConnection();

// Check if claim ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to claims page
    header('Location: claims.php');
    exit;
}

$claimId = (int)$_GET['id'];

// Include auth helper
require_once '../includes/auth_helper.php';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_claim') {
    try {
        // Get claim data first to compare status
        $claimQuery = "SELECT * FROM claims WHERE id = ?";
        $stmt = $conn->prepare($claimQuery);
        $stmt->execute([$claimId]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$claim) {
            throw new Exception("Claim not found.");
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Get form data
        $customerName = trim($_POST['customer_name']);
        $customerEmail = trim($_POST['customer_email']);
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $orderDate = $_POST['delivery_date'];
        $status = $_POST['status'];
        $statusNote = trim($_POST['status_note'] ?? '');
        $newSkus = trim($_POST['new_skus'] ?? '');
        
        // Get item data
        $itemIds = $_POST['item_id'] ?? [];
        $itemSkus = $_POST['item_sku'] ?? [];
        $itemProductNames = $_POST['item_product_name'] ?? [];
        $itemProductTypes = $_POST['item_product_type'] ?? [];
        $categoryIds = $_POST['category_id'] ?? [];
        $descriptions = $_POST['description'] ?? [];
        
        // Validate required fields
        $errors = [];
        
        if (empty($_POST['order_id'])) {
            $errors[] = "Order ID is required.";
        }
        
        if (empty($customerName)) {
            $errors[] = "Customer name is required.";
        }
        
        if (empty($customerEmail)) {
            $errors[] = "Customer email is required.";
        } else if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        
        if (empty($orderDate)) {
            $errors[] = "Order date is required.";
        }
        
        // Validate items
        if (empty($itemIds)) {
            $errors[] = "No claim items found.";
        } else {
            foreach ($categoryIds as $index => $categoryId) {
                if (empty($categoryId)) {
                    $errors[] = "Category is required for all items.";
                    break;
                }
            }
            
            foreach ($descriptions as $index => $description) {
                if (empty($description)) {
                    $errors[] = "Description is required for all items.";
                    break;
                }
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }
        
        // Check for duplicate claims with the same order ID and SKU combination
        // We exclude the current claim ID from the check
        foreach ($itemSkus as $index => $sku) {
            $duplicateQuery = "SELECT COUNT(*) FROM claims c 
                              JOIN claim_items ci ON c.id = ci.claim_id 
                              WHERE c.order_id = ? AND ci.sku = ? AND c.id != ? AND ci.id != ?";
            $stmt = $conn->prepare($duplicateQuery);
            $stmt->execute([$_POST['order_id'], $sku, $claimId, $itemIds[$index] ?? 0]);
            $duplicateCount = (int)$stmt->fetchColumn();
            
            if ($duplicateCount > 0) {
                throw new Exception("A claim with Order ID {$_POST['order_id']} and SKU {$sku} already exists.");
            }
        }
        
        // Update claim in database
        $updateQuery = "UPDATE claims SET 
                        customer_name = ?,
                        customer_email = ?,
                        customer_phone = ?,
                        delivery_date = ?,
                        updated_at = NOW()";
        
        $params = [
            $customerName,
            $customerEmail,
            $customerPhone,
            $orderDate
        ];
        
        // Only update status if it has changed and user is admin
        if ($status !== $claim['status']) {
            if (isAdmin()) {
                $updateQuery .= ", status = ?";
                $params[] = $status;
            } else {
                // Log attempt by non-admin to change status
                error_log("Non-admin user (ID: {$_SESSION['user_id']}) attempted to change claim status from {$claim['status']} to {$status}");
                
                // Use original status instead
                $status = $claim['status'];
            }
        }
        
        $updateQuery .= " WHERE id = ?";
        $params[] = $claimId;
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->execute($params);
        
        // Update claim items
        foreach ($itemIds as $index => $itemId) {
            $updateItemQuery = "UPDATE claim_items SET 
                                category_id = ?,
                                description = ?
                                WHERE id = ? AND claim_id = ?";
            $stmt = $conn->prepare($updateItemQuery);
            $stmt->execute([
                $categoryIds[$index],
                $descriptions[$index],
                $itemId,
                $claimId
            ]);
            
            // Process photos for this item
            if (isset($_FILES["photos_{$index}"]) && !empty($_FILES["photos_{$index}"]['name'][0])) {
                $photos = $_FILES["photos_{$index}"];
                $uploadDir = "../uploads/claims/{$claimId}/items/{$itemId}/photos/";
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Upload photos
                for ($i = 0; $i < count($photos['name']); $i++) {
                    if ($photos['error'][$i] === 0) {
                        $fileName = $photos['name'][$i];
                        $fileSize = $photos['size'][$i];
                        $fileTmpName = $photos['tmp_name'][$i];
                        $fileType = $photos['type'][$i];
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        // Validate file size (2MB max)
                        if ($fileSize > 2 * 1024 * 1024) {
                            throw new Exception("Photo '{$fileName}' exceeds the maximum size of 2MB.");
                        }
                        
                        // Validate file extension
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                        if (!in_array($fileExtension, $allowedExtensions)) {
                            throw new Exception("Photo '{$fileName}' has an invalid extension. Allowed: " . implode(', ', $allowedExtensions));
                        }
                        
                        // Generate unique filename
                        $newFileName = uniqid('photo_') . '.' . $fileExtension;
                        $destination = $uploadDir . $newFileName;
                        
                        // Move uploaded file
                        if (move_uploaded_file($fileTmpName, $destination)) {
                            // Insert media record
                            $insertMediaQuery = "INSERT INTO claim_media (claim_id, claim_item_id, file_path, file_type, original_filename, file_size, created_at) 
                                                VALUES (?, ?, ?, ?, ?, ?, NOW())";
                            $stmt = $conn->prepare($insertMediaQuery);
                            $stmt->execute([
                                $claimId,
                                $itemId,
                                "uploads/claims/{$claimId}/items/{$itemId}/photos/{$newFileName}",
                                'photo',
                                $fileName,
                                $fileSize
                            ]);
                        } else {
                            throw new Exception("Failed to upload photo '{$fileName}'.");
                        }
                    }
                }
            }
            
            // Process videos for this item
            if (isset($_FILES["videos_{$index}"]) && !empty($_FILES["videos_{$index}"]['name'][0])) {
                $videos = $_FILES["videos_{$index}"];
                $uploadDir = "../uploads/claims/{$claimId}/items/{$itemId}/videos/";
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Upload videos
                for ($i = 0; $i < count($videos['name']); $i++) {
                    if ($videos['error'][$i] === 0) {
                        $fileName = $videos['name'][$i];
                        $fileSize = $videos['size'][$i];
                        $fileTmpName = $videos['tmp_name'][$i];
                        $fileType = $videos['type'][$i];
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        // Validate file size (10MB max)
                        if ($fileSize > 10 * 1024 * 1024) {
                            throw new Exception("Video '{$fileName}' exceeds the maximum size of 10MB.");
                        }
                        
                        // Validate file extension
                        $allowedExtensions = ['mp4', 'mov'];
                        if (!in_array($fileExtension, $allowedExtensions)) {
                            throw new Exception("Video '{$fileName}' has an invalid extension. Allowed: " . implode(', ', $allowedExtensions));
                        }
                        
                        // Generate unique filename
                        $newFileName = uniqid('video_') . '.' . $fileExtension;
                        $destination = $uploadDir . $newFileName;
                        
                        // Move uploaded file
                        if (move_uploaded_file($fileTmpName, $destination)) {
                            // Insert media record
                            $insertMediaQuery = "INSERT INTO claim_media (claim_id, claim_item_id, file_path, file_type, original_filename, file_size, created_at) 
                                                VALUES (?, ?, ?, ?, ?, ?, NOW())";
                            $stmt = $conn->prepare($insertMediaQuery);
                            $stmt->execute([
                                $claimId,
                                $itemId,
                                "uploads/claims/{$claimId}/items/{$itemId}/videos/{$newFileName}",
                                'video',
                                $fileName,
                                $fileSize
                            ]);
                        } else {
                            throw new Exception("Failed to upload video '{$fileName}'.");
                        }
                    }
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Add status change note if status was changed and a note was provided
        if ($status !== $claim['status'] && !empty($statusNote)) {
            // Get current user ID
            $userId = $_SESSION['user_id'] ?? 1; // Default to admin if not set
            
            // Create note text with status change information
            $noteText = $statusNote . "\n\nStatus changed from '" . ucfirst(str_replace('_', ' ', $claim['status'])) . "' to '" . ucfirst(str_replace('_', ' ', $status)) . "'.";
            
            // Insert note
            $insertNoteQuery = "INSERT INTO claim_notes (claim_id, note, created_by, created_at) 
                               VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($insertNoteQuery);
            $stmt->execute([$claimId, $noteText, $userId]);
        }
        
        // Set success message
        $successMessage = "Claim updated successfully.";
        
        // Redirect to view claim page
        header("Location: view_claim.php?id={$claimId}&success=1");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        throw $e;
    }
}

// Include header
require_once 'includes/header.php';

// Get claim data
$claimQuery = "SELECT c.* 
               FROM claims c 
               WHERE c.id = ?";
$stmt = $conn->prepare($claimQuery);
$stmt->execute([$claimId]);
$claim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$claim) {
    echo '<div class="alert alert-danger">Claim not found.</div>';
    require_once 'includes/footer.php';
    exit;
}

// Get claim items
$itemsQuery = "SELECT i.* 
               FROM claim_items i 
               WHERE i.claim_id = ?";
$stmt = $conn->prepare($itemsQuery);
$stmt->execute([$claimId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$categoriesQuery = "SELECT * FROM claim_categories ORDER BY name";
$stmt = $conn->prepare($categoriesQuery);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get claim media
$mediaQuery = "SELECT * FROM claim_media WHERE claim_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($mediaQuery);
$stmt->execute([$claimId]);
$mediaResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get claim notes
$notesQuery = "SELECT n.*, u.username as created_by_name 
               FROM claim_notes n 
               LEFT JOIN users u ON n.created_by = u.id 
               WHERE n.claim_id = ? 
               ORDER BY n.created_at DESC";
$stmt = $conn->prepare($notesQuery);
$stmt->execute([$claimId]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if manual order exists for this claim
$manualOrderQuery = "SELECT id, document_no FROM manual_orders WHERE claim_id = ? LIMIT 1";
$stmt = $conn->prepare($manualOrderQuery);
$stmt->execute([$claimId]);
$manualOrder = $stmt->fetch(PDO::FETCH_ASSOC);

// Organize media by item ID and type
$media = [];
foreach ($items as $item) {
    $media[$item['id']] = [
        'photos' => [],
        'videos' => []
    ];
}

foreach ($mediaResults as $mediaItem) {
    $itemId = $mediaItem['claim_item_id'] ?? null;
    $type = $mediaItem['file_type'];
    
    // If item ID is valid and exists in our items array
    if ($itemId && isset($media[$itemId])) {
        if ($type === 'photo') {
            $media[$itemId]['photos'][] = $mediaItem;
        } elseif ($type === 'video') {
            $media[$itemId]['videos'][] = $mediaItem;
        }
    }
}

?>

<div class="page-title">
    <h1>Edit Claim #<?php echo $claimId; ?></h1>
    <div class="button-container">
        <a href="view_claim.php?id=<?php echo $claimId; ?>" class="btn btn-primary">
            <i class="fas fa-eye me-1"></i> View Claim
        </a>
        <?php if (($claim['status'] == 'in_progress' || $claim['status'] == 'approved')): ?>
        <?php if ($manualOrder): ?>
        <button type="button" class="btn btn-success" disabled title="Manual order already created (<?php echo htmlspecialchars($manualOrder['document_no']); ?>)">
            <i class="fas fa-check me-1"></i> Manual Order Created
        </button>
        <?php else: ?>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#manualOrderModal" id="manual-order-btn">
            <i class="fas fa-plus me-1"></i> Manual Order
        </button>
        <?php endif; ?>
        <?php endif; ?>
        <a href="claims.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Claims
        </a>
    </div>
</div>

<!-- Alert Container -->
<div id="alert-container"></div>

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

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title">Edit Claim</h5>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="edit_claim.php?id=<?php echo $claimId; ?>" enctype="multipart/form-data" class="needs-validation" novalidate id="edit-claim-form">
            <input type="hidden" name="action" value="update_claim">
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="order_id" class="form-label">Order ID</label>
                        <input type="text" class="form-control" id="order_id" name="order_id" value="<?php echo htmlspecialchars($claim['order_id']); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="delivery_date" class="form-label">Order Date</label>
                        <input type="date" class="form-control" id="delivery_date" name="delivery_date" value="<?php echo htmlspecialchars($claim['delivery_date']); ?>" readonly>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <h6 class="border-bottom pb-2 mb-3">Customer Information</h6>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($claim['customer_name']); ?>" required>
                        <div class="invalid-feedback">Customer name is required.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="customer_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="customer_email" name="customer_email" value="<?php echo htmlspecialchars($claim['customer_email']); ?>" required>
                        <div class="invalid-feedback">Valid email address is required.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="customer_phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="customer_phone" name="customer_phone" value="<?php echo htmlspecialchars($claim['customer_phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Claim Items -->
            <h6 class="border-bottom pb-2 mb-3">Claim Items</h6>
            <div id="claim_items_container" class="mb-4">
                <?php if (empty($items)): ?>
                <div class="alert alert-warning">No claim items found.</div>
                <?php else: ?>
                <?php foreach ($items as $index => $item): ?>
                <div class="item-form bg-light p-3 mb-3 border rounded">
                    <h5 class="border-bottom pb-2 mb-3 text-primary">Item: <?php echo htmlspecialchars($item['product_name']); ?></h5>
                    <input type="hidden" name="item_id[]" value="<?php echo $item['id']; ?>">
                    <input type="hidden" name="item_sku[]" value="<?php echo htmlspecialchars($item['sku']); ?>">
                    <input type="hidden" name="item_product_name[]" value="<?php echo htmlspecialchars($item['product_name']); ?>">
                    <input type="hidden" name="item_product_type[]" value="<?php echo htmlspecialchars($item['product_type']); ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <p><strong>SKU:</strong> <?php echo htmlspecialchars($item['sku']); ?> 
                            <span class="mx-3">|</span> 
                            <strong>Product Type:</strong> <?php echo htmlspecialchars($item['product_type'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Claim Category</label>
                            <select class="form-select" name="category_id[]" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($item['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Description</label>
                            <textarea class="form-control" name="description[]" rows="3" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Photos</label>
                            <input type="file" class="form-control" id="photos_<?php echo $index; ?>" name="photos_<?php echo $index; ?>[]" multiple accept="image/*">
                            <div class="form-text">Max size: 2MB per image</div>
                            <div class="invalid-feedback" id="photos_<?php echo $index; ?>_feedback"></div>
                            
                            <?php if (!empty($media[$item['id']]['photos'])): ?>
                            <div class="mt-2">
                                <p><strong>Existing Photos:</strong></p>
                                <div class="row">
                                    <?php foreach ($media[$item['id']]['photos'] as $photo): ?>
                                    <div class="col-md-3 mb-2">
                                        <div class="position-relative">
                                            <?php
                                            // Get the file path
                                            $filePath = $photo['file_path'];
                                            $originalFilename = $photo['original_filename'];
                                            
                                            // Check if the file path starts with a slash
                                            if (substr($filePath, 0, 1) === '/') {
                                                // Remove the leading slash for server path
                                                $serverPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
                                            } else {
                                                // Add the document root
                                                $serverPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $filePath;
                                            }
                                            
                                            // Try different path combinations
                                            $paths = [
                                                $serverPath,
                                                "../" . $filePath,
                                                "../uploads/claims/{$claimId}/items/{$item['id']}/photos/" . basename($filePath)
                                            ];
                                            
                                            $imgSrc = "/warranty-management-system/assets/img/placeholder-image.png";
                                            foreach ($paths as $path) {
                                                if (file_exists($path)) {
                                                    // Use relative path for browser
                                                    if (strpos($path, $_SERVER['DOCUMENT_ROOT']) === 0) {
                                                        $imgSrc = substr($path, strlen($_SERVER['DOCUMENT_ROOT']));
                                                    } else {
                                                        // Convert to web path
                                                        $imgSrc = str_replace("../", "/warranty-management-system/", $path);
                                                    }
                                                    break;
                                                }
                                            }
                                            ?>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#photoModal" 
                                               data-photo-src="<?php echo htmlspecialchars($imgSrc); ?>" 
                                               data-photo-name="<?php echo htmlspecialchars($originalFilename); ?>">
                                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="img-thumbnail" alt="<?php echo htmlspecialchars($originalFilename); ?>">
                                            </a>
                                            <div class="mt-1 small text-muted"><?php echo htmlspecialchars(basename($filePath)); ?></div>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-sm btn-danger delete-media-btn" 
                                                        data-media-id="<?php echo $photo['id']; ?>" 
                                                        data-media-type="photo"
                                                        data-file-path="<?php echo htmlspecialchars($filePath); ?>">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Videos</label>
                            <input type="file" class="form-control" id="videos_<?php echo $index; ?>" name="videos_<?php echo $index; ?>[]" multiple accept="video/mp4,video/quicktime">
                            <div class="form-text">Max size: 10MB (MP4/MOV only)</div>
                            <div class="invalid-feedback" id="videos_<?php echo $index; ?>_feedback"></div>
                            
                            <?php if (!empty($media[$item['id']]['videos'])): ?>
                            <div class="mt-2">
                                <p><strong>Existing Videos:</strong></p>
                                <div class="row">
                                    <?php foreach ($media[$item['id']]['videos'] as $video): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="position-relative">
                                            <?php
                                            // Get the file path
                                            $filePath = $video['file_path'];
                                            $originalFilename = $video['original_filename'];
                                            
                                            // Check if the file path starts with a slash
                                            if (substr($filePath, 0, 1) === '/') {
                                                // Remove the leading slash for server path
                                                $serverPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
                                            } else {
                                                // Add the document root
                                                $serverPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $filePath;
                                            }
                                            
                                            // Try different path combinations
                                            $paths = [
                                                $serverPath,
                                                "../" . $filePath,
                                                "../uploads/claims/{$claimId}/items/{$item['id']}/videos/" . basename($filePath)
                                            ];
                                            
                                            $videoSrc = "/warranty-management-system/assets/img/placeholder-image.png";
                                            foreach ($paths as $path) {
                                                if (file_exists($path)) {
                                                    // Use relative path for browser
                                                    if (strpos($path, $_SERVER['DOCUMENT_ROOT']) === 0) {
                                                        $videoSrc = substr($path, strlen($_SERVER['DOCUMENT_ROOT']));
                                                    } else {
                                                        // Convert to web path
                                                        $videoSrc = str_replace("../", "/warranty-management-system/", $path);
                                                    }
                                                    break;
                                                }
                                            }
                                            
                                            // Determine the correct video type based on file extension
                                            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                                            $videoType = ($fileExtension === 'mp4') ? 'video/mp4' : 'video/quicktime';
                                            ?>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#videoModal" 
                                               data-video-src="<?php echo htmlspecialchars($videoSrc); ?>" 
                                               data-video-type="<?php echo $videoType; ?>"
                                               data-video-name="<?php echo htmlspecialchars($originalFilename); ?>">
                                                <video class="img-thumbnail w-100" controls>
                                                    <source src="<?php echo htmlspecialchars($videoSrc); ?>" type="<?php echo $videoType; ?>">
                                                    Your browser does not support the video tag.
                                                </video>
                                            </a>
                                            <div class="mt-1 small text-muted"><?php echo htmlspecialchars(basename($filePath)); ?></div>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-sm btn-danger delete-media-btn" 
                                                        data-media-id="<?php echo $video['id']; ?>" 
                                                        data-media-type="video"
                                                        data-file-path="<?php echo htmlspecialchars($filePath); ?>">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Claim Status and Notes Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Claim Status</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <?php if (isAdmin()): ?>
                            <!-- Status dropdown for admins -->
                            <select class="form-select" id="status" name="status" required>
                                <option value="new" <?php echo $claim['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="in_progress" <?php echo ($claim['status'] === 'in_progress' || $claim['status'] === 'approved') ? 'selected' : ''; ?>>Approved-In Progress</option>
                                <option value="on_hold" <?php echo $claim['status'] === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                <option value="resolved" <?php echo $claim['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="rejected" <?php echo $claim['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <?php else: ?>
                            <!-- Read-only status for CS agents -->
                            <div class="form-control bg-light">
                                <span class="badge bg-<?php 
                                    echo match($claim['status']) {
                                        'new' => 'info',
                                        'in_progress' => 'primary',
                                        'on_hold' => 'warning',
                                        'approved' => 'primary',
                                        'resolved' => 'success',
                                        'rejected' => 'danger',
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
                            </div>
                            <!-- Hidden field to maintain the current status -->
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($claim['status']); ?>">
                            <?php endif; ?>
                        </div>

                        <?php if (isAdmin()): ?>
                        <div class="col-md-6">
                            <label for="status_note" class="form-label">Status Note (Optional)</label>
                            <textarea class="form-control" id="status_note" name="status_note" rows="3" placeholder="Add a note about this status change..."></textarea>
                            <div class="form-text">If provided, this note will be added to the claim history.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Claim Notes Section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Claim Notes</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                        <i class="fas fa-plus me-1"></i> Add Note
                    </button>
                </div>
                <div class="card-body">
                    <?php if (!empty($notes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover notes-table">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Note</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notes as $index => $note): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php 
                                        // Process the note to highlight tagged users
                                        $noteText = htmlspecialchars($note['note']);
                                        // Replace @username with highlighted version
                                        $noteText = preg_replace('/@([a-zA-Z0-9._]+)/', '<span class="badge bg-info text-dark">@$1</span>', $noteText);
                                        echo nl2br($noteText); 
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($note['created_by_name'] ?? 'System'); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($note['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        No notes have been added to this claim yet.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="view_claim.php?id=<?php echo $claimId; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="photoModalLabel">Photo Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img src="" class="img-fluid" id="photo-modal-image" style="max-height: 80vh;">
                <p id="photo-modal-name" class="mt-2 p-3 mb-0"></p>
            </div>
        </div>
    </div>
</div>

<!-- Video Modal -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="videoModalLabel">Video Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0">
                <video controls id="video-modal-video" style="max-height: 80vh; max-width: 100%;">
                    <source src="" id="video-modal-source">
                    Your browser does not support the video tag.
                </video>
                <p id="video-modal-name" class="mt-2 p-3 mb-0"></p>
            </div>
        </div>
    </div>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNoteModalLabel">Add Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addNoteForm">
                    <input type="hidden" name="claim_id" value="<?php echo $claimId; ?>">
                    <div class="mb-3">
                        <label for="note_text" class="form-label">Note</label>
                        <textarea class="form-control" id="note_text" name="note" rows="4" required></textarea>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> You can tag users with @username to notify them about this note.
                        </div>
                    </div>
                    
                    <div class="mb-3" id="taggedUsersPreview" style="display: none;">
                        <label class="form-label">Tagged Users</label>
                        <div class="tagged-users-list p-2 border rounded bg-light">
                            <span class="text-muted">No users tagged yet</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary add-note-btn">Add Note</button>
            </div>
        </div>
    </div>
</div>

<!-- User Tagging Suggestions Dropdown -->
<div class="dropdown-menu" id="userSuggestionsDropdown"></div>

<!-- Include jQuery if not already included -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Include claim notes JS -->
<script src="js/claim-notes.js"></script>

<!-- Include user tagging JS -->
<script src="js/user-tagging.js"></script>

<script>
    $(document).ready(function() {
        // Direct handler for Add Note button loading state
        $('.add-note-btn').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.html();
            $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
            $btn.prop('disabled', true);
            
            // Reset the button after 10 seconds in case of error
            setTimeout(function() {
                if ($btn.prop('disabled')) {
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            }, 10000);
        });
        // Status change handler
        $('#status').on('change', function() {
            // Status change event handler (previously controlled New SKUs visibility)
            // Now just a placeholder for any future status-related functionality
        });
        
        // Photo modal event handler
        $('#photoModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const photoSrc = button.data('photo-src');
            const photoName = button.data('photo-name');
            
            const modal = $(this);
            const modalImage = modal.find('#photo-modal-image');
            
            // Set the image source
            modalImage.attr('src', photoSrc);
            
            // Set the photo name
            modal.find('#photo-modal-name').text(photoName || '');
            
            // Adjust modal size based on image dimensions after it loads
            modalImage.on('load', function() {
                const img = this;
                const imgWidth = img.naturalWidth;
                const imgHeight = img.naturalHeight;
                
                // Set modal width based on image aspect ratio
                if (imgWidth > imgHeight) {
                    // Landscape image
                    modal.find('.modal-dialog').css('max-width', Math.min(imgWidth + 30, window.innerWidth * 0.9) + 'px');
                } else {
                    // Portrait image
                    modal.find('.modal-dialog').css('max-width', Math.min(imgWidth * 1.2 + 30, window.innerWidth * 0.8) + 'px');
                }
            });
        });
        
        // Video modal event handler
        $('#videoModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const videoSrc = button.data('video-src');
            const videoName = button.data('video-name');
            
            const modal = $(this);
            const modalVideo = modal.find('#video-modal-video');
            const modalSource = modal.find('#video-modal-source');
            
            // Set the video source
            modalSource.attr('src', videoSrc);
            
            // Set the video name
            modal.find('#video-modal-name').text(videoName || '');
            
            // Reload the video to apply the new source
            modalVideo[0].load();
            
            // Adjust modal size based on video dimensions after metadata loads
            modalVideo.on('loadedmetadata', function() {
                const video = this;
                const videoWidth = video.videoWidth;
                const videoHeight = video.videoHeight;
                
                // Set modal width based on video aspect ratio
                if (videoWidth > videoHeight) {
                    // Landscape video
                    modal.find('.modal-dialog').css('max-width', Math.min(videoWidth + 30, window.innerWidth * 0.9) + 'px');
                } else {
                    // Portrait video
                    modal.find('.modal-dialog').css('max-width', Math.min(videoWidth * 1.2 + 30, window.innerWidth * 0.8) + 'px');
                }
            });
        });
        
        // Reset modal size when closed
        $('.modal').on('hidden.bs.modal', function () {
            $(this).find('.modal-dialog').css('max-width', '');
        });
        
        // Initialize item counter
        let itemCounter = <?php echo count($items); ?>;
        
        // Add new item button click handler
        $('#add-item-btn').on('click', function() {
            addNewItem();
        });
        
        // Remove item button click handler
        $(document).on('click', '.remove-item-btn', function() {
            $(this).closest('.claim-item').remove();
        });
        
        // Function to add a new item
        function addNewItem() {
            const index = itemCounter++;
            const template = `
                <div class="claim-item card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Item #${index + 1}</h6>
                        <button type="button" class="btn btn-sm btn-danger remove-item-btn">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="item_sku_${index}" class="form-label">SKU</label>
                                <input type="text" class="form-control" id="item_sku_${index}" name="item_sku[]" required>
                            </div>
                            <div class="col-md-6">
                                <label for="item_product_name_${index}" class="form-label">Product Name</label>
                                <input type="text" class="form-control" id="item_product_name_${index}" name="item_product_name[]" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="item_product_type_${index}" class="form-label">Product Type</label>
                                <input type="text" class="form-control" id="item_product_type_${index}" name="item_product_type[]" required>
                            </div>
                            <div class="col-md-6">
                                <label for="item_category_id_${index}" class="form-label">Category</label>
                                <select class="form-select" id="item_category_id_${index}" name="category_id[]" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="item_description_${index}" class="form-label">Description</label>
                            <textarea class="form-control" id="item_description_${index}" name="description[]" rows="3" required></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="photos_${index}" class="form-label">Photos</label>
                                <input type="file" class="form-control" id="photos_${index}" name="photos_${index}[]" multiple accept="image/*">
                                <div class="form-text">Max size: 2MB per image</div>
                                <div class="invalid-feedback" id="photos_${index}_feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="videos_${index}" class="form-label">Videos</label>
                                <input type="file" class="form-control" id="videos_${index}" name="videos_${index}[]" multiple accept="video/mp4,video/quicktime">
                                <div class="form-text">Max size: 10MB (MP4/MOV only)</div>
                                <div class="invalid-feedback" id="videos_${index}_feedback"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#claim-items').append(template);
        }
        
        // Note: The add note functionality is now handled by claim-notes.js
        // This prevents duplicate event handlers and AJAX calls
        
        // Function to show alert messages
        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // Add alert to the page
            let alertContainer = $('#alert-container');
            if (alertContainer.length === 0) {
                // Create the alert container if it doesn't exist
                $('body').prepend('<div id="alert-container" class="container mt-3"></div>');
                alertContainer = $('#alert-container');
            }
            
            alertContainer.html(alertHtml);
            
            // Scroll to alert if it's visible
            if (alertContainer.is(':visible')) {
                $('html, body').animate({
                    scrollTop: alertContainer.offset().top - 100
                }, 200);
            }
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        }
        
        // File validation
        function validateFiles(fileInput, maxSize, allowedExtensions, fileType) {
            const files = fileInput.files;
            const errors = [];
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileSize = file.size;
                const fileName = file.name;
                const fileExt = fileName.split('.').pop().toLowerCase();
                
                // Check file size
                if (fileSize > maxSize) {
                    const maxSizeMB = maxSize / (1024 * 1024);
                    errors.push(`${fileType} '${fileName}' exceeds the maximum size of ${maxSizeMB}MB.`);
                }
                
                // Check file extension
                if (allowedExtensions && !allowedExtensions.includes(fileExt)) {
                    errors.push(`${fileType} '${fileName}' has an invalid extension. Allowed: ${allowedExtensions.join(', ')}`);
                }
            }
            
            return errors;
        }
        
        // Form submission validation
        $('#edit-claim-form').on('submit', function(e) {
            let hasErrors = false;
            let errorMessages = [];
            
            // Validate photos (max 2MB)
            $('input[type="file"][name^="photos_"]').each(function() {
                if (this.files.length > 0) {
                    const photoErrors = validateFiles(this, 2 * 1024 * 1024, null, 'Photo');
                    if (photoErrors.length > 0) {
                        hasErrors = true;
                        errorMessages = errorMessages.concat(photoErrors);
                    }
                }
            });
            
            // Validate videos (max 10MB, mp4/mov only)
            $('input[type="file"][name^="videos_"]').each(function() {
                if (this.files.length > 0) {
                    const videoErrors = validateFiles(this, 10 * 1024 * 1024, ['mp4', 'mov'], 'Video');
                    if (videoErrors.length > 0) {
                        hasErrors = true;
                        errorMessages = errorMessages.concat(videoErrors);
                    }
                }
            });
            
            // Show errors and prevent form submission if validation fails
            if (hasErrors) {
                e.preventDefault();
                
                // Create error alert
                let errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                errorHtml += '<strong>Error:</strong> Please fix the following issues:<ul>';
                
                errorMessages.forEach(function(error) {
                    errorHtml += `<li>${error}</li>`;
                });
                
                errorHtml += '</ul><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                
                // Show error alert
                $('#alert-container').html(errorHtml);
                
                // Scroll to alert
                $('html, body').animate({
                    scrollTop: $('#alert-container').offset().top - 100
                }, 200);
                
                return false;
            }
            
            return true;
        });
        
        // End of form validation
        });

        // Delete media button click handler
$(document).on('click', '.delete-media-btn', function(e) {
    e.preventDefault();
    const mediaId = $(this).data('media-id');
    const mediaType = $(this).data('media-type');
    const filePath = $(this).data('file-path');
    
    // Confirm deletion
    if (!confirm(`Are you sure you want to delete this ${mediaType}?`)) {
        return;
    }
    
    // Show loading state
    $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...');
    $(this).prop('disabled', true);
    
    $.ajax({
        url: 'ajax/delete_media.php',
        type: 'POST',
        data: {
            media_id: mediaId,
            file_path: filePath
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Show success message
                showAlert('success', response.message);
                
                // Remove the media element
                $(`.delete-media-btn[data-media-id="${mediaId}"]`).closest('.col-md-3').remove();
            } else {
                // Show error message
                showAlert('danger', response.message || 'An error occurred while deleting the media.');
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error:", status, error);
            
            // Show error message
            showAlert('danger', 'An error occurred while deleting the media. Please try again.');
        },
        complete: function() {
            // Reset button state
            $(`.delete-media-btn[data-media-id="${mediaId}"]`).html('<i class="fas fa-trash-alt"></i> Delete');
            $(`.delete-media-btn[data-media-id="${mediaId}"]`).prop('disabled', false);
        }
    });
});

// Status change handler
$('#status').on('change', function() {
    const status = $(this).val();
    
    // Show/hide new SKUs field based on status
    if (status === 'approved') {
        $('#new_sku_container').show();
    } else {
        $('#new_sku_container').hide();
    }
});

// Manual Order Modal Functionality
$(document).ready(function() {
    // Add SKU row
    $(document).on('click', '.add-sku-btn', function() {
        const newRow = `
            <div class="sku-row mb-3">
                <div class="row">
                    <div class="col-md-9">
                        <div class="input-group">
                            <span class="input-group-text">SKU</span>
                            <input type="text" class="form-control sku-input" placeholder="Enter SKU (e.g. TRC-DRIVER-TOY185-B-BL)">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-primary add-sku-btn">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="btn btn-danger remove-sku-btn">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#sku-container').append(newRow);
        updateSkuButtons();
    });
    
    // Remove SKU row
    $(document).on('click', '.remove-sku-btn', function() {
        $(this).closest('.sku-row').remove();
        updateSkuButtons();
    });
    
    // Update buttons visibility
    function updateSkuButtons() {
        const rows = $('.sku-row');
        if (rows.length === 1) {
            rows.find('.remove-sku-btn').hide();
        } else {
            $('.remove-sku-btn').show();
        }
    }
    
    // Show alert in the manual order modal
    function showManualOrderAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $('#manual-order-alert-container').html(alertHtml);
    }
    
    // Store verified SKUs
    let verifiedSkus = [];
    
    // Verify SKUs
    $('#verify-skus-btn').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        const skus = [];
        let hasEmptySku = false;
        
        // Reset verified SKUs
        verifiedSkus = [];
        
        // Hide Create Order button
        $('#create-order-btn').hide();
        
        // Collect all SKUs
        $('.sku-input').each(function() {
            const sku = $(this).val().trim();
            if (sku === '') {
                hasEmptySku = true;
            } else {
                skus.push(sku);
            }
        });
        
        if (hasEmptySku) {
            showManualOrderAlert('danger', 'Please fill in all SKU fields or remove empty rows.');
            return;
        }
        
        if (skus.length === 0) {
            showManualOrderAlert('danger', 'Please add at least one SKU to verify.');
            return;
        }
        
        // Show loading state
        $btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Verifying...');
        $btn.prop('disabled', true);
        
        // Clear previous results
        $('#verification-results').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Verifying SKUs...</div>');
        
        // Verify each SKU with the API
        const verificationPromises = skus.map(sku => {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'ajax/verify_sku.php',
                    type: 'POST',
                    data: JSON.stringify({ sku: sku }),
                    contentType: 'application/json',
                    success: function(response) {
                        resolve({ sku: sku, response: response });
                    },
                    error: function(xhr) {
                        reject({ sku: sku, error: xhr.responseText || 'Network error' });
                    }
                });
            });
        });
        
        // Process all verification results
        Promise.allSettled(verificationPromises)
            .then(results => {
                let resultsHtml = '';
                let hasSuccess = false;
                
                results.forEach(result => {
                    if (result.status === 'fulfilled') {
                        const data = result.value;
                        const response = data.response;
                        
                        if (response.success) {
                            hasSuccess = true;
                            const skuData = response.data;
                            
                            // Store verified SKU data for order creation
                            verifiedSkus.push({
                                skuNo: skuData.storageClientSkuNo,
                                skuDesc: skuData.skuDesc || '',
                                orderQty: 1,
                                itemCostPrice: 0,
                                itemSalesPrice: 0.00,
                                itemWeight: 1.00,
                                itemHeight: 1.00,
                                itemWidth: 1.00,
                                itemLength: 1.00
                            });
                            
                            resultsHtml += `
                                <div class="card mb-3 border-success">
                                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-check-circle me-2"></i> ${data.sku}</span>
                                        <span class="badge bg-light text-dark">${skuData.skuStatus || 'ACTIVE'}</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <p class="mb-1"><strong>Storage Client:</strong> ${skuData.storageClientNo || 'BOT1545'}</p>
                                                <p class="mb-1"><strong>Country:</strong> ${skuData.country || 'MALAYSIA'}</p>
                                                <p class="mb-1"><strong>Description:</strong> ${skuData.skuDesc || 'N/A'}</p>
                                                <p class="mb-0"><strong>Available Quantity:</strong> ${skuData.availableQty || '0'}</p>
                                            </div>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-verified-sku" data-sku="${data.sku}">
                                                    <i class="fas fa-times"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            resultsHtml += `
                                <div class="card mb-3 border-danger">
                                    <div class="card-header bg-danger text-white">
                                        <i class="fas fa-times-circle me-2"></i> ${data.sku}
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0 text-danger">${response.message || 'SKU verification failed'}</p>
                                    </div>
                                </div>
                            `;
                        }
                    } else {
                        resultsHtml += `
                            <div class="card mb-3 border-danger">
                                <div class="card-header bg-danger text-white">
                                    <i class="fas fa-times-circle me-2"></i> ${result.reason.sku}
                                </div>
                                <div class="card-body">
                                    <p class="mb-0 text-danger">Error: ${result.reason.error}</p>
                                </div>
                            </div>
                        `;
                    }
                });
                
                // Display results
                $('#verification-results').html(resultsHtml);
                
                // Show appropriate message and Create Order button if successful
                if (hasSuccess) {
                    showManualOrderAlert('success', 'SKU verification completed. See results below.');
                    
                    // Store verified SKUs in session
                    $.ajax({
                        url: 'ajax/store_verified_skus.php',
                        type: 'POST',
                        data: JSON.stringify({ verified_skus: verifiedSkus }),
                        contentType: 'application/json',
                        success: function(response) {
                            if (response.success) {
                                // Show Create Order button
                                $('#create-order-btn').show();
                            } else {
                                showManualOrderAlert('warning', 'Could not store verified SKUs: ' + response.message);
                            }
                        },
                        error: function() {
                            showManualOrderAlert('warning', 'Could not store verified SKUs. Order creation may not work properly.');
                        }
                    });
                } else {
                    showManualOrderAlert('danger', 'No SKUs were successfully verified. Please check the details and try again.');
                }
            })
            .catch(error => {
                console.error('Verification error:', error);
                showManualOrderAlert('danger', 'An error occurred during verification. Please try again.');
                $('#verification-results').html('<div class="alert alert-danger">Verification failed. Please try again.</div>');
            })
            .finally(() => {
                // Reset button state
                $btn.html(originalText);
                $btn.prop('disabled', false);
            });
    });
    
    // Remove verified SKU
    $(document).on('click', '.remove-verified-sku', function() {
        const sku = $(this).data('sku');
        
        // Remove from verifiedSkus array
        verifiedSkus = verifiedSkus.filter(item => item.skuNo !== sku);
        
        // Remove from UI
        $(this).closest('.card').fadeOut(300, function() {
            $(this).remove();
            
            // Update session
            $.ajax({
                url: 'ajax/store_verified_skus.php',
                type: 'POST',
                data: JSON.stringify({ verified_skus: verifiedSkus }),
                contentType: 'application/json'
            });
            
            // Hide Create Order button if no verified SKUs remain
            if (verifiedSkus.length === 0) {
                $('#create-order-btn').hide();
                showManualOrderAlert('warning', 'All verified SKUs have been removed. Please verify new SKUs.');
            }
        });
    });
    
    // Create Order button click handler
    $('#create-order-btn').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        
        // Show loading state
        $btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Creating Order...');
        $btn.prop('disabled', true);
        
        // Get claim ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const claimId = urlParams.get('id');
        
        // Create order via AJAX
        $.ajax({
            url: 'ajax/create_manual_order.php',
            type: 'POST',
            data: JSON.stringify({ claim_id: claimId }),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    showManualOrderAlert('success', `Order created successfully! Document No: ${response.document_no}`);
                    
                    // Disable Create Order button after successful creation
                    $btn.html('<i class="fas fa-check me-1"></i> Order Created');
                    $btn.removeClass('btn-primary').addClass('btn-success');
                    $btn.prop('disabled', true);
                    
                    // Clear verified SKUs
                    verifiedSkus = [];
                    
                    // Show success message for 2 seconds, then close modal and refresh page
                    setTimeout(function() {
                        // Close the modal
                        $('#manualOrderModal').modal('hide');
                        
                        // Show a success message at the top of the page
                        const alertHtml = `
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <strong>Success!</strong> Manual order created successfully with document number: ${response.document_no}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        $('#alert-container').html(alertHtml);
                        
                        // Refresh the page after a short delay to show the updated button state
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    }, 2000);
                } else {
                    showManualOrderAlert('danger', 'Failed to create order: ' + response.message);
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            },
            error: function(xhr) {
                let errorMsg = 'An error occurred while creating the order.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch (e) {}
                
                showManualOrderAlert('danger', errorMsg);
                $btn.html(originalText);
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Status change handler
    $('#status').on('change', function() {
        const status = $(this).val();
        
        // Show/hide new SKUs field based on status
        if (status === 'approved') {
            $('#new_sku_container').show();
        } else {
            $('#new_sku_container').hide();
        }
    });
});
</script>

<!-- Manual Order Modal -->
<div class="modal fade" id="manualOrderModal" tabindex="-1" aria-labelledby="manualOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manualOrderModalLabel">Add Manual Order SKUs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Add SKUs to verify with ODIN API. Click the plus button to add multiple SKUs.
                </div>
                <div id="manual-order-alert-container"></div>
                <div id="sku-container">
                    <div class="sku-row mb-3">
                        <div class="row">
                            <div class="col-md-9">
                                <div class="input-group">
                                    <span class="input-group-text">SKU</span>
                                    <input type="text" class="form-control sku-input" placeholder="Enter SKU (e.g. TRC-DRIVER-TOY185-B-BL)">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-primary add-sku-btn">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button type="button" class="btn btn-danger remove-sku-btn" style="display: none;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" id="verify-skus-btn">
                        <i class="fas fa-check me-1"></i> Verify SKUs
                    </button>
                </div>
                <div class="mt-4">
                    <h6>Verification Results</h6>
                    <div id="verification-results" class="mt-2">
                        <div class="alert alert-secondary">
                            No SKUs verified yet. Click the Verify SKUs button to check availability.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" id="create-order-btn" class="btn btn-primary" style="display: none;">
                        <i class="fas fa-shopping-cart me-1"></i> Create Order
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
