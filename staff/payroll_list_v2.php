<?php
/**
 * Staff Payroll List Page
 * Display all employees with DTR records and their payroll summaries (read-only)
 * Mirrors admin payroll_list.php functionality without edit/generate capabilities
 */

$page_title = 'Payroll List';
require_once 'include/header.php';
require_once 'include/sidebar.php';
require_once '../config/database.php';

$pdo = getDBConnection();

// Get all employees with DTR records and their latest net salary from payroll computations
// Same complex query as admin version for accurate data display
$stmt = $pdo->query("
    SELECT 
        e.id,
        e.employee_code,
        e.full_name,
        e.position,
        e.department,
        e.basic_monthly_salary,
        e.classification,
        e.status,
        COUNT(d.id) as dtr_count,
        MAX(d.dtr_date) as last_dtr_date,
        SUM(CASE WHEN d.is_absent = 1 THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN d.is_absent = 0 AND COALESCE(d.is_training, 0) = 0 AND (d.am_time_in IS NOT NULL AND d.am_time_in != '') THEN 1 ELSE 0 END) as actual_days_worked,
        SUM(d.daily_ot_hours) as total_ot_hours,
        (
            COALESCE((SELECT pc2.basic_pay FROM payroll_computations pc2 WHERE pc2.employee_id = e.id AND (pc2.other_deductions_notes IS NULL OR pc2.other_deductions_notes NOT LIKE '%\"cutoff_type\"%') ORDER BY pc2.computed_at DESC, pc2.id DESC LIMIT 1), 0)
            + COALESCE((SELECT pc2.ot_pay FROM payroll_computations pc2 WHERE pc2.employee_id = e.id AND (pc2.other_deductions_notes IS NULL OR pc2.other_deductions_notes NOT LIKE '%\"cutoff_type\"%') ORDER BY pc2.computed_at DESC, pc2.id DESC LIMIT 1), 0)
            + COALESCE((SELECT pc2.trainings_cost FROM payroll_computations pc2 WHERE pc2.employee_id = e.id AND (pc2.other_deductions_notes IS NULL OR pc2.other_deductions_notes NOT LIKE '%\"cutoff_type\"%') ORDER BY pc2.computed_at DESC, pc2.id DESC LIMIT 1), 0)
            - COALESCE((SELECT pc2.late_deduction FROM payroll_computations pc2 WHERE pc2.employee_id = e.id AND (pc2.other_deductions_notes IS NULL OR pc2.other_deductions_notes NOT LIKE '%\"cutoff_type\"%') ORDER BY pc2.computed_at DESC, pc2.id DESC LIMIT 1), 0)
            - COALESCE((SELECT pc2.undertime_deduction FROM payroll_computations pc2 WHERE pc2.employee_id = e.id AND (pc2.other_deductions_notes IS NULL OR pc2.other_deductions_notes NOT LIKE '%\"cutoff_type\"%') ORDER BY pc2.computed_at DESC, pc2.id DESC LIMIT 1), 0)
            - COALESCE((SELECT pc2.sss_contribution FROM payroll_computations pc2 WHERE pc2.employee_id = e.id AND (pc2.other_deductions_notes IS NULL OR pc2.other_deductions_notes NOT LIKE '%\"cutoff_type\"%') ORDER BY pc2.computed_at DESC, pc2.id DESC LIMIT 1), 0)
            - COALESCE((SELECT pc2.philhealth_contribution FROM payroll_computations pc2 WHERE pc2.employee_id = e.id AND (pc2.other_deductions_notes IS NULL OR pc2.other_deductions_notes NOT LIKE '%\"cutoff_type\"%') ORDER BY pc2.computed_at DESC, pc2.id DESC LIMIT 1), 0)
            - COALESCE((SELECT pc2.pagibig_contribution FROM payroll_computations pc2 WHERE pc2.employee_id = e.id AND (pc2.other_deductions_notes IS NULL OR pc2.other_deductions_notes NOT LIKE '%\"cutoff_type\"%') ORDER BY pc2.computed_at DESC, pc2.id DESC LIMIT 1), 0)
        ) as latest_net_pay,
        (
            SELECT pp.period_name
            FROM payroll_periods pp
            JOIN payroll_computations pc2 ON pp.id = pc2.payroll_period_id
            WHERE pc2.employee_id = e.id
              AND (pc2.other_deductions_notes IS NULL OR pc2.other_deductions_notes NOT LIKE '%\"cutoff_type\"%')
            ORDER BY pc2.computed_at DESC, pc2.id DESC
            LIMIT 1
        ) as latest_period_name
    FROM employees e
    LEFT JOIN dtr_records d ON e.id = d.employee_id
    LEFT JOIN (
        SELECT pc2.employee_id, pc2.id, pc2.payroll_period_id, pc2.ot_pay, pc2.trainings_cost,
               pc2.sss_contribution, pc2.philhealth_contribution, pc2.pagibig_contribution
        FROM (
            SELECT pc3.*, ROW_NUMBER() OVER (PARTITION BY pc3.employee_id ORDER BY pc3.computed_at DESC, pc3.id DESC) as rn
            FROM payroll_computations pc3
            WHERE (pc3.other_deductions_notes IS NULL OR pc3.other_deductions_notes NOT LIKE '%\"cutoff_type\"%')
        ) pc2
        WHERE pc2.rn = 1
    ) pc ON pc.employee_id = e.id
    LEFT JOIN payroll_periods pp ON pc.payroll_period_id = pp.id
    WHERE e.status = 'active'
    GROUP BY e.id, e.employee_code, e.full_name, e.position, e.department, e.basic_monthly_salary, e.classification, e.status
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
                            
                            $salary = floatval($emp['basic_monthly_salary']);
                            $netPay = floatval($emp['latest_net_pay'] ?? 0);
                            $daysWorked = intval($emp['actual_days_worked'] ?? 0);
                            
                            if ($netPay <= 0) {
                                $dailyRate = $salary / 15;
                                $grossPay = $daysWorked * $dailyRate;
                                $otPay = floatval($emp['total_ot_hours']) * ($dailyRate / 8) * 1.25;
                                $netPay = $grossPay + $otPay;
                            }
                        ?>
                        <?php
                            $empClassification = strtolower(str_replace(' ', '', trim($emp['classification'] ?? '')));
                            $classLabel = ($empClassification === 'trainer') ? 'TRAINER' : 'FIXED RATE';
                            $classBadgeClass = ($empClassification === 'trainer') ? 'badge-trainer' : 'badge-fixedrate';
                        ?>
                        <div class="employee-card" data-employee-id="<?php echo $emp['id']; ?>" onclick="openEmployeeDTRModal(<?php echo intval($emp['id']); ?>, <?php echo json_encode($emp['full_name']); ?>)">
                            <div class="employee-card-header">
                                <div class="employee-avatar"><?php echo $initials; ?></div>
                                <div>
                                    <div class="employee-card-name"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                    <div class="employee-card-code"><?php echo htmlspecialchars($emp['employee_code']); ?></div>
                                </div>
                                <span class="classification-badge <?php echo $classBadgeClass; ?>"><?php echo $classLabel; ?></span>
                            </div>
                            <div class="employee-card-details">
                                <div class="detail-item">
                                    <span class="detail-label">Position</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($emp['position'] ?: '-'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Department</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($emp['department'] ?: '-'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Per Day</span>
                                    <?php $perDayDisplay = $salary > 0 ? ($salary / 26) : 0; ?>
                                    <span class="detail-value">₱<?php echo number_format($perDayDisplay, 2); ?></span>
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
                            </div>
                            <div class="employee-card-footer">
                                <div class="net-pay-badge" 
                                     <?php if (!empty($emp['latest_period_name'])): ?>
                                     title="From: <?php echo htmlspecialchars($emp['latest_period_name']); ?>"
                                     <?php endif; ?>>
                                    <span class="net-label">Final Net Pay:</span>
                                    <span class="net-value">₱<?php echo number_format($netPay, 2); ?></span>
                                    <?php if (!empty($emp['latest_period_name'])): ?>
                                    <small class="period-badge"><?php echo htmlspecialchars($emp['latest_period_name']); ?></small>
                                    <?php endif; ?>
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

<!-- Employee DTR View Modal - Read-Only DTR Calculator Style -->
<div id="employeeDTRModal" class="modal-fullview" style="display: none;">
    <div class="modal-fullview-content" style="max-width: 98vw;">
        <div class="modal-fullview-header">
            <h2><i class="fas fa-user"></i> <span id="emp_modal_name">Employee DTR</span></h2>
            <button type="button" class="modal-close-btn" onclick="closeEmployeeDTRModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-fullview-body" style="padding: 20px;">
            <!-- Period Selector -->
            <div class="period-selector-row">
                <div class="period-select-group">
                    <label>Select Period:</label>
                    <select id="dtr_month_select" onchange="loadEmployeeDTRByMonth()">
                        <option value="">-- Select Period --</option>
                    </select>
                </div>
            </div>
            
            <!-- DTR Calculator Content -->
            <div id="employee_dtr_content" class="employee-dtr-content">
                <!-- Will be populated by JS -->
            </div>
        </div>
        <div class="modal-fullview-footer">
            <button type="button" class="btn-modern btn-primary" id="btn_print_dtr" onclick="exportDTRAsPDF()" style="display:none;">
                <i class="fas fa-file-pdf"></i> Export as PDF
            </button>
            <button type="button" class="btn-modern btn-success" id="btn_generate_payslip" onclick="showPayslipCutoffSelector()" style="display:none;">
                <i class="fas fa-file-invoice-dollar"></i> Generate Payslip
            </button>
            <button type="button" class="btn-modern btn-secondary" onclick="closeEmployeeDTRModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>

        <!-- Cutoff Period Selector Modal -->
        <div id="cutoffSelectorOverlay" class="cutoff-overlay" style="display:none;" onclick="if(event.target===this)closeCutoffSelector()">
            <div class="cutoff-modal">
                <div class="cutoff-header">
                    <h3><i class="fas fa-calendar-alt"></i> Select Cutoff Period</h3>
                    <button class="cutoff-close" onclick="closeCutoffSelector()">&times;</button>
                </div>
                <div class="cutoff-body">
                    <p class="cutoff-desc">Choose which cutoff period to include in the payslip:</p>
                    <div class="cutoff-options">
                        <label class="cutoff-option">
                            <input type="radio" name="cutoff_period" value="first" checked>
                            <div class="cutoff-card">
                                <i class="fas fa-calendar-day"></i>
                                <span class="cutoff-title">1st Cutoff</span>
                                <span class="cutoff-range">Day 1 - 15</span>
                                <span class="cutoff-days" id="cutoff_first_days"></span>
                            </div>
                        </label>
                        <label class="cutoff-option">
                            <input type="radio" name="cutoff_period" value="second">
                            <div class="cutoff-card">
                                <i class="fas fa-calendar-check"></i>
                                <span class="cutoff-title">2nd Cutoff</span>
                                <span class="cutoff-range">Day 16 - End</span>
                                <span class="cutoff-days" id="cutoff_second_days"></span>
                            </div>
                        </label>
                        <label class="cutoff-option">
                            <input type="radio" name="cutoff_period" value="full">
                            <div class="cutoff-card">
                                <i class="fas fa-calendar"></i>
                                <span class="cutoff-title">Full Month</span>
                                <span class="cutoff-range">All Days</span>
                                <span class="cutoff-days" id="cutoff_full_days"></span>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="cutoff-footer">
                    <button type="button" class="btn-modern btn-secondary" onclick="closeCutoffSelector()">Cancel</button>
                    <button type="button" class="btn-modern btn-success" onclick="generatePayslipForCutoff()">
                        <i class="fas fa-file-pdf"></i> Generate Payslip
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    function attachModalAutoLoad(){
        document.querySelectorAll('.employee-card').forEach(function(card){
            card.addEventListener('click', function(e){
                var empId = this.getAttribute('data-employee-id') || this.dataset.employeeId;
                try{ currentEmployeeId = parseInt(empId) || empId; } catch(err) { currentEmployeeId = empId; }
                var nameEl = this.querySelector('.employee-card-name');
                var empName = (nameEl && nameEl.textContent) ? nameEl.textContent.trim() : 'Employee';
                var modal = document.getElementById('employeeDTRModal');
                if (!modal) return;
                try { document.getElementById('emp_modal_name').textContent = empName + ' - DTR'; } catch(e) {}
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                if (typeof loadAvailableMonths === 'function') {
                    try { loadAvailableMonths(empId); } catch(e) { console.warn('loadAvailableMonths error', e); }
                }
            }, {capture: false});
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attachModalAutoLoad); else attachModalAutoLoad();
})();
</script>

<?php
// Extract CSS from admin payroll_list.php for consistent TB5 DTR styling
$_adminSource = @file_get_contents(__DIR__ . '/../admin/payroll_list.php');
if ($_adminSource && preg_match('/<style>(.*?)<\/style>/s', $_adminSource, $_cssM)) {
    echo '<style>' . $_cssM[1] . '</style>';
}
unset($_adminSource, $_cssM);
?>

<script>
let currentEmployeeId = null;
let currentPeriodId   = null;
let currentDTRRecords = [];
let currentEmpInfo    = null;
let currentComp       = null;

/* ====== Custom Modal Functions ====== */
function showCustomConfirm(message, title = 'Confirm Action') {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'custom-modal-overlay active';
        overlay.innerHTML = `
            <div class="custom-modal">
                <div class="custom-modal-header">
                    <i class="fas fa-question-circle"></i>
                    <h3>${title}</h3>
                </div>
                <div class="custom-modal-body">${message}</div>
                <div class="custom-modal-footer">
                    <button class="modal-btn modal-btn-secondary" id="modalCancelBtn"><i class="fas fa-times"></i> Cancel</button>
                    <button class="modal-btn modal-btn-primary" id="modalConfirmBtn"><i class="fas fa-check"></i> Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.querySelector('#modalConfirmBtn').onclick = () => { document.body.removeChild(overlay); resolve(true); };
        overlay.querySelector('#modalCancelBtn').onclick = () => { document.body.removeChild(overlay); resolve(false); };
        overlay.onclick = (e) => { if (e.target === overlay) { document.body.removeChild(overlay); resolve(false); } };
    });
}

function showCustomAlert(message, title = 'Notice', type = 'info') {
    return new Promise((resolve) => {
        const iconMap = { success: 'fa-check-circle', warning: 'fa-exclamation-triangle', error: 'fa-times-circle', info: 'fa-info-circle' };
        const overlay = document.createElement('div');
        overlay.className = 'custom-modal-overlay active';
        overlay.innerHTML = `
            <div class="custom-modal ${type}">
                <div class="custom-modal-header">
                    <i class="fas ${iconMap[type] || iconMap.info}"></i>
                    <h3>${title}</h3>
                </div>
                <div class="custom-modal-body">${message}</div>
                <div class="custom-modal-footer">
                    <button class="modal-btn modal-btn-primary" id="modalOkBtn"><i class="fas fa-check"></i> OK</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.querySelector('#modalOkBtn').onclick = () => { document.body.removeChild(overlay); resolve(); };
        overlay.onclick = (e) => { if (e.target === overlay) { document.body.removeChild(overlay); resolve(); } };
    });
}

/* ====== Modal Management ====== */
function openEmployeeDTRModal(empId, empName) {
    currentEmployeeId = empId;
    currentPeriodId = null;
    currentDTRRecords = [];
    currentEmpInfo = null;
    currentComp = null;

    const modal = document.getElementById('employeeDTRModal');
    if (!modal) return;

    document.getElementById('emp_modal_name').textContent = empName + ' - DTR';

    const printBtn = document.getElementById('btn_print_dtr');
    const payslipBtn = document.getElementById('btn_generate_payslip');
    if (printBtn) printBtn.style.display = 'none';
    if (payslipBtn) payslipBtn.style.display = 'none';

    loadAvailableMonths(empId);
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function loadAvailableMonths(employeeId) {
    const select = document.getElementById('dtr_month_select');
    const content = document.getElementById('employee_dtr_content');

    select.innerHTML = '<option value="">Loading...</option>';
    content.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i></div>';

    fetch(`../admin/get_employee_dtr_months.php?employee_id=${employeeId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.months && data.months.length > 0) {
            select.innerHTML = '<option value="">-- Select Period --</option>';
            data.months.forEach(month => {
                const opt = document.createElement('option');
                if (month.period_id) {
                    opt.value = 'pid:' + month.period_id;
                } else if (month.date_range) {
                    opt.value = 'range:' + month.date_range;
                } else {
                    opt.value = month.month_key;
                }
                opt.textContent = month.period_name || month.month_name || month.month_key;
                if (month.record_count) opt.textContent += ' (' + month.record_count + ' records)';
                select.appendChild(opt);
            });
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-alt"></i><br>Select a period to view DTR records</div>';
        } else {
            select.innerHTML = '<option value="">No records found</option>';
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-times"></i><br>No DTR records found</div>';
        }
    })
    .catch(error => {
        console.error('Error loading months:', error);
        select.innerHTML = '<option value="">Error loading</option>';
    });
}

function loadEmployeeDTRByMonth() {
    const select = document.getElementById('dtr_month_select');
    const content = document.getElementById('employee_dtr_content');
    const monthKey = select.value;

    if (!monthKey || !currentEmployeeId) {
        content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-alt"></i><br>Select a period</div>';
        const printBtn = document.getElementById('btn_print_dtr');
        const payslipBtn = document.getElementById('btn_generate_payslip');
        if (printBtn) printBtn.style.display = 'none';
        if (payslipBtn) payslipBtn.style.display = 'none';
        return;
    }

    content.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i></div>';

    let fetchUrl;
    if (monthKey.startsWith('pid:')) {
        const pid = monthKey.replace('pid:', '');
        currentPeriodId = pid;
        fetchUrl = `../admin/get_employee_dtr_data.php?employee_id=${currentEmployeeId}&period_id=${pid}`;
    } else if (monthKey.startsWith('range:')) {
        const range = monthKey.replace('range:', '');
        const parts = range.split('_');
        currentPeriodId = null;
        fetchUrl = `../admin/get_employee_dtr_data.php?employee_id=${currentEmployeeId}&start_date=${parts[0]}&end_date=${parts[1]}`;
    } else {
        currentPeriodId = null;
        fetchUrl = `../admin/get_employee_dtr_data.php?employee_id=${currentEmployeeId}&month=${monthKey}`;
    }

    fetch(fetchUrl)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentDTRRecords = data.records || [];
            currentEmpInfo = data.employee_info || {};
            currentComp = data.payroll_computation || null;

            if (data.records && data.records.length > 0) {
                content.innerHTML = buildDTRView(data.records, data.employee_info, data.payroll_computation);

                const printBtn = document.getElementById('btn_print_dtr');
                const payslipBtn = document.getElementById('btn_generate_payslip');
                if (printBtn) printBtn.style.display = 'inline-flex';
                if (payslipBtn) payslipBtn.style.display = 'inline-flex';
            } else {
                content.innerHTML = '<div class="no-cards-message"><i class="fas fa-file-alt"></i><br>No DTR records found for this period</div>';
                const printBtn = document.getElementById('btn_print_dtr');
                const payslipBtn = document.getElementById('btn_generate_payslip');
                if (printBtn) printBtn.style.display = 'none';
                if (payslipBtn) payslipBtn.style.display = 'none';
            }
        } else {
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-exclamation-circle"></i><br>Error: ' + (data.message || 'Unknown') + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        content.innerHTML = '<div class="no-cards-message"><i class="fas fa-exclamation-circle"></i><br>Error loading DTR data</div>';
    });
}

/* ====== Helper Formatters ====== */
function fmtTime24(t) {
    if (!t || t.trim() === '') return '';
    const parts = t.trim().split(':');
    if (parts.length >= 2) {
        const h = parseInt(parts[0]) || 0;
        const m = parseInt(parts[1]) || 0;
        return h + ':' + (m < 10 ? '0' + m : m);
    }
    return t;
}

function peso(v) {
    v = parseFloat(v) || 0;
    return '₱' + v.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function fmtTime(v) {
    if (!v) return '-';
    return v;
}

function dash(v) {
    return (v && v !== '0' && v !== '0.00') ? v : '-';
}

function formatNum(v, decimals = 2) {
    v = parseFloat(v) || 0;
    return v.toLocaleString('en-US', {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
}

/* ====== Rate Calculations ====== */
const WORKING_DAYS_IN_MONTH = 26;

function getRates(salary) {
    const perDay  = salary / WORKING_DAYS_IN_MONTH;
    const perHour = perDay / 8;
    const perMin  = perHour / 60;
    const otRate  = perHour * 1.25;
    return { perDay, perHour, perMin, otRate };
}

/* ====== Build DTR View (Read-Only) ====== */
function buildDTRView(records, empInfo, comp) {
    const salary = (comp && comp.basic_monthly_salary)
        ? parseFloat(comp.basic_monthly_salary)
        : parseFloat(empInfo.basic_monthly_salary) || 0;

    const classification = (empInfo.classification || '').toLowerCase().trim().replace(/\s+/g, '');
    const isTrainer = classification === 'trainer';

    let perDay, perHour, perMin, otRate;
    if (comp && comp.per_day_rate) {
        perDay = parseFloat(comp.per_day_rate);
        perHour = parseFloat(comp.per_hour_rate) || (perDay / 8);
        perMin = parseFloat(comp.per_minute_rate) || (perHour / 60);
        let savedOtRate = null;
        if (comp.other_deductions_notes) {
            try { const notes = JSON.parse(comp.other_deductions_notes); savedOtRate = notes.ot_rate || null; } catch(e) {}
        }
        otRate = savedOtRate || (perHour * 1.25);
    } else if (isTrainer) {
        perDay = 500; perHour = perDay / 8; perMin = perHour / 60; otRate = perHour * 1.25;
    } else {
        // Calculate from salary when no saved computation exists
        const rates = getRates(salary);
        perDay = rates.perDay; perHour = rates.perHour; perMin = rates.perMin; otRate = rates.otRate;
    }

    const savedLateStart = comp?.late_start || '8:00';
    const savedEndTime = comp?.end_time || '17:00';

    let totWorkHrs = 0, totLateMins = 0, totUtHrs = 0, totOtHrs = 0;
    let totAbsentDays = 0, totTrainingDays = 0, totAbsentDed = 0, totLateDed = 0, totUtDed = 0, totHalfDed = 0;
    let totOtPay = 0, totGovt = 0, totNetSalary = 0;
    let daysWorked = 0;

    let sssAmt = 0, philAmt = 0, pagAmt = 0;
    records.forEach(rec => {
        const rem = (rec.remarks || '').toUpperCase().trim();
        const gd = parseFloat(rec.govt_deduct) || 0;
        if (rem.includes('SSS') && !rem.includes('PHILHEALTH')) sssAmt = gd || sssAmt;
        if (rem.includes('PHILHEALTH') || rem.includes('PHIL HEALTH')) philAmt = gd || philAmt;
        if (rem.includes('PAGIBIG') || rem.includes('PAG-IBIG') || rem.includes('HDMF')) pagAmt = gd || pagAmt;
    });

    // TB5 Style DTR Calculator Header
    let html = `
    <div class="dtr-calc-container">
        <div class="dtr-header-tb5">
            <div class="tb5-title">DTR CALCULATOR</div>
        </div>
        <div class="tb5-info-row">
            <div class="tb5-employee-info">
                <span class="tb5-label">EMPLOYEE NAME:</span>
                <span class="tb5-value">${empInfo.full_name}</span>
            </div>
            <div class="tb5-company">THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.</div>
        </div>
        <div class="tb5-rate-row">
            <div class="tb5-rate-item rate-input-item" ${isTrainer ? 'style="display:none"' : ''}>
                <span class="tb5-rate-label">BASIC SALARY</span>
                <div class="rate-input-wrapper rate-green">
                    <span class="peso-sign">₱</span>
                    <input type="text" id="edit_basic_salary" class="rate-input" value="${salary.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" readonly>
                </div>
            </div>
            <div class="tb5-rate-item rate-input-item">
                <span class="tb5-rate-label">PER/DAY</span>
                <div class="rate-input-wrapper rate-red">
                    <span class="peso-sign">₱</span>
                    <input type="text" id="edit_per_day" class="rate-input" value="${perDay.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" readonly>
                </div>
            </div>
            <div class="tb5-rate-item">
                <span class="tb5-rate-label">PER/HOUR</span>
                <div class="rate-display rate-plain">
                    <span class="peso-sign">₱</span>
                    <span id="edit_per_hour" class="rate-val">${formatNum(perHour)}</span>
                </div>
            </div>
            <div class="tb5-rate-item">
                <span class="tb5-rate-label">PER/MIN</span>
                <div class="rate-display rate-plain">
                    <span class="peso-sign">₱</span>
                    <span id="edit_per_min" class="rate-val">${formatNum(perMin, 4)}</span>
                </div>
            </div>
            <div class="tb5-rate-item rate-input-item" ${isTrainer ? 'style="display:none"' : ''}>
                <span class="tb5-rate-label">OT RATE</span>
                <div class="rate-display rate-plain">
                    <span class="peso-sign">₱</span>
                    <span class="rate-val">${formatNum(otRate)}</span>
                </div>
            </div>
            <div class="tb5-rate-item rate-input-item">
                <span class="tb5-rate-label">TIME START</span>
                <input type="text" class="time-input" value="${savedLateStart}" readonly>
            </div>
            <div class="tb5-rate-item rate-input-item">
                <span class="tb5-rate-label">END TIME</span>
                <input type="text" class="time-input" value="${savedEndTime}" readonly>
            </div>
        </div>
    </div>`;

    // TB5 Style DTR Table
    html += `
    <div class="dtr-table-wrapper">
    <table class="dtr-table" id="dtrEditTable">
        <thead>
            <tr>
                <th rowspan="3" class="th-date">MO/YR<br>DATE</th>
                <th rowspan="3" class="th-group th-am">AM IN</th>
                <th rowspan="3" class="th-group th-pm">PM OUT</th>
                <th rowspan="3" class="th-single th-absent-col">ABSENT</th>
                <th rowspan="3" class="th-single th-training-col">TRAINING</th>
                <th rowspan="3" class="th-single th-ot-col">OT<br>OUT</th>
                <th rowspan="3" class="th-calc">TOT.WORK<br>(in hours)</th>
                <th rowspan="3" class="th-calc">LATE<br>(in mins)</th>
                <th rowspan="3" class="th-calc">UNDERTIME<br>(in hours)</th>
                <th rowspan="3" class="th-calc">OT<br>(in hours)</th>
                <th rowspan="3" class="th-single">ABSENT<br>(in days)</th>
                <th rowspan="3" class="th-deduct">LATE<br>DEDUCT</th>
                <th rowspan="3" class="th-deduct">UNDERTIME<br>DEDUCT</th>
                <th rowspan="3" class="th-deduct">HALFDAY<br>DEDUCT</th>
                <th rowspan="3" class="th-pay">OT PAY</th>
                <th colspan="3" class="th-group th-auto-calc">AUTOMATIC CALCULATIONS</th>
                <th rowspan="3" class="th-manual">Government<br>Benefits</th>
                <th rowspan="3" class="th-auto-salary">Net<br>Salary</th>
                <th rowspan="3" class="th-remarks">REMARKS</th>
            </tr>
            <tr>
                <th class="th-sub th-auto-calc">LATE/min</th>
                <th class="th-sub th-auto-calc">UNDERTIME</th>
                <th class="th-sub th-auto-calc">OT</th>
            </tr>
        </thead>
        <tbody>`;

    records.forEach((rec, idx) => {
        const isAbsent = rec.is_absent == 1;
        const isTraining = rec.is_training == 1;
        const isHalf = rec.is_halfday == 1;
        const workHrs  = (isAbsent || isTraining) ? 0 : (parseFloat(rec.total_work_hours) || 0);
        const lateMins = (isAbsent || isTraining) ? 0 : (parseFloat(rec.late_minutes) || 0);
        const utHrs    = (isAbsent || isTraining || isHalf) ? 0 : (parseFloat(rec.undertime_hours) || 0);
        const otHrs    = (isAbsent || isTraining) ? 0 : (parseFloat(rec.daily_ot_hours) || 0);
        const govtDb   = parseFloat(rec.govt_deduct) || 0;

        const absentDed = isAbsent ? perDay : 0;
        const lateDed   = (isAbsent || isTraining) ? 0 : (lateMins / 60) * perHour;
        const utDed     = (isAbsent || isTraining || isHalf) ? 0 : utHrs * perHour;
        const halfDed   = isHalf ? (perDay / 2) : 0;
        const otPayRow  = (isAbsent || isTraining) ? 0 : otHrs * otRate;

        let rowNet;
        if (isAbsent) rowNet = 0;
        else if (isTraining) rowNet = 0;
        else if (isHalf) rowNet = (perDay / 2) - lateDed;
        else if (workHrs === 0 && otHrs === 0) rowNet = 0;
        else rowNet = perDay - lateDed - utDed;

        if (!isAbsent && !isTraining && (rec.am_time_in || rec.pm_time_out || workHrs > 0)) daysWorked++;
        totAbsentDays += isAbsent ? 1 : 0;
        totTrainingDays += isTraining ? 1 : 0;
        totWorkHrs += workHrs;
        totLateMins += lateMins;
        totUtHrs += utHrs;
        totOtHrs += otHrs;
        totAbsentDed += absentDed;
        totLateDed += lateDed;
        totUtDed += utDed;
        totHalfDed += halfDed;
        totOtPay += otPayRow;
        totGovt += govtDb;
        totNetSalary += rowNet;

        let dateDisplay = rec.dtr_date;
        if (rec.dtr_date) {
            const dateParts = rec.dtr_date.split('-');
            if (dateParts.length === 3) {
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                const daysOfWeek = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                const day = parseInt(dateParts[2]);
                const month = months[parseInt(dateParts[1]) - 1] || '';
                const dateObj = new Date(rec.dtr_date);
                const dayOfWeek = daysOfWeek[dateObj.getDay()];
                dateDisplay = `<div class="date-day">${day}</div><div class="date-month">${month}</div><div class="date-weekday">${dayOfWeek}</div>`;
            }
        }

        const absentDays = isAbsent ? 1 : 0;
        const autoLate = (isAbsent || isTraining) ? 0 : (lateMins / 60) * perHour;
        const autoUt = (isAbsent || isTraining) ? 0 : utHrs * perHour;
        const autoOt = (isAbsent || isTraining) ? 0 : otHrs * otRate;

        const amTimeValue = (isAbsent || isTraining) ? '' : fmtTime24(rec.am_time_in);
        const pmTimeValue = (isAbsent || isTraining) ? '' : fmtTime24(rec.pm_time_out);
        const otTimeValue = (isAbsent || isTraining) ? '' : fmtTime24(rec.ot_time_out);

        html += `<tr class="dtr-data-row ${(isAbsent || isTraining) ? 'absent-row' : ''}" data-record-id="${rec.id}" data-idx="${idx}">`;
        html += `<td class="date-cell"><div class="date-display">${dateDisplay}</div></td>`;
        html += `<td class="td-am"><input type="text" class="dtr-input time24 input-am" value="${amTimeValue}" readonly></td>`;
        html += `<td class="td-pm"><input type="text" class="dtr-input time24 input-pm" value="${pmTimeValue}" readonly></td>`;
        html += `<td class="td-absent centered"><input type="checkbox" class="dtr-absent" ${isAbsent ? 'checked' : ''} disabled></td>`;
        html += `<td class="td-training centered"><input type="checkbox" class="dtr-training" ${rec.is_training ? 'checked' : ''} disabled></td>`;
        html += `<td class="td-ot"><input type="text" class="dtr-input time24 input-ot" value="${otTimeValue}" readonly></td>`;
        html += `<td class="td-calc calc-highlight"><input type="text" class="dtr-calc-input" value="${formatNum(workHrs)}" readonly></td>`;
        html += `<td class="td-calc calc-highlight"><input type="text" class="dtr-calc-input" value="${formatNum(lateMins, 0)}" readonly></td>`;
        html += `<td class="td-calc calc-highlight"><input type="text" class="dtr-calc-input" value="${formatNum(utHrs)}" readonly></td>`;
        html += `<td class="td-calc calc-highlight"><input type="text" class="dtr-calc-input" value="${formatNum(otHrs)}" readonly></td>`;
        html += `<td class="td-single"><input type="text" class="dtr-calc-input" value="${absentDays}" readonly></td>`;
        html += `<td class="td-deduct deduct-highlight"><input type="text" class="dtr-deduct-input" value="${formatNum(lateDed)}" readonly></td>`;
        html += `<td class="td-deduct deduct-highlight"><input type="text" class="dtr-deduct-input" value="${formatNum(utDed)}" readonly></td>`;
        html += `<td class="td-deduct deduct-highlight"><input type="text" class="dtr-deduct-input" value="${formatNum(halfDed)}" readonly></td>`;
        html += `<td class="td-pay pay-highlight"><input type="text" class="dtr-pay-input" value="${formatNum(otPayRow)}" readonly></td>`;
        html += `<td class="td-auto dtr-auto-calc"><input type="text" class="dtr-calc-input" value="${formatNum(autoLate)}" readonly></td>`;
        html += `<td class="td-auto dtr-auto-calc"><input type="text" class="dtr-calc-input" value="${formatNum(autoUt)}" readonly></td>`;
        html += `<td class="td-auto dtr-auto-calc"><input type="text" class="dtr-calc-input" value="${formatNum(autoOt)}" readonly></td>`;
        html += `<td class="td-manual dtr-manual"><input type="number" class="dtr-govt-input" value="${govtDb > 0 ? govtDb.toFixed(2) : ''}" readonly></td>`;
        html += `<td class="td-salary dtr-auto-salary"><input type="text" class="dtr-net-input" value="${formatNum(rowNet)}" readonly></td>`;
        html += `<td class="td-remarks"><input type="text" class="dtr-remarks-input" value="${rec.remarks || ''}" readonly></td>`;
        html += `</tr>`;
    });

    // Totals footer
    html += `</tbody><tfoot><tr class="totals-row" id="totalsRow">`;
    html += `<td class="totals-label" colspan="6">TOTALS:</td>`;
    html += `<td class="tot-work-hrs calc-highlight">${formatNum(totWorkHrs)}</td>`;
    html += `<td class="tot-late calc-highlight">${formatNum(totLateMins, 0)}</td>`;
    html += `<td class="tot-ut calc-highlight">${formatNum(totUtHrs)}</td>`;
    html += `<td class="tot-ot calc-highlight">${formatNum(totOtHrs)}</td>`;
    html += `<td class="tot-absent-days">${totAbsentDays}</td>`;
    html += `<td class="tot-late-ded deduct-highlight">${formatNum(totLateDed)}</td>`;
    html += `<td class="tot-ut-ded deduct-highlight">${formatNum(totUtDed)}</td>`;
    html += `<td class="tot-half-ded deduct-highlight">${formatNum(totHalfDed)}</td>`;
    html += `<td class="tot-ot-pay pay-highlight">${formatNum(totOtPay)}</td>`;
    html += `<td class="tot-auto-late">${formatNum(totLateDed)}</td>`;
    html += `<td class="tot-auto-ut">${formatNum(totUtDed)}</td>`;
    html += `<td class="tot-auto-ot">${formatNum(totOtPay)}</td>`;
    html += `<td class="tot-govt">${formatNum(totGovt)}</td>`;
    html += `<td class="tot-net"><strong>${formatNum(totNetSalary)}</strong></td>`;
    html += `<td></td>`;
    html += `</tr></tfoot></table></div>`;

    // Training Payment section
    const savedTrainingAmount = comp?.trainings_cost || comp?.training_amount || 0;
    const savedTrainingRemarks = comp?.training_remarks || '';

    html += `
    <div class="trainee-payment-card" style="margin-top: 20px;">
        <div class="trainee-card-header">
            <h3><i class="fas fa-users"></i> Training Payment</h3>
            <span class="trainee-badge">Additional Earnings</span>
        </div>
        <div class="trainee-card-body">
            <div class="trainee-form-row" style="gap: 15px;">
                <div class="trainee-input-group">
                    <label>Amount</label>
                    <div class="input-with-prefix">
                        <span class="prefix">₱</span>
                        <input type="text" class="trainee-input trainee-amount-input"
                               value="${parseFloat(savedTrainingAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}"
                               readonly style="padding: 8px 10px 8px 28px; border: 2px solid #cbd5e0;">
                    </div>
                </div>
                <div class="trainee-input-group" style="flex: 2;">
                    <label>Remarks</label>
                    <input type="text" class="trainee-input" value="${savedTrainingRemarks}" readonly
                           style="padding: 8px 10px; border: 2px solid #cbd5e0;">
                </div>
            </div>
        </div>
    </div>`;

    // Payroll Summary
    const totalDeductions = totAbsentDed + totLateDed + totUtDed + totHalfDed;
    const sssContrib = parseFloat(comp?.sss_contribution) || 0;
    const philHealthContrib = parseFloat(comp?.philhealth_contribution) || 0;
    const pagibigContrib = parseFloat(comp?.pagibig_contribution) || 0;
    const totalGovtDeductions = sssContrib + philHealthContrib + pagibigContrib;
    const totalAllDeductions = totalDeductions + totalGovtDeductions;
    const finalNetPay = totNetSalary + parseFloat(savedTrainingAmount) + totOtPay - totalGovtDeductions;

    html += `
    <div class="trainee-payment-card" style="margin-top: 20px;">
        <div class="trainee-card-header">
            <h3><i class="fas fa-file-invoice-dollar"></i> Payroll Summary</h3>
            <span class="trainee-badge">Salary Breakdown</span>
        </div>
        <div class="trainee-card-body">
            <div class="summary-two-column">
                <div class="summary-column summary-column-left">
                    <div class="summary-row">
                        <span class="summary-label">Days Worked:</span>
                        <span class="summary-value">${daysWorked} days</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Absent:</span>
                        <span class="summary-value">${totAbsentDays} days</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Training:</span>
                        <span class="summary-value">${totTrainingDays} days</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Late Deduct:</span>
                        <span class="summary-value negative">-${peso(totLateDed)}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Undertime Deduct:</span>
                        <span class="summary-value negative">-${peso(totUtDed)}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Halfday Deduct:</span>
                        <span class="summary-value negative">-${peso(totHalfDed)}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">OT Pay:</span>
                        <span class="summary-value positive">+${peso(totOtPay)}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Net Salary:</span>
                        <span class="summary-value">${peso(totNetSalary)}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Training Payment:</span>
                        <span class="summary-value positive">+${peso(savedTrainingAmount)}</span>
                    </div>
                </div>
                <div class="summary-column summary-column-right">
                    <div class="summary-section-header">Government Deductions</div>
                    <div class="summary-row">
                        <span class="summary-label">SSS:</span>
                        <span class="summary-value negative">-${peso(sssContrib)}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">PhilHealth:</span>
                        <span class="summary-value negative">-${peso(philHealthContrib)}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Pag-IBIG:</span>
                        <span class="summary-value negative">-${peso(pagibigContrib)}</span>
                    </div>
                    <div class="summary-row summary-subtotal">
                        <span class="summary-label">Total Deduction:</span>
                        <span class="summary-value negative">-${peso(totalAllDeductions)}</span>
                    </div>
                </div>
            </div>
            <div class="summary-row-full final">
                <span class="summary-label">FINAL NET PAY:</span>
                <span class="summary-value final-value">${peso(finalNetPay)}</span>
            </div>
        </div>
    </div>`;

    return html;
}

/* ====== Export Functions ====== */
function exportCurrentDTR() {
    if (!currentEmployeeId) return;
    const base = window.location.origin + '/TheBigFive_Payroll/admin/';
    let url = `${base}export_dtr_data.php?employee_id=${currentEmployeeId}`;
    if (currentPeriodId) url += `&period_id=${currentPeriodId}`;
    window.location.href = url;
}

function exportDTRAsPDF() {
    if (!currentEmployeeId || !currentPeriodId) return;
    const pdfBase = window.location.origin + '/TheBigFive_Payroll/admin/';
    const url = `${pdfBase}export_dtr_pdf_proxy.php?employee_id=${currentEmployeeId}&period_id=${currentPeriodId}`;
    window.open(url, '_blank');
}

/* ====== Modal Close ====== */
function closeEmployeeDTRModal() {
    const modal = document.getElementById('employeeDTRModal');
    if (modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }
    currentEmployeeId = null;
    currentPeriodId   = null;
    currentDTRRecords = [];
    currentEmpInfo    = null;
    currentComp       = null;
    const printBtn = document.getElementById('btn_print_dtr');
    const payslipBtn = document.getElementById('btn_generate_payslip');
    if (printBtn) printBtn.style.display = 'none';
    if (payslipBtn) payslipBtn.style.display = 'none';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEmployeeDTRModal(); });
document.getElementById('employeeDTRModal')?.addEventListener('click', function(e) { if (e.target === this) closeEmployeeDTRModal(); });

/* ====== Generate Payslip with Cutoff Period ====== */
async function showPayslipCutoffSelector() {
    if (!currentPeriodId) {
        await showCustomAlert('Please select a named payroll period (not a date range) to generate a payslip.', 'Period Required', 'warning');
        return;
    }
    if (!currentDTRRecords || currentDTRRecords.length === 0) {
        await showCustomAlert('No DTR records available.', 'No Records', 'warning');
        return;
    }

    let firstHalfDays = 0, secondHalfDays = 0, fullDays = 0;
    currentDTRRecords.forEach(rec => {
        const isAbsent = rec.is_absent == 1;
        const isTraining = rec.is_training == 1;
        const hasValue = !isAbsent && !isTraining && (rec.am_time_in || rec.pm_time_out || parseFloat(rec.total_work_hours) > 0);
        if (!hasValue) return;
        const day = parseInt(rec.dtr_date?.split('-')[2] || '0');
        if (day >= 1 && day <= 15) firstHalfDays++;
        if (day >= 16) secondHalfDays++;
        fullDays++;
    });

    document.getElementById('cutoff_first_days').textContent = `${firstHalfDays} working day${firstHalfDays !== 1 ? 's' : ''}`;
    document.getElementById('cutoff_second_days').textContent = `${secondHalfDays} working day${secondHalfDays !== 1 ? 's' : ''}`;
    document.getElementById('cutoff_full_days').textContent = `${fullDays} working day${fullDays !== 1 ? 's' : ''}`;

    const dayMap = { first: firstHalfDays, second: secondHalfDays, full: fullDays };
    document.querySelectorAll('.cutoff-option').forEach(label => {
        const radio = label.querySelector('input[type="radio"]');
        const cutoffVal = radio?.value;
        const hasNoDays = (dayMap[cutoffVal] ?? 1) === 0;
        label.classList.toggle('disabled', hasNoDays);
        radio.disabled = hasNoDays;
        label.querySelectorAll('.cutoff-generated-badge').forEach(b => b.remove());
    });

    try {
        if (!currentPeriodId) throw new Error('No period selected');
        const resp = await fetch(`../admin/save_cutoff_payslip.php?check=1&employee_id=${currentEmployeeId}&period_id=${currentPeriodId}`);
        const data = await resp.json();
        if (data.existing_cutoffs && data.existing_cutoffs.length > 0) {
            data.existing_cutoffs.forEach(type => {
                const radio = document.querySelector(`input[name="cutoff_period"][value="${type}"]`);
                if (radio) {
                    const label = radio.closest('.cutoff-option');
                    label.classList.add('disabled');
                    radio.disabled = true;
                    const card = label.querySelector('.cutoff-card');
                    if (card && !card.querySelector('.cutoff-generated-badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'cutoff-generated-badge';
                        badge.textContent = 'Already Generated';
                        badge.style.cssText = 'display:block;margin-top:6px;padding:2px 8px;background:none;color:#e53e3e;border-radius:4px;font-size:11px;font-weight:600;';
                        card.appendChild(badge);
                    }
                }
            });
        }
    } catch (e) {
        console.warn('Could not check existing cutoff payslips:', e);
    }

    const radios = [...document.querySelectorAll('input[name="cutoff_period"]')];
    const checkedRadio = document.querySelector('input[name="cutoff_period"]:checked');
    if (checkedRadio && checkedRadio.disabled) checkedRadio.checked = false;
    if (!document.querySelector('input[name="cutoff_period"]:checked')) {
        const firstEnabled = radios.find(r => !r.disabled);
        if (firstEnabled) firstEnabled.checked = true;
    }

    document.getElementById('cutoffSelectorOverlay').style.display = 'flex';
}

function closeCutoffSelector() {
    document.getElementById('cutoffSelectorOverlay').style.display = 'none';
}

function generatePayslipForCutoff() {
    const cutoffRadio = document.querySelector('input[name="cutoff_period"]:checked');
    if (!cutoffRadio) {
        alert('Please select a cutoff period.');
        return;
    }
    const cutoff = cutoffRadio.value;
    closeCutoffSelector();

    if (cutoff === 'full') {
        window.location.href = 'payslip_history.php';
        return;
    }

    const records = currentDTRRecords;
    const comp = currentComp || {};
    const empSalary = parseFloat(comp.basic_monthly_salary || currentEmpInfo?.basic_monthly_salary || 0);
    const classification = (currentEmpInfo?.classification || '').toLowerCase().trim().replace(/\s+/g, '');
    const isTrainerEmp = classification === 'trainer';
    const perDay = parseFloat(comp.per_day_rate) || (isTrainerEmp ? 500 : (empSalary / 26));
    const perHour = parseFloat(comp.per_hour_rate) || (perDay / 8);
    const otRateVal = parseFloat(comp.ot_rate) || perHour * 1.25;

    let daysWorked = 0, totWorkHrs = 0, totLateMins = 0, totUtHrs = 0, totOtHrs = 0;
    let totLateDed = 0, totUtDed = 0, totHalfDed = 0, totOtPay = 0, totNetSalary = 0;
    let totAbsentDays = 0, totTrainingDays = 0;

    records.forEach(rec => {
        const day = parseInt(rec.dtr_date?.split('-')[2] || '0');
        if (cutoff === 'first' && day > 15) return;
        if (cutoff === 'second' && day < 16) return;

        const isAbsent = rec.is_absent == 1;
        const isTraining = rec.is_training == 1;
        const isHalf = rec.is_halfday == 1;
        const workHrs = (isAbsent || isTraining) ? 0 : (parseFloat(rec.total_work_hours) || 0);
        const lateMins = (isAbsent || isTraining) ? 0 : (parseFloat(rec.late_minutes) || 0);
        const utHrs = (isAbsent || isTraining || isHalf) ? 0 : (parseFloat(rec.undertime_hours) || 0);
        const otHrs = (isAbsent || isTraining) ? 0 : (parseFloat(rec.daily_ot_hours) || 0);

        const lateDed = (isAbsent || isTraining) ? 0 : (lateMins / 60) * perHour;
        const utDed = (isAbsent || isTraining || isHalf) ? 0 : utHrs * perHour;
        const halfDed = isHalf ? (perDay / 2) : 0;
        const otPayRow = (isAbsent || isTraining) ? 0 : otHrs * otRateVal;

        let rowNet;
        if (isAbsent) rowNet = 0;
        else if (isTraining) rowNet = 0;
        else if (isHalf) rowNet = (perDay / 2) - lateDed;
        else if (workHrs === 0 && otHrs === 0) rowNet = 0;
        else rowNet = perDay - lateDed - utDed;

        if (!isAbsent && !isTraining && (rec.am_time_in || rec.pm_time_out || workHrs > 0)) daysWorked++;
        totAbsentDays += isAbsent ? 1 : 0;
        totTrainingDays += isTraining ? 1 : 0;
        totWorkHrs += workHrs;
        totLateMins += lateMins;
        totUtHrs += utHrs;
        totOtHrs += otHrs;
        totLateDed += lateDed;
        totUtDed += utDed;
        totHalfDed += halfDed;
        totOtPay += otPayRow;
        totNetSalary += rowNet;
    });

    if (daysWorked === 0 && totAbsentDays === 0 && totTrainingDays === 0) {
        const label = cutoff === 'first' ? '1st Cutoff (Day 1–15)' : '2nd Cutoff (Day 16–End)';
        showCustomAlert(`No work records found for the <b>${label}</b>. Payslip generation has been cancelled.`, 'No Work Days', 'warning');
        return;
    }

    const grossPay = totNetSalary;
    const totalHalfDeduct = totHalfDed;
    const sss = parseFloat(comp.sss_contribution || 0);
    const philhealth = parseFloat(comp.philhealth_contribution || 0);
    const pagibig = parseFloat(comp.pagibig_contribution || 0);
    const totalGovtDed = sss + philhealth + pagibig;

    const trainingAmount = parseFloat(comp.trainings_cost || 0);
    const trainingRemarks = comp.training_remarks || '';

    const totalEarnings = grossPay + totOtPay + trainingAmount;
    const totalDeductions = totalHalfDeduct + totalGovtDed;
    const netPay = totalEarnings - totalHalfDeduct - totalGovtDed;

    let cutoffLabel = '';
    if (cutoff === 'first') cutoffLabel = '1st Cutoff (Day 1-15)';
    else if (cutoff === 'second') cutoffLabel = '2nd Cutoff (Day 16-End)';

    const payload = {
        employee_id: currentEmployeeId,
        period_id: currentPeriodId,
        cutoff_type: cutoff,
        cutoff_label: cutoffLabel,
        basic_pay: grossPay,
        ot_pay: totOtPay,
        total_work_hours: totWorkHrs,
        total_ot_hours: totOtHrs,
        per_day_rate: perDay,
        per_hour_rate: perHour,
        ot_rate: otRateVal,
        late_minutes: totLateMins,
        undertime_hours: totUtHrs,
        halfday_deduct: totalHalfDeduct,
        sss_contribution: sss,
        philhealth_contribution: philhealth,
        pagibig_contribution: pagibig,
        trainings_cost: trainingAmount,
        training_remarks: trainingRemarks,
        total_earnings: totalEarnings,
        total_deductions: totalDeductions,
        net_pay: netPay,
        days_worked: daysWorked,
        absent_days: totAbsentDays,
        training_days: totTrainingDays,
        late_deduct: totLateDed,
        undertime_deduct: totUtDed,
        absent_deduct: totAbsentDays * perDay,
        source_computation_id: currentComp?.id || 0
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('../admin/save_cutoff_payslip.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.payslip_id) {
            window.location.href = 'payslip_history.php';
        } else {
            alert('Error generating payslip: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}

/* ====== Refresh Button ====== */
document.getElementById('btn_refresh_cards')?.addEventListener('click', function() {
    location.reload();
});
</script>

<?php require_once 'include/footer.php'; ?>
