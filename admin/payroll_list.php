<?php
/**
 * Payroll List Page
 * Display all employees with DTR records and their payroll summaries
 */

$page_title = 'Payroll List';
require_once 'include/header.php';
require_once 'include/sidebar.php';
require_once '../config/database.php';

$pdo = getDBConnection();

// Get all employees with DTR records and their latest net salary from payroll computations
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
        -- Compute final net pay using PRE-CALCULATED values from payroll_computations
        -- This ensures exact match with DTR calculator formulas, not recalculated here
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
                                <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                                <span class="page-breadcrumb-separator">/</span>
                                <span>Payroll</span>
                            </div>
                        </div>
                    </div>
                    <p class="page-subtitle">View and manage employee payroll records</p>
                </div>
                <div class="page-header-right">
                    <div class="page-header-actions">
                        <a href="Generatepayroll.php" class="btn-header-primary">
                            <i class="fas fa-plus"></i>
                            Generate New Payroll
                        </a>
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
                    <a href="Generatepayroll.php" class="btn-modern btn-add">
                        <i class="fas fa-plus"></i> Generate New Payroll
                    </a>
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
                        <small><a href="Generatepayroll.php">Import DTR data</a> to see employee records here.</small>
                    </div>
                <?php else: ?>
                    <div class="employee-cards-grid" id="employee_cards_container">
                        <?php foreach ($employees as $emp): 
                            $initials = strtoupper(substr($emp['full_name'], 0, 1));
                            $nameParts = explode(' ', $emp['full_name']);
                            if (count($nameParts) > 1) {
                                $initials = strtoupper($nameParts[0][0] . $nameParts[count($nameParts)-1][0]);
                            }
                            
                            // Use actual net pay from latest payroll computation
                            // Falls back to estimate if no computation exists yet
                            $salary = floatval($emp['basic_monthly_salary']);
                            $netPay = floatval($emp['latest_net_pay'] ?? 0);
                            $daysWorked = intval($emp['actual_days_worked'] ?? 0);
                            
                            // If no payroll computation exists, calculate estimate
                            if ($netPay <= 0) {
                                $dailyRate = $salary / 15;  // Per cutoff rate
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

<!-- Employee DTR View Modal - Editable DTR Calculator Style -->
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
                <div class="edit-mode-toggle">
                    <button type="button" id="btn_edit_mode" class="btn-edit-mode" onclick="toggleEditMode()" style="display:none;">
                        <i class="fas fa-edit"></i> Edit Mode
                    </button>
                </div>
            </div>
            
            <!-- DTR Calculator Content - Matching Generatepayroll.php design -->
            <div id="employee_dtr_content" class="employee-dtr-content">
                <!-- Will be populated by JS -->
            </div>
        </div>
        <div class="modal-fullview-footer">
            <button type="button" class="btn-modern btn-save-dtr" id="btn_save_dtr" onclick="saveDTRChanges()" style="display:none;">
                <i class="fas fa-save"></i> Save Changes
            </button>
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
                                <span class="cutoff-range" id="cutoff_first_range">Prev 28 - Curr 12</span>
                                <span class="cutoff-days" id="cutoff_first_days"></span>
                            </div>
                        </label>
                        <label class="cutoff-option">
                            <input type="radio" name="cutoff_period" value="second">
                            <div class="cutoff-card">
                                <i class="fas fa-calendar-check"></i>
                                <span class="cutoff-title">2nd Cutoff</span>
                                <span class="cutoff-range" id="cutoff_second_range">Curr 13 - 27</span>
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

                // Do NOT auto-select a period or auto-load records; wait for user to choose from dropdown
            }, {capture: false});
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attachModalAutoLoad); else attachModalAutoLoad();
})();
</script>

