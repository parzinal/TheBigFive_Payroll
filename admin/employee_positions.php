<?php
/**
 * Employee Positions Page
 * View employees grouped by their positions
 */

// Set page title
$page_title = 'Employee Positions';

// Include header
require_once 'include/header.php';

// Include database connection
require_once '../config/database.php';

// Include sidebar
require_once 'include/sidebar.php';

// Fetch employees grouped by position
try {
    $pdo = getDBConnection();
    
    // Get all positions with employee counts
    $stmt = $pdo->query("
        SELECT 
            position,
            COUNT(*) as employee_count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            AVG(CASE WHEN status = 'active' THEN basic_monthly_salary ELSE NULL END) as avg_salary
        FROM employees 
        WHERE position IS NOT NULL AND position != ''
        GROUP BY position 
        ORDER BY employee_count DESC
    ");
    $positions = $stmt->fetchAll();
    
    // Get employees without position
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE position IS NULL OR position = ''");
    $unassignedCount = $stmt->fetch()['count'];
    
    // Get total stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees");
    $totalEmployees = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT position) as total FROM employees WHERE position IS NOT NULL AND position != ''");
    $totalPositions = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "Error fetching positions: " . $e->getMessage();
    $positions = [];
    $unassignedCount = 0;
    $totalEmployees = 0;
    $totalPositions = 0;
}
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-left">
                <div class="page-title-row">
                    <div class="page-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="page-title-text">
                        <h1>Employee Positions</h1>
                        <div class="page-breadcrumb">
                            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                            <span class="page-breadcrumb-separator">/</span>
                            <span>Positions</span>
                        </div>
                    </div>
                </div>
                <p class="page-subtitle">View employees grouped by their positions</p>
            </div>
            <div class="page-header-right">
                <div class="page-header-actions">
                    <button class="btn-header-secondary" onclick="window.location.href='employee_list.php'">
                        <i class="fas fa-list"></i>
                        View All Employees
                    </button>
                    <button class="btn-header-primary" onclick="openCreatePositionModal()">
                        <i class="fas fa-plus"></i>
                        Create Position
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-briefcase"></i>
            </div>
            <div class="stat-details">
                <p class="stat-label">Total Positions</p>
                <h3 class="stat-value"><?php echo number_format($totalPositions); ?></h3>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <p class="stat-label">Total Employees</p>
                <h3 class="stat-value"><?php echo number_format($totalEmployees); ?></h3>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="stat-details">
                <p class="stat-label">Unassigned Position</p>
                <h3 class="stat-value"><?php echo number_format($unassignedCount); ?></h3>
            </div>
        </div>
    </div>

    <!-- Positions List -->
    <div class="positions-grid">
        <?php if (count($positions) > 0): ?>
            <?php foreach ($positions as $position): ?>
                <div class="position-card">
                    <div class="position-header">
                        <div class="position-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="position-info">
                            <h3 class="position-name"><?php echo htmlspecialchars($position['position']); ?></h3>
                            <p class="position-meta">
                                <span class="badge badge-primary">
                                    <?php echo number_format($position['employee_count']); ?> <?php echo $position['employee_count'] == 1 ? 'Employee' : 'Employees'; ?>
                                </span>
                                <span class="badge badge-success">
                                    <?php echo number_format($position['active_count']); ?> Active
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="position-details">
                        <div class="detail-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <div class="detail-content">
                                <span class="detail-label">Average Salary</span>
                                <span class="detail-value">₱<?php echo number_format($position['avg_salary'] ?? 0, 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <i class="fas fa-chart-line"></i>
                            <div class="detail-content">
                                <span class="detail-label">Active Rate</span>
                                <span class="detail-value">
                                    <?php echo $position['employee_count'] > 0 ? number_format(($position['active_count'] / $position['employee_count']) * 100, 1) : 0; ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="position-actions">
                        <button class="btn-view-employees" onclick="viewEmployeesByPosition('<?php echo htmlspecialchars($position['position']); ?>')">
                            <i class="fas fa-eye"></i> View Employees
                        </button>
                        <button class="btn-manage-employees" onclick="manageEmployeesInPosition('<?php echo htmlspecialchars($position['position']); ?>')">
                            <i class="fas fa-users-cog"></i> Manage
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Always show unassigned position card -->
            <div class="position-card position-unassigned">
                <div class="position-header">
                    <div class="position-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="position-info">
                        <h3 class="position-name">Unassigned Position</h3>
                        <p class="position-meta">
                            <span class="badge badge-secondary">
                                <?php echo number_format($unassignedCount); ?> <?php echo $unassignedCount == 1 ? 'Employee' : 'Employees'; ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="position-actions">
                    <button class="btn-view-employees" onclick="viewEmployeesByPosition('')">
                        <i class="fas fa-eye"></i> View Employees
                    </button>
                    <?php if ($unassignedCount > 0): ?>
                    <button class="btn-assign-position" onclick="openAssignModal()">
                        <i class="fas fa-user-plus"></i> Assign Position
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state-full">
                <i class="fas fa-briefcase"></i>
                <h3>No Positions Found</h3>
                <p>No employees have been assigned positions yet.</p>
                <button class="btn btn-primary" onclick="window.location.href='employee_list.php'">
                    <i class="fas fa-users"></i> View All Employees
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for viewing employees by position -->
<div id="employeesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Employees</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="modal-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
        </div>
    </div>
</div>

<!-- Modal for assigning positions to unassigned employees -->
<div id="assignModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Assign Position to Employees</h3>
            <button class="modal-close" onclick="closeAssignModal()">&times;</button>
        </div>
        <div class="modal-body" id="assignModalBody">
            <div class="modal-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading unassigned employees...
            </div>
        </div>
    </div>
</div>

<!-- Modal for creating new position -->
<div id="createPositionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Create New Position</h3>
            <button class="modal-close" onclick="closeCreatePositionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="createPositionForm" onsubmit="saveNewPosition(event)">
                <div class="form-group">
                    <label for="positionName">Position Name <span class="required">*</span></label>
                    <input type="text" id="positionName" name="position_name" class="form-control" 
                           placeholder="e.g., Senior Developer, HR Manager, etc." required>
                    <small class="form-text">Enter a unique position name</small>
                </div>
                
                <div class="form-group">
                    <label for="positionDescription">Description (Optional)</label>
                    <textarea id="positionDescription" name="description" class="form-control" 
                              rows="3" placeholder="Brief description of this position..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeCreatePositionModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Position
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for managing employees in a position -->
<div id="manageEmployeesModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="manageEmployeesTitle"><i class="fas fa-users-cog"></i> Manage Employees</h3>
            <button class="modal-close" onclick="closeManageEmployeesModal()">&times;</button>
        </div>
        <div class="modal-body" id="manageEmployeesBody">
            <div class="modal-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
        </div>
    </div>
</div>

<!-- Modal for adding employees to a position -->
<div id="addEmployeeToPositionModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="addEmployeeTitle"><i class="fas fa-user-plus"></i> Add Employees to Position</h3>
            <button class="modal-close" onclick="closeAddEmployeeToPositionModal()">&times;</button>
        </div>
        <div class="modal-body" id="addEmployeeToPositionBody">
            <div class="modal-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading available employees...
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification Container -->
<div id="toastContainer"></div>

<!-- Custom Confirmation Modal -->
<div id="confirmModal" class="modal">
    <div class="modal-content modal-confirm">
        <div class="modal-header confirm-header">
            <h3 id="confirmTitle"><i class="fas fa-exclamation-triangle"></i> Confirm Action</h3>
        </div>
        <div class="modal-body confirm-body">
            <p id="confirmMessage"></p>
        </div>
        <div class="modal-footer confirm-footer">
            <button class="btn btn-secondary" onclick="closeConfirmModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn btn-danger" id="confirmButton">
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

<style>
/* Modern Flat Design - Positions */
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

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    border: 1px solid #E5E7EB;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-primary .stat-icon {
    background: #2563EB;
}

