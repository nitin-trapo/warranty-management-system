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
    
    // Get time period filter
    $timePeriod = $_GET['period'] ?? 'year';
    
    // Set date filters based on time period
    $dateFilter = '';
    $periodLabel = 'This Year';
    
    switch ($timePeriod) {
        case 'week':
            $dateFilter = "AND YEARWEEK(c.created_at, 1) = YEARWEEK(NOW(), 1)";
            $periodLabel = 'This Week';
            break;
        case 'month':
            $dateFilter = "AND MONTH(c.created_at) = MONTH(CURRENT_DATE()) AND YEAR(c.created_at) = YEAR(CURRENT_DATE())";
            $periodLabel = 'This Month';
            break;
        case 'year':
            $dateFilter = "AND YEAR(c.created_at) = YEAR(CURRENT_DATE())";
            $periodLabel = 'This Year';
            break;
        default:
            $dateFilter = "AND YEAR(c.created_at) = YEAR(CURRENT_DATE())";
            $periodLabel = 'This Year';
    }
    
    // Track assigned claims for CS agents
    $assignedClaimsCount = 0;
    if (isCsAgent()) {
        $csAgentId = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) as assigned_count FROM claims WHERE assigned_to = ?");
        $stmt->execute([$csAgentId]);
        $assignedClaimsCount = $stmt->fetch()['assigned_count'] ?? 0;
    }
    
    // Total claims (not filtered by time period)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM claims");
    $totalClaims = $stmt->fetch()['total'] ?? 0;
    
    // Claims by status with time period filter
    $statusQuery = "SELECT status, COUNT(*) as count FROM claims WHERE 1=1";
    if (!empty($dateFilter)) {
        $dateFilterForStatus = str_replace('c.', '', $dateFilter);
        $statusQuery .= " " . $dateFilterForStatus;
    }
    $statusQuery .= " GROUP BY status";
    $stmt = $conn->query($statusQuery);
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
    
    // Recent claims - simplified query to ensure all claims are shown
    $recentClaimsQuery = "SELECT c.* FROM claims c ORDER BY c.created_at DESC LIMIT 5";
    $stmt = $conn->query($recentClaimsQuery);
    $recentClaims = $stmt->fetchAll();
    
    // Calculate SLA breaches
    $slaBreachQuery = "SELECT COUNT(DISTINCT c.id) as total FROM claims c 
                      JOIN claim_items ci ON c.id = ci.claim_id
                      JOIN claim_categories cc ON ci.category_id = cc.id 
                      WHERE c.status NOT IN ('approved', 'rejected') 
                      AND cc.sla_days > 0
                      AND DATE_ADD(c.created_at, INTERVAL cc.sla_days DAY) < NOW()";
    if ($timePeriod != 'all') {
        $slaBreachQuery .= " " . str_replace('AND ', 'AND ', $dateFilter);
    }
    $stmt = $conn->query($slaBreachQuery);
    $slaBreaches = $stmt->fetch()['total'] ?? 0;
    
    // Claims by category
    $stmt = $conn->prepare("SELECT cc.name, COUNT(*) as count 
                         FROM claim_items ci
                         JOIN claim_categories cc ON ci.category_id = cc.id
                         JOIN claims c ON ci.claim_id = c.id
                         WHERE 1=1 $dateFilter
                         GROUP BY ci.category_id
                         ORDER BY count DESC
                         LIMIT 5");
    $stmt->execute();
    $claimsByCategory = $stmt->fetchAll();
    
    // Claims by product type
    $stmt = $conn->prepare("SELECT ci.product_type, COUNT(*) as count 
                         FROM claim_items ci
                         JOIN claims c ON ci.claim_id = c.id
                         WHERE 1=1 $dateFilter
                         GROUP BY ci.product_type
                         ORDER BY count DESC
                         LIMIT 5");
    $stmt->execute();
    $claimsByProductType = $stmt->fetchAll();
    
    // Claims today
    $todayQuery = "SELECT COUNT(*) as total FROM claims WHERE DATE(created_at) = CURDATE()";
    $stmt = $conn->query($todayQuery);
    $claimsToday = $stmt->fetch()['total'] ?? 0;
    
    // Claims resolved today - using a query that works with the actual database structure
    $resolvedTodayQuery = "SELECT COUNT(*) as total FROM claims 
                          WHERE DATE(updated_at) = CURDATE() 
                          AND status IN ('approved', 'rejected')";
    $stmt = $conn->query($resolvedTodayQuery);
    $resolvedToday = $stmt->fetch()['total'] ?? 0;
        
    // Claims by month (for chart)
    $claimsByMonthQuery = "SELECT 
            MONTH(created_at) as month, 
            COUNT(*) as total 
        FROM claims 
        WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
        GROUP BY MONTH(created_at) 
        ORDER BY MONTH(created_at)";
    $stmt = $conn->query($claimsByMonthQuery);
    
    $claimsByMonth = [];
    $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
    ];
    
    // Initialize all months with 0
    foreach ($months as $monthNum => $monthName) {
        $claimsByMonth[$monthNum] = 0;
    }
    
    // Fill in actual data
    while ($row = $stmt->fetch()) {
        $claimsByMonth[$row['month']] = (int)$row['total'];
    }
    
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
    $claimsByCategory = [];
    $claimsByProductType = [];
    $claimsToday = 0;
    $resolvedToday = 0;
    $claimsByMonth = [];
    foreach ([1,2,3,4,5,6,7,8,9,10,11,12] as $m) {
        $claimsByMonth[$m] = 0;
    }
    $assignedClaimsCount = 0;
}
?>

