<?php
/**
 * Email Debugging Tool
 * 
 * This file provides tools to debug email sending issues in the Warranty Management System.
 */

// Set page title
$pageTitle = 'Email Debugging';

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
$logContent = '';
$emailConfig = [];
$testResult = null;
$claimData = null;
$claimItems = [];

// Get email configuration
$emailConfig = [
    'host' => MAIL_HOST,
    'port' => MAIL_PORT,
    'username' => MAIL_USERNAME,
    'encryption' => MAIL_ENCRYPTION,
    'from_address' => MAIL_FROM_ADDRESS,
    'from_name' => MAIL_FROM_NAME
];

// Get log file path
$logFile = ini_get('error_log');
if (file_exists($logFile) && is_readable($logFile)) {
    // Get the last 100 lines from the log file
    $logLines = [];
    $handle = fopen($logFile, 'r');
    if ($handle) {
        $lineCount = 0;
        $position = -1;
        $buffer = '';
        
        while ($lineCount < 200 && fseek($handle, $position, SEEK_END) >= 0) {
            $buffer = fgetc($handle) . $buffer;
            if ($buffer[0] == "\n") {
                $lineCount++;
                $buffer = '';
            }
            $position--;
        }
        
        // Reset file pointer
        fseek($handle, 0, SEEK_SET);
        
        // Read the entire file
        $allLines = file($logFile);
        
        // Get the last 200 lines
        $logLines = array_slice($allLines, -200);
        
        // Filter for email-related logs
        $filteredLines = [];
        foreach ($logLines as $line) {
            if (strpos($line, 'email') !== false || 
                strpos($line, 'mail') !== false || 
                strpos($line, 'PHPMailer') !== false || 
                strpos($line, 'SMTP') !== false ||
                strpos($line, 'claim notification') !== false) {
                $filteredLines[] = $line;
            }
        }
        
        $logContent = implode('', $filteredLines);
        fclose($handle);
    }
} else {
    $logContent = "Error log file not found or not readable: $logFile";
}

// Process test email form
if (isset($_POST['send_test_email']) && !empty($_POST['test_email'])) {
    $testEmail = trim($_POST['test_email']);
    if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            $result = testEmailConfig($testEmail);
            $testResult = $result;
            if ($result['status'] === 'success') {
                $successMessage = $result['message'];
            } else {
                $errorMessage = $result['message'];
            }
        } catch (Exception $e) {
            $errorMessage = "Exception: " . $e->getMessage();
        }
    } else {
        $errorMessage = 'Invalid email address.';
    }
}

