<?php
/**
 * Edit Claim
 * 
 * This file allows administrators to edit an existing warranty claim.
 */

// Set page title
$pageTitle = 'Edit Claim';

// Include header
require_once 'includes/header.php';

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
$successMessage = null;
$errorMessage = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_claim') {
    try {
        // Get form data
        $customerName = trim($_POST['customer_name']);
        $customerEmail = trim($_POST['customer_email']);
        $customerPhone = trim($_POST['customer_phone']);
        $categoryId = (int)$_POST['category_id'];
        $sku = trim($_POST['sku']);
        $productType = trim($_POST['product_type']);
        $deliveryDate = trim($_POST['delivery_date']);
        $description = trim($_POST['description']);
        $status = trim($_POST['status']);
        
        // Validate required fields
        $errors = [];
        
        if (empty($customerName)) {
            $errors[] = 'Customer name is required.';
        }
        
        if (empty($customerEmail)) {
            $errors[] = 'Customer email is required.';
        } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }
        
        if (empty($sku)) {
            $errors[] = 'SKU is required.';
        }
        
        if (empty($productType)) {
            $errors[] = 'Product type is required.';
        }
        
        if (empty($deliveryDate)) {
            $errors[] = 'Delivery date is required.';
        }
        
        if (empty($description)) {
            $errors[] = 'Description is required.';
        }
        
        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }
        
        // Update claim in database
        $updateQuery = "UPDATE claims SET 
                        customer_name = ?,
                        customer_email = ?,
                        customer_phone = ?,
                        category_id = ?,
                        sku = ?,
                        product_type = ?,
                        delivery_date = ?,
                        description = ?,
                        status = ?
                        WHERE id = ?";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->execute([
            $customerName,
            $customerEmail,
            $customerPhone,
            $categoryId,
            $sku,
            $productType,
            $deliveryDate,
            $description,
            $status,
            $claimId
        ]);
        
        // Process photo uploads if any
        if (!empty($_FILES['photos']['name'][0])) {
            $uploadDir = '../uploads/claims/' . $claimId . '/photos/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Loop through each uploaded photo
            foreach ($_FILES['photos']['name'] as $key => $name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['photos']['tmp_name'][$key];
                    $originalName = $_FILES['photos']['name'][$key];
                    $fileSize = $_FILES['photos']['size'][$key];
                    $fileType = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    
                    // Generate unique filename
                    $newFileName = uniqid('photo_') . '.' . $fileType;
                    $targetFilePath = $uploadDir . $newFileName;
                    
                    // Move uploaded file
                    if (move_uploaded_file($tmpName, $targetFilePath)) {
                        // Save file info to database
                        $mediaQuery = "INSERT INTO claim_media (claim_id, file_path, file_type, original_filename, file_size) 
                                      VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($mediaQuery);
                        $stmt->execute([
                            $claimId,
                            $targetFilePath,
                            'photo',
                            $originalName,
                            $fileSize
                        ]);
                    }
                }
            }
        }
        
        // Process video uploads if any
        if (!empty($_FILES['videos']['name'][0])) {
            $uploadDir = '../uploads/claims/' . $claimId . '/videos/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Loop through each uploaded video
            foreach ($_FILES['videos']['name'] as $key => $name) {
                if ($_FILES['videos']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['videos']['tmp_name'][$key];
                    $originalName = $_FILES['videos']['name'][$key];
                    $fileSize = $_FILES['videos']['size'][$key];
                    $fileType = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    
                    // Generate unique filename
                    $newFileName = uniqid('video_') . '.' . $fileType;
                    $targetFilePath = $uploadDir . $newFileName;
                    
                    // Move uploaded file
                    if (move_uploaded_file($tmpName, $targetFilePath)) {
                        // Save file info to database
                        $mediaQuery = "INSERT INTO claim_media (claim_id, file_path, file_type, original_filename, file_size) 
                                      VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($mediaQuery);
                        $stmt->execute([
                            $claimId,
                            $targetFilePath,
                            'video',
                            $originalName,
                            $fileSize
                        ]);
                    }
                }
            }
        }
        
        // Set success message
        $successMessage = 'Claim updated successfully.';
        
    } catch (Exception $e) {
        // Set error message
        $errorMessage = $e->getMessage();
    }
}

