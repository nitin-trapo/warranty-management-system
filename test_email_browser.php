<?php
/**
 * Browser-friendly test script for diagnosing email notification issues
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

// Establish database connection
$conn = getDbConnection();

// HTML header with styling
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Notification Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #2c3e50; }
        h2 { color: #3498db; margin-top: 30px; }
        h3 { color: #2980b9; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px; }
        .card { border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 20px; }
        .button { background-color: #4CAF50; border: none; color: white; padding: 10px 20px; 
                 text-align: center; text-decoration: none; display: inline-block; 
                 font-size: 16px; margin: 4px 2px; cursor: pointer; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Email Notification Diagnostic Tool</h1>';

// SECTION 1: System Information
echo '<div class="card">
    <h2>1. System Information</h2>
    <table>
        <tr><th>Setting</th><th>Value</th></tr>
        <tr><td>PHP Version</td><td>' . phpversion() . '</td></tr>
        <tr><td>Server</td><td>' . $_SERVER['SERVER_SOFTWARE'] . '</td></tr>
        <tr><td>ROOT_PATH</td><td>' . ROOT_PATH . '</td></tr>
    </table>
</div>';

// SECTION 2: Email Configuration
echo '<div class="card">
    <h2>2. Email Configuration</h2>';

// Check email_helper.php configuration
$emailHelperPath = 'includes/email_helper.php';
$emailConfigPath = 'config/email_config.php';

if (file_exists($emailConfigPath)) {
    $emailConfigContent = file_get_contents($emailConfigPath);
    
    // Extract SMTP settings
    preg_match('/MAIL_HOST.*?[\'"](.+?)[\'"]/', $emailConfigContent, $hostMatches);
    preg_match('/MAIL_USERNAME.*?[\'"](.+?)[\'"]/', $emailConfigContent, $usernameMatches);
    preg_match('/MAIL_PORT.*?(\d+)/', $emailConfigContent, $portMatches);
    preg_match('/MAIL_ENCRYPTION.*?[\'"](.+?)[\'"]/', $emailConfigContent, $secureMatches);
    
    echo '<table>
        <tr><th>Setting</th><th>Value</th></tr>
        <tr><td>SMTP Host</td><td>' . (isset($hostMatches[1]) ? $hostMatches[1] : '<span class="error">Not found</span>') . '</td></tr>
        <tr><td>SMTP Username</td><td>' . (isset($usernameMatches[1]) ? $usernameMatches[1] : '<span class="error">Not found</span>') . '</td></tr>
        <tr><td>SMTP Port</td><td>' . (isset($portMatches[1]) ? $portMatches[1] : '<span class="error">Not found</span>') . '</td></tr>
        <tr><td>SMTP Encryption</td><td>' . (isset($secureMatches[1]) ? $secureMatches[1] : '<span class="error">Not found</span>') . '</td></tr>
    </table>';
} else {
    echo '<p class="error">Could not find email_config.php file</p>';
}
echo '</div>';

// SECTION 3: Categories and Approvers
echo '<div class="card">
    <h2>3. Categories and Approvers</h2>';

$categoryStmt = $conn->query("SELECT id, name, approver FROM claim_categories ORDER BY name");
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($categories)) {
    echo '<p class="error">No categories found in the database.</p>';
} else {
    echo '<table>
        <tr><th>ID</th><th>Category Name</th><th>Approver Role</th></tr>';
    
    foreach ($categories as $category) {
        echo '<tr>
            <td>' . $category['id'] . '</td>
            <td>' . $category['name'] . '</td>
            <td>' . ($category['approver'] ?: '<span class="error">None</span>') . '</td>
        </tr>';
    }
    
    echo '</table>';
}
echo '</div>';

// SECTION 4: Users with Approver Roles
echo '<div class="card">
    <h2>4. Users with Approver Roles</h2>';

$userStmt = $conn->query("SELECT id, username, email, first_name, last_name, approver_role, status FROM users WHERE approver_role IS NOT NULL AND approver_role != '' ORDER BY approver_role, username");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo '<p class="error">No users with approver roles found!</p>';
} else {
    echo '<table>
        <tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Approver Role</th><th>Status</th></tr>';
    
    foreach ($users as $user) {
        $statusClass = $user['status'] === 'active' ? 'success' : 'error';
        echo '<tr>
            <td>' . $user['id'] . '</td>
            <td>' . $user['username'] . '</td>
            <td>' . $user['email'] . '</td>
            <td>' . $user['first_name'] . ' ' . $user['last_name'] . '</td>
            <td>' . $user['approver_role'] . '</td>
            <td class="' . $statusClass . '">' . $user['status'] . '</td>
        </tr>';
    }
    
    echo '</table>';
}
echo '</div>';

// SECTION 5: Recent Claims
echo '<div class="card">
    <h2>5. Recent Claims</h2>';

$recentClaimsQuery = "SELECT c.id, c.claim_number, c.customer_name, c.created_at, c.created_by, 
                      u.username as creator_username, u.email as creator_email,
                      ci.category_id, cc.name as category_name, cc.approver as category_approver
                      FROM claims c
                      LEFT JOIN users u ON c.created_by = u.id
                      LEFT JOIN claim_items ci ON c.id = ci.claim_id
                      LEFT JOIN claim_categories cc ON ci.category_id = cc.id
                      ORDER BY c.created_at DESC LIMIT 5";

$recentClaimsStmt = $conn->query($recentClaimsQuery);
$recentClaims = $recentClaimsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recentClaims)) {
    echo '<p class="warning">No recent claims found.</p>';
} else {
    echo '<table>
        <tr>
            <th>ID</th>
            <th>Claim Number</th>
            <th>Customer</th>
            <th>Created</th>
            <th>Creator</th>
            <th>Category</th>
            <th>Category Approver</th>
            <th>Matching Approvers</th>
        </tr>';
    
    foreach ($recentClaims as $claim) {
        // Find users with this approver role
        $approverRole = $claim['category_approver'] ?? null;
        $approvers = [];
        
        if (!empty($approverRole)) {
            $approverUsers = getUsersByApproverRole($approverRole);
            foreach ($approverUsers as $user) {
                $approvers[] = $user['email'];
            }
        }
        
        echo '<tr>
            <td>' . $claim['id'] . '</td>
            <td>' . $claim['claim_number'] . '</td>
            <td>' . $claim['customer_name'] . '</td>
            <td>' . $claim['created_at'] . '</td>
            <td>' . $claim['creator_username'] . ' (' . $claim['creator_email'] . ')</td>
            <td>' . $claim['category_name'] . '</td>
            <td>' . ($approverRole ?: '<span class="error">None</span>') . '</td>
            <td>' . (!empty($approvers) ? implode(', ', $approvers) : '<span class="error">No matching approvers</span>') . '</td>
        </tr>';
    }
    
    echo '</table>';
}
echo '</div>';

// SECTION 6: Test Email Form
echo '<div class="card">
    <h2>6. Send Test Email</h2>';

// Check if form is submitted
if (isset($_POST['send_test'])) {
    $testEmail = $_POST['test_email'];
    $testRole = $_POST['test_role'];
    
    echo '<h3>Sending Test Email</h3>';
    echo '<p>Sending to: <strong>' . $testEmail . '</strong> (Role: <strong>' . $testRole . '</strong>)</p>';
    
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
    
    try {
        // Send the test email
        $result = sendClaimNotificationEmail($testClaim, $testClaimItems, [$testEmail], false, false);
        
        if ($result) {
            echo '<p class="success">Test email sent successfully!</p>';
        } else {
            echo '<p class="error">Failed to send test email. Check PHP error logs for details.</p>';
        }
        
        // Check logs
        $logFile = ROOT_PATH . '/logs/email.log';
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $lastEntries = array_slice(explode("\n", $logContent), -30);
            
            echo '<h3>Recent Log Entries</h3>';
            echo '<pre>' . implode("\n", $lastEntries) . '</pre>';
        } else {
            echo '<p class="warning">Email log file not found at: ' . $logFile . '</p>';
        }
    } catch (Exception $e) {
        echo '<p class="error">Error sending test email: ' . $e->getMessage() . '</p>';
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
    }
    
    echo '<p><a href="test_email_browser.php" class="button">Back to Test Form</a></p>';
} else {
    // Show test email form
    echo '<form method="post" action="">
        <p><label>Email Address: <input type="email" name="test_email" required></label></p>
        <p><label>Approver Role: <select name="test_role" required>
            <option value="">Select Role</option>';
    
    // Get all possible approver roles from categories
    $rolesQuery = "SELECT DISTINCT approver FROM claim_categories WHERE approver IS NOT NULL AND approver != ''";
    $rolesStmt = $conn->query($rolesQuery);
    $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($roles as $role) {
        echo '<option value="' . $role . '">' . $role . '</option>';
    }
    
    echo '</select></label></p>
        <p><input type="submit" name="send_test" value="Send Test Email" class="button"></p>
    </form>';
}
echo '</div>';

// SECTION 7: Troubleshooting Guide
echo '<div class="card">
    <h2>7. Troubleshooting Guide</h2>
    <h3>Common Issues</h3>
    <ol>
        <li><strong>Category approver not set</strong> - Make sure categories have the correct approver role set</li>
        <li><strong>No matching users</strong> - Make sure users have the correct approver role and are active</li>
        <li><strong>Email configuration</strong> - Check that the email configuration in email_config.php is correct</li>
        <li><strong>Invalid email addresses</strong> - Verify that user email addresses are valid</li>
        <li><strong>SMTP server issues</strong> - Make sure your email server is properly configured and accessible</li>
    </ol>
    
    <h3>How to Fix</h3>
    <ol>
        <li>Edit categories to set the correct approver role</li>
        <li>Edit users to set the correct approver role and ensure they are active</li>
        <li>Check the email logs for detailed error messages</li>
        <li>Try sending a test email to verify the notification system is working</li>
        <li>Check your PHP error logs for any errors related to email sending</li>
    </ol>
</div>';

// HTML footer
echo '</body>
</html>';
?>
