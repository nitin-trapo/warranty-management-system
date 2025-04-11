<?php
/**
 * Reports
 * 
 * This file generates various reports for the Warranty Management System.
 */

// Set page title
$pageTitle = 'Reports';

// Include header
require_once 'includes/header.php';

// Include database connection
require_once '../config/database.php';

// Initialize variables
$reportType = isset($_GET['type']) ? $_GET['type'] : 'claim_performance';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get report data
try {
    $conn = getDbConnection();
    
    // Data for claim performance report
    if ($reportType == 'claim_performance') {
        // Claims by status
        $statusQuery = "SELECT status, COUNT(*) as count 
                       FROM claims 
                       WHERE created_at BETWEEN ? AND ? 
                       GROUP BY status";
        $stmt = $conn->prepare($statusQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $claimsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format claims by status for easy access
        $statusCounts = [
            'new' => 0,
            'in_progress' => 0,
            'on_hold' => 0,
            'approved' => 0,
            'rejected' => 0
        ];
        
        $totalClaims = 0;
        foreach ($claimsByStatus as $status) {
            $statusCounts[$status['status']] = $status['count'];
            $totalClaims += $status['count'];
        }
        
        // Average resolution time
        $resolutionQuery = "SELECT AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)) as avg_days 
                           FROM claims 
                           WHERE status IN ('approved', 'rejected') 
                           AND created_at BETWEEN ? AND ? 
                           AND updated_at IS NOT NULL";
        $stmt = $conn->prepare($resolutionQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $avgResolutionDays = $stmt->fetch(PDO::FETCH_ASSOC)['avg_days'] ?? 0;
        
        // SLA compliance
        $slaQuery = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN TIMESTAMPDIFF(DAY, c.created_at, c.updated_at) <= IFNULL(cc.sla_days, 7) THEN 1 ELSE 0 END) as within_sla
                    FROM claims c
                    LEFT JOIN claim_items ci ON c.id = ci.claim_id
                    LEFT JOIN claim_categories cc ON ci.category_id = cc.id
                    WHERE c.status IN ('approved', 'rejected')
                    AND c.created_at BETWEEN ? AND ?
                    AND c.updated_at IS NOT NULL
                    GROUP BY c.id";
        $stmt = $conn->prepare($slaQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $slaData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalResolved = count($slaData);
        $withinSla = 0;
        foreach ($slaData as $item) {
            if ($item['within_sla'] > 0) {
                $withinSla++;
            }
        }
        
        $slaComplianceRate = $totalResolved > 0 ? ($withinSla / $totalResolved * 100) : 0;
        
        // Escalated claims (exceeding SLA)
        $escalatedQuery = "SELECT c.id, c.order_id, c.customer_name, c.status, c.created_at,
                           TIMESTAMPDIFF(DAY, c.created_at, NOW()) as days_open,
                           IFNULL(cc.sla_days, 7) as sla_days
                           FROM claims c
                           LEFT JOIN claim_items ci ON c.id = ci.claim_id
                           LEFT JOIN claim_categories cc ON ci.category_id = cc.id
                           WHERE c.status NOT IN ('approved', 'rejected')
                           AND c.created_at BETWEEN ? AND ?
                           AND TIMESTAMPDIFF(DAY, c.created_at, NOW()) > IFNULL(cc.sla_days, 7)
                           GROUP BY c.id
                           ORDER BY days_open DESC";
        $stmt = $conn->prepare($escalatedQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $escalatedClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Data for SKU-based analysis
    if ($reportType == 'sku_analysis') {
        // Most claimed SKUs
        $skuQuery = "SELECT ci.sku, COUNT(*) as claim_count, 
                    MAX(ci.product_name) as product_name
                    FROM claim_items ci
                    JOIN claims c ON ci.claim_id = c.id
                    WHERE c.created_at BETWEEN ? AND ?
                    GROUP BY ci.sku
                    ORDER BY claim_count DESC
                    LIMIT 10";
        $stmt = $conn->prepare($skuQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $topClaimedSkus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Claims by category (reason)
        $categoryQuery = "SELECT cc.name as category_name, COUNT(*) as claim_count
                         FROM claim_items ci
                         JOIN claims c ON ci.claim_id = c.id
                         JOIN claim_categories cc ON ci.category_id = cc.id
                         WHERE c.created_at BETWEEN ? AND ?
                         GROUP BY cc.name
                         ORDER BY claim_count DESC";
        $stmt = $conn->prepare($categoryQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $claimsByCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total claims for each SKU
        $skuTotalQuery = "SELECT ci.sku, COUNT(*) as total_claims
                         FROM claim_items ci
                         JOIN claims c ON ci.claim_id = c.id
                         WHERE c.created_at BETWEEN ? AND ?
                         GROUP BY ci.sku";
        $stmt = $conn->prepare($skuTotalQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $skuTotals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format SKU totals for easy access
        $skuClaimCounts = [];
        foreach ($skuTotals as $item) {
            $skuClaimCounts[$item['sku']] = $item['total_claims'];
        }
    }
    
    // Data for product type analysis
    if ($reportType == 'product_type') {
        // Most claimed product types
        $productTypeQuery = "SELECT ci.product_type as product_type_name, COUNT(*) as claim_count
                             FROM claim_items ci
                             JOIN claims c ON ci.claim_id = c.id
                             WHERE c.created_at BETWEEN ? AND ?
                             AND ci.product_type IS NOT NULL AND ci.product_type != ''
                             GROUP BY ci.product_type
                             ORDER BY claim_count DESC";
        $stmt = $conn->prepare($productTypeQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $topClaimedProductTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Claims by product type and category
        $productTypeCategoryQuery = "SELECT ci.product_type as product_type_name, cc.name as category_name, COUNT(*) as claim_count
                                     FROM claim_items ci
                                     JOIN claims c ON ci.claim_id = c.id
                                     JOIN claim_categories cc ON ci.category_id = cc.id
                                     WHERE c.created_at BETWEEN ? AND ?
                                     AND ci.product_type IS NOT NULL AND ci.product_type != ''
                                     GROUP BY ci.product_type, cc.name
                                     ORDER BY claim_count DESC";
        $stmt = $conn->prepare($productTypeCategoryQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $claimsByProductTypeCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    // Log error
    error_log("Error generating reports: " . $e->getMessage());
    
    // Set error message
    $errorMessage = "An error occurred while generating reports. Please try again.";
}
?>

<div class="page-title">
    <h1>Reports</h1>
</div>

<!-- Report Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="reports.php" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="report_type" class="form-label">Report Type</label>
                <select class="form-select" id="report_type" name="type">
                    <option value="claim_performance" <?php echo $reportType == 'claim_performance' ? 'selected' : ''; ?>>Claim Performance</option>
                    <option value="sku_analysis" <?php echo $reportType == 'sku_analysis' ? 'selected' : ''; ?>>SKU Analysis</option>
                    <option value="product_type" <?php echo $reportType == 'product_type' ? 'selected' : ''; ?>>Product Type Analysis</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
            </div>
            <div class="col-md-2">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Generate Report</button>
            </div>
            <div class="col-md-2">
                <div class="dropdown w-100">
                    <button class="btn btn-success dropdown-toggle w-100" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-file-export me-1"></i> Export
                    </button>
                    <ul class="dropdown-menu w-100" aria-labelledby="exportDropdown">
                        <li><a class="dropdown-item" href="export_report.php?type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">Current Report</a></li>
                        <li><a class="dropdown-item" href="export_report.php?type=all_claims&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">All Claims Data</a></li>
                    </ul>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (isset($errorMessage)): ?>
<div class="alert alert-danger">
    <?php echo $errorMessage; ?>
</div>
<?php else: ?>

<!-- Claim Performance Report -->
<?php if ($reportType == 'claim_performance'): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Claim Performance Report</h5>
                <small class="text-muted"><?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></small>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Claims by Status -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2 mb-3">Claims by Status</h6>
                        <div class="chart-container" style="position: relative; height:250px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($statusCounts as $status => $count): ?>
                                    <tr>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $status)); ?></td>
                                        <td><?php echo $count; ?></td>
                                        <td><?php echo $totalClaims > 0 ? number_format(($count / $totalClaims) * 100, 1) : 0; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-active">
                                        <td><strong>Total</strong></td>
                                        <td><strong><?php echo $totalClaims; ?></strong></td>
                                        <td>100%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Resolution Time & SLA Compliance -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2 mb-3">Resolution Time & SLA Compliance</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="mb-0"><?php echo number_format($avgResolutionDays, 1); ?></h3>
                                        <p class="mb-0">Avg. Days to Resolve</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card <?php echo $slaComplianceRate >= 90 ? 'bg-success' : ($slaComplianceRate >= 75 ? 'bg-warning' : 'bg-danger'); ?> text-white">
                                    <div class="card-body text-center">
                                        <h3 class="mb-0"><?php echo number_format($slaComplianceRate, 1); ?>%</h3>
                                        <p class="mb-0">SLA Compliance</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="chart-container mt-3" style="position: relative; height:150px;">
                            <canvas id="slaChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Escalated Claims -->
                <div class="row">
                    <div class="col-12">
                        <h6 class="border-bottom pb-2 mb-3">Escalated Claims (Exceeding SLA)</h6>
                        <?php if (count($escalatedClaims) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Days Open</th>
                                        <th>SLA (Days)</th>
                                        <th>Overdue By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($escalatedClaims as $claim): ?>
                                    <tr>
                                        <td><?php echo $claim['id']; ?></td>
                                        <td><?php echo htmlspecialchars($claim['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['customer_name']); ?></td>
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
                                        <td><?php echo $claim['days_open']; ?></td>
                                        <td><?php echo $claim['sla_days']; ?></td>
                                        <td>
                                            <span class="text-danger">
                                                <?php echo $claim['days_open'] - $claim['sla_days']; ?> days
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_claim.php?id=<?php echo $claim['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i> No escalated claims found. All claims are within SLA.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- SKU Analysis Report -->
<?php if ($reportType == 'sku_analysis'): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">SKU-Based Claim Analysis</h5>
                <small class="text-muted"><?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></small>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Most Claimed SKUs -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2 mb-3">Most Claimed SKUs</h6>
                        <div class="chart-container" style="position: relative; height:300px;">
                            <canvas id="skuChart"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Product Name</th>
                                        <th>Claims</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topClaimedSkus as $sku): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sku['sku']); ?></td>
                                        <td><?php echo htmlspecialchars($sku['product_name']); ?></td>
                                        <td><?php echo $sku['claim_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Claims by Category (Reason) -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2 mb-3">Claims by Reason</h6>
                        <div class="chart-container" style="position: relative; height:300px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Claims</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($claimsByCategory as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                        <td><?php echo $category['claim_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Product Type Analysis Report -->
<?php if ($reportType == 'product_type'): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Product Type-Based Claim Analysis</h5>
                <small class="text-muted"><?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></small>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Most Claimed Product Types -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2 mb-3">Most Claimed Product Types</h6>
                        <div class="chart-container" style="position: relative; height:300px;">
                            <canvas id="productTypeChart"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product Type</th>
                                        <th>Claims</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topClaimedProductTypes as $productType): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($productType['product_type_name']); ?></td>
                                        <td><?php echo $productType['claim_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Claims by Product Type (Reason) -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2 mb-3">Claims by Reason</h6>
                        <div class="chart-container" style="position: relative; height:300px;">
                            <canvas id="productTypeCategoryChart"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product Type</th>
                                        <th>Category</th>
                                        <th>Claims</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($claimsByProductTypeCategory as $claim): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($claim['product_type_name']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['category_name']); ?></td>
                                        <td><?php echo $claim['claim_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($reportType == 'claim_performance'): ?>
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
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
                        position: 'right'
                    }
                }
            }
        });
        
        // SLA Chart
        const slaCtx = document.getElementById('slaChart').getContext('2d');
        const slaChart = new Chart(slaCtx, {
            type: 'doughnut',
            data: {
                labels: ['Within SLA', 'Exceeding SLA'],
                datasets: [{
                    data: [
                        <?php echo $withinSla; ?>,
                        <?php echo $totalResolved - $withinSla; ?>
                    ],
                    backgroundColor: [
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
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
        
        <?php if ($reportType == 'sku_analysis'): ?>
        // SKU Chart
        const skuCtx = document.getElementById('skuChart').getContext('2d');
        const skuChart = new Chart(skuCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($topClaimedSkus as $sku): ?>
                    '<?php echo htmlspecialchars($sku['sku']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Number of Claims',
                    data: [
                        <?php foreach ($topClaimedSkus as $sku): ?>
                        <?php echo $sku['claim_count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
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
                }
            }
        });
        
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: [
                    <?php foreach ($claimsByCategory as $category): ?>
                    '<?php echo htmlspecialchars($category['category_name']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($claimsByCategory as $category): ?>
                        <?php echo $category['claim_count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#0d6efd', // primary
                        '#6610f2', // purple
                        '#6f42c1', // indigo
                        '#d63384', // pink
                        '#dc3545', // danger
                        '#fd7e14', // orange
                        '#ffc107', // warning
                        '#198754', // success
                        '#20c997', // teal
                        '#0dcaf0'  // info
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        <?php endif; ?>
        
        <?php if ($reportType == 'product_type'): ?>
        // Product Type Chart
        const productTypeCtx = document.getElementById('productTypeChart').getContext('2d');
        const productTypeChart = new Chart(productTypeCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($topClaimedProductTypes as $productType): ?>
                    '<?php echo htmlspecialchars($productType['product_type_name']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Number of Claims',
                    data: [
                        <?php foreach ($topClaimedProductTypes as $productType): ?>
                        <?php echo $productType['claim_count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
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
                }
            }
        });
        
        // Product Type Category Chart
        const productTypeCategoryCtx = document.getElementById('productTypeCategoryChart').getContext('2d');
        const productTypeCategoryChart = new Chart(productTypeCategoryCtx, {
            type: 'pie',
            data: {
                labels: [
                    <?php foreach ($claimsByProductTypeCategory as $claim): ?>
                    '<?php echo htmlspecialchars($claim['product_type_name'] . ' - ' . $claim['category_name']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($claimsByProductTypeCategory as $claim): ?>
                        <?php echo $claim['claim_count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#0d6efd', // primary
                        '#6610f2', // purple
                        '#6f42c1', // indigo
                        '#d63384', // pink
                        '#dc3545', // danger
                        '#fd7e14', // orange
                        '#ffc107', // warning
                        '#198754', // success
                        '#20c997', // teal
                        '#0dcaf0'  // info
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        <?php endif; ?>
    });
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
