<?php
/**
 * Categories Management
 * 
 * This file allows administrators to manage warranty claim categories including
 * viewing, adding, editing, and deleting categories.
 */

// Set page title
$pageTitle = 'Categories Management';

// Include header
require_once 'includes/header.php';

// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

// Process form submissions
$successMessage = '';
$errorMessage = '';

// Add new category
if (isset($_POST['action']) && $_POST['action'] === 'add_category') {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            throw new Exception('Category name is required');
        }
        
        // Check if category already exists
        $stmt = $conn->prepare("SELECT id FROM claim_categories WHERE name = ?");
        $stmt->execute([$name]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('A category with this name already exists');
        }
        
        // Insert new category
        $stmt = $conn->prepare("INSERT INTO claim_categories (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        
        $successMessage = 'Category added successfully';
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Delete category
if (isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    try {
        $categoryId = (int)$_POST['category_id'];
        
        // Debug information
        error_log("Attempting to delete category ID: " . $categoryId);
        
        // Check if category exists
        $checkStmt = $conn->prepare("SELECT id FROM claim_categories WHERE id = ?");
        $checkStmt->execute([$categoryId]);
        if ($checkStmt->rowCount() === 0) {
            throw new Exception("Category with ID {$categoryId} not found");
        }
        
        // Check if claims table exists
        $tableExists = false;
        $checkTableStmt = $conn->query("SHOW TABLES LIKE 'claims'");
        if ($checkTableStmt->rowCount() > 0) {
            $tableExists = true;
            
            // Check if category is in use
            $stmt = $conn->prepare("SELECT COUNT(*) FROM claims WHERE category_id = ?");
            $stmt->execute([$categoryId]);
            $count = (int)$stmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception("Cannot delete this category because it is used by {$count} claim(s)");
            }
        }
        
        // Delete category
        $stmt = $conn->prepare("DELETE FROM claim_categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        
        if ($stmt->rowCount() > 0) {
            $successMessage = 'Category deleted successfully';
            error_log("Category deleted successfully: " . $categoryId);
        } else {
            $errorMessage = 'Category not found or could not be deleted';
            error_log("Failed to delete category: " . $categoryId);
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        error_log("Error deleting category: " . $e->getMessage());
    }
}

// Edit category
if (isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    try {
        $categoryId = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            throw new Exception('Category name is required');
        }
        
        // Check if another category with the same name exists
        $stmt = $conn->prepare("SELECT id FROM claim_categories WHERE name = ? AND id != ?");
        $stmt->execute([$name, $categoryId]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('Another category with this name already exists');
        }
        
        // Update category
        $stmt = $conn->prepare("UPDATE claim_categories SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $categoryId]);
        
        $successMessage = 'Category updated successfully';
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get all categories
$categories = [];
$stmt = $conn->query("SELECT * FROM claim_categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-title">
    <h1>Categories Management</h1>
    <div class="button-container">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus me-1"></i> New Category
        </button>
    </div>
</div>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $successMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $errorMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<section class="section">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Claim Categories</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="categoriesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo $category['id']; ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td><?php echo htmlspecialchars($category['description']); ?></td>
                                <td><?php echo date('M j, Y, g:i A', strtotime($category['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-primary edit-category" 
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($category['description']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-category"
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_category">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label fw-bold">Category Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label fw-bold">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="deleteCategoryForm">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="category_id" id="delete_category_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCategoryModalLabel">Delete Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <p>Are you sure you want to delete the category "<span id="delete_category_name"></span>"?</p>
                    <p class="text-danger">This action cannot be undone if the category is not in use. If the category is in use by any claims, deletion will be prevented.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        const table = $('#categoriesTable').DataTable({
            "order": [[0, "asc"]],
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
        });
        
        // Add event listeners for edit and delete buttons
        $(document).on('click', '.edit-category', function() {
            const categoryId = $(this).data('id');
            const categoryName = $(this).data('name');
            const categoryDescription = $(this).data('description');
            
            $('#edit_category_id').val(categoryId);
            $('#edit_name').val(categoryName);
            $('#edit_description').val(categoryDescription);
            
            $('#editCategoryModal').modal('show');
        });
        
        $(document).on('click', '.delete-category', function() {
            const categoryId = $(this).data('id');
            const categoryName = $(this).data('name');
            
            $('#delete_category_id').val(categoryId);
            $('#delete_category_name').text(categoryName);
            
            $('#deleteCategoryModal').modal('show');
        });
        
        // Make sure form submissions work properly
        $('#deleteCategoryForm').on('submit', function() {
            // Add any validation if needed
            return true; // Allow form submission
        });
    });
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
