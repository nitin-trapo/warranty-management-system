<?php
/**
 * Email Helper Functions
 * 
 * This file contains functions for sending emails in the Warranty Management System.
 */

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Include email configuration
require_once __DIR__ . '/../config/email_config.php';

// Include system settings helper
require_once __DIR__ . '/system_settings_helper.php';

// Include template helper
require_once __DIR__ . '/template_helper.php';

// Define email log file path
define('EMAIL_LOG_FILE', ROOT_PATH . '/logs/email.log');

// Create logs directory if it doesn't exist
if (!file_exists(ROOT_PATH . '/logs')) {
    mkdir(ROOT_PATH . '/logs', 0755, true);
}

// Create email log file if it doesn't exist
if (!file_exists(EMAIL_LOG_FILE)) {
    file_put_contents(EMAIL_LOG_FILE, "Email Log File Created: " . date('Y-m-d H:i:s') . "\n");
    chmod(EMAIL_LOG_FILE, 0644);
}

/**
 * Log email-related messages to the email log file
 * 
 * @param string $message Message to log
 * @return void
 */
function logEmail($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents(EMAIL_LOG_FILE, $logMessage, FILE_APPEND);
    // Also log to PHP error log for backward compatibility
    error_log($message);
}

/**
 * Send an email with OTP code
 * 
 * @param string $to Recipient email address
 * @param string $otp OTP code
 * @param string $purpose Purpose of the OTP (login or verification)
 * @return bool True if email was sent successfully, false otherwise
 */
function sendOtpEmail($to, $otp, $purpose = 'login') {
    try {
        // Get company name from email config
        $companyName = MAIL_FROM_NAME;
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Configure mailer with system settings
        $mail = configureMailer($mail);
        
        // Set from address
        $mail->setFrom(getSystemSetting('company_email') ?: 'noreply@example.com', $companyName);
        
        // Recipients
        $mail->addAddress($to);
        
        // Set email subject based on purpose
        $subject = ($purpose == 'login') 
            ? "$companyName - Your Login OTP Code" 
            : "$companyName - Email Verification Code";
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // Set expiry minutes
        $expiryMinutes = 10;
        
        // Render email template
        $message = renderEmailTemplate('otp_email', [
            'otp' => $otp,
            'purpose' => $purpose,
            'companyName' => $companyName,
            'expiryMinutes' => $expiryMinutes
        ]);
        
        $mail->Body = $message;
        $mail->AltBody = "Your OTP code is: $otp. This code will expire in $expiryMinutes minutes.";
        
        return $mail->send();
    } catch (Exception $e) {
        logEmail("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Generate a random OTP code
 * 
 * @param int $length Length of the OTP code
 * @return string Generated OTP code
 */
function generateOtp($length = 6) {
    // Generate a random numeric OTP
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= mt_rand(0, 9);
    }
    return $otp;
}

/**
 * Save OTP code to database
 * 
 * @param int $userId User ID
 * @param string $otp OTP code
 * @param string $purpose Purpose of the OTP (login or verification)
 * @param int $expiryMinutes Minutes until OTP expires
 * @return bool True if OTP was saved successfully, false otherwise
 */
function saveOtp($userId, $otp, $purpose = 'login', $expiryMinutes = 10) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        // Calculate expiry time
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiryMinutes minutes"));
        
        // First, invalidate any existing OTPs for this user and purpose
        $stmt = $conn->prepare("UPDATE otp_codes SET is_used = 1 WHERE user_id = :user_id AND purpose = :purpose AND is_used = 0");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->execute();
        
        // Insert new OTP
        $stmt = $conn->prepare("INSERT INTO otp_codes (user_id, otp_code, purpose, expires_at) VALUES (:user_id, :otp_code, :purpose, :expires_at)");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':otp_code', $otp);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->bindParam(':expires_at', $expiresAt);
        
        return $stmt->execute();
    } catch(PDOException $e) {
        logEmail("Error saving OTP: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify OTP code
 * 
 * @param int $userId User ID
 * @param string $otp OTP code
 * @param string $purpose Purpose of the OTP (login or verification)
 * @return bool True if OTP is valid, false otherwise
 */
function verifyOtp($userId, $otp, $purpose = 'login') {
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        // Get current time
        $currentTime = date('Y-m-d H:i:s');
        
        // Check if OTP is valid
        $stmt = $conn->prepare("SELECT id FROM otp_codes WHERE user_id = :user_id AND otp_code = :otp_code AND purpose = :purpose AND expires_at > :current_time AND is_used = 0");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':otp_code', $otp);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->bindParam(':current_time', $currentTime);
        $stmt->execute();
        
        $result = $stmt->fetch();
        
        if ($result) {
            // Mark OTP as used
            $otpId = $result['id'];
            $updateStmt = $conn->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = :id");
            $updateStmt->bindParam(':id', $otpId);
            $updateStmt->execute();
            
            return true;
        }
        
        return false;
    } catch(PDOException $e) {
        logEmail("Error verifying OTP: " . $e->getMessage());
        return false;
    }
}