// Get claim details
$claim = null;
try {
    $query = "SELECT c.* FROM claims c WHERE c.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$claimId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$claim) {
        // Claim not found, redirect to claims page
        header('Location: claims.php');
        exit;
    }
    
    // Get claim media
    $mediaQuery = "SELECT * FROM claim_media WHERE claim_id = ? ORDER BY file_type, created_at";
    $stmt = $conn->prepare($mediaQuery);
    $stmt->execute([$claimId]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get claim items
    $itemsQuery = "SELECT * FROM claim_items WHERE claim_id = ? ORDER BY id";
    $stmt = $conn->prepare($itemsQuery);
    $stmt->execute([$claimId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get claim notes
    $notesQuery = "SELECT cn.*, u.username as created_by_name 
                  FROM claim_notes cn
                  LEFT JOIN users u ON cn.created_by = u.id
                  WHERE cn.claim_id = ? 
                  ORDER BY cn.created_at DESC";
    $stmt = $conn->prepare($notesQuery);
    $stmt->execute([$claimId]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for dropdown
    $categoriesQuery = "SELECT id, name FROM claim_categories ORDER BY name";
    $stmt = $conn->prepare($categoriesQuery);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Log error
    error_log("Error fetching claim details: " . $e->getMessage());
    
    // Set error message
    $errorMessage = "An error occurred while fetching claim details. Please try again.";
}
?>

<div class="page-title">
    <h1>Edit Claim #<?php echo $claimId; ?></h1>
    <div class="button-container">
        <a href="view_claim.php?id=<?php echo $claimId; ?>" class="btn btn-primary">
            <i class="fas fa-eye me-1"></i> View Claim
        </a>
        <a href="claims.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Claims
        </a>
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

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title">Edit Claim</h5>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="edit_claim.php?id=<?php echo $claimId; ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="update_claim">
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Order Information</h6>
                    <div class="mb-3">
                        <label for="order_id" class="form-label">Order ID</label>
                        <input type="text" class="form-control" id="order_id" name="order_id" value="<?php echo htmlspecialchars($claim['order_id']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($claim['sku']); ?>" required>
                        <div class="invalid-feedback">SKU is required.</div>
                    </div>
                    <div class="mb-3">
                        <label for="product_type" class="form-label">Product Type <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="product_type" name="product_type" value="<?php echo htmlspecialchars($claim['product_type']); ?>" required>
                        <div class="invalid-feedback">Product type is required.</div>
                    </div>
                    <div class="mb-3">
                        <label for="delivery_date" class="form-label">Delivery Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="delivery_date" name="delivery_date" value="<?php echo htmlspecialchars($claim['delivery_date']); ?>" required>
                        <div class="invalid-feedback">Delivery date is required.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Customer Information</h6>
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($claim['customer_name']); ?>" required>
                        <div class="invalid-feedback">Customer name is required.</div>
                    </div>
                    <div class="mb-3">
                        <label for="customer_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="customer_email" name="customer_email" value="<?php echo htmlspecialchars($claim['customer_email']); ?>" required>
                        <div class="invalid-feedback">Valid email address is required.</div>
                    </div>
                    <div class="mb-3">
                        <label for="customer_phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="customer_phone" name="customer_phone" value="<?php echo htmlspecialchars($claim['customer_phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Claim Details</h6>
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Claim Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($claim['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a category.</div>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="new" <?php echo ($claim['status'] === 'new') ? 'selected' : ''; ?>>New</option>
                            <option value="in_progress" <?php echo ($claim['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="on_hold" <?php echo ($claim['status'] === 'on_hold') ? 'selected' : ''; ?>>On Hold</option>
                            <option value="approved" <?php echo ($claim['status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo ($claim['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                        <div class="invalid-feedback">Please select a status.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Claim Description</h6>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($claim['description']); ?></textarea>
                        <div class="invalid-feedback">Description is required.</div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Add Photos</h6>
                    <div class="mb-3">
                        <label for="photos" class="form-label">Upload Photos (Optional)</label>
                        <input type="file" class="form-control" id="photos" name="photos[]" multiple accept="image/*">
                        <div class="form-text">You can upload multiple photos. Max file size: 5MB each.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Add Videos</h6>
                    <div class="mb-3">
                        <label for="videos" class="form-label">Upload Videos (Optional)</label>
                        <input type="file" class="form-control" id="videos" name="videos[]" multiple accept="video/*">
                        <div class="form-text">You can upload multiple videos. Max file size: 20MB each.</div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($media)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h6 class="border-bottom pb-2 mb-3">Existing Media</h6>
                    
                    <div class="row">
                        <?php foreach ($media as $item): ?>
                            <?php if ($item['file_type'] === 'photo'): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <img src="/warranty-management-system/uploads/claims/<?php echo $claimId; ?>/photos/<?php echo basename(htmlspecialchars($item['file_path'])); ?>" class="card-img-top" alt="Claim Photo">
                                    <div class="card-body p-2">
                                        <p class="card-text small text-muted mb-2"><?php echo htmlspecialchars($item['original_filename']); ?></p>
                                        <a href="delete_media.php?id=<?php echo $item['id']; ?>&claim_id=<?php echo $claimId; ?>" class="btn btn-sm btn-danger w-100" onclick="return confirm('Are you sure you want to delete this photo?')">
                                            <i class="fas fa-trash-alt me-1"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <video class="w-100" controls>
                                        <source src="/warranty-management-system/uploads/claims/<?php echo $claimId; ?>/videos/<?php echo basename(htmlspecialchars($item['file_path'])); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                    <div class="card-body p-2">
                                        <p class="card-text small text-muted mb-2"><?php echo htmlspecialchars($item['original_filename']); ?></p>
                                        <a href="delete_media.php?id=<?php echo $item['id']; ?>&claim_id=<?php echo $claimId; ?>" class="btn btn-sm btn-danger w-100" onclick="return confirm('Are you sure you want to delete this video?')">
                                            <i class="fas fa-trash-alt me-1"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($notes)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="border-bottom pb-2 mb-3">Claim Notes</h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                <i class="fas fa-plus me-1"></i> Add Note
                            </button>
                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                <i class="fas fa-sync-alt me-1"></i> Update Status
                            </button>
                        </div>
                    </div>
                    
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
                                    <td><?php echo nl2br(htmlspecialchars($note['note'])); ?></td>
                                    <td><?php echo htmlspecialchars($note['created_by_name'] ?? 'System'); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($note['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="border-bottom pb-2 mb-3">Claim Notes</h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                <i class="fas fa-plus me-1"></i> Add Note
                            </button>
                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                <i class="fas fa-sync-alt me-1"></i> Update Status
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        No notes have been added to this claim yet.
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="claims.php" class="btn btn-secondary me-md-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Claim</button>
            </div>
        </form>
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

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel">Update Claim Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateStatusForm">
                    <input type="hidden" name="claim_id" value="<?php echo $claimId; ?>">
                    <div class="mb-3">
                        <label for="claim_status" class="form-label">Status</label>
                        <select class="form-select" id="claim_status" name="status" required>
                            <option value="new" <?php echo $claim['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="in_progress" <?php echo $claim['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="on_hold" <?php echo $claim['status'] === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                            <option value="approved" <?php echo $claim['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $claim['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status_note" class="form-label">Note (Optional)</label>
                        <textarea class="form-control" id="status_note" name="note" rows="3" placeholder="Add a note about this status change..."></textarea>
                        <div class="form-text">If left empty, a default note about the status change will be added.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary update-status-btn">Update Status</button>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<!-- Include jQuery if not already included -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Include claim notes JS -->
<script src="js/claim-notes.js"></script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
