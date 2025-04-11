<?php
/**
 * Export Report
 * 
 * This file exports report data in CSV format.
 */

// Include database connection
require_once '../config/database.php';

// Check if report type is provided
if (!isset($_GET['type']) || empty($_GET['type'])) {
    die("Report type is required.");
}

// Get parameters
$reportType = $_GET['type'];
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Set filename
$filename = 'warranty_' . $reportType . '_report_' . date('Y-m-d') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

try {
    $conn = getDbConnection();
    
    // Export claim performance report
    if ($reportType == 'claim_performance') {
        // Write CSV header
        fputcsv($output, ['Warranty Claims Performance Report']);
        fputcsv($output, ['Date Range:', date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate))]);
        fputcsv($output, []);
        
        // Claims by status
        fputcsv($output, ['Claims by Status']);
        fputcsv($output, ['Status', 'Count', 'Percentage']);
        
        $statusQuery = "SELECT status, COUNT(*) as count 
                       FROM claims 
                       WHERE created_at BETWEEN ? AND ? 
                       GROUP BY status";
        $stmt = $conn->prepare($statusQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $claimsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalClaims = 0;
        foreach ($claimsByStatus as $status) {
            $totalClaims += $status['count'];
        }
        
        foreach ($claimsByStatus as $status) {
            $percentage = $totalClaims > 0 ? ($status['count'] / $totalClaims * 100) : 0;
            fputcsv($output, [
                ucfirst(str_replace('_', ' ', $status['status'])),
                $status['count'],
                number_format($percentage, 1) . '%'
            ]);
        }
        
        fputcsv($output, ['Total', $totalClaims, '100%']);
        fputcsv($output, []);
        
        // Average resolution time
        fputcsv($output, ['Resolution Time']);
        
        $resolutionQuery = "SELECT AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)) as avg_days 
                           FROM claims 
                           WHERE status IN ('approved', 'rejected') 
                           AND created_at BETWEEN ? AND ? 
                           AND updated_at IS NOT NULL";
        $stmt = $conn->prepare($resolutionQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $avgResolutionDays = $stmt->fetch(PDO::FETCH_ASSOC)['avg_days'] ?? 0;
        
        fputcsv($output, ['Average Days to Resolve', number_format($avgResolutionDays, 1)]);
        fputcsv($output, []);
        
        // SLA compliance
        fputcsv($output, ['SLA Compliance']);
        
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
        
        fputcsv($output, ['Total Resolved Claims', $totalResolved]);
        fputcsv($output, ['Claims Resolved Within SLA', $withinSla]);
        fputcsv($output, ['SLA Compliance Rate', number_format($slaComplianceRate, 1) . '%']);
        fputcsv($output, []);
        
        // Escalated claims
        fputcsv($output, ['Escalated Claims (Exceeding SLA)']);
        fputcsv($output, ['ID', 'Order ID', 'Customer', 'Status', 'Days Open', 'SLA (Days)', 'Overdue By']);
        
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
        
        foreach ($escalatedClaims as $claim) {
            fputcsv($output, [
                $claim['id'],
                $claim['order_id'],
                $claim['customer_name'],
                ucfirst(str_replace('_', ' ', $claim['status'])),
                $claim['days_open'],
                $claim['sla_days'],
                ($claim['days_open'] - $claim['sla_days']) . ' days'
            ]);
        }
    }
    
    // Export SKU analysis report
    if ($reportType == 'sku_analysis') {
        // Write CSV header
        fputcsv($output, ['SKU-Based Claim Analysis Report']);
        fputcsv($output, ['Date Range:', date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate))]);
        fputcsv($output, []);
        
        // Most claimed SKUs
        fputcsv($output, ['Most Claimed SKUs']);
        fputcsv($output, ['SKU', 'Product Name', 'Claims']);
        
        $skuQuery = "SELECT ci.sku, COUNT(*) as claim_count, 
                    MAX(ci.product_name) as product_name
                    FROM claim_items ci
                    JOIN claims c ON ci.claim_id = c.id
                    WHERE c.created_at BETWEEN ? AND ?
                    GROUP BY ci.sku
                    ORDER BY claim_count DESC";
        $stmt = $conn->prepare($skuQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $topClaimedSkus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($topClaimedSkus as $sku) {
            fputcsv($output, [
                $sku['sku'],
                $sku['product_name'],
                $sku['claim_count']
            ]);
        }
        
        fputcsv($output, []);
        
        // Claims by category (reason)
        fputcsv($output, ['Claims by Reason']);
        fputcsv($output, ['Category', 'Claims']);
        
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
        
        foreach ($claimsByCategory as $category) {
            fputcsv($output, [
                $category['category_name'],
                $category['claim_count']
            ]);
        }
        
        fputcsv($output, []);
        
        // Detailed claim data by SKU
        fputcsv($output, ['Detailed Claim Data by SKU']);
        fputcsv($output, ['SKU', 'Product Name', 'Order ID', 'Customer', 'Status', 'Created Date']);
        
        $detailedQuery = "SELECT ci.sku, ci.product_name, c.order_id, c.customer_name, c.status, c.created_at
                         FROM claim_items ci
                         JOIN claims c ON ci.claim_id = c.id
                         WHERE c.created_at BETWEEN ? AND ?
                         ORDER BY ci.sku, c.created_at DESC";
        $stmt = $conn->prepare($detailedQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $detailedData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($detailedData as $item) {
            fputcsv($output, [
                $item['sku'],
                $item['product_name'],
                $item['order_id'],
                $item['customer_name'],
                ucfirst(str_replace('_', ' ', $item['status'])),
                date('Y-m-d', strtotime($item['created_at']))
            ]);
        }
    }
    
    // Export product type analysis report
    if ($reportType == 'product_type') {
        // Write CSV header
        fputcsv($output, ['Product Type-Based Claim Analysis Report']);
        fputcsv($output, ['Date Range:', date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate))]);
        fputcsv($output, []);
        
        // Most claimed product types
        fputcsv($output, ['Most Claimed Product Types']);
        fputcsv($output, ['Product Type', 'Claims']);
        
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
        
        foreach ($topClaimedProductTypes as $productType) {
            fputcsv($output, [
                $productType['product_type_name'],
                $productType['claim_count']
            ]);
        }
        
        fputcsv($output, []);
        
        // Claims by product type and category
        fputcsv($output, ['Claims by Product Type and Reason']);
        fputcsv($output, ['Product Type', 'Category', 'Claims']);
        
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
        
        foreach ($claimsByProductTypeCategory as $claim) {
            fputcsv($output, [
                $claim['product_type_name'],
                $claim['category_name'],
                $claim['claim_count']
            ]);
        }
        
        fputcsv($output, []);
        
        // Detailed claim data by product type
        fputcsv($output, ['Detailed Claim Data by Product Type']);
        fputcsv($output, ['Product Type', 'SKU', 'Order ID', 'Customer', 'Status', 'Created Date']);
        
        $detailedQuery = "SELECT ci.product_type, ci.sku, c.order_id, c.customer_name, c.status, c.created_at
                         FROM claim_items ci
                         JOIN claims c ON ci.claim_id = c.id
                         WHERE c.created_at BETWEEN ? AND ?
                         AND ci.product_type IS NOT NULL AND ci.product_type != ''
                         ORDER BY ci.product_type, c.created_at DESC";
        $stmt = $conn->prepare($detailedQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $detailedData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($detailedData as $item) {
            fputcsv($output, [
                $item['product_type'],
                $item['sku'],
                $item['order_id'],
                $item['customer_name'],
                ucfirst(str_replace('_', ' ', $item['status'])),
                date('Y-m-d', strtotime($item['created_at']))
            ]);
        }
    }
    
    // Export all claims data
    if ($reportType == 'all_claims') {
        // Write CSV header
        fputcsv($output, ['All Claims Report']);
        fputcsv($output, ['Date Range:', date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate))]);
        fputcsv($output, []);
        
        fputcsv($output, ['ID', 'Order ID', 'Customer Name', 'Customer Email', 'Customer Phone', 'Delivery Date', 'Status', 'Created At', 'Updated At']);
        
        $claimsQuery = "SELECT * FROM claims 
                       WHERE created_at BETWEEN ? AND ? 
                       ORDER BY created_at DESC";
        $stmt = $conn->prepare($claimsQuery);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($claims as $claim) {
            fputcsv($output, [
                $claim['id'],
                $claim['order_id'],
                $claim['customer_name'],
                $claim['customer_email'],
                $claim['customer_phone'] ?? 'N/A',
                date('Y-m-d', strtotime($claim['delivery_date'])),
                ucfirst(str_replace('_', ' ', $claim['status'])),
                date('Y-m-d H:i:s', strtotime($claim['created_at'])),
                !empty($claim['updated_at']) ? date('Y-m-d H:i:s', strtotime($claim['updated_at'])) : 'N/A'
            ]);
        }
    }
    
} catch (PDOException $e) {
    // Write error to CSV
    fputcsv($output, ['Error generating report: ' . $e->getMessage()]);
}

// Close output stream
fclose($output);
exit;
?>