// Process test claim notification form
if (isset($_POST['send_test_claim_notification'])) {
    try {
        $conn = getDbConnection();
        
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
                SELECT ci.*, cc.name as category_name
                FROM claim_items ci
                LEFT JOIN claim_categories cc ON ci.category_id = cc.id
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
}

// Check PHPMailer installation
$phpmailerInstalled = class_exists('PHPMailer\\PHPMailer\\PHPMailer');

// Check SMTP connection
$smtpStatus = "Unknown";
if ($phpmailerInstalled) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Host = MAIL_HOST;
        $mail->Port = MAIL_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
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

?>

<div class="page-title">
    <h1>Email Debugging</h1>
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

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Email Configuration</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th>SMTP Host</th>
                                <td><?php echo htmlspecialchars($emailConfig['host']); ?></td>
                            </tr>
                            <tr>
                                <th>SMTP Port</th>
                                <td><?php echo htmlspecialchars($emailConfig['port']); ?></td>
                            </tr>
                            <tr>
                                <th>SMTP Username</th>
                                <td><?php echo htmlspecialchars($emailConfig['username']); ?></td>
                            </tr>
                            <tr>
                                <th>SMTP Encryption</th>
                                <td><?php echo htmlspecialchars($emailConfig['encryption']); ?></td>
                            </tr>
                            <tr>
                                <th>From Address</th>
                                <td><?php echo htmlspecialchars($emailConfig['from_address']); ?></td>
                            </tr>
                            <tr>
                                <th>From Name</th>
                                <td><?php echo htmlspecialchars($emailConfig['from_name']); ?></td>
                            </tr>
                            <tr>
                                <th>PHPMailer Installed</th>
                                <td><?php echo $phpmailerInstalled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'; ?></td>
                            </tr>
                            <tr>
                                <th>SMTP Connection</th>
                                <td><?php echo $smtpStatus; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <a href="settings.php?tab=email" class="btn btn-primary">Edit Email Settings</a>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Test Email</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="email_debug.php">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="test_email" name="test_email" required>
                    </div>
                    <button type="submit" name="send_test_email" class="btn btn-primary">Send Test Email</button>
                </form>
                
                <?php if ($testResult): ?>
                <div class="mt-3">
                    <h6>Test Result:</h6>
                    <div class="alert alert-<?php echo $testResult['status'] === 'success' ? 'success' : 'danger'; ?>">
                        <?php echo $testResult['message']; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Test Claim Notification</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="email_debug.php">
                    <div class="mb-3">
                        <label for="test_claim_email" class="form-label">Test Recipient Email (Optional)</label>
                        <input type="email" class="form-control" id="test_claim_email" name="test_email">
                        <div class="form-text">If provided, a notification will be sent to this email instead of the configured recipients.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notify_customer" name="notify_customer" checked>
                            <label class="form-check-label" for="notify_customer">
                                Send to customer
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notify_staff" name="notify_staff" checked>
                            <label class="form-check-label" for="notify_staff">
                                Send to staff creator
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" name="send_test_claim_notification" class="btn btn-primary">Send Test Claim Notification</button>
                </form>
                
                <?php if ($claimData): ?>
                <div class="mt-3">
                    <h6>Claim Used for Testing:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <tbody>
                                <tr>
                                    <th>Claim ID</th>
                                    <td><?php echo $claimData['id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Claim Number</th>
                                    <td><?php echo $claimData['claim_number']; ?></td>
                                </tr>
                                <tr>
                                    <th>Order ID</th>
                                    <td><?php echo $claimData['order_id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Customer</th>
                                    <td><?php echo $claimData['customer_name']; ?> (<?php echo $claimData['customer_email']; ?>)</td>
                                </tr>
                                <tr>
                                    <th>Created By</th>
                                    <td><?php echo $claimData['created_by_name']; ?> (<?php echo $claimData['created_by_email']; ?>)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Email-Related Log Entries</h5>
                <a href="email_debug.php" class="btn btn-sm btn-outline-secondary">Refresh</a>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    Showing the most recent email-related log entries. Check the PHP error log for more details.
                </div>
                <pre class="log-container bg-dark text-light p-3" style="max-height: 600px; overflow-y: auto;"><?php echo htmlspecialchars($logContent); ?></pre>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Troubleshooting Steps</h5>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li class="mb-2">
                        <strong>Check SMTP Configuration:</strong> Verify that your SMTP host, port, username, and password are correct.
                    </li>
                    <li class="mb-2">
                        <strong>Test Email Connection:</strong> Use the "Send Test Email" function to check if basic email sending works.
                    </li>
                    <li class="mb-2">
                        <strong>Check Notification Settings:</strong> Make sure you have configured notification recipients in the Settings page.
                    </li>
                    <li class="mb-2">
                        <strong>Verify PHPMailer Installation:</strong> Ensure that PHPMailer is properly installed via Composer.
                    </li>
                    <li class="mb-2">
                        <strong>Check Firewall/Network:</strong> Some networks block outgoing SMTP connections on certain ports.
                    </li>
                    <li class="mb-2">
                        <strong>Review Error Logs:</strong> Check the logs above for specific error messages.
                    </li>
                    <li class="mb-2">
                        <strong>Try Different SMTP Provider:</strong> If your current provider is blocking you, try a different one.
                    </li>
                    <li class="mb-2">
                        <strong>Check Email Templates:</strong> Ensure your email templates are valid and don't contain syntax errors.
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>

<style>
.log-container {
    font-family: monospace;
    font-size: 12px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-all;
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>
