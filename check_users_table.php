<?php
/**
 * Check users table structure
 */
require_once 'config/database.php';

try {
    $conn = getDbConnection();
    $sql = "DESCRIBE users";
    $stmt = $conn->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Users Table Structure</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
