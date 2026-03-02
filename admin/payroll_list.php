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

// Get all employees with DTR records HGFHGFHFH DSGSDGD
$stmt = $pdo->query("
    SELECT 
        e.id,
        e.employee_code,
        e.full_name,
        e.position,
        e.department,
        e.basic_monthly_salary,
        e.status,
        COUNT(d.id) as dtr_count,
        MAX(d.dtr_date) as last_dtr_date,
        SUM(CASE WHEN d.is_absent = 1 THEN 1 ELSE 0 END) as absent_days,
        SUM(d.daily_ot_hours) as total_ot_hours
    FROM employees e
    LEFT JOIN dtr_records d ON e.id = d.employee_id
    WHERE e.status = 'active'
    GROUP BY e.id, e.employee_code, e.full_name, e.position, e.department, e.basic_monthly_salary, e.status
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
                            
                            // Calculate net pay estimate (per cutoff = 15 days) SADASDSAD
                            $salary = floatval($emp['basic_monthly_salary']);
                            $dailyRate = $salary / 15;  // Per cutoff rate
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
                                    <span class="detail-label">Department</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($emp['department'] ?: '-'); ?></span>
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
            <button type="button" class="btn-modern btn-primary" id="btn_print_dtr" onclick="printEmployeeDTR()" style="display:none;">
                <i class="fas fa-print"></i> Print
            </button>
            <button type="button" class="btn-modern btn-secondary" onclick="closeEmployeeDTRModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

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
.employee-card-header { display:flex; align-items:center; gap:15px; margin-bottom:15px; }
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
.view-dtr-link { color:#2563EB; font-size:13px; font-weight:500; display:flex; align-items:center; gap:5px; }
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

/* DTR Table Wrapper */
.dtr-table-wrapper {
    overflow-x: auto;
    padding: 0;
}

/* Main DTR Table - TB5 Style (Exact Match) */
.dtr-table {
    width: 100%;
    min-width: 2000px;
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
.dtr-table .th-am, .dtr-table .th-group.th-am { background: #FFCC99; color: #000; font-weight: 700; }
.dtr-table .th-pm, .dtr-table .th-group.th-pm { background: #FFCC99; color: #000; font-weight: 700; }
.dtr-table .th-absent-col, .dtr-table .th-single.th-absent-col { background: #FF9999; color: #000; font-weight: 700; }
.dtr-table .th-ot-col, .dtr-table .th-single.th-ot-col { background: #99CCFF; color: #000; font-weight: 700; }
.dtr-table .th-halfday, .dtr-table .th-group.th-halfday { background: #FFFF99; color: #000; font-weight: 700; }
.dtr-table .th-calc { background: #CCFFCC; color: #000; font-weight: 700; }
.dtr-table .th-deduct { background: #FFCCCC; color: #000; font-weight: 700; }
.dtr-table .th-pay { background: #99FF99; color: #000; font-weight: 700; }
.dtr-table .th-auto { background: #E6F3FF; color: #000; font-weight: 700; }
.dtr-table .th-govt { background: #FFFFCC; color: #000; font-weight: 700; }
.dtr-table .th-net { background: #66FF66; color: #000; font-weight: 700; }
.dtr-table .th-remarks { background: #f0f0f0; color: #000; font-weight: 600; }
.dtr-table .th-actions { background: #f0f0f0; color: #000; font-weight: 600; }

/* Sub headers - TB5 Style */
.dtr-table .th-sub { font-size: 9px; padding: 5px 4px; font-weight: 600; }

/* Date Cell - TB5 Format */
.dtr-table .date-cell { min-width: 70px; padding: 6px 8px; background: #fff; }
.dtr-table .date-display { text-align: center; }
.dtr-table .date-day { font-size: 18px; font-weight: 700; color: #1a202c; line-height: 1.1; }
.dtr-table .date-month { font-size: 10px; color: #4a5568; text-transform: uppercase; font-weight: 600; letter-spacing: 0.3px; }

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
    border-left: 4px solid #fc8181 !important;
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
</style>

<script>
let currentEmployeeId = null;
let currentPeriodId   = null;
let currentDTRRecords = [];
let currentEmpInfo    = null;
let currentComp       = null;
let editModeEnabled   = false;

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

document.getElementById('btn_refresh_cards')?.addEventListener('click', function() {
    location.reload();
});

function openEmployeeDTRModal(employeeId, employeeName) {
    currentEmployeeId = employeeId;
    editModeEnabled = false;
    const modal = document.getElementById('employeeDTRModal');
    if (!modal) return;
    document.getElementById('emp_modal_name').textContent = employeeName + ' - DTR';
    updateEditModeButton();
    loadAvailableMonths(employeeId);
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function updateEditModeButton() {
    const btn = document.getElementById('btn_edit_mode');
    const saveBtn = document.getElementById('btn_save_dtr');
    if (btn) {
        btn.innerHTML = editModeEnabled 
            ? '<i class="fas fa-times"></i> Cancel Edit' 
            : '<i class="fas fa-edit"></i> Edit Mode';
        btn.classList.toggle('active', editModeEnabled);
    }
    if (saveBtn) {
        saveBtn.style.display = editModeEnabled ? 'inline-flex' : 'none';
    }
}

function toggleEditMode() {
    editModeEnabled = !editModeEnabled;
    updateEditModeButton();
    
    // Toggle readonly/disabled on all editable inputs without re-rendering
    const table = document.getElementById('dtrEditTable');
    if (table) {
        // Time input fields
        table.querySelectorAll('.dtr-input.time24').forEach(input => {
            input.readOnly = !editModeEnabled;
        });
        // Absent checkboxes
        table.querySelectorAll('.dtr-absent').forEach(cb => {
            cb.disabled = !editModeEnabled;
        });
        // Remarks inputs
        table.querySelectorAll('.dtr-remarks-input').forEach(input => {
            input.readOnly = !editModeEnabled;
        });
    }
    
    // Rate input fields
    const rateInputs = document.querySelectorAll('#edit_basic_salary, #edit_per_day, #edit_late_start, #edit_end_time');
    rateInputs.forEach(input => {
        input.readOnly = !editModeEnabled;
    });
}

function loadAvailableMonths(employeeId) {
    const select  = document.getElementById('dtr_month_select');
    const content = document.getElementById('employee_dtr_content');
    const printBtn = document.getElementById('btn_print_dtr');
    const editBtn = document.getElementById('btn_edit_mode');
    select.innerHTML = '<option value="">Loading...</option>';
    content.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i></div>';
    if (printBtn) printBtn.style.display = 'none';
    if (editBtn) editBtn.style.display = 'none';

    fetch(`get_employee_dtr_months.php?employee_id=${employeeId}`)
    .then(r => r.json())
    .then(data => {
        if (data.success && data.months.length > 0) {
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
    .catch(err => { console.error(err); select.innerHTML = '<option value="">Error loading</option>'; });
}

function loadEmployeeDTRByMonth() {
    const select    = document.getElementById('dtr_month_select');
    const content   = document.getElementById('employee_dtr_content');
    const printBtn = document.getElementById('btn_print_dtr');
    const editBtn = document.getElementById('btn_edit_mode');
    const rawVal    = select.value;
    if (!rawVal || !currentEmployeeId) {
        content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-alt"></i><br>Select a period</div>';
        if (printBtn) printBtn.style.display = 'none';
        if (editBtn) editBtn.style.display = 'none';
        return;
    }
    content.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i></div>';
    if (printBtn) printBtn.style.display = 'none';
    if (editBtn) editBtn.style.display = 'none';
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
    .then(r => r.json())
    .then(data => {
        if (data.success && data.records.length > 0) {
            currentPeriodId = data.period_id || currentPeriodId;
            currentDTRRecords = data.records;
            currentEmpInfo = data.employee_info;
            currentComp = data.payroll_computation;
            if (printBtn) printBtn.style.display = 'inline-flex';
            if (editBtn) editBtn.style.display = 'inline-flex';
            content.innerHTML = buildDTRView(data.records, data.employee_info, data.payroll_computation);
        } else {
            currentDTRRecords = [];
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-file-alt"></i><br>No records</div>';
        }
    })
    .catch(err => { console.error(err); content.innerHTML = '<div class="no-cards-message"><i class="fas fa-exclamation-circle"></i><br>Error loading</div>'; });
}

/* ====== Helper Formatters ====== */
function peso(v) { return '₱' + Math.abs(v).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function fmtTime(t) { return t ? t : ''; }
function dash() { return '<span class="val-dash">-</span>'; }

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

/* ====== Rate Calculations ====== */
// Per cutoff (15 days) rate calculation
function getRates(salary) {
    const perDay  = salary / 15;  // Divided by 15 (per cutoff)
    const perHour = perDay / 8;
    const perMin  = perHour / 60;
    const otRate  = perHour * 1.25;
    return { perDay, perHour, perMin, otRate };
}

/* ====== Rate Update Functions (Like Generate Payroll) ====== */
function updateRatesFromSalary() {
    const salaryInput = document.getElementById('edit_basic_salary');
    const perDayInput = document.getElementById('edit_per_day');
    const perHourSpan = document.getElementById('edit_per_hour');
    const perMinSpan = document.getElementById('edit_per_min');
    
    if (!salaryInput) return;
    
    const salary = parseFloat(salaryInput.value) || 0;
    const { perDay, perHour, perMin } = getRates(salary);
    
    if (perDayInput) perDayInput.value = perDay.toFixed(2);
    if (perHourSpan) perHourSpan.textContent = perHour.toFixed(2);
    if (perMinSpan) perMinSpan.textContent = perMin.toFixed(4);
    
    // Update currentEmpInfo with new salary
    if (currentEmpInfo) currentEmpInfo.basic_monthly_salary = salary;
    
    // Recalculate all DTR rows with new rates
    recalculateAllRows();
}

function updateRatesFromDaily() {
    const perDayInput = document.getElementById('edit_per_day');
    const salaryInput = document.getElementById('edit_basic_salary');
    const perHourSpan = document.getElementById('edit_per_hour');
    const perMinSpan = document.getElementById('edit_per_min');
    
    if (!perDayInput) return;
    
    const perDay = parseFloat(perDayInput.value) || 0;
    const salary = perDay * 15;  // Multiply by 15 (per cutoff)
    const perHour = perDay / 8;
    const perMin = perHour / 60;
    
    if (salaryInput) salaryInput.value = salary.toFixed(0);
    if (perHourSpan) perHourSpan.textContent = perHour.toFixed(2);
    if (perMinSpan) perMinSpan.textContent = perMin.toFixed(4);
    
    // Update currentEmpInfo with new salary
    if (currentEmpInfo) currentEmpInfo.basic_monthly_salary = salary;
    
    // Recalculate all DTR rows with new rates
    recalculateAllRows();
}

function recalculateAllRows() {
    const table = document.getElementById('dtrEditTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr.dtr-data-row');
    rows.forEach((row) => {
        recalculateRow(row);
    });
    
    recalculateTotals();
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

/* ====== Calculate Work Hours ====== */
function calculateWorkHours(amIn, amOut, pmIn, pmOut) {
    let totalMins = 0;
    const amInMins = parseTimeToMinutes(amIn);
    const amOutMins = parseTimeToMinutes(amOut);
    const pmInMins = parseTimeToMinutes(pmIn);
    const pmOutMins = parseTimeToMinutes(pmOut);
    
    if (amInMins !== null && amOutMins !== null && amOutMins > amInMins) {
        totalMins += (amOutMins - amInMins);
    }
    if (pmInMins !== null && pmOutMins !== null && pmOutMins > pmInMins) {
        totalMins += (pmOutMins - pmInMins);
    }
    return totalMins / 60; // Return hours
}

/* ====== Calculate Late Minutes ====== */
function calculateLateMins(amIn, lateStart = '07:35') {
    const amInMins = parseTimeToMinutes(amIn);
    const startMins = parseTimeToMinutes(lateStart);
    if (amInMins === null || startMins === null) return 0;
    return Math.max(0, amInMins - startMins);
}

/* ====== Calculate Undertime ====== */
function calculateUndertime(pmOut, endTime = '17:00') {
    const pmOutMins = parseTimeToMinutes(pmOut);
    const endMins = parseTimeToMinutes(endTime);
    if (pmOutMins === null || endMins === null) return 0;
    const utMins = Math.max(0, endMins - pmOutMins);
    return utMins / 60; // Return hours
}

/* ====== Build the full DTR view ====== */
function buildDTRView(records, empInfo, comp) {
    const salary  = parseFloat(empInfo.basic_monthly_salary) || 0;
    const { perDay, perHour, perMin, otRate } = getRates(salary);
    
    // Get late_start and end_time from payroll computation if available
    const savedLateStart = comp?.late_start || '07:35';
    const savedEndTime = comp?.end_time || '17:00';

    // Totals accumulators
    let totWorkHrs = 0, totLateMins = 0, totUtHrs = 0, totOtHrs = 0;
    let totAbsentDays = 0, totAbsentDed = 0, totLateDed = 0, totUtDed = 0, totHalfDed = 0;
    let totOtPay = 0, totGovt = 0, totNetSalary = 0;
    let daysWorked = 0;

    // Govt deductions from DB
    let sssAmt = 0, philAmt = 0, pagAmt = 0;
    records.forEach(rec => {
        const rem = (rec.remarks || '').toUpperCase().trim();
        const gd = parseFloat(rec.govt_deduct) || 0;
        if (rem.includes('SSS') && !rem.includes('PHILHEALTH')) sssAmt = gd || sssAmt;
        if (rem.includes('PHILHEALTH') || rem.includes('PHIL HEALTH')) philAmt = gd || philAmt;
        if (rem.includes('PAGIBIG') || rem.includes('PAG-IBIG') || rem.includes('HDMF')) pagAmt = gd || pagAmt;
    });

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
            <div class="tb5-rate-item rate-input-item">
                <span class="tb5-rate-label">BASIC SALARY</span>
                <div class="rate-input-wrapper rate-green">
                    <span class="peso-sign">₱</span>
                    <input type="number" id="edit_basic_salary" name="basic_salary" 
                           class="rate-input" value="${salary}" step="100" 
                           oninput="updateRatesFromSalary()" ${!editModeEnabled ? 'readonly' : ''}>
                </div>
            </div>
            <div class="tb5-rate-item rate-input-item">
                <span class="tb5-rate-label">PER/DAY</span>
                <div class="rate-input-wrapper rate-red">
                    <span class="peso-sign">₱</span>
                    <input type="number" id="edit_per_day" name="per_day" 
                           class="rate-input" value="${perDay.toFixed(2)}" step="0.01" 
                           oninput="updateRatesFromDaily()" ${!editModeEnabled ? 'readonly' : ''}>
                </div>
            </div>
            <div class="tb5-rate-item">
                <span class="tb5-rate-label">PER/HOUR</span>
                <div class="rate-display rate-plain">
                    <span class="peso-sign">₱</span>
                    <span id="edit_per_hour" class="rate-val">${perHour.toFixed(2)}</span>
                </div>
            </div>
            <div class="tb5-rate-item">
                <span class="tb5-rate-label">PER/MIN</span>
                <div class="rate-display rate-plain">
                    <span class="peso-sign">₱</span>
                    <span id="edit_per_min" class="rate-val">${perMin.toFixed(4)}</span>
                </div>
            </div>
            <div class="tb5-rate-item rate-input-item">
                <span class="tb5-rate-label">LATE START</span>
                <input type="text" id="edit_late_start" name="late_start" value="${savedLateStart}" 
                       class="time-input" maxlength="5" placeholder="07:35"
                       oninput="formatTime24(this)" onchange="recalculateAllRows()" ${!editModeEnabled ? 'readonly' : ''}>
            </div>
            <div class="tb5-rate-item rate-input-item">
                <span class="tb5-rate-label">END TIME</span>
                <input type="text" id="edit_end_time" name="end_time" value="${savedEndTime}" 
                       class="time-input" maxlength="5" placeholder="17:00"
                       oninput="formatTime24(this)" onchange="recalculateAllRows()" ${!editModeEnabled ? 'readonly' : ''}>
            </div>
        </div>
    </div>`;

    // ====== TB5 Style DTR Table ======
    html += `
    <div class="dtr-table-wrapper">
    <table class="dtr-table" id="dtrEditTable">
        <thead>
            <tr>
                <th rowspan="2" class="th-date">MO/YR<br>DATE</th>
                <th colspan="2" class="th-group th-am">AM</th>
                <th colspan="2" class="th-group th-pm">PM</th>
                <th rowspan="2" class="th-single th-absent-col">ABSENT</th>
                <th rowspan="2" class="th-single th-ot-col">OT OUT</th>
                <th colspan="2" class="th-group th-halfday">HALFDAY</th>
                <th rowspan="2" class="th-calc">TOT.WORK<br>(hrs)</th>
                <th rowspan="2" class="th-calc">LATE<br>(mins)</th>
                <th rowspan="2" class="th-calc">UNDER<br>TIME</th>
                <th rowspan="2" class="th-calc">OT<br>(hrs)</th>
                <th rowspan="2" class="th-deduct">ABSENT<br>DEDUCT</th>
                <th rowspan="2" class="th-deduct">LATE<br>DEDUCT</th>
                <th rowspan="2" class="th-deduct">UT<br>DEDUCT</th>
                <th rowspan="2" class="th-deduct">HALF<br>DEDUCT</th>
                <th rowspan="2" class="th-pay">OT PAY</th>
                <th rowspan="2" class="th-govt">Gov't</th>
                <th rowspan="2" class="th-net">NET</th>
                <th rowspan="2" class="th-remarks">REMARKS</th>
            </tr>
            <tr>
                <th class="th-sub th-am">IN</th>
                <th class="th-sub th-am">OUT</th>
                <th class="th-sub th-pm">IN</th>
                <th class="th-sub th-pm">OUT</th>
                <th class="th-sub th-halfday">IN</th>
                <th class="th-sub th-halfday">OUT</th>
            </tr>
        </thead>
        <tbody>`;

    records.forEach((rec, idx) => {
        const isAbsent = rec.is_absent == 1;
        const isHalf   = rec.is_halfday == 1;
        const workHrs  = parseFloat(rec.total_work_hours) || 0;
        const lateMins = parseFloat(rec.late_minutes) || 0;
        const utHrs    = parseFloat(rec.undertime_hours) || 0;
        const otHrs    = parseFloat(rec.daily_ot_hours) || 0;
        const govtDb   = parseFloat(rec.govt_deduct) || 0;
        const netDb    = rec.net_salary !== null ? parseFloat(rec.net_salary) : null;

        // Compute row deductions
        const absentDed = isAbsent ? perDay : 0;
        const lateDed   = (lateMins / 60) * perHour;
        const utDed     = utHrs * perHour;
        const halfDed   = isHalf ? (perDay / 2) : 0;
        const otPayRow  = otHrs * otRate;

        let rowNet = netDb;
        if (rowNet === null) {
            if (isAbsent) {
                rowNet = 0 - absentDed - govtDb;
            } else {
                rowNet = (workHrs * perHour) - lateDed - utDed - halfDed + otPayRow - govtDb;
            }
        }

        if (!isAbsent) daysWorked++;
        totAbsentDays += isAbsent ? 1 : 0;
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

        // Format date like TB5 (day and month)
        let dateDisplay = rec.dtr_date;
        if (rec.dtr_date) {
            const dateParts = rec.dtr_date.split('-');
            if (dateParts.length === 3) {
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const day = parseInt(dateParts[2]);
                const month = months[parseInt(dateParts[1]) - 1] || '';
                dateDisplay = `<div class="date-day">${day}</div><div class="date-month">${month}</div>`;
            }
        }

        html += `<tr class="dtr-data-row ${isAbsent ? 'absent-row' : ''}" data-record-id="${rec.id}" data-idx="${idx}" data-row="${idx+1}">`;
        html += `<td class="date-cell"><div class="date-display">${dateDisplay}</div></td>`;

        // Always show editable inputs like in DTR Calculator
        html += `<td class="td-am"><input type="text" name="am_in_${idx}" class="dtr-input time24 input-am" value="${fmtTime24(rec.am_time_in)}" maxlength="5" onchange="recalculateRow(this.closest('tr'))" oninput="formatTime24(this)" placeholder="8:00" ${!editModeEnabled ? 'readonly' : ''}></td>`;
        html += `<td class="td-am"><input type="text" name="am_out_${idx}" class="dtr-input time24 input-am" value="${fmtTime24(rec.am_time_out)}" maxlength="5" onchange="recalculateRow(this.closest('tr'))" oninput="formatTime24(this)" placeholder="12:00" ${!editModeEnabled ? 'readonly' : ''}></td>`;
        html += `<td class="td-pm"><input type="text" name="pm_in_${idx}" class="dtr-input time24 input-pm" value="${fmtTime24(rec.pm_time_in)}" maxlength="5" onchange="recalculateRow(this.closest('tr'))" oninput="formatTime24(this)" placeholder="13:00" ${!editModeEnabled ? 'readonly' : ''}></td>`;
        html += `<td class="td-pm"><input type="text" name="pm_out_${idx}" class="dtr-input time24 input-pm" value="${fmtTime24(rec.pm_time_out)}" maxlength="5" onchange="recalculateRow(this.closest('tr'))" oninput="formatTime24(this)" placeholder="17:00" ${!editModeEnabled ? 'readonly' : ''}></td>`;
        html += `<td class="td-absent centered"><input type="checkbox" name="absent_${idx}" class="dtr-absent" ${isAbsent ? 'checked' : ''} onchange="recalculateRow(this.closest('tr'))" ${!editModeEnabled ? 'disabled' : ''}></td>`;
        html += `<td class="td-ot"><input type="text" name="ot_out_${idx}" class="dtr-input time24 input-ot" value="${fmtTime24(rec.ot_time_out)}" maxlength="5" onchange="recalculateRow(this.closest('tr'))" oninput="formatTime24(this)" placeholder="" ${!editModeEnabled ? 'readonly' : ''}></td>`;
        html += `<td class="td-half"><input type="text" name="half_in_${idx}" class="dtr-input time24 input-half" value="${fmtTime24(rec.halfday_in)}" maxlength="5" onchange="recalculateRow(this.closest('tr'))" oninput="formatTime24(this)" ${!editModeEnabled ? 'readonly' : ''}></td>`;
        html += `<td class="td-half"><input type="text" name="half_out_${idx}" class="dtr-input time24 input-half" value="${fmtTime24(rec.halfday_out)}" maxlength="5" onchange="recalculateRow(this.closest('tr'))" oninput="formatTime24(this)" ${!editModeEnabled ? 'readonly' : ''}></td>`;

        // Calculated fields with TB5 highlighting - use input fields for easy reading
        html += `<td class="td-calc calc-highlight"><input type="number" name="work_hrs_${idx}" class="dtr-calc-input" value="${workHrs.toFixed(2)}" readonly></td>`;
        html += `<td class="td-calc calc-highlight"><input type="number" name="late_mins_${idx}" class="dtr-calc-input" value="${lateMins}" readonly></td>`;
        html += `<td class="td-calc calc-highlight"><input type="number" name="ut_hrs_${idx}" class="dtr-calc-input" value="${utHrs.toFixed(2)}" readonly></td>`;
        html += `<td class="td-calc calc-highlight"><input type="number" name="ot_hrs_${idx}" class="dtr-calc-input" value="${otHrs.toFixed(2)}" readonly></td>`;
        html += `<td class="td-deduct deduct-highlight"><input type="number" name="absent_ded_${idx}" class="dtr-deduct-input" value="${absentDed.toFixed(2)}" readonly></td>`;
        html += `<td class="td-deduct deduct-highlight"><input type="number" name="late_ded_${idx}" class="dtr-deduct-input" value="${lateDed.toFixed(2)}" readonly></td>`;
        html += `<td class="td-deduct deduct-highlight"><input type="number" name="ut_ded_${idx}" class="dtr-deduct-input" value="${utDed.toFixed(2)}" readonly></td>`;
        html += `<td class="td-deduct deduct-highlight"><input type="number" name="half_ded_${idx}" class="dtr-deduct-input" value="${halfDed.toFixed(2)}" readonly></td>`;
        html += `<td class="td-pay pay-highlight"><input type="number" name="ot_pay_${idx}" class="dtr-pay-input" value="${otPayRow.toFixed(2)}" readonly></td>`;
        html += `<td class="td-govt govt-highlight"><input type="number" name="govt_${idx}" class="dtr-govt-input" value="${govtDb.toFixed(2)}" step="0.01" ${editModeEnabled ? '' : 'readonly'}></td>`;
        html += `<td class="td-net net-highlight"><input type="number" name="net_${idx}" class="dtr-net-input" value="${rowNet.toFixed(2)}" readonly></td>`;
        html += `<td class="td-remarks"><input type="text" name="remarks_${idx}" class="dtr-remarks-input" value="${rec.remarks || ''}" placeholder="Remarks" ${!editModeEnabled ? 'readonly' : ''}></td>`;
        html += `</tr>`;
    });

    // Totals footer with TB5 styling
    html += `</tbody><tfoot><tr class="totals-row" id="totalsRow">`;
    html += `<td class="totals-label" colspan="9">TOTALS:</td>`;
    html += `<td class="tot-work-hrs calc-highlight">${totWorkHrs.toFixed(2)}</td>`;
    html += `<td class="tot-late calc-highlight">${totLateMins > 0 ? totLateMins : '0'}</td>`;
    html += `<td class="tot-ut calc-highlight">${totUtHrs.toFixed(2)}</td>`;
    html += `<td class="tot-ot calc-highlight">${totOtHrs.toFixed(2)}</td>`;
    html += `<td class="tot-absent-ded deduct-highlight">${totAbsentDed > 0 ? peso(totAbsentDed) : '-'}</td>`;
    html += `<td class="tot-late-ded deduct-highlight">${totLateDed > 0.001 ? peso(totLateDed) : '-'}</td>`;
    html += `<td class="tot-ut-ded deduct-highlight">${totUtDed > 0.001 ? peso(totUtDed) : '-'}</td>`;
    html += `<td class="tot-half-ded deduct-highlight">${totHalfDed > 0.001 ? peso(totHalfDed) : '-'}</td>`;
    html += `<td class="tot-ot-pay pay-highlight">${totOtPay > 0.001 ? peso(totOtPay) : '-'}</td>`;
    html += `<td class="tot-govt govt-highlight">${totGovt > 0 ? totGovt.toFixed(2) : '-'}</td>`;
    html += `<td class="tot-net net-highlight"><strong>${peso(totNetSalary)}</strong></td>`;
    html += `<td>-</td>`;
    html += `</tr></tfoot></table></div>`;

    // ====== Payroll Summary ======
    const grossPay = daysWorked * perDay;
    if (comp) {
        sssAmt = parseFloat(comp.sss_contribution) || sssAmt;
        philAmt = parseFloat(comp.philhealth_contribution) || philAmt;
        pagAmt = parseFloat(comp.pagibig_contribution) || pagAmt;
    }
    const govTotal = sssAmt + philAmt + pagAmt;
    const totalAllDeduct = totAbsentDed + totLateDed + totUtDed + totHalfDed + govTotal;
    const netPay = grossPay + totOtPay - totalAllDeduct;

    html += `
    <div class="payroll-summary-card">
        <div class="payroll-summary-header"><i class="fas fa-file-invoice-dollar"></i> Payroll Summary &amp; Deductions</div>
        <div class="payroll-summary-body">
            <table>
                <tbody>
                    <tr><td>Days Office (Worked)</td><td>${daysWorked} days</td></tr>
                    <tr><td>Absent Days</td><td>${totAbsentDays} days</td></tr>
                    <tr><td>Total OT Hours</td><td>${totOtHrs.toFixed(2)} hrs</td></tr>
                    <tr><td>Gross Pay (${daysWorked} × ${peso(perDay)})</td><td>${peso(grossPay)}</td></tr>
                    <tr><td>Absent Deduction</td><td class="val-negative">-${peso(totAbsentDed)}</td></tr>
                    <tr><td>Late Deduction</td><td class="val-negative">-${peso(totLateDed)}</td></tr>
                    <tr><td>Undertime Deduction</td><td class="val-negative">-${peso(totUtDed)}</td></tr>
                    <tr><td>Halfday Deduction</td><td class="val-negative">-${peso(totHalfDed)}</td></tr>
                    <tr><td>OT Pay</td><td class="val-positive">+${peso(totOtPay)}</td></tr>
                    <tr class="section-divider"><td colspan="2"><i class="fas fa-landmark"></i> Government Deductions</td></tr>
                    <tr><td>SSS</td><td class="val-negative">${sssAmt > 0 ? peso(sssAmt) : '0.00'}</td></tr>
                    <tr><td>PhilHealth</td><td class="val-negative">${philAmt > 0 ? peso(philAmt) : '0.00'}</td></tr>
                    <tr><td>Pag-IBIG</td><td class="val-negative">${pagAmt > 0 ? peso(pagAmt) : '0.00'}</td></tr>
                    <tr class="total-row"><td><strong>Total All Deductions</strong></td><td class="val-negative"><strong>-${peso(totalAllDeduct)}</strong></td></tr>
                    <tr class="net-pay-row"><td><strong>NET PAY</strong></td><td><strong>${peso(netPay)}</strong></td></tr>
                </tbody>
            </table>
        </div>
    </div>`;

    return html;
}

/* ====== Recalculate Row on Edit ====== */
function recalculateRow(row) {
    if (!row || !currentEmpInfo) return;
    
    const salary = parseFloat(currentEmpInfo.basic_monthly_salary) || 0;
    const { perDay, perHour, perMin, otRate } = getRates(salary);
    const idx = row.dataset.idx;
    
    // Get time inputs by name (like DTR Calculator)
    const amIn = row.querySelector(`input[name="am_in_${idx}"]`)?.value || '';
    const amOut = row.querySelector(`input[name="am_out_${idx}"]`)?.value || '';
    const pmIn = row.querySelector(`input[name="pm_in_${idx}"]`)?.value || '';
    const pmOut = row.querySelector(`input[name="pm_out_${idx}"]`)?.value || '';
    const otOut = row.querySelector(`input[name="ot_out_${idx}"]`)?.value || '';
    const halfIn = row.querySelector(`input[name="half_in_${idx}"]`)?.value || '';
    const halfOut = row.querySelector(`input[name="half_out_${idx}"]`)?.value || '';
    const isAbsent = row.querySelector(`input[name="absent_${idx}"]`)?.checked || false;
    
    // If absent is checked, clear all time inputs
    if (isAbsent) {
        const amInInput = row.querySelector(`input[name="am_in_${idx}"]`);
        const amOutInput = row.querySelector(`input[name="am_out_${idx}"]`);
        const pmInInput = row.querySelector(`input[name="pm_in_${idx}"]`);
        const pmOutInput = row.querySelector(`input[name="pm_out_${idx}"]`);
        const otOutInput = row.querySelector(`input[name="ot_out_${idx}"]`);
        
        if (amInInput) amInInput.value = '';
        if (amOutInput) amOutInput.value = '';
        if (pmInInput) pmInInput.value = '';
        if (pmOutInput) pmOutInput.value = '';
        if (otOutInput) otOutInput.value = '';
    }
    
    const isHalf = halfIn && halfOut;
    
    // Get late start and end time from editable inputs
    const lateStartInput = document.getElementById('edit_late_start');
    const endTimeInput = document.getElementById('edit_end_time');
    const lateStart = lateStartInput?.value || '07:35';
    const endTime = endTimeInput?.value || '17:00';
    
    // Calculate values using editable late start and end time
    // If absent, zero out ALL hours-related calculations
    const workHrs = isAbsent ? 0 : calculateWorkHours(amIn, amOut, pmIn, pmOut);
    const lateMins = isAbsent ? 0 : calculateLateMins(amIn, lateStart);
    const utHrs = isAbsent ? 0 : calculateUndertime(pmOut, endTime);
    
    // OT calculation (if ot_out is after end time) - also zero if absent
    const otOutMins = parseTimeToMinutes(otOut);
    const endMins = parseTimeToMinutes(endTime);
    const otHrs = isAbsent ? 0 : ((otOutMins && endMins && otOutMins > endMins) ? (otOutMins - endMins) / 60 : 0);
    
    // Deductions
    const absentDed = isAbsent ? perDay : 0;
    const lateDed = isAbsent ? 0 : (lateMins / 60) * perHour;
    const utDed = isAbsent ? 0 : utHrs * perHour;
    const halfDed = (isHalf && !isAbsent) ? (perDay / 2) : 0; // No halfday deduction if absent
    const otPay = isAbsent ? 0 : otHrs * otRate;
    
    // Get govt deduction from input field
    const govtVal = parseFloat(row.querySelector(`input[name="govt_${idx}"]`)?.value) || 0;
    
    let rowNet;
    if (isAbsent) {
        rowNet = 0 - absentDed - govtVal;
    } else {
        rowNet = (workHrs * perHour) - lateDed - utDed - halfDed + otPay - govtVal;
    }
    
    // Update calculation input values (like DTR Calculator)
    const workHrsInput = row.querySelector(`input[name="work_hrs_${idx}"]`);
    const lateMinsInput = row.querySelector(`input[name="late_mins_${idx}"]`);
    const utHrsInput = row.querySelector(`input[name="ut_hrs_${idx}"]`);
    const otHrsInput = row.querySelector(`input[name="ot_hrs_${idx}"]`);
    const absentDedInput = row.querySelector(`input[name="absent_ded_${idx}"]`);
    const lateDedInput = row.querySelector(`input[name="late_ded_${idx}"]`);
    const utDedInput = row.querySelector(`input[name="ut_ded_${idx}"]`);
    const halfDedInput = row.querySelector(`input[name="half_ded_${idx}"]`);
    const otPayInput = row.querySelector(`input[name="ot_pay_${idx}"]`);
    const netInput = row.querySelector(`input[name="net_${idx}"]`);
    
    if (workHrsInput) workHrsInput.value = workHrs.toFixed(2);
    if (lateMinsInput) lateMinsInput.value = lateMins;
    if (utHrsInput) utHrsInput.value = utHrs.toFixed(2);
    if (otHrsInput) otHrsInput.value = otHrs.toFixed(2);
    if (absentDedInput) absentDedInput.value = absentDed.toFixed(2);
    if (lateDedInput) lateDedInput.value = lateDed.toFixed(2);
    if (utDedInput) utDedInput.value = utDed.toFixed(2);
    if (halfDedInput) halfDedInput.value = halfDed.toFixed(2);
    if (otPayInput) otPayInput.value = otPay.toFixed(2);
    if (netInput) netInput.value = rowNet.toFixed(2);
    
    // Toggle absent row style
    row.classList.toggle('absent-row', isAbsent);
    
    // Recalculate totals
    recalculateTotals();
}

/* ====== Recalculate All Totals ====== */
function recalculateTotals() {
    const table = document.getElementById('dtrEditTable');
    if (!table) return;
    
    const salary = parseFloat(currentEmpInfo?.basic_monthly_salary) || 0;
    const { perDay, perHour, otRate } = getRates(salary);
    
    let totWorkHrs = 0, totLateMins = 0, totUtHrs = 0, totOtHrs = 0;
    let totAbsentDed = 0, totLateDed = 0, totUtDed = 0, totHalfDed = 0;
    let totOtPay = 0, totGovt = 0, totNet = 0;
    
    table.querySelectorAll('tbody tr.dtr-data-row').forEach((row, idx) => {
        // Get values from input fields (like DTR Calculator)
        totWorkHrs += parseFloat(row.querySelector(`input[name="work_hrs_${idx}"]`)?.value) || 0;
        totLateMins += parseInt(row.querySelector(`input[name="late_mins_${idx}"]`)?.value) || 0;
        totUtHrs += parseFloat(row.querySelector(`input[name="ut_hrs_${idx}"]`)?.value) || 0;
        totOtHrs += parseFloat(row.querySelector(`input[name="ot_hrs_${idx}"]`)?.value) || 0;
        totAbsentDed += parseFloat(row.querySelector(`input[name="absent_ded_${idx}"]`)?.value) || 0;
        totLateDed += parseFloat(row.querySelector(`input[name="late_ded_${idx}"]`)?.value) || 0;
        totUtDed += parseFloat(row.querySelector(`input[name="ut_ded_${idx}"]`)?.value) || 0;
        totHalfDed += parseFloat(row.querySelector(`input[name="half_ded_${idx}"]`)?.value) || 0;
        totOtPay += parseFloat(row.querySelector(`input[name="ot_pay_${idx}"]`)?.value) || 0;
        totGovt += parseFloat(row.querySelector(`input[name="govt_${idx}"]`)?.value) || 0;
        totNet += parseFloat(row.querySelector(`input[name="net_${idx}"]`)?.value) || 0;
    });
    
    // Update totals row
    const totalsRow = document.getElementById('totalsRow');
    if (totalsRow) {
        const totCells = totalsRow.querySelectorAll('td');
        // Update total cells (starting after the "TOTALS:" label cell)
        // Format: TOTALS|WorkHrs|Late|UT|OT|AbsDed|LateDed|UTDed|HalfDed|OTPay|Govt|Net|Remarks
        if (totCells[1]) totCells[1].textContent = totWorkHrs.toFixed(2);
        if (totCells[2]) totCells[2].textContent = totLateMins > 0 ? totLateMins : '0';
        if (totCells[3]) totCells[3].textContent = totUtHrs.toFixed(2);
        if (totCells[4]) totCells[4].textContent = totOtHrs.toFixed(2);
        if (totCells[5]) totCells[5].textContent = totAbsentDed > 0 ? peso(totAbsentDed) : '-';
        if (totCells[6]) totCells[6].textContent = totLateDed > 0.001 ? peso(totLateDed) : '-';
        if (totCells[7]) totCells[7].textContent = totUtDed > 0.001 ? peso(totUtDed) : '-';
        if (totCells[8]) totCells[8].textContent = totHalfDed > 0.001 ? peso(totHalfDed) : '-';
        if (totCells[9]) totCells[9].textContent = totOtPay > 0.001 ? peso(totOtPay) : '-';
        if (totCells[10]) totCells[10].textContent = totGovt > 0 ? totGovt.toFixed(2) : '-';
        if (totCells[11]) totCells[11].innerHTML = '<strong>' + peso(totNet) + '</strong>';
    }
}

/* ====== Save DTR Changes ====== */
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
        
        // Get inputs by name attribute (like DTR Calculator)
        const amIn = row.querySelector(`input[name="am_in_${idx}"]`)?.value || '';
        const amOut = row.querySelector(`input[name="am_out_${idx}"]`)?.value || '';
        const pmIn = row.querySelector(`input[name="pm_in_${idx}"]`)?.value || '';
        const pmOut = row.querySelector(`input[name="pm_out_${idx}"]`)?.value || '';
        const otOut = row.querySelector(`input[name="ot_out_${idx}"]`)?.value || '';
        const halfIn = row.querySelector(`input[name="half_in_${idx}"]`)?.value || '';
        const halfOut = row.querySelector(`input[name="half_out_${idx}"]`)?.value || '';
        const isAbsent = row.querySelector(`input[name="absent_${idx}"]`)?.checked ? 1 : 0;
        const workHrs = parseFloat(row.querySelector(`input[name="work_hrs_${idx}"]`)?.value) || 0;
        const lateMins = parseInt(row.querySelector(`input[name="late_mins_${idx}"]`)?.value) || 0;
        const utHrs = parseFloat(row.querySelector(`input[name="ut_hrs_${idx}"]`)?.value) || 0;
        const otHrs = parseFloat(row.querySelector(`input[name="ot_hrs_${idx}"]`)?.value) || 0;
        const remarks = row.querySelector(`input[name="remarks_${idx}"]`)?.value || '';
        
        if (amIn || amOut || pmIn || pmOut || isAbsent) hasValidData = true;
        
        records.push({
            id: parseInt(recordId),
            am_time_in: amIn || null,
            am_time_out: amOut || null,
            pm_time_in: pmIn || null,
            pm_time_out: pmOut || null,
            ot_time_out: otOut || null,
            halfday_in: halfIn || null,
            halfday_out: halfOut || null,
            is_absent: isAbsent,
            total_work_hours: workHrs,
            late_minutes: lateMins,
            undertime_hours: utHrs,
            daily_ot_hours: otHrs,
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
    
    // Get rate fields to save
    const basicSalary = parseFloat(document.getElementById('edit_basic_salary')?.value) || 0;
    const perDay = parseFloat(document.getElementById('edit_per_day')?.value) || 0;
    const lateStart = document.getElementById('edit_late_start')?.value || '07:35';
    const endTime = document.getElementById('edit_end_time')?.value || '17:00';
    
    const payload = {
        employee_id: currentEmployeeId,
        period_id: currentPeriodId,
        records: records,
        // Rate fields
        basic_salary: basicSalary,
        per_day: perDay,
        late_start: lateStart,
        end_time: endTime
    };
    
    console.log('Saving DTR records:', payload);
    
    fetch('update_dtr_records.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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
            await showCustomAlert(
                `<strong>${records.length} DTR record(s)</strong> have been saved successfully for <strong>${currentEmpInfo?.full_name}</strong>.`,
                'Save Successful',
                'success'
            );
            editModeEnabled = false;
            updateEditModeButton();
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
    let url = `export_dtr_data.php?employee_id=${currentEmployeeId}`;
    if (currentPeriodId) url += `&period_id=${currentPeriodId}`;
    window.location.href = url;
}

/* ====== Print - Modified to export Excel template ====== */
function printEmployeeDTR() {
    // Export to Excel template instead of printing HTML
    exportCurrentDTR();
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
    if (printBtn) printBtn.style.display = 'none';
    if (editBtn) editBtn.style.display = 'none';
    if (saveBtn) saveBtn.style.display = 'none';
    updateEditModeButton();
}

// Close on Escape / outside click
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEmployeeDTRModal(); });
document.getElementById('employeeDTRModal')?.addEventListener('click', function(e) { if (e.target === this) closeEmployeeDTRModal(); });
</script>

<?php require_once 'include/footer.php'; ?>
