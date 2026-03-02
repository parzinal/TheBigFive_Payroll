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

<style>
/* Modern Flat Design - Profile Page */
.main-content {
    padding: 24px;
    background: #F9FAFB;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title i {
    color: #2563EB;
}

/* Card Styles */
.card {
    background: #FFFFFF;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 24px;
    border: 1px solid #E5E7EB;
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #E5E7EB;
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-title i {
    color: #2563EB;
    font-size: 20px;
}

.card-subtitle {
    color: #6B7280;
    margin: 0;
    font-size: 14px;
}

.card-body {
    padding: 24px;
}

/* Alert Messages */
.alert {
    padding: 16px 20px;
    border-radius: 10px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert i {
    font-size: 20px;
}

.alert-success {
    background: #D1FAE5;
    color: #065F46;
    border: 1px solid #10B981;
}

.alert-error {
    background: #FEE2E2;
    color: #991B1B;
    border: 1px solid #EF4444;
}

.alert-close {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: inherit;
    opacity: 0.6;
    transition: opacity 0.2s;
}

.alert-close:hover {
    opacity: 1;
}

/* Form Styles */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.form-grid .full-width {
    grid-column: span 2;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-weight: 500;
    color: #374151;
    font-size: 14px;
}

.form-label i {
    color: #6B7280;
    font-size: 14px;
}

.required {
    color: #EF4444;
    font-weight: bold;
}

.form-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 14px;
    color: #111827;
    background-color: #FFFFFF;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-input:disabled {
    background-color: #F3F4F6;
    color: #9CA3AF;
    cursor: not-allowed;
}

.form-help {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: #6B7280;
}

.password-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input-wrapper .form-input {
    padding-right: 45px;
}

.password-toggle {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: #6B7280;
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

.password-toggle:hover {
    color: #2563EB;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 24px;
    border-top: 1px solid #E5E7EB;
}

/* Buttons */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #2563EB;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-secondary {
    background: #FFFFFF;
    color: #374151;
    border: 1px solid #E5E7EB;
}

.btn-secondary:hover {
    background: #F9FAFB;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: #F9FAFB;
    border-radius: 10px;
    border: 1px solid #E5E7EB;
}

.info-item i {
    font-size: 32px;
    color: #2563EB;
}

.info-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-size: 12px;
    color: #6B7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 16px;
    color: #111827;
    font-weight: 600;
}

/* Badges */
.badge-role {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-admin {
    background: #2563EB;
    color: white;
}

.badge-staff {
    background: #F59E0B;
    color: white;
}

.badge-user {
    background: #2563EB;
    color: white;
}

.badge-status {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

.badge-active {
    background: #10B981;
    color: white;
}

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid .full-width {
        grid-column: span 1;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<style>
/* Field validation styles */
.form-input.input-error {
    border-color: #EF4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}
.form-input.input-success {
    border-color: #10B981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
}
.field-error-msg {
    color: #EF4444;
    font-size: 12px;
    margin-top: 4px;
    display: none;
    align-items: center;
    gap: 4px;
}
.field-error-msg.show {
    display: flex;
}
.field-error-msg i {
    font-size: 11px;
}
.btn:disabled, .btn[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}
.password-strength {
    height: 4px;
    border-radius: 2px;
    margin-top: 6px;
    background: #E5E7EB;
    overflow: hidden;
}
.password-strength-bar {
    height: 100%;
    border-radius: 2px;
    transition: width 0.3s ease, background 0.3s ease;
    width: 0%;
}
.password-strength-text {
    font-size: 11px;
    margin-top: 3px;
    color: #6B7280;
}
</style>

<script>
// ============================================================
// Original values for change detection
// ============================================================
const originalValues = {
    full_name: <?php echo json_encode($user['full_name'] ?? ''); ?>,
    username: <?php echo json_encode($user['username'] ?? ''); ?>,
    email: <?php echo json_encode($user['email'] ?? ''); ?>
};

// ============================================================
// Utility: show/clear field errors
// ============================================================
function showFieldError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.add('input-error');
    input.classList.remove('input-success');
    let errEl = input.closest('.form-group').querySelector('.field-error-msg');
    if (!errEl) {
        errEl = document.createElement('div');
        errEl.className = 'field-error-msg';
        errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span></span>';
        // Insert after the input or after the password wrapper
        const wrapper = input.closest('.password-input-wrapper');
        (wrapper || input).insertAdjacentElement('afterend', errEl);
    }
    errEl.querySelector('span').textContent = message;
    errEl.classList.add('show');
}

function clearFieldError(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.remove('input-error');
    const errEl = input.closest('.form-group').querySelector('.field-error-msg');
    if (errEl) errEl.classList.remove('show');
}

function markFieldValid(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.remove('input-error');
    input.classList.add('input-success');
    clearFieldError(inputId);
}

// ============================================================
// Profile form: change tracking + validation
// ============================================================
function checkProfileChanges() {
    const fullName = document.getElementById('full_name').value.trim();
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    
    const changed = (fullName !== originalValues.full_name || 
                     username !== originalValues.username || 
                     email !== originalValues.email);
    
    const btn = document.getElementById('btnUpdateProfile');
    if (btn) btn.disabled = !changed;
    return changed;
}

// Attach change tracking to profile fields
['full_name', 'username', 'email'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', function() { checkProfileChanges(); });
        el.addEventListener('change', function() { checkProfileChanges(); });
    }
});

// ============================================================
// Username: enforce lowercase, no spaces, alphanumeric + underscore
// ============================================================
const usernameInput = document.getElementById('username');
if (usernameInput) {
    usernameInput.addEventListener('input', function() {
        // Auto-convert to lowercase and strip invalid chars
        this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
        
        // Validate
        const val = this.value.trim();
        if (val.length === 0) {
            clearFieldError('username');
        } else if (val.length < 3) {
            showFieldError('username', 'Username must be at least 3 characters');
        } else if (!/^[a-z0-9_]+$/.test(val)) {
            showFieldError('username', 'Only lowercase letters, numbers, and underscores allowed');
        } else {
            markFieldValid('username');
        }
        checkProfileChanges();
    });
}

// ============================================================
// Email: force lowercase, validate format
// ============================================================
const emailInput = document.getElementById('email');
if (emailInput) {
    emailInput.addEventListener('input', function() {
        this.value = this.value.toLowerCase().replace(/\s/g, '');
        
        const val = this.value.trim();
        if (val.length === 0) {
            clearFieldError('email');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
            showFieldError('email', 'Please enter a valid email address');
        } else {
            markFieldValid('email');
        }
        checkProfileChanges();
    });
}

// ============================================================
// Full name: basic validation
// ============================================================
const fullNameInput = document.getElementById('full_name');
if (fullNameInput) {
    fullNameInput.addEventListener('input', function() {
        const val = this.value.trim();
        if (val.length === 0) {
            clearFieldError('full_name');
        } else if (val.length < 2) {
            showFieldError('full_name', 'Full name must be at least 2 characters');
        } else {
            markFieldValid('full_name');
        }
        checkProfileChanges();
    });
}

// ============================================================
// Profile form: submit validation
// ============================================================
document.getElementById('profileForm').addEventListener('submit', function(e) {
    let hasError = false;
    
    const fullName = document.getElementById('full_name').value.trim();
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    
    if (fullName.length < 2) {
        showFieldError('full_name', 'Full name must be at least 2 characters');
        hasError = true;
    }
    if (username.length < 3) {
        showFieldError('username', 'Username must be at least 3 characters');
        hasError = true;
    } else if (!/^[a-z0-9_]+$/.test(username)) {
        showFieldError('username', 'Only lowercase letters, numbers, and underscores allowed');
        hasError = true;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showFieldError('email', 'Please enter a valid email address');
        hasError = true;
    }
    
    if (hasError) {
        e.preventDefault();
        return false;
    }
});

// ============================================================
// Password form: change tracking + validation
// ============================================================
function checkPasswordChanges() {
    const current = document.getElementById('current_password').value;
    const newPw = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    const hasContent = current.length > 0 || newPw.length > 0 || confirm.length > 0;
    
    document.getElementById('btnResetPassword').disabled = !hasContent;
    document.getElementById('btnChangePassword').disabled = !(current.length > 0 && newPw.length >= 6 && confirm.length > 0);
    return hasContent;
}

['current_password', 'new_password', 'confirm_password'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', function() {
            checkPasswordChanges();
            validatePasswordField(id);
        });
    }
});

