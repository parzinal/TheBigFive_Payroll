<?php
/**
 * Staff Payroll List Page
 * Mirrors admin/payroll_list.php output so staff view stays identical to admin.
 */

ob_start();

// Ensure relative includes like include/header.php resolve to staff/include.
set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());

require __DIR__ . '/../admin/payroll_list.php';

$html = ob_get_clean();

$search = [
    'href="dashboard.php"',
    'backup_api.php?action=get_salary_rules',
    'get_employee_dtr_months.php',
    'get_employee_dtr_data.php',
    'get_employee_payslips.php',
    'save_cutoff_payslip.php',
    'generate_payslip_pdf.php?payslip_id=',
];

$replace = [
    'href="dashboard_staff.php"',
    'get_salary_rules.php',
    '../admin/get_employee_dtr_months.php',
    '../admin/get_employee_dtr_data.php',
    '../admin/get_employee_payslips.php',
    '../admin/save_cutoff_payslip.php',
    '../admin/generate_payslip_pdf.php?payslip_id=',
];

$html = str_replace($search, $replace, $html);

$staffLockScript = <<<HTML
<style>
/* Staff is view-only: hide editing and payroll generation controls */
#btn_edit_mode,
#btn_save_dtr,
.btn-header-primary,
.btn-modern.btn-add,
a[href="Generatepayroll.php"] {
    display: none !important;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Staff cannot edit/generate; keep page view-only.
    document.querySelectorAll('.btn-header-primary, .btn-modern.btn-add, a[href="Generatepayroll.php"]').forEach(function (el) {
        el.remove();
    });

    var editBtn = document.getElementById('btn_edit_mode');
    if (editBtn) {
        editBtn.style.display = 'none';
    }

    var saveBtn = document.getElementById('btn_save_dtr');
    if (saveBtn) {
        saveBtn.style.display = 'none';
    }

    // Keep Generate Payslip visible for staff.
});
</script>
HTML;

// Inject only before the final closing </body> to avoid corrupting embedded template strings.
$bodyClosePos = strripos($html, '</body>');
if ($bodyClosePos !== false) {
    $html = substr_replace($html, $staffLockScript . '</body>', $bodyClosePos, 7);
} else {
    $html .= $staffLockScript;
}

echo $html;
exit;

/* === LEGACY CODE BELOW - NO LONGER EXECUTED === */

$page_title = 'Payroll List';
require_once 'include/header.php';
require_once 'include/sidebar.php';
require_once '../config/database.php';

$pdo = getDBConnection();

