<?php
// Start session
session_start();

// Include database connection
require_once '../../includes/db_connect.php';

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
    echo "Audit logs table created successfully!";
    
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
        [1, 'update', 'claim', 1, 'Updated claim status from in_progress to approved', '127.0.0.1']
    ];
    
    $insertQuery = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    
    foreach ($sampleData as $data) {
        $stmt->execute($data);
    }
    
    echo "<br>Sample audit log data inserted successfully!";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
