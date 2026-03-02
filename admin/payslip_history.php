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

// Get all employees with payroll computations grouped
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
    max-width: 480px;
    width: 100%;
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

.receipt-barcode {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px dashed #e2e8f0;
}

.receipt-barcode-lines {
    display: flex;
    justify-content: center;
    gap: 2px;
    margin-bottom: 8px;
}

.barcode-line {
    width: 2px;
    height: 40px;
    background: #1e293b;
}

.barcode-line:nth-child(2n) {
    width: 1px;
}

.barcode-line:nth-child(3n) {
    width: 3px;
}

.receipt-barcode-text {
    text-align: center;
    font-size: 10px;
    color: #94a3b8;
    letter-spacing: 2px;
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
        .then(response => response.json())
        .then(data => {
            if (data.success && data.payslips.length > 0) {
                let html = '<div class="payslip-list-grid">';
                
                data.payslips.forEach((payslip, index) => {
                    html += `
                        <div class="payslip-list-item">
                            <div class="payslip-list-info">
                                <h4 class="payslip-list-period">${payslip.period_name}</h4>
                                <div class="payslip-list-meta">
                                    <div class="payslip-list-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        Generated: ${formatDate(payslip.created_at)}
                                    </div>
                                    <div class="payslip-list-meta-item">
                                        <i class="fas fa-money-bill-wave"></i>
                                        Net Pay: <strong>₱${parseFloat(payslip.net_pay || 0).toFixed(2)}</strong>
                                    </div>
                                    <div class="payslip-list-meta-item">
                                        <i class="fas fa-info-circle"></i>
                                        Status: <strong>${payslip.status.toUpperCase()}</strong>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-view-payslip" onclick='viewPayslipReceipt(${JSON.stringify(payslip).replace(/'/g, "&apos;")})'>
                                <i class="fas fa-receipt"></i> View Payslip
                            </button>
                        </div>
                    `;
                });
                
                html += '</div>';
                container.innerHTML = html;
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
            console.error('Error:', error);
            container.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error loading payslips. Please try again.</p>
                </div>
            `;
        });
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

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatShortDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Generate receipt-style HTML
function generateReceiptHTML(payslip) {
    const receiptId = `PSL-${payslip.id}-${new Date(payslip.created_at).getTime()}`;
    
    return `
        <div class="receipt-payslip">
            <!-- Header -->
            <div class="receipt-header">
                <h1 class="receipt-company">TheBigFive</h1>
                <p class="receipt-title">Payroll Receipt</p>
            </div>
            
            <!-- Employee Info -->
            <div class="receipt-section">
                <div class="receipt-label">Employee Information</div>
                <div class="receipt-info">
                    <div class="receipt-info-row">
                        <span class="receipt-info-label">Name:</span>
                        <span class="receipt-info-value">${payslip.employee_name}</span>
                    </div>
                    <div class="receipt-info-row">
                        <span class="receipt-info-label">ID:</span>
                        <span class="receipt-info-value">${payslip.employee_code}</span>
                    </div>
                    <div class="receipt-info-row">
                        <span class="receipt-info-label">Position:</span>
                        <span class="receipt-info-value">${payslip.position || 'N/A'}</span>
                    </div>
                </div>
            </div>
            
            <!-- Pay Period -->
            <div class="receipt-section">
                <div class="receipt-label">Pay Period</div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Period:</span>
                    <span class="receipt-row-value">${payslip.period_name}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">From - To:</span>
                    <span class="receipt-row-value">${formatShortDate(payslip.start_date)} - ${formatShortDate(payslip.end_date)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Pay Date:</span>
                    <span class="receipt-row-value">${formatShortDate(payslip.pay_date)}</span>
                </div>
            </div>
            
            <!-- Work Summary -->
            <div class="receipt-section">
                <div class="receipt-label">Work Summary</div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Days Worked:</span>
                    <span class="receipt-row-value">${parseFloat(payslip.total_work_days || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Hours Worked:</span>
                    <span class="receipt-row-value">${parseFloat(payslip.total_work_hours || 0).toFixed(2)} hrs</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Overtime Hours:</span>
                    <span class="receipt-row-value">${parseFloat(payslip.total_ot_hours || 0).toFixed(2)} hrs</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Late Hours:</span>
                    <span class="receipt-row-value">${parseFloat(payslip.total_late_hours || 0).toFixed(2)} hrs</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Absences:</span>
                    <span class="receipt-row-value">${parseFloat(payslip.total_absent_days || 0).toFixed(2)} days</span>
                </div>
            </div>
            
            <!-- Earnings -->
            <div class="receipt-section">
                <div class="receipt-label">Earnings</div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Basic Pay:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.basic_pay || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Overtime Pay:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.ot_pay || 0).toFixed(2)}</span>
                </div>
                <hr class="receipt-divider">
                <div class="receipt-row" style="font-weight: 700;">
                    <span class="receipt-row-label">TOTAL EARNINGS:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.total_earnings || 0).toFixed(2)}</span>
                </div>
            </div>
            
            <!-- Deductions -->
            <div class="receipt-section">
                <div class="receipt-label">Deductions</div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Late Deduction:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.late_deduction || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Undertime:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.undertime_deduction || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Absences:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.absent_deduction || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">SSS:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.sss_contribution || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">PhilHealth:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.philhealth_contribution || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Pag-IBIG:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.pagibig_contribution || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Withholding Tax:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.withholding_tax || 0).toFixed(2)}</span>
                </div>
                ${parseFloat(payslip.cash_advance || 0) > 0 ? `
                <div class="receipt-row">
                    <span class="receipt-row-label">Cash Advance:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.cash_advance || 0).toFixed(2)}</span>
                </div>
                ` : ''}
                <hr class="receipt-divider">
                <div class="receipt-row" style="font-weight: 700;">
                    <span class="receipt-row-label">TOTAL DEDUCTIONS:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.total_deductions || 0).toFixed(2)}</span>
                </div>
            </div>
            
            <!-- Net Pay -->
            <div class="receipt-total">
                <div>
                    <div class="receipt-total-label">NET PAY</div>
                    <div style="font-size: 10px; opacity: 0.8; margin-top: 2px;">Amount to Receive</div>
                </div>
                <div class="receipt-total-value">₱${parseFloat(payslip.net_pay || 0).toFixed(2)}</div>
            </div>
            
            <!-- Footer -->
            <div class="receipt-footer">
                <p class="receipt-footer-text">This is a computer-generated payslip.</p>
                <p class="receipt-footer-text">No signature required.</p>
                <p class="receipt-footer-text" style="margin-top: 10px;">Generated: ${formatDate(payslip.created_at)}</p>
                
                <!-- Barcode -->
                <div class="receipt-barcode">
                    <div class="receipt-barcode-lines">
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                    </div>
                    <p class="receipt-barcode-text">${receiptId}</p>
                </div>
                
                <p class="receipt-footer-text" style="margin-top: 15px;">© ${new Date().getFullYear()} TheBigFive Payroll System</p>
                <p class="receipt-footer-text">Thank you!</p>
            </div>
        </div>
    `;
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