<!-- Dashboard Stats -->
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
                        <i class="fas fa-file-alt"></i>
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
                        <div class="stat-title small">Resolved Today</div>
                        <div class="stat-value h4 mb-0"><?php echo number_format($resolvedToday); ?></div>
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
                        <div class="stat-title small">New Today</div>
                        <div class="stat-value h4 mb-0"><?php echo number_format($claimsToday); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
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
    
    <?php if (isCsAgent()): ?>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-primary text-white h-100">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-title small">Assigned to You</div>
                        <div class="stat-value h4 mb-0"><?php echo number_format($assignedClaimsCount); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Claims Status Chart & Quick Actions -->
<div class="row">
    <div class="col-lg-8 mb-3">
        <div class="card h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Claims Overview</h6>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle py-0 px-2" type="button" id="chartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo $periodLabel; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="chartDropdown">
                        <li><a class="dropdown-item" href="dashboard.php?period=week">This Week</a></li>
                        <li><a class="dropdown-item" href="dashboard.php?period=month">This Month</a></li>
                        <li><a class="dropdown-item" href="dashboard.php?period=year">This Year</a></li>
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
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $totalClaims > 0 ? ($statusCounts['new'] / $totalClaims * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>In Progress</span>
                                    <span class="fw-bold"><?php echo $statusCounts['in_progress']; ?></span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $totalClaims > 0 ? ($statusCounts['in_progress'] / $totalClaims * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>On Hold</span>
                                    <span class="fw-bold"><?php echo $statusCounts['on_hold']; ?></span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $totalClaims > 0 ? ($statusCounts['on_hold'] / $totalClaims * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>Approved</span>
                                    <span class="fw-bold"><?php echo $statusCounts['approved']; ?></span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $totalClaims > 0 ? ($statusCounts['approved'] / $totalClaims * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>Rejected</span>
                                    <span class="fw-bold"><?php echo $statusCounts['rejected']; ?></span>
                                </div>
                                <div class="progress" style="height: 6px;">
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
                <div class="list-group">
                    <a href="claims.php" class="list-group-item list-group-item-action py-2 px-3">
                        <i class="fas fa-list me-2"></i> View All Claims
                    </a>
                    <a href="claims.php?status=new" class="list-group-item list-group-item-action py-2 px-3">
                        <i class="fas fa-file-alt me-2"></i> View New Claims
                        <?php if ($statusCounts['new'] > 0): ?>
                            <span class="badge bg-info float-end"><?php echo $statusCounts['new']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="claims.php?status=in_progress" class="list-group-item list-group-item-action py-2 px-3">
                        <i class="fas fa-spinner me-2"></i> View In Progress Claims
                        <?php if ($statusCounts['in_progress'] > 0): ?>
                            <span class="badge bg-primary float-end"><?php echo $statusCounts['in_progress']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="claims.php?status=on_hold" class="list-group-item list-group-item-action py-2 px-3">
                        <i class="fas fa-pause-circle me-2"></i> View On Hold Claims
                        <?php if ($statusCounts['on_hold'] > 0): ?>
                            <span class="badge bg-warning float-end"><?php echo $statusCounts['on_hold']; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (isAdmin()): ?>
                    <a href="users.php" class="list-group-item list-group-item-action py-2 px-3">
                        <i class="fas fa-users me-2"></i> Manage Users
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Claims -->
<div class="row">
    <div class="col-lg-12 mb-3">
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Claims</h6>
                <a href="claims.php" class="btn btn-sm btn-outline-primary py-0 px-2">View All</a>
            </div>
            <div class="card-body p-2">
                <?php if (!empty($recentClaims)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Claim #</th>
                                    <th>Order ID</th>
                                    <th>Products</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Assignee</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentClaims as $claim): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($claim['claim_number'])): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($claim['claim_number']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Number</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($claim['order_id']); ?></td>
                                        <td>
                                            <?php 
                                            // Get product count for this claim
                                            $productStmt = $conn->prepare("SELECT COUNT(*) as count FROM claim_items WHERE claim_id = ?");
                                            $productStmt->execute([$claim['id']]);
                                            $productCount = $productStmt->fetch()['count'];
                                            echo "<span class='badge bg-primary'>$productCount</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            // Get category for this claim
                                            $catStmt = $conn->prepare("SELECT cc.name 
                                                                      FROM claim_items ci 
                                                                      JOIN claim_categories cc ON ci.category_id = cc.id 
                                                                      WHERE ci.claim_id = ? 
                                                                      LIMIT 1");
                                            $catStmt->execute([$claim['id']]);
                                            $category = $catStmt->fetch();
                                            echo htmlspecialchars($category['name'] ?? 'N/A'); 
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php 
                                                switch($claim['status']) {
                                                    case 'new': echo 'bg-info'; break;
                                                    case 'in_progress': echo 'bg-primary'; break;
                                                    case 'on_hold': echo 'bg-warning'; break;
                                                    case 'approved': echo 'bg-success'; break;
                                                    case 'rejected': echo 'bg-danger'; break;
                                                    default: echo 'bg-secondary';
                                                }
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $claim['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($claim['created_at'])); ?></td>
                                        <td>
                                            <?php 
                                            // Get assignee for this claim
                                            if (!empty($claim['assigned_to'])) {
                                                $userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                                                $userStmt->execute([$claim['assigned_to']]);
                                                $user = $userStmt->fetch();
                                                echo htmlspecialchars($user['username'] ?? 'Unknown');
                                            } else {
                                                echo 'Unassigned';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="view_claim.php?id=<?php echo $claim['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-1" data-bs-toggle="tooltip" title="View Claim">
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
</div>

<script>
// Initialize claims chart
document.addEventListener('DOMContentLoaded', function() {
    // Set up the claims chart
    var ctx = document.getElementById('claimsChart').getContext('2d');
    var claimsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['New', 'In Progress', 'On Hold', 'Approved', 'Rejected'],
            datasets: [{
                label: 'Claims by Status',
                data: [
                    <?php echo $statusCounts['new']; ?>,
                    <?php echo $statusCounts['in_progress']; ?>,
                    <?php echo $statusCounts['on_hold']; ?>,
                    <?php echo $statusCounts['approved']; ?>,
                    <?php echo $statusCounts['rejected']; ?>
                ],
                backgroundColor: [
                    '#36a2eb', // info (new)
                    '#4e73df', // primary (in progress)
                    '#f6c23e', // warning (on hold)
                    '#1cc88a', // success (approved)
                    '#e74a3b'  // danger (rejected)
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