.stat-success .stat-icon {
    background: #10B981;
}

.stat-warning .stat-icon {
    background: #F59E0B;
}

.stat-details {
    flex: 1;
}

.stat-label {
    font-size: 14px;
    color: #6B7280;
    margin: 0 0 8px 0;
    font-weight: 500;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

/* Positions Grid */
.positions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.position-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #E5E7EB;
    overflow: hidden;
    transition: all 0.2s ease;
}

.position-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    border-color: #2563EB;
}

.position-unassigned {
    border-color: #F59E0B;
}

.position-unassigned:hover {
    border-color: #D97706;
}

.position-header {
    padding: 24px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    border-bottom: 1px solid #F3F4F6;
}

.position-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background: #EEF2FF;
    color: #2563EB;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.position-unassigned .position-icon {
    background: #FEF3C7;
    color: #F59E0B;
}

.position-info {
    flex: 1;
}

.position-name {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin: 0 0 8px 0;
}

.position-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0;
}

.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.badge-primary {
    background: #2563EB;
    color: white;
}

.badge-success {
    background: #10B981;
    color: white;
}

.badge-secondary {
    background: #6B7280;
    color: white;
}

.position-details {
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    background: #F9FAFB;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.detail-item i {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: white;
    color: #2563EB;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.detail-content {
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 12px;
    color: #6B7280;
    font-weight: 500;
}

