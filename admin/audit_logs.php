<?php
/**
 * Audit Logs
 * 
 * This file displays the audit logs for the Warranty Management System.
 * It allows filtering and exporting of system activity logs.
 */

// Set page title
$pageTitle = 'Audit Logs';

// Include header
require_once 'includes/header.php';

// Get database connection
$conn = getDbConnection();

// Initialize variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filter parameters
$action = isset($_GET['action']) ? $_GET['action'] : '';
$entityType = isset($_GET['entity_type']) ? $_GET['entity_type'] : '';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Initialize variables for storing data
$auditLogs = [];
$actions = [];
$entityTypes = [];
$users = [];
$totalLogs = 0;
$totalPages = 1;
$error = null;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($action)) {
    $conditions[] = "al.action = ?";
    $params[] = $action;
}

if (!empty($entityType)) {
    // Handle both singular and plural forms (e.g., 'claim' and 'claims')
    if ($entityType == 'claims') {
        $entityType = 'claim';
    } elseif ($entityType == 'users') {
        $entityType = 'user';
    } elseif ($entityType == 'categories') {
        $entityType = 'category';
    } elseif ($entityType == 'media') {
        $entityType = 'claim_media';
    } elseif ($entityType == 'notes') {
        $entityType = 'claim_note';
    } elseif ($entityType == 'reports') {
        $entityType = 'report';
    }
    
    $conditions[] = "al.entity_type = ?";
    $params[] = $entityType;
}

if ($userId > 0) {
    $conditions[] = "al.user_id = ?";
    $params[] = $userId;
}

if (!empty($startDate)) {
    $conditions[] = "al.created_at >= ?";
    $params[] = $startDate . ' 00:00:00';
}

if (!empty($endDate)) {
    $conditions[] = "al.created_at <= ?";
    $params[] = $endDate . ' 23:59:59';
}

// Construct WHERE clause
$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

try {
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) FROM audit_logs al $whereClause";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalLogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $perPage);
    
    // Get audit logs with user information
    $query = "SELECT al.*, u.username 
              FROM audit_logs al
              LEFT JOIN users u ON al.user_id = u.id
              $whereClause
              ORDER BY al.created_at DESC
              LIMIT $offset, $perPage";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique actions for filter dropdown
    $actionQuery = "SELECT DISTINCT action FROM audit_logs ORDER BY action";
    $actionStmt = $conn->prepare($actionQuery);
    $actionStmt->execute();
    $actions = $actionStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get unique entity types for filter dropdown
    $entityQuery = "SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type";
    $entityStmt = $conn->prepare($entityQuery);
    $entityStmt->execute();
    $entityTypes = $entityStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get users for filter dropdown
    $userQuery = "SELECT id, username FROM users ORDER BY username";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute();
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // Log error
    error_log("Error fetching audit logs: " . $e->getMessage());
    $error = "An error occurred while retrieving audit logs: " . $e->getMessage();
}
?>

<div class="page-title">
    <h1>Audit Logs</h1>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-filter me-1"></i>
            Filter Logs
        </div>
        <div>
            <a href="export_audit_logs.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-success btn-sm">
                <i class="fas fa-file-export me-1"></i> Export to CSV
            </a>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="audit_logs.php" class="row g-3">
            <div class="col-md-2">
                <label for="action" class="form-label">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $action == $act ? 'selected' : ''; ?>>
                            <?php echo ucfirst(htmlspecialchars($act)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="entity_type" class="form-label">Entity Type</label>
                <select class="form-select" id="entity_type" name="entity_type">
                    <option value="">All Types</option>
                    <?php foreach ($entityTypes as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $entityType == $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($type))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="user_id" class="form-label">User</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="0">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                <a href="audit_logs.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Audit Logs Table -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-history me-1"></i>
        System Activity Logs
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity Type</th>
                        <th>Entity ID</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditLogs)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No audit logs found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $log['action'] == 'create' ? 'success' : 
                                            ($log['action'] == 'update' ? 'primary' : 
                                            ($log['action'] == 'delete' ? 'danger' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($log['action'])); ?>
                                    </span>
                                </td>
                                <td><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($log['entity_type']))); ?></td>
                                <td><?php echo $log['entity_id']; ?></td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Audit logs pagination">
                <ul class="pagination justify-content-center mt-4">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&action=<?php echo urlencode($action); ?>&entity_type=<?php echo urlencode($entityType); ?>&user_id=<?php echo $userId; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>">
                                Previous
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">Previous</span>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    // Display a limited number of page links
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&action=' . urlencode($action) . '&entity_type=' . urlencode($entityType) . '&user_id=' . $userId . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate) . '">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                        echo '<a class="page-link" href="?page=' . $i . '&action=' . urlencode($action) . '&entity_type=' . urlencode($entityType) . '&user_id=' . $userId . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate) . '">' . $i . '</a>';
                        echo '</li>';
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&action=' . urlencode($action) . '&entity_type=' . urlencode($entityType) . '&user_id=' . $userId . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate) . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&action=<?php echo urlencode($action); ?>&entity_type=<?php echo urlencode($entityType); ?>&user_id=<?php echo $userId; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>">
                                Next
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">Next</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
