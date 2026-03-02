<?php
/**
 * Staff Employee List Page
 * View all employees (read-only)
 */

// Set page title
$page_title = 'Employees';

// Include header
require_once 'include/header.php';

// Include database connection
require_once '../config/database.php';

// Include sidebar
require_once 'include/sidebar.php';

// Fetch all employees from database
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, employee_code, full_name, position, basic_monthly_salary, hire_date, status, created_at FROM employees ORDER BY created_at DESC");
    $stmt->execute();
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching employees: " . $e->getMessage();
    $employees = [];
}
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
                        <h1>Employees</h1>
                        <div class="page-breadcrumb">
                            <a href="dashboard_staff.php"><i class="fas fa-home"></i> Home</a>
                            <span class="page-breadcrumb-separator">/</span>
                            <span>Employees</span>
                        </div>
                    </div>
                </div>
                <p class="page-subtitle">View all employee records in the system</p>
            </div>
            <div class="page-header-right">
                <div class="page-stat-badge">
                    <i class="fas fa-calendar-day"></i>
                    <span><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
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
                            <th>Monthly Salary</th>
                            <th>Hire Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($employees) > 0): ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr data-status="<?php echo $employee['status']; ?>">
                                    <td>
                                        <span class="employee-code"><?php echo htmlspecialchars($employee['employee_code']); ?></span>
                                    </td>
                                    <td>
                                        <div class="employee-info">
                                            <i class="fas fa-user-circle"></i>
                                            <strong><?php echo htmlspecialchars($employee['full_name']); ?></strong>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['position'] ?: 'N/A'); ?></td>
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
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">
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

<style>
/* Modern Flat Design - Employee List */
.main-content {
    background: #F9FAFB;
    min-height: 100vh;
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

.text-center {
    text-align: center;
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
}
</style>

<script>
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

function filterTable() {
    let searchValue = document.getElementById('searchEmployees').value.toLowerCase();
    let activeFilter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
    let table = document.getElementById('employeesTable');
    let rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        let row = rows[i];
        let text = row.textContent.toLowerCase();
        let status = row.getAttribute('data-status');
        
        // Check search match
        let searchMatch = text.indexOf(searchValue) > -1;
        
        // Check filter match
        let filterMatch = (activeFilter === 'all' || status === activeFilter);
        
        // Show row only if both conditions are met
        if (searchMatch && filterMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}
</script>

<?php
// Include footer
require_once 'include/footer.php';
?>
