<?php
/**
 * Setup Audit Logs Table
 * 
 * This script creates the audit_logs table and adds sample data
 */

// Include header
require_once 'includes/header.php';

// Get database connection explicitly
$conn = getDbConnection();

// Set page title
$pageTitle = 'Setup Audit Logs';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Setup Audit Logs</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Setup Audit Logs</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-database me-1"></i>
            Create Audit Logs Table
        </div>
        <div class="card-body">
            <?php
            try {
                // Create audit_logs table
                $createTableQuery = "CREATE TABLE IF NOT EXISTS audit_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    action VARCHAR(255) NOT NULL,
                    entity_type VARCHAR(50) NOT NULL,
                    entity_id INT NOT NULL,
                    details TEXT,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )";
                
                $conn->exec($createTableQuery);
                echo "<div class='alert alert-success'>Audit logs table created successfully!</div>";
                
                // Check if sample data already exists
                $checkQuery = "SELECT COUNT(*) FROM audit_logs";
                $count = $conn->query($checkQuery)->fetchColumn();
                
                if ($count == 0) {
                    // Insert some sample data
                    $sampleData = [
                        [1, 'create', 'claim', 1, 'Created new claim #1', '127.0.0.1'],
                        [1, 'update', 'claim', 1, 'Updated claim status from new to in_progress', '127.0.0.1'],
                        [2, 'update', 'claim', 2, 'Added new SKU ABC123 to claim', '127.0.0.1'],
                        [1, 'delete', 'claim_media', 5, 'Deleted media from claim #3', '127.0.0.1'],
                        [3, 'create', 'claim', 4, 'Created new claim #4', '127.0.0.1'],
                        [2, 'update', 'claim', 3, 'Updated claim status from in_progress to approved', '127.0.0.1'],
                        [1, 'create', 'claim_note', 7, 'Added note to claim #2', '127.0.0.1'],
                        [3, 'update', 'claim', 5, 'Updated claim description', '127.0.0.1'],
                        [2, 'create', 'claim', 6, 'Created new claim #6', '127.0.0.1'],
                        [1, 'update', 'claim', 1, 'Updated claim status from in_progress to approved', '127.0.0.1'],
                        [1, 'login', 'user', 1, 'User logged in', '127.0.0.1'],
                        [2, 'login', 'user', 2, 'User logged in', '127.0.0.1'],
                        [3, 'login', 'user', 3, 'User logged in', '127.0.0.1'],
                        [1, 'logout', 'user', 1, 'User logged out', '127.0.0.1'],
                        [1, 'generate', 'report', 0, 'Generated claim performance report', '127.0.0.1']
                    ];
                    
                    $insertQuery = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) 
                                    VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insertQuery);
                    
                    foreach ($sampleData as $data) {
                        $stmt->execute($data);
                    }
                    
                    echo "<div class='alert alert-success'>Sample audit log data inserted successfully!</div>";
                } else {
                    echo "<div class='alert alert-info'>Audit logs table already contains data. No sample data was inserted.</div>";
                }
                
            } catch(PDOException $e) {
                echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
            ?>
            
            <div class="mt-3">
                <a href="audit_logs.php" class="btn btn-primary">
                    <i class="fas fa-history me-1"></i> View Audit Logs
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