function validatePasswordField(fieldId) {
    if (fieldId === 'new_password') {
        const val = document.getElementById('new_password').value;
        if (val.length > 0 && val.length < 6) {
            showFieldError('new_password', 'Password must be at least 6 characters');
        } else if (val.length >= 6) {
            markFieldValid('new_password');
        } else {
            clearFieldError('new_password');
        }
        // Update strength bar
        updatePasswordStrength(val);
    }
    if (fieldId === 'confirm_password') {
        const newPw = document.getElementById('new_password').value;
        const confirm = document.getElementById('confirm_password').value;
        if (confirm.length > 0 && confirm !== newPw) {
            showFieldError('confirm_password', 'Passwords do not match');
        } else if (confirm.length > 0 && confirm === newPw) {
            markFieldValid('confirm_password');
        } else {
            clearFieldError('confirm_password');
        }
    }
}

function updatePasswordStrength(password) {
    const bar = document.querySelector('.password-strength-bar');
    const text = document.querySelector('.password-strength-text');
    if (!bar || !text) return;
    
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    const levels = [
        { width: '0%', color: '#E5E7EB', label: '' },
        { width: '20%', color: '#EF4444', label: 'Very Weak' },
        { width: '40%', color: '#F59E0B', label: 'Weak' },
        { width: '60%', color: '#F59E0B', label: 'Fair' },
        { width: '80%', color: '#10B981', label: 'Strong' },
        { width: '100%', color: '#059669', label: 'Very Strong' }
    ];
    
    const level = levels[Math.min(strength, 5)];
    bar.style.width = level.width;
    bar.style.background = level.color;
    text.textContent = level.label;
    text.style.color = level.color;
}

function resetPasswordForm() {
    document.getElementById('passwordForm').reset();
    ['current_password', 'new_password', 'confirm_password'].forEach(clearFieldError);
    document.getElementById('btnResetPassword').disabled = true;
    document.getElementById('btnChangePassword').disabled = true;
    const bar = document.querySelector('.password-strength-bar');
    const text = document.querySelector('.password-strength-text');
    if (bar) { bar.style.width = '0%'; }
    if (text) { text.textContent = ''; }
}

// Password match validation on submit
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    let hasError = false;
    
    if (newPassword.length < 6) {
        showFieldError('new_password', 'Password must be at least 6 characters');
        hasError = true;
    }
    if (newPassword !== confirmPassword) {
        showFieldError('confirm_password', 'Passwords do not match');
        hasError = true;
    }
    
    if (hasError) {
        e.preventDefault();
        return false;
    }
});

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.remove();
        }, 500);
    });
}, 5000);
</script>

<?php
require_once 'include/footer.php';
?>
