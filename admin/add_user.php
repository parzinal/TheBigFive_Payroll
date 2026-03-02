<?php
/**
 * Add Account Page
 * Admin can create new user accounts
 */

// Set page title
$page_title = 'Add Account';

// Include header
require_once 'include/header.php';

// Include database connection
require_once '../config/database.php';

// Include account logs helper
require_once '../config/account_logs_helper.php';

// Include sidebar
require_once 'include/sidebar.php';

// Initialize variables
$success = '';
$error = '';
$username = '';
$email = '';
$full_name = '';
$role = 'staff';
$status = 'active';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
    // Get form data
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($username)) {
        $error = "Username is required.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } elseif (!preg_match('/^[a-z0-9_]+$/', $username)) {
        $error = "Username can only contain lowercase letters, numbers, and underscores.";
    } elseif (empty($email)) {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (empty($full_name)) {
        $error = "Full name is required.";
    } elseif (strlen($full_name) < 2) {
        $error = "Full name must be at least 2 characters.";
    } elseif (empty($password)) {
        $error = "Password is required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Username already exists.";
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Email already exists.";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new user
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashed_password, $full_name, $role, $status]);
                    
                    // Log the action
                    logCreateAction(
                        $_SESSION['user_id'],
                        $_SESSION['username'],
                        'User Account',
                        "{$full_name} ({$username}) - Role: {$role}",
                        $pdo
                    );
                    
                    // Success - redirect to user management
                    header('Location: user_management.php?success=Account created successfully');
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error = "Error creating user: " . $e->getMessage();
        }
    }    } // end CSRF else}
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-left">
                <div class="page-title-row">
                    <div class="page-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="page-title-text">
                        <h1>Add New Account</h1>
                        <div class="page-breadcrumb">
                            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                            <span class="page-breadcrumb-separator">/</span>
                            <a href="user_management.php">Accounts</a>
                            <span class="page-breadcrumb-separator">/</span>
                            <span>Add Account</span>
                        </div>
                    </div>
                </div>
                <p class="page-subtitle">Create a new system user account with role assignment</p>
            </div>
            <div class="page-header-right">
                <div class="page-header-actions">
                    <a href="user_management.php" class="btn-header-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Accounts
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Account Information</h3>
        </div>
        <div class="card-body" style="padding: 24px;">
            <form method="POST" action="" id="addUserForm">
                <?php echo csrfTokenField(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username" class="form-label required">Username</label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($username); ?>"
                               required
                               autocomplete="off">
                        <small class="form-help">Username for login (letters, numbers, underscore)</small>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label required">Email Address</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($email); ?>"
                               required
                               autocomplete="off">
                        <small class="form-help">Valid email address</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="full_name" class="form-label required">Full Name</label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($full_name); ?>"
                               required
                               autocomplete="off">
                        <small class="form-help">Complete name of the user</small>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label required">Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-input" 
                                   required
                                   minlength="6"
                                   autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="password-icon"></i>
                            </button>
                        </div>
                        <small class="form-help">Minimum 6 characters</small>
                        <div class="password-strength"><div class="password-strength-bar"></div></div>
                        <div class="password-strength-text"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label required">Confirm Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-input" 
                                   required
                                   minlength="6"
                                   autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password-icon"></i>
                            </button>
                        </div>
                        <small class="form-help">Re-enter password</small>
                    </div>

                    <div class="form-group">
                        <label for="role" class="form-label required">User Role</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="staff" <?php echo $role === 'staff' ? 'selected' : ''; ?>>Staff</option>
                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                        <small class="form-help">Access level for this user</small>
                    </div>

                    <div class="form-group">
                        <label for="status" class="form-label required">Account Status</label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <small class="form-help">Enable or disable account</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create User
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='user_management.php'">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Modern Flat Form Design */
.main-content {
    background: #F9FAFB;
    min-height: 100vh;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
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
    font-size: 32px;
}

.page-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #2563EB;
    color: white;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.2);
}

.btn-primary:hover {
    background: #1d4ed8;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-secondary {
    background: #6B7280;
    color: white;
    box-shadow: 0 1px 3px rgba(107, 114, 128, 0.2);
}

.btn-secondary:hover {
    background: #4B5563;
    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
}

.btn i {
    font-size: 16px;
}

.card {
    background: #FFFFFF;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #E5E7EB;
    overflow: hidden;
    margin-bottom: 24px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #E5E7EB;
    background: #FFFFFF;
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin: 0;
}

.card-body {
    padding: 24px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-bottom: 24px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
}

.form-label.required::after {
    content: ' *';
    color: #EF4444;
}

