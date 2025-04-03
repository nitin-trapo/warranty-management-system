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
        
        // Email template
        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>'.$subject.'</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                }
                .header {
                    background-color: #2563eb;
                    color: #fff;
                    padding: 20px;
                    text-align: center;
                    border-top-left-radius: 5px;
                    border-top-right-radius: 5px;
                    margin-bottom: 20px;
                }
                .content {
                    padding: 20px;
                }
                .otp-code {
                    font-size: 32px;
                    letter-spacing: 5px;
                    text-align: center;
                    padding: 15px;
                    background-color: #f5f5f5;
                    border-radius: 5px;
                    margin: 20px 0;
                    font-weight: bold;
                }
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 12px;
                    color: #777;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>'.htmlspecialchars($companyName).'</h2>
                </div>
                <div class="content">
                    <p>Hello,</p>';
        
        if ($purpose == 'login') {
            $message .= '<p>Your one-time password (OTP) for login is:</p>';
        } else {
            $message .= '<p>Your email verification code is:</p>';
        }
        
        $message .= '
                    <div class="otp-code">'.htmlspecialchars($otp).'</div>
                    <p>This code will expire in 10 minutes.</p>
                    <p>If you didn\'t request this code, please ignore this email.</p>
                    <p>Thank you,<br>'.htmlspecialchars($companyName).' Team</p>
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; '.date('Y').' '.htmlspecialchars($companyName).'. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->Body = $message;
        $mail->AltBody = "Your OTP code is: $otp. This code will expire in 10 minutes.";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
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
        error_log("Error saving OTP: " . $e->getMessage());
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
        error_log("Error verifying OTP: " . $e->getMessage());
        return false;
    }
}
