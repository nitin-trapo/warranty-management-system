<?php
/**
 * Simple test script for sending emails to approvers
 */

// Define ROOT_PATH if not already defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

// Include required files
require_once 'config/database.php';
require_once 'includes/email_helper.php';
require_once 'includes/user_helper.php';
require_once 'includes/category_helper.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Email Notification Test</h1>";

// Establish database connection
$conn = getDbConnection();

// Basic styling
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    pre { background: #f5f5f5; padding: 10px; overflow: auto; }
</style>";

// Check if form is submitted
if (isset($_POST['send_test'])) {
    $testEmail = $_POST['test_email'];
    $testRole = $_POST['test_role'];
    
    echo "<h2>Sending Test Email</h2>";
    echo "<p>Sending to: <strong>$testEmail</strong> (Role: <strong>$testRole</strong>)</p>";
    
    // Create a test claim
    $testClaim = [
        'id' => 'TEST-' . time(),
        'claim_number' => 'TEST-' . date('YmdHis'),
        'order_id' => 'TEST-ORDER',
        'customer_name' => 'Test Customer',
        'customer_email' => 'test@example.com',
        'customer_phone' => '123-456-7890',
        'delivery_date' => date('Y-m-d'),
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'pending',
        'created_by_name' => 'System Test',
        'created_by_email' => 'system@example.com',
        'category_approver' => $testRole
    ];
    
    // Create test claim items
    $testClaimItems = [
        [
            'id' => 1,
            'claim_id' => 'TEST-' . time(),
            'sku' => 'TEST-SKU-001',
            'product_name' => 'Test Product',
            'product_type' => 'Test Type',
            'description' => 'This is a test email to verify approver notifications are working.',
            'category_id' => 1,
            'category_name' => 'Test Category'
        ]
    ];
    
    // Debug information
    echo "<h3>Debug Information:</h3>";
    echo "<pre>";
    echo "Claim Data:\n";
    print_r($testClaim);
    echo "\nClaim Items:\n";
    print_r($testClaimItems);
    echo "\nRecipient: $testEmail\n";
    echo "</pre>";
    
    try {
        // Send the test email with detailed logging
        echo "<h3>Email Sending Log:</h3>";
        echo "<pre>";
        
        // Enable direct output for debugging
        echo "Starting email send process...\n";
        
        // Send the test email
        $result = sendClaimNotificationEmail($testClaim, $testClaimItems, [$testEmail], false, false);
        
        if ($result) {
            echo "\n<span class='success'>SUCCESS: Email sent successfully!</span>\n";
        } else {
            echo "\n<span class='error'>ERROR: Failed to send email.</span>\n";
        }
        
        echo "</pre>";
    } catch (Exception $e) {
        echo "<pre class='error'>";
        echo "EXCEPTION: " . $e->getMessage() . "\n";
        echo "Stack Trace:\n" . $e->getTraceAsString();
        echo "</pre>";
    }
    
    // Check email configuration
    echo "<h3>Email Configuration:</h3>";
    $emailHelperPath = 'includes/email_helper.php';
    if (file_exists($emailHelperPath)) {
        $emailHelperContent = file_get_contents($emailHelperPath);
        
        // Extract SMTP settings
        preg_match('/\$mail->Host\s*=\s*[\'"](.+?)[\'"]/', $emailHelperContent, $hostMatches);
        preg_match('/\$mail->Username\s*=\s*[\'"](.+?)[\'"]/', $emailHelperContent, $usernameMatches);
        preg_match('/\$mail->Port\s*=\s*(\d+)/', $emailHelperContent, $portMatches);
        
        echo "<pre>";
        echo "SMTP Host: " . ($hostMatches[1] ?? 'Not found') . "\n";
        echo "SMTP Username: " . ($usernameMatches[1] ?? 'Not found') . "\n";
        echo "SMTP Port: " . ($portMatches[1] ?? 'Not found') . "\n";
        echo "</pre>";
    }
    
    echo "<p><a href='test_email_send.php'>Send another test email</a></p>";
} else {
    // Show test email form
    echo "<h2>Send Test Email</h2>";
    echo "<form method='post' action=''>";
    echo "<p><label>Email Address: <input type='email' name='test_email' required></label></p>";
    echo "<p><label>Approver Role: <select name='test_role' required>";
    echo "<option value=''>Select Role</option>";
    
    // Get all possible approver roles from categories
    $rolesQuery = "SELECT DISTINCT approver FROM claim_categories WHERE approver IS NOT NULL AND approver != ''";
    $rolesStmt = $conn->query($rolesQuery);
    $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($roles as $role) {
        echo "<option value='$role'>$role</option>";
    }
    
    echo "</select></label></p>";
    echo "<p><input type='submit' name='send_test' value='Send Test Email'></p>";
    echo "</form>";
    
    // Show existing approver roles and users
    echo "<h2>Current Approver Roles</h2>";
    $approverUsersQuery = "SELECT id, username, email, approver_role, status FROM users WHERE approver_role IS NOT NULL AND approver_role != '' ORDER BY approver_role, username";
    $approverUsersStmt = $conn->query($approverUsersQuery);
    $approverUsers = $approverUsersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($approverUsers)) {
        echo "<p class='error'>No users with approver roles found!</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Username</th><th>Email</th><th>Approver Role</th><th>Status</th></tr>";
        
        foreach ($approverUsers as $user) {
            $statusClass = $user['status'] === 'active' ? 'success' : 'error';
            echo "<tr>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['approver_role']}</td>";
            echo "<td class='$statusClass'>{$user['status']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Show categories and their approvers
    echo "<h2>Categories and Approvers</h2>";
    $categoriesQuery = "SELECT id, name, approver FROM claim_categories ORDER BY name";
    $categoriesStmt = $conn->query($categoriesQuery);
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Category</th><th>Approver Role</th></tr>";
    
    foreach ($categories as $category) {
        echo "<tr>";
        echo "<td>{$category['name']}</td>";
        echo "<td>" . ($category['approver'] ?: '<span class="error">None</span>') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}
?>
