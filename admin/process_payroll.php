<?php
/**
 * Process Payroll Submission
 * Saves payroll data to database
 */

require_once '../config/bootstrap.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';
require_once '../config/account_logs_helper.php';
require_once '../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// CSRF check
requireCSRFToken();

// Validate required fields
if (empty($_POST['employee_id']) || empty($_POST['payroll_period_id'])) {
    echo json_encode(['success' => false, 'message' => 'Employee and payroll period are required']);
    exit();
}

$employeeId = intval($_POST['employee_id']);
$payrollPeriodId = intval($_POST['payroll_period_id']);
$createdBy = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Check if payroll already exists for this employee and period
    $stmt = $pdo->prepare("
        SELECT id FROM payroll_computations 
        WHERE employee_id = ? AND payroll_period_id = ?
    ");
    $stmt->execute([$employeeId, $payrollPeriodId]);
    $existingPayroll = $stmt->fetch();
    
    // Calculate totals
    $standardCurrent = floatval($_POST['standard_current'] ?? 0);
    $overtimeCurrent = floatval($_POST['overtime_current'] ?? 0);
    $holidayCurrent = floatval($_POST['holiday_current'] ?? 0);
    $basicCurrent = floatval($_POST['basic_current'] ?? 0);
    $commissionCurrent = floatval($_POST['commission_current'] ?? 0);
    $sickCurrent = floatval($_POST['sick_current'] ?? 0);
    $expenseCurrent = floatval($_POST['expense_current'] ?? 0);
    
    $totalEarnings = $standardCurrent + $overtimeCurrent + $holidayCurrent + 
                     $basicCurrent + $commissionCurrent + $sickCurrent + $expenseCurrent;
    
    // DTR Deductions
    $lateDeductCurrent = floatval($_POST['late_deduct_current'] ?? 0);
    $undertimeDeductCurrent = floatval($_POST['undertime_deduct_current'] ?? 0);
    $halfdayDeductCurrent = floatval($_POST['halfday_deduct_current'] ?? 0);
    $absentDeductCurrent = floatval($_POST['absent_deduct_current'] ?? 0);
    
    // Government & Other Deductions
    $payeCurrent = floatval($_POST['paye_current'] ?? 0);
    $nationalInsuranceCurrent = floatval($_POST['national_insurance_current'] ?? 0);
    $studentLoanCurrent = floatval($_POST['student_loan_current'] ?? 0);
    $pensionCurrent = floatval($_POST['pension_current'] ?? 0);
    $unionCurrent = floatval($_POST['union_current'] ?? 0);
    $other1Current = floatval($_POST['other1_current'] ?? 0);
    $other2Current = floatval($_POST['other2_current'] ?? 0);
    
    $totalDeductions = $lateDeductCurrent + $undertimeDeductCurrent + $halfdayDeductCurrent + 
                       $absentDeductCurrent + $payeCurrent + $nationalInsuranceCurrent + 
                       $studentLoanCurrent + $pensionCurrent + $unionCurrent + $other1Current + $other2Current;
    
    $netPay = $totalEarnings - $totalDeductions;
    
    if ($existingPayroll) {
        // Update existing payroll
        $stmt = $pdo->prepare("
            UPDATE payroll_computations SET
                -- Earnings
                basic_pay = ?,
                ot_pay = ?,
                
                -- Hours and Rates
                total_work_hours = ?,
                per_hour_rate = ?,
                
                -- Deductions
                withholding_tax = ?,
                sss_contribution = ?,
                philhealth_contribution = ?,
                pagibig_contribution = ?,
                other_deductions = ?,
                
                -- Totals
                total_earnings = ?,
                total_deductions = ?,
                net_pay = ?,
                
                -- Additional Data
                other_deductions_notes = ?,
                status = 'computed',
                computed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $otherDeductionsNotes = json_encode([
            'payslip_number' => $_POST['payslip_number'] ?? '',
            'employee_number' => $_POST['employee_number'] ?? '',
            'tax_code' => $_POST['tax_code'] ?? '',
            'payment_method' => $_POST['payment_method'] ?? 'Check',
            'standard_hours' => floatval($_POST['standard_hours'] ?? 0),
            'overtime_hours' => floatval($_POST['overtime_hours'] ?? 0),
            'holiday_hours' => floatval($_POST['holiday_hours'] ?? 0),
            'standard_rate' => floatval($_POST['standard_rate'] ?? 0),
            'overtime_rate' => floatval($_POST['overtime_rate'] ?? 0),
            'holiday_rate' => floatval($_POST['holiday_rate'] ?? 0),
            'holiday_pay' => $holidayCurrent,
            'basic_pay_current' => $basicCurrent,
            'commission' => $commissionCurrent,
            'sick_pay' => $sickCurrent,
            'expense' => $expenseCurrent,
            'dtr_data' => [
                'late_minutes' => floatval($_POST['late_minutes'] ?? 0),
                'late_deduct' => $lateDeductCurrent,
                'undertime_hours' => floatval($_POST['undertime_hours'] ?? 0),
                'undertime_deduct' => $undertimeDeductCurrent,
                'halfday_days' => floatval($_POST['halfday_days'] ?? 0),
                'halfday_deduct' => $halfdayDeductCurrent,
                'absent_days' => floatval($_POST['absent_days'] ?? 0),
                'absent_deduct' => $absentDeductCurrent
            ],
            'national_insurance' => $nationalInsuranceCurrent,
            'student_loan' => $studentLoanCurrent,
            'pension' => $pensionCurrent,
            'union_fees' => $unionCurrent,
            'other_deductions' => $other1Current + $other2Current,
            'ytd_values' => [
                'standard_ytd' => floatval($_POST['standard_ytd'] ?? 0),
                'overtime_ytd' => floatval($_POST['overtime_ytd'] ?? 0),
                'holiday_ytd' => floatval($_POST['holiday_ytd'] ?? 0),
                'basic_ytd' => floatval($_POST['basic_ytd'] ?? 0),
                'commission_ytd' => floatval($_POST['commission_ytd'] ?? 0),
                'sick_ytd' => floatval($_POST['sick_ytd'] ?? 0),
                'expense_ytd' => floatval($_POST['expense_ytd'] ?? 0),
                'late_deduct_ytd' => floatval($_POST['late_deduct_ytd'] ?? 0),
                'undertime_deduct_ytd' => floatval($_POST['undertime_deduct_ytd'] ?? 0),
                'halfday_deduct_ytd' => floatval($_POST['halfday_deduct_ytd'] ?? 0),
                'absent_deduct_ytd' => floatval($_POST['absent_deduct_ytd'] ?? 0),
                'paye_ytd' => floatval($_POST['paye_ytd'] ?? 0),
                'national_insurance_ytd' => floatval($_POST['national_insurance_ytd'] ?? 0),
                'student_loan_ytd' => floatval($_POST['student_loan_ytd'] ?? 0),
                'pension_ytd' => floatval($_POST['pension_ytd'] ?? 0),
                'union_ytd' => floatval($_POST['union_ytd'] ?? 0),
                'other1_ytd' => floatval($_POST['other1_ytd'] ?? 0),
                'other2_ytd' => floatval($_POST['other2_ytd'] ?? 0)
            ]
        ]);
        
        $stmt->execute([
            $standardCurrent + $basicCurrent,
            $overtimeCurrent,
            floatval($_POST['standard_hours'] ?? 0) + floatval($_POST['overtime_hours'] ?? 0),
            floatval($_POST['standard_rate'] ?? 0),
            $payeCurrent,
            $nationalInsuranceCurrent,
            $pensionCurrent,
            $studentLoanCurrent,
            $lateDeductCurrent + $undertimeDeductCurrent + $halfdayDeductCurrent + 
            $absentDeductCurrent + $unionCurrent + $other1Current + $other2Current,
            $totalEarnings,
            $totalDeductions,
            $netPay,
            $otherDeductionsNotes,
            $existingPayroll['id']
        ]);
        
        $payrollId = $existingPayroll['id'];
        $message = 'Payroll updated successfully';
        
    } else {
        // Insert new payroll
        $stmt = $pdo->prepare("
            INSERT INTO payroll_computations (
                employee_id,
                payroll_period_id,
                basic_monthly_salary,
                per_day_rate,
                per_hour_rate,
                per_minute_rate,
                total_work_hours,
                total_ot_hours,
                basic_pay,
                ot_pay,
                withholding_tax,
                sss_contribution,
                philhealth_contribution,
                pagibig_contribution,
                other_deductions,
                other_deductions_notes,
                total_earnings,
                total_deductions,
                net_pay,
                status,
                computed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'computed', NOW())
        ");
        
        // Get employee's basic salary
        $stmtEmp = $pdo->prepare("SELECT basic_monthly_salary FROM employees WHERE id = ?");
        $stmtEmp->execute([$employeeId]);
        $employeeData = $stmtEmp->fetch();
        $monthlySalary = floatval($employeeData['basic_monthly_salary'] ?? 0);
        $perDayRate = $monthlySalary / 30; // TB5: 30 days a month computation
        $perHourRate = $perDayRate / 8;
        $perMinuteRate = $perHourRate / 60;
        
        $otherDeductionsNotes = json_encode([
            'payslip_number' => $_POST['payslip_number'] ?? '',
            'employee_number' => $_POST['employee_number'] ?? '',
            'tax_code' => $_POST['tax_code'] ?? '',
            'payment_method' => $_POST['payment_method'] ?? 'Check',
            'standard_hours' => floatval($_POST['standard_hours'] ?? 0),
            'overtime_hours' => floatval($_POST['overtime_hours'] ?? 0),
            'holiday_hours' => floatval($_POST['holiday_hours'] ?? 0),
            'standard_rate' => floatval($_POST['standard_rate'] ?? 0),
            'overtime_rate' => floatval($_POST['overtime_rate'] ?? 0),
            'holiday_rate' => floatval($_POST['holiday_rate'] ?? 0),
            'holiday_pay' => $holidayCurrent,
            'basic_pay_current' => $basicCurrent,
            'commission' => $commissionCurrent,
            'sick_pay' => $sickCurrent,
            'expense' => $expenseCurrent,
            'dtr_data' => [
                'late_minutes' => floatval($_POST['late_minutes'] ?? 0),
                'late_deduct' => $lateDeductCurrent,
                'undertime_hours' => floatval($_POST['undertime_hours'] ?? 0),
                'undertime_deduct' => $undertimeDeductCurrent,
                'halfday_days' => floatval($_POST['halfday_days'] ?? 0),
                'halfday_deduct' => $halfdayDeductCurrent,
                'absent_days' => floatval($_POST['absent_days'] ?? 0),
                'absent_deduct' => $absentDeductCurrent
            ],
            'national_insurance' => $nationalInsuranceCurrent,
            'student_loan' => $studentLoanCurrent,
            'pension' => $pensionCurrent,
            'union_fees' => $unionCurrent,
            'other_deductions' => $other1Current + $other2Current,
            'ytd_values' => [
                'standard_ytd' => floatval($_POST['standard_ytd'] ?? 0),
                'overtime_ytd' => floatval($_POST['overtime_ytd'] ?? 0),
                'holiday_ytd' => floatval($_POST['holiday_ytd'] ?? 0),
                'basic_ytd' => floatval($_POST['basic_ytd'] ?? 0),
                'commission_ytd' => floatval($_POST['commission_ytd'] ?? 0),
                'sick_ytd' => floatval($_POST['sick_ytd'] ?? 0),
                'expense_ytd' => floatval($_POST['expense_ytd'] ?? 0),
                'late_deduct_ytd' => floatval($_POST['late_deduct_ytd'] ?? 0),
                'undertime_deduct_ytd' => floatval($_POST['undertime_deduct_ytd'] ?? 0),
                'halfday_deduct_ytd' => floatval($_POST['halfday_deduct_ytd'] ?? 0),
                'absent_deduct_ytd' => floatval($_POST['absent_deduct_ytd'] ?? 0),
                'paye_ytd' => floatval($_POST['paye_ytd'] ?? 0),
                'national_insurance_ytd' => floatval($_POST['national_insurance_ytd'] ?? 0),
                'student_loan_ytd' => floatval($_POST['student_loan_ytd'] ?? 0),
                'pension_ytd' => floatval($_POST['pension_ytd'] ?? 0),
                'union_ytd' => floatval($_POST['union_ytd'] ?? 0),
                'other1_ytd' => floatval($_POST['other1_ytd'] ?? 0),
                'other2_ytd' => floatval($_POST['other2_ytd'] ?? 0)
            ]
        ]);
        
        $stmt->execute([
            $employeeId,
            $payrollPeriodId,
            $monthlySalary,
            $perDayRate,
            $perHourRate,
            $perMinuteRate,
            floatval($_POST['standard_hours'] ?? 0) + floatval($_POST['overtime_hours'] ?? 0),
            floatval($_POST['overtime_hours'] ?? 0),
            $standardCurrent + $basicCurrent,
            $overtimeCurrent,
            $payeCurrent,
            $nationalInsuranceCurrent,
            $pensionCurrent,
            $lateDeductCurrent + $undertimeDeductCurrent + $halfdayDeductCurrent + 
            $absentDeductCurrent + $studentLoanCurrent,
            $unionCurrent + $other1Current + $other2Current,
            $otherDeductionsNotes,
            $totalEarnings,
            $totalDeductions,
            $netPay
        ]);
        
        $payrollId = $pdo->lastInsertId();
        $message = 'Payroll created successfully';
    }
    
    // Log the payroll action (matches payroll_history table: payroll_computation_id, action, changed_by, notes)
    $stmt = $pdo->prepare("
        INSERT INTO payroll_history (
            payroll_computation_id,
            action,
            changed_by,
            notes
        ) VALUES (?, ?, ?, ?)
    ");
    
    $historyAction = "Payroll " . ($existingPayroll ? "updated" : "created");
    $historyNotes = $historyAction . " by " . $_SESSION['full_name'];
    $stmt->execute([$payrollId, $historyAction, $createdBy, $historyNotes]);
    
    // Save DTR records from the form
    saveDTRRecordsFromForm($pdo, $employeeId, $payrollPeriodId, $createdBy);
    
    // Get employee and period names for logging
    $logStmt = $pdo->prepare("
        SELECT e.full_name, pp.period_name 
        FROM employees e
        JOIN payroll_periods pp ON pp.id = ?
        WHERE e.id = ?
    ");
    $logStmt->execute([$payrollPeriodId, $employeeId]);
    $logInfo = $logStmt->fetch(PDO::FETCH_ASSOC);
    
    // Log the payroll action to account logs
    $actionType = $existingPayroll ? 'Updated' : 'Generated';
    logPayrollAction(
        $_SESSION['user_id'],
        $_SESSION['username'],
        $actionType,
        $logInfo['full_name'] ?? "Employee #{$employeeId}",
        $logInfo['period_name'] ?? "Period #{$payrollPeriodId}",
        $netPay,
        $pdo
    );
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'payroll_id' => $payrollId
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Error processing payroll: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}

/**
 * Save DTR records from the payroll form
 */
function saveDTRRecordsFromForm($pdo, $employeeId, $payrollPeriodId, $createdBy) {
    // Find all DTR date fields in the form
    $dtrDates = [];
    foreach ($_POST as $key => $value) {
        if (preg_match('/^dtr_date_(\d+)$/', $key, $matches)) {
            $rowNum = $matches[1];
            $dtrDate = $value;
            if (!empty($dtrDate)) {
                $dtrDates[$rowNum] = $dtrDate;
            }
        }
    }
    
    if (empty($dtrDates)) {
        return;
    }
    
    // Prepared upsert statement - eliminates N+1 SELECT queries per DTR row
    $upsertStmt = $pdo->prepare("
        INSERT INTO dtr_records (
            employee_id, payroll_period_id, dtr_date,
            am_time_in, am_time_out, pm_time_in, pm_time_out,
            ot_time_out, halfday_in, halfday_out,
            is_halfday, is_absent, total_work_hours,
            late_minutes, undertime_hours, daily_ot_hours,
            govt_deduct, net_salary,
            created_by, created_at, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
            payroll_period_id = VALUES(payroll_period_id),
            am_time_in = VALUES(am_time_in),
            am_time_out = VALUES(am_time_out),
            pm_time_in = VALUES(pm_time_in),
            pm_time_out = VALUES(pm_time_out),
            ot_time_out = VALUES(ot_time_out),
            halfday_in = VALUES(halfday_in),
            halfday_out = VALUES(halfday_out),
            is_halfday = VALUES(is_halfday),
            is_absent = VALUES(is_absent),
            total_work_hours = VALUES(total_work_hours),
            late_minutes = VALUES(late_minutes),
            undertime_hours = VALUES(undertime_hours),
            daily_ot_hours = VALUES(daily_ot_hours),
            govt_deduct = VALUES(govt_deduct),
            net_salary = VALUES(net_salary),
            updated_at = NOW(),
            updated_by = VALUES(updated_by)
    ");
    
    foreach ($dtrDates as $rowNum => $dtrDate) {
        // Get all fields for this row
        $amIn = $_POST["am_in_{$rowNum}"] ?? null;
        $amOut = $_POST["am_out_{$rowNum}"] ?? null;
        $pmIn = $_POST["pm_in_{$rowNum}"] ?? null;
        $pmOut = $_POST["pm_out_{$rowNum}"] ?? null;
        $otOut = $_POST["ot_out_{$rowNum}"] ?? null;
        $halfdayIn = $_POST["halfday_in_{$rowNum}"] ?? null;
        $halfdayOut = $_POST["halfday_out_{$rowNum}"] ?? null;
        $isAbsent = isset($_POST["absent_{$rowNum}"]) ? 1 : 0;
        $workHours = floatval($_POST["work_hours_{$rowNum}"] ?? 0);
        $lateMins = intval($_POST["late_mins_{$rowNum}"] ?? 0);
        $undertime = floatval($_POST["undertime_{$rowNum}"] ?? 0);
        $otHours = floatval($_POST["ot_hours_{$rowNum}"] ?? 0);
        $govtDeduct = floatval($_POST["govt_{$rowNum}"] ?? 0);
        $autoSalary = isset($_POST["auto_salary_{$rowNum}"]) && $_POST["auto_salary_{$rowNum}"] !== '' 
                        ? floatval($_POST["auto_salary_{$rowNum}"]) : null;
        
        // Format times (ensure HH:MM format)
        $amIn = formatTimeValue($amIn);
        $amOut = formatTimeValue($amOut);
        $pmIn = formatTimeValue($pmIn);
        $pmOut = formatTimeValue($pmOut);
        $otOut = formatTimeValue($otOut);
        $halfdayIn = formatTimeValue($halfdayIn);
        $halfdayOut = formatTimeValue($halfdayOut);
        
        // Check for halfday
        $isHalfday = (!empty($halfdayIn) || !empty($halfdayOut)) ? 1 : 0;
        
        // Upsert: INSERT or UPDATE using UNIQUE KEY (employee_id, dtr_date)
        $upsertStmt->execute([
            $employeeId,
            $payrollPeriodId,
            $dtrDate,
            $amIn,
            $amOut,
            $pmIn,
            $pmOut,
            $otOut,
            $halfdayIn,
            $halfdayOut,
            $isHalfday,
            $isAbsent,
            $workHours,
            $lateMins,
            $undertime,
            $otHours,
            $govtDeduct,
            $autoSalary,
            $createdBy,
            $createdBy
        ]);
    }
}

/**
 * Format time value to HH:MM or null
 */
function formatTimeValue($value) {
    if (empty($value)) return null;
    
    $value = trim($value);
    
    // Already in correct format
    if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        return $value;
    }
    
    // Handle HH:MM:SS
    if (preg_match('/^(\d{2}):(\d{2}):\d{2}$/', $value, $matches)) {
        return $matches[1] . ':' . $matches[2];
    }
    
    // Try strtotime
    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return date('H:i', $timestamp);
    }
    
    return null;
}
?>
