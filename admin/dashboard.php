<?php
/**
 * Admin Dashboard
 * 
 * This file displays the admin dashboard for the Warranty Management System.
 */

// Set page title
$pageTitle = 'Dashboard';

// Include header
require_once 'includes/header.php';

// Get dashboard statistics
try {
    $conn = getDbConnection();
    
    // Total claims
    $stmt = $conn->query("SELECT COUNT(*) as total FROM claims");
    $totalClaims = $stmt->fetch()['total'] ?? 0;
    
    // Claims by status
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM claims GROUP BY status");
    $claimsByStatus = $stmt->fetchAll();
    
    // Format claims by status for easy access
    $statusCounts = [
        'new' => 0,
        'in_progress' => 0,
        'on_hold' => 0,
        'approved' => 0,
        'rejected' => 0
    ];
    
    foreach ($claimsByStatus as $status) {
        $statusCounts[$status['status']] = $status['count'];
    }
    
    // Recent claims
    $stmt = $conn->query("SELECT c.*, u.first_name, u.last_name, cat.name as category_name 
                         FROM claims c 
                         LEFT JOIN users u ON c.created_by = u.id 
                         LEFT JOIN categories cat ON c.category_id = cat.id 
                         ORDER BY c.created_at DESC LIMIT 5");
    $recentClaims = $stmt->fetchAll();
    
    // SLA breaches
    $stmt = $conn->query("SELECT COUNT(*) as total FROM sla_breach_logs WHERE resolved = 0");
    $slaBreaches = $stmt->fetch()['total'] ?? 0;
    
} catch (PDOException $e) {
    // Log error
    error_log("Error fetching dashboard data: " . $e->getMessage());
    
    // Set default values
    $totalClaims = 0;
    $statusCounts = [
        'new' => 0,
        'in_progress' => 0,
        'on_hold' => 0,
        'approved' => 0,
        'rejected' => 0
    ];
    $recentClaims = [];
    $slaBreaches = 0;
}
?>

<div class="page-title">
    <h1>Dashboard</h1>
    <div>
        <span class="me-3">
            <i class="fas fa-calendar-alt me-1"></i> <?php echo date('d M, Y'); ?>
        </span>
        <span id="current-time">
            <i class="fas fa-clock me-1"></i> <span id="time-display"></span>
        </span>
    </div>
</div>

<!-- Dashboard Overview -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-primary text-white h-100">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-title small">Total Claims</div>
                        <div class="stat-value h4 mb-0"><?php echo number_format($totalClaims); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-success text-white h-100">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-title small">Approved Claims</div>
                        <div class="stat-value h4 mb-0"><?php echo number_format($statusCounts['approved']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-warning text-white h-100">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-title small">Pending Claims</div>
                        <div class="stat-value h4 mb-0"><?php echo number_format($statusCounts['new'] + $statusCounts['in_progress'] + $statusCounts['on_hold']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-danger text-white h-100">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-title small">SLA Breaches</div>
                        <div class="stat-value h4 mb-0"><?php echo number_format($slaBreaches); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Claims Status Chart & Quick Actions -->
<div class="row">
    <div class="col-lg-8 mb-3">
        <div class="card h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Claims Overview</h6>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle py-0 px-2" type="button" id="chartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        This Month
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="chartDropdown">
                        <li><a class="dropdown-item" href="#">This Week</a></li>
                        <li><a class="dropdown-item" href="#">This Month</a></li>
                        <li><a class="dropdown-item" href="#">This Year</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body p-2">
                <div class="row">
                    <div class="col-md-8">
                        <canvas id="claimsChart" height="200"></canvas>
                    </div>
                    <div class="col-md-4">
                        <div class="mt-2 mt-md-0">
                            <h6 class="text-muted small mb-2">Claims by Status</h6>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>New</span>
                                    <span class="fw-bold"><?php echo $statusCounts['new']; ?></span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $totalClaims > 0 ? ($statusCounts['new'] / $totalClaims * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>In Progress</span>
                                    <span class="fw-bold"><?php echo $statusCounts['in_progress']; ?></span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $totalClaims > 0 ? ($statusCounts['in_progress'] / $totalClaims * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>On Hold</span>
                                    <span class="fw-bold"><?php echo $statusCounts['on_hold']; ?></span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $totalClaims > 0 ? ($statusCounts['on_hold'] / $totalClaims * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>Approved</span>
                                    <span class="fw-bold"><?php echo $statusCounts['approved']; ?></span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $totalClaims > 0 ? ($statusCounts['approved'] / $totalClaims * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>Rejected</span>
                                    <span class="fw-bold"><?php echo $statusCounts['rejected']; ?></span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-danger" style="width: <?php echo $totalClaims > 0 ? ($statusCounts['rejected'] / $totalClaims * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <h6 class="mb-0">Quick Actions</h6>
            </div>
            <div class="card-body p-2">
                <div class="d-grid gap-2">
                    <a href="claims.php?action=new" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle me-1"></i> Create New Claim
                    </a>
                    <a href="users.php?action=new" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-user-plus me-1"></i> Add New User
                    </a>
                    <a href="categories.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-tags me-1"></i> Manage Categories
                    </a>
                    <a href="reports.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-file-export me-1"></i> Generate Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Claims & System Info -->
<div class="row">
    <div class="col-lg-8 mb-3">
        <div class="card h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Claims</h6>
                <a href="claims.php" class="btn btn-sm btn-primary py-0 px-2">View All</a>
            </div>
            <div class="card-body p-2">
                <?php if (count($recentClaims) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Order ID</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentClaims as $claim): ?>
                                    <tr>
                                        <td><?php echo $claim['id']; ?></td>
                                        <td><?php echo htmlspecialchars($claim['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['category_name']); ?></td>
                                        <td>
                                            <?php
                                            $statusBadge = '';
                                            switch ($claim['status']) {
                                                case 'new':
                                                    $statusBadge = 'info';
                                                    break;
                                                case 'in_progress':
                                                    $statusBadge = 'primary';
                                                    break;
                                                case 'on_hold':
                                                    $statusBadge = 'warning';
                                                    break;
                                                case 'approved':
                                                    $statusBadge = 'success';
                                                    break;
                                                case 'rejected':
                                                    $statusBadge = 'danger';
                                                    break;
                                                default:
                                                    $statusBadge = 'secondary';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $statusBadge; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $claim['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($claim['created_at'])); ?></td>
                                        <td>
                                            <a href="claims.php?action=view&id=<?php echo $claim['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-1" data-bs-toggle="tooltip" title="View Claim">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info py-2 px-3 mb-0">
                        <i class="fas fa-info-circle me-1"></i> No claims found in the system.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-header py-2">
                <h6 class="mb-0">System Information</h6>
            </div>
            <div class="card-body p-2">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item py-1 px-2 d-flex justify-content-between align-items-center">
                        <small>System Version</small>
                        <span class="badge bg-primary"><?php echo APP_VERSION; ?></span>
                    </li>
                    <li class="list-group-item py-1 px-2 d-flex justify-content-between align-items-center">
                        <small>PHP Version</small>
                        <span class="badge bg-secondary"><?php echo phpversion(); ?></span>
                    </li>
                    <li class="list-group-item py-1 px-2 d-flex justify-content-between align-items-center">
                        <small>Server Time</small>
                        <span class="badge bg-info"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </li>
                    <li class="list-group-item py-1 px-2 d-flex justify-content-between align-items-center">
                        <small>Database</small>
                        <span class="badge bg-success">Connected</span>
                    </li>
                    <li class="list-group-item py-1 px-2 d-flex justify-content-between align-items-center">
                        <small>ODIN API Status</small>
                        <span class="badge bg-warning">Not Checked</span>
                    </li>
                </ul>
                
                <div class="mt-2">
                    <h6 class="small mb-2">System Health</h6>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span>Database</span>
                            <span>85%</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-success" style="width: 85%"></div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span>Disk Space</span>
                            <span>65%</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-info" style="width: 65%"></div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span>Memory Usage</span>
                            <span>45%</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-primary" style="width: 45%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize claims chart
document.addEventListener('DOMContentLoaded', function() {
    // Update current time
    function updateTime() {
        const now = new Date();
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        
        hours = hours % 12;
        hours = hours ? hours : 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        const timeString = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        document.getElementById('time-display').textContent = timeString;
        
        setTimeout(updateTime, 1000);
    }
    
    // Initialize time
    updateTime();
    
    // Claims chart
    const ctx = document.getElementById('claimsChart').getContext('2d');
    const claimsChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['New', 'In Progress', 'On Hold', 'Approved', 'Rejected'],
            datasets: [{
                data: [
                    <?php echo $statusCounts['new']; ?>,
                    <?php echo $statusCounts['in_progress']; ?>,
                    <?php echo $statusCounts['on_hold']; ?>,
                    <?php echo $statusCounts['approved']; ?>,
                    <?php echo $statusCounts['rejected']; ?>
                ],
                backgroundColor: [
                    '#0dcaf0', // info
                    '#0d6efd', // primary
                    '#ffc107', // warning
                    '#198754', // success
                    '#dc3545'  // danger
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            size: 11
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