// Get all employees with DTR records
$stmt = $pdo->query("
    SELECT 
        e.id,
        e.employee_code,
        e.full_name,
        e.position,
        e.basic_monthly_salary,
        e.status,
        COUNT(d.id) as dtr_count,
        MAX(d.dtr_date) as last_dtr_date,
        SUM(CASE WHEN d.is_absent = 1 THEN 1 ELSE 0 END) as absent_days,
        SUM(d.daily_ot_hours) as total_ot_hours
    FROM employees e
    LEFT JOIN dtr_records d ON e.id = d.employee_id
    WHERE e.status = 'active'
    GROUP BY e.id, e.employee_code, e.full_name, e.position, e.basic_monthly_salary, e.status
    ORDER BY e.full_name ASC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-header-left">
                    <div class="page-title-row">
                        <div class="page-icon">
                            <i class="fas fa-list-alt"></i>
                        </div>
                        <div class="page-title-text">
                            <h1>Payroll List</h1>
                            <div class="page-breadcrumb">
                                <a href="dashboard_staff.php"><i class="fas fa-home"></i> Home</a>
                                <span class="page-breadcrumb-separator">/</span>
                                <span>Payroll</span>
                            </div>
                        </div>
                    </div>
                    <p class="page-subtitle">View employee payroll records</p>
                </div>
                <div class="page-header-right">
                    <div class="page-stat-badge">
                        <i class="fas fa-calendar-day"></i>
                        <span><?php echo date('l, F j, Y'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon bg-primary"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo count($employees); ?></span>
                    <span class="stat-label">Total Employees</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-success"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo array_sum(array_column($employees, 'dtr_count')); ?></span>
                    <span class="stat-label">Total DTR Records</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-info"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo number_format(array_sum(array_column($employees, 'total_ot_hours')) ?? 0, 1); ?></span>
                    <span class="stat-label">Total OT Hours</span>
                </div>
            </div>
        </div>

        <!-- Employee Cards Section -->
        <div class="card employee-cards-section">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Employee Payroll Records</h3>
                <div class="header-actions">
                    <button type="button" id="btn_refresh_cards" class="btn-modern btn-sm">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($employees)): ?>
                    <div class="no-cards-message">
                        <i class="fas fa-users"></i><br>
                        No employees found.<br>
                        <small>No DTR data available to display.</small>
                    </div>
                <?php else: ?>
                    <div class="employee-cards-grid" id="employee_cards_container">
                        <?php foreach ($employees as $emp): 
                            $initials = strtoupper(substr($emp['full_name'], 0, 1));
                            $nameParts = explode(' ', $emp['full_name']);
                            if (count($nameParts) > 1) {
                                $initials = strtoupper($nameParts[0][0] . $nameParts[count($nameParts)-1][0]);
                            }
                            
                            // Calculate net pay estimate
                            $salary = floatval($emp['basic_monthly_salary']);
                            $dailyRate = $salary / 30;
                            $daysWorked = intval($emp['dtr_count']) - intval($emp['absent_days']);
                            $grossPay = $daysWorked * $dailyRate;
                            $absentDeduction = intval($emp['absent_days']) * $dailyRate;
                            $otPay = floatval($emp['total_ot_hours']) * ($dailyRate / 8) * 1.25;
                            $netPay = $grossPay + $otPay;
                        ?>
                        <div class="employee-card" onclick="openEmployeeDTRModal(<?php echo $emp['id']; ?>, '<?php echo addslashes($emp['full_name']); ?>')">
                            <div class="employee-card-header">
                                <div class="employee-avatar"><?php echo $initials; ?></div>
                                <div>
                                    <div class="employee-card-name"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                    <div class="employee-card-code"><?php echo htmlspecialchars($emp['employee_code']); ?></div>
                                </div>
                            </div>
                            <div class="employee-card-details">
                                <div class="detail-item">
                                    <span class="detail-label">Position</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($emp['position'] ?: '-'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Basic Salary</span>
                                    <span class="detail-value">₱<?php echo number_format($salary, 2); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Days Worked</span>
                                    <span class="detail-value"><?php echo $daysWorked; ?> days</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">DTR Records</span>
                                    <span class="detail-value"><?php echo $emp['dtr_count']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">OT Hours</span>
                                    <span class="detail-value"><?php echo number_format($emp['total_ot_hours'] ?? 0, 1); ?> hrs</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Absent Days</span>
                                    <span class="detail-value"><?php echo intval($emp['absent_days']); ?></span>
                                </div>
                            </div>
                            <div class="employee-card-footer">
                                <div class="net-pay-badge">
                                    <span class="net-label">Est. Gross:</span>
                                    <span class="net-value">₱<?php echo number_format($netPay, 2); ?></span>
                                </div>
                                <span class="view-dtr-link"><i class="fas fa-eye"></i> View DTR</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Employee DTR View Modal -->
<div id="employeeDTRModal" class="modal-fullview" style="display: none;">
    <div class="modal-fullview-content">
        <div class="modal-fullview-header">
            <h2><i class="fas fa-user"></i> <span id="emp_modal_name">Employee DTR</span></h2>
            <button type="button" class="modal-close-btn" onclick="closeEmployeeDTRModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-fullview-body">
            <div class="month-selector">
                <label>Select Month:</label>
                <select id="dtr_month_select" onchange="loadEmployeeDTRByMonth()">
                    <option value="">-- Select Month --</option>
                </select>
            </div>
            <div id="employee_dtr_content" class="employee-dtr-content">
                <!-- DTR records will be loaded here -->
            </div>
        </div>
        <div class="modal-fullview-footer">
            <button type="button" class="btn-modern btn-success" id="btn_generate_payslip" onclick="generatePayslipFromDTR()" style="display:none;">
                <i class="fas fa-file-invoice-dollar"></i> Generate Payslip
            </button>
            <button type="button" class="btn-modern btn-primary" onclick="printEmployeeDTR()">
                <i class="fas fa-print"></i> Print
            </button>
            <button type="button" class="btn-modern btn-secondary" onclick="closeEmployeeDTRModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<style>
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.stat-icon.bg-primary { background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%); }
.stat-icon.bg-success { background: linear-gradient(135deg, #38a169 0%, #2f855a 100%); }
.stat-icon.bg-info { background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%); }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #2d3748;
}

.stat-label {
    font-size: 13px;
    color: #718096;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* Employee Cards */
.employee-cards-section {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.07);
}

.employee-cards-section .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    border-radius: 12px 12px 0 0;
    color: white;
}

.employee-cards-section .card-header h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.employee-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    padding: 20px;
}

.employee-card {
    background: linear-gradient(145deg, #ffffff 0%, #f7fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.employee-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.12);
    border-color: #2563EB;
}

.employee-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2563EB 0%, #1d4ed8 100%);
}

.employee-card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.employee-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    font-weight: 600;
}

.employee-card-name {
    font-size: 16px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 4px;
}

.employee-card-code {
    font-size: 12px;
    color: #718096;
    font-family: monospace;
}

.employee-card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    font-size: 13px;
    margin-bottom: 15px;
}

.employee-card-details .detail-item {
    display: flex;
    flex-direction: column;
}

.employee-card-details .detail-label {
    color: #a0aec0;
    font-size: 11px;
    text-transform: uppercase;
}

.employee-card-details .detail-value {
    color: #4a5568;
    font-weight: 500;
}