<style>
/* ====== Stats Row ====== */
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
    width: 50px; height: 50px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: white;
}
.stat-icon.bg-primary { background: linear-gradient(135deg,#2563EB,#1d4ed8); }
.stat-icon.bg-success { background: linear-gradient(135deg,#38a169,#2f855a); }
.stat-icon.bg-info    { background: linear-gradient(135deg,#4299e1,#3182ce); }
.stat-info { display: flex; flex-direction: column; }
.stat-value { font-size: 24px; font-weight: 700; color: #2d3748; }
.stat-label { font-size: 13px; color: #718096; }
.header-actions { display: flex; gap: 10px; align-items: center; }

/* ====== Employee Cards ====== */
.employee-cards-section { background:#fff; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,.07); }
.employee-cards-section .card-header { display:flex; justify-content:space-between; align-items:center; padding:16px 24px; background:linear-gradient(135deg,#2563EB,#1d4ed8); border-radius:12px 12px 0 0; color:#fff; }
.employee-cards-section .card-header h3 { margin:0; font-size:18px; display:flex; align-items:center; gap:10px; }
.employee-cards-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:20px; padding:20px; }
.employee-card { background:linear-gradient(145deg,#fff,#f7fafc); border:1px solid #e2e8f0; border-radius:12px; padding:20px; cursor:pointer; transition:all .3s; position:relative; overflow:hidden; }
.employee-card:hover { transform:translateY(-4px); box-shadow:0 12px 24px rgba(0,0,0,.12); border-color:#2563EB; }
.employee-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg,#2563EB,#1d4ed8); }
.employee-card-header { display:flex; align-items:center; gap:15px; margin-bottom:15px; position:relative; }
.classification-badge { position:absolute; top:0; right:0; font-size:10px; font-weight:700; padding:3px 10px; border-radius:20px; letter-spacing:0.5px; text-transform:uppercase; }
.badge-fixedrate { background:#e6f4ea; color:#1e7e34; }
.badge-trainer { background:#fff3e0; color:#e65100; }
.employee-avatar { width:50px; height:50px; border-radius:50%; background:linear-gradient(135deg,#2563EB,#1d4ed8); display:flex; align-items:center; justify-content:center; color:#fff; font-size:20px; font-weight:600; }
.employee-card-name { font-size:16px; font-weight:600; color:#2d3748; margin-bottom:4px; }
.employee-card-code { font-size:12px; color:#718096; font-family:monospace; }
.employee-card-details { display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:13px; margin-bottom:15px; }
.employee-card-details .detail-item { display:flex; flex-direction:column; }
.employee-card-details .detail-label { color:#a0aec0; font-size:11px; text-transform:uppercase; }
.employee-card-details .detail-value { color:#4a5568; font-weight:500; }
.employee-card-footer { padding-top:15px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
.net-pay-badge { display:flex; flex-direction:column; }
.net-pay-badge .net-label { font-size:11px; color:#718096; text-transform:uppercase; }
.net-pay-badge .net-value { font-size:18px; font-weight:700; color:#38a169; }
.net-pay-badge .period-badge { font-size:9px; color:#a0aec0; margin-top:2px; font-weight:400; }
.view-dtr-link { color:#2563EB; font-size:13px; font-weight:500; display:flex; align-items:center; gap:5px; pointer-events:none; }
.no-cards-message { text-align:center; padding:60px 20px; color:#a0aec0; }
.no-cards-message i { font-size:48px; margin-bottom:15px; display:block; }
.no-cards-message a { color:#2563EB; }
.loading-cards { text-align:center; padding:40px; color:#718096; }

/* ====== Modal - TB5 Style (Exact Match) ====== */
.modal-fullview { 
    position: fixed; 
    top: 0; 
    left: 0; 
    right: 0; 
    bottom: 0; 
    background: rgba(0, 0, 0, 0.75); 
    z-index: 9999; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    padding: 20px; 
    animation: fadeIn .3s;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.modal-fullview-content { 
    background: #fff; 
    border-radius: 16px; 
    width: 100%; 
    max-width: 95vw; 
    max-height: 95vh; 
    display: flex; 
    flex-direction: column; 
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.35);
    border: 2px solid #cbd5e0;
}
.modal-fullview-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 22px 32px; 
    background: linear-gradient(135deg, #1F4E79, #163557); 
    border-radius: 16px 16px 0 0; 
    color: #fff;
    border-bottom: 3px solid #0d2b3e;
}
.modal-fullview-header h2 { 
    margin: 0; 
    font-size: 24px; 
    display: flex; 
    align-items: center; 
    gap: 14px;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.modal-close-btn { 
    background: rgba(255, 255, 255, 0.15); 
    border: 2px solid rgba(255, 255, 255, 0.3); 
    color: #fff; 
    width: 44px; 
    height: 44px; 
    border-radius: 50%; 
    cursor: pointer; 
    font-size: 20px; 
    transition: all .3s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-close-btn:hover { 
    background: rgba(255, 255, 255, 0.25); 
    border-color: rgba(255, 255, 255, 0.5);
    transform: rotate(90deg);
}
.modal-fullview-body { 
    flex: 1; 
    overflow: auto; 
    padding: 32px;
    background: #f7fafc;
}
.modal-fullview-footer { 
    display: flex; 
    justify-content: flex-end; 
    gap: 14px; 
    padding: 22px 32px; 
    border-top: 2px solid #cbd5e0; 
    background: linear-gradient(135deg, #f7fafc, #edf2f7); 
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

/* ====== Buttons - TB5 Style (Updated) ====== */
.btn-modern { 
    padding: 11px 22px; 
    border: none; 
    border-radius: 8px; 
    cursor: pointer; 
    font-size: 14px; 
    font-weight: 700; 
    display: inline-flex; 
    align-items: center; 
    gap: 10px; 
    transition: all .3s;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.btn-modern.btn-add { 
    background: linear-gradient(135deg, #38a169, #2f855a); 
    color: #fff;
    box-shadow: 0 2px 8px rgba(56, 161, 105, .3);
}
.btn-modern.btn-add:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 6px 16px rgba(56, 161, 105, .4); 
}
.btn-modern.btn-sm { 
    padding: 9px 18px; 
    font-size: 13px; 
    background: rgba(255, 255, 255, .25); 
    color: #fff;
    border: 1px solid rgba(255, 255, 255, .3);
}
.btn-modern.btn-sm:hover { 
    background: rgba(255, 255, 255, .35); 
}
.btn-modern.btn-save-dtr { 
    background: linear-gradient(135deg, #38a169, #2f855a); 
    color: #fff;
    box-shadow: 0 2px 8px rgba(56, 161, 105, .3);
}
.btn-modern.btn-save-dtr:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(56, 161, 105, .4);
}
.btn-modern.btn-edit-mode { 
    background: linear-gradient(135deg, #ed8936, #dd6b20); 
    color: #fff;
    box-shadow: 0 2px 8px rgba(237, 137, 54, .3);
}
.btn-modern.btn-edit-mode:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(237, 137, 54, .4);
}
.btn-modern.btn-primary { 
    background: linear-gradient(135deg, #2563EB, #1d4ed8); 
    color: #fff;
    box-shadow: 0 2px 8px rgba(37, 99, 235, .3);
}
.btn-modern.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, .4);
}
.btn-modern.btn-secondary { 
    background: #fff; 
    color: #4a5568;
    border: 2px solid #cbd5e0;
}
.btn-modern.btn-secondary:hover {
    background: #f7fafc;
    border-color: #a0aec0;
}
.btn-modern.btn-success {
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: #fff;
    box-shadow: 0 2px 8px rgba(22, 163, 74, .3);
}
.btn-modern.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(22, 163, 74, .4);
}

/* ====== Cutoff Period Selector ====== */
.cutoff-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 10001;
    display: flex; align-items: center; justify-content: center;
}
.cutoff-modal {
    background: #fff; border-radius: 16px; width: 520px; max-width: 95vw;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
}
.cutoff-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 18px 24px; background: linear-gradient(135deg, #16a34a, #15803d); color: #fff;
}
.cutoff-header h3 { margin: 0; font-size: 16px; }
.cutoff-header h3 i { margin-right: 8px; }
.cutoff-close {
    background: none; border: none; color: #fff; font-size: 22px; cursor: pointer;
    width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center;
    justify-content: center; transition: background .2s;
}
.cutoff-close:hover { background: rgba(255,255,255,0.2); }
.cutoff-body { padding: 24px; }
.cutoff-desc { margin: 0 0 16px; color: #64748b; font-size: 14px; }
.cutoff-options { display: flex; gap: 12px; }
.cutoff-option { flex: 1; cursor: pointer; }
.cutoff-option input[type="radio"] { display: none; }
.cutoff-card {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 16px 10px; border: 2px solid #e2e8f0; border-radius: 12px;
    transition: all .2s; text-align: center;
}
.cutoff-option input:checked + .cutoff-card {
    border-color: #16a34a; background: #f0fdf4; box-shadow: 0 2px 8px rgba(22,163,74,.15);
}
.cutoff-card:hover { border-color: #86efac; }
.cutoff-card i { font-size: 24px; color: #16a34a; }
.cutoff-title { font-weight: 700; font-size: 14px; color: #1e293b; }
.cutoff-range { font-size: 12px; color: #64748b; }
.cutoff-days { font-size: 11px; color: #16a34a; font-weight: 600; margin-top: 4px; }
/* Disabled cutoff card (0 working days) */
.cutoff-option.disabled { cursor: not-allowed; opacity: 0.45; pointer-events: none; }
.cutoff-option.disabled .cutoff-card { border-color: #e2e8f0; background: #f8fafc; }
.cutoff-option.disabled .cutoff-card i { color: #94a3b8; }
.cutoff-option.disabled .cutoff-days { color: #ef4444; }
.cutoff-zero-warn {
    margin: 8px 0 0;
    padding: 8px 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    font-size: 12px;
    color: #dc2626;
    display: none;
    align-items: center;
    gap: 6px;
}
.cutoff-footer {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0;
}

/* ====== DTR Template - Matches Generatepayroll.php Exactly ====== */

/* ====== TB5 DTR Calculator Styles ====== */

/* DTR Calculator Container */
.dtr-calc-container {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,.07);
    overflow: hidden;
    margin-bottom: 24px;
}

/* DTR Calculator Header - TB5 Dark Blue */
.dtr-header-tb5 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1F4E79;
    color: white;
    padding: 16px 24px;
    border-bottom: 3px solid #163557;
}
.tb5-title {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

/* Employee Info Row - TB5 Yellow Background */
.tb5-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 24px;
    background: #FFFF00;
    border-bottom: 2px solid #e6e600;
    flex-wrap: wrap;
    gap: 12px;
}
.tb5-employee-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.tb5-label {
    font-weight: 700;
    font-size: 12px;
    color: #000;
    text-transform: uppercase;
}
.tb5-value {
    font-weight: 700;
    color: #FF0000;
    font-size: 16px;
}
.tb5-company {
    font-size: 11px;
    font-weight: 600;
    color: #000;
    text-transform: uppercase;
}

/* Rate Row - TB5 Style (Exact Match) */
.tb5-rate-row {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    padding: 14px 24px;
    background: #f7f9fb;
    border-bottom: 2px solid #cbd5e0;
    align-items: flex-end;
}
.tb5-rate-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.tb5-rate-label {
    font-size: 10px;
    color: #4a5568;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}
.rate-display {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    padding: 6px 14px;
    min-width: 100px;
    font-weight: 700;
    font-size: 14px;
}
.rate-display.rate-green {
    background: #00FF00;
    border: 2px solid #00b300;
    color: #000;
}
.rate-display.rate-red {
    background: #FF0000;
    border: 2px solid #cc0000;
    color: #fff;
}
.rate-display.rate-plain {
    background: #fff;
    border: 2px solid #a0aec0;
    color: #2d3748;
}
.rate-display .peso-sign { margin-right: 3px; font-weight: 700; }
.time-display {
    width: 65px;
    padding: 6px 10px;
    border: 2px solid #4299e1;
    border-radius: 6px;
    font-weight: 700;
    font-size: 13px;
    text-align: center;
    background: #fff;
    color: #2d3748;
}

/* Rate Input Styles (Exact TB5 Match) */
.rate-input-item { position: relative; }
.rate-input-wrapper {
    display: flex;
    align-items: center;
    border-radius: 6px;
    padding: 6px 10px;
    min-width: 110px;
}
.rate-input-wrapper.rate-green {
    background: #00FF00;
    border: 2px solid #00b300;
}
.rate-input-wrapper.rate-red {
    background: #FF0000;
    border: 2px solid #cc0000;
}
.rate-input-wrapper .peso-sign {
    font-weight: 700;
    margin-right: 4px;
    font-size: 14px;
}
.rate-input-wrapper.rate-green .peso-sign,
.rate-input-wrapper.rate-green .rate-input {
    color: #000;
}
.rate-input-wrapper.rate-red .peso-sign,
.rate-input-wrapper.rate-red .rate-input {
    color: #fff;
}
.rate-input {
    border: none;
    background: transparent;
    font-weight: 700;
    font-size: 14px;
    width: 85px;
    text-align: right;
    outline: none;
    color: inherit;
}
.rate-input::-webkit-inner-spin-button,
.rate-input::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.rate-input[type="number"] { -moz-appearance: textfield; }
.rate-input:read-only {
    cursor: default;
    opacity: 0.9;
}
.time-input {
    width: 65px;
    padding: 6px 10px;
    border: 2px solid #4299e1;
    border-radius: 6px;
    font-weight: 700;
    font-size: 13px;
    text-align: center;
    background: #fff;
    font-family: 'Consolas', 'Monaco', monospace;
    color: #2d3748;
}
.time-input:read-only {
    cursor: default;
    background: #f7fafc;
    border-color: #cbd5e0;
}
.time-input:focus {
    border-color: #3182ce;
    outline: none;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
}

/* Day override switch */
.tb5-switch-item {
    min-width: 182px;
}
.tb5-switch-item .segment-switch-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.tb5-switch-item .segment-switch-track {
    position: relative;
    width: 164px;
    height: 36px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid #c8cdd4;
    border-radius: 10px;
    background: linear-gradient(180deg, #eef2f7 0%, #d8dee7 100%);
}
.tb5-switch-item .segment-switch-track::before {
    content: '';
    position: absolute;
    top: 1px;
    left: 0;
    width: 50%;
    height: calc(100% - 2px);
    z-index: 1;
    border-radius: 9px;
    background: linear-gradient(180deg, #b7f7c6 0%, #74d98f 100%);
    transition: transform 0.35s cubic-bezier(0.2, 0.8, 0.2, 1), background 0.25s ease;
}
.tb5-switch-item .segment-switch-track.checked-mode::before {
    transform: translateX(100%);
    background: linear-gradient(180deg, #bcdcff 0%, #7fb5ff 100%);
}
.tb5-switch-item .segment-option,
.tb5-switch-item .segment-btn {
    z-index: 2;
    height: 100%;
    border: 0;
    margin: 0;
    padding: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    user-select: none;
    background: transparent;
    color: #2f3a47;
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
}
.tb5-switch-item .segment-btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.dtr-table .th-day-override {
    min-width: 58px;
}

/* DTR Table Wrapper */
.dtr-table-wrapper {
    overflow-x: auto;
    padding: 0;
}

/* Main DTR Table - TB5 Style (Exact Match) */
.dtr-table {
    width: 100%;
    min-width: 2400px;
    border-collapse: collapse;
    font-size: 11px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.dtr-table th,
.dtr-table td {
    border: 1px solid #cbd5e0;
    padding: 7px 5px;
    text-align: center;
    vertical-align: middle;
}
.dtr-table thead th {
    font-weight: 700;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 8px 5px;
}

/* Header Colors - TB5 Excel Theme (Exact Colors) */
.dtr-table .th-date { background: #ffffff; min-width: 70px; font-weight: 700; color: #000; border: 2px solid #a0aec0; }
.dtr-table .th-am, .dtr-table .th-group.th-am { background: #FFCC99; color: #000; font-weight: 700; min-width: 75px; }
.dtr-table .th-pm, .dtr-table .th-group.th-pm { background: #FFCC99; color: #000; font-weight: 700; min-width: 75px; }
.dtr-table .th-absent-col, .dtr-table .th-single.th-absent-col { background: #FF9999; color: #000; font-weight: 700; min-width: 60px; }
.dtr-table .th-training-col, .dtr-table .th-single.th-training-col { background: #1A9E9E; color: #fff; font-weight: 700; min-width: 60px; }
.dtr-table .th-ot-col, .dtr-table .th-single.th-ot-col { background: #99CCFF; color: #000; font-weight: 700; min-width: 70px; }
.dtr-table .th-halfday, .dtr-table .th-group.th-halfday { background: #FFFF99; color: #000; font-weight: 700; min-width: 140px; }
.dtr-table .th-calc { background: #CCFFCC; color: #000; font-weight: 700; min-width: 75px; }
.dtr-table .th-deduct { background: #FFCCCC; color: #000; font-weight: 700; min-width: 80px; }
.dtr-table .th-pay { background: #99FF99; color: #000; font-weight: 700; min-width: 80px; }
.dtr-table .th-auto-calc, .dtr-table .th-group.th-auto-calc { background: #E6F3FF; color: #000; font-weight: 700; min-width: 75px; }
.dtr-table .th-manual { background: #FFFFCC; color: #000; font-weight: 700; min-width: 75px; }
.dtr-table .th-auto-salary { background: #CCFFCC; color: #000; font-weight: 700; min-width: 85px; }
.dtr-table .th-single { background: #ffffff; font-weight: 700; min-width: 65px; }
.dtr-table .th-remarks { background: #FFE6E6; color: #000; font-weight: 600; min-width: 150px; }
.dtr-table .th-action { background: #f0f0f0; color: #000; font-weight: 600; width: 50px; }

/* Sub headers - TB5 Style */
.dtr-table .th-sub { font-size: 9px; padding: 5px 4px; font-weight: 600; }

/* Period Selector Row */
.period-selector-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 15px;
}
.period-select-group {
    display: flex;
    align-items: center;
    gap: 10px;
}
.period-select-group label {
    font-weight: 600;
    color: #4a5568;
}
.period-select-group select {
    padding: 10px 20px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    min-width: 250px;
    background: #fff;
}
.edit-mode-toggle {
    display: flex;
    gap: 10px;
}

/* Date Cell - TB5 Format */
.dtr-table .date-cell { min-width: 70px; padding: 6px 8px; background: #fff; }
.dtr-table .date-display { text-align: center; }
.dtr-table .date-day { font-size: 18px; font-weight: 700; color: #1a202c; line-height: 1.1; }
.dtr-table .date-month { font-size: 10px; color: #4a5568; text-transform: uppercase; font-weight: 600; letter-spacing: 0.3px; }
.dtr-table .date-weekday { font-size: 9px; color: #718096; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.3px; font-weight: 600; }

/* Body Cell Styles for new columns */
.dtr-table .dtr-auto-calc { background: #E6F3FF !important; font-weight: 500; min-width: 75px; }
.dtr-table .dtr-manual { background: #FFFFCC !important; min-width: 75px; }
.dtr-table .dtr-auto-salary { background: #CCFFCC !important; font-weight: 500; min-width: 85px; }
.dtr-table .dtr-remarks-input { text-align: left; font-size: 11px; min-width: 150px; padding: 6px 8px; }

/* Delete row button */
.btn-delete-row {
    background: #e53e3e;
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}
.btn-delete-row:hover:not(:disabled) {
    background: #c53030;
    transform: scale(1.05);
}
.btn-delete-row:disabled {
    background: #cbd5e0;
    cursor: not-allowed;
    opacity: 0.5;
}

/* Body Rows - TB5 Style */
.dtr-table tbody td {
    background: #fff;
    color: #000;
    font-size: 11px;
    font-weight: 500;
}
.dtr-table tbody tr:nth-child(even) td {
    background: #f7fafc;
}
.dtr-table tbody tr:hover td {
    background: #e6f7ff;
    transition: background 0.2s;
}

/* Cell background colors by type - TB5 Theme */
.dtr-table .td-am { background: #f7fafc !important; }
.dtr-table .td-pm { background: #f7fafc !important; }
.dtr-table .td-absent { background: #fff !important; }
.dtr-table .td-training { background: #e6fafa !important; }
.dtr-table .td-ot { background: #fffaf0 !important; }
.dtr-table .td-half { background: #fffff0 !important; }
.dtr-table .td-calc { background: #f0fff4 !important; }
.dtr-table .td-deduct { background: #fff5f5 !important; }
.dtr-table .td-pay { background: #f0fdf4 !important; }
.dtr-table .td-govt { background: #fffef0 !important; }
.dtr-table .td-net { background: #ecfdf5 !important; }

/* Time Input - TB5 Excel Style (Exact Match) */
.dtr-input.time24 {
    width: 58px;
    padding: 5px;
    border: 1px solid #a0aec0;
    border-radius: 4px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    font-family: 'Consolas', 'Monaco', monospace;
}
.dtr-input.time24.input-am { background: #d4f4dd; border-color: #68d391; }
.dtr-input.time24.input-pm { background: #bee3f8; border-color: #63b3ed; }
.dtr-input.time24.input-ot { background: #feebc8; border-color: #f6ad55; }
.dtr-input.time24.input-half { background: #fefcbf; border-color: #f6e05e; }
.dtr-input.time24:focus {
    outline: none;
    border-color: #3182ce;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.25);
    transform: scale(1.02);
    transition: all 0.2s;
}

/* Display Time Values in Table */
.time-val {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12px;
    font-weight: 700;
    color: #2d3748;
}

/* Absent checkbox - TB5 Style */
.dtr-absent {
    width: 20px;
    height: 20px;
    cursor: pointer;
    margin: 0 auto;
    display: block;
}

/* Training checkbox - TB5 Style */
.dtr-training {
    width: 20px;
    height: 20px;
    cursor: pointer;
    margin: 0 auto;
    display: block;
    accent-color: #1A9E9E;
}

/* Shift selector checkbox in DAY OVR/Shift column */
.dtr-day-override-toggle {
    width: 18px;
    height: 18px;
    cursor: pointer;
    margin: 0 auto;
    display: block;
    appearance: auto;
    -webkit-appearance: checkbox;
    accent-color: #2563eb;
}
.dtr-day-override-toggle:disabled {
    opacity: 1;
    filter: none;
    cursor: not-allowed;
}

/* Calculated Cell Highlighting - TB5 Colors */
.calc-highlight {
    background: #d4edda !important;
    font-weight: 700;
    color: #155724;
}
.deduct-highlight {
    background: #ffe0e0 !important;
    font-weight: 700;
    color: #721c24;
}
.pay-highlight {
    background: #c6f6d5 !important;
    font-weight: 700;
    color: #155724;
}
.govt-highlight {
    background: #fff9db !important;
    font-weight: 700;
    color: #856404;
}
.net-highlight {
    background: #b3ffb3 !important;
    font-weight: 700;
    color: #155724;
}

/* Remarks input - TB5 Style */
.dtr-remarks-input {
    width: 95px;
    padding: 5px;
    border: 1px solid #cbd5e0;
    border-radius: 4px;
    text-align: left;
    font-size: 11px;
    background: #fff;
    font-family: Arial, sans-serif;
}
.dtr-remarks-input:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 2px rgba(66, 153, 225, 0.2);
}

/* Calculated/Deduction Input Fields - TB5 Style (Exact Match) */
.dtr-calc-input, .dtr-deduct-input, .dtr-pay-input, .dtr-govt-input, .dtr-net-input {
    width: 65px;
    padding: 4px;
    border: 1px solid transparent;
    border-radius: 4px;
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    background: transparent;
    -moz-appearance: textfield;
    color: #1a202c;
}
.dtr-calc-input::-webkit-inner-spin-button,
.dtr-deduct-input::-webkit-inner-spin-button,
.dtr-pay-input::-webkit-inner-spin-button,
.dtr-govt-input::-webkit-inner-spin-button,
.dtr-net-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.dtr-calc-input { background: #d4edda; border-color: #a3d9a5; }
.dtr-deduct-input { background: #ffe0e0; border-color: #ffb3b3; }
.dtr-pay-input { background: #c6f6d5; border-color: #9ae6b4; }
.dtr-govt-input { background: #fff9db; border-color: #ffd966; }
.dtr-net-input { background: #b3ffb3; border-color: #66ff66; font-size: 12px; font-weight: 700; }
.dtr-govt-input:not([readonly]) {
    border: 2px solid #ecc94b;
    cursor: text;
}
.dtr-govt-input:not([readonly]):focus {
    border-color: #d69e2e;
    outline: none;
    box-shadow: 0 0 0 3px rgba(236, 201, 75, 0.2);
}

/* Readonly inputs styling - TB5 Theme */
input[readonly].dtr-input.time24 {
    background: #edf2f7 !important;
    cursor: default;
    opacity: 0.85;
}
input[readonly].dtr-input.time24.input-am { background: #c6f6d5 !important; }
input[readonly].dtr-input.time24.input-pm { background: #bee3f8 !important; }
input[readonly].dtr-input.time24.input-ot { background: #fed7d7 !important; }
input[readonly].dtr-input.time24.input-half { background: #fefcbf !important; }
input[readonly].dtr-remarks-input { background: #edf2f7 !important; cursor: default; }

/* Totals Row - TB5 Dark Blue */
.dtr-table tfoot .totals-row td {
    background: #1F4E79 !important;
    color: #fff !important;
    font-weight: 700;
    font-size: 12px;
    padding: 12px 5px;
    border-top: 3px solid #163557;
}
.dtr-table tfoot .totals-label {
    text-align: right !important;
    padding-right: 16px !important;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-size: 13px;
}

/* Absent row highlight - TB5 Red Theme */
.dtr-table tbody tr.absent-row td {
    background: #fff5f5 !important;
}
.dtr-table tbody tr.absent-row:hover td {
    background: #fed7d7 !important;
}

/* Centered class */
.centered { text-align: center !important; }

/* ====== Payroll Summary Card - TB5 Style (Exact Match) ====== */
.payroll-summary-card {
    margin-top: 28px;
    background: #fff;
    border-radius: 12px;
    border: 2px solid #cbd5e0;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,.1);
}
.payroll-summary-header {
    padding: 16px 28px;
    font-size: 17px;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, #2563EB, #1d4ed8);
    display: flex;
    align-items: center;
    gap: 12px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.payroll-summary-body { padding: 0; }
.payroll-summary-body table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.payroll-summary-body table td {
    padding: 14px 24px;
    border-bottom: 1px solid #e2e8f0;
    color: #2d3748;
}
.payroll-summary-body table td:last-child {
    text-align: right;
    font-weight: 700;
    min-width: 160px;
    font-family: 'Consolas', 'Monaco', monospace;
}
.payroll-summary-body .section-divider td {
    background: linear-gradient(135deg, #e53e3e, #c53030);
    color: #fff;
    font-weight: 700;
    font-size: 15px;
    padding: 12px 24px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.payroll-summary-body .total-row td {
    background: #edf2f7;
    font-weight: 700;
    font-size: 16px;
    padding: 14px 24px;
    color: #1a202c;
}
.payroll-summary-body .net-pay-row td {
    background: linear-gradient(135deg, #38a169, #2f855a);
    color: #fff !important;
    font-weight: 700;
    font-size: 18px;
    padding: 16px 24px;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.val-positive { color: #38a169; font-weight: 700; }
.val-negative { color: #e53e3e; font-weight: 700; }

@media (max-width: 768px) {
    .dtr-emp-info-row { flex-direction: column; }
    .dtr-rate-row { flex-direction: column; align-items: stretch; }
}

/* ====== Period Selector & Edit Mode - TB5 Style (Exact Match) ====== */
.period-selector-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    gap: 24px;
    flex-wrap: wrap;
    padding: 16px 20px;
    background: linear-gradient(135deg, #f7fafc, #edf2f7);
    border-radius: 10px;
    border: 2px solid #e2e8f0;
}
.period-select-group {
    display: flex;
    align-items: center;
    gap: 14px;
}
.period-select-group label {
    font-weight: 700;
    color: #2d3748;
    white-space: nowrap;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.period-select-group select {
    padding: 11px 22px;
    border: 2px solid #cbd5e0;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    min-width: 300px;
    background: #fff;
    color: #2d3748;
    cursor: pointer;
    transition: all 0.2s;
}
.period-select-group select:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
}
.edit-mode-toggle {
    display: flex;
    gap: 12px;
}
.btn-edit-mode {
    padding: 11px 22px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    color: #2d3748;
    border: 2px solid #cbd5e0;
    transition: all .3s;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.btn-edit-mode:hover {
    background: #f7fafc;
    border-color: #a0aec0;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,.1);
}
.btn-edit-mode.active {
    background: linear-gradient(135deg, #f56565, #e53e3e);
    color: #fff;
    border-color: #c53030;
    box-shadow: 0 4px 12px rgba(245, 101, 101, .4);
}
.btn-edit-mode.active:hover {
    background: linear-gradient(135deg, #e53e3e, #c53030);
}
.btn-save-dtr {
    background: linear-gradient(135deg, #48bb78, #38a169) !important;
    color: #fff !important;
    padding: 11px 24px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.btn-save-dtr:hover {
    box-shadow: 0 6px 16px rgba(72, 187, 120, .5);
    transform: translateY(-2px);
}

/* ====== Custom Modal Overlay ====== */
.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    animation: fadeIn 0.2s ease;
}
.custom-modal-overlay.active {
    display: flex;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes slideIn {
    from { 
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to { 
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
.custom-modal {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    width: 90%;
    overflow: hidden;
    animation: slideIn 0.3s ease;
}
.custom-modal-header {
    padding: 24px 28px;
    background: linear-gradient(135deg, #2563EB, #1d4ed8);
    color: #fff;
    display: flex;
    align-items: center;
    gap: 12px;
}
.custom-modal-header i {
    font-size: 24px;
}
.custom-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}
.custom-modal-body {
    padding: 28px;
    font-size: 15px;
    color: #2d3748;
    line-height: 1.6;
}
.custom-modal-body strong {
    color: #1a202c;
    font-weight: 600;
}
.custom-modal-footer {
    padding: 20px 28px;
    background: #f7fafc;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    border-top: 1px solid #e2e8f0;
}
.modal-btn {
    padding: 11px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-btn-primary {
    background: linear-gradient(135deg, #2563EB, #1d4ed8);
    color: #fff;
}
.modal-btn-primary:hover {
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
    transform: translateY(-1px);
}
.modal-btn-success {
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: #fff;
}
.modal-btn-success:hover {
    box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
    transform: translateY(-1px);
}
.modal-btn-secondary {
    background: #fff;
    color: #4a5568;
    border: 2px solid #e2e8f0;
}
.modal-btn-secondary:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}
.custom-modal.success .custom-modal-header {
    background: linear-gradient(135deg, #48bb78, #38a169);
}
.custom-modal.warning .custom-modal-header {
    background: linear-gradient(135deg, #ed8936, #dd6b20);
}
.custom-modal.error .custom-modal-header {
    background: linear-gradient(135deg, #f56565, #e53e3e);
}

/* === Training Payment Card Styles (from Generatepayroll.php) === */
.trainee-payment-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(66, 153, 225, 0.25);
    border: 3px solid #4299e1;
    overflow: hidden;
}
.trainee-card-header {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.trainee-card-header h3 {
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.trainee-card-header h3 i {
    font-size: 18px;
}
.trainee-badge {
    background: rgba(255,255,255,0.25);
    color: #fff;
    padding: 5px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.trainee-card-body {
    padding: 15px 20px;
}
.trainee-info-text {
    background: #ebf8ff;
    border-left: 4px solid #4299e1;
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    font-size: 13px;
    color: #2c5282;
    display: flex;
    align-items: center;
    gap: 10px;
}
.trainee-info-text i {
    color: #4299e1;
    font-size: 16px;
}
.trainee-form-row {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
    margin-bottom: 20px;
}
.trainee-input-group {
    display: flex;
    flex-direction: column;
}
.trainee-input-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 6px;
}
.trainee-input {
    padding: 8px 10px;
    border: 2px solid #cbd5e0;
    border-radius: 4px;
    font-size: 14px;
    transition: all 0.2s;
    width: 100%;
}
.trainee-input:focus {
    border-color: #4299e1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
}
.trainee-impact-notice {
    background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
    border: 1px solid #48bb78;
    border-radius: 6px;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #22543d;
}
.trainee-impact-notice i {
    color: #38a169;
    font-size: 16px;
}
.trainee-impact-notice strong {
    color: #22543d;
    font-weight: 700;
}
.input-with-prefix {
    position: relative;
    display: flex;
    align-items: center;
}
.input-with-prefix .prefix {
    position: absolute;
    left: 8px;
    color: #718096;
    font-weight: 600;
    font-size: 14px;
    z-index: 1;
}
.input-with-prefix input {
    padding-left: 24px;
}

/* === Payroll Summary Compact Styles === */
.payroll-summary-compact {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 2px solid #e2e8f0;
    overflow: hidden;
}
.summary-title {
    background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
    color: #fff;
    padding: 12px 20px;
    font-size: 16px;
    font-weight: 700;
}
.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    background: #e2e8f0;
}
.summary-item {
    background: #fff;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.summary-item.final {
    grid-column: 1 / -1;
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    padding: 15px 20px;
}
.summary-label {
    font-size: 13px;
    color: #4a5568;
    font-weight: 600;
    display: flex;
    align-items: center;
    min-width: 140px;
}
.summary-item.final .summary-label {
    color: #fff;
    font-size: 15px;
    font-weight: 700;
}
.summary-value {
    font-size: 14px;
    font-weight: 700;
    color: #2d3748;
    text-align: right;
    min-width: 100px;
}
.summary-value.positive {
    color: #38a169;
}
.summary-value.negative {
    color: #e53e3e;
}
.summary-value.final-value {
    color: #fff;
    font-size: 22px;
    font-weight: 700;
}
/* Vertical layout for payroll summary */
.summary-vertical {
    display: flex;
    flex-direction: column;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 11px 20px;
    border-bottom: 1px solid #f1f5f9;
    background: #fff;
    transition: background 0.2s;
}
.summary-row:hover {
    background: #fafbfc;
}
.summary-row:last-child {
    border-bottom: none;
}
.summary-row.final {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    padding: 15px 20px;
}
.summary-row.final .summary-label {
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.summary-row.final .summary-value {
    color: #fff;
    font-size: 20px;
    font-weight: 700;
}
/* Two-column layout for payroll summary */
.summary-two-column {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    background: #fff;
    margin-bottom: 15px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.summary-column {
    background: #fff;
    display: flex;
    flex-direction: column;
}
.summary-column-left {
    border-right: 1px solid #e2e8f0;
}
.summary-section-header {
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    padding: 12px 20px;
    font-size: 12px;
    font-weight: 700;
    color: #2d3748;
    border-bottom: 1px solid #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
}
.summary-row-full {
    grid-column: 1 / -1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #fff;
    border-radius: 6px;
    border: 2px solid #48bb78;
}
.summary-row-full.final {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    padding: 16px 20px;
    border: none;
    box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}
.summary-row-full.final .summary-label {
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.summary-row-full.final .summary-value {
    color: #fff;
    font-size: 22px;
    font-weight: 700;
}
.summary-row.summary-subtotal {
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    font-weight: 700;
    border-top: 1px solid #cbd5e0;
    margin-top: auto;
}
@media (max-width: 992px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    .summary-two-column {
        grid-template-columns: 1fr;
    }
    .summary-column-left {
        border-right: none;
        border-bottom: 2px solid #e2e8f0;
    }
}

</style>

<script>
let currentEmployeeId = null;
let currentPeriodId   = null;
let currentDTRRecords = [];
let currentEmpInfo    = null;
let currentComp       = null;
let editModeEnabled   = false;
let payrollListCheckedDaysEditMode = false;
let payrollListCheckedDaysDefaultSnapshot = null;
let payrollListCheckedDaysOverrideSnapshot = null;
let payrollListDayOverrideValues = {};
let payrollListLoadedDayOverrideMeta = null;

const DEFAULT_PAYROLL_LIST_SHIFT_RULES = {
    shift_1: {
        shift_code: 'shift_1',
        shift_name: 'Shift 1',
        per_day_rate: 0,
        ot_rate: 0,
        time_in: '08:00',
        time_out: '17:00'
    },
    shift_2: {
        shift_code: 'shift_2',
        shift_name: 'Shift 2',
        per_day_rate: 0,
        ot_rate: 0,
        time_in: '08:00',
        time_out: '17:00'
    }
};

const DEFAULT_PAYROLL_LIST_LATE_RULES = [];

let payrollListShiftRules = JSON.parse(JSON.stringify(DEFAULT_PAYROLL_LIST_SHIFT_RULES));
let payrollListLateRules = JSON.parse(JSON.stringify(DEFAULT_PAYROLL_LIST_LATE_RULES));
let payrollListShiftRulesLoaded = false;

function clonePayrollListShiftRules() {
    return JSON.parse(JSON.stringify(DEFAULT_PAYROLL_LIST_SHIFT_RULES));
}

function clonePayrollListLateRules() {
    return JSON.parse(JSON.stringify(DEFAULT_PAYROLL_LIST_LATE_RULES));
}

function normalizePayrollListRuleRate(value) {
    const rate = parseFloat(value);
    if (!isFinite(rate) || rate < 0) return 0;
    return parseFloat(rate.toFixed(2));
}

function normalizePayrollListRuleTime(value, fallback) {
    const raw = String(value || '').trim();
    const match = raw.match(/^(\d{1,2}):(\d{2})/);
    if (!match) return fallback;
    const h = parseInt(match[1], 10);
    const m = parseInt(match[2], 10);
    if (isNaN(h) || isNaN(m) || h < 0 || h > 23 || m < 0 || m > 59) {
        return fallback;
    }
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}

function normalizePayrollListLateRules(rawRules, legacyRule = null) {
    let source = [];
    if (Array.isArray(rawRules)) {
        source = rawRules;
    } else if (rawRules && typeof rawRules === 'object') {
        source = [rawRules];
    }

    if (source.length === 0 && legacyRule && typeof legacyRule === 'object') {
        source = [legacyRule];
    }

    return source
        .map(item => {
            const actualRaw = parseFloat(item?.actual_minutes);
            const equivalentRaw = parseFloat(item?.equivalent_minutes);
            const actualMinutes = (isFinite(actualRaw) && actualRaw > 0) ? Number(actualRaw.toFixed(2)) : null;
            const equivalentMinutes = (isFinite(equivalentRaw) && equivalentRaw > 0) ? Number(equivalentRaw.toFixed(2)) : null;
            if (actualMinutes === null || equivalentMinutes === null) {
                return null;
            }
            const multiplier = equivalentMinutes / Math.max(0.01, actualMinutes);
            return {
                actual_minutes: actualMinutes,
                equivalent_minutes: equivalentMinutes,
                multiplier: Number(multiplier.toFixed(4))
            };
        })
        .filter(Boolean)
        .sort((a, b) => a.actual_minutes - b.actual_minutes)
        .slice(0, 3);
}

function getPayrollListLateEquivalentMinutes(lateMinutes) {
    const mins = parseFloat(lateMinutes);
    if (!isFinite(mins) || mins <= 0) return 0;
    let multiplier = 1;
    payrollListLateRules.forEach(rule => {
        if (mins >= rule.actual_minutes) {
            multiplier = rule.multiplier;
        }
    });
    return mins * multiplier;
}

function mergePayrollListShiftRules(rawRules) {
    const merged = clonePayrollListShiftRules();
    ['shift_1', 'shift_2'].forEach(code => {
        const incoming = rawRules && rawRules[code] ? rawRules[code] : {};
        merged[code] = {
            shift_code: code,
            shift_name: String(incoming.shift_name || merged[code].shift_name),
            per_day_rate: normalizePayrollListRuleRate(incoming.per_day_rate),
            ot_rate: normalizePayrollListRuleRate(incoming.ot_rate),
            time_in: normalizePayrollListRuleTime(incoming.time_in, merged[code].time_in),
            time_out: normalizePayrollListRuleTime(incoming.time_out, merged[code].time_out)
        };
    });
    return merged;
}

function loadPayrollListShiftRules(force = false) {
    if (payrollListShiftRulesLoaded && !force) {
        return Promise.resolve(payrollListShiftRules);
    }

    return fetch('backup_api.php?action=get_salary_rules')
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                payrollListShiftRules = mergePayrollListShiftRules(data.rules || {});
                payrollListLateRules = normalizePayrollListLateRules(data.late_rules || [], data.late_rule || null);
                payrollListShiftRulesLoaded = true;
            }
            return payrollListShiftRules;
        })
        .catch(error => {
            console.error('Failed to load salary rules for payroll list:', error);
            payrollListShiftRules = clonePayrollListShiftRules();
            payrollListLateRules = clonePayrollListLateRules();
            return payrollListShiftRules;
        });
}

function getPayrollListShiftValues() {
    const shift1 = payrollListShiftRules?.shift_1 || DEFAULT_PAYROLL_LIST_SHIFT_RULES.shift_1;
    const shift2 = payrollListShiftRules?.shift_2 || DEFAULT_PAYROLL_LIST_SHIFT_RULES.shift_2;
    const shift1Rate = normalizePayrollListRuleRate(shift1.per_day_rate);
    const shift2Rate = normalizePayrollListRuleRate(shift2.per_day_rate);
    const shift1OtRate = normalizePayrollListRuleRate(shift1.ot_rate);
    const shift2OtRate = normalizePayrollListRuleRate(shift2.ot_rate);

    return {
        shift1: {
            perDay: shift1Rate > 0 ? String(shift1Rate) : '',
            otRate: String(shift1OtRate),
            lateStart: normalizePayrollListRuleTime(shift1.time_in, '08:00'),
            endTime: normalizePayrollListRuleTime(shift1.time_out, '17:00')
        },
        shift2: {
            perDay: shift2Rate > 0 ? String(shift2Rate) : '',
            otRate: String(shift2OtRate),
            lateStart: normalizePayrollListRuleTime(shift2.time_in, '08:00'),
            endTime: normalizePayrollListRuleTime(shift2.time_out, '17:00')
        }
    };
}

function getPayrollListShiftDefaultMeta(defaultValues) {
    const normalizedFallback = normalizePayrollOverrideValues(defaultValues);
    const shiftRules = getPayrollListShiftValues();
    const shift1Values = normalizePayrollOverrideValues(shiftRules.shift1, normalizedFallback);
    const shift2Values = normalizePayrollOverrideValues(shiftRules.shift2, shift1Values);

    return {
        default_values: shift1Values,
        checked_values: shift2Values,
        checked_rows: [],
        row_values: {}
    };
}

function getCurrentPayrollEditorValues() {
    return {
        perDay: document.getElementById('edit_per_day')?.value || '',
        otRate: document.getElementById('edit_ot_rate')?.value || '0.00',
        lateStart: document.getElementById('edit_late_start')?.value || '8:00',
        endTime: document.getElementById('edit_end_time')?.value || '17:00'
    };
}

function normalizePayrollOverrideValues(values, fallback = {}) {
    const fallbackPerDay = fallback.perDay !== undefined ? String(fallback.perDay) : '';
    const fallbackOtRate = fallback.otRate !== undefined ? String(fallback.otRate) : '0.00';
    const fallbackLateStart = fallback.lateStart || '8:00';
    const fallbackEndTime = fallback.endTime || '17:00';
    return {
        perDay: values && values.perDay !== undefined && values.perDay !== null ? String(values.perDay) : fallbackPerDay,
        otRate: values && values.otRate !== undefined && values.otRate !== null ? String(values.otRate) : fallbackOtRate,
        lateStart: values && values.lateStart ? String(values.lateStart) : fallbackLateStart,
        endTime: values && values.endTime ? String(values.endTime) : fallbackEndTime
    };
}

function parsePayrollDayOverrideMeta(comp, defaultValues) {
    const fallback = normalizePayrollOverrideValues(defaultValues);
    const fallbackShiftMeta = getPayrollListShiftDefaultMeta(fallback);
    let notes = null;

    if (comp && comp.other_deductions_notes) {
        try {
            notes = JSON.parse(comp.other_deductions_notes);
        } catch (e) {
            console.warn('Failed to parse other_deductions_notes for day_override metadata');
        }
    }

    const rawMeta = notes && notes.day_override && typeof notes.day_override === 'object' ? notes.day_override : null;
    if (!rawMeta) {
        return fallbackShiftMeta;
    }

    // New schema from Generate Payroll: shift_1 / shift_2 / shift_2_rows
    if (rawMeta.mode === 'shift_day_override' || rawMeta.shift_1 || rawMeta.shift_2) {
        const shift2Rows = Array.isArray(rawMeta.shift_2_rows)
            ? rawMeta.shift_2_rows.map(v => String(v))
            : (Array.isArray(rawMeta.checked_rows) ? rawMeta.checked_rows.map(v => String(v)) : []);

        return {
            // Always follow current global settings rules for values.
            default_values: fallbackShiftMeta.default_values,
            checked_values: fallbackShiftMeta.checked_values,
            checked_rows: shift2Rows,
            row_values: {}
        };
    }

    const checkedRows = Array.isArray(rawMeta.checked_rows) ? rawMeta.checked_rows.map(v => String(v)) : [];

    return {
        // Legacy metadata: keep row mapping only, use current global rule values.
        default_values: fallbackShiftMeta.default_values,
        checked_values: fallbackShiftMeta.checked_values,
        checked_rows: checkedRows,
        row_values: {}
    };
}

function buildPayrollListDayOverrideMetaForSave() {
    const table = document.getElementById('dtrEditTable');
    if (!table) return null;

    const defaultValues = normalizePayrollOverrideValues(payrollListCheckedDaysDefaultSnapshot || getCurrentPayrollEditorValues());
    const checkedValues = normalizePayrollOverrideValues(payrollListCheckedDaysOverrideSnapshot || defaultValues, defaultValues);
    const checkedRows = [];
    const rowValues = {};

    table.querySelectorAll('tbody tr.dtr-data-row').forEach((row) => {
        const idx = row.dataset.idx;
        const rowDate = row.dataset.dtrDate || '';
        const toggle = row.querySelector('.dtr-day-override-toggle');
        if (!idx || !rowDate || !toggle || toggle.disabled || !toggle.checked) return;

        checkedRows.push(rowDate);
        if (payrollListDayOverrideValues[idx]) {
            rowValues[rowDate] = normalizePayrollOverrideValues(payrollListDayOverrideValues[idx], checkedValues);
        }
    });

    const shift1Values = normalizePayrollOverrideValues(defaultValues);
    const shift2Values = normalizePayrollOverrideValues(checkedValues, shift1Values);

    return {
        version: 2,
        mode: 'shift_day_override',
        shift_1: shift1Values,
        shift_2: shift2Values,
        shift_2_rows: checkedRows,
        row_values: rowValues,
        // Keep legacy keys for backward compatibility with older pages.
        default_values: shift1Values,
        checked_values: shift2Values,
        checked_rows: checkedRows
    };
}

function getPayrollCheckedDayRows() {
    const rows = [];
    document.querySelectorAll('#dtrEditTable .dtr-day-override-toggle').forEach(toggle => {
        if (toggle.checked && !toggle.disabled) {
            rows.push(toggle.getAttribute('data-idx'));
        }
    });
    return rows;
}

function setPayrollCheckedModeLabel(enabled) {
    const defaultBtn = document.getElementById('pl_checked_days_default_btn');
    const checkedBtn = document.getElementById('pl_checked_days_checked_btn');
    const track = document.querySelector('#employee_dtr_content .segment-switch-track');
    if (defaultBtn) defaultBtn.classList.toggle('active', !enabled);
    if (checkedBtn) checkedBtn.classList.toggle('active', enabled);
    if (track) track.classList.toggle('checked-mode', enabled);
}

function setPayrollCheckedDaysMode(enabled) {
    const isEnabled = !!enabled;
    if (payrollListCheckedDaysEditMode === isEnabled) {
        const targetSame = isEnabled
            ? (payrollListCheckedDaysOverrideSnapshot || getCurrentPayrollEditorValues())
            : (payrollListCheckedDaysDefaultSnapshot || getCurrentPayrollEditorValues());
        loadPayrollCheckedValuesIntoHeader(targetSame);
        setPayrollCheckedModeLabel(isEnabled);
        updateRatesFromDaily();
        return;
    }

    // Persist values only while editing; in view mode this switch is preview-only.
    if (editModeEnabled) {
        const currentValues = getCurrentPayrollEditorValues();
        if (payrollListCheckedDaysEditMode) {
            payrollListCheckedDaysOverrideSnapshot = currentValues;
        } else {
            payrollListCheckedDaysDefaultSnapshot = currentValues;
        }
    }

    payrollListCheckedDaysEditMode = isEnabled;

    if (!payrollListCheckedDaysDefaultSnapshot) {
        payrollListCheckedDaysDefaultSnapshot = getCurrentPayrollEditorValues();
    }
    if (!payrollListCheckedDaysOverrideSnapshot) {
        payrollListCheckedDaysOverrideSnapshot = getCurrentPayrollEditorValues();
    }

    const target = isEnabled ? payrollListCheckedDaysOverrideSnapshot : payrollListCheckedDaysDefaultSnapshot;
    loadPayrollCheckedValuesIntoHeader(target);
    setPayrollCheckedModeLabel(isEnabled);
    updateRatesFromDaily();
}

function loadPayrollCheckedValuesIntoHeader(values) {
    const perDay = document.getElementById('edit_per_day');
    const otRate = document.getElementById('edit_ot_rate');
    const lateStart = document.getElementById('edit_late_start');
    const endTime = document.getElementById('edit_end_time');
    if (perDay) perDay.value = values?.perDay || '';
    if (otRate) otRate.value = values?.otRate || '0.00';
    if (lateStart) lateStart.value = values?.lateStart || '8:00';
    if (endTime) endTime.value = values?.endTime || '17:00';
}

function applyPayrollCheckedHeaderValuesToRows() {
    const values = getCurrentPayrollEditorValues();
    payrollListCheckedDaysOverrideSnapshot = values;

    getPayrollCheckedDayRows().forEach(idx => {
        const existing = payrollListDayOverrideValues[idx] || {};
        payrollListDayOverrideValues[idx] = {
            ...existing,
            perDay: values.perDay,
            otRate: values.otRate,
            lateStart: values.lateStart,
            endTime: values.endTime
        };
        const row = document.querySelector(`#dtrEditTable tr.dtr-data-row[data-idx="${idx}"]`);
        if (row) recalculateRow(row);
    });
    recalculateTotals();
}

function handleDayOverrideToggleEdit(toggleEl) {
    const idx = toggleEl?.getAttribute('data-idx');
    if (!idx) return;

    if (toggleEl.checked) {
        if (!payrollListDayOverrideValues[idx]) {
            const seed = payrollListCheckedDaysOverrideSnapshot || getCurrentPayrollEditorValues();
            payrollListDayOverrideValues[idx] = {
                perDay: seed.perDay || '',
                otRate: seed.otRate || '0.00',
                lateStart: seed.lateStart || '8:00',
                endTime: seed.endTime || '17:00'
            };
        }
    }

    const row = toggleEl.closest('tr');
    if (row) recalculateRow(row);
    recalculateTotals();
}

function initializePayrollListOverrideState(records = [], dayOverrideMeta = null) {
    payrollListCheckedDaysEditMode = false;
    payrollListDayOverrideValues = {};

    const baseValues = getCurrentPayrollEditorValues();
    const meta = dayOverrideMeta || parsePayrollDayOverrideMeta(currentComp, baseValues);
    payrollListLoadedDayOverrideMeta = meta;

    payrollListCheckedDaysDefaultSnapshot = normalizePayrollOverrideValues(meta.default_values, baseValues);
    payrollListCheckedDaysOverrideSnapshot = normalizePayrollOverrideValues(meta.checked_values, payrollListCheckedDaysDefaultSnapshot);

    loadPayrollCheckedValuesIntoHeader(payrollListCheckedDaysDefaultSnapshot);
    setPayrollCheckedModeLabel(false);

    const checkedRowsSet = new Set((meta.checked_rows || []).map(v => String(v)));
    const rowValuesByDate = (meta.row_values && typeof meta.row_values === 'object') ? meta.row_values : {};
    const rows = document.querySelectorAll('#dtrEditTable tbody tr.dtr-data-row');

    rows.forEach((row) => {
        const idx = row.dataset.idx;
        const rowDate = String(row.dataset.dtrDate || '');
        const toggle = row.querySelector('.dtr-day-override-toggle');
        const isAbsent = row.querySelector(`input[name="absent_${idx}"]`)?.checked || false;
        const isTraining = row.querySelector(`input[name="training_${idx}"]`)?.checked || false;
        const rowBlocked = isAbsent || isTraining;

        if (toggle) {
            const shouldCheck = !rowBlocked && checkedRowsSet.has(rowDate);
            toggle.checked = shouldCheck;
            if (!editModeEnabled) {
                toggle.disabled = true;
            } else {
                toggle.disabled = rowBlocked;
            }
        }

        if (idx && rowDate && rowValuesByDate[rowDate]) {
            payrollListDayOverrideValues[idx] = normalizePayrollOverrideValues(
                rowValuesByDate[rowDate],
                payrollListCheckedDaysOverrideSnapshot
            );
        }
    });
}

/* ====== Custom Modal Functions ====== */
function showCustomConfirm(message, title = 'Confirm Action', onConfirm = null, onCancel = null) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'custom-modal-overlay active';
        overlay.innerHTML = `
            <div class="custom-modal">
                <div class="custom-modal-header">
                    <i class="fas fa-question-circle"></i>
                    <h3>${title}</h3>
                </div>
                <div class="custom-modal-body">
                    ${message}
                </div>
                <div class="custom-modal-footer">
                    <button class="modal-btn modal-btn-secondary" id="modalCancelBtn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="modal-btn modal-btn-primary" id="modalConfirmBtn">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        const confirmBtn = overlay.querySelector('#modalConfirmBtn');
        const cancelBtn = overlay.querySelector('#modalCancelBtn');
        
        confirmBtn.onclick = () => {
            document.body.removeChild(overlay);
            if (onConfirm) onConfirm();
            resolve(true);
        };
        
        cancelBtn.onclick = () => {
            document.body.removeChild(overlay);
            if (onCancel) onCancel();
            resolve(false);
        };
        
        overlay.onclick = (e) => {
            if (e.target === overlay) {
                document.body.removeChild(overlay);
                if (onCancel) onCancel();
                resolve(false);
            }
        };
    });
}

function showCustomAlert(message, title = 'Notification', type = 'success') {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'custom-modal-overlay active';
        
        let icon = 'fa-check-circle';
        if (type === 'error') icon = 'fa-exclamation-circle';
        if (type === 'warning') icon = 'fa-exclamation-triangle';
        if (type === 'info') icon = 'fa-info-circle';
        
        overlay.innerHTML = `
            <div class="custom-modal ${type}">
                <div class="custom-modal-header">
                    <i class="fas ${icon}"></i>
                    <h3>${title}</h3>
                </div>
                <div class="custom-modal-body">
                    ${message}
                </div>
                <div class="custom-modal-footer">
                    <button class="modal-btn modal-btn-${type === 'success' ? 'success' : 'primary'}" id="modalOkBtn">
                        <i class="fas fa-check"></i> OK
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        const okBtn = overlay.querySelector('#modalOkBtn');
        okBtn.onclick = () => {
            document.body.removeChild(overlay);
            resolve(true);
        };
        
        overlay.onclick = (e) => {
            if (e.target === overlay) {
                document.body.removeChild(overlay);
                resolve(true);
            }
        };
    });
}

function showPayslipGeneratedModal(payslipId, cutoffLabel = 'Selected Period') {
    return new Promise((resolve) => {
        const numericId = parseInt(payslipId, 10);
        if (!Number.isFinite(numericId) || numericId <= 0) {
            showCustomAlert('Unable to open payslip preview because the payslip ID is invalid.', 'Invalid Payslip ID', 'error');
            resolve(false);
            return;
        }

        const safeId = encodeURIComponent(String(numericId));
        const pdfUrl = `generate_payslip_pdf.php?payslip_id=${safeId}`;
        const overlay = document.createElement('div');
        overlay.className = 'custom-modal-overlay active';
        const safeCutoffLabel = String(cutoffLabel || 'Selected Period')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        overlay.innerHTML = `
            <div class="custom-modal success" style="max-width: 1020px; width: 95%; max-height: 94vh; display: flex; flex-direction: column;">
                <div class="custom-modal-header">
                    <i class="fas fa-file-pdf"></i>
                    <h3>Payslip Generated</h3>
                </div>
                <div class="custom-modal-body" style="padding: 16px 20px; overflow-y: auto;">
                    <div style="font-size: 16px; margin-bottom: 10px;">Payslip is ready for <strong>${safeCutoffLabel}</strong>.</div>
                    <div style="color: #4a5568; margin-bottom: 12px;">Preview is shown below in the same receipt style as Payslip tab.</div>
                    <div id="modalPayslipPreview" style="border: 1px solid #cbd5e0; border-radius: 8px; overflow: hidden; background: #edf2f7; min-height: 220px;">
                        <div style="padding: 28px; text-align: center; color: #4a5568; font-weight: 600;">
                            <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Loading payslip preview...
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer" style="justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <button class="modal-btn modal-btn-secondary" id="modalClosePayslipBtn">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <a class="modal-btn modal-btn-success" id="modalDownloadPayslipBtn" href="${pdfUrl}" target="_blank" rel="noopener">
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                </div>
            </div>
        `;

        const closeModal = () => {
            if (overlay.parentNode) {
                document.body.removeChild(overlay);
            }
            resolve(true);
        };

        document.body.appendChild(overlay);

        const previewContainer = overlay.querySelector('#modalPayslipPreview');
        fetchPayslipPreviewData(numericId)
            .then((payslip) => {
                if (payslip) {
                    previewContainer.innerHTML = generatePayslipPreviewReceiptHTML(payslip);
                    return;
                }
                previewContainer.innerHTML = `
                    <iframe src="${pdfUrl}"
                            title="Payslip Preview"
                            style="width: 100%; height: min(70vh, 760px); border: 0; display: block; background: #fff;"
                            loading="lazy"></iframe>
                `;
            })
            .catch((err) => {
                console.warn('Failed to render payslip tab preview, using PDF fallback:', err);
                previewContainer.innerHTML = `
                    <iframe src="${pdfUrl}"
                            title="Payslip Preview"
                            style="width: 100%; height: min(70vh, 760px); border: 0; display: block; background: #fff;"
                            loading="lazy"></iframe>
                `;
            });

        const closeBtn = overlay.querySelector('#modalClosePayslipBtn');
        closeBtn.onclick = closeModal;

        const downloadBtn = overlay.querySelector('#modalDownloadPayslipBtn');
        downloadBtn.onclick = () => {
            // Keep UX simple: close modal after triggering download/open.
            setTimeout(closeModal, 120);
        };

        overlay.onclick = (e) => {
            if (e.target === overlay) {
                closeModal();
            }
        };
    });
}

async function fetchPayslipPreviewData(payslipId) {
    const targetId = parseInt(payslipId, 10);
    if (!Number.isFinite(targetId) || targetId <= 0) {
        return null;
    }
    if (!currentEmployeeId) {
        return null;
    }

    const resp = await fetch(`get_employee_payslips.php?employee_id=${currentEmployeeId}`);
    const data = await resp.json();
    if (!data || !data.success || !Array.isArray(data.payslips)) {
        return null;
    }

    const exact = data.payslips.find((p) => parseInt(p.id, 10) === targetId);
    return exact || null;
}

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

function getGovtDeductionCategory(remark) {
    const upper = String(remark || '').toUpperCase().trim();
    if (!upper) return '';
    if (upper.includes('PHILHEALTH') || upper.includes('PHIL HEALTH')) return 'philhealth';
    if (upper.includes('PAGIBIG') || upper.includes('PAG-IBIG') || upper.includes('HDMF')) return 'pagibig';
    if (upper.includes('WITHHOLD') || upper.includes('TAX')) return 'withholding';
    if (upper.includes('SSS')) return 'sss';
    return 'other';
}

function createPayrollBenefitBreakdown() {
    return {
        sss: 0,
        philhealth: 0,
        pagibig: 0,
        withholding: 0,
        other: 0,
        otherLabels: [],
        hasClassifiedRows: false
    };
}

function getOtherDeductionLabel(otherLabels = []) {
    const labels = Array.isArray(otherLabels)
        ? [...new Set(otherLabels.map(label => String(label || '').trim()).filter(Boolean))]
        : [];

    if (labels.length === 0) return 'Others / C.A.:';
    if (labels.length === 1) return `${labels[0]}:`;

    const preview = labels.slice(0, 2).join(' / ');
    return labels.length > 2 ? `${preview} +${labels.length - 2} more:` : `${preview}:`;
}

function escapeHtmlInline(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getPayrollBenefitBreakdownFromRecords(records = []) {
    const breakdown = createPayrollBenefitBreakdown();
    if (!Array.isArray(records)) return breakdown;

    records.forEach((rec) => {
        const amount = parseFloat(rec?.govt_deduct || 0) || 0;
        const remark = String(rec?.remarks || '').trim();
        if (amount <= 0 || !remark) return;

        breakdown.hasClassifiedRows = true;
        const category = getGovtDeductionCategory(remark);
        if (category && Object.prototype.hasOwnProperty.call(breakdown, category)) {
            breakdown[category] += amount;
            if (category === 'other' && !breakdown.otherLabels.includes(remark)) {
                breakdown.otherLabels.push(remark);
            }
        }
    });

    return breakdown;
}

function getPayrollBenefitBreakdownFromTable(table) {
    const breakdown = createPayrollBenefitBreakdown();
    if (!table) return breakdown;

    table.querySelectorAll('tbody tr.dtr-data-row').forEach((row) => {
        const amount = parseFloat((row.querySelector('input.dtr-govt-input')?.value || '0').replace(/,/g, '')) || 0;
        const remark = String(row.querySelector('input.dtr-remarks-input')?.value || '').trim();
        if (amount <= 0 || !remark) return;

        breakdown.hasClassifiedRows = true;
        const category = getGovtDeductionCategory(remark);
        if (category && Object.prototype.hasOwnProperty.call(breakdown, category)) {
            breakdown[category] += amount;
            if (category === 'other' && !breakdown.otherLabels.includes(remark)) {
                breakdown.otherLabels.push(remark);
            }
        }
    });

    return breakdown;
}

function generatePayslipPreviewReceiptHTML(payslip) {
        const notes = parsePayslipNotes(payslip.other_deductions_notes || '{}');
        const dtr = notes.dtr_data || {};

        const regularPay  = parseFloat(payslip.basic_pay || 0);
        const otPay       = parseFloat(payslip.ot_pay || 0);
        const otHours     = parseFloat(payslip.total_ot_hours || 0);
        const workHours   = parseFloat(payslip.total_work_hours || 0);
        const incentive   = parseFloat(notes.commission || 0);
        const paidLeaves  = parseFloat(notes.sick_pay || 0);
        const holidayPay  = parseFloat(notes.holiday_pay || 0);
        const othersAdj   = parseFloat(notes.expense || 0);
        const trainingPay = parseFloat(payslip.trainings_cost || payslip.training_amount || notes.training_pay || 0);

        const whTax      = parseFloat(payslip.withholding_tax || 0);
        const sss        = parseFloat(payslip.sss_contribution || 0);
        const philhealth = parseFloat(payslip.philhealth_contribution || 0);
        const pagibig    = parseFloat(payslip.pagibig_contribution || 0);
        const lateDeduct      = parseFloat(dtr.late_deduct || payslip.late_deduction || 0);
        const undertimeDeduct = parseFloat(dtr.undertime_deduct || payslip.undertime_deduction || 0);
        const halfdayDeduct   = parseFloat(dtr.halfday_deduct || notes.halfday_deduct || 0);
        const loan       = (parseFloat(notes.student_loan || 0)
                                            + parseFloat(notes.union_fees || 0)
                                            + parseFloat(notes.pension || 0));
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
        const netPay               = getPayslipDisplayNetPay(payslip, othersCa, governmentDeductions);
        const otMinutes            = Math.round(otHours * 60);

        const cutoffTypeFromNotes = String(notes.cutoff_type || '').trim().toLowerCase();
        const cutoffTypeFallback = getPayrollListCutoffTypeFromDate(payslip.start_date);
        const isFirstCut = cutoffTypeFromNotes
            ? cutoffTypeFromNotes === 'first'
            : cutoffTypeFallback === 'first';
        const formatCutoffRangeFromPeriod = (startDate, endDate, isFirstCutoff) => {
            const startLabel = formatPayrollListMonthDay(startDate);
            const endLabel = formatPayrollListMonthDay(endDate);
            if (startLabel && endLabel) return `${startLabel} - ${endLabel}`;

            const anchorRaw = String(payslip.pay_date || payslip.created_at || '').trim();
            const anchor = new Date(anchorRaw);
            if (!isNaN(anchor.getTime())) {
                const year = anchor.getFullYear();
                const month = anchor.getMonth();
                const rangeStart = isFirstCutoff
                    ? new Date(year, month - 1, 28)
                    : new Date(year, month, 13);
                const rangeEnd = isFirstCutoff
                    ? new Date(year, month, 12)
                    : new Date(year, month, 27);
                const fallbackStart = formatPayrollListMonthDay(toPayrollIsoDate(rangeStart));
                const fallbackEnd = formatPayrollListMonthDay(toPayrollIsoDate(rangeEnd));
                if (fallbackStart && fallbackEnd) return `${fallbackStart} - ${fallbackEnd}`;
            }

            return '';
        };
        const cutoffRange = formatCutoffRangeFromPeriod(payslip.start_date, payslip.end_date, isFirstCut);
        const cutoffStr   = cutoffRange
            ? `${isFirstCut ? '1st CUT OFF' : '2nd CUT OFF'} (${cutoffRange})`
            : `${isFirstCut ? '1st CUT OFF' : '2nd CUT OFF'}`;
        const cutoffClass = isFirstCut ? 'tb5-pill-amber' : 'tb5-pill-rose';
        const payDateFmt  = payslip.pay_date
                ? new Date(payslip.pay_date).toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'})
                : new Date(payslip.created_at).toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});

        const fmt = (v) => parseFloat(v).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const escapeHtml = (txt) => String(txt)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        const amtCell = (v) => v > 0 ? `<span style="font-weight:700;color:#0f3460;">P ${fmt(v)}</span>` : `<span style="color:#94a3b8;">&mdash;</span>`;
        const dedCell = (v) => v > 0 ? `<span style="font-weight:700;color:#dc2626;">P ${fmt(v)}</span>` : `<span style="color:#94a3b8;">&mdash;</span>`;
        const remarksCell = (items) => (Array.isArray(items) && items.length > 0)
                ? `<span style="display:inline-block;max-width:170px;text-align:right;color:#475569;font-size:7pt;line-height:1.2;white-space:normal;">${items.map(item => `&#8226; ${escapeHtml(item)}`).join('<br>')}</span>`
                : `<span style="color:#94a3b8;">&mdash;</span>`;

        const employeeName = escapeHtml(payslip.employee_name || '');
        const statusText = escapeHtml(payslip.status || 'computed');

        return `
        <style>
        .tb5-wrap { font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; color: #1e293b; background:#fff; }
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

        <table class="tb5-info-strip">
        <tr>
            <td style="width:36%;">
                <span class="tb5-lbl">Employee</span>
                <span class="tb5-pill-green">${employeeName}</span>
            </td>
            <td class="tb5-divv" style="width:18%;">
                <span class="tb5-lbl">Status</span>
                <span class="tb5-pill-status">${statusText}</span>
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

        <table class="tb5-body-wrap">
        <tr>
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
                    <tr class="tb5-dr"><td class="tb5-rl">Training Pay</td><td class="tb5-rv" style="text-align:right;">${amtCell(trainingPay)}</td></tr>
                </table>
            </td>

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

        <table class="tb5-footer">
        <tr>
            <td class="tb5-footer-l" style="width:50%;">
                <span class="tb5-foot-stamp">Received By</span>
                <span class="tb5-sig-line"></span>
                <span class="tb5-sig-note">Employee Signature and Date</span>
            </td>
            <td style="width:50%;">
                <span class="tb5-foot-stamp">Approved By</span>
                <span class="tb5-sig-line"></span>
                <span class="tb5-sig-note">Danver S. Reyes - Authorized Signatory</span>
            </td>
        </tr>
        </table>
        </div>`;
}

async function resolveFullMonthPayslipId() {
    const directCompId = parseInt(currentComp?.id, 10);
    if (Number.isFinite(directCompId) && directCompId > 0) {
        return directCompId;
    }

    if (!currentEmployeeId) {
        return null;
    }

    const targetPeriodId = parseInt(currentPeriodId, 10);

    try {
        const resp = await fetch(`get_employee_payslips.php?employee_id=${currentEmployeeId}`);
        const data = await resp.json();
        const payslips = Array.isArray(data?.payslips) ? data.payslips : [];

        const parseNotes = (entry) => {
            try {
                return JSON.parse(entry?.other_deductions_notes || '{}');
            } catch (e) {
                return {};
            }
        };

        const periodFiltered = payslips.filter((entry) => {
            if (!Number.isFinite(targetPeriodId) || targetPeriodId <= 0) {
                return true;
            }
            return parseInt(entry?.payroll_period_id, 10) === targetPeriodId;
        });

        const fullPeriodPayslip = periodFiltered.find((entry) => {
            const notes = parseNotes(entry);
            return !notes.cutoff_type;
        });

        const fallbackPayslip = periodFiltered[0];
        const resolved = parseInt((fullPeriodPayslip || fallbackPayslip || {}).id, 10);
        if (Number.isFinite(resolved) && resolved > 0) {
            return resolved;
        }
    } catch (err) {
        console.warn('Failed to resolve full-month payslip ID from API:', err);
    }

    const sourceCompId = parseInt(currentComp?.source_computation_id, 10);
    if (Number.isFinite(sourceCompId) && sourceCompId > 0) {
        return sourceCompId;
    }

    return null;
}

document.getElementById('btn_refresh_cards')?.addEventListener('click', function() {
    location.reload();
});

function openEmployeeDTRModal(employeeId, employeeName) {
    console.log('[DTR Modal] Opening for employee:', employeeId, employeeName);
    currentEmployeeId = employeeId;
    editModeEnabled = false;
    const modal = document.getElementById('employeeDTRModal');
    console.log('[DTR Modal] Modal element found:', !!modal);
    if (!modal) {
        console.error('[DTR Modal] Modal not found!');
        return;
    }
    document.getElementById('emp_modal_name').textContent = employeeName + ' - DTR';
    updateEditModeButton();
    loadAvailableMonths(employeeId);
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    console.log('[DTR Modal] Modal opened, display set to flex');
}


function loadAvailableMonths(employeeId) {
    const select  = document.getElementById('dtr_month_select');
    const content = document.getElementById('employee_dtr_content');
    const printBtn = document.getElementById('btn_print_dtr');
    const editBtn = document.getElementById('btn_edit_mode');
    const payslipBtn = document.getElementById('btn_generate_payslip');
    select.innerHTML = '<option value="">Loading...</option>';
    content.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i></div>';
    if (printBtn) printBtn.style.display = 'none';
    if (editBtn) editBtn.style.display = 'none';
    if (payslipBtn) payslipBtn.style.display = 'none';

    fetch(`get_employee_dtr_months.php?employee_id=${employeeId}`)
    .then(r => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
    })
    .then(data => {
        if (data.success && data.months && data.months.length > 0) {
            select.innerHTML = '<option value="">-- Select Period --</option>';
            data.months.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.period_id > 0 ? 'pid:' + m.period_id : 'range:' + m.first_date + ':' + m.last_date;
                opt.textContent = m.month_name + ' (' + m.record_count + ' records)';
                select.appendChild(opt);
            });
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-alt"></i><br>Select a period to view DTR records</div>';
        } else {
            select.innerHTML = '<option value="">No records found</option>';
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-times"></i><br>No DTR records found</div>';
        }
    })
    .catch(err => { 
        console.error('loadAvailableMonths error:', err);
        select.innerHTML = '<option value="">Error loading</option>';
        content.innerHTML = '<div class="no-cards-message"><i class="fas fa-exclamation-triangle"></i><br>Error loading DTR months: ' + err.message + '</div>';
    });
}

function loadEmployeeDTRByMonth() {
    const select    = document.getElementById('dtr_month_select');
    const content   = document.getElementById('employee_dtr_content');
    const printBtn = document.getElementById('btn_print_dtr');
    const editBtn = document.getElementById('btn_edit_mode');
    const saveBtn = document.getElementById('btn_save_dtr');
    const payslipBtn = document.getElementById('btn_generate_payslip');
    const rawVal    = select.value;
    if (!rawVal || !currentEmployeeId) {
        content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-alt"></i><br>Select a period</div>';
        if (printBtn) printBtn.style.display = 'none';
        if (editBtn) editBtn.style.display = 'none';
        if (saveBtn) saveBtn.style.display = 'none';
        if (payslipBtn) payslipBtn.style.display = 'none';
        return;
    }
    content.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i></div>';
    if (printBtn) printBtn.style.display = 'none';
    if (editBtn) editBtn.style.display = 'none';
    if (saveBtn) saveBtn.style.display = 'none';
    if (payslipBtn) payslipBtn.style.display = 'none';
    editModeEnabled = false;
    updateEditModeButton();

    let url = `get_employee_dtr_data.php?employee_id=${currentEmployeeId}`;
    if (rawVal.startsWith('pid:')) {
        currentPeriodId = parseInt(rawVal.replace('pid:', ''));
        url += `&period_id=${currentPeriodId}`;
    } else {
        currentPeriodId = null;
        const parts = rawVal.split(':');
        const monthKey = parts[1] ? parts[1].substring(0, 7) : '';
        url += `&month=${monthKey}`;
    }

    fetch(url)
    .then(r => {
        if (!r.ok) {
            throw new Error(`HTTP error! status: ${r.status}`);
        }
        return r.json();
    })
    .then(data => {
        console.log('DTR Data Response:', data); // Debug log
        
        if (data.success && data.records && data.records.length > 0) {
            const renderView = () => {
                currentPeriodId = data.period_id || currentPeriodId;
                currentDTRRecords = data.records;
                currentEmpInfo = data.employee_info;
                currentComp = data.payroll_computation;
                if (printBtn) printBtn.style.display = 'inline-flex';
                if (editBtn) editBtn.style.display = 'inline-flex';
                if (payslipBtn) payslipBtn.style.display = 'inline-flex';
                content.innerHTML = buildDTRView(data.records, data.employee_info, data.payroll_computation);
                const initialMeta = parsePayrollDayOverrideMeta(data.payroll_computation, getCurrentPayrollEditorValues());
                initializePayrollListOverrideState(data.records, initialMeta);

                // Add event listener to training amount input to update summary
                const trainingAmountInput = document.getElementById('view_training_amount');
                if (trainingAmountInput) {
                    trainingAmountInput.addEventListener('input', function() {
                        updatePayrollSummary();
                    });
                }
            };

            loadPayrollListShiftRules()
                .catch(() => null)
                .finally(renderView);
        } else {
            console.warn('No DTR records found:', data);
            currentDTRRecords = [];
            const message = data.message || 'No records';
            content.innerHTML = `<div class="no-cards-message"><i class="fas fa-file-alt"></i><br>${message}</div>`;
        }
    })
    .catch(err => { 
        console.error('Error loading DTR:', err); 
        content.innerHTML = `<div class="no-cards-message"><i class="fas fa-exclamation-circle"></i><br>Error loading<br><small style="color:#ef4444;">${err.message}</small></div>`; 
    });
}

/* ====== Helper Formatters ====== */
function peso(v) { return '₱' + Math.abs(v).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function fmtTime(t) { return t ? t : ''; }
function dash() { return '<span class="val-dash">-</span>'; }
// Format numbers with comma thousand separators (e.g., 13000 → "13,000.00")
function formatNum(val, decimals = 2) {
    const num = parseFloat(val);
    if (isNaN(num)) return val;
    return num.toLocaleString('en-US', {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
}

// Format time to 24-hour display (e.g., "8:00" or "17:00")
function fmtTime24(t) {
    if (!t || t.trim() === '') return '';
    // Handle HH:MM:SS format
    const parts = t.trim().split(':');
    if (parts.length >= 2) {
        const h = parseInt(parts[0]) || 0;
        const m = parseInt(parts[1]) || 0;
        return h + ':' + (m < 10 ? '0' + m : m);
    }
    return t;
}

// Format time input on typing (auto-add colon)
function formatTime24(input) {
    let val = input.value.replace(/[^0-9:]/g, '');
    if (val.length === 2 && !val.includes(':')) {
        val = val + ':';
    }
    if (val.length > 5) val = val.substring(0, 5);
    input.value = val;
}

// Format salary input with commas on blur
function formatSalaryInput(input) {
    const value = parseFloat(input.value.replace(/,/g, '')) || 0;
    if (value > 0) {
        input.value = value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}

// Format per day input with commas on blur
function formatPerDayInput(input) {
    const value = parseFloat(input.value.replace(/,/g, '')) || 0;
    if (value > 0) {
        input.value = value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}

// Format OT rate input on blur
function formatOTRateInput(input) {
    const raw = String(input.value || '').replace(/,/g, '').trim();
    if (raw === '') {
        input.value = '0.00';
        return;
    }

    const value = parseFloat(raw);
    if (!isFinite(value) || value <= 0) {
        input.value = '0.00';
        return;
    }

    input.value = formatNum(value);
}

/* ====== Rate Calculations ====== */
// Per day rate calculation - MUST match Generatepayroll.php exactly
// Per day rate = basic salary ÷ 26 (standard working days per month, excluding Sundays)
const WORKING_DAYS_IN_MONTH = 26;

function getRates(salary) {
    const perDay  = salary / WORKING_DAYS_IN_MONTH;  // Divided by 26 (monthly working days)
    const perHour = perDay / 8;  // 8 hours per day
    const perMin  = perHour / 60;  // Per minute rate
    const otRate  = perHour * 1.25;  // OT rate = hourly × 1.25
    return { perDay, perHour, perMin, otRate };
}

/* ====== Rate Update Functions (Like Generate Payroll) ====== */
function updateRatesFromSalary() {
    // Basic salary is now independent - does not calculate per day
    const salaryInput = document.getElementById('edit_basic_salary');
    if (!salaryInput) return;
    
    const salary = parseFloat(salaryInput.value.replace(/,/g, '')) || 0;
    
    // Update currentEmpInfo with new salary
    if (currentEmpInfo) currentEmpInfo.basic_monthly_salary = salary;
    
    console.log(`Basic Salary updated to: ₱${salary.toLocaleString('en-US', {minimumFractionDigits: 2})} (independent field)`);
}

function updateRatesFromDaily() {
    const perDayInput = document.getElementById('edit_per_day');
    const perHourSpan = document.getElementById('edit_per_hour');
    const perMinSpan = document.getElementById('edit_per_min');
    const otRateInput = document.getElementById('edit_ot_rate');
    
    if (!perDayInput) return;
    
    // Parse comma-formatted value
    const perDay = parseFloat(perDayInput.value.replace(/,/g, '')) || 0;
    const perHour = perDay / 8;
    const perMin = perHour / 60;
    const otRate = perHour * 1.25;
    
    // Update per hour, per minute, and OT rate (but NOT basic salary)
    if (perHourSpan) perHourSpan.textContent = formatNum(perHour);
    if (perMinSpan) perMinSpan.textContent = formatNum(perMin, 4);
    // Do not auto-fill OT rate from per-day. It should come from saved value or explicit user input.
    
    console.log(`Per/Day updated to: ₱${perDay.toLocaleString('en-US', {minimumFractionDigits: 2})} → Per/Hour: ₱${perHour.toFixed(2)}, Per/Min: ₱${perMin.toFixed(4)}`);
    
    // Only recalculate if in edit mode
    if (!editModeEnabled) return;

    if (payrollListCheckedDaysEditMode) {
        applyPayrollCheckedHeaderValuesToRows();
        return;
    }

    payrollListCheckedDaysDefaultSnapshot = getCurrentPayrollEditorValues();
    recalculateAllRows();
}

function recalculateAllRows() {
    // Only recalculate if in edit mode
    if (!editModeEnabled) {
        return;
    }

    if (payrollListCheckedDaysEditMode) {
        const values = getCurrentPayrollEditorValues();
        payrollListCheckedDaysOverrideSnapshot = values;
        getPayrollCheckedDayRows().forEach(idx => {
            const existing = payrollListDayOverrideValues[idx] || {};
            payrollListDayOverrideValues[idx] = {
                ...existing,
                perDay: values.perDay,
                otRate: values.otRate,
                lateStart: values.lateStart,
                endTime: values.endTime
            };
        });
    } else {
        payrollListCheckedDaysDefaultSnapshot = getCurrentPayrollEditorValues();
    }
    
    const table = document.getElementById('dtrEditTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr.dtr-data-row');
    rows.forEach((row) => {
        recalculateRow(row);
    });
    
    recalculateTotals();
}

function getEffectiveEditorValuesForRow(row) {
    const headerValues = getCurrentPayrollEditorValues();
    const idx = row?.dataset?.idx;
    const toggle = idx ? row.querySelector('.dtr-day-override-toggle') : null;
    const isDayOverride = !!(toggle && toggle.checked && !toggle.disabled);

    let effective = {
        perDay: headerValues.perDay,
        otRate: headerValues.otRate,
        lateStart: headerValues.lateStart,
        endTime: headerValues.endTime
    };

    // Always map unchecked rows to Shift 1 values and checked rows to Shift 2 values.
    const defaultSnapshot = payrollListCheckedDaysDefaultSnapshot || headerValues;
    const shift2Snapshot = payrollListCheckedDaysOverrideSnapshot || defaultSnapshot;
    effective = {
        ...effective,
        ...(isDayOverride ? shift2Snapshot : defaultSnapshot)
    };

    if (payrollListCheckedDaysEditMode) {
        if (isDayOverride) {
            effective = {
                ...effective,
                ...(payrollListCheckedDaysOverrideSnapshot || {})
            };
        } else {
            effective = {
                ...effective,
                ...(payrollListCheckedDaysDefaultSnapshot || {})
            };
        }
    } else if (!isDayOverride) {
        effective = {
            ...effective,
            ...(payrollListCheckedDaysDefaultSnapshot || {})
        };
    }

    // In Checked mode, checked rows should always follow the currently visible Checked header values.
    // This avoids stale stored row values overriding the active header values.
    if (isDayOverride && payrollListCheckedDaysEditMode) {
        effective = {
            ...effective,
            ...headerValues,
            ...(payrollListCheckedDaysOverrideSnapshot || {})
        };
    }

    if (isDayOverride && idx && payrollListDayOverrideValues[idx]) {
        effective = {
            ...effective,
            ...payrollListDayOverrideValues[idx]
        };

        // Re-apply current checked/header values to ensure they win over stale row snapshots.
        if (payrollListCheckedDaysEditMode) {
            effective = {
                ...effective,
                ...headerValues,
                ...(payrollListCheckedDaysOverrideSnapshot || {})
            };
        }
    }

    return effective;
}

/* ====== Time Parsing ====== */
function parseTimeToMinutes(timeStr) {
    if (!timeStr || timeStr.trim() === '') return null;
    const parts = timeStr.trim().split(':');
    if (parts.length < 2) return null;
    const h = parseInt(parts[0]) || 0;
    const m = parseInt(parts[1]) || 0;
    return h * 60 + m;
}

function minutesToTime(mins) {
    if (mins === null || isNaN(mins)) return '';
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
}

/* ====== Calculate Work Hours - WITH GRACE PERIOD (Matching Generatepayroll.php) ====== */
function calculateWorkHours(amIn, pmOut, scheduledStartOverride = null) {
    if (!amIn || !pmOut) return 0;
    
    // Get scheduled start time (late threshold) - default 8:00 AM (matching Generatepayroll.php)
    const lateStartInput = document.getElementById('edit_late_start');
    let scheduledStartMins = 8 * 60; // Default: 8:00 AM
    if (scheduledStartOverride) {
        const parsed = parseTimeToMinutes(scheduledStartOverride);
        if (parsed !== null) scheduledStartMins = parsed;
    } else if (lateStartInput && lateStartInput.value) {
        const parsed = parseTimeToMinutes(lateStartInput.value);
        if (parsed !== null) scheduledStartMins = parsed;
    }
    
    // Calculate grace period end time (scheduled start + 5 minutes)
    const gracePeriodMinutes = 5;
    const graceEndMins = scheduledStartMins + gracePeriodMinutes;
    
    // Get actual arrival time in minutes
    const actualArrivalMins = parseTimeToMinutes(amIn);
    
    // If arrived on/before grace end, count from scheduled start.
    // This intentionally caps early arrivals to TIME START for computations.
    let effectiveStartTime = amIn;
    if (actualArrivalMins !== null && actualArrivalMins <= graceEndMins) {
        // Use scheduled start time instead of actual arrival
        const schedHour = Math.floor(scheduledStartMins / 60);
        const schedMin = scheduledStartMins % 60;
        effectiveStartTime = `${String(schedHour).padStart(2, '0')}:${String(schedMin).padStart(2, '0')}`;
    }
    
    const [inHour, inMin] = effectiveStartTime.split(':').map(Number);
    const [outHour, outMin] = pmOut.split(':').map(Number);
    
    let hours = outHour - inHour;
    let minutes = outMin - inMin;
    
    if (minutes < 0) {
        hours -= 1;
        minutes += 60;
    }
    
    let totalHours = hours + (minutes / 60);
    
    // Subtract 1 hour for lunch break (12:00 PM - 1:00 PM)
    // Only deduct lunch if work period spans across lunch time
    const timeInMins = (inHour * 60) + inMin;
    const timeOutMins = (outHour * 60) + outMin;
    const lunchStart = 12 * 60; // 12:00 PM in minutes
    const lunchEnd = 13 * 60;   // 1:00 PM in minutes
    
    // If the work period includes lunch time, deduct 1 hour
    if (timeInMins < lunchEnd && timeOutMins > lunchStart) {
        totalHours = Math.max(0, totalHours - 1);
    }
    
    return totalHours;
}

/* ====== Calculate Late Minutes - WITH GRACE PERIOD (Matching Generatepayroll.php) ====== */
function calculateLateMins(amIn, lateStart = '8:00', scheduledStartOverride = null) {
    if (!amIn) return 0;
    const actualStart = parseTimeToMinutes(amIn);
    if (actualStart === null) return 0;

    // Read late start time from UI input or use parameter
    const lateStartInput = document.getElementById('edit_late_start');
    let scheduledStartMins = parseTimeToMinutes(lateStart);
    if (scheduledStartOverride) {
        const parsed = parseTimeToMinutes(scheduledStartOverride);
        if (parsed !== null) scheduledStartMins = parsed;
    } else if (lateStartInput && lateStartInput.value) {
        const parsed = parseTimeToMinutes(lateStartInput.value);
        if (parsed !== null) scheduledStartMins = parsed;
    }

    // Fixed 5-minute grace period AFTER the scheduled start
    const gracePeriodMinutes = 5;
    const graceEndMins = scheduledStartMins + gracePeriodMinutes;

    // If arrived within grace period (at or before grace end), not late
    if (actualStart <= graceEndMins) return 0;

    // Late minutes are measured from the scheduled start time (not grace end)
    return actualStart - scheduledStartMins;
}

/* ====== Calculate Undertime - Consistent with Generatepayroll.php ====== */
function calculateUndertime(pmOut, endTime = '17:00', scheduledEndOverride = null) {
    if (!pmOut) return 0;
    const pmOutMins = parseTimeToMinutes(pmOut);
    if (pmOutMins === null) return 0;
    
    // Read end threshold from UI input (default: 17:00)
    const endTimeInput = document.getElementById('edit_end_time');
    let schedEndMins = parseTimeToMinutes(endTime);
    if (scheduledEndOverride) {
        const parsed = parseTimeToMinutes(scheduledEndOverride);
        if (parsed !== null) schedEndMins = parsed;
    } else if (endTimeInput && endTimeInput.value) {
        const parsed = parseTimeToMinutes(endTimeInput.value);
        if (parsed !== null) schedEndMins = parsed;
    }
    
    // If left before scheduled end time, calculate undertime
    if (pmOutMins < schedEndMins) {
        let undertimeHours = (schedEndMins - pmOutMins) / 60;
        
        // Subtract 1 hour for lunch break (12:00 PM - 1:00 PM) if undertime period spans lunch
        const lunchStart = 12 * 60; // 12:00 PM in minutes
        const lunchEnd = 13 * 60;   // 1:00 PM in minutes
        
        // If employee left before 1:00 PM and scheduled end is after 12:00 PM, deduct lunch hour
        if (pmOutMins < lunchEnd && schedEndMins > lunchStart) {
            undertimeHours = Math.max(0, undertimeHours - 1);
        }
        
        return undertimeHours;
    }
    return 0;
}

/* ====== Build the full DTR view ====== */
function buildDTRView(records, empInfo, comp) {
    // Use basic_monthly_salary from saved payroll computation if available
    // This preserves salary modifications like Sunday pay added in DTR Calculator
    const salary = (comp && comp.basic_monthly_salary) 
        ? parseFloat(comp.basic_monthly_salary) 
        : parseFloat(empInfo.basic_monthly_salary) || 0;
    
    // Get employee classification (trainer or fixedrate)
    const classification = (empInfo.classification || '').toLowerCase().trim().replace(/\s+/g, '');
    const isTrainer = classification === 'trainer';
    
    // Use per_day_rate from saved payroll computation if available
    // This preserves the correct per/day rate even when salary includes Sunday work
    let perDay, perHour, perMin, otRate;
    let hasSavedOtRate = false;
    if (comp && comp.per_day_rate) {
        // Use saved rates from payroll computation
        perDay = parseFloat(comp.per_day_rate);
        perHour = parseFloat(comp.per_hour_rate) || (perDay / 8);
        perMin = parseFloat(comp.per_minute_rate) || (perHour / 60);
        
        // Try to get OT rate from other_deductions_notes JSON
        let savedOtRate = null;
        if (comp.other_deductions_notes) {
            try {
                const notes = JSON.parse(comp.other_deductions_notes);
                if (notes.ot_rate !== undefined && notes.ot_rate !== null && notes.ot_rate !== '') {
                    const parsedOtRate = parseFloat(notes.ot_rate);
                    if (!isNaN(parsedOtRate) && parsedOtRate > 0) {
                        savedOtRate = parsedOtRate;
                        hasSavedOtRate = true;
                    }
                }
            } catch (e) {
                console.warn('Failed to parse other_deductions_notes for ot_rate');
            }
        }
        otRate = hasSavedOtRate ? savedOtRate : 0;
        
        // Calculate per hour and per minute from per day if not saved
        perHour = parseFloat(comp.per_hour_rate) || (perDay / 8);
        perMin = parseFloat(comp.per_minute_rate) || (perHour / 60);
        
        const salarySource = (comp && comp.basic_monthly_salary) ? 'saved computation' : 'employee record';
        console.log(`Using saved rates from payroll computation: Basic Salary = ₱${salary.toLocaleString()} (from ${salarySource}), Per/Day = ₱${perDay.toFixed(2)}`);
    } else if (isTrainer) {
        // Trainer classification uses fixed daily rate of 500
        perDay = 500;
        perHour = perDay / 8;
        perMin = perHour / 60;
        otRate = 0;
        console.log(`Trainer classification: Using fixed daily rate ₱${perDay.toFixed(2)}`);
    } else {
        // NO automatic salary ÷ 26 calculation - per day must be manually entered in DTR Calculator
        perDay = 0;
        perHour = 0;
        perMin = 0;
        otRate = 0;
        console.warn('No saved rates found. Per day rate must be entered manually in DTR Calculator.');
    }
    
    // Get late_start and end_time from payroll computation if available (default 8:00 like Generatepayroll.php)
    const savedLateStart = comp?.late_start || '8:00';
    const savedEndTime = comp?.end_time || '17:00';

    const loadedMeta = parsePayrollDayOverrideMeta(comp, {
        perDay: String(perDay || ''),
        otRate: String(otRate || 0),
        lateStart: savedLateStart,
        endTime: savedEndTime
    });
    payrollListLoadedDayOverrideMeta = loadedMeta;

    const headerDefaultValues = normalizePayrollOverrideValues(loadedMeta.default_values, {
        perDay: String(perDay || ''),
        otRate: String(otRate || 0),
        lateStart: savedLateStart,
        endTime: savedEndTime
    });
    const checkedValues = normalizePayrollOverrideValues(loadedMeta.checked_values, headerDefaultValues);
    const checkedRowsSet = new Set((loadedMeta.checked_rows || []).map(v => String(v)));
    const rowOverrideByDate = (loadedMeta.row_values && typeof loadedMeta.row_values === 'object') ? loadedMeta.row_values : {};

    const headerPerDay = parseFloat(String(headerDefaultValues.perDay || '').replace(/,/g, '')) || 0;
    const headerOtRate = parseFloat(String(headerDefaultValues.otRate || '').replace(/,/g, '')) || 0;
    if (headerPerDay > 0) {
        perDay = headerPerDay;
        perHour = perDay / 8;
        perMin = perHour / 60;
    }
    otRate = headerOtRate;

    // Totals accumulators
    let totWorkHrs = 0, totLateMins = 0, totUtHrs = 0, totOtHrs = 0;
    let totAbsentDays = 0, totTrainingDays = 0, totAbsentDed = 0, totLateDed = 0, totUtDed = 0, totHalfDed = 0;
    let totOtPay = 0, totGovt = 0, totNetSalary = 0;
    let daysWorked = 0;

    const rowBenefitBreakdown = getPayrollBenefitBreakdownFromRecords(records);

    // ====== TB5 Style DTR Calculator Header ======
    let html = `
    <div class="dtr-calc-container">
        <div class="dtr-header-tb5">
            <div class="tb5-title">DTR CALCULATOR</div>
        </div>
        
        <!-- TB5 Employee Info Row -->
        <div class="tb5-info-row">
            <div class="tb5-employee-info">
                <span class="tb5-label">EMPLOYEE NAME:</span>
                <span class="tb5-value">${empInfo.full_name}</span>
            </div>
            <div class="tb5-company">THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.</div>
        </div>
        
        <!-- TB5 Rate Calculation Row (Editable like Generate Payroll) -->
        <div class="tb5-rate-row">
            <div class="tb5-rate-item rate-input-item" ${isTrainer ? 'style="display:none"' : ''}>
                <span class="tb5-rate-label">BASIC SALARY</span>
                <div class="rate-input-wrapper rate-green">
                    <span class="peso-sign">₱</span>
                    <input type="text" id="edit_basic_salary" name="basic_salary" 
                           class="rate-input" value="${salary.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" 
                           onblur="formatSalaryInput(this)" ${!editModeEnabled ? 'readonly' : ''}>
                </div>
            </div>
            <div class="tb5-rate-item rate-input-item">
                <span class="tb5-rate-label">PER/DAY</span>
                <div class="rate-input-wrapper rate-red">
                    <span class="peso-sign">₱</span>
                    <input type="text" id="edit_per_day" name="per_day" 
                           class="rate-input" value="${perDay.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" 
                           oninput="updateRatesFromDaily()" onblur="formatPerDayInput(this)" readonly>
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
                    <input type="text" id="edit_ot_rate" name="ot_rate" 
                              class="rate-input-editable" value="${formatNum(otRate)}" 
                           style="width: 70px; border: none; background: transparent; font-weight: 700; font-size: 12px; text-align: center; color: #333;"
                           oninput="recalculateAllRows()" onblur="formatOTRateInput(this)" readonly>
                </div>  
            </div>
            <div class="tb5-rate-item rate-input-item">
                <span class="tb5-rate-label">TIME START</span>
                <input type="text" id="edit_late_start" name="late_start" value="${headerDefaultValues.lateStart}" 
                       class="time-input" maxlength="5" placeholder="8:00"
                       oninput="formatTime24(this)" onchange="recalculateAllRows()" readonly>
            </div>
            <div class="tb5-rate-item rate-input-item">
                <span class="tb5-rate-label">END TIME</span>
                <input type="text" id="edit_end_time" name="end_time" value="${headerDefaultValues.endTime}" 
                       class="time-input" maxlength="5" placeholder="17:00"
                       oninput="formatTime24(this)" onchange="recalculateAllRows()" readonly>
            </div>
            <div class="tb5-rate-item tb5-switch-item">
                <span class="tb5-rate-label">SHIFT EDIT</span>
                <div class="segment-switch-wrap" title="Shift values are managed in Settings > Rules">
                    <div class="segment-switch-track" role="group" aria-label="Toggle shift values">
                        <button type="button" id="pl_checked_days_default_btn" class="segment-option segment-btn active" onclick="setPayrollCheckedDaysMode(false)">Shift 1</button>
                        <button type="button" id="pl_checked_days_checked_btn" class="segment-option segment-btn" onclick="setPayrollCheckedDaysMode(true)">Shift 2</button>
                    </div>
                </div>
            </div>
        </div>
    </div>`;

    // ====== TB5 Style DTR Table - Exact Match to Generatepayroll.php ======
    html += `
    <div class="dtr-table-wrapper">
    <table class="dtr-table" id="dtrEditTable">
        <thead>
            <tr>
                <th rowspan="3" class="th-single th-day-override">Shift</th>
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
                <th rowspan="3" class="th-action">ACTIONS</th>
            </tr>
            <tr>
                <th class="th-sub th-auto-calc">LATE/min</th>
                <th class="th-sub th-auto-calc">UNDERTIME</th>
                <th class="th-sub th-auto-calc">OT</th>
            </tr>
        </thead>
        <tbody>`;

    records.forEach((rec, idx) => {
        const rowDate = String(rec.dtr_date || '');
        const isDayOverrideChecked = checkedRowsSet.has(rowDate);
        const rowOverrideValues = rowOverrideByDate[rowDate] || null;
        const effectiveRowValues = isDayOverrideChecked
            ? normalizePayrollOverrideValues(rowOverrideValues, checkedValues)
            : normalizePayrollOverrideValues(null, headerDefaultValues);
        const rowPerDay = parseFloat(String(effectiveRowValues.perDay || '').replace(/,/g, '')) || perDay;
        const rowOtRate = parseFloat(String(effectiveRowValues.otRate || '').replace(/,/g, '')) || otRate;
        const rowPerHour = rowPerDay / 8;
        const rowLateStart = effectiveRowValues.lateStart || headerDefaultValues.lateStart;
        const rowEndTime = effectiveRowValues.endTime || headerDefaultValues.endTime;

        const isAbsent = rec.is_absent == 1;
        const isTraining = rec.is_training == 1;
        const isHalf   = rec.is_halfday == 1;
        // Training and Absent days have zero calculations
        const amInRec = fmtTime24(rec.am_time_in);
        const pmOutRec = fmtTime24(rec.pm_time_out);
        const otOutRec = fmtTime24(rec.ot_time_out);
        const workHrs  = (isAbsent || isTraining) ? 0 : calculateWorkHours(amInRec, pmOutRec, rowLateStart);
        const lateMins = (isAbsent || isTraining) ? 0 : calculateLateMins(amInRec, rowLateStart, rowLateStart);
        const equivalentLateMins = (isAbsent || isTraining) ? 0 : getPayrollListLateEquivalentMinutes(lateMins);
        // For halfday, undertime should be 0 (halfday deduction already covers not working full day)
        const utHrs    = (isAbsent || isTraining || isHalf) ? 0 : calculateUndertime(pmOutRec, rowEndTime, rowEndTime);
        const otOutMins = parseTimeToMinutes(otOutRec);
        const rowEndMins = parseTimeToMinutes(rowEndTime);
        const otHrs = (isAbsent || isTraining) ? 0 : ((otOutMins && rowEndMins && otOutMins > rowEndMins) ? (otOutMins - rowEndMins) / 60 : 0);
        const govtDb   = parseFloat(rec.govt_deduct) || 0;

        // Compute row deductions (Training and Absent both have zero calculations)
        const absentDed = isAbsent ? rowPerDay : 0; // Training has NO absent deduction
        const lateDed   = (isAbsent || isTraining) ? 0 : (equivalentLateMins / 60) * rowPerHour;
        // For halfday, no undertime deduction (halfday covers it)
        const utDed     = (isAbsent || isTraining || isHalf) ? 0 : utHrs * rowPerHour;
        const halfDed   = isHalf ? (rowPerDay / 2) : 0;
        const otPayRow  = (isAbsent || isTraining) ? 0 : otHrs * rowOtRate;

        // ALWAYS recalculate net salary based on current row state (don't use stale database value)
        let rowNet;
        if (isAbsent) {
            rowNet = 0;  // Absent = 0 net salary
        } else if (isTraining) {
            // Training days have no salary
            rowNet = 0;
        } else if (isHalf) {
            // Halfday: pay for half day minus late deduction (OT shown separately in summary)
            // No undertime deduction because halfday already accounts for not working full day
            rowNet = (rowPerDay / 2) - lateDed;
        } else if (workHrs === 0 && otHrs === 0) {
            // No work hours and no OT = no pay (likely incomplete/invalid time entry)
            rowNet = 0;
        } else {
            // Calculate row net: Start with full day rate, subtract deductions (OT shown separately)
            // Pay full day minus late/undertime deductions (not workHrs * perHour to avoid double-counting)
            rowNet = rowPerDay - lateDed - utDed;
        }

        // Count as "worked" only if the row has actual time entries (not empty rows)
        if (!isAbsent && !isTraining && (rec.am_time_in || rec.pm_time_out || workHrs > 0)) daysWorked++;
        totAbsentDays += isAbsent ? 1 : 0;
        totTrainingDays += isTraining ? 1 : 0;
        totWorkHrs += workHrs;
        totLateMins += equivalentLateMins;
        totUtHrs += utHrs;
        totOtHrs += otHrs;
        totAbsentDed += absentDed;
        totLateDed += lateDed;
        totUtDed += utDed;
        totHalfDed += halfDed;
        totOtPay += otPayRow;
        totGovt += govtDb;
        totNetSalary += rowNet;

        // Format date like TB5 (day and month with day of week)
        let dateDisplay = rec.dtr_date;
        if (rec.dtr_date) {
            const dateParts = rec.dtr_date.split('-');
            if (dateParts.length === 3) {
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const day = parseInt(dateParts[2]);
                const month = months[parseInt(dateParts[1]) - 1] || '';
                // Create date object to get day of week
                const dateObj = new Date(rec.dtr_date);
                const dayOfWeek = daysOfWeek[dateObj.getDay()];
                dateDisplay = `<div class="date-day">${day}</div><div class="date-month">${month}</div><div class="date-weekday">${dayOfWeek}</div>`;
            }
        }

        // Calculate new fields
        const absentDays = isAbsent ? 1 : 0;
        
        // Automatic Calculations (same as main calculations for display)
        const autoLate = (isAbsent || isTraining) ? 0 : (equivalentLateMins / 60) * rowPerHour;
        const autoUt = (isAbsent || isTraining) ? 0 : utHrs * rowPerHour;
        const autoOt = (isAbsent || isTraining) ? 0 : otHrs * rowOtRate;

        html += `<tr class="dtr-data-row ${(isAbsent || isTraining) ? 'absent-row' : ''}" data-record-id="${rec.id}" data-idx="${idx}" data-row="${idx+1}" data-dtr-date="${rowDate}">`;
        html += `<td class="centered day-override-cell"><input type="checkbox" name="day_override_${idx}" class="dtr-day-override-toggle" data-idx="${idx}" onchange="handleDayOverrideToggleEdit(this)" ${isDayOverrideChecked ? 'checked' : ''} ${(!editModeEnabled || isAbsent || isTraining) ? 'disabled' : ''}></td>`;
        html += `<td class="date-cell"><div class="date-display">${dateDisplay}</div></td>`;

        // Simplified columns - only AM IN and PM OUT like Generatepayroll.php
        // Training and Absent days have no time entries
        const amTimeValue = (isAbsent || isTraining) ? '' : amInRec;
        const pmTimeValue = (isAbsent || isTraining) ? '' : pmOutRec;
        const otTimeValue = (isAbsent || isTraining) ? '' : otOutRec;
        
        html += `<td class="td-am"><input type="text" name="am_in_${idx}" class="dtr-input time24 input-am" value="${amTimeValue}" maxlength="5" onchange="recalculateRow(this.closest('tr'))" oninput="formatTime24(this); recalculateRow(this.closest('tr'))" placeholder="8:00" ${!editModeEnabled ? 'readonly' : ''}></td>`;
        html += `<td class="td-pm"><input type="text" name="pm_out_${idx}" class="dtr-input time24 input-pm" value="${pmTimeValue}" maxlength="5" onchange="recalculateRow(this.closest('tr'))" oninput="formatTime24(this); recalculateRow(this.closest('tr'))" placeholder="17:00" ${!editModeEnabled ? 'readonly' : ''}></td>`;
        html += `<td class="td-absent centered"><input type="checkbox" name="absent_${idx}" class="dtr-absent" ${isAbsent ? 'checked' : ''} onchange="recalculateRow(this.closest('tr'))" ${!editModeEnabled ? 'disabled' : ''}></td>`;
        html += `<td class="td-training centered"><input type="checkbox" name="training_${idx}" class="dtr-training" ${rec.is_training ? 'checked' : ''} onchange="recalculateRow(this.closest('tr'))" ${!editModeEnabled ? 'disabled' : ''}></td>`;
        html += `<td class="td-ot"><input type="text" name="ot_out_${idx}" class="dtr-input time24 input-ot" value="${otTimeValue}" maxlength="5" onchange="recalculateRow(this.closest('tr'))" oninput="formatTime24(this); recalculateRow(this.closest('tr'))" placeholder="" ${!editModeEnabled ? 'readonly' : ''}></td>`;

        // Calculated fields - with comma formatting for thousands
        html += `<td class="td-calc calc-highlight"><input type="text" name="work_hrs_${idx}" class="dtr-calc-input" value="${formatNum(workHrs)}" readonly></td>`;
        html += `<td class="td-calc calc-highlight"><input type="text" name="late_mins_${idx}" class="dtr-calc-input" value="${formatNum(equivalentLateMins, 2)}" readonly><input type="hidden" name="actual_late_mins_${idx}" value="${formatNum(lateMins, 2)}"></td>`;
        html += `<td class="td-calc calc-highlight"><input type="text" name="ut_hrs_${idx}" class="dtr-calc-input" value="${formatNum(utHrs)}" readonly></td>`;
        html += `<td class="td-calc calc-highlight"><input type="text" name="ot_hrs_${idx}" class="dtr-calc-input" value="${formatNum(otHrs)}" readonly></td>`;
        html += `<td class="td-single"><input type="text" name="absent_days_${idx}" class="dtr-calc-input" value="${absentDays}" readonly></td>`;
        html += `<td class="td-deduct deduct-highlight"><input type="text" name="late_ded_${idx}" class="dtr-deduct-input" value="${formatNum(lateDed)}" readonly></td>`;
        html += `<td class="td-deduct deduct-highlight"><input type="text" name="ut_ded_${idx}" class="dtr-deduct-input" value="${formatNum(utDed)}" readonly></td>`;
        html += `<td class="td-deduct deduct-highlight"><input type="text" name="half_ded_${idx}" class="dtr-deduct-input" value="${formatNum(halfDed)}" readonly></td>`;
        html += `<td class="td-pay pay-highlight"><input type="text" name="ot_pay_${idx}" class="dtr-pay-input" value="${formatNum(otPayRow)}" readonly></td>`;
        
        // Automatic Calculations columns
        html += `<td class="td-auto dtr-auto-calc"><input type="text" name="auto_late_${idx}" class="dtr-calc-input" value="${formatNum(autoLate)}" readonly></td>`;
        html += `<td class="td-auto dtr-auto-calc"><input type="text" name="auto_ut_${idx}" class="dtr-calc-input" value="${formatNum(autoUt)}" readonly></td>`;
        html += `<td class="td-auto dtr-auto-calc"><input type="text" name="auto_ot_${idx}" class="dtr-calc-input" value="${formatNum(autoOt)}" readonly></td>`;
        
        html += `<td class="td-manual dtr-manual"><input type="number" name="govt_${idx}" class="dtr-govt-input" value="${govtDb > 0 ? govtDb.toFixed(2) : ''}" step="0.01" placeholder="0.00" oninput="recalculateTotals()" onchange="recalculateTotals()" ${editModeEnabled ? '' : 'readonly'}></td>`;
        html += `<td class="td-salary dtr-auto-salary"><input type="text" name="net_${idx}" class="dtr-net-input" value="${formatNum(rowNet)}" readonly></td>`;
        html += `<td class="td-remarks"><input type="text" name="remarks_${idx}" class="dtr-remarks-input" value="${rec.remarks || ''}" placeholder="Remarks" oninput="updatePayrollSummary()" onchange="updatePayrollSummary()" ${!editModeEnabled ? 'readonly' : ''}></td>`;
        html += `<td class="td-action"><button type="button" class="btn-delete-row" onclick="deleteRow(${idx})" ${!editModeEnabled ? 'disabled' : ''}><i class="fas fa-trash"></i></button></td>`;
        html += `</tr>`;
    });

    // Totals footer with TB5 styling - with comma formatting
    html += `</tbody><tfoot><tr class="totals-row" id="totalsRow">`;
    html += `<td class="totals-label" colspan="7">TOTALS:</td>`;
    html += `<td class="tot-work-hrs calc-highlight">${formatNum(totWorkHrs)}</td>`;
    html += `<td class="tot-late calc-highlight">${formatNum(totLateMins, 2)}</td>`;
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
    html += `<td></td>`;
    html += `</tr></tfoot></table></div>`;

    // Get training amount from payroll computation if saved
    const savedTrainingAmount = comp?.trainings_cost || comp?.training_amount || 0;
    const savedTrainingRemarks = comp?.training_remarks || '';

    // Training Payment section - Simple design
    html += `
    <div class="trainee-payment-card" id="trainee_payment_card_view" style="margin-top: 20px;">
        <div class="trainee-card-header">
            <h3>
                <i class="fas fa-users"></i>
                Training Payment
            </h3>
            <span class="trainee-badge">Additional Earnings</span>
        </div>
        <div class="trainee-card-body">
            <div class="trainee-form-row" style="gap: 15px;">
                <div class="trainee-input-group">
                    <label>Amount</label>
                    <div class="input-with-prefix">
                        <span class="prefix">₱</span>
                        <input type="text" 
                               id="view_training_amount" 
                               class="trainee-input trainee-amount-input" 
                               value="${parseFloat(savedTrainingAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" 
                               ${!editModeEnabled ? 'readonly' : ''}
                               oninput="updatePayrollSummary()"
                               placeholder="0.00"
                               style="padding: 8px 10px 8px 28px; border: 2px solid #cbd5e0;">
                    </div>
                </div>
                
                <div class="trainee-input-group" style="flex: 2;">
                    <label>Remarks</label>
                    <input type="text" 
                           id="view_training_remarks" 
                           class="trainee-input" 
                           value="${savedTrainingRemarks}" 
                           ${!editModeEnabled ? 'readonly' : ''}
                           placeholder="Enter remarks or notes"
                           style="padding: 8px 10px; border: 2px solid #cbd5e0;">
                </div>
            </div>
        </div>
    </div>`;

    // Payroll Summary below Training Payment - Uses totNetSalary from DTR column total
    
    // Get government deductions from payroll computation
    const hasClassifiedRows = rowBenefitBreakdown.hasClassifiedRows;
    const sssContrib = hasClassifiedRows ? rowBenefitBreakdown.sss : (parseFloat(comp?.sss_contribution) || 0);
    const philHealthContrib = hasClassifiedRows ? rowBenefitBreakdown.philhealth : (parseFloat(comp?.philhealth_contribution) || 0);
    const pagibigContrib = hasClassifiedRows ? rowBenefitBreakdown.pagibig : (parseFloat(comp?.pagibig_contribution) || 0);
    const otherDeductions = hasClassifiedRows ? rowBenefitBreakdown.other : getPayslipOthersCa(comp);
    const otherDeductionLabel = getOtherDeductionLabel(rowBenefitBreakdown.otherLabels);
    const totalGovtDeductions = sssContrib + philHealthContrib + pagibigContrib;
    
    // Show all deductions in the Total Deduction display.
    // Attendance deductions are already reflected in per-row net values,
    // but are still shown here for record/visibility.
    const totalAllDeductions = totLateDed + totUtDed + totHalfDed + totalGovtDeductions + otherDeductions;
    
    // Final Net Pay = Net Salary + Training + OT - Govt Deductions - Others/C.A.
    // Note: totNetSalary already has late/undertime/halfday deducted per-row, so do NOT subtract them again
    const finalNetPay = totNetSalary + parseFloat(savedTrainingAmount) + totOtPay - totalGovtDeductions - otherDeductions;

    html += `
    <div class="trainee-payment-card" id="payroll_summary_view" style="margin-top: 20px;">
        <div class="trainee-card-header">
            <h3>
                <i class="fas fa-file-invoice-dollar"></i>
                Payroll Summary
            </h3>
            <span class="trainee-badge">Salary Breakdown</span>
        </div>
        <div class="trainee-card-body">
            <div class="summary-two-column">
                <div class="summary-column summary-column-left">
                <div class="summary-section-header">Earnings Summary</div>
                <div class="summary-row">
                    <span class="summary-label">Days Worked:</span>
                    <span class="summary-value" id="summary_days_worked">${daysWorked} days</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Absent:</span>
                    <span class="summary-value" id="summary_absent_deduct">${totAbsentDays} days</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Training:</span>
                    <span class="summary-value" id="summary_training_days">${totTrainingDays} days</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">OT Pay:</span>
                    <span class="summary-value positive" id="summary_ot_pay">+${peso(totOtPay)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Net Salary:</span>
                    <span class="summary-value" id="summary_net_salary">${peso(totNetSalary)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Training Payment:</span>
                    <span class="summary-value positive" id="summary_training_payment">+${peso(savedTrainingAmount)}</span>
                </div>
            </div>
            <div class="summary-column summary-column-right">
                <div class="summary-section-header">Other Deductions</div>
                <div class="summary-row">
                    <span class="summary-label">Late Deduct:</span>
                    <span class="summary-value negative" id="summary_late_deduct">-${peso(totLateDed)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Undertime Deduct:</span>
                    <span class="summary-value negative" id="summary_ut_deduct">-${peso(totUtDed)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Halfday Deduct:</span>
                    <span class="summary-value negative" id="summary_half_deduct">-${peso(totHalfDed)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label" id="summary_other_deductions_label">${escapeHtmlInline(otherDeductionLabel)}</span>
                    <span class="summary-value negative" id="summary_other_deductions">-${peso(otherDeductions)}</span>
                </div>
                <div class="summary-section-header">Government Deductions</div>
                <div class="summary-row">
                    <span class="summary-label">SSS:</span>
                    <span class="summary-value negative" id="summary_sss">-${peso(sssContrib)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">PhilHealth:</span>
                    <span class="summary-value negative" id="summary_philhealth">-${peso(philHealthContrib)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Pag-IBIG:</span>
                    <span class="summary-value negative" id="summary_pagibig">-${peso(pagibigContrib)}</span>
                </div>
                <div class="summary-row summary-subtotal">
                    <span class="summary-label">Total Deduction:</span>
                    <span class="summary-value negative" id="summary_total_govt">-${peso(totalAllDeductions)}</span>
                </div>
            </div>
            <div class="summary-row-full final">
                <span class="summary-label">FINAL NET PAY:</span>
                <span class="summary-value final-value" id="summary_final_net_pay">${peso(finalNetPay)}</span>
            </div>
        </div>
    </div>`;

    return html;
}

/* ====== Recalculate Row on Edit - Simplified for AM IN / PM OUT ====== */
function recalculateRow(row) {
    // Only recalculate if in edit mode
    if (!editModeEnabled) {
        return;
    }
    
    if (!row) return;
    
    // Resolve effective values for this row (default/checked/day-override aware)
    const effective = getEffectiveEditorValuesForRow(row);
    const perDay = parseFloat(String(effective.perDay || '').replace(/,/g, '')) || 0;

    // Allow time-based calculations even when per-day rate is not set (0).
    // Monetary deductions will be zero when perDay is 0, but late/undertime/OT
    // and hours should still be computed for live preview.
    if (perDay === 0) {
        // don't early-return; continue with perHour/perMin = 0
    }

    const perHour = perDay / 8;
    const perMin = perHour / 60;
    const idx = row.dataset.idx;
    
    // Get simplified time inputs (only AM IN and PM OUT)
    const amIn = row.querySelector(`input[name="am_in_${idx}"]`)?.value || '';
    const pmOut = row.querySelector(`input[name="pm_out_${idx}"]`)?.value || '';
    const otOut = row.querySelector(`input[name="ot_out_${idx}"]`)?.value || '';
    const isAbsent = row.querySelector(`input[name="absent_${idx}"]`)?.checked || false;
    const isTraining = row.querySelector(`input[name="training_${idx}"]`)?.checked || false;
    
    // If absent OR training is checked, clear all time inputs
    if (isAbsent || isTraining) {
        const amInInput = row.querySelector(`input[name="am_in_${idx}"]`);
        const pmOutInput = row.querySelector(`input[name="pm_out_${idx}"]`);
        const otOutInput = row.querySelector(`input[name="ot_out_${idx}"]`);
        
        if (amInInput) amInInput.value = '';
        if (pmOutInput) pmOutInput.value = '';
        if (otOutInput) otOutInput.value = '';
    }
    
    // Use effective row thresholds
    const lateStart = effective.lateStart || '8:00';
    const endTime = effective.endTime || '17:00';
    
    // OT rate follows effective shift values loaded from Settings rules.
    const finalOtRate = parseFloat(String(effective.otRate || '').replace(/,/g, '')) || 0;
    
    // Disable day override on rows blocked by absent/training; otherwise restore edit-mode state.
    const dayOverrideToggle = row.querySelector('.dtr-day-override-toggle');
    if (dayOverrideToggle) {
        if (isAbsent || isTraining) {
            dayOverrideToggle.checked = false;
            dayOverrideToggle.disabled = true;
            delete payrollListDayOverrideValues[idx];
        } else {
            dayOverrideToggle.disabled = !editModeEnabled;
        }
    }

    // Calculate values - Zero out if absent OR training
    const workHrs = (isAbsent || isTraining) ? 0 : calculateWorkHours(amIn, pmOut, lateStart);
    const lateMins = (isAbsent || isTraining) ? 0 : calculateLateMins(amIn, lateStart, lateStart);
    const equivalentLateMins = (isAbsent || isTraining) ? 0 : getPayrollListLateEquivalentMinutes(lateMins);
    
    // Check if halfday first (leaving around 12pm)
    let isHalfday = false;
    let halfDed = 0;
    if (!isAbsent && !isTraining && pmOut) {
        const pmOutMins = parseTimeToMinutes(pmOut);
        const noonMins = 12 * 60; // 12:00 PM
        
        // If work ends at noon (11:45 - 12:15), consider it halfday
        if (pmOutMins >= (noonMins - 15) && pmOutMins <= (noonMins + 15)) {
            isHalfday = true;
            halfDed = perDay / 2;
        }
    }
    
    // Calculate undertime ONLY if NOT halfday
    // If halfday, no undertime is calculated (halfday deduction covers it)
    const utHrs = (isAbsent || isTraining || isHalfday) ? 0 : calculateUndertime(pmOut, endTime, endTime);
    
    // OT calculation (if ot_out is after end time)
    const otOutMins = parseTimeToMinutes(otOut);
    const endMins = parseTimeToMinutes(endTime);
    const otHrs = (isAbsent || isTraining) ? 0 : ((otOutMins && endMins && otOutMins > endMins) ? (otOutMins - endMins) / 60 : 0);
    
    // Deductions and payments - Training and Absent both have zero salary
    const absentDays = isAbsent ? 1 : 0; // Training does NOT count as absent day
    const absentDed = isAbsent ? perDay : 0; // Training has NO absent deduction
    const lateDed = (isAbsent || isTraining) ? 0 : equivalentLateMins * perMin;  // TB5: LATE deduction follows configured late equivalency rule
    const utDed = (isAbsent || isTraining || isHalfday) ? 0 : utHrs * perHour;
    
    const otPay = (isAbsent || isTraining) ? 0 : otHrs * finalOtRate;
    
    // Calculate total deductions
    const totalDeductions = absentDed + lateDed + utDed + halfDed;
    
    // Automatic calculations (same values for display)
    const autoLate = lateDed;
    const autoUt = utDed;
    const autoOt = otPay;
    
    // Get govt deduction from input field (NOT deducted per row, only at summary level)
    const govtVal = parseFloat(row.querySelector(`input[name="govt_${idx}"]`)?.value) || 0;
    
    let rowNet;
    if (isAbsent) {
        rowNet = 0;  // Absent = 0 net salary
    } else if (isTraining) {
        // Training days have no salary
        rowNet = 0;
    } else if (isHalfday) {
        // Halfday: pay for half day minus late deduction plus OT
        // No undertime deduction because halfday already accounts for not working full day
        rowNet = (perDay / 2) - lateDed + otPay;
    } else if (workHrs === 0 && otHrs === 0) {
        // No work hours and no OT = no pay (likely incomplete/invalid time entry)
        rowNet = 0;
    } else {
        // Calculate row net: Start with full day rate, subtract deductions, add OT
        // Pay full day minus late/undertime deductions (not workHrs * perHour to avoid double-counting)
        rowNet = perDay - lateDed - utDed + otPay;
    }
    
    // Update all calculation input values
    const workHrsInput = row.querySelector(`input[name="work_hrs_${idx}"]`);
    const lateMinsInput = row.querySelector(`input[name="late_mins_${idx}"]`);
    const actualLateMinsInput = row.querySelector(`input[name="actual_late_mins_${idx}"]`);
    const utHrsInput = row.querySelector(`input[name="ut_hrs_${idx}"]`);
    const otHrsInput = row.querySelector(`input[name="ot_hrs_${idx}"]`);
    const absentDaysInput = row.querySelector(`input[name="absent_days_${idx}"]`);
    const absentDedInput = row.querySelector(`input[name="absent_ded_${idx}"]`);
    const lateDedInput = row.querySelector(`input[name="late_ded_${idx}"]`);
    const utDedInput = row.querySelector(`input[name="ut_ded_${idx}"]`);
    const halfDedInput = row.querySelector(`input[name="half_ded_${idx}"]`);
    const otPayInput = row.querySelector(`input[name="ot_pay_${idx}"]`);
    const autoLateInput = row.querySelector(`input[name="auto_late_${idx}"]`);
    const autoUtInput = row.querySelector(`input[name="auto_ut_${idx}"]`);
    const autoOtInput = row.querySelector(`input[name="auto_ot_${idx}"]`);
    const netInput = row.querySelector(`input[name="net_${idx}"]`);
    
    if (workHrsInput) workHrsInput.value = formatNum(workHrs);
    if (lateMinsInput) lateMinsInput.value = formatNum(equivalentLateMins, 2);
    if (actualLateMinsInput) actualLateMinsInput.value = formatNum(lateMins, 2);
    if (utHrsInput) utHrsInput.value = formatNum(utHrs);
    if (otHrsInput) otHrsInput.value = formatNum(otHrs);
    if (absentDaysInput) absentDaysInput.value = absentDays;
    if (absentDedInput) absentDedInput.value = formatNum(absentDed);
    if (lateDedInput) lateDedInput.value = formatNum(lateDed);
    if (utDedInput) utDedInput.value = formatNum(utDed);
    if (halfDedInput) halfDedInput.value = formatNum(halfDed);
    if (otPayInput) otPayInput.value = formatNum(otPay);
    if (autoLateInput) autoLateInput.value = formatNum(autoLate);
    if (autoUtInput) autoUtInput.value = formatNum(autoUt);
    if (autoOtInput) autoOtInput.value = formatNum(autoOt);
    if (netInput) netInput.value = formatNum(rowNet);
    
    // Toggle absent row style for both absent and training rows
    row.classList.toggle('absent-row', isAbsent || isTraining);
    
    // Recalculate totals
    recalculateTotals();
}

/* ====== Recalculate All Totals - Updated for new columns ====== */
function recalculateTotals() {
    const table = document.getElementById('dtrEditTable');
    if (!table) return;
    
    // Get per day rate directly from the input field (not calculated from basic salary)
    const perDayInput = document.getElementById('edit_per_day');
    const perDay = perDayInput ? parseFloat(perDayInput.value.replace(/,/g, '')) || 0 : 0;
    const perHour = perDay / 8;
    
    let totWorkHrs = 0, totLateMins = 0, totUtHrs = 0, totOtHrs = 0;
    let totAbsentDays = 0, totAbsentDed = 0, totLateDed = 0, totUtDed = 0, totHalfDed = 0;
    let totOtPay = 0, totGovt = 0, totNet = 0;
    let totAutoLate = 0, totAutoUt = 0, totAutoOt = 0;
    
    table.querySelectorAll('tbody tr.dtr-data-row').forEach((row, idx) => {
        // Strip commas before parsing since values now have thousand separators
        totWorkHrs += parseFloat((row.querySelector(`input[name="work_hrs_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totLateMins += parseFloat((row.querySelector(`input[name="late_mins_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totUtHrs += parseFloat((row.querySelector(`input[name="ut_hrs_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totOtHrs += parseFloat((row.querySelector(`input[name="ot_hrs_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totAbsentDays += parseInt((row.querySelector(`input[name="absent_days_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        // Calculate absent deduction from absent days (removed from table display)
        // totAbsentDed will be calculated after loop: totAbsentDays * perDay
        totLateDed += parseFloat((row.querySelector(`input[name="late_ded_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totUtDed += parseFloat((row.querySelector(`input[name="ut_ded_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totHalfDed += parseFloat((row.querySelector(`input[name="half_ded_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totOtPay += parseFloat((row.querySelector(`input[name="ot_pay_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totAutoLate += parseFloat((row.querySelector(`input[name="auto_late_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totAutoUt += parseFloat((row.querySelector(`input[name="auto_ut_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totAutoOt += parseFloat((row.querySelector(`input[name="auto_ot_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totGovt += parseFloat((row.querySelector(`input[name="govt_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        totNet += parseFloat((row.querySelector(`input[name="net_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
    });
    
    // Calculate absent deduction (not displayed in table but used in summary)
    totAbsentDed = totAbsentDays * perDay;
    
    // Update totals row with comma formatting (MINUS OT column removed)
    const totalsRow = document.getElementById('totalsRow');
    if (totalsRow) {
        const totCells = totalsRow.querySelectorAll('td');
        // Format: TOTALS (col 0) | WorkHrs | Late | UT | OT | AbsDays | LateDed | UTDed | HalfDed | OTPay | AutoLate | AutoUT | AutoOT | Govt | Net | empty | empty
        if (totCells[1]) totCells[1].textContent = formatNum(totWorkHrs);
        if (totCells[2]) totCells[2].textContent = formatNum(totLateMins, 2);
        if (totCells[3]) totCells[3].textContent = formatNum(totUtHrs);
        if (totCells[4]) totCells[4].textContent = formatNum(totOtHrs);
        if (totCells[5]) totCells[5].textContent = totAbsentDays;
        if (totCells[6]) totCells[6].textContent = formatNum(totLateDed);
        if (totCells[7]) totCells[7].textContent = formatNum(totUtDed);
        if (totCells[8]) totCells[8].textContent = formatNum(totHalfDed);
        if (totCells[9]) totCells[9].textContent = formatNum(totOtPay);
        if (totCells[10]) totCells[10].textContent = formatNum(totAutoLate);
        if (totCells[11]) totCells[11].textContent = formatNum(totAutoUt);
        if (totCells[12]) totCells[12].textContent = formatNum(totAutoOt);
        if (totCells[13]) totCells[13].textContent = formatNum(totGovt);
        if (totCells[14]) totCells[14].innerHTML = '<strong>' + formatNum(totNet) + '</strong>';
    }
    
    // Update Payroll Summary
    updatePayrollSummary();
}

/* ====== Update Payroll Summary ====== */
function updatePayrollSummary() {
    const table = document.getElementById('dtrEditTable');
    if (!table) return;
    
    // Get per day rate directly from the input field (not calculated from basic salary)
    const perDayInput = document.getElementById('edit_per_day');
    const perDay = perDayInput ? parseFloat(perDayInput.value.replace(/,/g, '')) || 0 : 0;
    
    // Get totals from the totals row
    const totalsRow = document.getElementById('totalsRow');
    if (!totalsRow) return;
    
    const totCells = totalsRow.querySelectorAll('td');
    
    // Parse totals (removing commas)
    const totAbsentDays = parseInt(totCells[5]?.textContent || '0') || 0;
    const totLateDed = parseFloat((totCells[6]?.textContent || '0').replace(/,/g, '')) || 0;
    const totUtDed = parseFloat((totCells[7]?.textContent || '0').replace(/,/g, '')) || 0;
    const totHalfDed = parseFloat((totCells[8]?.textContent || '0').replace(/,/g, '')) || 0;
    const totOtPay = parseFloat((totCells[9]?.textContent || '0').replace(/,/g, '')) || 0;
    
    // Calculate absent deduction (days * per day rate)
    const totAbsentDed = totAbsentDays * perDay;
    
    // Calculate days worked and training days from DTR rows
    let daysWorked = 0;
    let trainingDays = 0;
    table.querySelectorAll('tbody tr.dtr-data-row').forEach((row, idx) => {
        const isAbsent = row.querySelector(`input[name="absent_${idx}"]`)?.checked || false;
        const isTraining = row.querySelector(`input[name="training_${idx}"]`)?.checked || false;
        const amIn = row.querySelector(`input[name="am_in_${idx}"]`)?.value?.trim() || '';
        const pmOut = row.querySelector(`input[name="pm_out_${idx}"]`)?.value?.trim() || '';
        const workHrsVal = parseFloat(row.querySelector(`input[name="work_hrs_${idx}"]`)?.value?.replace(/,/g, '') || '0') || 0;
        if (!isAbsent && !isTraining && (amIn || pmOut || workHrsVal > 0)) {
            daysWorked++;
        }
        if (isTraining) {
            trainingDays++;
        }
    });
    
    // Get Net Salary from the totals row (tot-net column - index 14 after removing MINUS OT column)
    const totNetSalary = parseFloat((totCells[14]?.textContent || '0').replace(/,/g, '')) || 0;
    
    // Get training payment amount
    const trainingAmountInput = document.getElementById('view_training_amount');
    const trainingAmount = trainingAmountInput ? parseFloat(trainingAmountInput.value.replace(/,/g, '')) || 0 : 0;

    const rowBenefitBreakdown = getPayrollBenefitBreakdownFromTable(table);
    const hasClassifiedRows = rowBenefitBreakdown.hasClassifiedRows;
    
    // Get government deductions from currentComp
    const sssContrib = hasClassifiedRows ? rowBenefitBreakdown.sss : (parseFloat(currentComp?.sss_contribution) || 0);
    const philHealthContrib = hasClassifiedRows ? rowBenefitBreakdown.philhealth : (parseFloat(currentComp?.philhealth_contribution) || 0);
    const pagibigContrib = hasClassifiedRows ? rowBenefitBreakdown.pagibig : (parseFloat(currentComp?.pagibig_contribution) || 0);
    const otherDeductions = hasClassifiedRows ? rowBenefitBreakdown.other : getPayslipOthersCa(currentComp);
    const otherDeductionLabel = getOtherDeductionLabel(rowBenefitBreakdown.otherLabels);
    const totalGovtDeductions = sssContrib + philHealthContrib + pagibigContrib;
    
    // Show all deductions in Total Deduction for visibility/record keeping.
    // Late/undertime/halfday are already deducted in per-row net salary,
    // so Final Net Pay subtracts only govt deductions plus Others/C.A. here.
    const totalAllDeductions = totLateDed + totUtDed + totHalfDed + totalGovtDeductions + otherDeductions;
    
    // Calculate final net pay: Net Salary + Training + OT - Govt Deductions - Others/C.A.
    // Note: totNetSalary already has late/undertime/halfday deducted per-row, so do NOT subtract them again
    const finalNetPay = totNetSalary + trainingAmount + totOtPay - totalGovtDeductions - otherDeductions;
    
    // Update summary display
    const summaryDaysWorked = document.getElementById('summary_days_worked');
    const summaryNetSalary = document.getElementById('summary_net_salary');
    const summaryAbsentDeduct = document.getElementById('summary_absent_deduct');
    const summaryTrainingDays = document.getElementById('summary_training_days');
    const summaryLateDeduct = document.getElementById('summary_late_deduct');
    const summaryUtDeduct = document.getElementById('summary_ut_deduct');
    const summaryHalfDeduct = document.getElementById('summary_half_deduct');
    const summaryOtherDeductionsLabel = document.getElementById('summary_other_deductions_label');
    const summaryOtherDeductions = document.getElementById('summary_other_deductions');
    const summaryOtPay = document.getElementById('summary_ot_pay');
    const summaryTrainingPayment = document.getElementById('summary_training_payment');
    const summarySss = document.getElementById('summary_sss');
    const summaryPhilHealth = document.getElementById('summary_philhealth');
    const summaryPagibig = document.getElementById('summary_pagibig');
    const summaryTotalGovt = document.getElementById('summary_total_govt');
    const summaryFinalNetPay = document.getElementById('summary_final_net_pay');
    
    if (summaryDaysWorked) summaryDaysWorked.textContent = `${daysWorked} days`;
    if (summaryNetSalary) summaryNetSalary.textContent = peso(totNetSalary);
    if (summaryAbsentDeduct) summaryAbsentDeduct.textContent = `${totAbsentDays} days`;
    if (summaryTrainingDays) summaryTrainingDays.textContent = `${trainingDays} days`;
    if (summaryLateDeduct) summaryLateDeduct.textContent = `-${peso(totLateDed)}`;
    if (summaryUtDeduct) summaryUtDeduct.textContent = `-${peso(totUtDed)}`;
    if (summaryHalfDeduct) summaryHalfDeduct.textContent = `-${peso(totHalfDed)}`;
    if (summaryOtherDeductionsLabel) summaryOtherDeductionsLabel.textContent = otherDeductionLabel;
    if (summaryOtherDeductions) summaryOtherDeductions.textContent = `-${peso(otherDeductions)}`;
    if (summaryOtPay) summaryOtPay.textContent = `+${peso(totOtPay)}`;
    if (summaryTrainingPayment) summaryTrainingPayment.textContent = `+${peso(trainingAmount)}`;
    if (summarySss) summarySss.textContent = `-${peso(sssContrib)}`;
    if (summaryPhilHealth) summaryPhilHealth.textContent = `-${peso(philHealthContrib)}`;
    if (summaryPagibig) summaryPagibig.textContent = `-${peso(pagibigContrib)}`;
    if (summaryTotalGovt) summaryTotalGovt.textContent = `-${peso(totalAllDeductions)}`;
    if (summaryFinalNetPay) summaryFinalNetPay.textContent = peso(finalNetPay);
}

/* ====== Delete Row Function ====== */
function deleteRow(idx) {
    if (!editModeEnabled) {
        showCustomAlert('Please enable <strong>Edit Mode</strong> first before deleting rows.', 'Edit Mode Required', 'warning');
        return;
    }
    
    const table = document.getElementById('dtrEditTable');
    if (!table) return;
    
    const row = table.querySelector(`tr.dtr-data-row[data-idx="${idx}"]`);
    if (row) {
        delete payrollListDayOverrideValues[String(idx)];
        row.remove();
        recalculateTotals();
    }
}

/* ====== Toggle Edit Mode ====== */
function toggleEditMode() {
    editModeEnabled = !editModeEnabled;
    updateEditModeButton();
    
    const table = document.getElementById('dtrEditTable');
    if (!table) return;
    
    const saveBtn = document.getElementById('btn_save_dtr');
    if (saveBtn) {
        saveBtn.style.display = editModeEnabled ? 'inline-flex' : 'none';
    }
    
    // Toggle readonly attribute on all inputs
    table.querySelectorAll('input[type="text"], input[type="number"], input[type="checkbox"]').forEach(input => {
        if (input.classList.contains('dtr-calc-input') || 
            input.classList.contains('dtr-deduct-input') || 
            input.classList.contains('dtr-pay-input') ||
            input.classList.contains('dtr-net-input')) {
            // Keep calculated fields readonly
            return;
        }
        if (input.type === 'checkbox') {
            input.disabled = !editModeEnabled;
        } else {
            if (editModeEnabled) {
                input.removeAttribute('readonly');
            } else {
                input.setAttribute('readonly', 'readonly');
            }
        }
    });
    
    // Toggle delete buttons
    table.querySelectorAll('.btn-delete-row').forEach(btn => {
        btn.disabled = !editModeEnabled;
    });
    
    // Toggle rate inputs
    const salaryInput = document.getElementById('edit_basic_salary');
    const perDayInput = document.getElementById('edit_per_day');
    const otRateInput = document.getElementById('edit_ot_rate');
    const lateStartInput = document.getElementById('edit_late_start');
    const endTimeInput = document.getElementById('edit_end_time');
    
    [salaryInput].forEach(input => {
        if (input) {
            if (editModeEnabled) {
                input.removeAttribute('readonly');
            } else {
                input.setAttribute('readonly', 'readonly');
            }
        }
    });

    // Shift-managed values are global and must stay read-only in this modal.
    [perDayInput, otRateInput, lateStartInput, endTimeInput].forEach(input => {
        if (input) {
            input.setAttribute('readonly', 'readonly');
        }
    });

    // Toggle day override checkbox controls
    table.querySelectorAll('.dtr-day-override-toggle').forEach(toggle => {
        const row = toggle.closest('tr');
        const idx = row?.dataset?.idx;
        const isAbsent = row?.querySelector(`input[name="absent_${idx}"]`)?.checked || false;
        const isTraining = row?.querySelector(`input[name="training_${idx}"]`)?.checked || false;
        toggle.disabled = !editModeEnabled || isAbsent || isTraining;
    });

    if (!editModeEnabled) {
        payrollListCheckedDaysEditMode = false;
        setPayrollCheckedModeLabel(false);
    }
    
    // Toggle training payment inputs
    const trainingAmountInput = document.getElementById('view_training_amount');
    const trainingRemarksInput = document.getElementById('view_training_remarks');
    
    [trainingAmountInput, trainingRemarksInput].forEach(input => {
        if (input) {
            if (editModeEnabled) {
                input.removeAttribute('readonly');
            } else {
                input.setAttribute('readonly', 'readonly');
            }
        }
    });
}

function updateEditModeButton() {
    const btn = document.getElementById('btn_edit_mode');
    const saveBtn = document.getElementById('btn_save_dtr');
    
    if (!btn) return;
    
    if (editModeEnabled) {
        btn.innerHTML = '<i class="fas fa-lock-open"></i> Edit Mode ON';
        btn.style.background = 'linear-gradient(135deg, #38a169, #2f855a)';
    } else {
        btn.innerHTML = '<i class="fas fa-edit"></i> Edit Mode';
        btn.style.background = 'linear-gradient(135deg, #ed8936, #dd6b20)';
    }
    
    // Show/hide save button based on edit mode
    if (saveBtn) {
        saveBtn.style.display = editModeEnabled ? 'inline-flex' : 'none';
    }
}

/* ====== Save DTR Changes - Updated for simplified columns ====== */
async function saveDTRChanges() {
    const table = document.getElementById('dtrEditTable');
    
    // Validation like DTR Calculator
    if (!table) {
        await showCustomAlert('No DTR table found. Please reload the page.', 'Error', 'error');
        return;
    }
    if (!currentEmployeeId) {
        await showCustomAlert('No employee selected. Please select an employee first.', 'Error', 'error');
        return;
    }
    if (!editModeEnabled) {
        await showCustomAlert('Please enable <strong>Edit Mode</strong> first before saving changes.', 'Edit Mode Required', 'warning');
        return;
    }
    
    const rows = table.querySelectorAll('tbody tr.dtr-data-row');
    if (rows.length === 0) {
        await showCustomAlert('No DTR records to save. Please select a period with records.', 'No Records', 'warning');
        return;
    }
    
    const records = [];
    let hasValidData = false;
    
    rows.forEach((row, idx) => {
        const recordId = row.dataset.recordId;
        if (!recordId) return;
        
        // Get simplified time inputs (only AM IN and PM OUT)
        const amIn = row.querySelector(`input[name="am_in_${idx}"]`)?.value || '';
        const pmOut = row.querySelector(`input[name="pm_out_${idx}"]`)?.value || '';
        const otOut = row.querySelector(`input[name="ot_out_${idx}"]`)?.value || '';
        const isAbsent = row.querySelector(`input[name="absent_${idx}"]`)?.checked ? 1 : 0;
        const isTraining = row.querySelector(`input[name="training_${idx}"]`)?.checked ? 1 : 0;
        const workHrs = parseFloat(row.querySelector(`input[name="work_hrs_${idx}"]`)?.value) || 0;
        const lateMins = parseFloat((row.querySelector(`input[name="actual_late_mins_${idx}"]`)?.value || '0').replace(/,/g, '')) || 0;
        const utHrs = parseFloat(row.querySelector(`input[name="ut_hrs_${idx}"]`)?.value) || 0;
        const otHrs = parseFloat(row.querySelector(`input[name="ot_hrs_${idx}"]`)?.value) || 0;
        const remarks = row.querySelector(`input[name="remarks_${idx}"]`)?.value || '';
        const govtDed = parseFloat(row.querySelector(`input[name="govt_${idx}"]`)?.value) || 0;
        
        // Detect halfday: if PM OUT is between 11:45 AM - 12:15 PM (around noon)
        let isHalfday = 0;
        if (pmOut && !isAbsent && !isTraining) {
            const pmOutMins = parseTimeToMinutes(pmOut);
            const noonMins = 12 * 60; // 12:00 PM
            if (pmOutMins >= (noonMins - 15) && pmOutMins <= (noonMins + 15)) {
                isHalfday = 1;
            }
        }
        
        if (amIn || pmOut || isAbsent) hasValidData = true;
        
        // Store as AM IN and PM OUT (we'll need to derive AM OUT and PM IN on the backend if needed)
        records.push({
            id: parseInt(recordId),
            am_time_in: amIn || null,
            am_time_out: null, // Not used in simplified version
            pm_time_in: null,  // Not used in simplified version
            pm_time_out: pmOut || null,
            ot_time_out: otOut || null,
            halfday_in: null,
            halfday_out: null,
            is_halfday: isHalfday,
            is_absent: isAbsent,
            is_training: isTraining,
            total_work_hours: workHrs,
            late_minutes: lateMins,
            undertime_hours: utHrs,
            daily_ot_hours: otHrs,
            govt_deduct: govtDed,
            remarks: remarks
        });
    });
    
    if (records.length === 0) {
        await showCustomAlert('No records found to save. Make sure the DTR table has data.', 'No Records', 'warning');
        return;
    }
    
    // Confirm before saving (like DTR Calculator)
    const confirmed = await showCustomConfirm(
        `Save <strong>${records.length} DTR record(s)</strong> for <strong>${currentEmpInfo?.full_name || 'this employee'}</strong>?<br><br>This will update attendance records and rate settings.`,
        'Confirm Save'
    );
    
    if (!confirmed) return;
    
    const saveBtn = document.getElementById('btn_save_dtr');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    
    // Get rate fields to save - STRIP COMMAS before parsing to avoid "13,000" being read as "13"
    const basicSalaryVal = document.getElementById('edit_basic_salary')?.value || '0';
    const perDayVal = document.getElementById('edit_per_day')?.value || '0';
    const otRateVal = document.getElementById('edit_ot_rate')?.value || '0';
    const basicSalary = parseFloat(basicSalaryVal.replace(/,/g, '')) || 0;
    const perDay = parseFloat(perDayVal.replace(/,/g, '')) || 0;
    const otRate = parseFloat(otRateVal.replace(/,/g, '')) || 0;
    const lateStart = document.getElementById('edit_late_start')?.value || '8:00';
    const endTime = document.getElementById('edit_end_time')?.value || '17:00';
    
    // Get training payment data
    const trainingAmountVal = document.getElementById('view_training_amount')?.value || '0';
    const trainingAmount = parseFloat(trainingAmountVal.replace(/,/g, '')) || 0;
    const trainingRemarks = document.getElementById('view_training_remarks')?.value || '';
    
    const payload = {
        employee_id: currentEmployeeId,
        period_id: currentPeriodId,
        records: records,
        // Rate fields
        basic_salary: basicSalary,
        per_day: perDay,
        ot_rate: otRate,
        late_start: lateStart,
        end_time: endTime,
        // Training payment
        training_amount: trainingAmount,
        training_remarks: trainingRemarks,
        day_override_meta: buildPayrollListDayOverrideMetaForSave()
    };
    
    console.log('Saving DTR records:', payload);
    // Include CSRF token header and payload for server validation
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    payload._csrf = csrfToken;

    fetch('update_dtr_records.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Server error: ' + response.status);
        }
        return response.json();
    })
    .then(async data => {
        console.log('Save response:', data);
        if (data.success) {
            // Capture the current finalNetPay from the modal summary BEFORE reloading
            const finalNetPayEl = document.getElementById('summary_final_net_pay');
            const finalNetPayText = finalNetPayEl ? finalNetPayEl.textContent.trim() : null;

            await showCustomAlert(
                `<strong>${records.length} DTR record(s)</strong> have been saved successfully for <strong>${currentEmpInfo?.full_name}</strong>.`,
                'Save Successful',
                'success'
            );
            editModeEnabled = false;
            updateEditModeButton();

            // Update the employee card's "Final Net Pay" immediately with the saved value
            if (finalNetPayText && currentEmployeeId) {
                const card = document.querySelector(`.employee-card[data-employee-id="${currentEmployeeId}"]`);
                if (card) {
                    const netValueEl = card.querySelector('.net-value');
                    if (netValueEl) netValueEl.textContent = finalNetPayText;
                }
            }

            loadEmployeeDTRByMonth(); // Reload data to show updated values
        } else {
            await showCustomAlert(
                `Error saving DTR records:<br><br><strong>${data.message || 'Unknown error occurred'}</strong>`,
                'Save Failed',
                'error'
            );
        }
    })
    .catch(async err => {
        console.error('Save error:', err);
        await showCustomAlert(
            `Failed to save DTR records.<br><br><strong>Error:</strong> ${err.message}<br><br>Please check your connection and try again.`,
            'Connection Error',
            'error'
        );
    })
    .finally(() => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
    });
}

/* ====== Export to Excel ====== */
function exportCurrentDTR() {
    if (!currentEmployeeId) return;
    // Use detailed TB5-format export so the spreadsheet matches the DTR layout
    const base = window.location.origin + '/TheBigFive_Payroll/admin/';
    let url = `${base}export_dtr_data.php?employee_id=${currentEmployeeId}`;
    if (currentPeriodId) url += `&period_id=${currentPeriodId}`;
    window.location.href = url;
}

/* ====== Export DTR as PDF ====== */
function exportDTRAsPDF() {
    if (!currentEmployeeId || !currentPeriodId) return;
    // Open the DTR table PDF exporter for the selected employee + period
    const pdfBase = window.location.origin + '/TheBigFive_Payroll/admin/';
    const url = `${pdfBase}export_dtr_pdf_proxy.php?employee_id=${currentEmployeeId}&period_id=${currentPeriodId}`;
    window.open(url, '_blank');
    return;

    // Old print code (disabled)
    /*
    const content = document.getElementById('employee_dtr_content').innerHTML;
    const name    = document.getElementById('emp_modal_name')?.textContent || 'Employee';
    const pw = window.open('', '_blank');
    pw.document.write(`<html><head><title>DTR - ${name}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body{font-family:Arial,sans-serif;padding:20px;font-size:12px;}
        table{border-collapse:collapse;width:100%;font-size:11px;margin-bottom:20px;}
        th,td{border:1px solid #999;padding:6px 8px;text-align:center;}
        input[type="time"],input[type="text"],input[type="checkbox"]{display:none;}
        .dtr-calc-header,.emp-info-row{display:flex;gap:20px;margin-bottom:15px;padding:15px;background:#f5f5f5;flex-wrap:wrap;}
        .dtr-rate-row{display:flex;gap:12px;flex-wrap:wrap;padding:10px 15px;background:#f0f0f0;border:1px solid #ccc;border-radius:6px;margin-bottom:15px;align-items:flex-end;}
        .rate-item{display:flex;flex-direction:column;align-items:center;}
        .rate-label{font-size:8px;color:#666;text-transform:uppercase;font-weight:600;margin-bottom:3px;}
        .rate-box{display:flex;align-items:center;border-radius:4px;padding:3px 10px;min-width:90px;justify-content:center;font-weight:700;font-size:12px;}
        .rate-box.rate-green{background:#00FF00!important;border:2px solid #28a745;}
        .rate-box.rate-red{background:#ff4444!important;border:2px solid #cc0000;}
        .rate-box.rate-red .peso-sign,.rate-box.rate-red .rate-val{color:#fff!important;}
        .rate-box.rate-plain{background:#fff!important;border:2px solid #ddd;min-width:70px;}
        .rate-box.rate-plain .peso-sign,.rate-box.rate-plain .rate-val{color:#333!important;}
        .peso-sign,.rate-val{font-weight:700;color:#d63384;font-size:12px;}
        .time-box{min-width:60px;background:#fff;border:2px solid #90cdf4;border-radius:4px;padding:3px 10px;font-weight:700;font-size:12px;text-align:center;}
        .th-am,.th-pm{background:#ed8936!important;color:#fff!important;}
        .th-absent{background:#e53e3e!important;color:#fff!important;}
        .th-ot{background:#4299e1!important;color:#fff!important;}
        .th-halfday{background:#fef08a!important;color:#78350f!important;}
        .th-calc{background:#f7fafc!important;color:#2d3748!important;font-weight:700;}
        .th-deduct{background:#fff5f5!important;color:#c53030!important;font-weight:700;}
        .th-pay{background:#f0fff4!important;color:#276749!important;font-weight:700;}
        .th-govt{background:#FFFFCC!important;color:#333!important;font-weight:700;}
        .th-net{background:#CCFFCC!important;color:#333!important;font-weight:700;}
        .th-remarks{background:#f7fafc!important;}
        .absent-row td{background:#fff5f5!important;}
        tfoot td{background:#2d3748!important;color:#fff!important;font-weight:700;}
        .totals-label{text-align:right!important;padding-right:16px!important;}
        .val-dash{color:#ccc;} .val-late{color:#e53e3e;font-weight:600;} .val-ut{color:#dd6b20;font-weight:600;}
        .val-ot{color:#38a169;font-weight:600;} .val-deduct{color:#e53e3e;font-weight:600;}
        .val-pay{color:#276749;font-weight:600;} .val-govt{color:#744210;font-weight:700;}
        .val-salary{font-weight:700;} .val-salary.positive{color:#276749;} .val-salary.negative{color:#e53e3e;}
        .time-am,.time-pm{color:#c05621;font-weight:600;} .time-ot{color:#2b6cb0;font-weight:600;} .time-half{color:#6b46c1;font-weight:600;}
        .payroll-summary-card{margin-top:20px;border:1px solid #ccc;border-radius:8px;overflow:hidden;}
        .payroll-summary-header{padding:10px 20px;background:#2563EB;color:#fff;font-weight:700;font-size:14px;}
        .payroll-summary-body table{font-size:12px;}
        .payroll-summary-body table td{padding:8px 16px;border-bottom:1px solid #eee;}
        .payroll-summary-body table td:last-child{text-align:right;font-weight:600;}
        .section-divider td{background:#e53e3e!important;color:#fff!important;font-weight:700;}
        .total-row td{background:#edf2f7!important;font-weight:700;}
        .net-pay-row td{background:#38a169!important;color:#fff!important;font-weight:700;font-size:14px;}
        .val-positive{color:#38a169;} .val-negative{color:#e53e3e;}
        h1{text-align:center;margin-bottom:20px;}
        @media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
    </style></head><body>
    <h1>Daily Time Record - ${name}</h1>
    ${content}
    </body></html>`);
    pw.document.close();
    pw.print();
    */
}

// Reset state when modal closes
function closeEmployeeDTRModal() {
    const modal = document.getElementById('employeeDTRModal');
    if (modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }
    currentEmployeeId = null;
    currentPeriodId   = null;
    currentDTRRecords = [];
    currentEmpInfo    = null;
    currentComp       = null;
    editModeEnabled   = false;
    const printBtn = document.getElementById('btn_print_dtr');
    const editBtn = document.getElementById('btn_edit_mode');
    const saveBtn = document.getElementById('btn_save_dtr');
    const payslipBtn = document.getElementById('btn_generate_payslip');
    if (printBtn) printBtn.style.display = 'none';
    if (editBtn) editBtn.style.display = 'none';
    if (saveBtn) saveBtn.style.display = 'none';
    if (payslipBtn) payslipBtn.style.display = 'none';
    updateEditModeButton();
}

// Close on Escape / outside click
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEmployeeDTRModal(); });
document.getElementById('employeeDTRModal')?.addEventListener('click', function(e) { if (e.target === this) closeEmployeeDTRModal(); });

function getPayrollListCutoffTypeFromDate(dateValue) {
    const dateStr = String(dateValue || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return null;
    const day = parseInt(dateStr.slice(8, 10), 10);
    if (!Number.isFinite(day)) return null;
    if (day >= 13 && day <= 27) return 'second';
    if (day >= 28 || day <= 12) return 'first';
    return null;
}

function getPayrollListCutoffLabel(cutoffType) {
    if (cutoffType === 'first') return '1st Cutoff (Prev 28 - Curr 12)';
    if (cutoffType === 'second') return '2nd Cutoff (Curr 13 - 27)';
    return 'Selected Period';
}

function formatPayrollListMonthDay(dateValue) {
    const raw = String(dateValue || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) return '';
    const dt = new Date(raw + 'T00:00:00');
    if (isNaN(dt.getTime())) return '';
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function toPayrollIsoDate(dateObj) {
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function getPayrollListCutoffRangeLabels(records = []) {
    const uniqueDates = [...new Set((records || [])
        .map(rec => String(rec?.dtr_date || '').trim())
        .filter(date => /^\d{4}-\d{2}-\d{2}$/.test(date)))].sort();

    const firstDates = uniqueDates.filter(date => getPayrollListCutoffTypeFromDate(date) === 'first');
    const secondDates = uniqueDates.filter(date => getPayrollListCutoffTypeFromDate(date) === 'second');

    const buildRange = (dates) => {
        if (!dates || dates.length === 0) return '';
        const start = formatPayrollListMonthDay(dates[0]);
        const end = formatPayrollListMonthDay(dates[dates.length - 1]);
        return (start && end) ? `${start} - ${end}` : '';
    };

    let firstRange = buildRange(firstDates);
    let secondRange = buildRange(secondDates);

    if (!firstRange && secondDates.length > 0) {
        const anchor = new Date(secondDates[0] + 'T00:00:00');
        if (!isNaN(anchor.getTime())) {
            const firstStart = new Date(anchor.getFullYear(), anchor.getMonth() - 1, 28);
            const firstEnd = new Date(anchor.getFullYear(), anchor.getMonth(), 12);
            firstRange = `${formatPayrollListMonthDay(toPayrollIsoDate(firstStart))} - ${formatPayrollListMonthDay(toPayrollIsoDate(firstEnd))}`;
        }
    }

    if (!secondRange && firstDates.length > 0) {
        const anchor = new Date(firstDates[firstDates.length - 1] + 'T00:00:00');
        if (!isNaN(anchor.getTime())) {
            const secondStart = new Date(anchor.getFullYear(), anchor.getMonth(), 13);
            const secondEnd = new Date(anchor.getFullYear(), anchor.getMonth(), 27);
            secondRange = `${formatPayrollListMonthDay(toPayrollIsoDate(secondStart))} - ${formatPayrollListMonthDay(toPayrollIsoDate(secondEnd))}`;
        }
    }

    return {
        first: firstRange || 'Prev 28 - Curr 12',
        second: secondRange || 'Curr 13 - 27'
    };
}

/* ====== Generate Payslip with Cutoff Period ====== */
async function showPayslipCutoffSelector() {
    if (!currentComp || !currentComp.id) {
        alert('Please save the DTR first before generating a payslip.');
        return;
    }
    if (!currentDTRRecords || currentDTRRecords.length === 0) {
        alert('No DTR records available.');
        return;
    }

    // Count days with actual values per cutoff
    let firstHalfDays = 0, secondHalfDays = 0, fullDays = 0;
    currentDTRRecords.forEach(rec => {
        const isAbsent = rec.is_absent == 1;
        const isTraining = rec.is_training == 1;
        const hasValue = !isAbsent && !isTraining && (rec.am_time_in || rec.pm_time_out || parseFloat(rec.total_work_hours) > 0);
        if (!hasValue) return;
        const cutoffType = getPayrollListCutoffTypeFromDate(rec.dtr_date);
        if (cutoffType === 'first') firstHalfDays++;
        if (cutoffType === 'second') secondHalfDays++;
        fullDays++;
    });

    document.getElementById('cutoff_first_days').textContent = `${firstHalfDays} working day${firstHalfDays !== 1 ? 's' : ''}`;
    document.getElementById('cutoff_second_days').textContent = `${secondHalfDays} working day${secondHalfDays !== 1 ? 's' : ''}`;
    document.getElementById('cutoff_full_days').textContent = `${fullDays} working day${fullDays !== 1 ? 's' : ''}`;

    const cutoffRanges = getPayrollListCutoffRangeLabels(currentDTRRecords);
    const firstRangeEl = document.getElementById('cutoff_first_range');
    const secondRangeEl = document.getElementById('cutoff_second_range');
    if (firstRangeEl) firstRangeEl.textContent = cutoffRanges.first;
    if (secondRangeEl) secondRangeEl.textContent = cutoffRanges.second;

    // Disable cards with no working days
    const dayMap = { first: firstHalfDays, second: secondHalfDays, full: fullDays };
    document.querySelectorAll('.cutoff-option').forEach(label => {
        const radio = label.querySelector('input[type="radio"]');
        const cutoffVal = radio?.value;
        const hasNoDays = (dayMap[cutoffVal] ?? 1) === 0;
        label.classList.toggle('disabled', hasNoDays);
        radio.disabled = hasNoDays;
        // Remove any previous "Already Generated" badges
        label.querySelectorAll('.cutoff-generated-badge').forEach(b => b.remove());
    });

    // Check which cutoffs already have generated payslips
    try {
        const resp = await fetch(`save_cutoff_payslip.php?check=1&employee_id=${currentEmployeeId}&period_id=${currentPeriodId}`);
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

    // Auto-select first enabled option; uncheck disabled ones
    const radios = [...document.querySelectorAll('input[name="cutoff_period"]')];
    const checkedRadio = document.querySelector('input[name="cutoff_period"]:checked');
    if (checkedRadio && checkedRadio.disabled) {
        checkedRadio.checked = false;
    }
    if (!document.querySelector('input[name="cutoff_period"]:checked')) {
        const firstEnabled = radios.find(r => !r.disabled);
        if (firstEnabled) firstEnabled.checked = true;
    }

    // Zero-day warning removed - allow all cutoff types regardless of working days

    document.getElementById('cutoffSelectorOverlay').style.display = 'flex';
}

function closeCutoffSelector() {
    document.getElementById('cutoffSelectorOverlay').style.display = 'none';
}

async function generatePayslipForCutoff() {
    const cutoffRadio = document.querySelector('input[name="cutoff_period"]:checked');
    if (!cutoffRadio) {
        alert('Please select a cutoff period.');
        return;
    }
    const cutoff = cutoffRadio.value;

    // (allow the later comprehensive check to decide whether to cancel generation)

    closeCutoffSelector();

    if (!currentComp || !currentComp.id) {
        alert('No payroll computation found. Please save the DTR first.');
        return;
    }

    if (cutoff === 'full') {
        // Full month should resolve to an existing non-cutoff computation row.
        const resolvedPayslipId = await resolveFullMonthPayslipId();
        if (!resolvedPayslipId) {
            await showCustomAlert(
                'Unable to find a valid full-month payslip to preview. Please refresh this record and try again.',
                'Payslip Not Found',
                'warning'
            );
            return;
        }
        showPayslipGeneratedModal(resolvedPayslipId, 'Full Month');
        return;
    }

    // For cutoff periods, compute values from DTR records and save a new computation
    const records = currentDTRRecords;
    const comp = currentComp;
    const fallbackPerDay = parseFloat(comp.per_day_rate) || ((parseFloat(comp.basic_monthly_salary) || 0) / 26);
    let savedCutoffOtRate = 0;
    if (comp.other_deductions_notes) {
        try {
            const notes = JSON.parse(comp.other_deductions_notes);
            const parsed = parseFloat(notes?.ot_rate);
            if (!isNaN(parsed) && parsed > 0) savedCutoffOtRate = parsed;
        } catch (e) {
            console.warn('Failed to parse other_deductions_notes for cutoff ot_rate');
        }
    }
    const fallbackOtRate = savedCutoffOtRate;

    const fallbackOverrideValues = {
        perDay: String(fallbackPerDay || ''),
        otRate: String(fallbackOtRate || 0),
        lateStart: comp?.late_start || '8:00',
        endTime: comp?.end_time || '17:00'
    };
    const cutoffOverrideMeta = parsePayrollDayOverrideMeta(comp, fallbackOverrideValues);
    const cutoffDefaultValues = normalizePayrollOverrideValues(cutoffOverrideMeta.default_values, fallbackOverrideValues);
    const cutoffCheckedValues = normalizePayrollOverrideValues(cutoffOverrideMeta.checked_values, cutoffDefaultValues);
    const cutoffCheckedRowsSet = new Set((cutoffOverrideMeta.checked_rows || []).map(v => String(v)));
    const cutoffRowOverrideByDate = (cutoffOverrideMeta.row_values && typeof cutoffOverrideMeta.row_values === 'object')
        ? cutoffOverrideMeta.row_values
        : {};

    const parseRateOrFallback = (rawValue, fallbackValue) => {
        const parsed = parseFloat(String(rawValue ?? '').replace(/,/g, ''));
        return Number.isFinite(parsed) ? parsed : fallbackValue;
    };

    const payloadPerDay = parseRateOrFallback(cutoffDefaultValues.perDay, fallbackPerDay);
    const payloadPerHour = payloadPerDay / 8;
    const payloadOtRate = parseRateOrFallback(cutoffDefaultValues.otRate, fallbackOtRate);

    let daysWorked = 0, totWorkHrs = 0, totLateMins = 0, totUtHrs = 0, totOtHrs = 0;
    let totLateDed = 0, totUtDed = 0, totHalfDed = 0, totOtPay = 0, totNetSalary = 0;
    let totAbsentDed = 0;
    let totAbsentDays = 0, totTrainingDays = 0;
    let deductionRemarks = [];
    let dtrOtherDeduction = 0;
    let dtrWithholdingTax = 0;
    let dtrSss = 0;
    let dtrPhilhealth = 0;
    let dtrPagibig = 0;

    const isGovernmentDeductionRemark = (remark) => {
        const upper = (remark || '').toUpperCase();
        if (!upper) return false;
        return upper.includes('SSS')
            || upper.includes('PHILHEALTH')
            || upper.includes('PHIL HEALTH')
            || upper.includes('PAGIBIG')
            || upper.includes('PAG-IBIG')
            || upper.includes('HDMF')
            || upper.includes('WITHHOLD')
            || upper.includes('TAX');
    };

    records.forEach(rec => {
        const cutoffTypeForRecord = getPayrollListCutoffTypeFromDate(rec.dtr_date);
        // Filter by cutoff using the same company cycle as Generate Payroll.
        if (cutoff === 'first' && cutoffTypeForRecord !== 'first') return;
        if (cutoff === 'second' && cutoffTypeForRecord !== 'second') return;

        const rowDate = String(rec.dtr_date || '');
        const isDayOverrideChecked = cutoffCheckedRowsSet.has(rowDate);
        const rowOverrideValues = cutoffRowOverrideByDate[rowDate] || null;
        const effectiveRowValues = isDayOverrideChecked
            ? normalizePayrollOverrideValues(rowOverrideValues, cutoffCheckedValues)
            : normalizePayrollOverrideValues(null, cutoffDefaultValues);
        const rowPerDay = parseRateOrFallback(effectiveRowValues.perDay, payloadPerDay);
        const rowPerHour = rowPerDay / 8;
        const rowOtRate = parseRateOrFallback(effectiveRowValues.otRate, payloadOtRate);

        const remarkText = String(rec.remarks || '').trim();
        const rowGovtDeduct = parseFloat(rec.govt_deduct) || 0;
        const upperRemark = remarkText.toUpperCase();
        if (remarkText) {
            deductionRemarks.push(remarkText);
        }

        if (rowGovtDeduct > 0 && remarkText) {
            if (upperRemark.includes('PHILHEALTH') || upperRemark.includes('PHIL HEALTH')) {
                dtrPhilhealth += rowGovtDeduct;
            } else if (upperRemark.includes('PAGIBIG') || upperRemark.includes('PAG-IBIG') || upperRemark.includes('HDMF')) {
                dtrPagibig += rowGovtDeduct;
            } else if (upperRemark.includes('WITHHOLD') || upperRemark.includes('TAX')) {
                dtrWithholdingTax += rowGovtDeduct;
            } else if (upperRemark.includes('SSS')) {
                dtrSss += rowGovtDeduct;
            } else if (!isGovernmentDeductionRemark(remarkText)) {
                dtrOtherDeduction += rowGovtDeduct;
            }
        }

        const isAbsent = rec.is_absent == 1;
        const isTraining = rec.is_training == 1;
        const isHalf = rec.is_halfday == 1;
        const workHrs = (isAbsent || isTraining) ? 0 : (parseFloat(rec.total_work_hours) || 0);
        const lateMins = (isAbsent || isTraining) ? 0 : (parseFloat(rec.late_minutes) || 0);
        const equivalentLateMins = (isAbsent || isTraining) ? 0 : getPayrollListLateEquivalentMinutes(lateMins);
        const utHrs = (isAbsent || isTraining || isHalf) ? 0 : (parseFloat(rec.undertime_hours) || 0);
        const otHrs = (isAbsent || isTraining) ? 0 : (parseFloat(rec.daily_ot_hours) || 0);

        const lateDed = (isAbsent || isTraining) ? 0 : (equivalentLateMins / 60) * rowPerHour;
        const utDed = (isAbsent || isTraining || isHalf) ? 0 : utHrs * rowPerHour;
        const halfDed = isHalf ? (rowPerDay / 2) : 0;
        const otPayRow = (isAbsent || isTraining) ? 0 : otHrs * rowOtRate;
        const absentDed = isAbsent ? rowPerDay : 0;

        let rowNet;
        if (isAbsent) rowNet = 0;
        else if (isTraining) rowNet = 0;
        else if (isHalf) rowNet = (rowPerDay / 2) - lateDed;
        else if (workHrs === 0 && otHrs === 0) rowNet = 0;
        else rowNet = rowPerDay - lateDed - utDed;

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
        totNetSalary += rowNet;
    });

    // Block generation if there are no work days in the selected cutoff period
    if (daysWorked === 0 && totAbsentDays === 0 && totTrainingDays === 0) {
        const label = getPayrollListCutoffLabel(cutoff);
        showCustomAlert(
            `No work records found for the <b>${label}</b>. Payslip generation has been cancelled.`,
            'No Work Days',
            'warning'
        );
        return;
    }

    // Compute final values
    const grossPay = totNetSalary; // Sum of row net salaries (basic pay portion)
    const totalHalfDeduct = totHalfDed;

    // Split govt deductions proportionally based on cutoff
    const compWithholdingTax = parseFloat(comp.withholding_tax) || 0;
    const compSss = parseFloat(comp.sss_contribution) || 0;
    const compPhilhealth = parseFloat(comp.philhealth_contribution) || 0;
    const compPagibig = parseFloat(comp.pagibig_contribution) || 0;
    const withholdingTax = dtrWithholdingTax > 0 ? dtrWithholdingTax : compWithholdingTax;
    const sss = dtrSss > 0 ? dtrSss : compSss;
    const philhealth = dtrPhilhealth > 0 ? dtrPhilhealth : compPhilhealth;
    const pagibig = dtrPagibig > 0 ? dtrPagibig : compPagibig;
    // For cutoff: govt deductions are typically applied once per month (usually 2nd cutoff)
    // But let user configure — for now, apply full govt deductions to whichever cutoff they chose
    const totalGovtDed = withholdingTax + sss + philhealth + pagibig;

    // Training: include training payment from the training payment input (user can edit per cutoff)
    const trainingAmountInput = document.getElementById('view_training_amount');
    const trainingAmount = trainingAmountInput ? parseFloat(trainingAmountInput.value.replace(/,/g, '')) || 0 : 0;
    const trainingRemarksInput = document.getElementById('view_training_remarks');
    const trainingRemarks = trainingRemarksInput ? trainingRemarksInput.value.trim() : '';

    const existingOtherDeductions = parseFloat(comp.other_deductions) || 0;
    const otherDeductions = dtrOtherDeduction > 0 ? dtrOtherDeduction : existingOtherDeductions;

    const totalEarnings = grossPay + totOtPay + trainingAmount;
    // Attendance deductions are already reflected in row net salaries.
    // Keep cutoff net pay aligned: deduct government contributions and Others/C.A. here.
    const totalDeductions = totalGovtDed + otherDeductions;
    const netPay = totalEarnings - totalDeductions;
    const uniqueDeductionRemarks = [...new Set(deductionRemarks)];
    const deductionRemarksText = uniqueDeductionRemarks.join(' | ');

    // Determine cutoff label
    let cutoffLabel = getPayrollListCutoffLabel(cutoff);

    // Save cutoff computation and generate payslip
    const payload = {
        employee_id: currentEmployeeId,
        period_id: currentPeriodId,
        cutoff_type: cutoff,
        cutoff_label: cutoffLabel,
        basic_pay: grossPay,
        ot_pay: totOtPay,
        total_work_hours: totWorkHrs,
        total_ot_hours: totOtHrs,
        per_day_rate: payloadPerDay,
        per_hour_rate: payloadPerHour,
        ot_rate: payloadOtRate,
        late_minutes: totLateMins,
        undertime_hours: totUtHrs,
        halfday_deduct: totalHalfDeduct,
        withholding_tax: withholdingTax,
        sss_contribution: sss,
        philhealth_contribution: philhealth,
        pagibig_contribution: pagibig,
        other_deductions: otherDeductions,
        deduction_remarks: deductionRemarksText,
        deduction_remarks_list: uniqueDeductionRemarks,
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
        absent_deduct: totAbsentDed,
        source_computation_id: currentComp.id
    };

    // POST to save_cutoff_payslip.php which saves and returns the new payslip_id
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('save_cutoff_payslip.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.payslip_id) {
            showPayslipGeneratedModal(data.payslip_id, cutoffLabel);
        } else {
            alert('Error generating payslip: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}
</script>

<?php require_once 'include/footer.php'; ?>