.detail-value {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.position-actions {
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-view-employees {
    width: 100%;
    padding: 12px;
    background: #2563EB;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-view-employees:hover {
    background: #1d4ed8;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-assign-position {
    width: 100%;
    padding: 12px;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-assign-position:hover {
    background: #059669;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-manage-employees {
    width: 100%;
    padding: 12px;
    background: #2563EB;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-manage-employees:hover {
    background: #1d4ed8;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Empty State */
.empty-state-full {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 12px;
    border: 1px solid #E5E7EB;
}

.empty-state-full i {
    font-size: 64px;
    color: #D1D5DB;
    margin-bottom: 20px;
}

.empty-state-full h3 {
    font-size: 20px;
    font-weight: 600;
    color: #111827;
    margin: 0 0 10px 0;
}

.empty-state-full p {
    font-size: 14px;
    color: #6B7280;
    margin: 0 0 24px 0;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s ease;
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 800px;
    width: 90%;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease;
}

.modal-large {
    max-width: 1000px;
}

@keyframes slideUp {
    from {
        transform: translateY(50px);
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
    padding: 24px;
    border-bottom: 1px solid #E5E7EB;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #111827;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #6B7280;
    cursor: pointer;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: #F3F4F6;
    color: #111827;
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
}

.modal-loading {
    text-align: center;
    padding: 40px;
    color: #6B7280;
    font-size: 16px;
}

.modal-loading i {
    font-size: 32px;
    margin-bottom: 10px;
}

.employee-list-modal {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.employee-item-modal {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    background: #F9FAFB;
    border-radius: 8px;
    border: 1px solid #E5E7EB;
}

.employee-item-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.employee-item-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: #2563EB;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.employee-item-details h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
    font-weight: 600;
    color: #111827;
}

.employee-item-details p {
    margin: 0;
    font-size: 12px;
    color: #6B7280;
}

/* Assignment Form Styles */
.assign-form-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.assign-employee-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    background: #F9FAFB;
    border-radius: 8px;
    border: 1px solid #E5E7EB;
    gap: 15px;
}

.assign-employee-info {
    flex: 1;
    min-width: 0;
}

.assign-employee-info h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
    font-weight: 600;
    color: #111827;
}

.assign-employee-info p {
    margin: 0;
    font-size: 12px;
    color: #6B7280;
}

.assign-position-select {
    flex: 0 0 250px;
    padding: 10px 14px;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 14px;
    color: #111827;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.assign-position-select:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.assign-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #E5E7EB;
    margin-top: 20px;
}

.btn-save-assignments {
    padding: 12px 24px;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-save-assignments:hover {
    background: #059669;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-save-assignments:disabled {
    background: #9CA3AF;
    cursor: not-allowed;
    box-shadow: none;
}

/* Manage Employees Styles */
.manage-employees-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.manage-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 12px;
    border-bottom: 2px solid #E5E7EB;
}

.manage-section-title {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 8px;
}

.manage-employee-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    background: #F9FAFB;
    border-radius: 8px;
    border: 1px solid #E5E7EB;
    transition: all 0.2s ease;
}

.manage-employee-item:hover {
    border-color: #D1D5DB;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.btn-remove-employee {
    padding: 8px 16px;
    background: #EF4444;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.btn-remove-employee:hover {
    background: #DC2626;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.btn-add-employee-to-position {
    padding: 10px 20px;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-add-employee-to-position:hover {
    background: #059669;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.add-employee-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    background: #F9FAFB;
    border-radius: 8px;
    border: 1px solid #E5E7EB;
}

.btn-add-this-employee {
    padding: 8px 16px;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.btn-add-this-employee:hover {
    background: #059669;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-add-this-employee:disabled {
    background: #9CA3AF;
    cursor: not-allowed;
}

/* Create Position Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.form-group .required {
    color: #EF4444;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 14px;
    color: #111827;
    background: white;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-control::placeholder {
    color: #9CA3AF;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.form-text {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: #6B7280;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
    padding-top: 20px;
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

.alert-danger {
    background-color: #FEE2E2;
    color: #991B1B;
    border-color: #FECACA;
}

/* Toast Notifications */
#toastContainer {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 400px;
}

.toast {
    background: white;
    padding: 16px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideInRight 0.3s ease;
    border-left: 4px solid;
    min-width: 300px;
}

@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}

.toast.hiding {
    animation: slideOutRight 0.3s ease forwards;
}

.toast-success {
    border-color: #10B981;
}

.toast-error {
    border-color: #EF4444;
}

.toast-warning {
    border-color: #F59E0B;
}

.toast-info {
    border-color: #3B82F6;
}

.toast-icon {
    font-size: 20px;
    flex-shrink: 0;
}

.toast-success .toast-icon {
    color: #10B981;
}

.toast-error .toast-icon {
    color: #EF4444;
}

.toast-warning .toast-icon {
    color: #F59E0B;
}

.toast-info .toast-icon {
    color: #3B82F6;
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 600;
    font-size: 14px;
    color: #111827;
    margin: 0 0 4px 0;
}

.toast-message {
    font-size: 13px;
    color: #6B7280;
    margin: 0;
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    color: #9CA3AF;
    cursor: pointer;
    font-size: 20px;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.toast-close:hover {
    background: #F3F4F6;
    color: #111827;
}

/* Confirmation Modal */
.modal-confirm {
    max-width: 500px;
}

.confirm-header {
    background: #FEF3C7;
    border-bottom: 1px solid #FCD34D;
}

.confirm-header h3 {
    color: #92400E;
    display: flex;
    align-items: center;
    gap: 10px;
}

.confirm-header i {
    color: #F59E0B;
}

.confirm-body {
    padding: 30px 24px;
}

.confirm-body p {
    margin: 0;
    font-size: 15px;
    color: #374151;
    line-height: 1.6;
    white-space: pre-line;
}

.confirm-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 24px;
    background: #F9FAFB;
    border-top: 1px solid #E5E7EB;
    border-radius: 0 0 12px 12px;
}

.btn-danger {
    background: #EF4444;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background: #DC2626;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .positions-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    #toastContainer {
        left: 20px;
        right: 20px;
        max-width: none;
    }
    
    .toast {
        min-width: auto;
    }
}
</style>

<script>
// Toast Notification System
function showToast(message, type = 'info', title = '') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const titles = {
        success: title || 'Success',
        error: title || 'Error',
        warning: title || 'Warning',
        info: title || 'Info'
    };
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas ${icons[type]}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${titles[type]}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="closeToast(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        removeToast(toast);
    }, 5000);
}

