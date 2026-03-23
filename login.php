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
$rateLimited = false;

// --- Rate Limiting Helper ---
function checkLoginRateLimit(PDO $pdo, string $ip, int $maxAttempts = 5, int $windowMinutes = 15): array {
    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, attempted_at)
    ) ENGINE=InnoDB");

    // Purge old records beyond window
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)")
        ->execute([$windowMinutes]);

    // Count recent attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$ip, $windowMinutes]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $maxAttempts) {
        // Calculate seconds remaining
        $stmtOldest = $pdo->prepare("SELECT attempted_at FROM login_attempts WHERE ip_address = ? ORDER BY attempted_at ASC LIMIT 1");
        $stmtOldest->execute([$ip]);
        $oldest = $stmtOldest->fetchColumn();
        $unlockTime = strtotime($oldest) + ($windowMinutes * 60);
        $remaining = max(0, $unlockTime - time());
        return ['blocked' => true, 'remaining' => $remaining, 'attempts' => $count];
    }
    return ['blocked' => false, 'remaining' => 0, 'attempts' => $count];
}

function recordLoginAttempt(PDO $pdo, string $ip): void {
    $pdo->prepare("INSERT INTO login_attempts (ip_address, attempted_at) VALUES (?, NOW())")
        ->execute([$ip]);
}

function clearLoginAttempts(PDO $pdo, string $ip): void {
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
}

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
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            // Check rate limit before processing
            $rateCheck = checkLoginRateLimit($pdo, $clientIp);
            if ($rateCheck['blocked']) {
                $mins = ceil($rateCheck['remaining'] / 60);
                $error = "Too many failed login attempts. Please try again in {$mins} minute(s).";
                $rateLimited = true;
            } else {
            
            // Check if input is email or username
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1");
            $stmt->execute([$email_or_username, $email_or_username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Clear rate limit on successful login
                clearLoginAttempts($pdo, $clientIp);

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
                // Record failed attempt for rate limiting
                recordLoginAttempt($pdo, $clientIp);

                // Log failed login attempt
                logFailedLogin($email_or_username, 'Invalid credentials or account inactive');
                $attemptsLeft = 5 - ($rateCheck['attempts'] + 1);
                if ($attemptsLeft > 0) {
                    $error = "Invalid credentials or account is inactive. {$attemptsLeft} attempt(s) remaining.";
                } else {
                    $error = 'Too many failed login attempts. Please try again in 15 minutes.';
                    $rateLimited = true;
                }
            }

            } // end rate limit else
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

                    <button type="submit" class="btn-login" id="loginBtn" <?php echo $rateLimited ? 'disabled style="opacity:0.6;cursor:not-allowed;"' : ''; ?>>LOGIN</button>
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

        // Prevent double-submit on login form (S1)
        const loginForm = document.querySelector('.login-form');
        const loginBtn = document.getElementById('loginBtn');
        if (loginForm && loginBtn) {
            loginForm.addEventListener('submit', function() {
                loginBtn.disabled = true;
                loginBtn.textContent = 'LOGGING IN...';
                loginBtn.style.opacity = '0.6';
                loginBtn.style.cursor = 'not-allowed';
            });
        }
    </script>
</body>
</html>
