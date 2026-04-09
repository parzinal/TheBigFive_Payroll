<?php
/**
 * Payslip History Page
 * Display all employees with generated payslips
 */

$page_title = 'Payslip History';
require_once 'include/header.php';
require_once 'include/sidebar.php';
require_once '../config/database.php';

$pdo = getDBConnection();

$stmt = $pdo->query("
    SELECT 
        e.id,
        e.employee_code,
        e.full_name,
        e.position,
        e.department,
        e.basic_monthly_salary,
        COUNT(pc.id) as payslip_count,
        MAX(pc.created_at) as last_payslip_date,
        SUM(pc.net_pay) as total_net_pay
    FROM employees e
    INNER JOIN payroll_computations pc ON e.id = pc.employee_id
    WHERE pc.status IN ('computed', 'approved', 'paid')
    -- Include rows explicitly marked as cutoff OR rows updated to indicate a generated payslip
    AND (
        pc.other_deductions_notes LIKE '%\"cutoff_type\"%'
        OR pc.other_deductions_notes LIKE '%\"payslip_generated\"%'
    )
    GROUP BY e.id, e.employee_code, e.full_name, e.position, e.department, e.basic_monthly_salary
    ORDER BY MAX(pc.created_at) DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total statistics
$total_employees = count($employees);
$total_payslips = array_sum(array_column($employees, 'payslip_count'));
$total_amount = array_sum(array_column($employees, 'total_net_pay'));
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-header-left">
                    <div class="page-title-row">
                        <div class="page-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="page-title-text">
                            <h1>Payslip History</h1>
                            <div class="page-breadcrumb">
                                <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                                <span class="page-breadcrumb-separator">/</span>
                                <span>Payroll</span>
                                <span class="page-breadcrumb-separator">/</span>
                                <span>Payslip History</span>
                            </div>
                        </div>
                    </div>
                    <p class="page-subtitle">View all generated payslips for employees</p>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon bg-primary"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo $total_employees; ?></span>
                    <span class="stat-label">Employees with Payslips</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-success"><i class="fas fa-file-invoice"></i></div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo $total_payslips; ?></span>
                    <span class="stat-label">Total Payslips</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-info"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info">
                    <span class="stat-value">₱<?php echo number_format($total_amount, 2); ?></span>
                    <span class="stat-label">Total Amount Paid</span>
                </div>
            </div>
        </div>

        <!-- Employee Cards Section -->
        <div class="employee-payslip-cards">
            <?php if (count($employees) > 0): ?>
                <?php foreach ($employees as $employee): ?>
                    <div class="payslip-card" data-employee-id="<?php echo $employee['id']; ?>">
                        <div class="payslip-card-header">
                            <div class="employee-info">
                                <div class="employee-avatar-large">
                                    <?php echo strtoupper(substr($employee['full_name'], 0, 2)); ?>
                                </div>
                                <div class="employee-details">
                                    <h3 class="employee-name"><?php echo htmlspecialchars($employee['full_name']); ?></h3>
                                    <span class="employee-code"><?php echo htmlspecialchars($employee['employee_code']); ?></span>
                                    <div class="employee-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-briefcase"></i>
                                            <?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-building"></i>
                                            <?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="payslip-card-body">
                            <div class="payslip-stats">
                                <div class="stat-item">
                                    <div class="stat-icon-small"><i class="fas fa-file-alt"></i></div>
                                    <div class="stat-content">
                                        <span class="stat-number"><?php echo $employee['payslip_count']; ?></span>
                                        <span class="stat-text">Payslips</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-small"><i class="fas fa-calendar"></i></div>
                                    <div class="stat-content">
                                        <span class="stat-number"><?php echo date('M d, Y', strtotime($employee['last_payslip_date'])); ?></span>
                                        <span class="stat-text">Last Generated</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-small"><i class="fas fa-peso-sign"></i></div>
                                    <div class="stat-content">
                                        <span class="stat-number">₱<?php echo number_format($employee['total_net_pay'], 2); ?></span>
                                        <span class="stat-text">Total Paid</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="payslip-card-footer">
                            <button class="btn-view-payslips" onclick="viewEmployeePayslips(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['full_name']); ?>')">
                                <i class="fas fa-eye"></i> View All Payslips
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-payslips-message">
                    <i class="fas fa-inbox"></i>
                    <h3>No Payslips Found</h3>
                    <p>No payslips have been generated yet. Start by <a href="Generatepayroll.php">generating payroll</a>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Payslips List Modal -->
<div id="payslipsListModal" class="modal-payslips">
    <div class="modal-payslips-content">
        <div class="modal-payslips-header">
            <h2 id="modalEmployeeName">Employee Payslips</h2>
            <button class="modal-close" onclick="closePayslipsListModal()">&times;</button>
        </div>
        <div class="modal-payslips-body" id="payslipsListContainer">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i> Loading payslips...
            </div>
        </div>
    </div>
</div>

<!-- Single Payslip Receipt Modal -->
<div id="payslipReceiptModal" class="modal-receipt">
    <div class="modal-receipt-overlay" onclick="closePayslipReceiptModal()"></div>
    <div class="modal-receipt-content">
        <button class="receipt-close-btn" onclick="closePayslipReceiptModal()">
            <i class="fas fa-times"></i>
        </button>
        <div id="payslipReceiptContainer"></div>
    </div>
</div>

<style>
/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
}