function closeToast(button) {
    const toast = button.closest('.toast');
    removeToast(toast);
}

function removeToast(toast) {
    if (!toast) return;
    toast.classList.add('hiding');
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 300);
}

// Custom Confirmation Modal
let confirmCallback = null;

function showConfirm(message, onConfirm, title = 'Confirm Action') {
    console.log('showConfirm called with message:', message);
    const modal = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmTitle');
    const messageEl = document.getElementById('confirmMessage');
    const confirmBtn = document.getElementById('confirmButton');
    
    console.log('Modal elements:', { modal, titleEl, messageEl, confirmBtn });
    
    titleEl.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${title}`;
    messageEl.textContent = message;
    
    // Store the callback - this must happen BEFORE showing modal
    confirmCallback = onConfirm;
    console.log('Callback stored:', typeof confirmCallback);
    
    modal.classList.add('active');
    console.log('Modal shown');
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('active');
    confirmCallback = null;
}

// Initialize confirm button handler once on page load
document.addEventListener('DOMContentLoaded', function() {
    const confirmBtn = document.getElementById('confirmButton');
    if (confirmBtn) {
        console.log('Setting up confirm button handler');
        confirmBtn.addEventListener('click', function() {
            console.log('Confirm button clicked!');
            console.log('Callback exists?', typeof confirmCallback);
            
            // Save callback BEFORE closing modal (which sets it to null)
            const callback = confirmCallback;
            
            // Close modal and reset
            closeConfirmModal();
            
            // Execute the saved callback
            if (callback && typeof callback === 'function') {
                console.log('Executing callback');
                callback();
            } else {
                console.log('No callback to execute');
            }
        });
    }
});

function viewEmployeesByPosition(position) {
    const modal = document.getElementById('employeesModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    // Show modal
    modal.classList.add('active');
    
    // Update title
    modalTitle.textContent = position ? `Employees - ${position}` : 'Employees - Unassigned Position';
    
    // Show loading
    modalBody.innerHTML = '<div class="modal-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    // Fetch employees
    fetch(`get_employees_by_position.php?position=${encodeURIComponent(position)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayEmployees(data.employees);
            } else {
                modalBody.innerHTML = `<div class="empty-state-full"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>${data.message}</p></div>`;
            }
        })
        .catch(error => {
            modalBody.innerHTML = '<div class="empty-state-full"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>Failed to load employees.</p></div>';
        });
}

