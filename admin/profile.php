<?php
/**
 * My Profile Page
 * User profile management and settings
 */

// Set page title
$page_title = 'My Profile';

// Include header
require_once 'include/header.php';

// Include database connection
require_once '../config/database.php';

// Include account logs helper
require_once '../config/account_logs_helper.php';

// Include sidebar
require_once 'include/sidebar.php';

// Initialize variables
$success_message = '';
$error_message = '';
$password_success = '';
$password_error = '';

// Get current user data
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, created_at, last_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: ../logout.php');
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Error fetching user data: " . $e->getMessage();
    $user = [];
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
    } else {
    try {
        $pdo = getDBConnection();
        
        $full_name = trim($_POST['full_name']);
        $email = strtolower(trim($_POST['email']));
        $username = strtolower(trim($_POST['username']));
        
        // Validation
        $errors = [];
        
        if (empty($full_name)) {
            $errors[] = "Full name is required.";
        } elseif (strlen($full_name) < 2) {
            $errors[] = "Full name must be at least 2 characters.";
        } elseif (!preg_match('/^[\p{L}\s.\'-]+$/u', $full_name)) {
            $errors[] = "Full name contains invalid characters.";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required.";
        }
        
        if (empty($username)) {
            $errors[] = "Username is required.";
        } elseif (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters.";
        } elseif (!preg_match('/^[a-z0-9_]+$/', $username)) {
            $errors[] = "Username can only contain lowercase letters, numbers, and underscores. No spaces allowed.";
        }
        
        // Check if anything actually changed
        $current_data = [
            'full_name' => $user['full_name'] ?? '',
            'email' => $user['email'] ?? '',
            'username' => $user['username'] ?? ''
        ];
        $no_changes = ($full_name === $current_data['full_name'] && 
                       $email === $current_data['email'] && 
                       $username === $current_data['username']);
        
        if ($no_changes) {
            $error_message = "No changes were made to update.";
            $errors[] = "skip"; // Skip further processing
        }
        
        // Check if email already exists for another user
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $errors[] = "Email already exists.";
            }
        }
        
        // Check if username already exists for another user
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $errors[] = "Username already exists.";
            }
        }
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, username = ?, updated_at = NOW() WHERE id = ?");
            
            if ($stmt->execute([$full_name, $email, $username, $_SESSION['user_id']])) {
                $success_message = "Profile updated successfully!";
                
                // Log profile update
                logProfileUpdate(
                    $_SESSION['user_id'],
                    $_SESSION['username'],
                    "Updated profile information",
                    $pdo
                );
                
                // Update session data
                $_SESSION['full_name'] = $full_name;
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, created_at, last_login FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
            } else {
                $error_message = "Failed to update profile.";
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
    } // end CSRF else
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $password_error = 'Invalid security token. Please refresh the page and try again.';
    } else {
    try {
        $pdo = getDBConnection();
        
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        $errors = [];
        
        if (empty($current_password)) {
            $errors[] = "Current password is required.";
        }
        
        if (empty($new_password)) {
            $errors[] = "New password is required.";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters.";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match.";
        }
        
        if (empty($errors)) {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user_data = $stmt->fetch();
            
            if (!password_verify($current_password, $user_data['password'])) {
                $errors[] = "Current password is incorrect.";
            }
        }
        
        if (empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            
            if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                $password_success = "Password changed successfully!";
                
                // Log password change
                logPasswordChange(
                    $_SESSION['user_id'],
                    $_SESSION['username'],
                    $pdo
                );
            } else {
                $password_error = "Failed to change password.";
            }
        } else {
            $password_error = implode("<br>", $errors);
        }
    } catch (PDOException $e) {
        $password_error = "Database error: " . $e->getMessage();
    }
    } // end CSRF else
}
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-left">
                <div class="page-title-row">
                    <div class="page-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="page-title-text">
                        <h1>My Profile</h1>
                        <div class="page-breadcrumb">
                            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                            <span class="page-breadcrumb-separator">/</span>
                            <span>Profile</span>
                        </div>
                    </div>
                </div>
                <p class="page-subtitle">Manage your personal information and account settings</p>
            </div>
        </div>
    </div>

    <!-- Profile Information Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-id-card"></i> Profile Information
            </h3>
            <p class="card-subtitle">Manage your personal information</p>
        </div>
        <div class="card-body">
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success_message; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error_message; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="profileForm">
                <?php echo csrfTokenField(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name" class="form-label">
                            <i class="fas fa-user"></i> Full Name <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user-tag"></i> Username <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i> Email Address <span class="required">*</span>
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="role" class="form-label">
                            <i class="fas fa-shield-alt"></i> Role
                        </label>
                        <input type="text" 
                               id="role" 
                               class="form-input" 
                               value="<?php echo ucfirst($user['role'] ?? 'User'); ?>"
                               disabled>
                        <small class="form-help">Your role cannot be changed</small>
                    </div>

                    <div class="form-group">
                        <label for="member_since" class="form-label">
                            <i class="fas fa-calendar-alt"></i> Member Since
                        </label>
                        <input type="text" 
                               id="member_since" 
                               class="form-input" 
                               value="<?php echo $user['created_at'] ? date('F d, Y', strtotime($user['created_at'])) : 'N/A'; ?>"
                               disabled>
                    </div>

                    <div class="form-group">
                        <label for="last_login" class="form-label">
                            <i class="fas fa-clock"></i> Last Login
                        </label>
                        <input type="text" 
                               id="last_login" 
                               class="form-input" 
                               value="<?php echo $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'N/A'; ?>"
                               disabled>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn btn-primary" id="btnUpdateProfile" disabled>
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-lock"></i> Change Password
            </h3>
            <p class="card-subtitle">Update your password to keep your account secure</p>
        </div>
        <div class="card-body">
            <?php if (!empty($password_success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $password_success; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>

            <?php if (!empty($password_error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $password_error; ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="passwordForm">
                <?php echo csrfTokenField(); ?>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="current_password" class="form-label">
                            <i class="fas fa-key"></i> Current Password <span class="required">*</span>
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   class="form-input" 
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password" class="form-label">
                            <i class="fas fa-lock"></i> New Password <span class="required">*</span>
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   class="form-input" 
                                   minlength="6"
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-help">At least 6 characters</small>
                        <div class="password-strength"><div class="password-strength-bar"></div></div>
                        <div class="password-strength-text"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i> Confirm New Password <span class="required">*</span>
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-input" 
                                   minlength="6"
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="btnResetPassword" onclick="resetPasswordForm()" disabled>
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="submit" name="change_password" class="btn btn-primary" id="btnChangePassword" disabled>
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Account Information Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-info-circle"></i> Account Information
            </h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <i class="fas fa-user-circle"></i>
                    <div class="info-content">
                        <span class="info-label">User ID</span>
                        <span class="info-value">#<?php echo $user['id']; ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-shield-alt"></i>
                    <div class="info-content">
                        <span class="info-label">Account Type</span>
                        <span class="info-value">
                            <span class="badge-role badge-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-check-circle"></i>
                    <div class="info-content">
                        <span class="info-label">Account Status</span>
                        <span class="info-value">
                            <span class="badge-status badge-active">Active</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../assets/css/profile.css">

<script>
// PHP data for change detection (must be inline)
const originalValues = {
    full_name: <?php echo json_encode($user['full_name'] ?? ''); ?>,
    username: <?php echo json_encode($user['username'] ?? ''); ?>,
    email: <?php echo json_encode($user['email'] ?? ''); ?>
};
</script>
<script src="../assets/js/profile.js"></script>

<?php
require_once 'include/footer.php';
?>