.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
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

.stat-icon.bg-primary { background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%); }
.stat-icon.bg-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-icon.bg-info { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

/* Employee Payslip Cards */
.employee-payslip-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.payslip-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.07);
    overflow: hidden;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.payslip-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.12);
    border-color: #2563EB;
}

.payslip-card-header {
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    padding: 18px;
}

.employee-info {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.employee-avatar-large {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    border: 3px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    font-weight: 700;
    flex-shrink: 0;
}

.employee-details {
    flex: 1;
    color: white;
}

.employee-name {
    margin: 0 0 5px 0;
    font-size: 17px;
    font-weight: 600;
    color: white;
}

.employee-code {
    display: inline-block;
    background: rgba(255, 255, 255, 0.2);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    margin-bottom: 8px;
}

.employee-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-top: 8px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    opacity: 0.95;
}

.meta-item i {
    width: 16px;
    text-align: center;
}

.payslip-card-body {
    padding: 18px;
}

.payslip-stats {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.stat-item:hover {
    background: #f1f5f9;
}

.stat-icon-small {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.stat-content {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.stat-number {
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
}

.stat-text {
    font-size: 11px;
    color: #64748b;
    font-weight: 500;
}

.payslip-card-footer {
    padding: 0 18px 18px 18px;
}

.btn-view-payslips {
    width: 100%;
    padding: 12px 20px;
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
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
    transition: all 0.3s ease;
}

.btn-view-payslips:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
}

.btn-view-payslips i {
    font-size: 14px;
}

/* No Payslips Message */
.no-payslips-message {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.no-payslips-message i {
    font-size: 64px;
    color: #cbd5e1;
    margin-bottom: 20px;
}

.no-payslips-message h3 {
    font-size: 24px;
    color: #475569;
    margin: 0 0 12px 0;
}

.no-payslips-message p {
    font-size: 15px;
    color: #64748b;
    margin: 0;
}

.no-payslips-message a {
    color: #2563EB;
    font-weight: 600;
    text-decoration: none;
}

.no-payslips-message a:hover {
    text-decoration: underline;
}

/* Modal Styles */
.modal-payslips {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.75);
    z-index: 10000;
    overflow-y: auto;
    padding: 40px 20px;
}

.modal-payslips.active {
    display: flex;
    align-items: flex-start;
    justify-content: center;
}

.modal-payslips-content {
    background: #f8fafc;
    border-radius: 16px;
    width: 100%;
    max-width: 900px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    margin: auto;
}

.modal-payslips-header {
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    color: white;
    padding: 24px 32px;
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-payslips-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.modal-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    font-size: 32px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.modal-payslips-body {
    padding: 32px;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
}

/* Cutoff Filter Bar */
.payslip-filter-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: #f1f5f9;
    border-radius: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.payslip-filter-bar label {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    white-space: nowrap;
}
.payslip-filter-bar select {
    padding: 8px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    color: #1e293b;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.2s;
    min-width: 130px;
}
.payslip-filter-bar select:focus {
    outline: none;
    border-color: #2563EB;
}
.payslip-filter-bar .filter-divider {
    width: 1px;
    height: 28px;
    background: #cbd5e1;
}
.btn-filter-reset {
    padding: 8px 16px;
    background: #e2e8f0;
    color: #475569;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    white-space: nowrap;
}
.btn-filter-reset:hover {
    background: #cbd5e1;
    color: #1e293b;
}
.filter-result-count {
    margin-left: auto;
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
}
.payslip-list-item.hidden-by-filter {
    display: none;
}
.no-filter-results {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
    font-size: 15px;
}
.no-filter-results i {
    font-size: 36px;
    display: block;
    margin-bottom: 12px;
    color: #cbd5e1;
}

.loading-spinner {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
    font-size: 18px;
}

.loading-spinner i {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
    color: #2563EB;
}

/* Payslip Image Container */
.payslip-list-grid {
    display: grid;
    gap: 16px;
}

.payslip-list-item {
    background: white;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.payslip-list-item:hover {
    border-color: #2563EB;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
    transform: translateY(-2px);
}

.payslip-list-info {
    flex: 1;
}

.payslip-list-period {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

/* Cut-off badges */
.cutoff-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    white-space: nowrap;
    flex-shrink: 0;
}
.cutoff-badge.first {
    background: #dbeafe;
    color: #1d4ed8;
    border: 1.5px solid #93c5fd;
}
.cutoff-badge.second {
    background: #fef3c7;
    color: #b45309;
    border: 1.5px solid #fcd34d;
}

.payslip-list-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.payslip-list-meta-item {
    font-size: 13px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
}

.payslip-list-meta-item i {
    color: #2563EB;
}

.payslip-list-meta-item strong {
    color: #1e293b;
    font-weight: 600;
}

.btn-view-payslip {
    padding: 12px 28px;
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-view-payslip:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
}

.btn-download-pdf {
    padding: 10px 20px;
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    white-space: nowrap;
    text-decoration: none;
}

.btn-download-pdf:hover {
    background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.3);
    color: white;
    text-decoration: none;
}

/* Receipt Modal */
.modal-receipt {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 10001;
    overflow-y: auto;
}

.modal-receipt.active {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-receipt-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.75);
    z-index: 1;
}

.modal-receipt-content {
    position: relative;
    z-index: 2;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 960px;
    width: 95vw;
    max-height: 90vh;
    overflow-y: auto;
}

.receipt-close-btn {
    position: sticky;
    top: 10px;
    right: 10px;
    float: right;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    z-index: 10;
    margin: 10px 10px 0 0;
    transition: all 0.2s ease;
}

.receipt-close-btn:hover {
    background: rgba(0, 0, 0, 0.8);
}

/* Receipt Payslip Design */
.receipt-payslip {
    padding: 30px 25px;
    background: white;
    font-family: 'Courier New', Courier, monospace;
    color: #1e293b;
}

.receipt-header {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 2px dashed #cbd5e1;
    margin-bottom: 20px;
}

.receipt-company {
    font-size: 20px;
    font-weight: 700;
    color: #2563EB;
    margin: 0 0 5px 0;
    font-family: Arial, sans-serif;
}

.receipt-title {
    font-size: 14px;
    color: #64748b;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.receipt-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px dashed #e2e8f0;
}

.receipt-section:last-child {
    border-bottom: none;
}

.receipt-label {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 8px;
    letter-spacing: 0.5px;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 13px;
}

.receipt-row-label {
    color: #475569;
}

.receipt-row-value {
    font-weight: 600;
    color: #1e293b;
    text-align: right;
}

.receipt-divider {
    border: none;
    border-top: 1px dashed #cbd5e1;
    margin: 12px 0;
}

.receipt-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border-radius: 8px;
    margin: 20px 0;
    font-size: 14px;
    font-weight: 700;
}

.receipt-total-label {
    font-size: 12px;
    opacity: 0.9;
}

.receipt-total-value {
    font-size: 24px;
}

.receipt-info {
    background: #f8fafc;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.receipt-info-row {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    padding: 4px 0;
}

.receipt-info-label {
    color: #64748b;
}

.receipt-info-value {
    color: #1e293b;
    font-weight: 600;
}

.receipt-footer {
    text-align: center;
    padding-top: 20px;
    border-top: 2px dashed #cbd5e1;
    margin-top: 20px;
}

.receipt-footer-text {
    font-size: 10px;
    color: #94a3b8;
    margin: 3px 0;
    line-height: 1.5;
}



/* Responsive */
@media (max-width: 768px) {
    .employee-payslip-cards {
        grid-template-columns: 1fr;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .modal-payslips-content {
        margin: 0;
        border-radius: 0;
        min-height: 100vh;
    }
    
    .modal-payslips-header {
        border-radius: 0;
    }
    
    .modal-receipt-content {
        max-width: 100%;
        border-radius: 0;
        max-height: 100vh;
    }
    
    .receipt-payslip {
        padding: 20px 15px;
    }
    
    .payslip-list-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .btn-view-payslip {
        width: 100%;
        justify-content: center;
    }
    
    .payslip-list-meta {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<script>
// Show list of payslips for employee
function viewEmployeePayslips(employeeId, employeeName) {
    const modal = document.getElementById('payslipsListModal');
    const modalTitle = document.getElementById('modalEmployeeName');
    const container = document.getElementById('payslipsListContainer');

    modalTitle.textContent = employeeName + ' - Payslips';
    modal.classList.add('active');

    // Show loading
    container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading payslips...</div>';

    // Fetch payslips
    fetch(`get_employee_payslips.php?employee_id=${employeeId}`)
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.text();
        })
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch(e) {
                console.error('JSON parse error. Raw response:', text);
                throw new Error('Invalid server response');
            }

            if (data.success && data.payslips && data.payslips.length > 0) {
                renderPayslipList(data.payslips, container);
            } else {
                container.innerHTML = `
                    <div class="loading-spinner">
                        <i class="fas fa-inbox"></i>
                        <p>No payslips found for this employee.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading payslips:', error);
            container.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i>
                    <p>Error loading payslips. Please try again.</p>
                    <small style="color:#94a3b8;">${error.message}</small>
                </div>
            `;
        });
}

// Render the payslip list inside the modal container
function renderPayslipList(payslips, container) {
    // ── Build YYYY-MM values for the month date-picker ──
    const monthSet = new Set();
    payslips.forEach(p => {
        if (p.start_date) {
            const d = new Date(p.start_date);
            const ym = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            monthSet.add(ym);
        }
    });
    const sortedMonths = [...monthSet].sort((a, b) => b.localeCompare(a));

    // ── Filter bar with month date-picker ──
    let monthOptions = '<option value="">All Periods</option>';
    sortedMonths.forEach(ym => {
        const [yr, mo] = ym.split('-');
        const label = new Date(yr, mo - 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        monthOptions += `<option value="${ym}">${label}</option>`;
    });

    let html = `
    <div class="payslip-filter-bar">
        <i class="fas fa-calendar-alt" style="color:#2563EB;"></i>
        <label for="filterMonthPicker">Filter by Period:</label>
        <select id="filterMonthPicker" onchange="applyPayslipFilter()">${monthOptions}</select>
        <div class="filter-divider"></div>
        <label style="font-size:13px;font-weight:600;color:#475569;">Cut-off:</label>
        <select id="filterCutoff" onchange="applyPayslipFilter()">
            <option value="">All</option>
            <option value="1st">1st Cut-off (Prev 28 - Curr 12)</option>
            <option value="2nd">2nd Cut-off (Curr 13 - 27)</option>
        </select>
        <div class="filter-divider"></div>
        <button class="btn-filter-reset" onclick="resetPayslipFilter()">
            <i class="fas fa-times"></i> Reset
        </button>
        <span class="filter-result-count" id="filterResultCount"></span>
    </div>`;

    html += '<div class="payslip-list-grid" id="payslipListGrid">';

    payslips.forEach(payslip => {
        const notesForCutoff = parsePayslipNotes(payslip.other_deductions_notes || '{}');
        const cutoffTypeFromNotes = String(notesForCutoff.cutoff_type || '').trim().toLowerCase();
        const isFirst = cutoffTypeFromNotes
            ? cutoffTypeFromNotes === 'first'
            : isPayrollFirstCutoffByDate(payslip.start_date);
        const cutoffLabel = isFirst ? '1st Cut-off' : '2nd Cut-off';
        const cutoffKey   = isFirst ? '1st' : '2nd';
        const badgeClass  = isFirst ? 'first' : 'second';
        const cutoffRange = formatCutoffRangeFromPeriod(payslip.start_date, payslip.end_date, isFirst);

        // YYYY-MM for data attribute filtering
        const ym = payslip.start_date
            ? payslip.start_date.substring(0, 7)   // "2026-03"
            : '';

        // Period label: use period_name if available, else derive from start_date
        const periodLabel = payslip.period_name || formatShortDate(payslip.start_date);

        // Net pay formatted
        const notes = notesForCutoff;
        const othersCa = getPayslipOthersCa(payslip, notes);
        const governmentDeductions = getPayslipGovernmentDeductions(payslip);
        const netPay = getPayslipDisplayNetPay(payslip, othersCa, governmentDeductions);
        const netPayFmt = '₱' + netPay.toLocaleString('en-PH', {minimumFractionDigits: 2});

        html += `
        <div class="payslip-list-item" data-ym="${ym}" data-cutoff="${cutoffKey}">
            <div class="payslip-list-info">
                <h4 class="payslip-list-period">
                    <span class="cutoff-badge ${badgeClass}" style="font-size:13px;padding:5px 14px;border-radius:8px;">
                        ${isFirst
                            ? '<i class="fas fa-1" style="font-style:normal;font-weight:900;">1</i>'
                            : '<i class="fas fa-2" style="font-style:normal;font-weight:900;">2</i>'}
                        &nbsp;${cutoffLabel}
                        <span style="opacity:0.75;font-weight:500;font-size:10px;margin-left:4px;">(${cutoffRange})</span>
                    </span>
                    <span style="color:#1e293b;font-weight:700;">${periodLabel}</span>
                </h4>
                <div class="payslip-list-meta">
                    <div class="payslip-list-meta-item">
                        <i class="fas fa-calendar-check"></i>
                        Period: <strong>${formatShortDate(payslip.start_date)} – ${formatShortDate(payslip.end_date)}</strong>
                    </div>
                    <div class="payslip-list-meta-item">
                        <i class="fas fa-clock"></i>
                        Generated: <strong>${formatDate(payslip.created_at)}</strong>
                    </div>
                    <div class="payslip-list-meta-item">
                        <i class="fas fa-money-bill-wave"></i>
                        Net Pay: <strong style="color:#059669;font-size:15px;">${netPayFmt}</strong>
                    </div>
                    <div class="payslip-list-meta-item">
                        <i class="fas fa-check-circle" style="color:#2563EB;"></i>
                        Status: <strong>${payslip.status.toUpperCase()}</strong>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-direction:column;align-items:stretch;min-width:140px;">
                <button class="btn-view-payslip" onclick='viewPayslipReceipt(${JSON.stringify(payslip).replace(/'/g, "&#39;")})'>
                    <i class="fas fa-eye"></i> View Payslip
                </button>
                <a class="btn-download-pdf" href="generate_payslip_pdf.php?payslip_id=${payslip.id}" target="_blank">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </a>
            </div>
        </div>`;
    });

    html += '</div>';
    container.innerHTML = html;
    updateFilterCount();
}

// Show single payslip in receipt format
function viewPayslipReceipt(payslip) {
    const modal = document.getElementById('payslipReceiptModal');
    const container = document.getElementById('payslipReceiptContainer');
    
    container.innerHTML = generateReceiptHTML(payslip);
    modal.classList.add('active');
}

// Close payslips list modal
function closePayslipsListModal() {
    const modal = document.getElementById('payslipsListModal');
    modal.classList.remove('active');
}

// Close payslip receipt modal
function closePayslipReceiptModal() {
    const modal = document.getElementById('payslipReceiptModal');
    modal.classList.remove('active');
}

// ── Payslip cutoff filters ──────────────────────────────────────────────────
function applyPayslipFilter() {
    const ym      = document.getElementById('filterMonthPicker')?.value || '';
    const cutoff  = document.getElementById('filterCutoff')?.value || '';
    const items   = document.querySelectorAll('#payslipListGrid .payslip-list-item');

    items.forEach(item => {
        const matchYM     = !ym     || item.dataset.ym     === ym;
        const matchCutoff = !cutoff || item.dataset.cutoff === cutoff;
        item.classList.toggle('hidden-by-filter', !(matchYM && matchCutoff));
    });

    updateFilterCount();
}

function resetPayslipFilter() {
    const monthSel  = document.getElementById('filterMonthPicker');
    const cutoffSel = document.getElementById('filterCutoff');
    if (monthSel)  monthSel.value  = '';
    if (cutoffSel) cutoffSel.value = '';
    applyPayslipFilter();
}

function updateFilterCount() {
    const all     = document.querySelectorAll('#payslipListGrid .payslip-list-item');
    const visible = document.querySelectorAll('#payslipListGrid .payslip-list-item:not(.hidden-by-filter)');
    const badge   = document.getElementById('filterResultCount');
    if (badge) badge.textContent = `${visible.length} of ${all.length} payslip${all.length !== 1 ? 's' : ''}`;

    // Show/hide empty state
    let noResult = document.getElementById('noFilterResult');
    if (visible.length === 0) {
        if (!noResult) {
            noResult = document.createElement('div');
            noResult.id = 'noFilterResult';
            noResult.className = 'no-filter-results';
            noResult.innerHTML = '<i class="fas fa-search"></i>No payslips match the selected filter.';
            const grid = document.getElementById('payslipListGrid');
            if (grid) grid.after(noResult);
        }
    } else if (noResult) {
        noResult.remove();
    }
}
// ────────────────────────────────────────────────────────────────────────────

function parsePayslipNotes(rawNotes) {
    if (!rawNotes) return {};
    try {
        const parsed = JSON.parse(rawNotes);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (e) {
        return {};
    }
}

function getPayslipOthersCa(payslip, notes = null) {
    const parsedNotes = notes || parsePayslipNotes(payslip?.other_deductions_notes);
    const dtrOtherDeduction = parseFloat(parsedNotes.dtr_other_deduction || payslip?.dtr_other_deduction || 0);
    const othersCaStored = parseFloat(parsedNotes.other_deductions || 0)
        + parseFloat(payslip?.other_deductions || 0);
    return othersCaStored > 0 ? othersCaStored : dtrOtherDeduction;
}

function getPayslipGovernmentDeductions(payslip) {
    const whTax = parseFloat(payslip?.withholding_tax || 0);
    const sss = parseFloat(payslip?.sss_contribution || 0);
    const philhealth = parseFloat(payslip?.philhealth_contribution || 0);
    const pagibig = parseFloat(payslip?.pagibig_contribution || 0);
    return whTax + sss + philhealth + pagibig;
}

function getPayslipDisplayNetPay(payslip, othersCa = null, governmentDeductions = null) {
    const rawNetPay = parseFloat(payslip?.net_pay || 0);
    const grossPay = parseFloat(payslip?.total_earnings || 0);
    const resolvedOthersCa = othersCa === null ? getPayslipOthersCa(payslip) : othersCa;
    const resolvedGovtDeductions = governmentDeductions === null
        ? getPayslipGovernmentDeductions(payslip)
        : governmentDeductions;

    // Backward-compatible fallback: old cutoff rows stored net_pay without Others/C.A.
    const looksMissingOthers = resolvedOthersCa > 0
        && Math.abs(rawNetPay - (grossPay - resolvedGovtDeductions)) < 0.01;
    const adjustedNetPay = looksMissingOthers ? (rawNetPay - resolvedOthersCa) : rawNetPay;
    return adjustedNetPay < 0 ? 0 : adjustedNetPay;
}

function getCutoff(startDate) {
    if (!startDate) return '';
    return isPayrollFirstCutoffByDate(startDate) ? '1st Cut-off' : '2nd Cut-off';
}

function isPayrollFirstCutoffByDate(dateValue) {
    const raw = String(dateValue || '').trim();
    if (!raw) return false;
    const day = new Date(raw).getDate();
    if (!Number.isFinite(day)) return false;
    return day >= 28 || day <= 12;
}

function formatCutoffRangeFromPeriod(startDate, endDate, isFirstCutoff = null) {
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const formatMonthDay = (dateValue) => {
        const raw = String(dateValue || '').trim();
        const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return '';
        const monthIndex = Math.max(0, Math.min(11, parseInt(match[2], 10) - 1));
        const day = parseInt(match[3], 10);
        if (!Number.isFinite(day) || day <= 0) return '';
        return `${monthNames[monthIndex]} ${day}`;
    };

    const startLabel = formatMonthDay(startDate);
    const endLabel = formatMonthDay(endDate);
    if (startLabel && endLabel) {
        return `${startLabel} - ${endLabel}`;
    }

    if (isFirstCutoff === true) return 'Prev 28 - Curr 12';
    if (isFirstCutoff === false) return 'Curr 13 - 27';
    return '';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatShortDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Generate TB5-style payslip HTML
function generateReceiptHTML(payslip) {
    // Parse other_deductions_notes JSON
    const notes = parsePayslipNotes(payslip.other_deductions_notes || '{}');
    const dtr = notes.dtr_data || {};

    // Earnings
    const regularPay  = parseFloat(payslip.basic_pay   || 0);
    const otPay       = parseFloat(payslip.ot_pay       || 0);
    const otHours     = parseFloat(payslip.total_ot_hours  || 0);
    const workHours   = parseFloat(payslip.total_work_hours || 0);
    const incentive   = parseFloat(notes.commission   || 0);
    const paidLeaves  = parseFloat(notes.sick_pay      || 0);
    const holidayPay  = parseFloat(notes.holiday_pay   || 0);
    const othersAdj   = parseFloat(notes.expense       || 0);
    const trainingPay = parseFloat(payslip.trainings_cost || payslip.training_amount || notes.training_pay || 0);
    const debugTrainingRaw = `${payslip.trainings_cost ?? ''} | ${payslip.training_amount ?? ''} | ${notes.training_pay ?? ''}`;

    // Deductions
    const whTax      = parseFloat(payslip.withholding_tax          || 0);
    const sss        = parseFloat(payslip.sss_contribution         || 0);
    const philhealth = parseFloat(payslip.philhealth_contribution  || 0);
    const pagibig    = parseFloat(payslip.pagibig_contribution     || 0);
    const lateDeduct      = parseFloat(dtr.late_deduct      || payslip.late_deduction      || 0);
    const undertimeDeduct = parseFloat(dtr.undertime_deduct || payslip.undertime_deduction || 0);
    const halfdayDeduct   = parseFloat(dtr.halfday_deduct   || notes.halfday_deduct        || 0);
    const loan       = (parseFloat(notes.student_loan || 0)
                      + parseFloat(notes.union_fees   || 0)
                      + parseFloat(notes.pension      || 0));
    const othersCa = getPayslipOthersCa(payslip, notes);
    const remarksFromNotes = Array.isArray(notes.dtr_remarks_list) ? notes.dtr_remarks_list : [];
    const remarksFromPayslip = Array.isArray(payslip.dtr_remarks_list) ? payslip.dtr_remarks_list : [];
    const rawRemarksList = remarksFromNotes.length > 0 ? remarksFromNotes : remarksFromPayslip;
    const deductionRemarksList = rawRemarksList
        .map(item => String(item).trim())
        .filter(item => item !== '');
    if (deductionRemarksList.length === 0) {
        const singleRemark = (notes.dtr_remarks || payslip.dtr_remarks || '').toString().trim();
        if (singleRemark) deductionRemarksList.push(singleRemark);
    }

    const grossPay             = parseFloat(payslip.total_earnings || 0);
    const governmentDeductions = getPayslipGovernmentDeductions(payslip);
    const netPay      = getPayslipDisplayNetPay(payslip, othersCa, governmentDeductions);
    const otMinutes   = Math.round(otHours * 60);

    // Cut-off helpers
    const cutoffTypeFromNotes = String(notes.cutoff_type || '').trim().toLowerCase();
    const isFirstCut = cutoffTypeFromNotes
        ? cutoffTypeFromNotes === 'first'
        : isPayrollFirstCutoffByDate(payslip.start_date);
    const cutoffRange = formatCutoffRangeFromPeriod(payslip.start_date, payslip.end_date, isFirstCut);
    const cutoffStr   = `${isFirstCut ? '1st CUT OFF' : '2nd CUT OFF'} (${cutoffRange})`;
    const cutoffClass = isFirstCut ? 'tb5-pill-amber' : 'tb5-pill-rose';
    const payDateFmt  = payslip.pay_date
        ? new Date(payslip.pay_date).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})
        : new Date(payslip.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});

    const fmt = v => parseFloat(v).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    const escapeHtml = txt => String(txt)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    const amtCell = v => v > 0 ? `<span style="font-weight:700;color:#0f3460;">P ${fmt(v)}</span>` : `<span style="color:#94a3b8;">&mdash;</span>`;
    const dedCell = v => v > 0 ? `<span style="font-weight:700;color:#dc2626;">P ${fmt(v)}</span>` : `<span style="color:#94a3b8;">&mdash;</span>`;
    const remarksCell = items => (Array.isArray(items) && items.length > 0)
        ? `<span style="display:inline-block;max-width:170px;text-align:right;color:#475569;font-size:7pt;line-height:1.2;white-space:normal;">${items.map(item => `&#8226; ${escapeHtml(item)}`).join('<br>')}</span>`
        : `<span style="color:#94a3b8;">&mdash;</span>`;

    return `
    <style>
    .tb5-wrap { font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; color: #1e293b; }
    .tb5-wrap table { width: 100%; border-collapse: collapse; }
    .tb5-wrap td { vertical-align: middle; }
    .tb5-banner { background: #0f3460; padding: 10px 14px; }
    .tb5-co-tag { font-size: 6.5pt; color: #93c5fd; letter-spacing: 2px; text-transform: uppercase; font-weight: 700; }
    .tb5-co-name { font-size: 14pt; font-weight: 800; color: #fff; margin-top: 2px; }
    .tb5-badge { background: #1a56db; color: #fff; font-weight: 800; font-size: 12pt; letter-spacing: 2px; text-align: center; padding: 6px 14px; border-radius: 5px; border: 2px solid #3b82f6; }
    .tb5-badge-sub { font-size: 6.5pt; color: #bfdbfe; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; display: block; text-align: center; margin-top: 3px; }
    .tb5-info-strip td { background: #e8f0fe; padding: 5px 12px; border-bottom: 1.5px solid #c7d7f9; }
    .tb5-lbl { font-size: 6pt; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; display: block; }
    .tb5-val { font-size: 8.5pt; font-weight: 700; color: #0f3460; display: block; margin-top: 1px; }
    .tb5-pill-green  { background: #15803d; color: #fff; font-weight: 700; font-size: 9pt; padding: 2px 12px; border-radius: 20px; display: inline-block; }
    .tb5-pill-amber  { background: #fef3c7; color: #92400e; font-weight: 700; font-size: 8pt; padding: 2px 10px; border-radius: 20px; border: 1px solid #fbbf24; display: inline-block; }
    .tb5-pill-rose   { background: #fce7f3; color: #9d174d; font-weight: 700; font-size: 8pt; padding: 2px 10px; border-radius: 20px; border: 1px solid #f472b6; display: inline-block; }
    .tb5-pill-status { background: #dcfce7; color: #15803d; font-weight: 700; font-size: 7pt; padding: 2px 8px; border-radius: 20px; border: 1px solid #86efac; text-transform: uppercase; display: inline-block; }
    .tb5-divv { border-left: 1.5px solid #c7d7f9; }
    .tb5-body-wrap > tbody > tr > td { vertical-align: top; padding: 0; }
    .tb5-pane-earn { width: 38%; border-right: 1.5px solid #e2e8f0; }
    .tb5-pane-ded  { width: 35%; border-right: 1.5px solid #e2e8f0; }
    .tb5-pane-sum  { width: 27%; background: #f8fafc; }
    .tb5-sec { padding: 4px 12px; font-size: 7pt; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #fff; }
    .tb5-sec-green { background: #15803d; }
    .tb5-sec-red   { background: #991b1b; }
    .tb5-sec-navy  { background: #1e40af; }
    .tb5-sub td { background: #f1f5f9; font-size: 6.5pt; font-weight: 700; color: #475569; letter-spacing: 0.8px; text-transform: uppercase; padding: 3px 12px; border-bottom: 1px solid #e2e8f0; border-top: 1px solid #e2e8f0; }
    .tb5-sub-r { text-align: right; }
    .tb5-dr td { font-size: 8pt; padding: 3px 12px; border-bottom: 1px solid #f1f5f9; }
    .tb5-rl { color: #334155; font-weight: 600; }
    .tb5-rv { text-align: right; font-weight: 700; color: #0f3460; }
    .tb5-hr-box { margin: 6px 8px 5px; border: 1px solid #e2e8f0; border-radius: 5px; background: #fff; padding: 4px 10px; text-align: center; }
    .tb5-hr-lbl { font-size: 6pt; font-weight: 700; color: #64748b; letter-spacing: 0.8px; text-transform: uppercase; display: block; }
    .tb5-hv-g { color: #15803d; font-weight: 700; font-size: 9pt; padding: 1px 5px; }
    .tb5-hv-b { color: #1a56db; font-weight: 700; font-size: 9pt; padding: 1px 5px; }
    .tb5-hv-s { color: #cbd5e1; padding: 1px 3px; }
    .tb5-sum td { font-size: 8.5pt; padding: 3px 12px; }
    .tb5-sl { color: #475569; font-weight: 700; }
    .tb5-sv  { text-align: right; font-weight: 700; color: #0f3460; border-bottom: 1px solid #e2e8f0; }
    .tb5-svr { text-align: right; font-weight: 700; color: #dc2626; border-bottom: 1px solid #e2e8f0; }
    .tb5-net-box { background: #0f3460; margin: 6px 8px; border-radius: 5px; padding: 8px 12px; text-align: center; }
    .tb5-net-t { color: #93c5fd; font-size: 6pt; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; display: block; }
    .tb5-net-v { color: #fff; font-size: 15pt; font-weight: 800; display: block; margin-top: 3px; }
    .tb5-footer { border-top: 2px solid #0f3460; }
    .tb5-footer td { background: #f0f4ff; padding: 8px 14px; vertical-align: top; }
    .tb5-footer-l { border-right: 1.5px solid #c7d7f9; }
    .tb5-foot-stamp { background: #0f3460; color: #fff; font-size: 6.5pt; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; padding: 2px 8px; border-radius: 3px; display: inline-block; margin-bottom: 6px; }
    .tb5-sig-line { border-bottom: 2px solid #0f3460; display: block; width: 170px; margin-top: 18px; }
    .tb5-sig-note { font-size: 6pt; color: #64748b; display: block; margin-top: 3px; text-align: center; width: 170px; }
    </style>
    <div class="tb5-wrap">

    <!-- BANNER -->
    <table>
    <tr>
      <td class="tb5-banner" style="width:68%;">
        <div class="tb5-co-tag">Official Payslip Document</div>
        <div class="tb5-co-name">The Big Five Training and Assessment Center</div>
      </td>
      <td class="tb5-banner" style="width:32%; border-left:1px solid #1d4ed8; text-align:center;">
        <div class="tb5-badge">TB5 COPY</div>
        <span class="tb5-badge-sub">Semi-Monthly Payroll</span>
      </td>
    </tr>
    </table>

    <!-- INFO STRIP -->
    <table class="tb5-info-strip">
    <tr>
      <td style="width:36%;">
        <span class="tb5-lbl">Employee</span>
        <span class="tb5-pill-green">${payslip.employee_name}</span>
      </td>
      <td class="tb5-divv" style="width:18%;">
        <span class="tb5-lbl">Status</span>
        <span class="tb5-pill-status">${payslip.status}</span>
      </td>
      <td class="tb5-divv" style="width:28%;">
        <span class="tb5-lbl">Cut-off Period</span>
        <span class="${cutoffClass}">${cutoffStr}</span>
      </td>
      <td class="tb5-divv" style="width:18%;">
        <span class="tb5-lbl">Pay Date</span>
        <span class="tb5-val">${payDateFmt}</span>
      </td>
    </tr>
    </table>

    <!-- BODY -->
    <table class="tb5-body-wrap">
    <tr>

      <!-- EARNINGS -->
      <td class="tb5-pane-earn">
        <div class="tb5-sec tb5-sec-green">Earnings</div>
        <table>
          <tr class="tb5-sub"><td style="width:42%;">Type</td><td style="width:29%;" class="tb5-sub-r">OT Min</td><td style="width:29%;" class="tb5-sub-r">Amount</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Regular Pay</td><td style="text-align:right;color:#94a3b8;">&mdash;</td><td class="tb5-rv">P ${fmt(regularPay)}</td></tr>
          ${otPay > 0
            ? `<tr class="tb5-dr"><td class="tb5-rl">Overtime</td><td style="text-align:right;color:#64748b;">${otMinutes} min</td><td class="tb5-rv">P ${fmt(otPay)}</td></tr>`
            : `<tr class="tb5-dr"><td class="tb5-rl" style="color:#94a3b8;">Overtime</td><td></td><td style="text-align:right;color:#94a3b8;">&mdash;</td></tr>`
          }
        </table>
        <table style="margin-top:2px;">
          <tr class="tb5-sub"><td style="width:70%;">Adjustment</td><td style="width:30%;" class="tb5-sub-r">Amount</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Incentive</td>   <td class="tb5-rv" style="text-align:right;">${amtCell(incentive)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Paid Leaves</td> <td class="tb5-rv" style="text-align:right;">${amtCell(paidLeaves)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Holiday Pay</td> <td class="tb5-rv" style="text-align:right;">${amtCell(holidayPay)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Others</td>      <td class="tb5-rv" style="text-align:right;">${amtCell(othersAdj)}</td></tr>
                    <tr class="tb5-dr"><td class="tb5-rl">Training Pay</td>
                        <td class="tb5-rv" style="text-align:right;">
                            ${amtCell(trainingPay)}
                            ${trainingPay > 0 ? '' : `<div style="font-size:11px;color:#94a3b8;margin-top:4px;">raw: ${debugTrainingRaw}</div>`}
                        </td>
                    </tr>
        </table>
      </td>

      <!-- DEDUCTIONS -->
      <td class="tb5-pane-ded">
        <div class="tb5-sec tb5-sec-red">Deductions</div>
        <table>
          <tr class="tb5-sub"><td style="width:65%;">Description</td><td style="width:35%;" class="tb5-sub-r">Amount</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Withholding Tax</td><td style="text-align:right;">${dedCell(whTax)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">SSS</td>            <td style="text-align:right;">${dedCell(sss)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">PhilHealth</td>     <td style="text-align:right;">${dedCell(philhealth)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Pag-IBIG</td>       <td style="text-align:right;">${dedCell(pagibig)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Late Deduct</td>    <td style="text-align:right;">${dedCell(lateDeduct)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Undertime Deduct</td><td style="text-align:right;">${dedCell(undertimeDeduct)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Halfday Deduct</td> <td style="text-align:right;">${dedCell(halfdayDeduct)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Loan</td>           <td style="text-align:right;">${dedCell(loan)}</td></tr>
          <tr class="tb5-dr"><td class="tb5-rl">Others / C.A.</td>  <td style="text-align:right;">${dedCell(othersCa)}</td></tr>
                                        ${deductionRemarksList.length > 0 ? `<tr class="tb5-dr"><td class="tb5-rl">Remarks</td><td style="text-align:right;">${remarksCell(deductionRemarksList)}</td></tr>` : ''}
        </table>
      </td>

      <!-- SUMMARY -->
      <td class="tb5-pane-sum" style="vertical-align:top;">
        <div class="tb5-sec tb5-sec-navy">Summary</div>
        <div class="tb5-hr-box">
          <span class="tb5-hr-lbl">Hours Rendered</span>
          <table><tr>
            <td class="tb5-hv-g">${fmt(workHours)} hrs</td>
            <td class="tb5-hv-s">/</td>
            <td class="tb5-hv-b">${fmt(otHours)} OT</td>
          </tr></table>
        </div>
        <table class="tb5-sum">
                    <tr><td class="tb5-sl">Gross Pay</td>             <td class="tb5-sv">P ${fmt(grossPay)}</td></tr>
                    <tr><td class="tb5-sl">Government Deductions</td> <td class="tb5-svr">P ${fmt(governmentDeductions)}</td></tr>
        </table>
        <div class="tb5-net-box">
          <span class="tb5-net-t">Net Pay</span>
          <span class="tb5-net-v">P ${fmt(netPay)}</span>
        </div>
      </td>

    </tr>
    </table>

    <!-- FOOTER -->
    <table class="tb5-footer">
    <tr>
      <td class="tb5-footer-l" style="width:50%;">
        <span class="tb5-foot-stamp">Received By</span>
        <span class="tb5-sig-line"></span>
        <span class="tb5-sig-note">Employee Signature &amp; Date</span>
      </td>
      <td style="width:50%;">
        <span class="tb5-foot-stamp">Approved By</span>
        <span class="tb5-sig-line"></span>
        <span class="tb5-sig-note">Danver S. Reyes &mdash; Authorized Signatory</span>
      </td>
    </tr>
    </table>

    </div>`;
}



// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const listModal = document.getElementById('payslipsListModal');
    if (event.target === listModal) {
        closePayslipsListModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePayslipsListModal();
        closePayslipReceiptModal();
    }
});
</script>

<?php
require_once 'include/footer.php';
?>