function displayEmployees(employees) {
    const modalBody = document.getElementById('modalBody');
    
    if (employees.length === 0) {
        modalBody.innerHTML = '<div class="empty-state-full"><i class="fas fa-users"></i><h3>No Employees</h3><p>No employees found for this position.</p></div>';
        return;
    }
    
    let html = '<div class="employee-list-modal">';
    employees.forEach(emp => {
        const statusClass = emp.status === 'active' ? 'badge-success' : 'badge-secondary';
        html += `
            <div class="employee-item-modal">
                <div class="employee-item-info">
                    <div class="employee-item-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="employee-item-details">
                        <h4>${emp.full_name}</h4>
                        <p>${emp.employee_code} • ${emp.department || 'N/A'} • ₱${parseFloat(emp.basic_monthly_salary).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                    </div>
                </div>
                <span class="badge ${statusClass}">${emp.status.charAt(0).toUpperCase() + emp.status.slice(1)}</span>
            </div>
        `;
    });
    html += '</div>';
    
    modalBody.innerHTML = html;
}

function closeModal() {
    const modal = document.getElementById('employeesModal');
    modal.classList.remove('active');
}

// Assign Position Functions
function openAssignModal() {
    const modal = document.getElementById('assignModal');
    const modalBody = document.getElementById('assignModalBody');
    
    // Show modal
    modal.classList.add('active');
    
    // Show loading
    modalBody.innerHTML = '<div class="modal-loading"><i class="fas fa-spinner fa-spin"></i> Loading unassigned employees...</div>';
    
    // Fetch unassigned employees and all positions
    Promise.all([
        fetch('get_employees_by_position.php?position=').then(r => r.json()),
        fetch('get_all_positions.php').then(r => r.json())
    ])
    .then(([employeesData, positionsData]) => {
        if (employeesData.success && positionsData.success) {
            displayAssignForm(employeesData.employees, positionsData.positions);
        } else {
            modalBody.innerHTML = '<div class="empty-state-full"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>Failed to load data.</p></div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalBody.innerHTML = '<div class="empty-state-full"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>Failed to load employees and positions.</p></div>';
    });
}

function displayAssignForm(employees, positions) {
    const modalBody = document.getElementById('assignModalBody');
    
    if (employees.length === 0) {
        modalBody.innerHTML = '<div class="empty-state-full"><i class="fas fa-check-circle"></i><h3>All Set!</h3><p>All employees have been assigned positions.</p></div>';
        return;
    }
    
    let html = '<div class="assign-form-container">';
    
    employees.forEach(emp => {
        html += `
            <div class="assign-employee-item">
                <div class="assign-employee-info">
                    <h4>${emp.full_name}</h4>
                    <p>${emp.employee_code} • ${emp.department || 'No Department'}</p>
                </div>
                <select class="assign-position-select" data-employee-id="${emp.id}">
                    <option value="">-- Select Position --</option>
                    ${positions.map(pos => `<option value="${pos.position}">${pos.position}</option>`).join('')}
                </select>
            </div>
        `;
    });
    
    html += `
        <div class="assign-actions">
            <button class="btn btn-secondary" onclick="closeAssignModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn-save-assignments" onclick="saveAssignments()">
                <i class="fas fa-save"></i> Save Assignments
            </button>
        </div>
    </div>`;
    
    modalBody.innerHTML = html;
}

function closeAssignModal() {
    const modal = document.getElementById('assignModal');
    modal.classList.remove('active');
}

function saveAssignments() {
    const selects = document.querySelectorAll('.assign-position-select');
    const assignments = [];
    
    selects.forEach(select => {
        if (select.value) {
            assignments.push({
                employee_id: select.dataset.employeeId,
                position: select.value
            });
        }
    });
    
    if (assignments.length === 0) {
        showToast('Please select at least one position to assign.', 'warning');
        return;
    }
    
    // Disable button
    const saveBtn = document.querySelector('.btn-save-assignments');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    // Save assignments
    fetch('save_position_assignments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ assignments })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Successfully assigned positions to ${data.count} employee(s).`, 'success');
            closeAssignModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Assignments';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save assignments. Please try again.', 'error');
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Assignments';
    });
}

