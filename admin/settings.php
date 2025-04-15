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
        
        // Begin transaction
        $conn->beginTransaction();
        
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
                
                // Update email_config.php file
                $configFile = ROOT_PATH . '/config/email_config.php';
                if (file_exists($configFile)) {
                    try {
                        // Create a backup of the original file
                        $backupFile = $configFile . '.bak';
                        copy($configFile, $backupFile);
                        
                        // Read the current file
                        $configContent = file_get_contents($configFile);
                        
                        // Create new content with updated constants
                        $newConfigContent = "<?php
/**
 * Email Configuration
 * 
 * This file contains the email configuration settings for the Warranty Management System.
 */

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Default email settings
define('MAIL_MAILER', 'smtp');
define('MAIL_HOST', '$smtpHost');
define('MAIL_PORT', $smtpPort);
define('MAIL_USERNAME', '$smtpUsername');
define('MAIL_PASSWORD', '$smtpPassword');
define('MAIL_ENCRYPTION', '$smtpEncryption');
define('MAIL_FROM_ADDRESS', '$fromEmail');
define('MAIL_FROM_NAME', '$fromName');

// Email templates directory
define('EMAIL_TEMPLATES_DIR', ROOT_PATH . '/templates/emails');

";
                        
                        // Extract the functions from the original file
                        $functionMatches = [];
                        preg_match_all('/\/\*\*\s*\n.*?function\s+\w+\s*\(.*?\)\s*{.*?}\n/s', $configContent, $functionMatches);
                        
                        // Append the functions to the new content
                        if (!empty($functionMatches[0])) {
                            $newConfigContent .= implode("\n", $functionMatches[0]);
                        } else {
                            // If function extraction fails, append everything after the constants
                            $startPos = strpos($configContent, "define('EMAIL_TEMPLATES_DIR");
                            if ($startPos !== false) {
                                $startPos = strpos($configContent, "\n", $startPos) + 1;
                                $newConfigContent .= substr($configContent, $startPos);
                            }
                        }
                        
                        // Write the updated content back to the file
                        if (file_put_contents($configFile, $newConfigContent)) {
                            $successMessage = 'Email configuration settings saved successfully in both database and config file.';
                            
                            // Log the update
                            logEmail("Email configuration updated by admin. Host: $smtpHost, Username: $smtpUsername");
                        } else {
                            $successMessage = 'Email configuration settings saved in database, but failed to update config file. Please check file permissions.';
                            
                            // Restore the backup
                            copy($backupFile, $configFile);
                        }
                    } catch (Exception $e) {
                        $successMessage = 'Email configuration settings saved in database, but failed to update config file: ' . $e->getMessage();
                    }
                } else {
                    $successMessage = 'Email configuration settings saved in database, but config file not found.';
                }
            }
            
            $activeTab = 'email_config';
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
        
        // Commit transaction
        $conn->commit();
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Set error message
        $errorMessage = 'Error saving settings: ' . $e->getMessage();
        
        // Log error
        error_log("Error saving settings: " . $e->getMessage());
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
<?php else: ?>
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

<style>
.log-container {
    font-family: monospace;
    font-size: 0.85rem;
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
                        <i class="fas fa-check-circle text-muted me-3"></i>
                        SLA Configuration
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
