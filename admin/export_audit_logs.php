<?php
// Include database connection
require_once '../config/database.php';

// Get database connection
$conn = getDbConnection();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_logs_export_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, [
    'ID', 
    'User', 
    'Action', 
    'Entity Type', 
    'Entity ID', 
    'Details', 
    'IP Address', 
    'Timestamp'
]);

// Filter parameters
$action = isset($_GET['action']) ? $_GET['action'] : '';
$entityType = isset($_GET['entity_type']) ? $_GET['entity_type'] : '';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($action)) {
    $conditions[] = "al.action = ?";
    $params[] = $action;
}

if (!empty($entityType)) {
    // Handle both singular and plural forms
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
    // Get audit logs with user information
    $query = "SELECT al.*, u.username 
              FROM audit_logs al
              LEFT JOIN users u ON al.user_id = u.id
              $whereClause
              ORDER BY al.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    
    // Write data rows
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['username'] ?? 'Unknown',
            ucfirst($row['action']),
            ucfirst(str_replace('_', ' ', $row['entity_type'])),
            $row['entity_id'],
            $row['details'],
            $row['ip_address'],
            $row['created_at']
        ]);
    }
    
} catch(PDOException $e) {
    // Log error
    error_log("Error exporting audit logs: " . $e->getMessage());
    
    // Write error to CSV
    fputcsv($output, ['Error exporting data: ' . $e->getMessage()]);
}

// Close the output stream
fclose($output);
exit;
?>
