<?php
/**
 * Notifications Page
 * 
 * This file displays all notifications for the current user.
 */

// Set page title
$pageTitle = 'Notifications';

// Include required files
require_once '../includes/auth_helper.php';
require_once '../config/database.php';
require_once '../includes/notification_helper.php';

// Enforce admin-only access
requireAdminOrCsAgent();

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    markAllNotificationsAsRead($_SESSION['user_id']);
    header('Location: notifications.php');
    exit;
}

// Mark single notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    markNotificationAsRead($_GET['mark_read']);
    
    // Redirect to link if provided
    if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
        header('Location: ' . $_GET['redirect']);
        exit;
    }
    
    header('Location: notifications.php');
    exit;
}

// Get all notifications for the current user
try {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE (user_id = :user_id OR user_id = 0)
        ORDER BY created_at DESC
    ");
    
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notifications = [];
    $errorMessage = "Error retrieving notifications: " . $e->getMessage();
}

// Include header
require_once 'includes/header.php';
?>

<div class="page-title">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1>Notifications</h1>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if (count($notifications) > 0): ?>
            <a href="notifications.php?mark_all_read=1" class="btn btn-outline-primary">
                <i class="fas fa-check-double me-2"></i>Mark All as Read
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (count($notifications) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>Message</th>
                            <th style="width: 180px;">Date</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $notification): ?>
                        <tr class="<?php echo $notification['is_read'] ? '' : 'table-light'; ?>">
                            <td class="text-center">
                                <?php
                                $icon = 'info-circle';
                                $iconClass = 'text-primary';
                                
                                switch ($notification['type']) {
                                    case 'success':
                                        $icon = 'check-circle';
                                        $iconClass = 'text-success';
                                        break;
                                    case 'warning':
                                        $icon = 'exclamation-triangle';
                                        $iconClass = 'text-warning';
                                        break;
                                    case 'danger':
                                        $icon = 'exclamation-circle';
                                        $iconClass = 'text-danger';
                                        break;
                                }
                                ?>
                                <i class="fas fa-<?php echo $icon; ?> fa-lg <?php echo $iconClass; ?>"></i>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($notification['message']); ?>
                                <?php if (!$notification['is_read']): ?>
                                <span class="badge bg-primary ms-2">New</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y g:i a', strtotime($notification['created_at'])); ?></td>
                            <td>
                                <?php if (!empty($notification['link'])): ?>
                                <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>&redirect=<?php echo urlencode($notification['link']); ?>" class="btn btn-sm btn-outline-primary me-1" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!$notification['is_read']): ?>
                                <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-success" title="Mark as Read">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                <h4>No notifications</h4>
                <p class="text-muted">You don't have any notifications at this time.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
