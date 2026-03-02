<?php
/**
 * SMTP Email Configuration for TheBigFive Payroll System
 * Using PHPMailer for sending emails
 *
 * Credentials are loaded from the .env file via bootstrap.php.
 * Do NOT hardcode sensitive values here.
 */

// Load bootstrap (environment variables, session, headers)
require_once __DIR__ . '/bootstrap.php';

// Include PHPMailer
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// SMTP Settings from environment
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) env('SMTP_PORT', 587));
define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
define('SMTP_SECURE', env('SMTP_ENCRYPTION', 'tls'));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_ADDRESS', ''));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'TheBigFive Payroll System'));

/**
 * Send OTP Email
 * @param string $to_email Recipient email
 * @param string $to_name Recipient name
 * @param string $otp OTP code
 * @return bool Success status
 */
function sendOTPEmail($to_email, $to_name, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Password Reset OTP - TheBigFive Payroll";
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #00a8e8, #0091cc); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                .otp-box { background: white; border: 2px solid #00a8e8; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #00a8e8; letter-spacing: 5px; }
                .footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
                .warning { color: #e74c3c; font-size: 14px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>TheBigFive Payroll System</h1>
                </div>
                <div class='content'>
                    <h2>Password Reset Request</h2>
                    <p>Hello " . htmlspecialchars($to_name) . ",</p>
                    <p>We received a request to reset your password. Use the OTP code below to complete the password reset process:</p>
                    
                    <div class='otp-box'>
                        <p style='margin: 0; font-size: 14px; color: #666;'>Your OTP Code:</p>
                        <p class='otp-code'>" . htmlspecialchars($otp) . "</p>
                    </div>
                    
                    <p><strong>This OTP will expire in 10 minutes.</strong></p>
                    <p>If you didn't request a password reset, please ignore this email or contact support if you have concerns.</p>
                    
                    <p class='warning'>⚠️ Never share this OTP with anyone. TheBigFive staff will never ask for your OTP.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " TheBigFive Payroll System. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send Password Reset Success Email
 * @param string $to_email Recipient email
 * @param string $to_name Recipient name
 * @return bool Success status
 */
function sendPasswordResetSuccessEmail($to_email, $to_name) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Password Reset Successful - TheBigFive Payroll";
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #27ae60, #229954); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                .footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
                .success-icon { font-size: 48px; color: #27ae60; text-align: center; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Successful</h1>
                </div>
                <div class='content'>
                    <div class='success-icon'>✓</div>
                    <h2>Your password has been reset!</h2>
                    <p>Hello " . htmlspecialchars($to_name) . ",</p>
                    <p>This email confirms that your password has been successfully reset.</p>
                    <p>You can now log in to your account using your new password.</p>
                    <p>If you did not make this change, please contact our support team immediately.</p>
                    <p style='margin-top: 30px;'><a href='" . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . "/login.php' style='background: #00a8e8; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block;'>Login Now</a></p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " TheBigFive Payroll System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate random OTP
 * @param int $length OTP length
 * @return string OTP code
 */
function generateOTP($length = 6) {
    $digits = '0123456789';
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= $digits[rand(0, strlen($digits) - 1)];
    }
    return $otp;
}
