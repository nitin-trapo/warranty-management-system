<?php
/**
 * Warranty Rules Management
 * 
 * This file allows administrators to manage warranty rules including
 * product types, durations, coverage details, and exclusions.
 */

// Set page title
$pageTitle = 'Warranty Rules';

// Include header
require_once 'includes/header.php';

// Include database connection
require_once '../config/database.php';

// Check if the warranty_rules table exists, if not create it
try {
    $conn = getDbConnection();
    
    // Check if table exists
    $tableExists = false;
    $stmt = $conn->query("SHOW TABLES LIKE 'warranty_rules'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
    }
    
    // Create table if it doesn't exist
    if (!$tableExists) {
        $createTableSQL = "
            CREATE TABLE `warranty_rules` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `product_type` varchar(100) NOT NULL,
              `duration` int(11) NOT NULL COMMENT 'Duration in months',
              `coverage` text NOT NULL COMMENT 'What is covered under warranty',
              `exclusions` text NOT NULL COMMENT 'What is not covered under warranty',
              `created_by` int(11) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `created_by` (`created_by`),
              CONSTRAINT `warranty_rules_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        
        $conn->exec($createTableSQL);
        
        // Insert sample data
        $sampleDataSQL = "
            INSERT INTO `warranty_rules` (`product_type`, `duration`, `coverage`, `exclusions`, `created_by`) VALUES
            ('Smartphones', 12, 'Manufacturing defects, Hardware failures, Battery issues (if battery capacity falls below 80%)', 'Physical damage, Water damage, Unauthorized repairs, Software issues', 1),
            ('Laptops', 24, 'Hardware failures, Display issues, Keyboard and touchpad malfunctions, Battery (first 12 months only)', 'Physical damage, Liquid damage, Normal wear and tear, Software issues, Consumable parts', 1),
            ('Home Appliances', 36, 'Motor failures, Electronic component failures, Manufacturing defects', 'Cosmetic damage, Misuse, Commercial use, Consumable parts, Installation issues', 1);
        ";
        
        $conn->exec($sampleDataSQL);
    }
    
    // Process form submission for adding/editing warranty rules
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            // Add new warranty rule
            if ($_POST['action'] === 'add') {
                try {
                    $productType = trim($_POST['product_type']);
                    $duration = (int)$_POST['duration'];
                    $coverage = trim($_POST['coverage']);
                    $exclusions = trim($_POST['exclusions']);
                    $userId = $_SESSION['user_id'];
                    
                    // Check if product type already exists
                    $checkStmt = $conn->prepare("SELECT id FROM warranty_rules WHERE product_type = ?");
                    $checkStmt->execute([$productType]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        throw new Exception("A warranty rule for product type '$productType' already exists. Please use a different product type or edit the existing rule.");
                    }
                    
                    $stmt = $conn->prepare("
                        INSERT INTO warranty_rules 
                        (product_type, duration, coverage, exclusions, created_by) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([$productType, $duration, $coverage, $exclusions, $userId]);
                    
                    // Set success message
                    $successMessage = "Warranty rule for '$productType' added successfully.";
                } catch (Exception $e) {
                    $errorMessage = $e->getMessage();
                }
            }
            
            // Edit existing warranty rule
            else if ($_POST['action'] === 'edit' && isset($_POST['id'])) {
                try {
                    $id = (int)$_POST['id'];
                    $productType = trim($_POST['product_type']);
                    $duration = (int)$_POST['duration'];
                    $coverage = trim($_POST['coverage']);
                    $exclusions = trim($_POST['exclusions']);
                    
                    // Check if product type already exists for a different rule
                    $checkStmt = $conn->prepare("SELECT id FROM warranty_rules WHERE product_type = ? AND id != ?");
                    $checkStmt->execute([$productType, $id]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        throw new Exception("A warranty rule for product type '$productType' already exists. Please use a different product type.");
                    }
                    
                    $stmt = $conn->prepare("
                        UPDATE warranty_rules 
                        SET product_type = ?, duration = ?, coverage = ?, exclusions = ? 
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([$productType, $duration, $coverage, $exclusions, $id]);
                    
                    // Set success message
                    $successMessage = "Warranty rule for '$productType' updated successfully.";
                } catch (Exception $e) {
                    $errorMessage = $e->getMessage();
                }
            }
            
            // Delete warranty rule
            else if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                
                $stmt = $conn->prepare("DELETE FROM warranty_rules WHERE id = ?");
                $stmt->execute([$id]);
                
                // Set success message
                $successMessage = "Warranty rule deleted successfully.";
            }
        }
    }
    
    // Get all warranty rules
    $stmt = $conn->query("
        SELECT wr.*, u.first_name, u.last_name 
        FROM warranty_rules wr
        LEFT JOIN users u ON wr.created_by = u.id
        ORDER BY wr.product_type ASC
    ");
    
    $warrantyRules = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Log error
    error_log("Error in warranty rules management: " . $e->getMessage());
    
    // Set error message
    $errorMessage = "Database error: " . $e->getMessage();
    
    // Initialize empty array
    $warrantyRules = [];
}
?>

<div class="page-title">
    <h1>Warranty Rules Management</h1>
    <div class="button-container">
        <button type="button" class="btn btn-primary add-rule-btn" data-bs-toggle="modal" data-bs-target="#addRuleModal">
            <i class="fas fa-plus me-1"></i> Add New Rule
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

<!-- Warranty Rules List -->
<div class="card mb-4">
    <div class="card-header py-3">
        <h6 class="mb-0">Warranty Rules</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="warrantyRulesTable" width="100%" cellspacing="0">
                <thead class="table-light">
                    <tr>
                        <th width="20%">Product Type</th>
                        <th width="10%">Duration</th>
                        <th width="20%">Coverage</th>
                        <th width="20%">Exclusions</th>
                        <th width="15%">Created By</th>
                        <th width="10%">Created At</th>
                        <th width="5%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($warrantyRules)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-3">No warranty rules found.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($warrantyRules as $rule): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($rule['product_type']); ?></td>
                        <td><?php echo $rule['duration'] == 0 ? 'Lifetime' : $rule['duration'] . ' months'; ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="popover" 
                                    data-bs-placement="top" 
                                    data-bs-trigger="focus" 
                                    title="Coverage Details" 
                                    data-bs-content="<?php echo htmlspecialchars($rule['coverage']); ?>">
                                View Details
                            </button>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    data-bs-toggle="popover" 
                                    data-bs-placement="top" 
                                    data-bs-trigger="focus" 
                                    title="Exclusion Details" 
                                    data-bs-content="<?php echo htmlspecialchars($rule['exclusions']); ?>">
                                View Details
                            </button>
                        </td>
                        <td><?php echo htmlspecialchars($rule['first_name'] . ' ' . $rule['last_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($rule['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary edit-rule" 
                                        data-id="<?php echo $rule['id']; ?>"
                                        data-product-type="<?php echo htmlspecialchars($rule['product_type']); ?>"
                                        data-duration="<?php echo $rule['duration']; ?>"
                                        data-coverage="<?php echo htmlspecialchars($rule['coverage']); ?>"
                                        data-exclusions="<?php echo htmlspecialchars($rule['exclusions']); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger delete-rule" 
                                        data-id="<?php echo $rule['id']; ?>"
                                        data-product-type="<?php echo htmlspecialchars($rule['product_type']); ?>">
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

<!-- Add Warranty Rule Modal -->
<div class="modal fade" id="addRuleModal" tabindex="-1" aria-labelledby="addRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addRuleModalLabel">Add New Warranty Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="product_type" class="form-label">Product Type</label>
                            <input type="text" class="form-control" id="product_type" name="product_type" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="duration" class="form-label">Duration (months)</label>
                            <input type="number" class="form-control" id="duration" name="duration" min="0" required>
                            <small class="text-muted">Enter number of months (1 year = 12, Lifetime = 0)</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="coverage" class="form-label">Coverage (What We Cover)</label>
                        <textarea class="form-control" id="coverage" name="coverage" rows="4" required></textarea>
                        <small class="text-muted">List items separated by commas or new lines</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="exclusions" class="form-label">Exclusions (What We Don't Cover)</label>
                        <textarea class="form-control" id="exclusions" name="exclusions" rows="4" required></textarea>
                        <small class="text-muted">List items separated by commas or new lines</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Warranty Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Warranty Rule Modal -->
<div class="modal fade" id="editRuleModal" tabindex="-1" aria-labelledby="editRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editRuleModalLabel">Edit Warranty Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_product_type" class="form-label">Product Type</label>
                            <input type="text" class="form-control" id="edit_product_type" name="product_type" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_duration" class="form-label">Duration (months)</label>
                            <input type="number" class="form-control" id="edit_duration" name="duration" min="0" required>
                            <small class="text-muted">Enter number of months (1 year = 12, Lifetime = 0)</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_coverage" class="form-label">Coverage (What We Cover)</label>
                        <textarea class="form-control" id="edit_coverage" name="coverage" rows="4" required></textarea>
                        <small class="text-muted">List items separated by commas or new lines</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_exclusions" class="form-label">Exclusions (What We Don't Cover)</label>
                        <textarea class="form-control" id="edit_exclusions" name="exclusions" rows="4" required></textarea>
                        <small class="text-muted">List items separated by commas or new lines</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Warranty Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Warranty Rule Modal -->
<div class="modal fade" id="deleteRuleModal" tabindex="-1" aria-labelledby="deleteRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteRuleModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <p>Are you sure you want to delete the warranty rule for <strong id="delete_product_type"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include footer -->
<?php require_once 'includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        $('#warrantyRulesTable').DataTable({
            order: [[0, 'asc']], // Sort by product type
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            responsive: true,
            columnDefs: [
                { className: "align-middle", targets: "_all" },
                { orderable: false, targets: [2, 3, 6] } // Disable sorting for coverage, exclusions and actions columns
            ],
            dom: '<"row mb-3"<"col-md-6"l><"col-md-6"f>>' +
                 '<"row"<"col-md-12"tr>>' +
                 '<"row mt-3"<"col-md-5"i><"col-md-7"p>>',
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search warranty rules...",
                lengthMenu: "_MENU_ rules per page",
                info: "Showing _START_ to _END_ of _TOTAL_ warranty rules",
                infoEmpty: "No warranty rules found",
                paginate: {
                    first: '<i class="fas fa-angle-double-left"></i>',
                    previous: '<i class="fas fa-angle-left"></i>',
                    next: '<i class="fas fa-angle-right"></i>',
                    last: '<i class="fas fa-angle-double-right"></i>'
                }
            }
        });
        
        // Initialize popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                html: true,
                sanitize: false,
                container: 'body'
            });
        });
        
        // Edit warranty rule
        document.querySelectorAll('.edit-rule').forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var productType = this.getAttribute('data-product-type');
                var duration = this.getAttribute('data-duration');
                var coverage = this.getAttribute('data-coverage');
                var exclusions = this.getAttribute('data-exclusions');
                
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_product_type').value = productType;
                document.getElementById('edit_duration').value = duration;
                document.getElementById('edit_coverage').value = coverage;
                document.getElementById('edit_exclusions').value = exclusions;
                
                var editModal = new bootstrap.Modal(document.getElementById('editRuleModal'));
                editModal.show();
            });
        });
        
        // Delete warranty rule
        document.querySelectorAll('.delete-rule').forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var productType = this.getAttribute('data-product-type');
                
                document.getElementById('delete_id').value = id;
                document.getElementById('delete_product_type').textContent = productType;
                
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteRuleModal'));
                deleteModal.show();
            });
        });
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
    #warrantyRulesTable {
        border-collapse: separate;
        border-spacing: 0;
    }
    
    #warrantyRulesTable th {
        font-weight: 600;
        padding: 12px 15px;
    }
    
    #warrantyRulesTable td {
        padding: 12px 15px;
        vertical-align: middle;
    }
    
    /* Button styling */
    .btn-group-sm > .btn {
        padding: 0.25rem 0.5rem;
    }
    
    /* Popover styling */
    .popover {
        max-width: 300px;
    }
    
    .popover-body {
        padding: 15px;
        white-space: pre-line;
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
    
    .add-rule-btn {
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
    
    .add-rule-btn:hover,
    .add-rule-btn:focus,
    .add-rule-btn:active {
        background-color: var(--secondary-color) !important;
        border-color: var(--secondary-color) !important;
        color: white !important;
        box-shadow: none !important;
        transform: none !important;
    }
</style>
