<?php
/**
 * Users Management
 * 
 * This file allows administrators to manage users including
 * viewing, adding, editing, and deleting users.
 */

// Set page title
$pageTitle = 'Users Management';

// Include required files
require_once '../includes/auth_helper.php';

// Enforce admin-only access
enforceAdminOnly();

// Include header
require_once 'includes/header.php';

// Include database connection
require_once '../config/database.php';

// Establish database connection
$conn = getDbConnection();

// Process form submissions

// Add new user
if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
    try {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($firstName) || empty($lastName)) {
            throw new Exception('All fields are required');
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('Username already exists');
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('Email already exists');
        }
        
        // Generate a default password (hashed)
        $defaultPassword = password_hash('password123', PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, email, first_name, last_name, role, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$username, $defaultPassword, $email, $firstName, $lastName, $role, $status]);
        
        setSuccessAlert('User added successfully');
    } catch (Exception $e) {
        setErrorAlert($e->getMessage());
    }
}

// Update user
if (isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    try {
        $userId = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($firstName) || empty($lastName)) {
            throw new Exception('All fields are required');
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Check if username already exists for a different user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('Username already exists');
        }
        
        // Check if email already exists for a different user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('Email already exists');
        }
        
        // Update user
        $stmt = $conn->prepare("
            UPDATE users 
            SET username = ?, email = ?, first_name = ?, last_name = ?, role = ?, status = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([$username, $email, $firstName, $lastName, $role, $status, $userId]);
        
        setSuccessAlert('User updated successfully');
    } catch (Exception $e) {
        setErrorAlert($e->getMessage());
    }
}

// Delete user
if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    try {
        $userId = (int)$_POST['user_id'];
        
        // Check if user exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $checkStmt->execute([$userId]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception("User not found");
        }
        
        // Check if user is the current logged-in user
        if ($userId === $_SESSION['user_id']) {
            throw new Exception("You cannot delete your own account");
        }
        
        // Check if user is referenced in other tables
        $tables = ['claims', 'claim_attachments', 'claim_status_history', 'claim_notes', 'audit_logs'];
        $inUse = false;
        $usageCount = 0;
        
        foreach ($tables as $table) {
            // Check created_by, assigned_to, uploaded_by, changed_by fields
            $fields = ['created_by', 'assigned_to', 'uploaded_by', 'changed_by', 'user_id'];
            
            foreach ($fields as $field) {
                // Check if the table has this field
                $checkFieldStmt = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$field'");
                
                if ($checkFieldStmt->rowCount() > 0) {
                    // Count records with this user ID
                    $countStmt = $conn->prepare("SELECT COUNT(*) FROM `$table` WHERE `$field` = ?");
                    $countStmt->execute([$userId]);
                    $count = (int)$countStmt->fetchColumn();
                    
                    if ($count > 0) {
                        $inUse = true;
                        $usageCount += $count;
                    }
                }
            }
        }
        
        if ($inUse) {
            throw new Exception("Cannot delete this user because they are referenced in $usageCount record(s)");
        }
        
        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        setSuccessAlert('User deleted successfully');
    } catch (Exception $e) {
        setErrorAlert($e->getMessage());
    }
}

// Get all users
$stmt = $conn->query("SELECT * FROM users ORDER BY username");
$users = $stmt->fetchAll();
?>

<div class="page-title">
    <h1>Users Management</h1>
    <div class="button-container">
        <button type="button" class="btn btn-primary add-user-btn" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus me-1"></i> Add New User
        </button>
    </div>
</div>

<?php if (hasSuccessAlert() || hasErrorAlert()): ?>
    <div class="alert <?php echo hasSuccessAlert() ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo hasSuccessAlert() ? getSuccessAlert() : getErrorAlert(); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header py-3">
        <h6 class="mb-0">Users</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="usersTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td>
                                <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary edit-user-btn" 
                                        data-id="<?php echo $user['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                        data-firstname="<?php echo htmlspecialchars($user['first_name']); ?>"
                                        data-lastname="<?php echo htmlspecialchars($user['last_name']); ?>"
                                        data-role="<?php echo $user['role']; ?>"
                                        data-status="<?php echo $user['status']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editUserModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-outline-danger delete-user-btn"
                                        data-id="<?php echo $user['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                        data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-outline-danger" disabled title="Cannot delete your own account">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="addUserForm">
                <input type="hidden" name="action" value="add_user">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="add_username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="add_email" name="email" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="add_first_name" name="first_name" required>
                        </div>
                        <div class="col">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="add_last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="add_role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="cs_agent">Customer Service Agent</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="add_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>A default password will be set for the new user. They can change it after logging in.</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="editUserForm">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col">
                            <label for="edit_first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="col">
                            <label for="edit_last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="cs_agent">Customer Service Agent</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <p>Are you sure you want to delete user <strong id="delete_username"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. The user will be permanently removed from the system.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>

