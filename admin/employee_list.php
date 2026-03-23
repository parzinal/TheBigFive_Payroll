<?php
/**
 * Employee List Page
 * View and manage all employees
 */

// Set page title
$page_title = 'All Employees';

// Start session for auth & AJAX handling
require_once '../config/bootstrap.php';

// Include database connection
require_once '../config/database.php';

// Include account logs helper
require_once '../config/account_logs_helper.php';

// Check for success message
$success = $_GET['success'] ?? '';

// Handle AJAX update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_employee') {
    header('Content-Type: application/json');
    // Ensure user is authenticated for AJAX
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    // CSRF check
    require_once '../config/csrf.php';
    if (!validateCSRFToken()) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
        exit;
    }
    
    try {
        $pdo = getDBConnection();
        
        $employee_id = intval($_POST['employee_id']);
        $employee_code = trim($_POST['employee_code']);
        $full_name = trim($_POST['full_name']);
        $position = trim($_POST['position']);
        $classification = trim($_POST['classification']);
        $allowedClassifications = ['Fix Rate', 'Trainer'];
        if (!in_array($classification, $allowedClassifications)) {
            $classification = 'Fix Rate';
        }
        $basic_salary = floatval($_POST['basic_salary']);
        $hire_date = trim($_POST['hire_date']);
        $status = trim($_POST['status']);
        
        // Validate required fields
        if (empty($employee_code) || empty($full_name)) {
            echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
            exit;
        }
        
        if (strlen($full_name) < 2) {
            echo json_encode(['success' => false, 'message' => 'Full name must be at least 2 characters']);
            exit;
        }
        
        if ($basic_salary <= 0) {
            echo json_encode(['success' => false, 'message' => 'Basic salary must be greater than 0']);
            exit;
        }
        
        // Check if employee code exists for another employee
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE employee_code = ? AND id != ?");
        $stmt->execute([$employee_code, $employee_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Employee code already exists']);
            exit;
        }
        
        // Update employee
        $stmt = $pdo->prepare("UPDATE employees SET 
            employee_code = ?, 
            full_name = ?, 
            position = ?, 
            classification = ?, 
            basic_monthly_salary = ?, 
            hire_date = ?, 
            status = ?,
            updated_at = NOW()
            WHERE id = ?");
        
        $result = $stmt->execute([
            $employee_code,
            $full_name,
            $position,
            $classification,
            $basic_salary,
            $hire_date,
            $status,
            $employee_id
        ]);
        
        if ($result) {
            // Log the action
            logUpdateAction(
                $_SESSION['user_id'],
                $_SESSION['username'],
                'Employee',
                "{$full_name} ({$employee_code})",
                "Position: {$position}, Status: {$status}",
                $pdo
            );
            
            echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update employee']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch all employees from database
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, employee_code, full_name, position, classification, basic_monthly_salary, hire_date, status, created_at FROM employees ORDER BY created_at DESC");
    $stmt->execute();
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching employees: " . $e->getMessage();
    $employees = [];
}
?>

<?php
// Include header and sidebar for normal page rendering (do not include for AJAX responses)
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="page-title-text">
                        <h1>All Employees</h1>
                        <div class="page-breadcrumb">
                            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                            <span class="page-breadcrumb-separator">/</span>
                            <span>Employees</span>
                        </div>
                    </div>
                </div>
                <p class="page-subtitle">View and manage all employee records in the system</p>
            </div>
            <div class="page-header-right">
                <div class="page-header-actions">
                    <a href="Add_emplooyees.php" class="btn-header-primary">
                        <i class="fas fa-user-plus"></i>
                        Add New Employee
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
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
            <h3 class="card-title">Employee List</h3>
            <div class="card-tools">
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">
                        <i class="fas fa-users"></i> All
                    </button>
                    <button class="filter-btn" data-filter="active">
                        <i class="fas fa-user-check"></i> Active
                    </button>
                    <button class="filter-btn" data-filter="inactive">
                        <i class="fas fa-user-slash"></i> Inactive
                    </button>
                    <button class="filter-btn" data-filter="resigned">
                        <i class="fas fa-user-times"></i> Resigned
                    </button>
                </div>
                <input type="text" id="searchEmployees" class="employee-search-input" placeholder="Search employees...">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table" id="employeesTable">
                    <thead>
                        <tr>
                            <th>Employee Code</th>
                            <th>Full Name</th>
                            <th>Position</th>
                            <th>Employee Classification</th>
                            <th>Monthly Salary</th>
                            <th>Hire Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($employees) > 0): ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr data-status="<?php echo $employee['status']; ?>" data-employee-id="<?php echo $employee['id']; ?>">
                                    <td>
                                        <span class="employee-code"><?php echo htmlspecialchars($employee['employee_code']); ?></span>
                                    </td>
                                    <td>
                                        <div class="employee-info">
                                            <i class="fas fa-user-circle"></i>
                                            <strong class="employee-name"><?php echo htmlspecialchars($employee['full_name']); ?></strong>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['position'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($employee['classification'] ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="salary-amount">₱<?php echo number_format($employee['basic_monthly_salary'], 2); ?></span>
                                    </td>
                                    <td><?php echo $employee['hire_date'] ? date('M d, Y', strtotime($employee['hire_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        $statusIcon = '';
                                        switch ($employee['status']) {
                                            case 'active':
                                                $statusClass = 'badge-success';
                                                $statusIcon = 'fa-check-circle';
                                                break;
                                            case 'inactive':
                                                $statusClass = 'badge-secondary';
                                                $statusIcon = 'fa-pause-circle';
                                                break;
                                            case 'resigned':
                                                $statusClass = 'badge-warning';
                                                $statusIcon = 'fa-user-times';
                                                break;
                                            case 'terminated':
                                                $statusClass = 'badge-danger';
                                                $statusIcon = 'fa-ban';
                                                break;
                                            default:
                                                $statusClass = 'badge-secondary';
                                                $statusIcon = 'fa-question-circle';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <i class="fas <?php echo $statusIcon; ?>"></i>
                                            <?php echo ucfirst($employee['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-edit" title="Edit Employee" 
                                                    onclick="openEditModal(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['employee_code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($employee['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($employee['position'] ?: '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($employee['classification'] ?: 'Fix Rate', ENT_QUOTES); ?>', <?php echo $employee['basic_monthly_salary']; ?>, '<?php echo $employee['hire_date'] ?: ''; ?>', '<?php echo $employee['status']; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-delete" title="Delete Employee" 
                                                    onclick="confirmDelete(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['full_name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px;"></i>
                                        <p>No employees found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Employee Statistics</h3>
        </div>
        <div class="card-body" style="padding: 24px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <?php
                $activeCount = count(array_filter($employees, function($e) { return $e['status'] == 'active'; }));
                $inactiveCount = count(array_filter($employees, function($e) { return $e['status'] == 'inactive'; }));
                $resignedCount = count(array_filter($employees, function($e) { return $e['status'] == 'resigned'; }));
                $totalSalary = array_sum(array_map(function($e) { return $e['status'] == 'active' ? $e['basic_monthly_salary'] : 0; }, $employees));
                ?>
                
                <div class="stat-card stat-total">
                    <i class="fas fa-users stat-icon"></i>
                    <h4>Total Employees</h4>
                    <p class="stat-value"><?php echo count($employees); ?></p>
                </div>
                
                <div class="stat-card stat-active">
                    <i class="fas fa-user-check stat-icon"></i>
                    <h4>Active Employees</h4>
                    <p class="stat-value"><?php echo $activeCount; ?></p>
                </div>
                
                <div class="stat-card stat-inactive">
                    <i class="fas fa-user-slash stat-icon"></i>
                    <h4>Inactive</h4>
                    <p class="stat-value"><?php echo $inactiveCount; ?></p>
                </div>
                
                <div class="stat-card stat-resigned">
                    <i class="fas fa-user-times stat-icon"></i>
                    <h4>Resigned</h4>
                    <p class="stat-value"><?php echo $resignedCount; ?></p>
                </div>
                
                <div class="stat-card stat-payroll" style="grid-column: span 2;">
                    <i class="fas fa-money-bill-wave stat-icon"></i>
                    <h4>Total Monthly Payroll (Active)</h4>
                    <p class="stat-value">₱<?php echo number_format($totalSalary, 2); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div id="editModal" class="modal">
    <div class="modal-content" style="position: relative;">
        <!-- Loading Overlay -->
        <div id="editModalOverlay" class="modal-loading-overlay">
            <div class="spinner"></div>
            <span class="loading-text">Updating employee...</span>
        </div>
        <div class="modal-header">
            <h2 class="modal-title">
                <i class="fas fa-edit"></i> Edit Employee
            </h2>
            <button class="modal-close" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="editEmployeeForm" onsubmit="updateEmployee(event)">
            <div class="modal-body">
                <input type="hidden" id="edit_employee_id" name="employee_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_employee_code" class="form-label">
                            <i class="fas fa-id-badge"></i> Employee Code <span class="required">*</span>
                        </label>
                        <input type="text" id="edit_employee_code" name="employee_code" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_full_name" class="form-label">
                            <i class="fas fa-user"></i> Full Name <span class="required">*</span>
                        </label>
                        <input type="text" id="edit_full_name" name="full_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_position" class="form-label">
                            <i class="fas fa-briefcase"></i> Position
                        </label>
                        <input type="text" id="edit_position" name="position" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_classification" class="form-label">
                            <i class="fas fa-tags"></i> Employee Classification
                        </label>
                        <select id="edit_classification" name="classification" class="form-input">
                            <option value="Fix Rate">Fix Rate</option>
                            <option value="Trainer">Trainer</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_basic_salary" class="form-label">
                            <i class="fas fa-money-bill-wave"></i> Basic Monthly Salary <span class="required">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-prefix">₱</span>
                            <input type="number" id="edit_basic_salary" name="basic_salary" class="form-input" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_hire_date" class="form-label">
                            <i class="fas fa-calendar-alt"></i> Hire Date
                        </label>
                        <input type="date" id="edit_hire_date" name="hire_date" class="form-input">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="edit_status" class="form-label">
                            <i class="fas fa-toggle-on"></i> Employment Status <span class="required">*</span>
                        </label>
                        <select id="edit_status" name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="resigned">Resigned</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="btnUpdateEmployee" disabled>
                    <i class="fas fa-save"></i> Update Employee
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Employee Confirmation Modal -->
<div id="deleteConfirmModal" class="modal">
    <div class="modal-content delete-modal-content">
        <div class="delete-modal-body">
            <div class="delete-warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 class="delete-modal-title">Delete Employee</h2>
            <p class="delete-modal-message">
                Are you sure you want to permanently delete<br>
                <span id="deleteEmployeeNameDisplay" class="employee-name-highlight"></span>?
            </p>
            <p class="delete-modal-warning">This action cannot be undone.</p>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-danger" onclick="confirmDeleteAction()">
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

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

/* Center Delete Confirmation Modal */
#deleteConfirmModal.modal {
    position: fixed;
    inset: 0; /* top:0; right:0; bottom:0; left:0; */
    display: none; /* JS will set to 'flex' when opened */
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.45);
    z-index: 1050;
    padding: 20px;
}
.delete-modal-content {
    background: #ffffff;
    border-radius: 10px;
    width: 520px;
    max-width: calc(100% - 40px);
    box-shadow: 0 20px 40px rgba(2,6,23,0.12);
    overflow: hidden;
}
.delete-modal-body { padding: 28px; text-align: center; }
.delete-warning-icon { font-size: 40px; color: #f59e0b; margin-bottom: 8px; }
.delete-modal-title { margin: 0 0 8px 0; font-size: 20px; }
.delete-modal-message { margin: 0 0 6px 0; }
.delete-modal-warning { color: #6b7280; font-size: 13px; margin-top: 8px; }
.delete-modal-footer { display:flex; gap:12px; justify-content:center; padding: 16px 24px 22px; }

/* Modern Flat Design - Employee List */
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

.btn-primary i {
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
    flex-wrap: wrap;
    gap: 15px;
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin: 0;
    flex: 0 0 auto;
}

.card-tools {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
    margin-left: auto;
}

.filter-buttons {
    display: flex;
    gap: 8px;
    background: #F9FAFB;
    padding: 4px;
    border-radius: 8px;
    border: 1px solid #E5E7EB;
}

.filter-btn {
    padding: 8px 16px;
    border: none;
    background: transparent;
    color: #6B7280;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.filter-btn i {
    font-size: 14px;
}

.filter-btn:hover {
    background: #E5E7EB;
    color: #374151;
}

.filter-btn.active {
    background: #2563EB;
    color: white;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.3);
}

.employee-search-input {
    padding: 10px 16px;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    font-size: 14px;
    width: 250px;
    transition: all 0.2s ease;
    background: #FFFFFF;
}

.employee-search-input:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.card-body {
    padding: 0;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.data-table thead {
    background: #F9FAFB;
}

.data-table th {
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #E5E7EB;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #F3F4F6;
    color: #1F2937;
}

.data-table tbody tr {
    transition: all 0.15s ease;
}

.data-table tbody tr:hover {
    background-color: #F9FAFB;
}

.employee-code {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #2563EB;
    background: #EEF2FF;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 13px;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

.employee-info i {
    font-size: 20px;
    color: #9CA3AF;
}

.salary-amount {
    font-weight: 600;
    color: #10B981;
}

.badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    letter-spacing: 0.3px;
}

.badge i {
    font-size: 12px;
}

.badge-success {
    background: #10B981;
    color: white;
}

.badge-secondary {
    background: #6B7280;
    color: white;
}

.badge-warning {
    background: #F59E0B;
    color: white;
}

.badge-danger {
    background: #EF4444;
    color: white;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-icon {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon i {
    font-size: 14px;
}

.btn-view {
    background: #2563EB;
    color: white;
}

.btn-view:hover {
    background: #1d4ed8;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.btn-edit {
    background: #2563EB;
    color: white;
}

.btn-edit:hover {
    background: #1d4ed8;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.btn-delete {
    background: #EF4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.empty-state {
    padding: 60px 40px;
    text-align: center;
}

.empty-state i {
    color: #D1D5DB;
}

.empty-state p {
    color: #9CA3AF;
    font-size: 16px;
    margin-top: 15px;
}

.stat-card {
    padding: 24px;
    border-radius: 12px;
    color: white;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-card .stat-icon {
    font-size: 36px;
    margin-bottom: 12px;
    opacity: 0.95;
}

.stat-card h4 {
    font-size: 13px;
    font-weight: 600;
    margin: 12px 0;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card .stat-value {
    font-size: 32px;
    font-weight: 700;
    margin: 12px 0 0 0;
}

.stat-total {
    background: #2563EB;
}

.stat-active {
    background: #10B981;
}

.stat-inactive {
    background: #6B7280;
}

.stat-resigned {
    background: #F59E0B;
}

.stat-payroll {
    background: #2563EB;
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

.text-center {
    text-align: center;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s ease;
    align-items: center;
    justify-content: center;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.modal-content {
    background-color: #FFFFFF;
    margin: 3% auto;
    border-radius: 12px;
    width: 90%;
    max-width: 800px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 28px;
    border-bottom: 1px solid #E5E7EB;
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    border-radius: 12px 12px 0 0;
}

.modal-title {
    font-size: 20px;
    font-weight: 700;
    color: white;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-title i {
    font-size: 22px;
}

.modal-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 6px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 28px;
    max-height: 500px;
    overflow-y: auto;
}

.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: #F3F4F6;
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #D1D5DB;
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: #9CA3AF;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 28px;
    border-top: 1px solid #E5E7EB;
    background: #F9FAFB;
    border-radius: 0 0 12px 12px;
}

/* Form Styles for Modal */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
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

/* Responsive */
@media (max-width: 768px) {
    .filter-buttons {
        width: 100%;
        justify-content: space-between;
    }
    
    .filter-btn {
        flex: 1;
        justify-content: center;
        font-size: 11px;
        padding: 8px 10px;
    }
    
    .employee-search-input {
        width: 100%;
    }
    
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid .full-width {
        grid-column: span 1;
    }
}

/* Modal Loading Overlay */
.modal-loading-overlay {
    display: none;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(2px);
    z-index: 10;
    border-radius: 12px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
}

.modal-loading-overlay.active {
    display: flex;
}

.modal-loading-overlay .spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #E5E7EB;
    border-top: 4px solid #2563EB;
    border-radius: 50%;
    animation: overlaySpinner 0.8s linear infinite;
}

.modal-loading-overlay .loading-text {
    font-size: 15px;
    font-weight: 600;
    color: #374151;
    letter-spacing: 0.3px;
}

@keyframes overlaySpinner {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
// Auto-hide success message after 5 seconds
if (document.querySelector('.alert-success')) {
    setTimeout(function() {
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            successAlert.style.transition = 'opacity 0.5s ease';
            successAlert.style.opacity = '0';
            setTimeout(function() {
                successAlert.remove();
            }, 500);
        }
    }, 5000);
}

// Search functionality
document.getElementById('searchEmployees').addEventListener('keyup', function() {
    filterTable();
});

// Filter buttons functionality
document.querySelectorAll('.filter-btn').forEach(button => {
    button.addEventListener('click', function() {
        // Remove active class from all buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add active class to clicked button
        this.classList.add('active');
        
        // Filter table
        filterTable();
    });
});
// Tag original row order so we can restore it when search is cleared
(function tagOriginalRowOrder() {
    const rows = document.querySelectorAll('#employeesTable tbody tr');
    rows.forEach((r, i) => r.dataset.originalIndex = i);
})();

function filterTable() {
    let searchValue = document.getElementById('searchEmployees').value.trim().toLowerCase();
    let activeFilter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
    let table = document.getElementById('employeesTable');
    let tbody = table.getElementsByTagName('tbody')[0];
    let rows = Array.from(tbody.getElementsByTagName('tr'));

    // If search is empty: restore original order and show rows by filter
    if (!searchValue) {
        rows.sort((a, b) => (parseInt(a.dataset.originalIndex || 0) - parseInt(b.dataset.originalIndex || 0)));
        rows.forEach(row => {
            let status = row.getAttribute('data-status');
            if (activeFilter === 'all' || status === activeFilter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
            tbody.appendChild(row);
        });
        return;
    }

    const startsWithMatches = [];
    const containsMatches = [];

    rows.forEach(row => {
        const status = row.getAttribute('data-status');
        if (!(activeFilter === 'all' || status === activeFilter)) {
            row.style.display = 'none';
            return;
        }

        const nameElem = row.querySelector('.employee-name');
        const text = nameElem ? nameElem.textContent.toLowerCase() : '';

        const parts = text.split(/\s+/).filter(Boolean);
        const isStart = parts.some(p => p.startsWith(searchValue));

        if (isStart) {
            startsWithMatches.push(row);
        } else if (text.indexOf(searchValue) > -1) {
            containsMatches.push(row);
        } else {
            row.style.display = 'none';
        }
    });

    // Append matches: startsWith first, then contains
    startsWithMatches.forEach(r => { r.style.display = ''; tbody.appendChild(r); });
    containsMatches.forEach(r => { r.style.display = ''; tbody.appendChild(r); });
}

// Store delete context
let pendingDeleteId = null;
let pendingDeleteName = null;

// Delete confirmation - Show Modal
function confirmDelete(employeeId, employeeName) {
    pendingDeleteId = employeeId;
    pendingDeleteName = employeeName;
    document.getElementById('deleteEmployeeNameDisplay').textContent = employeeName;
    document.getElementById('deleteConfirmModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Close Delete Modal
function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    pendingDeleteId = null;
    pendingDeleteName = null;
    // Always reset the delete button so it's ready for the next use
    const deleteBtn = document.querySelector('#deleteConfirmModal .btn-danger');
    if (deleteBtn) {
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = '<i class="fas fa-check"></i> Confirm';
    }
}

// Confirm delete action
function confirmDeleteAction() {
    if (!pendingDeleteId) return;
    
    const employeeId = pendingDeleteId;

    // S5: Disable delete button during operation
    const deleteBtn = document.querySelector('#deleteConfirmModal .btn-danger');
    if (deleteBtn) { deleteBtn.disabled = true; deleteBtn.textContent = 'Deleting...'; }
    
    // Perform AJAX POST to delete endpoint
    fetch('delete_employee.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'employee_id=' + encodeURIComponent(employeeId)
    })
    .then(res => res.json())
    .then(data => {
        closeDeleteModal();
        if (data.success) {
            showNotification(data.message, 'success');
            // Remove row from table
            const row = document.querySelector('tr[data-employee-id="' + employeeId + '"]');
            if (row) row.remove();
        } else {
            showNotification(data.message || 'Failed to delete', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        closeDeleteModal();
        showNotification('An error occurred while deleting.', 'error');
        if (deleteBtn) { deleteBtn.disabled = false; deleteBtn.textContent = 'Delete'; }
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const deleteModal = document.getElementById('deleteConfirmModal');
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}

let editOriginalValues = {};

function showEditFieldError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.add('input-error');
    input.classList.remove('input-success');
    let errEl = input.closest('.form-group').querySelector('.field-error-msg');
    if (!errEl) {
        errEl = document.createElement('div');
        errEl.className = 'field-error-msg';
        errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span></span>';
        const wrapper = input.closest('.input-group');
        const insertAfter = wrapper || input;
        insertAfter.insertAdjacentElement('afterend', errEl);
    }
    errEl.querySelector('span').textContent = message;
    errEl.classList.add('show');
}

function clearEditFieldError(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.remove('input-error');
    const errEl = input.closest('.form-group').querySelector('.field-error-msg');
    if (errEl) errEl.classList.remove('show');
}

function markEditFieldValid(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.remove('input-error');
    input.classList.add('input-success');
    clearEditFieldError(inputId);
}

function checkEditChanges() {
    const code = document.getElementById('edit_employee_code').value.trim();
    const name = document.getElementById('edit_full_name').value.trim();
    const position = document.getElementById('edit_position').value.trim();
    const classification = document.getElementById('edit_classification').value;
    const salary = document.getElementById('edit_basic_salary').value;
    const hireDate = document.getElementById('edit_hire_date').value;
    const status = document.getElementById('edit_status').value;
    const changed = (code !== editOriginalValues.code ||
                     name !== editOriginalValues.name ||
                     position !== editOriginalValues.position ||
                     classification !== editOriginalValues.classification ||
                     salary !== editOriginalValues.salary ||
                     hireDate !== editOriginalValues.hireDate ||
                     status !== editOriginalValues.status);
    const btn = document.getElementById('btnUpdateEmployee');
    if (btn) btn.disabled = !changed;
    return changed;
}

['edit_employee_code', 'edit_full_name', 'edit_position', 'edit_classification', 'edit_basic_salary', 'edit_hire_date', 'edit_status'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', checkEditChanges);
        el.addEventListener('change', checkEditChanges);
    }
});

// Real-time validation for edit modal fields
document.getElementById('edit_employee_code').addEventListener('input', function() {
    const val = this.value.trim();
    if (val.length === 0) { showEditFieldError('edit_employee_code', 'Employee code is required'); }
    else { markEditFieldValid('edit_employee_code'); }
    checkEditChanges();
});

document.getElementById('edit_full_name').addEventListener('input', function() {
    const val = this.value.trim();
    if (val.length === 0) { showEditFieldError('edit_full_name', 'Full name is required'); }
    else if (val.length < 2) { showEditFieldError('edit_full_name', 'Full name must be at least 2 characters'); }
    else { markEditFieldValid('edit_full_name'); }
    checkEditChanges();
});

document.getElementById('edit_basic_salary').addEventListener('input', function() {
    const val = this.value;
    if (val === '' || parseFloat(val) <= 0) { showEditFieldError('edit_basic_salary', 'Please enter a valid positive salary amount'); }
    else { markEditFieldValid('edit_basic_salary'); }
    checkEditChanges();
});

// Open Edit Modal
function openEditModal(id, code, name, position, classification, salary, hireDate, status) {
    editOriginalValues = { code: code, name: name, position: position, classification: classification, salary: String(salary), hireDate: hireDate, status: status };
    document.getElementById('edit_employee_id').value = id;
    document.getElementById('edit_employee_code').value = code;
    document.getElementById('edit_full_name').value = name;
    document.getElementById('edit_position').value = position;
    document.getElementById('edit_classification').value = classification;
    document.getElementById('edit_basic_salary').value = salary;
    document.getElementById('edit_hire_date').value = hireDate;
    document.getElementById('edit_status').value = status;
    
    // Clear any previous field errors and reset button state
    ['edit_employee_code', 'edit_full_name', 'edit_position', 'edit_classification', 'edit_basic_salary', 'edit_hire_date'].forEach(function(fid) {
        clearEditFieldError(fid);
        const inp = document.getElementById(fid);
        if (inp) inp.classList.remove('input-success', 'input-error');
    });
    document.getElementById('btnUpdateEmployee').disabled = true;
    
    document.getElementById('editModal').style.display = 'block';
    document.body.style.overflow = 'hidden';

    // Re-evaluate button state in case displayed value differs from stored original
    // (e.g. DB has 'N/A' classification which isn't a valid option so the select
    //  snaps to the first option, creating an immediate detectable change)
    checkEditChanges();
}

// Close Edit Modal
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
    document.getElementById('editEmployeeForm').reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeEditModal();
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditModal();
    }
});

// Update Employee via AJAX
function updateEmployee(event) {
    event.preventDefault();
    
    // Client-side validation
    let hasError = false;
    const code = document.getElementById('edit_employee_code').value.trim();
    const name = document.getElementById('edit_full_name').value.trim();
    const salary = document.getElementById('edit_basic_salary').value;
    
    if (code === '') { showEditFieldError('edit_employee_code', 'Employee code is required'); hasError = true; }
    if (name === '') { showEditFieldError('edit_full_name', 'Full name is required'); hasError = true; }
    else if (name.length < 2) { showEditFieldError('edit_full_name', 'Full name must be at least 2 characters'); hasError = true; }
    if (salary === '' || parseFloat(salary) <= 0) { showEditFieldError('edit_basic_salary', 'Valid salary is required'); hasError = true; }
    if (hasError) return;
    
    const formData = new FormData(document.getElementById('editEmployeeForm'));
    formData.append('action', 'update_employee');
    
    // Show loading state
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    submitBtn.disabled = true;
    
    // Show modal loading overlay
    const overlay = document.getElementById('editModalOverlay');
    overlay.classList.add('active');
    
    fetch('employee_list.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Hide overlay
        overlay.classList.remove('active');
        
        if (data.success) {
            // Capture updated values BEFORE closeEditModal() resets the form
            const employeeId      = document.getElementById('edit_employee_id').value;
            const newCode         = document.getElementById('edit_employee_code').value.trim();
            const newName         = document.getElementById('edit_full_name').value.trim();
            const newPosition     = document.getElementById('edit_position').value.trim();
            const newClassification = document.getElementById('edit_classification').value;
            const newSalary       = parseFloat(document.getElementById('edit_basic_salary').value);
            const newHireDate     = document.getElementById('edit_hire_date').value;
            const newStatus       = document.getElementById('edit_status').value;

            // Restore button to original state before closing
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = true; // keep disabled (no changes after close)

            // Show success message
            showNotification(data.message, 'success');

            // Close modal (resets the form)
            closeEditModal();

            // Update the table row directly without a page reload
            const row = document.querySelector('tr[data-employee-id="' + employeeId + '"]');
            if (row) {
                const cells = row.querySelectorAll('td');

                // Employee Code
                const codeEl = cells[0].querySelector('.employee-code');
                if (codeEl) codeEl.textContent = newCode;

                // Full Name
                const nameEl = cells[1].querySelector('.employee-name');
                if (nameEl) nameEl.textContent = newName;

                // Position
                cells[2].textContent = newPosition || 'N/A';

                // Employee Classification
                cells[3].textContent = newClassification || 'N/A';

                // Monthly Salary
                const salaryEl = cells[4].querySelector('.salary-amount');
                if (salaryEl) salaryEl.textContent = '\u20b1' + newSalary.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // Hire Date
                if (newHireDate) {
                    const d = new Date(newHireDate + 'T00:00:00');
                    cells[5].textContent = d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                } else {
                    cells[5].textContent = 'N/A';
                }

                // Status Badge
                const statusMap = {
                    active:     { cls: 'badge-success',   icon: 'fa-check-circle' },
                    inactive:   { cls: 'badge-secondary', icon: 'fa-pause-circle' },
                    resigned:   { cls: 'badge-warning',   icon: 'fa-user-times' },
                    terminated: { cls: 'badge-danger',    icon: 'fa-ban' }
                };
                const statusInfo = statusMap[newStatus] || { cls: 'badge-secondary', icon: 'fa-question-circle' };
                const badgeEl = cells[6].querySelector('.badge');
                if (badgeEl) {
                    badgeEl.className = 'badge ' + statusInfo.cls;
                    badgeEl.innerHTML = '<i class="fas ' + statusInfo.icon + '"></i> ' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                }
                row.setAttribute('data-status', newStatus);

                // Update the edit button onclick so re-opening shows fresh values
                const editBtn = row.querySelector('.btn-edit');
                if (editBtn) {
                    const safeCode  = newCode.replace(/'/g, "\\'");
                    const safeName  = newName.replace(/'/g, "\\'");
                    const safePos   = newPosition.replace(/'/g, "\\'");
                    const safeClassif = newClassification.replace(/'/g, "\\'");
                    editBtn.setAttribute('onclick',
                        `openEditModal(${employeeId}, '${safeCode}', '${safeName}', '${safePos}', '${safeClassif}', ${newSalary}, '${newHireDate}', '${newStatus}')`
                    );
                }
            }
        } else {
            // Show error message
            showNotification(data.message, 'error');
            
            // Restore button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        // Hide overlay
        overlay.classList.remove('active');
        
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
        
        // Restore button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Show notification
function showNotification(message, type) {
    // Remove existing notification if any
    const existingNotification = document.querySelector('.notification-toast');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification-toast notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Add to body
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}
</script>

<style>
/* Notification Toast */
.notification-toast {
    position: fixed;
    top: 80px;
    right: -400px;
    background: white;
    padding: 16px 24px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 10000;
    transition: right 0.3s ease;
    min-width: 300px;
    max-width: 400px;
}

.notification-toast.show {
    right: 24px;
}

.notification-toast i {
    font-size: 20px;
}

.notification-success {
    border-left: 4px solid #10B981;
}

.notification-success i {
    color: #10B981;
}

.notification-error {
    border-left: 4px solid #EF4444;
}

.notification-error i {
    color: #EF4444;
}

.notification-toast span {
    color: #374151;
    font-weight: 500;
}

/* Delete Confirmation Modal Styles */
.delete-modal-content {
    background-color: #FFFFFF;
    margin: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.3s ease;
    overflow: hidden;
}

.delete-modal-body {
    padding: 40px 32px;
    text-align: center;
    background: #FFFFFF;
}

.delete-warning-icon {
    width: 80px;
    height: 80px;
    background: #FEE2E2;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    font-size: 40px;
    color: #EF4444;
}

.delete-modal-title {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 16px 0;
}

.delete-modal-message {
    font-size: 15px;
    color: #6B7280;
    margin: 0 0 12px 0;
    line-height: 1.5;
}

.employee-name-highlight {
    font-weight: 600;
    color: #111827;
    display: inline-block;
    max-width: 100%;
    word-break: break-word;
}

.delete-modal-warning {
    font-size: 13px;
    color: #9CA3AF;
    margin: 0;
    font-style: italic;
}

.delete-modal-footer {
    display: flex;
    justify-content: center;
    gap: 12px;
    padding: 20px 32px;
    border-top: 1px solid #E5E7EB;
    background: #F9FAFB;
}

.btn-secondary {
    background: #FFFFFF;
    color: #374151;
    border: 1px solid #D1D5DB;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.btn-secondary:hover {
    background: #F9FAFB;
    border-color: #9CA3AF;
}

.btn-danger {
    background: #EF4444;
    color: white;
    box-shadow: 0 1px 3px rgba(239, 68, 68, 0.2);
}

.btn-danger:hover {
    background: #DC2626;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}
</style>

<?php
// Include footer
require_once 'include/footer.php';
?>