.form-input,
.form-select {
    padding: 10px 14px;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    font-size: 14px;
    color: #1F2937;
    background: #FFFFFF;
    transition: all 0.2s ease;
}

.form-input:focus,
.form-select:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-help {
    font-size: 12px;
    color: #6B7280;
    margin-top: 6px;
}

.password-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input-wrapper .form-input {
    padding-right: 45px;
    flex: 1;
}

.password-toggle {
    position: absolute;
    right: 10px;
    background: transparent;
    border: none;
    color: #6B7280;
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
}

.password-toggle:hover {
    color: #2563EB;
}

.password-toggle i {
    font-size: 16px;
}

.form-actions {
    display: flex;
    gap: 12px;
    padding-top: 24px;
    border-top: 1px solid #E5E7EB;
}

.alert {
    padding: 14px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
    border: 1px solid;
}

.alert i {
    font-size: 18px;
}

.alert-danger {
    background-color: #FEE2E2;
    color: #991B1B;
    border-color: #FECACA;
}

.alert-success {
    background-color: #D1FAE5;
    color: #065F46;
    border-color: #A7F3D0;
}

/* Field validation styles */
.form-input.input-error, .form-select.input-error {
    border-color: #EF4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}
.form-input.input-success, .form-select.input-success {
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

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
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
        const wrapper = input.closest('.password-input-wrapper');
        const helpText = input.closest('.form-group').querySelector('.form-help');
        const strength = input.closest('.form-group').querySelector('.password-strength');
        const insertAfter = strength || (wrapper ? wrapper : (helpText ? helpText : input));
        insertAfter.insertAdjacentElement('afterend', errEl);
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

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
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

// Username validation - force lowercase, strip invalid chars
document.getElementById('username').addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
    const val = this.value.trim();
    if (val.length === 0) { clearFieldError('username'); }
    else if (val.length < 3) { showFieldError('username', 'Username must be at least 3 characters'); }
    else if (!/^[a-z0-9_]+$/.test(val)) { showFieldError('username', 'Only lowercase letters, numbers, and underscores allowed'); }
    else { markFieldValid('username'); }
});

// Email validation - lowercase, no spaces
document.getElementById('email').addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/\s/g, '');
    const val = this.value.trim();
    if (val.length === 0) { clearFieldError('email'); }
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { showFieldError('email', 'Please enter a valid email address'); }
    else { markFieldValid('email'); }
});

// Full name validation
document.getElementById('full_name').addEventListener('input', function() {
    const val = this.value.trim();
    if (val.length === 0) { clearFieldError('full_name'); }
    else if (val.length < 2) { showFieldError('full_name', 'Full name must be at least 2 characters'); }
    else { markFieldValid('full_name'); }
});

// Password validation with strength meter
document.getElementById('password').addEventListener('input', function() {
    const val = this.value;
    if (val.length === 0) { clearFieldError('password'); }
    else if (val.length < 6) { showFieldError('password', 'Password must be at least 6 characters'); }
    else { markFieldValid('password'); }
    updatePasswordStrength(val);
    // Also check confirm match
    const confirm = document.getElementById('confirm_password').value;
    if (confirm.length > 0) {
        if (confirm !== val) { showFieldError('confirm_password', 'Passwords do not match'); }
        else { markFieldValid('confirm_password'); }
    }
});

// Confirm password validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const val = this.value;
    const pw = document.getElementById('password').value;
    if (val.length === 0) { clearFieldError('confirm_password'); }
    else if (val !== pw) { showFieldError('confirm_password', 'Passwords do not match'); }
    else { markFieldValid('confirm_password'); }
});

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

// Form validation on submit
document.getElementById('addUserForm').addEventListener('submit', function(e) {
    let hasError = false;
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const fullName = document.getElementById('full_name').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (username.length < 3) { showFieldError('username', 'Username must be at least 3 characters'); hasError = true; }
    else if (!/^[a-z0-9_]+$/.test(username)) { showFieldError('username', 'Only lowercase letters, numbers, and underscores allowed'); hasError = true; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showFieldError('email', 'Please enter a valid email address'); hasError = true; }
    if (fullName.length < 2) { showFieldError('full_name', 'Full name must be at least 2 characters'); hasError = true; }
    if (password.length < 6) { showFieldError('password', 'Password must be at least 6 characters'); hasError = true; }
    if (password !== confirmPassword) { showFieldError('confirm_password', 'Passwords do not match'); hasError = true; }
    
    if (hasError) { e.preventDefault(); return false; }
});
</script>

<?php
// Include footer
require_once 'include/footer.php';
?>
