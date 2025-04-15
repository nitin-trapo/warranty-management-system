<?php
/**
 * Settings
 * 
 * This file will contain system settings for the Warranty Management System.
 */

// Set page title
$pageTitle = 'Settings';

// Include required files
require_once '../includes/auth_helper.php';
require_once '../config/database.php';
require_once '../includes/email_helper.php';
require_once '../includes/system_settings_helper.php';
require_once '../includes/template_helper.php';

// Enforce admin-only access
enforceAdminOnly();

// Create backup directory if it doesn't exist
$backupDir = '../database/backups';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

/**
 * Format file size in human-readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Include header
require_once 'includes/header.php';

// Initialize variables
$successMessage = '';
$errorMessage = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'email';

// Include email debugging functionality if needed
if ($activeTab === 'debug') {
    require_once '../config/email_config.php';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDbConnection();
        
        // Begin transaction only for operations that need it
        $needsTransaction = isset($_POST['email_settings']) || 
                            isset($_POST['email_config']) || 
                            isset($_POST['template_settings']);
        
        if ($needsTransaction) {
            $conn->beginTransaction();
        }
        
        // Email notification settings
        if (isset($_POST['email_settings'])) {
            // Get form data
            $notificationEmails = trim($_POST['claim_notification_emails']);
            $notifyCreator = isset($_POST['notify_claim_creator']) ? '1' : '0';
            $notifyStaffCreator = isset($_POST['notify_staff_creator']) ? '1' : '0';
            
            // Update settings
            updateSystemSetting('claim_notification_emails', $notificationEmails);
            updateSystemSetting('notify_claim_creator', $notifyCreator);
            updateSystemSetting('notify_staff_creator', $notifyStaffCreator);
            
            // Test email if requested
            if (isset($_POST['send_test_email']) && !empty($_POST['test_email'])) {
                $testEmail = trim($_POST['test_email']);
                if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                    $result = testEmailConfig($testEmail);
                    if ($result['status'] === 'success') {
                        $successMessage = $result['message'];
                    } else {
                        $errorMessage = $result['message'];
                    }
                } else {
                    $errorMessage = 'Invalid test email address.';
                }
            }
            
            // Set success message if no error
            if (empty($errorMessage)) {
                $successMessage = 'Email notification settings saved successfully.';
            }
            
            $activeTab = 'email';
        }
        
        // Email configuration settings
        if (isset($_POST['email_config'])) {
            // Get form data
            $smtpHost = trim($_POST['smtp_host']);
            $smtpPort = trim($_POST['smtp_port']);
            $smtpUsername = trim($_POST['smtp_username']);
            $smtpPassword = trim($_POST['smtp_password']);
            $smtpEncryption = trim($_POST['smtp_encryption']);
            $fromEmail = trim($_POST['from_email']);
            $fromName = trim($_POST['from_name']);
            
            // Validate required fields
            if (empty($smtpHost) || empty($smtpPort) || empty($smtpUsername) || empty($fromEmail) || empty($fromName)) {
                $errorMessage = 'All fields except password are required. If you want to keep the existing password, leave it blank.';
            } else {
                // Update settings in database
                updateSystemSetting('smtp_host', $smtpHost);
                updateSystemSetting('smtp_port', $smtpPort);
                updateSystemSetting('smtp_username', $smtpUsername);
                updateSystemSetting('smtp_encryption', $smtpEncryption);
                updateSystemSetting('company_email', $fromEmail);
                updateSystemSetting('company_name', $fromName);
                
                // Only update password if provided
                if (!empty($smtpPassword)) {
                    updateSystemSetting('smtp_password', $smtpPassword);
                } else {
                    // Get existing password for the config file update
                    $smtpPassword = getSystemSetting('smtp_password') ?: MAIL_PASSWORD;
                }
                
                // Set success message
                $successMessage = 'Email configuration settings saved successfully.';
            }
            
            $activeTab = 'email_config';
        }
        
        // Database backup
        if (isset($_POST['create_backup'])) {
            try {
                // Create backup file name with timestamp
                $timestamp = date('Y-m-d_H-i-s');
                $backupFileName = "warranty_system_backup_{$timestamp}.sql";
                $backupFilePath = $backupDir . '/' . $backupFileName;
                
                // Get database connection
                $conn = getDbConnection();
                
                // Get all tables
                $tables = [];
                $result = $conn->query("SHOW TABLES");
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }
                
                // Start output buffering
                ob_start();
                
                // Add SQL header
                echo "-- Warranty Management System Database Backup\n";
                echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
                echo "-- Host: " . DB_HOST . "\n";
                echo "-- Database: " . DB_NAME . "\n\n";
                
                echo "SET FOREIGN_KEY_CHECKS=0;\n";
                echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
                echo "SET AUTOCOMMIT = 0;\n";
                echo "START TRANSACTION;\n";
                echo "SET time_zone = \"+00:00\";\n\n";
                
                // Process each table
                foreach ($tables as $table) {
                    // Get create table statement
                    $stmt = $conn->query("SHOW CREATE TABLE `$table`");
                    $row = $stmt->fetch(PDO::FETCH_NUM);
                    
                    echo "\n\n-- Table structure for table `$table`\n\n";
                    echo "DROP TABLE IF EXISTS `$table`;\n";
                    echo $row[1] . ";\n\n";
                    
                    // Get table data
                    $result = $conn->query("SELECT * FROM `$table`");
                    $numFields = $result->columnCount();
                    $numRows = $result->rowCount();
                    
                    if ($numRows > 0) {
                        echo "-- Dumping data for table `$table`\n";
                        
                        // Process rows in batches to avoid large INSERT statements
                        $rowCounter = 0;
                        $batchSize = 100;
                        
                        while ($row = $result->fetch(PDO::FETCH_NUM)) {
                            if ($rowCounter % $batchSize == 0) {
                                if ($rowCounter > 0) {
                                    echo ";\n";
                                }
                                echo "INSERT INTO `$table` VALUES\n";
                            } else {
                                echo ",\n";
                            }
                            
                            echo "(";
                            
                            for ($i = 0; $i < $numFields; $i++) {
                                if (isset($row[$i])) {
                                    if (is_numeric($row[$i]) && !preg_match('/^0/', $row[$i])) {
                                        echo $row[$i];
                                    } else {
                                        echo "'" . addslashes($row[$i]) . "'";
                                    }
                                } else {
                                    echo "NULL";
                                }
                                
                                if ($i < ($numFields - 1)) {
                                    echo ",";
                                }
                            }
                            
                            echo ")";
                            
                            $rowCounter++;
                            
                            if ($rowCounter % $batchSize == 0 || $rowCounter == $numRows) {
                                echo ";\n";
                            }
                        }
                        
                        // Ensure we end with a semicolon
                        if ($rowCounter > 0 && $rowCounter % $batchSize != 0) {
                            echo ";\n";
                        }
                    }
                }
                
                echo "\nSET FOREIGN_KEY_CHECKS=1;\n";
                echo "COMMIT;\n";
                
                // Get output buffer content and end buffering
                $sqlContent = ob_get_clean();
                
                // Write SQL content to file
                if (file_put_contents($backupFilePath, $sqlContent) !== false) {
                    // Compress the backup file
                    $zipFile = $backupFilePath . '.zip';
                    $zip = new ZipArchive();
                    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                        $zip->addFile($backupFilePath, $backupFileName);
                        $zip->close();
                        
                        // Remove the uncompressed SQL file
                        unlink($backupFilePath);
                        
                        $successMessage = 'Database backup created successfully.';
                    } else {
                        $errorMessage = 'Failed to compress backup file.';
                    }
                } else {
                    $errorMessage = 'Failed to write backup file.';
                }
            } catch (Exception $e) {
                $errorMessage = 'Error creating backup: ' . $e->getMessage();
            }
            
            $activeTab = 'backup';
        }
        
        // Delete backup
        if (isset($_POST['delete_backup']) && !empty($_POST['backup_file'])) {
            $backupFile = basename($_POST['backup_file']);
            $backupFilePath = $backupDir . '/' . $backupFile;
            
            // Validate file path to prevent directory traversal
            if (strpos($backupFile, '..') !== false || strpos($backupFile, '/') !== false || strpos($backupFile, '\\') !== false) {
                $errorMessage = 'Invalid backup file.';
            } else if (file_exists($backupFilePath) && unlink($backupFilePath)) {
                $successMessage = 'Backup file deleted successfully.';
            } else {
                $errorMessage = 'Failed to delete backup file.';
            }
            
            $activeTab = 'backup';
        }
        
        // Email template settings
        if (isset($_POST['template_settings'])) {
            $templateType = $_POST['template_type'];
            $templateContent = $_POST['template_content'];
            
            // Validate template content
            if (empty($templateContent)) {
                $errorMessage = 'Template content cannot be empty.';
            } else {
                // Save template content to file
                $templateFile = '';
                
                if ($templateType === 'otp') {
                    $templateFile = '../templates/emails/otp_email.php';
                } elseif ($templateType === 'claim') {
                    $templateFile = '../templates/emails/claim_notification.php';
                }
                
                if (!empty($templateFile) && file_put_contents($templateFile, $templateContent)) {
                    $successMessage = 'Email template updated successfully.';
                } else {
                    $errorMessage = 'Failed to update email template.';
                }
            }
            
            $activeTab = 'templates';
        }
        
        // Test email form
        if (isset($_POST['send_test_email']) && !empty($_POST['test_email'])) {
            $testEmail = trim($_POST['test_email']);
            if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $result = testEmailConfig($testEmail);
                if ($result['status'] === 'success') {
                    $successMessage = $result['message'];
                } else {
                    $errorMessage = $result['message'];
                }
            } else {
                $errorMessage = 'Invalid test email address.';
            }
            
            $activeTab = 'debug';
        }
        
        // Test claim notification form
        if (isset($_POST['send_test_claim_notification'])) {
            try {
                // Get the most recent claim
                $stmt = $conn->query("
                    SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name, u.email as created_by_email
                    FROM claims c
                    LEFT JOIN users u ON c.created_by = u.id
                    ORDER BY c.id DESC
                    LIMIT 1
                ");
                $claimData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($claimData) {
                    // Get claim items
                    $stmt = $conn->prepare("
                        SELECT ci.*, c.name as category_name
                        FROM claim_items ci
                        LEFT JOIN categories c ON ci.category_id = c.id
                        WHERE ci.claim_id = ?
                    ");
                    $stmt->execute([$claimData['id']]);
                    $claimItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get notification recipients
                    $recipients = [];
                    if (!empty($_POST['test_email'])) {
                        $testEmail = trim($_POST['test_email']);
                        if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                            $recipients[] = $testEmail;
                        }
                    }
                    
                    // Send email notification
                    $emailSent = sendClaimNotificationEmail(
                        $claimData, 
                        $claimItems, 
                        $recipients, 
                        isset($_POST['notify_customer']), 
                        isset($_POST['notify_staff'])
                    );
                    
                    if ($emailSent) {
                        $successMessage = "Claim notification email sent successfully.";
                    } else {
                        $errorMessage = "Failed to send claim notification email.";
                    }
                } else {
                    $errorMessage = "No claims found in the database.";
                }
            } catch (Exception $e) {
                $errorMessage = "Error sending claim notification: " . $e->getMessage();
            }
            
            $activeTab = 'debug';
        }
        
        // Commit transaction if started
        if ($needsTransaction) {
            $conn->commit();
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error if started
        if (isset($conn) && $needsTransaction) {
            $conn->rollBack();
        }
        
        $errorMessage = 'Error: ' . $e->getMessage();
    }
}

// Get current settings
$notificationEmails = getSystemSetting('claim_notification_emails') ?: '';
$notifyCreator = getSystemSetting('notify_claim_creator') === '1';
$notifyStaffCreator = getSystemSetting('notify_staff_creator') === '1';

// Get email configuration settings
$smtpHost = getSystemSetting('smtp_host') ?: MAIL_HOST;
$smtpPort = getSystemSetting('smtp_port') ?: MAIL_PORT;
$smtpUsername = getSystemSetting('smtp_username') ?: MAIL_USERNAME;
$smtpEncryption = getSystemSetting('smtp_encryption') ?: MAIL_ENCRYPTION;
$fromEmail = getSystemSetting('company_email') ?: MAIL_FROM_ADDRESS;
$fromName = getSystemSetting('company_name') ?: MAIL_FROM_NAME;

// Get template content
$otpTemplate = file_get_contents('../templates/emails/otp_email.php');
$claimTemplate = file_get_contents('../templates/emails/claim_notification.php');

// Default template type for editor
$templateType = isset($_GET['template']) ? $_GET['template'] : 'otp';
$templateContent = $templateType === 'otp' ? $otpTemplate : $claimTemplate;

?>

<div class="page-title">
    <h1>Settings</h1>
</div>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $successMessage; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $errorMessage; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $activeTab === 'email' ? 'active' : ''; ?>" href="?tab=email">Email Notifications</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $activeTab === 'email_config' ? 'active' : ''; ?>" href="?tab=email_config">Email Configuration</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $activeTab === 'templates' ? 'active' : ''; ?>" href="?tab=templates">Email Templates</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $activeTab === 'debug' ? 'active' : ''; ?>" href="?tab=debug">Email Debugging</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $activeTab === 'backup' ? 'active' : ''; ?>" href="?tab=backup">Database Backup</a>
    </li>
</ul>

<!-- Email Notification Settings Tab -->
<?php if ($activeTab === 'email'): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Email Notification Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="settings.php?tab=email">
                    <input type="hidden" name="email_settings" value="1">
                    
                    <div class="mb-3">
                        <label for="claim_notification_emails" class="form-label">Claim Notification Recipients</label>
                        <textarea class="form-control" id="claim_notification_emails" name="claim_notification_emails" rows="3" placeholder="Enter email addresses separated by commas"><?php echo htmlspecialchars($notificationEmails); ?></textarea>
                        <div class="form-text">Enter email addresses that should receive notifications when new claims are created. Separate multiple emails with commas.</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="notify_claim_creator" name="notify_claim_creator" <?php echo $notifyCreator ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="notify_claim_creator">Send notification to claim creator (customer)</label>
                        <div class="form-text">If checked, the customer who created the claim will also receive a notification email.</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="notify_staff_creator" name="notify_staff_creator" <?php echo $notifyStaffCreator ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="notify_staff_creator">Send notification to staff member who created the claim</label>
                        <div class="form-text">If checked, the admin or CS agent who created the claim will also receive a notification email.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Test Email</label>
                        <div class="input-group">
                            <input type="email" class="form-control" id="test_email" name="test_email" placeholder="Enter email address for testing">
                            <button type="submit" name="send_test_email" class="btn btn-outline-secondary">Send Test Email</button>
                        </div>
                        <div class="form-text">Send a test email to verify your email configuration.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Email Configuration Tab -->
<?php elseif ($activeTab === 'email_config'): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Email Configuration</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="settings.php?tab=email_config">
                    <input type="hidden" name="email_config" value="1">
                    
                    <div class="mb-3">
                        <label for="smtp_host" class="form-label">SMTP Host</label>
                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtpHost); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_port" class="form-label">SMTP Port</label>
                        <input type="text" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtpPort); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_username" class="form-label">SMTP Username</label>
                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($smtpUsername); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_password" class="form-label">SMTP Password</label>
                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" placeholder="Leave blank to keep existing password">
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_encryption" class="form-label">SMTP Encryption</label>
                        <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                            <option value="tls" <?php echo $smtpEncryption === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo $smtpEncryption === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="from_email" class="form-label">From Email</label>
                        <input type="email" class="form-control" id="from_email" name="from_email" value="<?php echo htmlspecialchars($fromEmail); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="from_name" class="form-label">From Name</label>
                        <input type="text" class="form-control" id="from_name" name="from_name" value="<?php echo htmlspecialchars($fromName); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Email Templates Tab -->
<?php elseif ($activeTab === 'templates'): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Email Templates</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <div class="btn-group" role="group" aria-label="Template selection">
                        <a href="?tab=templates&template=otp" class="btn btn-outline-primary <?php echo $templateType === 'otp' ? 'active' : ''; ?>">OTP Email</a>
                        <a href="?tab=templates&template=claim" class="btn btn-outline-primary <?php echo $templateType === 'claim' ? 'active' : ''; ?>">Claim Notification</a>
                    </div>
                </div>
                
                <form method="POST" action="settings.php?tab=templates&template=<?php echo $templateType; ?>">
                    <input type="hidden" name="template_settings" value="1">
                    <input type="hidden" name="template_type" value="<?php echo $templateType; ?>">
                    
                    <div class="mb-3">
                        <label for="template_content" class="form-label">
                            <?php echo $templateType === 'otp' ? 'OTP Email Template' : 'Claim Notification Template'; ?>
                        </label>
                        <div class="alert alert-info">
                            <strong>Note:</strong> This is a PHP template file. Be careful when editing to maintain valid PHP syntax.
                            The template includes PHP code for dynamic content. Only modify the HTML and CSS portions if you're not familiar with PHP.
                        </div>
                        <textarea class="form-control code-editor" id="template_content" name="template_content" rows="20"><?php echo htmlspecialchars($templateContent); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Available Variables:</h6>
                        <?php if ($templateType === 'otp'): ?>
                        <ul>
                            <li><code>$otp</code> - The OTP code</li>
                            <li><code>$purpose</code> - Purpose of the OTP (login or verification)</li>
                            <li><code>$companyName</code> - Company name from settings</li>
                            <li><code>$expiryMinutes</code> - Minutes until OTP expires</li>
                        </ul>
                        <?php else: ?>
                        <ul>
                            <li><code>$claim</code> - Array of claim data (id, order_id, customer_name, etc.)</li>
                            <li><code>$claimItems</code> - Array of claim items data</li>
                            <li><code>$companyName</code> - Company name from settings</li>
                            <li><code>$isCustomer</code> - Boolean indicating if the recipient is the customer</li>
                            <li><code>$isStaffCreator</code> - Boolean indicating if the recipient is the staff creator</li>
                            <li><code>$adminUrl</code> - URL to view the claim in the admin panel</li>
                        </ul>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Template</button>
                    <a href="?tab=templates&template=<?php echo $templateType; ?>" class="btn btn-outline-secondary">Reset Changes</a>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Email Debugging Tab -->
<?php elseif ($activeTab === 'debug'): ?>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Email Configuration</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <tbody>
                        <tr>
                            <th>SMTP Host</th>
                            <td><?php echo htmlspecialchars(getSystemSetting('smtp_host') ?: MAIL_HOST); ?></td>
                        </tr>
                        <tr>
                            <th>SMTP Port</th>
                            <td><?php echo htmlspecialchars(getSystemSetting('smtp_port') ?: MAIL_PORT); ?></td>
                        </tr>
                        <tr>
                            <th>SMTP Username</th>
                            <td><?php echo htmlspecialchars(getSystemSetting('smtp_username') ?: MAIL_USERNAME); ?></td>
                        </tr>
                        <tr>
                            <th>SMTP Encryption</th>
                            <td><?php echo htmlspecialchars(getSystemSetting('smtp_encryption') ?: MAIL_ENCRYPTION); ?></td>
                        </tr>
                        <tr>
                            <th>From Address</th>
                            <td><?php echo htmlspecialchars(getSystemSetting('company_email') ?: MAIL_FROM_ADDRESS); ?></td>
                        </tr>
                        <tr>
                            <th>From Name</th>
                            <td><?php echo htmlspecialchars(getSystemSetting('company_name') ?: MAIL_FROM_NAME); ?></td>
                        </tr>
                        <tr>
                            <th>PHPMailer Installed</th>
                            <td><?php echo class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>SMTP Connection</th>
                            <td>
                                <?php
                                $smtpStatus = "Unknown";
                                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                                    try {
                                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                                        $mail->isSMTP();
                                        $mail->SMTPDebug = 0;
                                        $mail->Host = getSystemSetting('smtp_host') ?: MAIL_HOST;
                                        $mail->Port = getSystemSetting('smtp_port') ?: MAIL_PORT;
                                        $mail->SMTPAuth = true;
                                        $mail->Username = getSystemSetting('smtp_username') ?: MAIL_USERNAME;
                                        $mail->Password = getSystemSetting('smtp_password') ?: MAIL_PASSWORD;
                                        $mail->SMTPSecure = getSystemSetting('smtp_encryption') ?: MAIL_ENCRYPTION;
                                        $mail->Timeout = 10;
                                        
                                        if ($mail->smtpConnect()) {
                                            $smtpStatus = '<span class="text-success">Connected successfully</span>';
                                            $mail->smtpClose();
                                        } else {
                                            $smtpStatus = '<span class="text-danger">Connection failed</span>';
                                        }
                                    } catch (Exception $e) {
                                        $smtpStatus = '<span class="text-danger">Error: ' . $e->getMessage() . '</span>';
                                    }
                                }
                                echo $smtpStatus;
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Test Email</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="settings.php?tab=debug">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Test Email Address</label>
                        <input type="email" class="form-control" id="test_email" name="test_email" required>
                    </div>
                    <button type="submit" name="send_test_email" class="btn btn-primary">Send Test Email</button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Test Claim Notification</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="settings.php?tab=debug">
                    <div class="mb-3">
                        <label for="test_claim_email" class="form-label">Test Email Address (Optional)</label>
                        <input type="email" class="form-control" id="test_claim_email" name="test_email">
                        <div class="form-text">If provided, this email will receive the test notification. Otherwise, only the selected options below will be used.</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="notify_customer" name="notify_customer" checked>
                        <label class="form-check-label" for="notify_customer">Send to customer</label>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="notify_staff" name="notify_staff" checked>
                        <label class="form-check-label" for="notify_staff">Send to staff creator</label>
                    </div>
                    
                    <button type="submit" name="send_test_claim_notification" class="btn btn-primary">Send Test Claim Notification</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Email Log Entries</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="refreshLogs()">Refresh</button>
            </div>
            <div class="card-body">
                <div class="log-container p-3 bg-light" style="max-height: 600px; overflow-y: auto;">
                    <?php
                    // Get log file path
                    $logFile = defined('EMAIL_LOG_FILE') ? EMAIL_LOG_FILE : ROOT_PATH . '/logs/email.log';
                    $logContent = '';
                    
                    if (file_exists($logFile) && is_readable($logFile)) {
                        // Read the log file
                        $logContent = file_get_contents($logFile);
                        
                        // If the log file is too large, only show the last 200 lines
                        if (strlen($logContent) > 50000) {
                            $lines = explode("\n", $logContent);
                            $lines = array_slice($lines, -200);
                            $logContent = implode("\n", $lines);
                        }
                    } else {
                        $logContent = "Email log file not found or not readable. Please check if the log file exists at: $logFile";
                    }
                    
                    echo nl2br(htmlspecialchars($logContent));
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Database Backup Tab -->
<?php elseif ($activeTab === 'backup'): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Database Backup</h5>
            </div>
            <div class="card-body">
                <p class="mb-4">Create a backup of your database to safeguard your warranty system data. You can download or delete backups as needed.</p>
                
                <form method="POST" action="settings.php?tab=backup" class="mb-4">
                    <button type="submit" name="create_backup" class="btn btn-primary">
                        <i class="fas fa-database me-2"></i>Create New Backup
                    </button>
                </form>
                
                <?php if (file_exists($backupDir) && is_dir($backupDir)): ?>
                    <?php
                    $files = scandir($backupDir);
                    $backupFiles = [];
                    
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                            $backupFiles[] = [
                                'name' => $file,
                                'path' => $backupDir . '/' . $file,
                                'size' => filesize($backupDir . '/' . $file),
                                'date' => filemtime($backupDir . '/' . $file)
                            ];
                        }
                    }
                    
                    // Sort by date (newest first)
                    usort($backupFiles, function($a, $b) {
                        return $b['date'] - $a['date'];
                    });
                    ?>
                    
                    <?php if (count($backupFiles) > 0): ?>
                        <h6 class="mt-4 mb-3">Available Backups:</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Backup File</th>
                                        <th>Size</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backupFiles as $file): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($file['name']); ?></td>
                                        <td><?php echo formatFileSize($file['size']); ?></td>
                                        <td><?php echo date('M j, Y g:i a', $file['date']); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="download_backup.php?file=<?php echo urlencode($file['name']); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download me-1"></i>Download
                                                </a>
                                                <form method="POST" action="settings.php?tab=backup" class="d-inline">
                                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                    <button type="submit" name="delete_backup" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this backup?');">
                                                        <i class="fas fa-trash-alt me-1"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Tip:</strong> It's recommended to download and store backups in a secure location regularly.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No backup files found. Create a backup to protect your data.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.log-container {
    font-family: monospace;
    font-size: 0.85rem;
    line-height: 1.5;
    tab-size: 4;
    white-space: pre-wrap;
    word-break: break-all;
}
</style>

<script>
function refreshLogs() {
    // Reload the page to refresh logs
    window.location.href = 'settings.php?tab=debug';
}
</script>

<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Implemented Features</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-3"></i>
                        Email Notification Settings
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-3"></i>
                        Email Templates
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-3"></i>
                        Database Backup
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check-circle text-muted me-3"></i>
                        File Upload Limits
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check-circle text-muted me-3"></i>
                        System Branding
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">System Version:</div>
                    <div class="col-sm-8">1.0.0</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">PHP Version:</div>
                    <div class="col-sm-8"><?php echo phpversion(); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Database:</div>
                    <div class="col-sm-8">MySQL</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Server:</div>
                    <div class="col-sm-8"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Last Updated:</div>
                    <div class="col-sm-8"><?php echo date('F d, Y'); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.code-editor {
    font-family: monospace;
    font-size: 14px;
    line-height: 1.5;
    tab-size: 4;
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>