// Create Position Functions
function openCreatePositionModal() {
    const modal = document.getElementById('createPositionModal');
    modal.classList.add('active');
    document.getElementById('createPositionForm').reset();
}

function closeCreatePositionModal() {
    const modal = document.getElementById('createPositionModal');
    modal.classList.remove('active');
}

function saveNewPosition(event) {
    event.preventDefault();
    
    const form = event.target;
    const positionName = form.position_name.value.trim();
    const description = form.description.value.trim();
    
    if (!positionName) {
        showToast('Please enter a position name.', 'warning');
        return;
    }
    
    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    
    // Save new position
    fetch('create_new_position.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            position_name: positionName,
            description: description
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Position created successfully!', 'success');
            closeCreatePositionModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to create position. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    });
}

// Manage Employees Functions
let currentManagingPosition = '';

function manageEmployeesInPosition(position) {
    currentManagingPosition = position;
    const modal = document.getElementById('manageEmployeesModal');
    const modalTitle = document.getElementById('manageEmployeesTitle');
    const modalBody = document.getElementById('manageEmployeesBody');
    
    // Show modal
    modal.classList.add('active');
    
    // Update title
    modalTitle.innerHTML = `<i class="fas fa-users-cog"></i> Manage Employees - ${position}`;
    
    // Show loading
    modalBody.innerHTML = '<div class="modal-loading"><i class="fas fa-spinner fa-spin"></i> Loading employees...</div>';
    
    // Fetch employees for this position
    fetch(`get_employees_by_position.php?position=${encodeURIComponent(position)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayManageEmployees(data.employees, position);
            } else {
                modalBody.innerHTML = `<div class="empty-state-full"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>${data.message}</p></div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="empty-state-full"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>Failed to load employees.</p></div>';
        });
}