.employee-card-footer {
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.net-pay-badge {
    display: flex;
    flex-direction: column;
}

.net-pay-badge .net-label {
    font-size: 11px;
    color: #718096;
    text-transform: uppercase;
}

.net-pay-badge .net-value {
    font-size: 18px;
    font-weight: 700;
    color: #38a169;
}

.view-dtr-link {
    color: #2563EB;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
}

.no-cards-message {
    text-align: center;
    padding: 60px 20px;
    color: #a0aec0;
}

.no-cards-message i {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
}

.no-cards-message a {
    color: #2563EB;
}

/* Modal Styles */
.modal-fullview {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-fullview-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 95vw;
    max-height: 95vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px rgba(0,0,0,0.3);
}

.modal-fullview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
    border-radius: 16px 16px 0 0;
    color: white;
}

.modal-fullview-header h2 {
    margin: 0;
    font-size: 22px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-close-btn {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    transition: all 0.3s ease;
}

.modal-close-btn:hover {
    background: rgba(255,255,255,0.2);
}

.modal-fullview-body {
    flex: 1;
    overflow: auto;
    padding: 30px;
}

.modal-fullview-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 30px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 0 0 16px 16px;
}

.month-selector {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.month-selector label {
    font-weight: 600;
    color: #4a5568;
}

.month-selector select {
    padding: 10px 20px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    min-width: 200px;
}

.employee-dtr-content {
    min-height: 300px;
}

.modal-employee-info {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 25px;
    padding: 20px;
    background: #f7fafc;
    border-radius: 12px;
}

.modal-employee-info .info-item {
    flex: 1;
    min-width: 150px;
    font-size: 14px;
}

.modal-table-container {
    overflow-x: auto;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}

/* Green rate boxes - matches DTR calculator style */
.modal-rate-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    padding: 12px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    border: 1px solid #dee2e6;
    margin-bottom: 20px;
    align-items: flex-end;
}

.modal-rate-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.modal-rate-label {
    font-size: 9px;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
    letter-spacing: 0.5px;
}

.modal-rate-box {
    display: flex;
    align-items: center;
    background: #00FF00;
    border-radius: 4px;
    padding: 4px 12px;
    border: 2px solid #28a745;
    min-width: 110px;
    justify-content: center;
    box-shadow: 0 0 8px rgba(0,255,0,0.4);
}

.modal-rate-box.modal-rate-box--sm {
    min-width: 80px;
    background: #ffffff;
    border: 2px solid #ddd;
    box-shadow: none;
}

.modal-rate-box.modal-rate-box--sm .modal-peso-sign,
.modal-rate-box.modal-rate-box--sm .modal-rate-value {
    color: #2d3748;
}

.modal-time-box {
    min-width: 70px;
    background: #ffffff;
    border: 2px solid #90cdf4;
    border-radius: 4px;
    padding: 4px 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    color: #2d3748;
}

.modal-peso-sign {
    font-weight: 700;
    color: #d63384;
    font-size: 14px;
    margin-right: 2px;
}

.modal-rate-value {
    font-weight: 700;
    color: #d63384;
    font-size: 14px;
}

/* DTR Table in Modal - matches Generatepayroll.php style */
.tb5-dtr-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
    font-size: 13px;
    background: #fff;
}

.tb5-dtr-table th,
.tb5-dtr-table td {
    border: 1px solid #d8dde6;
    padding: 10px 14px;
    text-align: center;
    white-space: nowrap;
}

.tb5-dtr-table thead th {
    font-weight: 700;
    font-size: 13px;
    letter-spacing: 0.2px;
    color: #fff;
    background: #f7fafc;
}

.tb5-dtr-table tbody td {
    color: #4a5568;
    font-size: 13px;
    background: #fff;
}

.tb5-dtr-table tbody tr:hover td {
    background: #f7fafc;
}

.tb5-dtr-table tbody tr:nth-child(even) td {
    background: #fafbfc;
}

.tb5-dtr-table tbody tr:nth-child(even):hover td {
    background: #f0f4f8;
}

.tb5-dtr-table .col-date {
    background: #ffffff !important;
    color: #2d3748 !important;
    font-weight: 600;
    min-width: 110px;
}

.th-orange {
    background: #ed8936 !important;
    color: #fff !important;
    min-width: 90px;
}

.th-red {
    background: #e53e3e !important;
    color: #fff !important;
    min-width: 80px;
}

.th-halfday {
    background: #805ad5 !important;
    color: #fff !important;
    min-width: 80px;
}

.th-blue {
    background: #4299e1 !important;
    color: #fff !important;
    min-width: 90px;
}

.th-calc {
    background: #f7fafc !important;
    color: #2d3748 !important;
    font-weight: 700;
    min-width: 90px;
}

.th-manual-col {
    background: #FFFFCC !important;
    color: #333 !important;
    font-weight: 700;
    min-width: 90px;
}

.th-auto-salary-col {
    background: #CCFFCC !important;
    color: #333 !important;
    font-weight: 700;
    min-width: 100px;
}

.absent-row td {
    background: #fff5f5 !important;
}

