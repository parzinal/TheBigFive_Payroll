<?php
/**
 * Add Employee Page
 * Professional interface for adding new employees
 */

// ── IMPORTANT: Process form BEFORE any HTML output ──────────────────────────
// This must come first so we can set $success_message / $error_message
// before the page renders, and avoid header-already-sent issues.
require_once __DIR__ . '/../config/auth.php';       // starts session, CSRF, etc.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/account_logs_helper.php';
require_once __DIR__ . '/../config/notifications_helper.php';

// Require admin role
requireAuth('admin');

// Initialize variables
$success_message = '';
$error_message = '';
$full_name = '';
$position = '';
$basic_salary = '';
$hire_date = '';
$status = 'active';
$classification = 'Fix Rate';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_form_add_employee'])) {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
    } else {
    try {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get and sanitize form data
        $full_name      = trim($_POST['full_name']      ?? '');
        $position       = trim($_POST['position']       ?? '');
        $basic_salary   = trim($_POST['basic_salary']   ?? '');
        $hire_date      = trim($_POST['hire_date']      ?? '');
        $status         = $_POST['status']              ?? 'active';
        $classification = $_POST['classification']      ?? 'Fix Rate';

        // Validation
        $errors = [];

        if (empty($full_name)) {
            $errors[] = "Full Name is required.";
        } elseif (strlen($full_name) < 2) {
            $errors[] = "Full Name must be at least 2 characters.";
        } elseif (!preg_match('/^[\p{L}\s.\'\-]+$/u', $full_name)) {
            $errors[] = "Full Name contains invalid characters.";
        }

        if ($basic_salary === '' || !is_numeric($basic_salary) || floatval($basic_salary) < 0) {
            $errors[] = "Valid Basic Monthly Salary is required.";
        } elseif (floatval($basic_salary) > 99999999.99) {
            $errors[] = "Basic Monthly Salary is too large.";
        }

        if (!empty($hire_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hire_date)) {
            $errors[] = "Invalid hire date format.";
        }

        $allowedStatuses = ['active', 'inactive'];
        if (!in_array($status, $allowedStatuses)) {
            $errors[] = "Invalid status value.";
        }

        $allowedClassifications = ['Fix Rate', 'Trainer'];
        if (!in_array($classification, $allowedClassifications)) {
            $classification = 'Fix Rate'; // default fallback
        }

        if (empty($errors)) {
            // Auto-generate a unique employee code
            $currentYear = date('Y');
            $stmt = $pdo->query("SELECT COUNT(*) FROM employees");
            $nextNumber = intval($stmt->fetchColumn()) + 1;
            do {
                $employee_code = 'EMP-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT) . '-' . $currentYear;
                $chk = $pdo->prepare("SELECT id FROM employees WHERE employee_code = ?");
                $chk->execute([$employee_code]);
                if ($chk->fetchColumn()) { $nextNumber++; } else { break; }
            } while (true);

            // NULL-ify empty optional fields (MySQL strict mode rejects '' for DATE)
            $hire_date_val = !empty($hire_date) ? $hire_date : null;
            $position_val  = !empty($position)  ? $position  : null;

            $stmt = $pdo->prepare("
                INSERT INTO employees
                    (employee_code, full_name, position, basic_monthly_salary, hire_date, status, classification)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$employee_code, $full_name, $position_val, floatval($basic_salary), $hire_date_val, $status, $classification]);

            $newEmpId = $pdo->lastInsertId();
            $success_message = "Employee added successfully! Employee Code: " . htmlspecialchars($employee_code);

            // Log action (errors here are non-fatal)
            try {
                logCreateAction(
                    $_SESSION['user_id']  ?? null,
                    $_SESSION['username'] ?? 'admin',
                    'Employee',
                    "{$full_name} ({$employee_code})",
                    $pdo
                );
                notifyEmployeeAdded($full_name, $newEmpId);
                notifyStaff(
                    'New Employee Added',
                    "{$full_name} ({$employee_code}) has been added by " . ($_SESSION['username'] ?? 'Admin') . ".",
                    'success', 'fa-user-plus', 'employee_list.php'
                );
            } catch (\Throwable $ignored) {
                error_log("Add Employee post-actions error: " . $ignored->getMessage());
            }

            // Clear form fields on success
            $full_name = $position = $basic_salary = $hire_date = '';
            $status = 'active';
            $classification = 'Fix Rate';
        } else {
            $error_message = implode("<br>", $errors);
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
        error_log("Add Employee PDOException: " . $e->getMessage());
    } catch (\Throwable $e) {
        $error_message = "Unexpected error: " . $e->getMessage();
        error_log("Add Employee Throwable: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    }
    } // end CSRF else
}

// ── NOW output the page ──────────────────────────────────────────────────────
$page_title = 'Add Employee';
require_once 'include/header.php';
require_once 'include/sidebar.php';
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
                        <h1>Add New Employee</h1>
                        <div class="page-breadcrumb">
                            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                            <span class="page-breadcrumb-separator">/</span>
                            <a href="employee_list.php">Employees</a>
                            <span class="page-breadcrumb-separator">/</span>
                            <span>Add Employee</span>
                        </div>
                    </div>
                </div>
                <p class="page-subtitle">Create a new employee record in the system</p>
            </div>
            <div class="page-header-right">
                <div class="page-header-actions">
                    <a href="employee_list.php" class="btn-header-secondary">
                        <i class="fas fa-list"></i>
                        View All Employees
                    </a>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Employee Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user"></i> Employee Information
            </h3>
            <p class="card-subtitle">Fill in the required fields to add a new employee</p>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="employeeForm">
                <?php echo csrfTokenField(); ?>
                <input type="hidden" name="_form_add_employee" value="1">
                <div class="alert-info" style="margin-bottom: 20px; padding: 12px; background: #EFF6FF; border-left: 4px solid #2563EB; border-radius: 8px;">
                    <i class="fas fa-info-circle" style="color: #2563EB; margin-right: 8px;"></i>
                    <strong>Employee Code:</strong> Will be automatically generated (e.g., EMP-001-<?php echo date('Y'); ?>)
                </div>
                
                <div class="form-grid">
                    <!-- Full Name -->
                    <div class="form-group">
                        <label for="full_name" class="form-label">
                            <i class="fas fa-user"></i> Full Name <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($full_name); ?>"
                               placeholder="e.g., Juan Dela Cruz"
                               required>
                    </div>

                    <!-- Position -->
                    <div class="form-group">
                        <label for="position" class="form-label">
                            <i class="fas fa-briefcase"></i> Position
                        </label>
                        <input type="text" 
                               id="position" 
                               name="position" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($position); ?>"
                               placeholder="e.g., Software Developer"
                               oninput="this.value=this.value.replace(/[^a-zA-Z0-9 ]/g,'')">
                        <small class="form-help">Job title or role (letters and numbers only)</small>
                    </div>

                    <!-- Basic Monthly Salary -->
                    <div class="form-group">
                        <label for="basic_salary" class="form-label">
                            <i class="fas fa-money-bill-wave"></i> Basic Monthly Salary <span class="required">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-prefix">₱</span>
                            <input type="number" 
                                   id="basic_salary" 
                                   name="basic_salary" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($basic_salary); ?>"
                                   placeholder="0.00"
                                   step="0.01"
                                   min="0"
                                   required>
                        </div>
                        <small class="form-help">Monthly basic salary amount</small>
                    </div>

                    <!-- Hire Date -->
                    <div class="form-group">
                        <label for="hire_date" class="form-label">
                            <i class="fas fa-calendar-alt"></i> Hire Date
                        </label>
                        <input type="date" 
                               id="hire_date" 
                               name="hire_date" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($hire_date); ?>">
                        <small class="form-help">Date when employee started</small>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="status" class="form-label">
                            <i class="fas fa-toggle-on"></i> Employment Status <span class="required">*</span>
                        </label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <small class="form-help">Current employment status</small>
                    </div>

                    <!-- Employee Classification -->
                    <div class="form-group">
                        <label for="classification" class="form-label">
                            <i class="fas fa-tags"></i> Employee Classification
                        </label>
                        <select id="classification" name="classification" class="form-select">
                            <option value="Fix Rate" <?php echo ($classification !== 'Trainer') ? 'selected' : ''; ?>>Fix Rate</option>
                            <option value="Trainer" <?php echo ($classification === 'Trainer') ? 'selected' : ''; ?>>Trainer</option>
                        </select>
                        <small class="form-help">Employee classification type</small>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="btnResetForm" onclick="resetForm()" disabled>
                        <i class="fas fa-undo"></i> Reset Form
                    </button>
                    <button type="submit" id="btnSubmitEmployee" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Info Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-info-circle"></i> Quick Tips
            </h3>
        </div>
        <div class="card-body">
            <ul class="tips-list">
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span><strong>Employee Code</strong> must be unique for each employee</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span><strong>Basic Monthly Salary</strong> is used for payroll calculations</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span>You can link an employee to a user account for portal access</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span>Use datalist suggestions for Position</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span>All fields marked with <span class="required">*</span> are required</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<style>
/* Modern Flat Design - Add Employee */
.main-content {
    padding: 24px;
    background: #F9FAFB;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
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

.page-actions {
    display: flex;
    gap: 12px;
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
    text-decoration: none;
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
    border-color: #D1D5DB;
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

/* Form Styles */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
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

.form-input,
.form-select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 14px;
    color: #111827;
    background-color: #FFFFFF;
    transition: all 0.2s ease;
}

.form-input:focus,
.form-select:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-input::placeholder {
    color: #9CA3AF;
}

.input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.input-prefix {
    position: absolute;
    left: 14px;
    color: #6B7280;
    font-weight: 500;
    pointer-events: none;
    font-size: 14px;
}

.input-group .form-input {
    padding-left: 36px;
}

.form-help {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: #6B7280;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #E5E7EB;
}

/* Tips List */
.tips-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.tips-list li {
    padding: 12px 0;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border-bottom: 1px solid #E5E7EB;
}

.tips-list li:last-child {
    border-bottom: none;
}

.tips-list li i {
    color: #10B981;
    margin-top: 2px;
    font-size: 16px;
    flex-shrink: 0;
}

.tips-list li span {
    color: #374151;
    font-size: 14px;
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn:disabled, .btn[disabled] {
        width: 100%;
        justify-content: center;
    }
}
</style>

<style>
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
.btn:disabled, .btn[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
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
        const helpText = input.closest('.form-group').querySelector('.form-help');
        const wrapper = input.closest('.input-group');
        const insertAfter = wrapper || helpText || input;
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

// Track if any field has content to enable/disable reset button
function checkFormHasContent() {
    const fields = ['full_name', 'position', 'basic_salary', 'hire_date'];
    let hasContent = false;
    fields.forEach(function(id) {
        const el = document.getElementById(id);
        if (el && el.value.trim() !== '') hasContent = true;
    });
    // Also check if status changed from default
    const statusEl = document.getElementById('status');
    if (statusEl && statusEl.value !== 'active') hasContent = true;
    
    const resetBtn = document.getElementById('btnResetForm');
    if (resetBtn) resetBtn.disabled = !hasContent;
    return hasContent;
}

// Listen for changes on all fields
['full_name', 'position', 'basic_salary', 'hire_date', 'status'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', checkFormHasContent);
        el.addEventListener('change', checkFormHasContent);
    }
});

// Real-time validation for specific fields

document.getElementById('full_name').addEventListener('input', function() {
    const val = this.value.trim();
    if (val.length === 0) { clearFieldError('full_name'); }
    else if (val.length < 2) { showFieldError('full_name', 'Full name must be at least 2 characters'); }
    else { markFieldValid('full_name'); }
});

document.getElementById('basic_salary').addEventListener('input', function() {
    const val = this.value;
    if (val === '') { clearFieldError('basic_salary'); }
    else if (isNaN(val) || parseFloat(val) < 0) { showFieldError('basic_salary', 'Please enter a valid positive amount'); }
    else { markFieldValid('basic_salary'); }
});

// Reset form function
function resetForm() {
    if (confirm('Are you sure you want to reset the form? All entered data will be cleared.')) {
        document.getElementById('employeeForm').reset();
        ['full_name', 'position', 'basic_salary', 'hire_date'].forEach(function(id) {
            clearFieldError(id);
            const el = document.getElementById(id);
            if (el) el.classList.remove('input-success', 'input-error');
        });
        document.getElementById('btnResetForm').disabled = true;
    }
}

// Form validation on submit
document.getElementById('employeeForm').addEventListener('submit', function(e) {
    const fullName = document.getElementById('full_name').value.trim();
    const basicSalary = document.getElementById('basic_salary').value;
    
    let hasError = false;
    
    if (fullName === '') {
        showFieldError('full_name', 'Full Name is required');
        hasError = true;
    } else if (fullName.length < 2) {
        showFieldError('full_name', 'Full name must be at least 2 characters');
        hasError = true;
    }
    
    if (basicSalary === '' || parseFloat(basicSalary) < 0) {
        showFieldError('basic_salary', 'Valid Basic Monthly Salary is required');
        hasError = true;
    }
    
    if (hasError) {
        e.preventDefault();
        return false;
    }

    // Disable after a brief delay so the browser finishes collecting POST data first
    const submitBtn = document.getElementById('btnSubmitEmployee');
    if (submitBtn) {
        setTimeout(function() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }, 50);
    }
});

// O3: Auto-dismiss only success alerts after 5 seconds; keep errors visible
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(function(alert) {
        alert.style.animation = 'slideUp 0.3s ease';
        setTimeout(function() {
            alert.remove();
        }, 300);
    });
}, 5000);
</script>

<?php
require_once 'include/footer.php';
?>