function displayManageEmployees(employees, position) {
    const modalBody = document.getElementById('manageEmployeesBody');
    
    let html = '<div class="manage-employees-container">';
    
    // Section header with add button
    html += `
        <div class="manage-section-header">
            <div class="manage-section-title">
                <i class="fas fa-users"></i>
                <span>${employees.length} Employee(s) in this position</span>
            </div>
            <button class="btn-add-employee-to-position" onclick="openAddEmployeeToPosition('${position}')">
                <i class="fas fa-user-plus"></i> Add Employee
            </button>
        </div>
    `;
    
    // List of employees
    if (employees.length === 0) {
        html += '<div class="empty-state-full"><i class="fas fa-users"></i><h3>No Employees</h3><p>No employees in this position yet. Click "Add Employee" to assign employees.</p></div>';
    } else {
        html += '<div class="employee-list-modal">';
        employees.forEach(emp => {
            const statusClass = emp.status === 'active' ? 'badge-success' : 'badge-secondary';
            const safeName = emp.full_name.replace(/'/g, "\\'");
            const safePosition = position.replace(/'/g, "\\'");
            html += `
                <div class="manage-employee-item">
                    <div class="employee-item-info">
                        <div class="employee-item-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="employee-item-details">
                            <h4>${emp.full_name}</h4>
                            <p>${emp.employee_code} • ${emp.department || 'N/A'} • ₱${parseFloat(emp.basic_monthly_salary).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="badge ${statusClass}">${emp.status.charAt(0).toUpperCase() + emp.status.slice(1)}</span>
                        <button class="btn-remove-employee" 
                                data-employee-id="${emp.id}" 
                                data-employee-name="${safeName}" 
                                data-position="${safePosition}">
                            <i class="fas fa-minus-circle"></i> Remove
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
    }
    
    html += '</div>';
    modalBody.innerHTML = html;
    
    // Attach event listeners to remove buttons
    const removeButtons = modalBody.querySelectorAll('.btn-remove-employee');
    console.log('Found remove buttons:', removeButtons.length);
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const employeeId = this.getAttribute('data-employee-id');
            const employeeName = this.getAttribute('data-employee-name');
            const position = this.getAttribute('data-position');
            console.log('Remove button clicked:', { employeeId, employeeName, position });
            removeEmployeeFromPosition(employeeId, employeeName, position);
        });
    });
}

function closeManageEmployeesModal() {
    const modal = document.getElementById('manageEmployeesModal');
    modal.classList.remove('active');
    currentManagingPosition = '';
}

function removeEmployeeFromPosition(employeeId, employeeName, position) {
    console.log('Remove called:', { employeeId, employeeName, position });
    
    showConfirm(
        `Are you sure you want to remove ${employeeName} from ${position}?\n\nThis will set their position to unassigned.`,
        () => {
            console.log('Confirm callback executed');
            // Update employee position to null
            fetch('update_employee_position.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    employee_id: employeeId,
                    position: null
                })
            })
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                if (data.success) {
                    showToast(`${employeeName} has been removed from ${position}.`, 'success');
                    // Refresh the manage modal
                    manageEmployeesInPosition(position);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to remove employee from position.', 'error');
            });
        }
    );
}

function openAddEmployeeToPosition(position) {
    const modal = document.getElementById('addEmployeeToPositionModal');
    const modalTitle = document.getElementById('addEmployeeTitle');
    const modalBody = document.getElementById('addEmployeeToPositionBody');
    
    // Show modal
    modal.classList.add('active');
    
    // Update title
    modalTitle.innerHTML = `<i class="fas fa-user-plus"></i> Add Employees to ${position}`;
    
    // Show loading
    modalBody.innerHTML = '<div class="modal-loading"><i class="fas fa-spinner fa-spin"></i> Loading available employees...</div>';
    
    // Fetch all employees
    fetch('get_all_employees.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Filter out employees already in this position
                const availableEmployees = data.employees.filter(emp => emp.position !== position);
                displayAddEmployeeForm(availableEmployees, position);
            } else {
                modalBody.innerHTML = `<div class="empty-state-full"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>${data.message}</p></div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="empty-state-full"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>Failed to load employees.</p></div>';
        });
}

function displayAddEmployeeForm(employees, position) {
    const modalBody = document.getElementById('addEmployeeToPositionBody');
    
    if (employees.length === 0) {
        modalBody.innerHTML = '<div class="empty-state-full"><i class="fas fa-check-circle"></i><h3>No Available Employees</h3><p>All employees are already assigned to this position.</p></div>';
        return;
    }
    
    let html = '<div class="assign-form-container">';
    
    employees.forEach(emp => {
        const currentPos = emp.position || 'Unassigned';
        const statusClass = emp.status === 'active' ? 'badge-success' : 'badge-secondary';
        const safeName = emp.full_name.replace(/'/g, "\\'");
        const safePosition = position.replace(/'/g, "\\'");
        html += `
            <div class="add-employee-item">
                <div class="employee-item-info">
                    <div class="employee-item-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="employee-item-details">
                        <h4>${emp.full_name}</h4>
                        <p>${emp.employee_code} • ${emp.department || 'N/A'} • Current: ${currentPos}</p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="badge ${statusClass}">${emp.status.charAt(0).toUpperCase() + emp.status.slice(1)}</span>
                    <button class="btn-add-this-employee" 
                            data-employee-id="${emp.id}" 
                            data-employee-name="${safeName}" 
                            data-position="${safePosition}">
                        <i class="fas fa-plus-circle"></i> Add
                    </button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    modalBody.innerHTML = html;
    
    // Attach event listeners to add buttons
    const addButtons = modalBody.querySelectorAll('.btn-add-this-employee');
    addButtons.forEach(button => {
        button.addEventListener('click', function() {
            const employeeId = this.getAttribute('data-employee-id');
            const employeeName = this.getAttribute('data-employee-name');
            const position = this.getAttribute('data-position');
            addEmployeeToPosition(employeeId, employeeName, position, this);
        });
    });
}

function closeAddEmployeeToPositionModal() {
    const modal = document.getElementById('addEmployeeToPositionModal');
    modal.classList.remove('active');
}

function addEmployeeToPosition(employeeId, employeeName, position, buttonElement) {
    // Disable button
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    // Update employee position
    fetch('update_employee_position.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            employee_id: employeeId,
            position: position
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`${employeeName} has been added to ${position}.`, 'success');
            // Close add modal
            closeAddEmployeeToPositionModal();
            // Refresh manage modal
            manageEmployeesInPosition(position);
        } else {
            showToast(data.message, 'error');
            buttonElement.disabled = false;
            buttonElement.innerHTML = '<i class="fas fa-plus-circle"></i> Add';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to add employee to position.', 'error');
        buttonElement.disabled = false;
        buttonElement.innerHTML = '<i class="fas fa-plus-circle"></i> Add';
    });
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const manageModal = document.getElementById('manageEmployeesModal');
    const addModal = document.getElementById('addEmployeeToPositionModal');
    const confirmModalEl = document.getElementById('confirmModal');
    
    if (event.target === manageModal) {
        closeManageEmployeesModal();
    }
    if (event.target === addModal) {
        closeAddEmployeeToPositionModal();
    }
    if (event.target === confirmModalEl) {
        closeConfirmModal();
    }
});

// Update escape key handler
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
        closeAssignModal();
        closeCreatePositionModal();
        closeManageEmployeesModal();
        closeAddEmployeeToPositionModal();
        closeConfirmModal();
    }
});
</script>

<?php
// Include footer
require_once 'include/footer.php';
?>