/**
 * Send claim notification email
 * 
 * @param array $claim Claim data
 * @param array $claimItems Claim items data
 * @param array $recipients Array of email addresses to notify
 * @param bool $notifyCreator Whether to notify the claim creator (customer)
 * @param bool $notifyStaffCreator Whether to notify the staff member who created the claim
 * @return bool True if email was sent successfully, false otherwise
 */
function sendClaimNotificationEmail($claim, $claimItems, $recipients = [], $notifyCreator = true, $notifyStaffCreator = true) {
    try {
        // Enhanced debug logging
        logEmail("==== STARTING EMAIL NOTIFICATION PROCESS ====");
        logEmail("Claim ID: " . ($claim['id'] ?? 'unknown'));
        logEmail("Claim Number: " . ($claim['claim_number'] ?? 'unknown'));
        logEmail("Category Approver: " . ($claim['category_approver'] ?? 'None'));
        logEmail("Recipients: " . json_encode($recipients));
        logEmail("Recipient Count: " . count($recipients));
        logEmail("Notify creator: " . ($notifyCreator ? 'Yes' : 'No'));
        logEmail("Notify staff creator: " . ($notifyStaffCreator ? 'Yes' : 'No'));
        
        // Log claim data for debugging
        logEmail("Claim Data: " . json_encode($claim));
        logEmail("Claim Items: " . json_encode($claimItems));
        
        // Get company name from email config
        $companyName = MAIL_FROM_NAME;
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Enable debug output
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            logEmail("PHPMailer [$level]: $str");
        };
        
        // Configure mailer with system settings
        $mail = configureMailer($mail);
        
        // Set from address
        $fromEmail = getSystemSetting('company_email') ?: MAIL_FROM_ADDRESS;
        $mail->setFrom($fromEmail, $companyName);
        logEmail("From address: $fromEmail");
        
        // Set email subject
        $subject = "{$companyName} - New Warranty Claim #{$claim['id']} ({$claim['claim_number']})";
        $mail->Subject = $subject;
        
        // Content
        $mail->isHTML(true);
        
        // Build admin URL for the claim
        $adminUrl = '';
        $baseUrl = getSystemSetting('site_url');
        if (!empty($baseUrl)) {
            $adminUrl = rtrim($baseUrl, '/') . '/admin/view_claim.php?id=' . $claim['id'];
        }
        
        // Common template variables
        $templateVars = [
            'claim' => $claim,
            'claimItems' => $claimItems,
            'companyName' => $companyName,
            'adminUrl' => $adminUrl
        ];
        
        $emailsSent = 0;
        $emailErrors = [];
        
        // Send to regular recipients
        if (!empty($recipients)) {
            logEmail("==== PROCESSING APPROVER RECIPIENTS ====");
            // Reset recipients
            $mail->clearAddresses();
            logEmail("Cleared previous email addresses");
            
            // Add recipients
            $validRecipientCount = 0;
            foreach ($recipients as $recipient) {
                logEmail("Processing recipient: $recipient");
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($recipient);
                    logEmail("Added valid recipient: $recipient");
                    $validRecipientCount++;
                } else {
                    logEmail("ERROR: Invalid recipient email format: $recipient");
                }
            }
            
            logEmail("Total valid recipients added: $validRecipientCount");
            
            if (count($mail->getToAddresses()) === 0) {
                logEmail("ERROR: No valid recipients for approver notification");
            } else {
                // Generate email content for admin/staff recipients
                $templateVars['isCustomer'] = false;
                $templateVars['isStaffCreator'] = false;
                
                try {
                    $message = renderEmailTemplate('claim_notification', $templateVars);
                    $mail->Body = $message;
                    
                    // Generate plain text version
                    $plainText = generateClaimPlainText($claim, $claimItems, false);
                    $mail->AltBody = $plainText;
                    
                    // Send email
                    if ($mail->send()) {
                        logEmail("Admin notification email sent successfully");
                        $emailsSent++;
                    } else {
                        $error = "Admin notification email failed: " . $mail->ErrorInfo;
                        logEmail($error);
                        $emailErrors[] = $error;
                    }
                } catch (Exception $e) {
                    $error = "Error rendering admin template: " . $e->getMessage();
                    logEmail($error);
                    $emailErrors[] = $error;
                }
            }
        } else {
            logEmail("No admin recipients configured");
        }
        
        // Send to customer if enabled
        if ($notifyCreator && !empty($claim['customer_email']) && filter_var($claim['customer_email'], FILTER_VALIDATE_EMAIL)) {
            // Reset recipients
            $mail->clearAddresses();
            $mail->addAddress($claim['customer_email'], $claim['customer_name']);
            logEmail("Added customer recipient: " . $claim['customer_email']);
            
            // Generate email content for customer
            $templateVars['isCustomer'] = true;
            $templateVars['isStaffCreator'] = false;
            
            try {
                $message = renderEmailTemplate('claim_notification', $templateVars);
                $mail->Body = $message;
                
                // Generate plain text version
                $plainText = generateClaimPlainText($claim, $claimItems, true);
                $mail->AltBody = $plainText;
                
                // Send email
                if ($mail->send()) {
                    logEmail("Customer notification email sent successfully");
                    $emailsSent++;
                } else {
                    $error = "Customer notification email failed: " . $mail->ErrorInfo;
                    logEmail($error);
                    $emailErrors[] = $error;
                }
            } catch (Exception $e) {
                $error = "Error rendering customer template: " . $e->getMessage();
                logEmail($error);
                $emailErrors[] = $error;
            }
        } else {
            if (!$notifyCreator) {
                logEmail("Customer notification disabled");
            } else if (empty($claim['customer_email'])) {
                logEmail("No customer email available");
            } else {
                logEmail("Invalid customer email: " . $claim['customer_email']);
            }
        }
        
        // Send to staff creator if enabled
        if ($notifyStaffCreator && !empty($claim['created_by_email']) && filter_var($claim['created_by_email'], FILTER_VALIDATE_EMAIL)) {
            // Check if staff email is already in recipients
            $alreadyNotified = false;
            foreach ($recipients as $recipient) {
                if (strtolower($recipient) === strtolower($claim['created_by_email'])) {
                    $alreadyNotified = true;
                    logEmail("Staff creator already notified as admin recipient");
                    break;
                }
            }
            
            if (!$alreadyNotified) {
                // Reset recipients
                $mail->clearAddresses();
                $mail->addAddress($claim['created_by_email'], $claim['created_by_name'] ?? '');
                logEmail("Added staff creator recipient: " . $claim['created_by_email']);
                
                // Generate email content for staff creator
                $templateVars['isCustomer'] = false;
                $templateVars['isStaffCreator'] = true;
                
                try {
                    $message = renderEmailTemplate('claim_notification', $templateVars);
                    $mail->Body = $message;
                    
                    // Generate plain text version
                    $plainText = generateClaimPlainText($claim, $claimItems, false, true);
                    $mail->AltBody = $plainText;
                    
                    // Send email
                    if ($mail->send()) {
                        logEmail("Staff creator notification email sent successfully");
                        $emailsSent++;
                    } else {
                        $error = "Staff creator notification email failed: " . $mail->ErrorInfo;
                        logEmail($error);
                        $emailErrors[] = $error;
                    }
                } catch (Exception $e) {
                    $error = "Error rendering staff creator template: " . $e->getMessage();
                    logEmail($error);
                    $emailErrors[] = $error;
                }
            }
        } else {
            if (!$notifyStaffCreator) {
                logEmail("Staff creator notification disabled");
            } else if (empty($claim['created_by_email'])) {
                logEmail("No staff creator email available");
            } else {
                logEmail("Invalid staff creator email: " . $claim['created_by_email']);
            }
        }
        
        // Log summary
        logEmail("Email sending complete. Emails sent: $emailsSent, Errors: " . count($emailErrors));
        if (!empty($emailErrors)) {
            logEmail("Email errors: " . json_encode($emailErrors));
        }
        
        return $emailsSent > 0;
    } catch (Exception $e) {
        logEmail("Failed to send claim notification email: {$e->getMessage()}");
        logEmail("Exception trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Generate plain text version of claim notification
 * 
 * @param array $claim Claim data
 * @param array $claimItems Claim items data
 * @param bool $isCustomer Whether the recipient is the customer
 * @param bool $isStaffCreator Whether the recipient is the staff creator
 * @return string Plain text email content
 */
function generateClaimPlainText($claim, $claimItems, $isCustomer = false, $isStaffCreator = false) {
    $text = '';
    
    if ($isCustomer) {
        $text .= "Your Warranty Claim Has Been Submitted\n\n";
        $text .= "Thank you for submitting your warranty claim. We have received your request and will begin processing it shortly.\n\n";
    } elseif ($isStaffCreator) {
        $text .= "Warranty Claim Created Successfully\n\n";
        $text .= "You have successfully created a new warranty claim with the following details:\n\n";
    } else {
        $text .= "New Warranty Claim Submitted\n\n";
        $text .= "A new warranty claim has been submitted with the following details:\n\n";
    }
    
    $text .= "Claim Number: {$claim['claim_number']}\n";
    $text .= "Order ID: {$claim['order_id']}\n";
    $text .= "Customer: {$claim['customer_name']} ({$claim['customer_email']})\n";
    if (!empty($claim['customer_phone'])) {
        $text .= "Phone: {$claim['customer_phone']}\n";
    }
    $text .= "Delivery Date: {$claim['delivery_date']}\n";
    $text .= "Status: " . ucfirst(str_replace('_', ' ', $claim['status'])) . "\n";
    $text .= "Submission Date: {$claim['created_at']}\n";
    
    if (!$isCustomer && !empty($claim['created_by_name'])) {
        $text .= "Created By: {$claim['created_by_name']}\n";
    }
    
    $text .= "\nClaim Items:\n";
    foreach ($claimItems as $item) {
        $text .= "- SKU: {$item['sku']}, Product: {$item['product_name']}, Category: {$item['category_name']}\n";
        $text .= "  Description: {$item['description']}\n\n";
    }
    
    if ($isCustomer) {
        $text .= "We will review your claim and get back to you as soon as possible. ";
        $text .= "Your claim reference number is {$claim['claim_number']}. Please keep this for your records.\n\n";
        $text .= "If you have any questions about your claim, please contact our customer service team.\n\n";
    } else {
        $text .= "You can view and manage this claim in the Warranty Management System.\n\n";
    }
    
    return $text;
}

/**
 * Get notification recipients from settings
 * 
 * @return array Array of email addresses
 */
function getClaimNotificationRecipients() {
    // Get from system settings
    $recipientsStr = getSystemSetting('claim_notification_emails');
    
    if (empty($recipientsStr)) {
        // Default to the system email if no recipients are configured
        return [MAIL_FROM_ADDRESS];
    }
    
    // Split by comma and trim whitespace
    $recipients = array_map('trim', explode(',', $recipientsStr));
    
    // Filter out invalid email addresses
    return array_filter($recipients, function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    });
}

/**
 * Check if creator notification is enabled
 * 
 * @return bool True if enabled, false otherwise
 */
function isCreatorNotificationEnabled() {
    return getSystemSetting('notify_claim_creator') === '1';
}

/**
 * Check if staff creator notification is enabled
 * 
 * @return bool True if enabled, false otherwise
 */
function isStaffCreatorNotificationEnabled() {
    return getSystemSetting('notify_staff_creator') === '1';
}
