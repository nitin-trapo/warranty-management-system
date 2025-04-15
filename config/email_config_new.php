<?php
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
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'nitinpatoliya.my@trapo.com');
define('MAIL_PASSWORD', 'pbui hitr hmuu odni');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', 'it-support@trapo.com');
define('MAIL_FROM_NAME', 'TRAPO');

// Email templates directory
define('EMAIL_TEMPLATES_DIR', ROOT_PATH . '/templates/emails');

/**
 * Get email configuration from database
 */
function getEmailConfig() {
    $config = [
        'host' => MAIL_HOST,
        'port' => MAIL_PORT,
        'username' => MAIL_USERNAME,
        'password' => MAIL_PASSWORD,
        'encryption' => MAIL_ENCRYPTION,
        'from_address' => MAIL_FROM_ADDRESS,
        'from_name' => MAIL_FROM_NAME
    ];
    
    // Override with database settings if available
    try {
        $conn = getDbConnection();
        
        // Get settings from database
        $settings = [
            'smtp_host' => 'host',
            'smtp_port' => 'port',
            'smtp_username' => 'username',
            'smtp_password' => 'password',
            'smtp_encryption' => 'encryption',
            'company_email' => 'from_address',
            'company_name' => 'from_name'
        ];
        
        foreach ($settings as $key => $configKey) {
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :key");
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['setting_value'])) {
                    $config[$configKey] = $row['setting_value'];
                }
            }
        }
    } catch (PDOException $e) {
        // Log error but continue with default settings
        error_log("Error getting email config from database: " . $e->getMessage());
    }
    
    return $config;
} // End of getEmailConfig function

/**
 * Configure PHPMailer instance with settings
 */
function configureMailer($mail) {
    // Get configuration
    $config = getEmailConfig();
    
    // Server settings
    $mail->SMTPDebug = 0;                      // Enable verbose debug output
    $mail->isSMTP();                           // Send using SMTP
    $mail->Host       = $config['host'];       // SMTP server
    $mail->SMTPAuth   = true;                  // Enable SMTP authentication
    $mail->Username   = $config['username'];   // SMTP username
    $mail->Password   = $config['password'];   // SMTP password
    $mail->SMTPSecure = $config['encryption']; // Enable TLS encryption
    $mail->Port       = $config['port'];       // TCP port to connect to
    
    // Set sender
    $mail->setFrom($config['from_address'], $config['from_name']);
    
    return $mail;
} // End of configureMailer function

/**
 * Update email configuration in database
 */
function updateEmailConfig($key, $value) {
    try {
        $conn = getDbConnection();
        
        // Check if setting exists
        $stmt = $conn->prepare("SELECT id FROM system_settings WHERE setting_key = :key");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update existing setting
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = :value WHERE setting_key = :key");
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            return $stmt->execute();
        } else {
            // Insert new setting
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (:key, :value, :description)");
            $description = 'SMTP ' . ucfirst($key) . ' setting';
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':description', $description);
            return $stmt->execute();
        }
    } catch (PDOException $e) {
        error_log("Error updating email config: " . $e->getMessage());
        return false;
    }
} // End of updateEmailConfig function

/**
 * Test email configuration by sending a test email
 */
function testEmailConfig($to) {
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Configure mailer
        $mail = configureMailer($mail);
        
        // Recipients
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Configuration Test';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <h2 style="color: #2563eb;">Email Configuration Test</h2>
                <p>This is a test email to verify that your email configuration is working correctly.</p>
                <p>If you received this email, your email configuration is working properly.</p>
                <p>Time sent: ' . date('Y-m-d H:i:s') . '</p>
                <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                <p style="font-size: 12px; color: #666;">This is an automated message from the Warranty Management System.</p>
            </div>
        ';
        $mail->AltBody = 'This is a test email to verify that your email configuration is working correctly.';
        
        // Send email
        $mail->send();
        
        return [
            'status' => 'success',
            'message' => 'Test email sent successfully to ' . $to
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Failed to send test email: ' . $mail->ErrorInfo
        ];
    }
} // End of testEmailConfig function
