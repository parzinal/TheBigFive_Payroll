<?php
/**
 * Staff Dashboard
 * Main dashboard page for staff members
 */

// Set page title
$page_title = 'Dashboard';

// Include header
require_once 'include/header.php';

// Include database connection
require_once '../config/database.php';

// Include sidebar
require_once 'include/sidebar.php';

// Fetch dashboard statistics
try {
    $pdo = getDBConnection();
    
    // Get total employees count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees");
    $totalEmployees = $stmt->fetch()['total'];
    
    // Get active employees count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
    $activeEmployees = $stmt->fetch()['total'];
    
    // Get total monthly payroll
    $stmt = $pdo->query("SELECT SUM(basic_monthly_salary) as total FROM employees WHERE status = 'active'");
    $totalPayroll = $stmt->fetch()['total'] ?? 0;
    
    // Get recent payroll periods
    $stmt = $pdo->query("SELECT * FROM payroll_periods ORDER BY created_at DESC LIMIT 5");
    $recentPeriods = $stmt->fetchAll();
    
    // Get recent employees
    $stmt = $pdo->query("SELECT employee_code, full_name, position, status, hire_date FROM employees ORDER BY hire_date DESC LIMIT 5");
    $recentEmployees = $stmt->fetchAll();
    
    // Get employees by position
    $stmt = $pdo->query("SELECT position, COUNT(*) as count FROM employees WHERE status = 'active' GROUP BY position ORDER BY count DESC LIMIT 5");
    $employeesByPosition = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $totalEmployees = 0;
    $activeEmployees = 0;
    $totalPayroll = 0;
    $recentPeriods = [];
    $recentEmployees = [];
    $employeesByPosition = [];
}
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-left">
                <div class="page-title-row">
                    <div class="page-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="page-title-text">
                        <h1>Staff Dashboard</h1>
                        <div class="page-breadcrumb">
                            <i class="fas fa-home"></i>
                            <span>Home</span>
                            <span class="page-breadcrumb-separator">/</span>
                            <span>Dashboard</span>
                        </div>
                    </div>
                </div>
                <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?>! Here's an overview of the payroll system.</p>
            </div>
            <div class="page-header-right">
                <div class="page-stat-badge">
                    <i class="fas fa-calendar-day"></i>
                    <span><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <p class="stat-label">Total Employees</p>
                <h3 class="stat-value"><?php echo number_format($totalEmployees); ?></h3>
                <span class="stat-badge badge-success">
                    <?php echo number_format($activeEmployees); ?> Active
                </span>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-details">
                <p class="stat-label">Monthly Payroll</p>
                <h3 class="stat-value">₱<?php echo number_format($totalPayroll, 2); ?></h3>
                <span class="stat-badge badge-info">Active Employees</span>
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-icon">
                <i class="fas fa-briefcase"></i>
            </div>
            <div class="stat-details">
                <p class="stat-label">Positions</p>
                <h3 class="stat-value"><?php echo count($employeesByPosition); ?></h3>
                <span class="stat-badge badge-primary">Active Positions</span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <!-- Recent Employees -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-plus"></i> Recent Employees
                </h3>
                <a href="employee_list.php" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <?php if (count($recentEmployees) > 0): ?>
                    <div class="activity-list">
                        <?php foreach ($recentEmployees as $employee): ?>
                            <div class="activity-item">
                                <div class="activity-icon activity-icon-<?php echo $employee['status'] === 'active' ? 'success' : 'inactive'; ?>">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-title"><?php echo htmlspecialchars($employee['full_name']); ?></p>
                                    <p class="activity-meta">
                                        <span class="badge badge-sm badge-<?php echo $employee['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($employee['status']); ?>
                                        </span>
                                        <?php if (!empty($employee['position'])): ?>
                                            <span class="badge badge-sm badge-info">
                                                <?php echo htmlspecialchars($employee['position']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="activity-time">
                                            <?php echo !empty($employee['hire_date']) ? date('M d, Y', strtotime($employee['hire_date'])) : 'N/A'; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state-sm">
                        <i class="fas fa-users"></i>
                        <p>No recent employees</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Employees by Position -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-briefcase"></i> Employees by Position
                </h3>
            </div>
            <div class="card-body">
                <?php if (count($employeesByPosition) > 0): ?>
                    <div class="department-list">
                        <?php foreach ($employeesByPosition as $position): ?>
                            <div class="department-item">
                                <div class="department-info">
                                    <span class="department-name"><?php echo htmlspecialchars($position['position'] ?: 'Unassigned'); ?></span>
                                    <span class="department-count"><?php echo number_format($position['count']); ?> employees</span>
                                </div>
                                <div class="department-bar">
                                    <div class="department-progress" style="width: <?php echo $totalEmployees > 0 ? ($position['count'] / $totalEmployees) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state-sm">
                        <i class="fas fa-briefcase"></i>
                        <p>No positions found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Payroll Periods -->
        <div class="card card-full">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt"></i> Recent Payroll Periods
                </h3>
                <a href="payroll_list.php" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <?php if (count($recentPeriods) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Period Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Pay Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPeriods as $period): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($period['period_name']); ?></strong></td>
                                        <td><?php echo !empty($period['start_date']) ? date('M d, Y', strtotime($period['start_date'])) : 'N/A'; ?></td>
                                        <td><?php echo !empty($period['end_date']) ? date('M d, Y', strtotime($period['end_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $period['pay_date'] ? date('M d, Y', strtotime($period['pay_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch ($period['status']) {
                                                case 'paid':
                                                    $statusClass = 'badge-success';
                                                    break;
                                                case 'completed':
                                                    $statusClass = 'badge-info';
                                                    break;
                                                case 'processing':
                                                    $statusClass = 'badge-warning';
                                                    break;
                                                default:
                                                    $statusClass = 'badge-secondary';
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($period['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state-sm">
                        <i class="fas fa-calendar-alt"></i>
                        <p>No payroll periods found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3 class="section-title">Quick Actions</h3>
        <div class="actions-grid">
            <a href="employee_list.php" class="action-card">
                <i class="fas fa-users"></i>
                <span>View Employees</span>
            </a>
            <a href="Generatepayroll.php" class="action-card">
                <i class="fas fa-calculator"></i>
                <span>Generate Payroll</span>
            </a>
            <a href="payroll_list.php" class="action-card">
                <i class="fas fa-list"></i>
                <span>Payroll List</span>
            </a>
            <a href="profile.php" class="action-card">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
        </div>
    </div>
</div>

<style>
/* Modern Dashboard Design */
.main-content {
    background: #F9FAFB;
    min-height: 100vh;
    padding: 24px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #E5E7EB;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
}

.stat-primary .stat-icon {
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.stat-success .stat-icon {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.stat-info .stat-icon {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.stat-warning .stat-icon {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.stat-details {
    flex: 1;
}

.stat-label {
    font-size: 14px;
    color: #6B7280;
    margin: 0 0 8px 0;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 8px 0;
    line-height: 1;
}

.stat-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
    color: #065F46;
}

.badge-info {
    background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
    color: #1E40AF;
}

.badge-primary {
    background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
    color: #2563EB;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-bottom: 30px;
}

.card {
    background: white;
    border-radius: 16px;
    border: 1px solid #E5E7EB;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.card-full {
    grid-column: 1 / -1;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #E5E7EB;
    background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);
}

.card-title {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-title i {
    color: #2563EB;
    font-size: 18px;
}

.card-link {
    font-size: 14px;
    color: #2563EB;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.card-link:hover {
    color: #1d4ed8;
    transform: translateX(4px);
}

.card-body {
    padding: 24px;
}

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px;
    border-radius: 12px;
    transition: background 0.2s ease;
}

.activity-item:hover {
    background: #F9FAFB;
}

.activity-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.activity-icon-success {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
}

.activity-icon-inactive {
    background: linear-gradient(135deg, #9CA3AF 0%, #6B7280 100%);
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    margin: 0 0 6px 0;
}

.activity-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.badge-sm {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.badge-sm.badge-success {
    background: #10B981;
    color: white;
}

.badge-sm.badge-secondary {
    background: #6B7280;
    color: white;
}

.badge-sm.badge-info {
    background: #3B82F6;
    color: white;
}

.activity-time {
    font-size: 12px;
    color: #9CA3AF;
}

/* Department List */
.department-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.department-item {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.department-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.department-name {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
}

.department-count {
    font-size: 13px;
    color: #6B7280;
    font-weight: 500;
}

.department-bar {
    height: 10px;
    background: #F3F4F6;
    border-radius: 6px;
    overflow: hidden;
}

.department-progress {
    height: 100%;
    background: linear-gradient(90deg, #2563EB 0%, #3B82F6 100%);
    border-radius: 6px;
    transition: width 0.5s ease;
}

/* Table */
.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);
}

.data-table th {
    padding: 14px 16px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #E5E7EB;
}

.data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #F3F4F6;
    color: #1F2937;
    font-size: 14px;
}

.data-table tbody tr {
    transition: background 0.2s ease;
}

.data-table tbody tr:hover {
    background-color: #F9FAFB;
}

.badge {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.badge-secondary {
    background: #6B7280;
    color: white;
}

.badge.badge-success {
    background: #10B981;
    color: white;
}

.badge.badge-info {
    background: #3B82F6;
    color: white;
}

.badge.badge-warning {
    background: #F59E0B;
    color: white;
}

/* Empty State */
.empty-state-sm {
    text-align: center;
    padding: 48px 24px;
    color: #9CA3AF;
}

.empty-state-sm i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
    color: #D1D5DB;
}

.empty-state-sm p {
    margin: 0;
    font-size: 14px;
    font-weight: 500;
}

/* Quick Actions */
.quick-actions {
    margin-bottom: 30px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.action-card {
    background: white;
    border: 2px solid #E5E7EB;
    border-radius: 16px;
    padding: 28px 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    text-decoration: none;
    transition: all 0.3s ease;
    color: #374151;
}

.action-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.15);
    border-color: #2563EB;
    background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
}

.action-card i {
    font-size: 40px;
    color: #2563EB;
    transition: transform 0.3s ease;
}

.action-card:hover i {
    transform: scale(1.15);
}

.action-card span {
    font-size: 15px;
    font-weight: 600;
    text-align: center;
}

/* Responsive */
@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 16px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-value {
        font-size: 28px;
    }
    
    .actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-card {
        padding: 20px 16px;
    }
    
    .action-card i {
        font-size: 32px;
    }
}
</style>

<?php
// Include footer
require_once 'include/footer.php';
?>
