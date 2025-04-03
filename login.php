<?php
/**
 * Login Page
 * 
 * This file handles the login process with OTP verification for the Warranty Management System.
 */

// Include required files
require_once 'config/config.php';
require_once 'includes/auth_helper.php';

// Initialize variables
$email = '';
$userId = '';
$otp = '';
$step = 'email'; // Default step is email input

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect based on user role
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: cs_agent/dashboard.php');
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Warranty Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #3b82f6;
            --background-color: #f8fafc;
            --card-color: #ffffff;
            --text-color: #1e293b;
            --light-text-color: #64748b;
        }
        
        body {
            background-color: var(--background-color);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
            background-image: url('assets/images/pattern.svg');
            background-size: cover;
        }
        
        .login-container {
            display: flex;
            max-width: 900px;
            width: 100%;
            background-color: var(--card-color);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .login-sidebar {
            background-color: var(--primary-color);
            color: white;
            padding: 40px;
            width: 40%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-sidebar h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .login-sidebar p {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .login-form-container {
            padding: 40px;
            width: 60%;
        }
        
        .login-header {
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            font-size: 24px;
            color: var(--text-color);
            font-weight: 600;
        }
        
        .login-header p {
            color: var(--light-text-color);
            margin-top: 5px;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 12px 20px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #e2e8f0;
            border: none;
            color: var(--text-color);
            padding: 12px 20px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background-color: #cbd5e1;
        }
        
        .otp-input-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        
        .otp-input {
            width: 100%;
            text-align: center;
            font-size: 24px;
            letter-spacing: 10px;
            font-weight: 600;
        }
        
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .back-btn {
            display: flex;
            align-items: center;
            color: var(--light-text-color);
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .back-btn i {
            margin-right: 8px;
        }
        
        .back-btn:hover {
            color: var(--primary-color);
        }
        
        .form-text {
            color: var(--light-text-color);
            font-size: 14px;
        }
        
        /* Loader styles */
        .loader-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loader {
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        .loader-text {
            color: white;
            margin-top: 15px;
            font-weight: 500;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Form steps */
        .form-step {
            display: none;
        }
        
        .form-step.active {
            display: block;
        }
        
        /* Timer styles */
        .timer-container {
            text-align: center;
            margin-top: 10px;
            font-size: 14px;
            color: var(--light-text-color);
        }
        
        .timer {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 400px;
            }
            
            .login-sidebar, .login-form-container {
                width: 100%;
            }
            
            .login-sidebar {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Loader -->
    <div class="loader-container" id="loader">
        <div class="d-flex flex-column align-items-center">
            <div class="loader"></div>
            <div class="loader-text" id="loader-text">Processing...</div>
        </div>
    </div>
    
    <div class="login-container">
        <div class="login-sidebar">
            <h1>Warranty Management System</h1>
            <p>Streamline your warranty claims process with our comprehensive management solution. Validate customer purchases, manage warranty claims, and track claim resolutions efficiently.</p>
            <div class="mt-4">
                <i class="fas fa-shield-alt fa-2x me-2"></i>
                <i class="fas fa-clipboard-check fa-2x me-2"></i>
                <i class="fas fa-chart-line fa-2x"></i>
            </div>
        </div>
        
        <div class="login-form-container">
            <!-- Email Form Step -->
            <div class="form-step active" id="email-step">
                <div class="login-header">
                    <h2>Welcome Back</h2>
                    <p>Please enter your email to receive an OTP for secure login</p>
                </div>
                
                <div class="alert alert-danger" id="email-error" style="display: none;"></div>
                
                <form id="email-form" class="login-form">
                    <div class="mb-4">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required autofocus>
                        </div>
                        <div class="form-text mt-2">We'll send a one-time password to this email</div>
                    </div>
                    <div class="mb-4">
                        <button type="submit" id="send-otp-btn" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane me-2"></i>Send OTP
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- OTP Form Step -->
            <div class="form-step" id="otp-step">
                <div class="login-header">
                    <a href="#" class="back-btn" id="back-to-email">
                        <i class="fas fa-arrow-left"></i> Back to Email
                    </a>
                    <h2>Verify Your Identity</h2>
                    <p>Enter the 6-digit code sent to <span id="user-email" class="fw-bold"></span></p>
                </div>
                
                <div class="alert alert-danger" id="otp-error" style="display: none;"></div>
                <div class="alert alert-success" id="otp-success" style="display: none;"></div>
                
                <form id="otp-form" class="login-form">
                    <input type="hidden" id="user-id" name="user_id">
                    
                    <div class="mb-4">
                        <label for="otp" class="form-label text-center w-100">One-Time Password</label>
                        <div class="otp-input-container">
                            <input type="text" class="form-control otp-input" id="otp" name="otp" maxlength="6" required autocomplete="off">
                        </div>
                        <div class="form-text text-center mt-2">The OTP will expire in 10 minutes</div>
                        
                        <div class="timer-container">
                            <span>Resend OTP in <span class="timer" id="timer">00:30</span></span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <button type="submit" id="verify-otp-btn" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Verify & Login
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <button type="button" id="resend-otp-btn" class="btn btn-link p-0" disabled>
                            Didn't receive the code? Resend OTP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Variables
            let timerInterval;
            let secondsLeft = 30;
            
            // Show loader
            function showLoader(message = 'Processing...') {
                $('#loader-text').text(message);
                $('#loader').css('display', 'flex');
            }
            
            // Hide loader
            function hideLoader() {
                $('#loader').css('display', 'none');
            }
            
            // Switch to step
            function switchToStep(stepId) {
                $('.form-step').removeClass('active');
                $(stepId).addClass('active');
            }
            
            // Start timer for resend OTP
            function startResendTimer() {
                // Reset timer
                secondsLeft = 30;
                updateTimerDisplay();
                
                // Disable resend button
                $('#resend-otp-btn').prop('disabled', true);
                
                // Clear existing interval if any
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
                
                // Start new interval
                timerInterval = setInterval(function() {
                    secondsLeft--;
                    updateTimerDisplay();
                    
                    if (secondsLeft <= 0) {
                        clearInterval(timerInterval);
                        $('#resend-otp-btn').prop('disabled', false);
                    }
                }, 1000);
            }
            
            // Update timer display
            function updateTimerDisplay() {
                const minutes = Math.floor(secondsLeft / 60);
                const seconds = secondsLeft % 60;
                $('#timer').text(
                    (minutes < 10 ? '0' + minutes : minutes) + ':' + 
                    (seconds < 10 ? '0' + seconds : seconds)
                );
            }
            
            // Email form submission
            $('#email-form').on('submit', function(e) {
                e.preventDefault();
                
                const email = $('#email').val().trim();
                
                // Validate email
                if (!email) {
                    $('#email-error').text('Please enter your email address.').show();
                    return;
                }
                
                // Hide any previous errors
                $('#email-error').hide();
                
                // Show loader
                showLoader('Sending OTP...');
                
                // Send AJAX request
                $.ajax({
                    url: 'ajax/login_handler.php',
                    type: 'POST',
                    data: {
                        action: 'send_otp',
                        email: email
                    },
                    dataType: 'json',
                    success: function(response) {
                        // Hide loader
                        hideLoader();
                        
                        if (response.status === 'success') {
                            // Set user email and ID
                            $('#user-email').text(response.email);
                            $('#user-id').val(response.user_id);
                            
                            // Switch to OTP step
                            switchToStep('#otp-step');
                            
                            // Focus on OTP input
                            $('#otp').focus();
                            
                            // Start resend timer
                            startResendTimer();
                            
                            // Show success message
                            $('#otp-success').text(response.message).show();
                            
                            // Hide success message after 5 seconds
                            setTimeout(function() {
                                $('#otp-success').hide();
                            }, 5000);
                        } else {
                            // Show error message
                            $('#email-error').text(response.message).show();
                        }
                    },
                    error: function() {
                        // Hide loader
                        hideLoader();
                        
                        // Show error message
                        $('#email-error').text('An error occurred. Please try again.').show();
                    }
                });
            });
            
            // OTP form submission
            $('#otp-form').on('submit', function(e) {
                e.preventDefault();
                
                const userId = $('#user-id').val();
                const otp = $('#otp').val().trim();
                
                // Validate OTP
                if (!otp) {
                    $('#otp-error').text('Please enter the OTP sent to your email.').show();
                    return;
                }
                
                // Hide any previous errors
                $('#otp-error').hide();
                
                // Show loader
                showLoader('Verifying OTP...');
                
                // Send AJAX request
                $.ajax({
                    url: 'ajax/login_handler.php',
                    type: 'POST',
                    data: {
                        action: 'verify_otp',
                        user_id: userId,
                        otp: otp
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Show success message
                            $('#otp-success').text('Login successful. Redirecting...').show();
                            
                            // Redirect after a short delay
                            setTimeout(function() {
                                window.location.href = response.redirect;
                            }, 1000);
                        } else {
                            // Hide loader
                            hideLoader();
                            
                            // Show error message
                            $('#otp-error').text(response.message).show();
                        }
                    },
                    error: function() {
                        // Hide loader
                        hideLoader();
                        
                        // Show error message
                        $('#otp-error').text('An error occurred. Please try again.').show();
                    }
                });
            });
            
            // Back to email button
            $('#back-to-email').on('click', function(e) {
                e.preventDefault();
                
                // Clear OTP input and errors
                $('#otp').val('');
                $('#otp-error, #otp-success').hide();
                
                // Stop timer
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
                
                // Switch to email step
                switchToStep('#email-step');
            });
            
            // Resend OTP button
            $('#resend-otp-btn').on('click', function() {
                const email = $('#user-email').text();
                
                // Show loader
                showLoader('Resending OTP...');
                
                // Send AJAX request
                $.ajax({
                    url: 'ajax/login_handler.php',
                    type: 'POST',
                    data: {
                        action: 'resend_otp',
                        email: email
                    },
                    dataType: 'json',
                    success: function(response) {
                        // Hide loader
                        hideLoader();
                        
                        if (response.status === 'success') {
                            // Update user ID if needed
                            $('#user-id').val(response.user_id);
                            
                            // Start resend timer
                            startResendTimer();
                            
                            // Show success message
                            $('#otp-success').text('OTP has been resent to your email.').show();
                            
                            // Hide success message after 5 seconds
                            setTimeout(function() {
                                $('#otp-success').hide();
                            }, 5000);
                        } else {
                            // Show error message
                            $('#otp-error').text(response.message).show();
                        }
                    },
                    error: function() {
                        // Hide loader
                        hideLoader();
                        
                        // Show error message
                        $('#otp-error').text('An error occurred. Please try again.').show();
                    }
                });
            });
            
            // Format OTP input
            $('#otp').on('input', function() {
                // Allow only numbers
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });
    </script>
</body>
</html>