.absent-row:hover td {
    background: #fed7d7 !important;
}

.tb5-dtr-table tfoot td {
    background: #2d3748 !important;
    color: #fff !important;
    font-weight: 700;
    font-size: 13px;
    padding: 12px 14px;
}

.tb5-totals-label {
    text-align: right !important;
    padding-right: 20px !important;
    letter-spacing: 0.5px;
}

.loading-cards {
    text-align: center;
    padding: 40px;
    color: #718096;
}

.btn-modern {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-modern.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
    background: rgba(255,255,255,0.2);
    color: white;
}

.btn-modern.btn-sm:hover {
    background: rgba(255,255,255,0.3);
}

.btn-modern.btn-primary {
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    color: white;
}

.btn-modern.btn-success {
    background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
    color: white;
}

.btn-modern.btn-success:hover {
    background: linear-gradient(135deg, #2f855a 0%, #276749 100%);
}

.btn-modern.btn-secondary {
    background: #e2e8f0;
    color: #4a5568;
}

/* Summary Table */
.summary-table {
    width: 100%;
    margin-top: 20px;
    border-collapse: collapse;
}

.summary-table th, .summary-table td {
    padding: 12px;
    border: 1px solid #e2e8f0;
    text-align: left;
}

.summary-table th {
    background: #f7fafc;
    font-weight: 600;
}

.summary-table .total-row {
    background: #edf2f7;
    font-weight: 700;
}

.summary-table .total-row td {
    color: #2d3748;
}

.net-pay-row {
    background: linear-gradient(135deg, #38a169 0%, #2f855a 100%) !important;
    color: white !important;
}

.net-pay-row td {
    color: white !important;
    font-size: 16px;
}

/* Summary Tables Row Layout */
.summary-tables-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

/* Deduction/Earnings Values */
.deduction-value {
    color: #e53e3e !important;
    font-weight: 600;
}

.earnings-value {
    color: #38a169 !important;
    font-weight: 600;
}

/* Government Deductions Table */
.gov-deductions-table .gov-header {
    background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%) !important;
    color: white !important;
}

.gov-deductions-table td.deduction-value {
    color: #e53e3e;
    font-weight: 600;
    text-align: right;
}

.gov-total-row {
    background: #fed7d7;
}

.gov-total-row td {
    color: #c53030 !important;
}

/* Final Summary Table */
.final-summary {
    margin-top: 20px;
}

.final-summary .total-row {
    background: #edf2f7;
}

@media (max-width: 768px) {
    .summary-tables-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
let currentEmployeeId = null;
let currentMonthKey = null;
let currentPayslipData = null;

document.getElementById('btn_refresh_cards')?.addEventListener('click', function() {
    location.reload();
});

function openEmployeeDTRModal(employeeId, employeeName) {
    currentEmployeeId = employeeId;
    const modal = document.getElementById('employeeDTRModal');
    if (!modal) return;
    
    document.getElementById('emp_modal_name').textContent = employeeName + ' - DTR';
    loadAvailableMonths(employeeId);
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEmployeeDTRModal() {
    const modal = document.getElementById('employeeDTRModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    currentEmployeeId = null;
}

function loadAvailableMonths(employeeId) {
    const select = document.getElementById('dtr_month_select');
    const content = document.getElementById('employee_dtr_content');
    
    select.innerHTML = '<option value="">Loading...</option>';
    content.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i></div>';
    
    fetch(`../admin/get_employee_dtr_months.php?employee_id=${employeeId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.months.length > 0) {
            select.innerHTML = '<option value="">-- Select Month --</option>';
            data.months.forEach(month => {
                const opt = document.createElement('option');
                opt.value = month.month_key;
                opt.textContent = month.month_name + ' (' + month.record_count + ' records)';
                select.appendChild(opt);
            });
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-alt"></i><br>Select a month to view DTR records</div>';
        } else {
            select.innerHTML = '<option value="">No records found</option>';
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-times"></i><br>No DTR records found</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        select.innerHTML = '<option value="">Error loading</option>';
    });
}

function loadEmployeeDTRByMonth() {
    const select = document.getElementById('dtr_month_select');
    const content = document.getElementById('employee_dtr_content');
    const monthKey = select.value;
    
    if (!monthKey || !currentEmployeeId) {
        content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-alt"></i><br>Select a month</div>';
        document.getElementById('btn_generate_payslip').style.display = 'none';
        return;
    }
    
    currentMonthKey = monthKey;
    content.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i></div>';
    
    fetch(`../admin/get_employee_dtr_data.php?employee_id=${currentEmployeeId}&month=${monthKey}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.records.length > 0) {
            content.innerHTML = buildDTRTableWithSummary(data.records, data.employee_info);
            document.getElementById('btn_generate_payslip').style.display = 'inline-flex';
        } else {
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-file-alt"></i><br>No records</div>';
            document.getElementById('btn_generate_payslip').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        content.innerHTML = '<div class="no-cards-message"><i class="fas fa-exclamation-circle"></i><br>Error loading</div>';
        document.getElementById('btn_generate_payslip').style.display = 'none';
    });
}

function buildDTRTableWithSummary(records, empInfo) {
    const salary = parseFloat(empInfo.basic_monthly_salary) || 0;
    const dailyRate = salary / 30;
    const hourlyRate = dailyRate / 8;
    
    let totalDays = records.length;
    let absentDays = 0;
    let totalOtHours = 0;
    let totalLateMinutes = 0;
    let totalUndertimeHours = 0;
    let totalAutoSalary = 0;
    
    // Government deductions - parse from remarks
    let sssAmount = 0;
    let philhealthAmount = 0;
    let pagibigAmount = 0;
    let cashAdvanceAmount = 0;
    let cashAdvanceNote = '';
    
    // Default contribution amounts
    const defaultSSS = 317.50;
    const defaultPhilHealth = 125.00;
    const defaultPagibig = 100.00;
    
    records.forEach(rec => {
        if (rec.is_absent == 1) absentDays++;
        totalOtHours += parseFloat(rec.daily_ot_hours) || 0;
        totalLateMinutes += parseFloat(rec.late_minutes) || 0;
        totalUndertimeHours += parseFloat(rec.undertime_hours) || 0;
        
        // Parse remarks for government contributions
        const remarks = (rec.remarks || '').toUpperCase();
        if (remarks.includes('SSS')) {
            sssAmount = defaultSSS;
        }
        if (remarks.includes('PHILHEALTH') || remarks.includes('PHIL HEALTH')) {
            philhealthAmount = defaultPhilHealth;
        }
        if (remarks.includes('PAGIBIG') || remarks.includes('PAG-IBIG') || remarks.includes('HDMF')) {
            pagibigAmount = defaultPagibig;
        }
        // Cash Advance
        if (remarks.includes('CA ') || remarks.includes('CASH ADVANCE')) {
            const caMatch = rec.remarks.match(/CA\s+([A-Z0-9.,\s]+)/i);
            if (caMatch) {
                cashAdvanceNote = caMatch[1].trim();
            }
            const amountMatch = rec.remarks.match(/(\d+[,.]?\d*)/);
            if (amountMatch && remarks.includes('CA')) {
                const amt = parseFloat(amountMatch[1].replace(',', ''));
                if (amt > 0) cashAdvanceAmount = amt;
            }
            if (cashAdvanceAmount === 0) cashAdvanceAmount = 3000.00;
        }
    });
    
    const daysWorked = totalDays - absentDays;
    const grossPay = daysWorked * dailyRate;
    const absentDeduction = absentDays * dailyRate;
    const lateDeduction = (totalLateMinutes / 60) * hourlyRate;
    const undertimeDeduction = totalUndertimeHours * hourlyRate;
    const otPay = totalOtHours * hourlyRate * 1.25;
    
    const govDeductions = sssAmount + philhealthAmount + pagibigAmount;
    const totalDeductions = absentDeduction + lateDeduction + undertimeDeduction + govDeductions + cashAdvanceAmount;
    const netPay = grossPay - totalDeductions + otPay;
    
    // Store payslip data for generation
    currentPayslipData = {
        employee_id: empInfo.id,
        employee_name: empInfo.full_name,
        employee_code: empInfo.employee_code,
        position: empInfo.position || '',
        basic_monthly_salary: salary,
        per_day_rate: dailyRate,
        per_hour_rate: hourlyRate,
        total_work_days: daysWorked,
        total_work_hours: daysWorked * 8,
        total_late_hours: totalLateMinutes / 60,
        total_undertime_hours: totalUndertimeHours,
        total_ot_hours: totalOtHours,
        total_absent_days: absentDays,
        basic_pay: grossPay,
        ot_pay: otPay,
        late_deduction: lateDeduction,
        undertime_deduction: undertimeDeduction,
        absent_deduction: absentDeduction,
        cash_advance: cashAdvanceAmount,
        sss_contribution: sssAmount,
        philhealth_contribution: philhealthAmount,
        pagibig_contribution: pagibigAmount,
        withholding_tax: 0,
        other_deductions: 0,
        total_earnings: grossPay + otPay,
        total_deductions: totalDeductions,
        net_pay: netPay,
        month_key: currentMonthKey
    };
    
    let html = `
        <div class="modal-employee-info">
            <div class="info-item"><strong>Employee:</strong> ${empInfo.full_name}</div>
            <div class="info-item"><strong>Code:</strong> ${empInfo.employee_code}</div>
            <div class="info-item"><strong>Position:</strong> ${empInfo.position || '-'}</div>
        </div>
        <div class="modal-rate-row">
            <div class="modal-rate-item">
                <span class="modal-rate-label">BASIC SALARY</span>
                <div class="modal-rate-box">
                    <span class="modal-peso-sign">₱</span>
                    <span class="modal-rate-value">${salary.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                </div>
            </div>
            <div class="modal-rate-item">
                <span class="modal-rate-label">PER/DAY</span>
                <div class="modal-rate-box">
                    <span class="modal-peso-sign">₱</span>
                    <span class="modal-rate-value">${dailyRate.toFixed(2)}</span>
                </div>
            </div>
            <div class="modal-rate-item">
                <span class="modal-rate-label">PER/HOUR</span>
                <div class="modal-rate-box modal-rate-box--sm">
                    <span class="modal-peso-sign">₱</span>
                    <span class="modal-rate-value">${hourlyRate.toFixed(2)}</span>
                </div>
            </div>
            <div class="modal-rate-item">
                <span class="modal-rate-label">PER/MIN</span>
                <div class="modal-rate-box modal-rate-box--sm">
                    <span class="modal-peso-sign">₱</span>
                    <span class="modal-rate-value">${(hourlyRate/60).toFixed(4)}</span>
                </div>
            </div>
            <div class="modal-rate-item">
                <span class="modal-rate-label">LATE START</span>
                <div class="modal-time-box">07:35</div>
            </div>
            <div class="modal-rate-item">
                <span class="modal-rate-label">END TIME</span>
                <div class="modal-time-box">17:00</div>
            </div>
        </div>
        
        <div class="modal-table-container">
            <table class="tb5-dtr-table">
                <thead>
                    <tr>
                        <th class="col-date" rowspan="2" style="background:#fff;color:#2d3748;">Date</th>
                        <th class="th-orange" colspan="2">AM</th>
                        <th class="th-orange" colspan="2">PM</th>
                        <th class="th-halfday-yellow" colspan="2">HALFDAY</th>
                        <th class="th-calc" rowspan="2">Work Hrs</th>
                        <th class="th-red" rowspan="2">Absent</th>
                        <th class="th-blue" rowspan="2">OT Out</th>
                        <th class="th-calc" rowspan="2">Late (mins)</th>
                        <th class="th-calc" rowspan="2">Undertime (hrs)</th>
                        <th class="th-calc" rowspan="2">OT (hrs)</th>
                        <th class="th-manual-col" rowspan="2">Government<br>Benefits</th>
                        <th class="th-auto-salary-col" rowspan="2">Net<br>Salary</th>
                        <th class="th-calc" rowspan="2">Remarks</th>
                    </tr>
                    <tr>
                        <th class="th-orange th-sub">IN</th>
                        <th class="th-orange th-sub">OUT</th>
                        <th class="th-orange th-sub">IN</th>
                        <th class="th-orange th-sub">OUT</th>
                        <th class="th-halfday-yellow th-sub">IN</th>
                        <th class="th-halfday-yellow th-sub">OUT</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    records.forEach(rec => {
        const isAbsent = rec.is_absent == 1;
        const lateVal = parseFloat(rec.late_minutes) || 0;
        const utVal = parseFloat(rec.undertime_hours) || 0;
        const otVal = parseFloat(rec.daily_ot_hours) || 0;
        const workHrsVal = parseFloat(rec.total_work_hours) || 0;
        const lateDeductRow = (lateVal / 60) * hourlyRate;
        const utDeductRow = utVal * hourlyRate;
        const otPayRow = otVal * hourlyRate * 1.25;
        const autoSalaryRow = (workHrsVal / 8) * dailyRate - lateDeductRow - utDeductRow + otPayRow;
        totalAutoSalary += autoSalaryRow;
        // Derive govt benefit amount from remarks
        const rowRemarks = (rec.remarks || '').toUpperCase();
        let govtAmountRow = 0;
        if (rowRemarks.includes('SSS') && !rowRemarks.includes('PHILHEALTH')) govtAmountRow = 317.50;
        else if (rowRemarks.includes('PHILHEALTH') || rowRemarks.includes('PHIL HEALTH')) govtAmountRow = 125.00;
        else if (rowRemarks.includes('PAGIBIG') || rowRemarks.includes('PAG-IBIG') || rowRemarks.includes('HDMF')) govtAmountRow = 100.00;
        const govtDisplay = govtAmountRow > 0 ? `<span style="color:#744210;font-weight:600;">${govtAmountRow.toFixed(2)}</span>` : '<span style="color:#a0aec0;">-</span>';
        
        html += `
            <tr class="${isAbsent ? 'absent-row' : ''}">
                <td style="font-weight:600;color:#2d3748;">${rec.dtr_date}</td>
                <td>${rec.am_time_in  ? `<span style="color:#c05621;font-weight:600;">${rec.am_time_in}</span>`  : '<span style="color:#a0aec0;">-</span>'}</td>
                <td>${rec.am_time_out ? `<span style="color:#c05621;font-weight:600;">${rec.am_time_out}</span>` : '<span style="color:#a0aec0;">-</span>'}</td>
                <td>${rec.pm_time_in  ? `<span style="color:#c05621;font-weight:600;">${rec.pm_time_in}</span>`  : '<span style="color:#a0aec0;">-</span>'}</td>
                <td>${rec.pm_time_out ? `<span style="color:#c05621;font-weight:600;">${rec.pm_time_out}</span>` : '<span style="color:#a0aec0;">-</span>'}</td>
                <td>${rec.halfday_in  ? `<span style="color:#6b46c1;font-weight:600;">${rec.halfday_in}</span>`  : '<span style="color:#a0aec0;">-</span>'}</td>
                <td>${rec.halfday_out ? `<span style="color:#6b46c1;font-weight:600;">${rec.halfday_out}</span>` : '<span style="color:#a0aec0;">-</span>'}</td>
                <td>${parseFloat(rec.total_work_hours) > 0 ? `<span style="color:#2d3748;font-weight:600;">${parseFloat(rec.total_work_hours).toFixed(2)}</span>` : '<span style="color:#a0aec0;">0.00</span>'}</td>
                <td>${isAbsent ? '<i class="fas fa-times-circle" style="color:#e53e3e;font-size:16px;"></i>' : '<span style="color:#a0aec0;">-</span>'}</td>
                <td>${rec.ot_time_out ? `<span style="color:#2b6cb0;font-weight:600;">${rec.ot_time_out}</span>` : '<span style="color:#a0aec0;">-</span>'}</td>
                <td>${lateVal > 0 ? `<span style="color:#e53e3e;font-weight:600;">${lateVal}</span>` : '0'}</td>
                <td>${utVal > 0 ? `<span style="color:#dd6b20;font-weight:600;">${utVal.toFixed(2)}</span>` : '0.00'}</td>
                <td>${otVal > 0 ? `<span style="color:#38a169;font-weight:600;">${otVal.toFixed(2)}</span>` : '0.00'}</td>
                <td>${govtDisplay}</td>
                <td><span style="color:${autoSalaryRow >= 0 ? '#276749' : '#e53e3e'};font-weight:600;">&#8369;${autoSalaryRow.toFixed(2)}</span></td>
                <td style="text-align:left;">${rec.remarks || '<span style="color:#a0aec0;">-</span>'}</td>
            </tr>
        `;
    });

    html += `
        </tbody>
        <tfoot>
            <tr>
                <td class="tb5-totals-label" colspan="8">TOTALS</td>
                <td>${absentDays > 0 ? absentDays + ' day(s)' : '-'}</td>
                <td>-</td>
                <td>${totalLateMinutes > 0 ? totalLateMinutes.toFixed(0) : '0'}</td>
                <td>${totalUndertimeHours > 0 ? totalUndertimeHours.toFixed(2) : '0.00'}</td>
                <td>${totalOtHours > 0 ? totalOtHours.toFixed(2) : '0.00'}</td>
                <td>-</td>
                <td>&#8369;${totalAutoSalary.toFixed(2)}</td>
                <td>-</td>
            </tr>
        </tfoot>
    `;

    html += `
            </table>
        </div>
        
        <div class="summary-tables-row">
            <table class="summary-table">
                <thead>
                    <tr>
                        <th colspan="2">Payroll Summary</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Days Office (Worked)</td>
                        <td>${daysWorked} days</td>
                    </tr>
                    <tr>
                        <td>Absent Days</td>
                        <td>${absentDays} days</td>
                    </tr>
                    <tr>
                        <td>Total OT Hours</td>
                        <td>${totalOtHours.toFixed(2)} hrs</td>
                    </tr>
                    <tr>
                        <td>Gross Pay (${daysWorked} × ₱${dailyRate.toFixed(2)})</td>
                        <td>₱${grossPay.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    </tr>
                    <tr>
                        <td>Absent Deduction</td>
                        <td class="deduction-value">-₱${absentDeduction.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    </tr>
                    <tr>
                        <td>Late Deduction</td>
                        <td class="deduction-value">-₱${lateDeduction.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    </tr>
                    <tr>
                        <td>Undertime Deduction</td>
                        <td class="deduction-value">-₱${undertimeDeduction.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    </tr>
                    <tr>
                        <td>OT Pay</td>
                        <td class="earnings-value">+₱${otPay.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    </tr>
                </tbody>
            </table>
            
            <table class="summary-table gov-deductions-table">
                <thead>
                    <tr>
                        <th colspan="2" class="gov-header">Government Deductions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>SSS</td>
                        <td class="deduction-value">${sssAmount > 0 ? sssAmount.toFixed(2) : '0.00'}</td>
                    </tr>
                    <tr>
                        <td>PhilHealth</td>
                        <td class="deduction-value">${philhealthAmount > 0 ? philhealthAmount.toFixed(2) : '0.00'}</td>
                    </tr>
                    <tr>
                        <td>Pag-IBIG</td>
                        <td class="deduction-value">${pagibigAmount > 0 ? pagibigAmount.toFixed(2) : '0.00'}</td>
                    </tr>
                    <tr>
                        <td>Cash Advance ${cashAdvanceNote ? '(' + cashAdvanceNote + ')' : ''}</td>
                        <td class="deduction-value">${cashAdvanceAmount > 0 ? cashAdvanceAmount.toLocaleString('en-PH', {minimumFractionDigits: 2}) : '0.00'}</td>
                    </tr>
                    <tr class="gov-total-row">
                        <td><strong>Total Gov Deductions</strong></td>
                        <td class="deduction-value"><strong>${(govDeductions + cashAdvanceAmount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <table class="summary-table final-summary">
            <tbody>
                <tr class="total-row">
                    <td>Total All Deductions</td>
                    <td class="deduction-value">-₱${totalDeductions.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                </tr>
                <tr class="net-pay-row">
                    <td><strong>NET PAY</strong></td>
                    <td><strong>₱${netPay.toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong></td>
                </tr>
            </tbody>
        </table>
    `;
    
    return html;
}

function printEmployeeDTR() {
    const content = document.getElementById('employee_dtr_content').innerHTML;
    const employeeName = document.getElementById('emp_modal_name')?.textContent || 'Employee';
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>DTR - ${employeeName}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { border-collapse: collapse; width: 100%; font-size: 11px; margin-bottom: 20px; }
                th, td { border: 1px solid #333; padding: 6px; text-align: center; }
                th { background: #f0f0f0; }
                .th-orange { background: #ed8936 !important; color: white !important; }
                .th-red { background: #e53e3e !important; color: white !important; }
                .th-blue { background: #4299e1 !important; color: white !important; }
                .th-halfday { background: #805ad5 !important; color: white !important; }
                .th-manual-col { background: #FFFFCC !important; color: #333 !important; font-weight: 700; }
                .th-auto-salary-col { background: #CCFFCC !important; color: #333 !important; font-weight: 700; }
                .th-calc { background: #f7fafc !important; color: #2d3748 !important; font-weight: 700; }
                .col-date { background: #fff !important; color: #2d3748 !important; font-weight: 700; }
                .absent-row td { background: #fff5f5 !important; color: #c53030 !important; }
                tfoot td { background: #2d3748 !important; color: #fff !important; font-weight: 700; }
                .tb5-totals-label { text-align: right !important; font-weight: 700; }
                .modal-employee-info { display: flex; gap: 20px; margin-bottom: 15px; padding: 15px; background: #f5f5f5; flex-wrap: wrap; }
                .modal-rate-row { display: flex; gap: 15px; flex-wrap: wrap; padding: 10px 15px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 15px; align-items: flex-end; }
                .modal-rate-item { display: flex; flex-direction: column; align-items: center; }
                .modal-rate-label { font-size: 8px; color: #666; text-transform: uppercase; font-weight: 600; margin-bottom: 3px; letter-spacing: 0.5px; }
                .modal-rate-box { display: flex; align-items: center; background: #00FF00 !important; border: 2px solid #28a745; border-radius: 4px; padding: 3px 10px; min-width: 100px; justify-content: center; }
                .modal-rate-box.modal-rate-box--sm { background: #fff !important; border: 2px solid #ddd; min-width: 70px; }
                .modal-rate-box.modal-rate-box--sm .modal-peso-sign, .modal-rate-box.modal-rate-box--sm .modal-rate-value { color: #2d3748 !important; }
                .modal-peso-sign, .modal-rate-value { font-weight: 700; color: #d63384 !important; font-size: 13px; }
                .net-pay-row { background: #38a169 !important; color: white !important; }
                .net-pay-row td { color: white !important; }
                .summary-tables-row { display: flex; gap: 20px; margin-top: 20px; }
                .summary-tables-row table { flex: 1; }
                .deduction-value { color: #e53e3e; font-weight: 600; }
                .earnings-value { color: #38a169; font-weight: 600; }
                .gov-header { background: #e53e3e !important; color: white !important; }
                .gov-total-row { background: #fed7d7; }
                .gov-total-row td { color: #c53030 !important; }
                .final-summary { margin-top: 20px; }
                h1 { text-align: center; margin-bottom: 20px; }
                @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
            </style>
        </head>
        <body>
            <h1>Daily Time Record - ${employeeName}</h1>
            ${content}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEmployeeDTRModal();
    }
});

// Close modal on outside click
document.getElementById('employeeDTRModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEmployeeDTRModal();
    }
});

// Generate Payslip from DTR
function generatePayslipFromDTR() {
    if (!currentPayslipData || !currentEmployeeId || !currentMonthKey) {
        alert('No DTR data available. Please select a month first.');
        return;
    }
    
    const btn = document.getElementById('btn_generate_payslip');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    
    // Send payslip data to backend
    fetch('save_payslip.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(currentPayslipData)
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            alert('Payslip generated successfully!\n\nEmployee: ' + currentPayslipData.employee_name + '\nNet Pay: ₱' + currentPayslipData.net_pay.toLocaleString('en-PH', {minimumFractionDigits: 2}));
            // Optionally redirect to payslip history
            if (confirm('Would you like to view the payslip history?')) {
                window.location.href = 'payslip_history.php';
            }
        } else {
            alert('Error generating payslip: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error generating payslip. Please try again.');
    });
}
</script>

<?php require_once 'include/footer.php'; ?>
