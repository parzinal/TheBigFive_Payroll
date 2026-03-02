<?php
require_once 'config/database.php';
require_once 'config/smtp.php';
require_once 'config/csrf.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'staff':
            header('Location: staff/dashboard_staff.php');
            break;
        case 'user':
            header('Location: user/dashboard_user.php');
            break;
    }
    exit();
}

$error = '';
$success = '';
$step = $_GET['step'] ?? 'email'; // email, otp, reset

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
    
    // Step 1: Email submission
    if (isset($_POST['submit_email'])) {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate OTP
                    $otp = generateOTP(6);
                    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    // Store OTP in session
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_otp'] = $otp;
                    $_SESSION['reset_otp_expiry'] = $otp_expiry;
                    $_SESSION['reset_user_id'] = $user['id'];
                    
                    // Send OTP email
                    if (sendOTPEmail($user['email'], $user['full_name'], $otp)) {
                        header('Location: forgotpass.php?step=otp');
                        exit();
                    } else {
                        $error = 'Failed to send OTP email. Please try again.';
                    }
                } else {
                    $error = 'No active account found with this email address';
                }
            } catch (PDOException $e) {
                error_log("Forgot Password Error: " . $e->getMessage());
                $error = 'An error occurred. Please try again later.';
            }
        }
    }
    
    // Step 2: OTP verification
    elseif (isset($_POST['submit_otp'])) {
        $entered_otp = trim($_POST['otp'] ?? '');
        
        if (empty($entered_otp)) {
            $error = 'Please enter the OTP code';
        } elseif (!isset($_SESSION['reset_otp']) || !isset($_SESSION['reset_otp_expiry'])) {
            $error = 'Session expired. Please start over.';
            $step = 'email';
        } elseif (strtotime($_SESSION['reset_otp_expiry']) < time()) {
            $error = 'OTP has expired. Please request a new one.';
            unset($_SESSION['reset_otp'], $_SESSION['reset_otp_expiry']);
            $step = 'email';
        } elseif ($entered_otp !== $_SESSION['reset_otp']) {
            $error = 'Invalid OTP code. Please try again.';
        } else {
            // OTP verified successfully
            $_SESSION['otp_verified'] = true;
            header('Location: forgotpass.php?step=reset');
            exit();
        }
    }
    
    // Step 3: Password reset
    elseif (isset($_POST['submit_reset'])) {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
            $error = 'Unauthorized access. Please verify OTP first.';
            $step = 'email';
        } elseif (empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all fields';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            try {
                $pdo = getDBConnection();
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$hashed_password, $_SESSION['reset_user_id']])) {
                    // Get user info for email
                    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['reset_user_id']]);
                    $user = $stmt->fetch();
                    
                    // Send success email
                    sendPasswordResetSuccessEmail($user['email'], $user['full_name']);
                    
                    // Clear session
                    unset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_otp_expiry'], $_SESSION['reset_user_id'], $_SESSION['otp_verified']);
                    
                    header('Location: login.php?reset=success');
                    exit();
                } else {
                    $error = 'Failed to reset password. Please try again.';
                }
            } catch (PDOException $e) {
                error_log("Password Reset Error: " . $e->getMessage());
                $error = 'An error occurred. Please try again later.';
            }
        }
    }
    } // end CSRF else
} // end POST

