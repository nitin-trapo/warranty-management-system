<?php
/**
 * User Profile
 * 
 * This file allows users to view and update their basic profile information.
 */

// Set page title
$pageTitle = 'My Profile';

// Include header
require_once 'includes/header.php';

// Get database connection
$conn = getDbConnection();

// Initialize variables
$success = false;
$error = false;
$errorMessage = '';

// Get user data
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $error = true;
    $errorMessage = 'User not found';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    try {
        // Get form data
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        
        // Validate form data
        if (empty($firstName)) {
            $error = true;
            $errorMessage = 'First name is required';
        } elseif (empty($lastName)) {
            $error = true;
            $errorMessage = 'Last name is required';
        } elseif (empty($email)) {
            $error = true;
            $errorMessage = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = true;
            $errorMessage = 'Invalid email format';
        }
        
        // Check if email already exists for another user
        if (!$error && $email !== $user['email']) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->rowCount() > 0) {
                $error = true;
                $errorMessage = 'Email already exists for another user';
            }
        }
        
        // Update user profile if no errors
        if (!$error) {
            // Update user information
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $email, $userId]);
            
            // Log profile update
            if (function_exists('logAuditAction')) {
                logAuditAction($userId, 'update', 'user', $userId, 'User updated profile information');
            }
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $success = true;
        }
    } catch (PDOException $e) {
        $error = true;
        $errorMessage = 'Database error: ' . $e->getMessage();
        error_log('Profile update error: ' . $e->getMessage());
    }
}
?>

<div class="page-title">
    <h1>My Profile</h1>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    Profile updated successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<script>
    // Reload page after 1 second
    setTimeout(function() {
        window.location.reload();
    }, 1000);
</script>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($errorMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!$error || $success): ?>
<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="profile.php">
                    <div class="row mb-3">
                        <label for="first_name" class="col-sm-3 col-form-label">First Name</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="last_name" class="col-sm-3 col-form-label">Last Name</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="email" class="col-sm-3 col-form-label">Email</label>
                        <div class="col-sm-9">
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="username" class="col-sm-3 col-form-label">Username</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="role" class="col-sm-3 col-form-label">Role</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="role" value="<?php echo ucfirst($user['role']); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Account Information -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Account Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Last Login:</strong> <?php echo isset($user['last_login']) ? date('M d, Y H:i', strtotime($user['last_login'])) : 'N/A'; ?></p>
                <p><strong>Account Created:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php
                // Get recent audit logs for this user
                try {
                    $stmt = $conn->prepare("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                    $stmt->execute([$userId]);
                    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($recentActivity) > 0):
                ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentActivity as $activity): ?>
                    <li class="list-group-item px-0">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="badge bg-<?php 
                                    echo $activity['action'] == 'create' ? 'success' : 
                                        ($activity['action'] == 'update' ? 'primary' : 
                                        ($activity['action'] == 'delete' ? 'danger' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($activity['action']); ?>
                                </span>
                                <?php echo htmlspecialchars($activity['details']); ?>
                            </div>
                            <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-muted">No recent activity found</p>
                <?php 
                    endif;
                } catch (PDOException $e) {
                    echo '<p class="text-danger">Error loading recent activity</p>';
                    error_log('Error loading recent activity: ' . $e->getMessage());
                }
                ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
require_once 'includes/footer.php';
?>
