<?php
/**
 * View Claim
 * 
 * This file allows administrators to view the details of a warranty claim.
 */

// Set page title
$pageTitle = 'View Claim';

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

// Get claim details
$claim = null;
try {
    $query = "SELECT c.*, cc.name as category_name 
              FROM claims c
              LEFT JOIN claim_categories cc ON c.category_id = cc.id
              WHERE c.id = ?";
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
    
} catch (PDOException $e) {
    // Log error
    error_log("Error fetching claim details: " . $e->getMessage());
    
    // Set error message
    $errorMessage = "An error occurred while fetching claim details. Please try again.";
}
?>

<div class="page-title">
    <h1>View Claim #<?php echo $claimId; ?></h1>
    <div class="button-container">
        <a href="edit_claim.php?id=<?php echo $claimId; ?>" class="btn btn-primary">
            <i class="fas fa-edit me-1"></i> Edit Claim
        </a>
        <a href="claims.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Claims
        </a>
    </div>
</div>

<?php if (isset($errorMessage)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($errorMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title">Claim Details</h5>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3">Claim Information</h6>
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 30%">Claim ID:</th>
                        <td>#<?php echo $claim['id']; ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
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
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?php echo htmlspecialchars($claim['category_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Created At:</th>
                        <td><?php echo date('M d, Y h:i A', strtotime($claim['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated:</th>
                        <td><?php echo $claim['updated_at'] ? date('M d, Y h:i A', strtotime($claim['updated_at'])) : 'N/A'; ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3">Order Information</h6>
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 30%">Order ID:</th>
                        <td><?php echo htmlspecialchars($claim['order_id']); ?></td>
                    </tr>
                    <tr>
                        <th>SKU:</th>
                        <td><?php echo htmlspecialchars($claim['sku']); ?></td>
                    </tr>
                    <tr>
                        <th>Product Type:</th>
                        <td><?php echo htmlspecialchars($claim['product_type']); ?></td>
                    </tr>
                    <tr>
                        <th>Delivery Date:</th>
                        <td><?php echo date('M d, Y', strtotime($claim['delivery_date'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3">Customer Information</h6>
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 30%">Name:</th>
                        <td><?php echo htmlspecialchars($claim['customer_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo htmlspecialchars($claim['customer_email']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone:</th>
                        <td><?php echo htmlspecialchars($claim['customer_phone'] ?? 'N/A'); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3">Claim Description</h6>
                <div class="p-3 bg-light rounded">
                    <?php echo nl2br(htmlspecialchars($claim['description'])); ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($items)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="border-bottom pb-2 mb-3">Claim Items</h6>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Added On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($item['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($media)): ?>
        <div class="row">
            <div class="col-12">
                <h6 class="border-bottom pb-2 mb-3">Claim Media</h6>
                
                <div class="row">
                    <?php foreach ($media as $item): ?>
                        <?php if ($item['file_type'] === 'photo'): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <img src="/warranty-management-system/uploads/claims/<?php echo $claimId; ?>/photos/<?php echo basename(htmlspecialchars($item['file_path'])); ?>" class="card-img-top" alt="Claim Photo">
                                <div class="card-body p-2">
                                    <p class="card-text small text-muted mb-0"><?php echo htmlspecialchars($item['original_filename']); ?></p>
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
                                    <p class="card-text small text-muted mb-0"><?php echo htmlspecialchars($item['original_filename']); ?></p>
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
                        <button type="button" class="btn btn-sm btn-info me-2" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                            <i class="fas fa-sync-alt me-1"></i> Update Status
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class="fas fa-plus me-1"></i> Add Note
                        </button>
                    </div>
                </div>
                <div class="alert alert-info">
                    No notes have been added to this claim yet.
                </div>
            </div>
        </div>
        <?php endif; ?>
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

<!-- Include jQuery if not already included -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Include claim notes JS -->
<script src="js/claim-notes.js"></script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
