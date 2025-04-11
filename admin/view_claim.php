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
    $query = "SELECT c.* FROM claims c WHERE c.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$claimId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$claim) {
        // Claim not found, redirect to claims page
        header('Location: claims.php');
        exit;
    }
    
    // Get claim items with category information
    $itemsQuery = "SELECT ci.*, cc.name as category_name, cc.sla_days 
                  FROM claim_items ci
                  LEFT JOIN claim_categories cc ON ci.category_id = cc.id
                  WHERE ci.claim_id = ? 
                  ORDER BY ci.id";
    $stmt = $conn->prepare($itemsQuery);
    $stmt->execute([$claimId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get claim media with item association
    $mediaQuery = "SELECT cm.*, ci.sku 
                  FROM claim_media cm
                  LEFT JOIN claim_items ci ON cm.claim_item_id = ci.id
                  WHERE cm.claim_id = ? 
                  ORDER BY cm.file_type, cm.created_at";
    $stmt = $conn->prepare($mediaQuery);
    $stmt->execute([$claimId]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <h1>
        <?php if (!empty($claim['claim_number'])): ?>
            View Claim <span class="badge bg-info"><?php echo htmlspecialchars($claim['claim_number']); ?></span>
        <?php else: ?>
            View Claim #<?php echo $claimId; ?>
        <?php endif; ?>
    </h1>
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
                        <td>
                            <?php if (!empty($claim['claim_number'])): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($claim['claim_number']); ?></span>
                            <?php else: ?>
                                #<?php echo $claim['id']; ?>
                            <?php endif; ?>
                        </td>
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
                    <?php if (!empty($items)): ?>
                    <tr>
                        <th>SLA Days:</th>
                        <td>
                            <?php 
                            // Get unique SLA days from items
                            $slaDays = array_unique(array_column($items, 'sla_days'));
                            echo implode(', ', array_filter($slaDays)) ?: 'N/A'; 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>SLA Status:</th>
                        <td>
                            <?php
                            // Calculate SLA deadline based on first item with SLA days
                            $slaDays = 0;
                            foreach ($items as $item) {
                                if (!empty($item['sla_days'])) {
                                    $slaDays = (int)$item['sla_days'];
                                    break;
                                }
                            }
                            
                            // Calculate SLA deadline
                            $createdDate = new DateTime($claim['created_at']);
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
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>SLA Deadline:</th>
                        <td><?php echo $deadline->format('M d, Y'); ?></td>
                    </tr>
                    <?php endif; ?>
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
                        <th>Delivery Date:</th>
                        <td><?php echo date('M d, Y', strtotime($claim['delivery_date'])); ?></td>
                    </tr>
                </table>
                
                <h6 class="border-bottom pb-2 mb-3 mt-4">Customer Information</h6>
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
                                <th>Product Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Added On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['product_type'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($item['category_name'])): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['description'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="popover" data-bs-trigger="focus" title="Description" data-bs-content="<?php echo htmlspecialchars($item['description']); ?>">
                                            View
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
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
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="border-bottom pb-2 mb-3">Claim Media</h6>
                
                <?php 
                // Group media by claim item ID and SKU
                $mediaByItem = [];
                
                foreach ($media as $mediaItem) {
                    $itemId = $mediaItem['claim_item_id'] ?? 0;
                    if (!isset($mediaByItem[$itemId])) {
                        $mediaByItem[$itemId] = [
                            'sku' => $mediaItem['sku'] ?? 'Unknown',
                            'media' => []
                        ];
                    }
                    $mediaByItem[$itemId]['media'][] = $mediaItem;
                }
                
                // Display media grouped by item
                foreach ($mediaByItem as $itemId => $itemData): 
                    $sku = $itemData['sku'];
                    $itemMedia = $itemData['media'];
                ?>
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="text-primary mb-3">
                            <?php if ($itemId > 0): ?>
                                Media for Item: <?php echo htmlspecialchars($sku); ?>
                            <?php else: ?>
                                General Claim Media
                            <?php endif; ?>
                        </h6>
                        
                        <?php 
                        // Get all media items (photos and videos)
                        $hasMedia = !empty($itemMedia);
                        
                        if ($hasMedia): 
                        ?>
                        <div class="row">
                            <?php foreach ($itemMedia as $item): ?>
                                <?php if ($item['file_type'] === 'photo'): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 shadow-sm">
                                        <?php 
                                        // Construct the correct file path
                                        $photoPath = "/warranty-management-system/uploads/claims/{$claimId}/items/{$itemId}/photos/" . basename(htmlspecialchars($item['file_path']));
                                        
                                        // Check if file exists, otherwise use a placeholder
                                        $serverPath = $_SERVER['DOCUMENT_ROOT'] . $photoPath;
                                        $imgSrc = file_exists($serverPath) ? $photoPath : "/warranty-management-system/assets/img/placeholder-image.png";
                                        ?>
                                        <a href="<?php echo $imgSrc; ?>" target="_blank" class="image-link">
                                            <img src="<?php echo $imgSrc; ?>" class="card-img-top" alt="Claim Photo" style="height: 180px; object-fit: cover;">
                                        </a>
                                        <div class="card-body p-2">
                                            <p class="card-text small text-muted mb-0"><?php echo htmlspecialchars($item['original_filename']); ?></p>
                                            <p class="card-text small text-muted"><?php echo date('M d, Y', strtotime($item['created_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php elseif ($item['file_type'] === 'video'): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 shadow-sm">
                                        <?php 
                                        // Construct the correct file path
                                        $videoPath = "/warranty-management-system/uploads/claims/{$claimId}/items/{$itemId}/videos/" . basename(htmlspecialchars($item['file_path']));
                                        
                                        // Check if file exists
                                        $serverPath = $_SERVER['DOCUMENT_ROOT'] . $videoPath;
                                        $videoExists = file_exists($serverPath);
                                        ?>
                                        
                                        <?php if ($videoExists): ?>
                                        <video class="w-100" controls style="height: 200px; object-fit: cover;">
                                            <source src="<?php echo $videoPath; ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                        <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                            <p class="text-muted"><i class="fas fa-video fa-2x mb-2"></i><br>Video not found</p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="card-body p-2">
                                            <p class="card-text small text-muted mb-0"><?php echo htmlspecialchars($item['original_filename']); ?></p>
                                            <p class="card-text small text-muted"><?php echo date('M d, Y', strtotime($item['created_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            No media files found for this item.
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($mediaByItem)): ?>
                <div class="alert alert-info">
                    No media files have been uploaded for this claim.
                </div>
                <?php endif; ?>
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
                                <th>New SKUs</th>
                                <th>Created By</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notes as $index => $note): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo nl2br(htmlspecialchars($note['note'])); ?></td>
                                <td><?php echo !empty($note['new_skus']) ? htmlspecialchars($note['new_skus']) : '-'; ?></td>
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

<!-- Initialize popovers -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                html: true,
                sanitize: false
            })
        })
    });
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
