<?php
require_once 'config/database.php';
require_once 'config/account_logs_helper.php';
require_once 'config/auth.php';
require_once 'config/notifications_helper.php';

// Handle session error messages
$sessionError = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'session_invalid':
            $sessionError = 'Your session is invalid. Please log in again.';
            break;
        case 'session_expired':
            $sessionError = 'Your session has expired. Please log in again.';
            break;
        case 'unauthorized':
            $sessionError = 'Unauthorized access. Please log in.';
            break;
    }
}

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    redirectToProperDashboard($_SESSION['role']);
    exit();
}

$error = $sessionError;
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $email_or_username = strtolower(trim($_POST['email_username'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    if (empty($email_or_username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if input is email or username
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1");
            $stmt->execute([$email_or_username, $email_or_username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Initialize secure session
                initializeSecureSession($user);
                
                // Update last login
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Log successful login
                logUserLogin($user['id'], $user['username'], $user['role'], $pdo);
                
                // Create login notification
                $loginMessage = "{$user['username']} ({$user['role']}) logged in";
                $loginTitle = "User Login";
                
                // Notify admins about the login
                notifyAdmins(
                    $loginTitle,
                    $loginMessage,
                    'info',
                    'fa-sign-in-alt',
                    ''
                );
                
                // Redirect based on role
                redirectToProperDashboard($user['role']);
                exit();
            } else {
                // Log failed login attempt
                logFailedLogin($email_or_username, 'Invalid credentials or account inactive');
                $error = 'Invalid credentials or account is inactive';
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
    } // end CSRF else
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TheBigFive Payroll</title>
    <!-- Professional Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Left Side - Image Section -->
        <div class="left-section">
            <div class="overlay">
                <div class="content">
                    <h1 class="logo">The Big Five Payroll</h1>
                </div>
                <div class="bottom-profile">
                </div>
                <button class="fullscreen-toggle" onclick="toggleFullscreen()" title="Toggle Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="right-section">
            <div class="decorative-circle-top"></div>
            <div class="decorative-circle-bottom"></div>
            <div class="login-box">
              
                
                <div class="welcome-header">
                    <h2>Welcome</h2>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Registration successful! Please login.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Password reset successful! Please login with your new password.
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="login-form">
                    <?php echo csrfTokenField(); ?>
                    <div class="form-group">
                        <i class="fas fa-user"></i>
                        <input 
                            type="text" 
                            name="email_username" 
                            placeholder="Ex: smith.allen" 
                            required
                            value="<?php echo htmlspecialchars($_POST['email_username'] ?? ''); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="password" 
                            name="password" 
                            id="password"
                            placeholder="••••••••••••••••" 
                            required
                        >
                        <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                    </div>

                    <div class="forgot-password">
                        <a href="forgotpass.php">Forgot your password?</a>
                    </div>

                    <button type="submit" class="btn-login">LOGIN</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Convert email/username input to lowercase and strip spaces
        const emailUsernameInput = document.querySelector('input[name="email_username"]');
        if (emailUsernameInput) {
            emailUsernameInput.addEventListener('input', function() {
                this.value = this.value.toLowerCase().replace(/\s/g, '');
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
    </script>
</body>
</html>
