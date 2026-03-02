<?php
$page_title = "Generate Payroll";
include 'include/header.php';

// Database connection
require_once '../config/database.php';
require_once '../config/notifications_helper.php';
require_once '../config/account_logs_helper.php';
$pdo = getDBConnection();

// Fetch all active employees
$employees_query = "SELECT id, employee_code, full_name, position, department, basic_monthly_salary 
                    FROM employees 
                    WHERE status = 'active' 
                    ORDER BY full_name ASC";
$employees_result = $pdo->query($employees_query)->fetchAll();

// Fetch all payroll periods
$periods_query = "SELECT id, period_name, start_date, end_date, status 
                  FROM payroll_periods 
                  ORDER BY start_date DESC";    
$periods_result = $pdo->query($periods_query)->fetchAll();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // CSRF check
    require_once '../config/csrf.php';
    if (!validateCSRFToken()) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
        exit;
    }
    
    if ($_POST['action'] === 'get_employee_data') {
        $employee_id = intval($_POST['employee_id']);
        $period_id = isset($_POST['period_id']) ? intval($_POST['period_id']) : null;
        
        // Get employee details
        $emp_query = "SELECT * FROM employees WHERE id = ?";
        $stmt = $pdo->prepare($emp_query);
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch();
        
        $response = ['success' => true, 'employee' => $employee];
        
        // Get DTR summary if period is selected
        if ($period_id) {
            $dtr_query = "SELECT * FROM vw_employee_dtr_summary 
                         WHERE employee_id = ? AND payroll_period_id = ?";
            $stmt = $pdo->prepare($dtr_query);
            $stmt->execute([$employee_id, $period_id]);
            $dtr_summary = $stmt->fetch();
            $response['dtr_summary'] = $dtr_summary;
        }
        
        echo json_encode($response);
        exit;
    }
    
    if ($_POST['action'] === 'compute_payroll') {
        $employee_id = intval($_POST['employee_id']);
        $period_id = intval($_POST['period_id']);
        $user_id = $_SESSION['user_id'];
        
        try {
            // Call stored procedure to compute payroll
            $compute_query = "CALL sp_compute_payroll(?, ?, ?)";
            $stmt = $pdo->prepare($compute_query);
            $stmt->execute([$employee_id, $period_id, $user_id]);
            
            // Fetch computed payroll
            $stmt->closeCursor();
            $payroll_query = "SELECT * FROM vw_payroll_summary 
                             WHERE employee_id = ? AND payroll_period_id = ?";
            $stmt = $pdo->prepare($payroll_query);
            $stmt->execute([$employee_id, $period_id]);
            $payroll = $stmt->fetch();
            
            echo json_encode(['success' => true, 'payroll' => $payroll]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'save_payroll') {
        $employee_id = intval($_POST['employee_id']);
        $period_id = intval($_POST['period_id']);
        $basic_salary = floatval($_POST['basic_salary']);
        $per_day = floatval($_POST['per_day']);
        $per_hour = floatval($_POST['per_hour']);
        $per_minute = floatval($_POST['per_minute']);
        $dtr_data = json_decode($_POST['dtr_data'], true);
        $sss = floatval($_POST['sss']);
        $philhealth = floatval($_POST['philhealth']);
        $pagibig = floatval($_POST['pagibig']);
        $cash_advance = floatval($_POST['cash_advance']);
        $user_id = $_SESSION['user_id'];
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Get period dates
            $period_query = "SELECT start_date, end_date FROM payroll_periods WHERE id = ?";
            $stmt = $pdo->prepare($period_query);
            $stmt->execute([$period_id]);
            $period = $stmt->fetch();
            $start_date = new DateTime($period['start_date']);
            
            // Initialize totals
            $total_work_hours = 0;
            $total_late_minutes = 0;
            $total_late_hours = 0;
            $total_undertime_hours = 0;
            $total_ot_hours = 0;
            $total_absent_days = 0;
            $total_work_days = 0;
            
            // Process each day
            foreach ($dtr_data as $day_data) {
                $day_num = intval($day_data['day']);
                $date = clone $start_date;
                $date->modify('+' . ($day_num - 1) . ' days');
                $date_str = $date->format('Y-m-d');
                
                $is_absent = intval($day_data['is_absent']);
                $work_hours = floatval($day_data['work_hours']);
                $late_mins = intval($day_data['late_mins']);
                $undertime = floatval($day_data['undertime']);
                $ot_hours = floatval($day_data['ot_hours']);
                
                // Update totals
                if ($is_absent) {
                    $total_absent_days++;
                } else {
                    if ($work_hours > 0 || $ot_hours > 0) {
                        $total_work_days++;
                    }
                    $total_work_hours += $work_hours;
                    $total_late_minutes += $late_mins;
                    $total_undertime_hours += $undertime;
                    $total_ot_hours += $ot_hours;
                }
                
                // Check if DTR record exists
                $check_query = "SELECT id FROM dtr_records WHERE employee_id = ? AND date = ?";
                $stmt = $pdo->prepare($check_query);
                $stmt->execute([$employee_id, $date_str]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update existing record
                    $update_query = "UPDATE dtr_records SET 
                                    am_time_in = ?, am_time_out = ?,
                                    pm_time_in = ?, pm_time_out = ?,
                                    ot_time_in = ?, ot_time_out = ?,
                                    halfday_in = ?, halfday_out = ?,
                                    is_absent = ?, total_work_hours = ?,
                                    late_minutes = ?, late_hours = ?,
                                    undertime_hours = ?, daily_ot_hours = ?,
                                    payroll_period_id = ?
                                    WHERE id = ?";
                    $stmt = $pdo->prepare($update_query);
                    $late_hours = $late_mins / 60;
                    $stmt->execute([
                        $day_data['am_in'], $day_data['am_out'],
                        $day_data['pm_in'], $day_data['pm_out'],
                        $day_data['ot_in'], $day_data['ot_out'],
                        $day_data['halfday_in'], $day_data['halfday_out'],
                        $is_absent, $work_hours,
                        $late_mins, $late_hours,
                        $undertime, $ot_hours,
                        $period_id, $existing['id']
                    ]);
                } else {
                    // Insert new record
                    $insert_query = "INSERT INTO dtr_records 
                                    (employee_id, date, am_time_in, am_time_out,
                                     pm_time_in, pm_time_out, ot_time_in, ot_time_out,
                                     halfday_in, halfday_out, is_absent, total_work_hours,
                                     late_minutes, late_hours, undertime_hours, daily_ot_hours,
                                     payroll_period_id)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($insert_query);
                    $late_hours = $late_mins / 60;
                    $stmt->execute([
                        $employee_id, $date_str,
                        $day_data['am_in'], $day_data['am_out'],
                        $day_data['pm_in'], $day_data['pm_out'],
                        $day_data['ot_in'], $day_data['ot_out'],
                        $day_data['halfday_in'], $day_data['halfday_out'],
                        $is_absent, $work_hours,
                        $late_mins, $late_hours,
                        $undertime, $ot_hours,
                        $period_id
                    ]);
                }
            }
            
            // Calculate late hours from minutes
            $total_late_hours = $total_late_minutes / 60;
            
            // Calculate earnings
            $basic_pay = $basic_salary;
            $ot_pay = $total_ot_hours * $per_hour * 1.25; // OT is 1.25x
            $total_earnings = $basic_pay + $ot_pay;
            
            // Calculate deductions
            $late_deduction = $total_late_minutes * $per_minute;
            $undertime_deduction = $total_undertime_hours * $per_hour;
            $absent_deduction = $total_absent_days * $per_day;
            
            $total_deductions = $late_deduction + $undertime_deduction + $absent_deduction
                              + $sss + $philhealth + $pagibig + $cash_advance;
            
            $net_pay = $total_earnings - $total_deductions;
            
            // Check if payroll computation exists
            $check_payroll = "SELECT id FROM payroll_computations 
                             WHERE employee_id = ? AND payroll_period_id = ?";
            $stmt = $pdo->prepare($check_payroll);
            $stmt->execute([$employee_id, $period_id]);
            $existing_payroll = $stmt->fetch();
            
            if ($existing_payroll) {
                // Update existing payroll
                $update_payroll = "UPDATE payroll_computations SET
                                  basic_monthly_salary = ?, per_day_rate = ?, 
                                  per_hour_rate = ?, per_minute_rate = ?,
                                  total_work_days = ?, total_work_hours = ?,
                                  total_late_hours = ?, total_undertime_hours = ?,
                                  total_absent_days = ?, total_ot_hours = ?,
                                  basic_pay = ?, ot_pay = ?, total_earnings = ?,
                                  late_deduction = ?, undertime_deduction = ?,
                                  absent_deduction = ?, sss_contribution = ?,
                                  philhealth_contribution = ?, pagibig_contribution = ?,
                                  cash_advance = ?, total_deductions = ?,
                                  net_pay = ?, computed_by = ?, computed_at = NOW()
                                  WHERE id = ?";
                $stmt = $pdo->prepare($update_payroll);
                $stmt->execute([
                    $basic_salary, $per_day, $per_hour, $per_minute,
                    $total_work_days, $total_work_hours,
                    $total_late_hours, $total_undertime_hours,
                    $total_absent_days, $total_ot_hours,
                    $basic_pay, $ot_pay, $total_earnings,
                    $late_deduction, $undertime_deduction, $absent_deduction,
                    $sss, $philhealth, $pagibig, $cash_advance,
                    $total_deductions, $net_pay,
                    $user_id, $existing_payroll['id']
                ]);
            } else {
                // Insert new payroll computation
                $insert_payroll = "INSERT INTO payroll_computations
                                  (employee_id, payroll_period_id,
                                   basic_monthly_salary, per_day_rate, per_hour_rate, per_minute_rate,
                                   total_work_days, total_work_hours, total_late_hours,
                                   total_undertime_hours, total_absent_days, total_ot_hours,
                                   basic_pay, ot_pay, total_earnings,
                                   late_deduction, undertime_deduction, absent_deduction,
                                   sss_contribution, philhealth_contribution, pagibig_contribution,
                                   cash_advance, total_deductions, net_pay,
                                   computed_by, computed_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($insert_payroll);
                $stmt->execute([
                    $employee_id, $period_id,
                    $basic_salary, $per_day, $per_hour, $per_minute,
                    $total_work_days, $total_work_hours, $total_late_hours,
                    $total_undertime_hours, $total_absent_days, $total_ot_hours,
                    $basic_pay, $ot_pay, $total_earnings,
                    $late_deduction, $undertime_deduction, $absent_deduction,
                    $sss, $philhealth, $pagibig, $cash_advance,
                    $total_deductions, $net_pay, $user_id
                ]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Get employee name for notification
            $emp_query = "SELECT full_name FROM employees WHERE id = ?";
            $emp_stmt = $pdo->prepare($emp_query);
            $emp_stmt->execute([$employee_id]);
            $employee_data = $emp_stmt->fetch();
            $employee_name = $employee_data['full_name'];
            
            // Get period name for notification
            $period_query = "SELECT period_name FROM payroll_periods WHERE id = ?";
            $period_stmt = $pdo->prepare($period_query);
            $period_stmt->execute([$period_id]);
            $period_data = $period_stmt->fetch();
            $period_name = $period_data['period_name'];
            
            // Get staff name
            $staff_name = $_SESSION['full_name'] ?? 'Staff';
            
            // Notify all admins
            $notification_title = "New Payslip Generated";
            $notification_message = "{$staff_name} generated a payslip for {$employee_name} ({$period_name})";
            notifyAdmins(
                $notification_title,
                $notification_message,
                'success',
                'fa-file-invoice-dollar',
                'Generatepayroll.php'
            );
            
            // Log payroll generation
            logPayrollAction(
                $_SESSION['user_id'],
                $_SESSION['username'],
                'Generated payslip',
                $employee_name,
                $period_name,
                $net_pay,
                $pdo
            );
            
            echo json_encode(['success' => true, 'message' => 'Payroll saved successfully']);
        } catch (Exception $e) {
            // Rollback on error
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
?>

<?php include 'include/sidebar.php'; ?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-left">
                <div class="page-title-row">
                    <div class="page-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="page-title-text">
                        <h1>Generate Payroll</h1>
                        <div class="page-breadcrumb">
                            <a href="dashboard_staff.php"><i class="fas fa-home"></i> Home</a>
                            <span class="page-breadcrumb-separator">/</span>
                            <span>Generate Payroll</span>
                        </div>
                    </div>
                </div>
                <p class="page-subtitle">Compute payroll for employees based on DTR records</p>
            </div>
        </div>
    </div>

    <div class="old-page-header" style="display:none">
    <div class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1><i class="fas fa-calculator"></i> Generate Payroll</h1>
                <p>Compute payroll for employees based on DTR records</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location.href='payroll_list.php'">
                    <i class="fas fa-list"></i> View All Payrolls
                </button>
            </div>
        </div>
    </div>

    <!-- Employee Selection Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-check"></i> Select Employee</h3>
            <p>Click on an employee card to open the DTR Calculator</p>
        </div>
        <div class="card-body">
            <div class="employees-grid">
                <?php 
                foreach ($employees_result as $emp): 
                ?>
                    <div class="employee-card" onclick="selectEmployee(<?php echo $emp['id']; ?>, '<?php echo addslashes(htmlspecialchars($emp['full_name'])); ?>', '<?php echo htmlspecialchars($emp['employee_code']); ?>', '<?php echo $emp['basic_monthly_salary']; ?>')">
                        <div class="emp-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="emp-info">
                            <h4><?php echo htmlspecialchars($emp['full_name']); ?></h4>
                            <p class="emp-code"><?php echo htmlspecialchars($emp['employee_code']); ?></p>
                            <p class="emp-dept"><?php echo htmlspecialchars($emp['department'] ?? 'No Department'); ?></p>
                            <p class="emp-salary">₱<?php echo number_format($emp['basic_monthly_salary'], 2); ?>/month</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- DTR Calculator Modal -->
<div id="dtrModal" class="modal">
    <div class="modal-content modal-fullwidth">
        <div class="modal-header">
            <div class="modal-title-section">
                <h2><i class="fas fa-calculator"></i> DTR CALCULATOR</h2>
                <p id="modalEmployeeName">EMPLOYEE NAME: <span class="highlight">-</span></p>
                <p id="modalPeriodDates">Period: <span class="highlight">-</span></p>
            </div>
            <button class="modal-close" onclick="closeDTRModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Payroll Period Selection -->
            <div class="period-select-section">
                <label for="modalPeriodSelect"><i class="fas fa-calendar-alt"></i> Select Payroll Period:</label>
                <select id="modalPeriodSelect" class="form-control" onchange="loadPeriodDates()">
                    <option value="">-- Select Period --</option>
                    <?php 
                    foreach ($periods_result as $period): 
                    ?>
                        <option value="<?php echo $period['id']; ?>" 
                                data-start="<?php echo $period['start_date']; ?>"
                                data-end="<?php echo $period['end_date']; ?>"
                                data-name="<?php echo htmlspecialchars($period['period_name']); ?>">
                            <?php echo htmlspecialchars($period['period_name']); ?>
                            (<?php echo date('M d', strtotime($period['start_date'])); ?> - 
                             <?php echo date('M d, Y', strtotime($period['end_date'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" id="selectedEmployeeId">
            <input type="hidden" id="selectedEmployeeName">
            <input type="hidden" id="selectedEmployeeCode">
            
            <!-- DTR Calculator Form -->
            <div class="dtr-calculator" id="dtrCalculatorForm" style="display: none;">
                <!-- Top Input Section -->
                <div class="top-inputs">
                    <div class="input-row">
                        <label>INPUT BASIC MONTHLY SALARY HERE:</label>
                        <input type="number" id="basicSalary" class="calc-input" step="0.01" value="13000" onchange="calculateRates()">
                    </div>
                    <div class="calc-row">
                        <div class="calc-item">
                            <span class="calc-label">PER/DAY:</span>
                            <span class="calc-value" id="perDay">₱0.00</span>
                        </div>
                        <div class="calc-item">
                            <span class="calc-label">PER/MIN:</span>
                            <span class="calc-value" id="perMin">₱0.0000</span>
                        </div>
                        <div class="calc-item">
                            <span class="calc-label">PER/HOUR:</span>
                            <span class="calc-value" id="perHour">₱0.00</span>
                        </div>
                    </div>
                </div>

                <!-- DTR Table -->
                <div class="dtr-table-container">
                    <table class="dtr-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="col-date">MO/YR<br>DATE</th>
                                <th colspan="2" class="col-group">AM</th>
                                <th colspan="2" class="col-group">PM</th>
                                <th rowspan="2" class="col-checkbox">ABSENT</th>
                                <th colspan="2" class="col-group">OT</th>
                                <th colspan="2" class="col-group">HALFDAY</th>
                                <th rowspan="2" class="col-calc">TOT.WORK<br>HOURS</th>
                                <th rowspan="2" class="col-calc">(in mins)<br>LATE</th>
                                <th rowspan="2" class="col-calc">(in mins)<br>UNDERTM</th>
                                <th rowspan="2" class="col-calc">(in hours)<br>OT</th>
                                <th rowspan="2" class="col-calc">(in days)<br>ABSENT</th>
                                <th rowspan="2" class="col-deduct">(ABSENT)<br>DEDUCT</th>
                                <th rowspan="2" class="col-deduct">LATE/MIN<br>DEDUCT</th>
                                <th rowspan="2" class="col-deduct">UNDERTIM<br>DEDUCT</th>
                                <th rowspan="2" class="col-deduct">HALFDAY<br>DEDUCT</th>
                                <th rowspan="2" class="col-deduct">OT<br>PAYMENT</th>
                                <th rowspan="2" class="col-deduct">CA ADV.+<br>GOV'T.</th>
                                <th rowspan="2" class="col-remarks">REMARKS</th>
                            </tr>
                            <tr>
                                <th class="col-time">IN</th>
                                <th class="col-time">OUT</th>
                                <th class="col-time">IN</th>
                                <th class="col-time">OUT</th>
                                <th class="col-time">IN</th>
                                <th class="col-time">OUT</th>
                                <th class="col-time">IN</th>
                                <th class="col-time">OUT</th>
                            </tr>
                        </thead>
                        <tbody id="dtrTableBody">
                            <!-- Rows will be generated by JavaScript -->
                        </tbody>
                        <tfoot>
                            <tr class="totals-row">
                                <td colspan="10" class="text-right"><strong>TOTALS:</strong></td>
                                <td id="totalWorkHours">0.00</td>
                                <td id="totalLateMins">0</td>
                                <td id="totalUndertimeMins">0</td>
                                <td id="totalOT">0.00</td>
                                <td id="totalAbsentDays">0</td>
                                <td id="totalAbsentDeduct">₱0.00</td>
                                <td id="totalLateDeduct">₱0.00</td>
                                <td id="totalUndertimeDeduct">₱0.00</td>
                                <td id="totalHalfdayDeduct">₱0.00</td>
                                <td id="totalOTPayment">₱0.00</td>
                                <td id="totalOtherDeduct">₱0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Bottom Calculations -->
                <div class="bottom-calculations">
                    <div class="calc-section">
                        <h4>Government Deductions & Cash Advance</h4>
                        <div class="deduction-inputs">
                            <div class="deduct-item">
                                <label>SSS:</label>
                                <input type="number" id="sssDeduct" class="calc-input-small" step="0.01" value="0.00" onchange="calculateTotals()">
                            </div>
                            <div class="deduct-item">
                                <label>PhilHealth:</label>
                                <input type="number" id="philhealthDeduct" class="calc-input-small" step="0.01" value="0.00" onchange="calculateTotals()">
                            </div>
                            <div class="deduct-item">
                                <label>Pag-IBIG:</label>
                                <input type="number" id="pagibigDeduct" class="calc-input-small" step="0.01" value="0.00" onchange="calculateTotals()">
                            </div>
                            <div class="deduct-item">
                                <label>Cash Advance:</label>
                                <input type="number" id="cashAdvance" class="calc-input-small" step="0.01" value="0.00" onchange="calculateTotals()">
                            </div>
                        </div>
                    </div>

                    <div class="net-salary-section">
                        <div class="net-display">
                            <span class="net-label">TOTAL NET SALARY:</span>
                            <span class="net-value" id="totalNetSalary">₱0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDTRModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-success" onclick="savePayroll()">
                        <i class="fas fa-save"></i> Save Payroll
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.page-header {
    margin-bottom: 30px;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.header-text h1 {
    font-size: 28px;
    color: var(--text-primary);
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-text p {
    color: var(--text-secondary);
    margin: 0;
}

.card {
    background: var(--bg-primary);
    border-radius: 12px;
    box-shadow: var(--shadow);
    margin-bottom: 24px;
}

.card-header {
    padding: 24px;
    border-bottom: 1px solid var(--border-color);
}

.card-header h3 {
    font-size: 20px;
    color: var(--text-primary);
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header p {
    color: var(--text-secondary);
    margin: 0;
    font-size: 14px;
}

.card-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 14px;
    color: var(--text-primary);
    background-color: var(--bg-secondary);
    transition: var(--transition);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    background-color: var(--bg-primary);
}

.select-employee {
    font-size: 15px;
    font-weight: 500;
}

.employee-info-card {
    margin-top: 24px;
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 10px;
    border: 2px dashed var(--border-color);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    font-weight: 600;
}

.info-value {
    font-size: 16px;
    color: var(--text-primary);
    font-weight: 500;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-hover);
    transform: translateY(-2px);
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--bg-hover);
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-generate {
    width: 100%;
    justify-content: center;
    font-size: 16px;
    padding: 14px;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    overflow-y: auto;
    padding: 20px;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--bg-primary);
    border-radius: 12px;
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideDown 0.3s ease;
}

.modal-large {
    max-width: 1100px;
}

:root {
    --dtr-orange: #FF8C00;
    --dtr-green: #00A651;
    --dtr-blue: #007BFF;
    --dtr-red: #DC3545;
    --dtr-yellow: #FFC107;
    --dtr-black: #000000;
}

/* Employee Grid */
.employees-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.employee-card {
    background: var(--bg-secondary);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    gap: 15px;
    align-items: center;
}

.employee-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
    border-color: var(--primary-color);
}

.emp-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    flex-shrink: 0;
}

.emp-info h4 {
    margin: 0 0 5px 0;
    font-size: 16px;
    color: var(--text-primary);
}

.emp-info p {
    margin: 3px 0;
    font-size: 12px;
    color: var(--text-secondary);
}

.emp-code {
    font-weight: 600;
    color: var(--primary-color) !important;
}

.emp-salary {
    color: #10b981 !important;
    font-weight: 600;
}

/* Modal Fullwidth */
.modal-fullwidth {
    max-width: 98%;
    width: 98%;
    max-height: 95vh;
}

.modal-title-section {
    flex: 1;
}

.modal-title-section h2 {
    margin: 0 0 8px 0;
    font-size: 24px;
    color: var(--dtr-orange);
    font-weight: bold;
}

.modal-title-section p {
    margin: 4px 0;
    font-size: 14px;
    color: var(--text-primary);
}

.highlight {
    color: var(--primary-color);
    font-weight: bold;
}

/* Period Selection */
.period-select-section {
    margin-bottom: 20px;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: 8px;
}

.period-select-section label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
}

.period-select-section .form-control {
    width: 100%;
    max-width: 500px;
}

/* Top Inputs */
.top-inputs {
    background: var(--dtr-orange);
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.input-row {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 12px;
}

.input-row label {
    font-weight: bold;
    font-size: 14px;
}

.calc-input {
    width: 200px;
    padding: 8px 12px;
    border: 2px solid white;
    border-radius: 6px;
    font-size: 16px;
    font-weight: bold;
}

.calc-row {
    display: flex;
    gap: 30px;
}

.calc-item {
    display: flex;
    gap: 10px;
    align-items: center;
}

.calc-label {
    font-weight: bold;
    font-size: 13px;
}

.calc-value {
    background: white;
    color: var(--dtr-black);
    padding: 6px 15px;
    border-radius: 6px;
    font-weight: bold;
    min-width: 120px;
    text-align: right;
}

/* DTR Table */
.dtr-table-container {
    overflow-x: auto;
    margin: 20px 0;
    border: 2px solid var(--border-color);
    border-radius: 8px;
}

.dtr-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    background: var(--bg-primary);
}

.dtr-table thead {
    background: var(--dtr-orange);
    color: white;
    position: sticky;
    top: 0;
    z-index: 10;
}

.dtr-table th {
    padding: 8px 4px;
    border: 1px solid white;
    font-weight: bold;
    text-align: center;
    font-size: 10px;
}

.dtr-table td {
    padding: 4px;
    border: 1px solid var(--border-color);
    text-align: center;
}

.col-date {
    width: 60px;
    background: var(--dtr-green);
}

.col-group {
    background: var(--dtr-blue);
}

.col-time {
    width: 50px;
}

.col-checkbox {
    width: 50px;
    background: var(--dtr-red);
}

.col-calc {
    width: 60px;
    background: var(--dtr-yellow);
    color: var(--dtr-black);
}

.col-deduct {
    width: 70px;
    background: #E8E8E8;
}

.col-remarks {
    width: 100px;
}

.dtr-table tbody tr:nth-child(even) {
    background: var(--bg-secondary);
}

.dtr-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.1);
}

.dtr-table input[type="time"],
.dtr-table input[type="text"] {
    width: 100%;
    padding: 4px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 11px;
    text-align: center;
}

.dtr-table input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.totals-row {
    background: var(--dtr-yellow) !important;
    font-weight: bold;
}

.totals-row td {
    padding: 10px 4px;
    font-size: 12px;
}

.text-right {
    text-align: right;
    padding-right: 10px;
}

/* Bottom Calculations */
.bottom-calculations {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.calc-section {
    background: var(--bg-secondary);
    padding: 20px;
    border-radius: 8px;
    border: 2px solid var(--border-color);
}

.calc-section h4 {
    margin: 0 0 15px 0;
    color: var(--text-primary);
    font-size: 16px;
}

.deduction-inputs {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.deduct-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.deduct-item label {
    font-weight: 600;
    font-size: 13px;
    color: var(--text-primary);
}

.calc-input-small {
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 14px;
}

.net-salary-section {
    background: linear-gradient(135deg, var(--dtr-green), #008844);
    padding: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.net-display {
    text-align: center;
    color: white;
}

.net-label {
    display: block;
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 10px;
}

.net-value {
    display: block;
    font-size: 36px;
    font-weight: bold;
}

/* Responsive */
@media (max-width: 1200px) {
    .employees-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
    
    .dtr-table {
        font-size: 10px;
    }
    
    .bottom-calculations {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .modal-fullwidth {
        max-width: 100%;
        width: 100%;
    }
    
    .employees-grid {
        grid-template-columns: 1fr;
    }
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 24px;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: var(--transition);
}

.modal-close:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.modal-body {
    padding: 24px;
}

.form-step {
    display: none;
}

.form-step.active {
    display: block;
}

.form-step h3 {
    font-size: 20px;
    color: var(--text-primary);
    margin-bottom: 24px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border-color);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.required {
    color: #ef4444;
}

/* DTR Summary */
.dtr-summary {
    margin: 24px 0;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 10px;
    border: 1px solid var(--border-color);
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.summary-icon.bg-primary { background: var(--primary-color); }
.summary-icon.bg-success { background: #10b981; }
.summary-icon.bg-warning { background: #f59e0b; }
.summary-icon.bg-danger { background: #ef4444; }
.summary-icon.bg-info { background: #3b82f6; }
.summary-icon.bg-secondary { background: #6b7280; }

.summary-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.summary-label {
    font-size: 12px;
    color: var(--text-secondary);
    font-weight: 500;
}

.summary-value {
    font-size: 24px;
    color: var(--text-primary);
    font-weight: 700;
}

/* Payroll Result */
.payroll-result {
    margin: 24px 0;
}

.result-section {
    margin-bottom: 24px;
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 10px;
}

.result-section h4 {
    font-size: 16px;
    color: var(--text-primary);
    margin: 0 0 16px 0;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.result-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
}

.result-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--bg-primary);
    border-radius: 6px;
}

.result-item.highlight {
    background: var(--bg-hover);
    border: 1px solid var(--border-color);
}

.result-label {
    font-size: 14px;
    color: var(--text-secondary);
}

.result-value {
    font-size: 16px;
    color: var(--text-primary);
    font-weight: 600;
}

.text-success {
    color: #10b981 !important;
}

.text-danger {
    color: #ef4444 !important;
}

.highlight-section {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
    color: white;
}

.highlight-section h4 {
    color: white;
    border-bottom-color: rgba(255, 255, 255, 0.3);
}

.net-pay-display {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
}

.net-pay-label {
    font-size: 18px;
    font-weight: 600;
    color: white;
}

.net-pay-value {
    font-size: 32px;
    font-weight: 700;
    color: white;
}

@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .info-grid,
    .summary-grid,
    .result-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        max-width: 100%;
    }
}
</style>

<script src="../assets/js/dashboard.js"></script>
<script>
let currentEmployeeId = null;
let currentEmployeeName = '';
let currentEmployeeCode = '';
let currentBasicSalary = 0;

// DTR Calculator Functions
function selectEmployee(id, name, code, salary) {
    currentEmployeeId = id;
    currentEmployeeName = name;
    currentEmployeeCode = code;
    currentBasicSalary = parseFloat(salary);
    
    // Open modal
    document.getElementById('dtrModal').classList.add('show');
    
    // Set employee info
    const nameSpan = document.querySelector('#modalEmployeeName .highlight');
    if (nameSpan) nameSpan.textContent = name;
    
    // Set basic salary
    document.getElementById('basicSalary').value = salary;
    calculateRates();
}

function closeDTRModal() {
    document.getElementById('dtrModal').classList.remove('show');
    document.getElementById('dtrCalculatorForm').style.display = 'none';
    document.getElementById('modalPeriodSelect').selectedIndex = 0;
    document.getElementById('dtrTableBody').innerHTML = '';
    
    currentEmployeeId = null;
    currentEmployeeName = '';
    currentEmployeeCode = '';
    currentBasicSalary = 0;
}

// Load period dates and generate DTR rows
function loadPeriodDates() {
    const periodSelect = document.getElementById('modalPeriodSelect');
    const selectedOption = periodSelect.options[periodSelect.selectedIndex];
    
    if (!selectedOption || !selectedOption.value) {
        document.getElementById('dtrCalculatorForm').style.display = 'none';
        document.getElementById('dtrTableBody').innerHTML = '';
        return;
    }
    
    const startDate = selectedOption.getAttribute('data-start');
    const endDate = selectedOption.getAttribute('data-end');
    const periodName = selectedOption.getAttribute('data-name');
    
    // Update period display
    const periodSpan = document.querySelector('#modalPeriodDates .highlight');
    if (periodSpan) {
        periodSpan.textContent = `${periodName} (${startDate} to ${endDate})`;
    }
    
    if (startDate && endDate) {
        generateDTRRows(startDate, endDate);
        document.getElementById('dtrCalculatorForm').style.display = 'block';
    }
}

// Generate DTR table rows based on date range
function generateDTRRows(startDate, endDate) {
    const tbody = document.getElementById('dtrTableBody');
    tbody.innerHTML = '';
    
    const start = new Date(startDate);
    const end = new Date(endDate);
    let dayNum = 1;
    
    for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
        const dayName = date.toLocaleDateString('en-US', { weekday: 'short' });
        const dateDisplay = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="date-cell">${dateDisplay}<br><small>${dayName}</small></td>
            <td><input type="time" class="time-input" id="amIn_${dayNum}" data-day="${dayNum}" onchange="calculateRow(${dayNum})"></td>
            <td><input type="time" class="time-input" id="amOut_${dayNum}" data-day="${dayNum}" onchange="calculateRow(${dayNum})"></td>
            <td><input type="time" class="time-input" id="pmIn_${dayNum}" data-day="${dayNum}" onchange="calculateRow(${dayNum})"></td>
            <td><input type="time" class="time-input" id="pmOut_${dayNum}" data-day="${dayNum}" onchange="calculateRow(${dayNum})"></td>
            <td><input type="checkbox" class="absent-checkbox" id="absent_${dayNum}" data-day="${dayNum}" onchange="calculateRow(${dayNum})"></td>
            <td><input type="time" class="time-input" id="otIn_${dayNum}" data-day="${dayNum}" onchange="calculateRow(${dayNum})"></td>
            <td><input type="time" class="time-input" id="otOut_${dayNum}" data-day="${dayNum}" onchange="calculateRow(${dayNum})"></td>
            <td><input type="time" class="time-input" id="halfdayIn_${dayNum}" data-day="${dayNum}" onchange="calculateRow(${dayNum})"></td>
            <td><input type="time" class="time-input" id="halfdayOut_${dayNum}" data-day="${dayNum}" onchange="calculateRow(${dayNum})"></td>
            <td class="calc-cell" id="workHours_${dayNum}">0.00</td>
            <td class="calc-cell" id="lateMins_${dayNum}">0</td>
            <td class="calc-cell" id="undertimeMins_${dayNum}">0</td>
            <td class="calc-cell" id="otHours_${dayNum}">0.00</td>
            <td class="calc-cell" id="absentDays_${dayNum}">0</td>
            <td class="calc-cell" id="absentDeduct_${dayNum}">₱0.00</td>
            <td class="calc-cell" id="lateDeduct_${dayNum}">₱0.00</td>
            <td class="calc-cell" id="undertimeDeduct_${dayNum}">₱0.00</td>
            <td class="calc-cell" id="halfdayDeduct_${dayNum}">₱0.00</td>
            <td class="calc-cell" id="otPayment_${dayNum}">₱0.00</td>
            <td class="calc-cell" id="otherDeduct_${dayNum}">₱0.00</td>
            <td><input type="text" class="remarks-input" id="remarks_${dayNum}" placeholder=""></td>
        `;
        tbody.appendChild(row);
        dayNum++;
    }
}

// Calculate per day, hour, minute rates from basic salary
function calculateRates() {
    const basicSalary = parseFloat(document.getElementById('basicSalary').value) || 0;
    
    // TB5: 30 days a month computation, 8 hours per day
    const perDay = basicSalary / 30;
    const perHour = perDay / 8;
    const perMinute = perHour / 60;
    
    document.getElementById('perDay').textContent = '₱' + perDay.toFixed(2);
    document.getElementById('perMin').textContent = '₱' + perMinute.toFixed(4);
    document.getElementById('perHour').textContent = '₱' + perHour.toFixed(2);
    
    // Recalculate all rows if there are any
    const rows = document.querySelectorAll('#dtrTableBody tr');
    rows.forEach((row, index) => {
        calculateRow(index + 1);
    });
}

// Parse time string to minutes since midnight
function timeToMinutes(timeStr) {
    if (!timeStr) return null;
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours * 60 + minutes;
}

// Calculate work hours, late, undertime, OT for a single day
function calculateRow(dayNum) {
    const isAbsent = document.getElementById(`absent_${dayNum}`).checked;
    const basicSalary = parseFloat(document.getElementById('basicSalary').value) || 0;
    const perDay = basicSalary / 30; // TB5: 30 days a month computation
    const perHour = perDay / 8;
    const perMinute = perHour / 60;
    
    // If absent, set all values
    if (isAbsent) {
        document.getElementById(`workHours_${dayNum}`).textContent = '0.00';
        document.getElementById(`lateMins_${dayNum}`).textContent = '0';
        document.getElementById(`undertimeMins_${dayNum}`).textContent = '0';
        document.getElementById(`otHours_${dayNum}`).textContent = '0.00';
        document.getElementById(`absentDays_${dayNum}`).textContent = '1';
        document.getElementById(`absentDeduct_${dayNum}`).textContent = '₱' + perDay.toFixed(2);
        document.getElementById(`lateDeduct_${dayNum}`).textContent = '₱0.00';
        document.getElementById(`undertimeDeduct_${dayNum}`).textContent = '₱0.00';
        document.getElementById(`halfdayDeduct_${dayNum}`).textContent = '₱0.00';
        document.getElementById(`otPayment_${dayNum}`).textContent = '₱0.00';
        document.getElementById(`otherDeduct_${dayNum}`).textContent = '₱0.00';
        calculateTotals();
        return;
    }
    
    const amIn = document.getElementById(`amIn_${dayNum}`).value;
    const amOut = document.getElementById(`amOut_${dayNum}`).value;
    const pmIn = document.getElementById(`pmIn_${dayNum}`).value;
    const pmOut = document.getElementById(`pmOut_${dayNum}`).value;
    const otIn = document.getElementById(`otIn_${dayNum}`).value;
    const otOut = document.getElementById(`otOut_${dayNum}`).value;
    const halfdayIn = document.getElementById(`halfdayIn_${dayNum}`).value;
    const halfdayOut = document.getElementById(`halfdayOut_${dayNum}`).value;
    
    let totalWorkMinutes = 0;
    let lateMinutes = 0;
    let undertimeMinutes = 0;
    let otMinutes = 0;
    
    // AM Shift: 8:00 - 12:00 (4 hours)
    if (amIn && amOut) {
        const amInMins = timeToMinutes(amIn);
        const amOutMins = timeToMinutes(amOut);
        
        // Work hours (max 4 hours for AM)
        const amWorkMins = Math.min(amOutMins - amInMins, 240);
        totalWorkMinutes += Math.max(0, amWorkMins);
        
        // Late: if check-in after 8:00
        const startTime = 8 * 60; // 8:00 AM
        if (amInMins > startTime) {
            lateMinutes += (amInMins - startTime);
        }
        
        // Undertime: if check-out before 12:00
        const endTime = 12 * 60; // 12:00 PM
        if (amOutMins < endTime) {
            undertimeMinutes += (endTime - amOutMins);
        }
    }
    
    // PM Shift: 13:00 - 17:00 (4 hours)
    if (pmIn && pmOut) {
        const pmInMins = timeToMinutes(pmIn);
        const pmOutMins = timeToMinutes(pmOut);
        
        // Work hours (max 4 hours for PM)
        const pmWorkMins = Math.min(pmOutMins - pmInMins, 240);
        totalWorkMinutes += Math.max(0, pmWorkMins);
        
        // Late: if check-in after 13:00
        const startTime = 13 * 60; // 1:00 PM
        if (pmInMins > startTime) {
            lateMinutes += (pmInMins - startTime);
        }
        
        // Undertime: if check-out before 17:00
        const endTime = 17 * 60; // 5:00 PM
        if (pmOutMins < endTime) {
            undertimeMinutes += (endTime - pmOutMins);
        }
    }
    
    // OT (overtime with 1.25x multiplier)
    if (otIn && otOut) {
        const otInMins = timeToMinutes(otIn);
        const otOutMins = timeToMinutes(otOut);
        otMinutes = Math.max(0, otOutMins - otInMins);
    }
    
    // Halfday
    if (halfdayIn && halfdayOut) {
        const halfdayInMins = timeToMinutes(halfdayIn);
        const halfdayOutMins = timeToMinutes(halfdayOut);
        totalWorkMinutes += Math.max(0, halfdayOutMins - halfdayInMins);
    }
    
    // Calculate deductions and payments
    const lateDeduction = lateMinutes * perMinute;
    const undertimeDeduction = undertimeMinutes * perMinute;
    const otPayment = (otMinutes / 60) * perHour * 1.25;
    
    // Update display
    document.getElementById(`workHours_${dayNum}`).textContent = (totalWorkMinutes / 60).toFixed(2);
    document.getElementById(`lateMins_${dayNum}`).textContent = lateMinutes;
    document.getElementById(`undertimeMins_${dayNum}`).textContent = undertimeMinutes;
    document.getElementById(`otHours_${dayNum}`).textContent = (otMinutes / 60).toFixed(2);
    document.getElementById(`absentDays_${dayNum}`).textContent = '0';
    document.getElementById(`absentDeduct_${dayNum}`).textContent = '₱0.00';
    document.getElementById(`lateDeduct_${dayNum}`).textContent = '₱' + lateDeduction.toFixed(2);
    document.getElementById(`undertimeDeduct_${dayNum}`).textContent = '₱' + undertimeDeduction.toFixed(2);
    document.getElementById(`halfdayDeduct_${dayNum}`).textContent = '₱0.00';
    document.getElementById(`otPayment_${dayNum}`).textContent = '₱' + otPayment.toFixed(2);
    document.getElementById(`otherDeduct_${dayNum}`).textContent = '₱0.00';
    
    // Recalculate totals
    calculateTotals();
}

// Calculate totals and net salary
function calculateTotals() {
    const rows = document.querySelectorAll('#dtrTableBody tr');
    let totalWorkHours = 0;
    let totalLateMins = 0;
    let totalUndertimeMins = 0;
    let totalOtHours = 0;
    let totalAbsentDays = 0;
    let totalAbsentDeduct = 0;
    let totalLateDeduct = 0;
    let totalUndertimeDeduct = 0;
    let totalHalfdayDeduct = 0;
    let totalOTPayment = 0;
    let totalOtherDeduct = 0;
    
    rows.forEach((row, index) => {
        const dayNum = index + 1;
        
        totalWorkHours += parseFloat(document.getElementById(`workHours_${dayNum}`).textContent) || 0;
        totalLateMins += parseInt(document.getElementById(`lateMins_${dayNum}`).textContent) || 0;
        totalUndertimeMins += parseInt(document.getElementById(`undertimeMins_${dayNum}`).textContent) || 0;
        totalOtHours += parseFloat(document.getElementById(`otHours_${dayNum}`).textContent) || 0;
        totalAbsentDays += parseInt(document.getElementById(`absentDays_${dayNum}`).textContent) || 0;
        totalAbsentDeduct += parseFloat(document.getElementById(`absentDeduct_${dayNum}`).textContent.replace('₱', '')) || 0;
        totalLateDeduct += parseFloat(document.getElementById(`lateDeduct_${dayNum}`).textContent.replace('₱', '')) || 0;
        totalUndertimeDeduct += parseFloat(document.getElementById(`undertimeDeduct_${dayNum}`).textContent.replace('₱', '')) || 0;
        totalHalfdayDeduct += parseFloat(document.getElementById(`halfdayDeduct_${dayNum}`).textContent.replace('₱', '')) || 0;
        totalOTPayment += parseFloat(document.getElementById(`otPayment_${dayNum}`).textContent.replace('₱', '')) || 0;
        totalOtherDeduct += parseFloat(document.getElementById(`otherDeduct_${dayNum}`).textContent.replace('₱', '')) || 0;
    });
    
    // Update totals display
    document.getElementById('totalWorkHours').textContent = totalWorkHours.toFixed(2);
    document.getElementById('totalLateMins').textContent = totalLateMins;
    document.getElementById('totalUndertimeMins').textContent = totalUndertimeMins;
    document.getElementById('totalOT').textContent = totalOtHours.toFixed(2);
    document.getElementById('totalAbsentDays').textContent = totalAbsentDays;
    document.getElementById('totalAbsentDeduct').textContent = '₱' + totalAbsentDeduct.toFixed(2);
    document.getElementById('totalLateDeduct').textContent = '₱' + totalLateDeduct.toFixed(2);
    document.getElementById('totalUndertimeDeduct').textContent = '₱' + totalUndertimeDeduct.toFixed(2);
    document.getElementById('totalHalfdayDeduct').textContent = '₱' + totalHalfdayDeduct.toFixed(2);
    document.getElementById('totalOTPayment').textContent = '₱' + totalOTPayment.toFixed(2);
    
    // Government deductions
    const sss = parseFloat(document.getElementById('sssDeduct').value) || 0;
    const philhealth = parseFloat(document.getElementById('philhealthDeduct').value) || 0;
    const pagibig = parseFloat(document.getElementById('pagibigDeduct').value) || 0;
    const cashAdvance = parseFloat(document.getElementById('cashAdvance').value) || 0;
    const govtTotal = sss + philhealth + pagibig + cashAdvance;
    
    document.getElementById('totalOtherDeduct').textContent = '₱' + govtTotal.toFixed(2);
    
    // Calculate net salary
    const basicSalary = parseFloat(document.getElementById('basicSalary').value) || 0;
    const netSalary = basicSalary + totalOTPayment - totalAbsentDeduct - totalLateDeduct - totalUndertimeDeduct - totalHalfdayDeduct - govtTotal;
    
    document.getElementById('totalNetSalary').textContent = '₱' + netSalary.toFixed(2);
}

// Save payroll
function savePayroll() {
    if (!currentEmployeeId) {
        alert('No employee selected.');
        return;
    }
    
    const periodId = document.getElementById('modalPeriodSelect').value;
    if (!periodId) {
        alert('Please select a payroll period.');
        return;
    }
    
    const basicSalary = parseFloat(document.getElementById('basicSalary').value) || 0;
    if (basicSalary <= 0) {
        alert('Please enter a valid basic salary.');
        return;
    }
    
    // Collect DTR data
    const dtrData = [];
    const rows = document.querySelectorAll('#dtrTableBody tr');
    
    rows.forEach((row, index) => {
        const dayNum = index + 1;
        const isAbsent = document.getElementById(`absent_${dayNum}`).checked;
        
        dtrData.push({
            day: dayNum,
            am_in: document.getElementById(`amIn_${dayNum}`).value || null,
            am_out: document.getElementById(`amOut_${dayNum}`).value || null,
            pm_in: document.getElementById(`pmIn_${dayNum}`).value || null,
            pm_out: document.getElementById(`pmOut_${dayNum}`).value || null,
            ot_in: document.getElementById(`otIn_${dayNum}`).value || null,
            ot_out: document.getElementById(`otOut_${dayNum}`).value || null,
            halfday_in: document.getElementById(`halfdayIn_${dayNum}`).value || null,
            halfday_out: document.getElementById(`halfdayOut_${dayNum}`).value || null,
            is_absent: isAbsent ? 1 : 0,
            work_hours: parseFloat(document.getElementById(`workHours_${dayNum}`).textContent) || 0,
            late_mins: parseInt(document.getElementById(`lateMins_${dayNum}`).textContent) || 0,
            undertime: parseFloat(document.getElementById(`undertimeMins_${dayNum}`).textContent) / 60 || 0,
            ot_hours: parseFloat(document.getElementById(`otHours_${dayNum}`).textContent) || 0
        });
    });
    
    // Collect government deductions
    const sss = parseFloat(document.getElementById('sssDeduct').value) || 0;
    const philhealth = parseFloat(document.getElementById('philhealthDeduct').value) || 0;
    const pagibig = parseFloat(document.getElementById('pagibigDeduct').value) || 0;
    const cashAdvance = parseFloat(document.getElementById('cashAdvance').value) || 0;
    
    const basicSalaryVal = basicSalary;
    const perDay = basicSalaryVal / 30; // TB5: 30 days a month computation
    const perHour = perDay / 8;
    const perMinute = perHour / 60;
    
    // Prepare data for submission
    const formData = new FormData();
    formData.append('action', 'save_payroll');
    formData.append('employee_id', currentEmployeeId);
    formData.append('period_id', periodId);
    formData.append('basic_salary', basicSalaryVal);
    formData.append('per_day', perDay);
    formData.append('per_hour', perHour);
    formData.append('per_minute', perMinute);
    formData.append('dtr_data', JSON.stringify(dtrData));
    formData.append('sss', sss);
    formData.append('philhealth', philhealth);
    formData.append('pagibig', pagibig);
    formData.append('cash_advance', cashAdvance);
    
    // Submit via AJAX
    fetch('Generatepayroll.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Payroll saved successfully!');
            closeDTRModal();
            location.reload();
        } else {
            alert('Error saving payroll: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save payroll. Please check the console for details.');
    });
}

// Calculate rates on initial page load
document.addEventListener('DOMContentLoaded', function() {
    calculateRates();
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('dtrModal');
    if (event.target === modal) {
        closeDTRModal();
    }
}
</script>

<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
include 'include/footer.php';
?>
