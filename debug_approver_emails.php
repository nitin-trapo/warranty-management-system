<?php
/**
 * Debug script to diagnose why approvers aren't receiving email notifications
 */

// Define ROOT_PATH if not already defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

// Include database connection
require_once 'config/database.php';

// Include email helper
require_once 'includes/email_helper.php';

// Include user helper
require_once 'includes/user_helper.php';

// Include category helper
require_once 'includes/category_helper.php';

// Set up error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Email Notification Debug</h1>";

// Establish database connection
$conn = getDbConnection();

// 1. Check claim categories and their approvers
echo "<h2>1. Claim Categories and Approvers</h2>";
$categoryStmt = $conn->query("SELECT id, name, approver FROM claim_categories ORDER BY name");
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Category Name</th><th>Approver Role</th></tr>";
foreach ($categories as $category) {
    echo "<tr>";
    echo "<td>{$category['id']}</td>";
    echo "<td>{$category['name']}</td>";
    echo "<td>" . ($category['approver'] ?: 'None') . "</td>";
    echo "</tr>";
}
echo "</table>";

// 2. Check users and their approver roles
echo "<h2>2. Users and Approver Roles</h2>";
$userStmt = $conn->query("SELECT id, username, email, first_name, last_name, approver_role, status FROM users ORDER BY username");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Approver Role</th><th>Status</th></tr>";
foreach ($users as $user) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>{$user['first_name']} {$user['last_name']}</td>";
    echo "<td>" . ($user['approver_role'] ?: 'None') . "</td>";
    echo "<td>{$user['status']}</td>";
    echo "</tr>";
}
echo "</table>";

