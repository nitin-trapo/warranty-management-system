<?php
/**
 * OTP Email Template
 * 
 * Available variables:
 * $otp - The OTP code
 * $purpose - Purpose of the OTP (login or verification)
 * $companyName - Company name from settings
 * $expiryMinutes - Minutes until OTP expires (default: 10)
 */

// Set default values if not provided
$expiryMinutes = $expiryMinutes ?? 10;
$subject = ($purpose == 'login') 
    ? "$companyName - Your Login OTP Code" 
    : "$companyName - Email Verification Code";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subject); ?></title>
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
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
           
            <p>Hello,</p>
            
            <?php if ($purpose == 'login'): ?>
                <p>Your one-time password (OTP) for login is:</p>
            <?php else: ?>
                <p>Your email verification code is:</p>
            <?php endif; ?>
            
            <div class="otp-code"><?php echo htmlspecialchars($otp); ?></div>
            
            <p>This code will expire in <?php echo $expiryMinutes; ?> minutes.</p>
            
            <p>If you didn't request this code, please ignore this email.</p>
            
            <p>Thank you,<br><?php echo htmlspecialchars($companyName); ?> Team</p>
        </div>
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