// Handle resend OTP (GET request — outside POST block)
if (isset($_GET['resend']) && $_GET['resend'] == '1' && isset($_SESSION['reset_email'])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$_SESSION['reset_email']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $otp = generateOTP(6);
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_otp_expiry'] = $otp_expiry;
            
            if (sendOTPEmail($user['email'], $user['full_name'], $otp)) {
                $success = 'New OTP has been sent to your email address';
            } else {
                $error = 'Failed to send OTP email. Please try again later.';
            }
        } else {
            $error = 'Session expired. Please start over.';
        }
    } catch (PDOException $e) {
        error_log("Resend OTP Error: " . $e->getMessage());
        $error = 'An error occurred while resending OTP. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - TheBigFive Payroll</title>
    <!-- Professional Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .otp-input {
            text-align: center;
            letter-spacing: 10px;
            font-size: 24px;
            font-weight: bold;
        }
        .resend-link {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
        }
        .resend-link a {
            color: #00a8e8;
            text-decoration: none;
        }
        .resend-link a:hover {
            text-decoration: underline;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        .step-indicator .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            color: #999;
        }
        .step-indicator .step.active {
            background: #00a8e8;
            color: white;
        }
        .step-indicator .step.completed {
            background: #27ae60;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Side - Image Section -->
        <div class="left-section">
            <div class="overlay">
                <div class="content">
                    <h1 class="logo">The Big Five Payroll</h1>
                    <p class="tagline">Trust is the only platform that provides you super<br>Payroll services and we are here</p>
                </div>
                <div class="bottom-profile">
                </div>
                <button class="fullscreen-toggle" onclick="toggleFullscreen()" title="Toggle Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
        </div>

        <!-- Right Side - Forgot Password Form -->
        <div class="right-section">
            <div class="decorative-circle-top"></div>
            <div class="decorative-circle-bottom"></div>
            <div class="login-box">
                <div class="logo-container">
                    <img src="assets/images/blue.png" alt="Blue Logo">
                    <img src="assets/images/red.png" alt="Red Logo">
                </div>
                
                <div class="welcome-header">
                    <h2>Reset Password</h2>
                </div>

                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step == 'email' ? 'active' : ($step == 'otp' || $step == 'reset' ? 'completed' : ''); ?>">1</div>
                    <div class="step <?php echo $step == 'otp' ? 'active' : ($step == 'reset' ? 'completed' : ''); ?>">2</div>
                    <div class="step <?php echo $step == 'reset' ? 'active' : ''; ?>">3</div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($step == 'email'): ?>
                    <!-- Step 1: Email Input -->
                    <p style="text-align: center; color: #666; margin-bottom: 20px; font-size: 14px;">
                        Enter your email address and we'll send you an OTP to reset your password.
                    </p>
                    <form method="POST" action="" class="login-form">
                        <?php echo csrfTokenField(); ?>
                        <div class="form-group">
                            <i class="fas fa-envelope"></i>
                            <input 
                                type="email" 
                                name="email" 
                                placeholder="Enter your email address" 
                                required
                                autofocus
                            >
                        </div>
                        <button type="submit" name="submit_email" class="btn-login">SEND OTP</button>
                    </form>

                <?php elseif ($step == 'otp'): ?>
                    <!-- Step 2: OTP Verification -->
                    <p style="text-align: center; color: #666; margin-bottom: 20px; font-size: 14px;">
                        We've sent a 6-digit OTP to <strong><?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></strong>
                    </p>
                    <form method="POST" action="" class="login-form">
                        <?php echo csrfTokenField(); ?>
                        <div class="form-group">
                            <i class="fas fa-key"></i>
                            <input 
                                type="text" 
                                name="otp" 
                                placeholder="Enter 6-digit OTP" 
                                required
                                maxlength="6"
                                pattern="[0-9]{6}"
                                class="otp-input"
                                autofocus
                            >
                        </div>
                        <button type="submit" name="submit_otp" class="btn-login">VERIFY OTP</button>
                    </form>
                    <div class="resend-link">
                        Didn't receive the code? <a href="forgotpass.php?step=otp&resend=1">Resend OTP</a>
                    </div>

                <?php elseif ($step == 'reset'): ?>
                    <!-- Step 3: New Password -->
                    <p style="text-align: center; color: #666; margin-bottom: 20px; font-size: 14px;">
                        Enter your new password below.
                    </p>
                    <form method="POST" action="" class="login-form">
                        <?php echo csrfTokenField(); ?>
                        <div class="form-group">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                name="new_password" 
                                id="new_password"
                                placeholder="New Password (min. 6 characters)" 
                                required
                                autofocus
                            >
                            <i class="fas fa-eye toggle-password" id="toggleNewPassword"></i>
                        </div>

                        <div class="form-group">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                id="confirm_password"
                                placeholder="Confirm New Password" 
                                required
                            >
                            <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                        </div>

                        <button type="submit" name="submit_reset" class="btn-login">RESET PASSWORD</button>
                    </form>
                <?php endif; ?>

                <div class="register-link">
                    Remember your password? <a href="login.php">Login Now</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const toggleNewPassword = document.getElementById('toggleNewPassword');
        const newPassword = document.getElementById('new_password');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPassword = document.getElementById('confirm_password');

        if (toggleNewPassword) {
            toggleNewPassword.addEventListener('click', function() {
                const type = newPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                newPassword.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        if (toggleConfirmPassword) {
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassword.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        // Password match validation
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });

            newPassword.addEventListener('input', function() {
                if (newPassword.value !== confirmPassword.value && confirmPassword.value !== '') {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });
        }

        // Add animation on load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.login-box').style.opacity = '0';
            document.querySelector('.login-box').style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                document.querySelector('.login-box').style.transition = 'all 0.5s ease';
                document.querySelector('.login-box').style.opacity = '1';
                document.querySelector('.login-box').style.transform = 'translateY(0)';
            }, 100);
        });
        
        // Toggle fullscreen function
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log('Error attempting to enable fullscreen:', err);
                });
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

        // Auto-submit OTP when 6 digits are entered
        const otpInput = document.querySelector('.otp-input');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 6) {
                    // Optional: auto-submit form
                    // this.form.submit();
                }
            });
        }
    </script>
</body>
</html>