// 3. Check recent claims and their categories
echo "<h2>3. Recent Claims and Categories</h2>";
$claimStmt = $conn->query("
    SELECT c.id, c.claim_number, c.customer_name, c.created_at, ci.category_id, cc.name as category_name, cc.approver 
    FROM claims c
    JOIN claim_items ci ON c.id = ci.claim_id
    JOIN claim_categories cc ON ci.category_id = cc.id
    ORDER BY c.created_at DESC
    LIMIT 10
");
$claims = $claimStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Claim ID</th><th>Claim Number</th><th>Customer</th><th>Created</th><th>Category</th><th>Approver Role</th></tr>";
foreach ($claims as $claim) {
    echo "<tr>";
    echo "<td>{$claim['id']}</td>";
    echo "<td>{$claim['claim_number']}</td>";
    echo "<td>{$claim['customer_name']}</td>";
    echo "<td>{$claim['created_at']}</td>";
    echo "<td>{$claim['category_name']}</td>";
    echo "<td>" . ($claim['approver'] ?: 'None') . "</td>";
    echo "</tr>";
}
echo "</table>";

// 4. Test approver lookup function
echo "<h2>4. Testing getUsersByApproverRole Function</h2>";

// Get all possible approver roles from categories
$approverRoles = [];
foreach ($categories as $category) {
    if (!empty($category['approver']) && !in_array($category['approver'], $approverRoles)) {
        $approverRoles[] = $category['approver'];
    }
}

// Test each approver role
foreach ($approverRoles as $role) {
    echo "<h3>Testing role: $role</h3>";
    $approvers = getUsersByApproverRole($role);
    
    if (empty($approvers)) {
        echo "<p style='color:red'>No users found with approver role: $role</p>";
    } else {
        echo "<p>Found " . count($approvers) . " users with approver role: $role</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Approver Role</th></tr>";
        foreach ($approvers as $approver) {
            echo "<tr>";
            echo "<td>{$approver['id']}</td>";
            echo "<td>{$approver['username']}</td>";
            echo "<td>{$approver['email']}</td>";
            echo "<td>{$approver['first_name']} {$approver['last_name']}</td>";
            echo "<td>{$approver['approver_role']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// 5. Test email configuration
echo "<h2>5. Email Configuration Test</h2>";
echo "<p>Testing email configuration in email_helper.php...</p>";

// Get the email configuration from the email_helper.php file
$emailHelperContent = file_get_contents('includes/email_helper.php');
preg_match('/\$mail->Host\s*=\s*[\'"](.+?)[\'"]/', $emailHelperContent, $hostMatches);
preg_match('/\$mail->Username\s*=\s*[\'"](.+?)[\'"]/', $emailHelperContent, $usernameMatches);
preg_match('/\$mail->Port\s*=\s*(\d+)/', $emailHelperContent, $portMatches);
preg_match('/\$mail->SMTPSecure\s*=\s*[\'"](.+?)[\'"]/', $emailHelperContent, $secureMatches);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>SMTP Host</td><td>" . ($hostMatches[1] ?? 'Not found') . "</td></tr>";
echo "<tr><td>SMTP Username</td><td>" . ($usernameMatches[1] ?? 'Not found') . "</td></tr>";
echo "<tr><td>SMTP Port</td><td>" . ($portMatches[1] ?? 'Not found') . "</td></tr>";
echo "<tr><td>SMTP Secure</td><td>" . ($secureMatches[1] ?? 'Not found') . "</td></tr>";
echo "</table>";

// 6. Test sending a test email to all approvers
echo "<h2>6. Test Sending Email</h2>";

// Only show the form if not submitted
if (!isset($_POST['send_test'])) {
    echo "<form method='post'>";
    echo "<p>This will send a test email to all users with approver roles.</p>";
    echo "<input type='hidden' name='send_test' value='1'>";
    echo "<button type='submit'>Send Test Email to All Approvers</button>";
    echo "</form>";
} else {
    // Send test email to all approvers
    $testRecipients = [];
    $testSent = 0;
    $testFailed = 0;
    
    foreach ($users as $user) {
        if (!empty($user['approver_role']) && $user['status'] == 'active' && !empty($user['email'])) {
            $testRecipients[] = $user['email'];
            
            // Create a test claim array
            $testClaim = [
                'id' => 'TEST',
                'claim_number' => 'TEST-' . date('YmdHis'),
                'customer_name' => 'Test Customer',
                'customer_email' => 'test@example.com',
                'created_by_name' => 'System Test',
                'created_by_email' => 'system@example.com',
                'category_approver' => $user['approver_role']
            ];
            
            // Create a test claim item
            $testClaimItems = [
                [
                    'sku' => 'TEST-SKU',
                    'product_name' => 'Test Product',
                    'category_name' => 'Test Category',
                    'description' => 'This is a test email to verify approver notifications are working.'
                ]
            ];
            
            // Send test email
            try {
                $result = sendClaimNotificationEmail($testClaim, $testClaimItems, [$user['email']], false, false);
                if ($result) {
                    echo "<p style='color:green'>Successfully sent test email to: {$user['email']} (Role: {$user['approver_role']})</p>";
                    $testSent++;
                } else {
                    echo "<p style='color:red'>Failed to send test email to: {$user['email']} (Role: {$user['approver_role']})</p>";
                    $testFailed++;
                }
            } catch (Exception $e) {
                echo "<p style='color:red'>Error sending test email to {$user['email']}: " . $e->getMessage() . "</p>";
                $testFailed++;
            }
        }
    }
    
    echo "<h3>Email Test Summary</h3>";
    echo "<p>Total approvers: " . count($testRecipients) . "</p>";
    echo "<p>Emails sent successfully: $testSent</p>";
    echo "<p>Emails failed: $testFailed</p>";
    
    if ($testFailed > 0) {
        echo "<p style='color:red'><strong>There were errors sending emails. Check your email configuration and PHP error logs.</strong></p>";
    }
}

// 7. Check PHP error log for recent errors
echo "<h2>7. Recent PHP Error Log Entries</h2>";

// Try to find the PHP error log
$possibleLogPaths = [
    'C:/xampp/php/logs/php_error_log',
    'C:/xampp/apache/logs/error.log',
    ini_get('error_log')
];

$logFound = false;
foreach ($possibleLogPaths as $logPath) {
    if (file_exists($logPath)) {
        echo "<p>Found log at: $logPath</p>";
        $logFound = true;
        
        // Get the last 50 lines of the log
        $logLines = [];
        $file = new SplFileObject($logPath);
        $file->seek(PHP_INT_MAX); // Seek to end of file
        $totalLines = $file->key(); // Get total line count
        
        $startLine = max(0, $totalLines - 50);
        $file->seek($startLine);
        
        $logContent = "";
        while (!$file->eof()) {
            $line = $file->current();
            if (strpos($line, 'claim') !== false || 
                strpos($line, 'email') !== false || 
                strpos($line, 'approver') !== false || 
                strpos($line, 'notification') !== false) {
                $logContent .= htmlspecialchars($line) . "<br>";
            }
            $file->next();
        }
        
        if (empty($logContent)) {
            echo "<p>No relevant log entries found.</p>";
        } else {
            echo "<div style='background-color: #f5f5f5; padding: 10px; max-height: 400px; overflow: auto;'>";
            echo $logContent;
            echo "</div>";
        }
        
        break;
    }
}

if (!$logFound) {
    echo "<p style='color:red'>Could not find PHP error log. Check your PHP configuration.</p>";
}

echo "<h2>Debugging Complete</h2>";
echo "<p>If you're still having issues with approver emails, check:</p>";
echo "<ol>";
echo "<li>Make sure categories have the correct approver role set</li>";
echo "<li>Make sure users have the correct approver role and are active</li>";
echo "<li>Check your email server configuration in email_helper.php</li>";
echo "<li>Review the PHP error logs for any additional errors</li>";
echo "</ol>";
?>