<script>
    $(document).ready(function() {
        // Initialize DataTable with enhanced features
        var usersTable = $('#usersTable').DataTable({
            order: [[0, 'asc']], // Sort by ID by default
            columnDefs: [
                { orderable: false, targets: [7] }, // Disable sorting on actions column
                { className: 'text-center', targets: [4, 5, 7] } // Center align status, role and actions columns
            ],
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            pageLength: 25,
            stateSave: true, // Save the state of the table (sorting, pagination, etc.)
            responsive: true, // Make the table responsive
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            language: {
                search: "_INPUT_", // Remove the 'Search' label
                searchPlaceholder: "Search users...",
                lengthMenu: "_MENU_ users per page",
                info: "Showing _START_ to _END_ of _TOTAL_ users",
                infoEmpty: "Showing 0 to 0 of 0 users",
                infoFiltered: "(filtered from _MAX_ total users)",
                zeroRecords: "No matching users found",
                paginate: {
                    first: '<i class="fas fa-angle-double-left"></i>',
                    previous: '<i class="fas fa-angle-left"></i>',
                    next: '<i class="fas fa-angle-right"></i>',
                    last: '<i class="fas fa-angle-double-right"></i>'
                }
            },
            initComplete: function() {
                // Create a wrapper for filters and search
                var filtersWrapper = $('<div class="d-flex justify-content-between align-items-center mb-3 mt-3"></div>');
                $('#usersTable_wrapper .dataTables_filter').parent().prepend(filtersWrapper);
                
                // Move search into wrapper
                $('#usersTable_filter').appendTo(filtersWrapper);
                
                // Add filter container
                var filterContainer = $('<div class="d-flex align-items-center"></div>');
                filtersWrapper.prepend(filterContainer);
                
                // Add role filter dropdown
                this.api().columns(4).every(function() {
                    var column = this;
                    var select = $('<select class="form-select form-select-sm mx-1" style="width: auto;"><option value="">All Roles</option></select>')
                        .appendTo(filterContainer)
                        .on('change', function() {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            column.search(val ? val : '', true, false).draw();
                        });
                    
                    // Get unique role values from the column
                    column.data().unique().sort().each(function(d, j) {
                        // Extract role name from badge HTML
                        var role = $(d).text().trim();
                        select.append('<option value="' + role + '">' + role + '</option>');
                    });
                });
                
                // Add status filter dropdown
                this.api().columns(5).every(function() {
                    var column = this;
                    var select = $('<select class="form-select form-select-sm mx-1" style="width: auto;"><option value="">All Statuses</option></select>')
                        .appendTo(filterContainer)
                        .on('change', function() {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            column.search(val ? val : '', true, false).draw();
                        });
                    
                    // Get unique status values from the column
                    column.data().unique().sort().each(function(d, j) {
                        // Extract status name from badge HTML
                        var status = $(d).text().trim();
                        select.append('<option value="' + status + '">' + status + '</option>');
                    });
                });
                
                // Add some custom styling
                $('#usersTable_filter input').addClass('form-control form-control-sm');
                $('#usersTable_length select').addClass('form-select form-select-sm');
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            $('.alert-dismissible').fadeOut('slow');
        }, 5000);

        // Handle edit user button click
        $('.edit-user-btn').on('click', function() {
            const userId = $(this).data('id');
            const username = $(this).data('username');
            const email = $(this).data('email');
            const firstName = $(this).data('firstname');
            const lastName = $(this).data('lastname');
            const role = $(this).data('role');
            const status = $(this).data('status');
            
            $('#edit_user_id').val(userId);
            $('#edit_username').val(username);
            $('#edit_email').val(email);
            $('#edit_first_name').val(firstName);
            $('#edit_last_name').val(lastName);
            $('#edit_role').val(role);
            $('#edit_status').val(status);
        });
        
        // Handle delete user button click
        $('.delete-user-btn').on('click', function() {
            const userId = $(this).data('id');
            const username = $(this).data('username');
            
            $('#delete_user_id').val(userId);
            $('#delete_username').text(username);
        });
        
        // Form validation for add user
        $('#addUserForm').on('submit', function(e) {
            const username = $('#add_username').val().trim();
            const email = $('#add_email').val().trim();
            const firstName = $('#add_first_name').val().trim();
            const lastName = $('#add_last_name').val().trim();
            
            if (!username || !email || !firstName || !lastName) {
                e.preventDefault();
                alert('All fields are required');
                return false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
            
            return true;
        });
        
        // Form validation for edit user
        $('#editUserForm').on('submit', function(e) {
            const username = $('#edit_username').val().trim();
            const email = $('#edit_email').val().trim();
            const firstName = $('#edit_first_name').val().trim();
            const lastName = $('#edit_last_name').val().trim();
            
            if (!username || !email || !firstName || !lastName) {
                e.preventDefault();
                alert('All fields are required');
                return false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
            
            return true;
        });
    });
</script>
