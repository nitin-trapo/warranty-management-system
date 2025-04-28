<?php
/**
 * Enhanced test script for approver email notifications
 * This script provides comprehensive debugging for the approver email system
 */

// Include required files
require_once 'config/database.php';
require_once 'includes/email_helper.php';
require_once 'includes/user_helper.php';
require_once 'includes/category_helper.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Enhanced Testing for Approver Email System</h1>";

// Establish database connection
$conn = getDbConnection();

// Add CSS for better formatting
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #2c3e50; }
    h2 { color: #3498db; margin-top: 30px; }
    h3 { color: #2980b9; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .code { font-family: monospace; background-color: #f5f5f5; padding: 10px; overflow: auto; }
</style>";

// Test 1: Check categories and their approvers
echo "<h2>Test 1: Categories and Approvers</h2>";
$stmt = $conn->query("SELECT * FROM claim_categories ORDER BY id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Approver</th></tr>";
foreach ($categories as $category) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($category['id']) . "</td>";
    echo "<td>" . htmlspecialchars($category['name']) . "</td>";
    echo "<td>" . htmlspecialchars($category['approver'] ?? 'None') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 2: Check users and their approver roles
echo "<h2>Test 2: Users and Approver Roles</h2>";
$stmt = $conn->query("SELECT id, username, email, first_name, last_name, approver_role, status FROM users ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Approver Role</th><th>Status</th></tr>";
foreach ($users as $user) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($user['id']) . "</td>";
    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
    echo "<td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($user['approver_role'] ?? 'None') . "</td>";
    echo "<td>" . htmlspecialchars($user['status']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 3: Helper Functions
echo "<h2>Test 3: Helper Functions</h2>";

// Test getUsersByApproverRole function
echo "<h3>Testing getUsersByApproverRole function</h3>";

$approverRoles = ['Production coordinator', 'Stan', 'Finance'];

foreach ($approverRoles as $role) {
    echo "<p>Testing role: <strong>$role</strong></p>";
    $users = getUsersByApproverRole($role);
    
    if (empty($users)) {
        echo "<p class='error'>No users found with approver role: $role</p>";
    } else {
        echo "<p class='success'>Found " . count($users) . " users with approver role: $role</p>";
        echo "<ul>";
        foreach ($users as $user) {
            echo "<li>{$user['username']} ({$user['email']})</li>";
        }
        echo "</ul>";
    }
}

// Test getCategoryApprover function
echo "<h3>Testing getCategoryApprover function</h3>";
// Test 4: Test getCategoryApprover function
echo "<h2>Test 4: Testing getCategoryApprover Function</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Category ID</th><th>Category Name</th><th>Approver Role</th></tr>";
foreach ($categories as $category) {
    $approverRole = getCategoryApprover($category['id']);
    echo "<tr>";
    echo "<td>" . htmlspecialchars($category['id']) . "</td>";
    echo "<td>" . htmlspecialchars($category['name']) . "</td>";
    echo "<td>" . htmlspecialchars($approverRole ?? 'None') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Test Complete</h2>";
echo "<p>Check the PHP error log for additional debugging information.</p>";
?>
