<?php
/**
 * Save DTR Records
 * Saves employee info and DTR data from the imported Excel/form
 * Auto-generates employee code if not provided
 */

require_once '../config/bootstrap.php';
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/account_logs_helper.php';
require_once '../config/csrf.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// CSRF check
requireCSRFToken();

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    $createdBy = $_SESSION['user_id'];
    
    // Get employee data
    $directEmployeeId = intval($_POST['employee_id'] ?? 0);  // passed when selecting from dropdown
    $employeeName = trim($_POST['employee_name'] ?? '');
    $employeeCode = trim($_POST['employee_code'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $salary = floatval($_POST['salary'] ?? 0);
    $periodStart = $_POST['period_start'] ?? null;
    $periodEnd = $_POST['period_end'] ?? null;
    $dtrRecordsJson = $_POST['dtr_records'] ?? '[]';

    // Totals passed from main DTR table
    $totalLateHours      = floatval($_POST['total_late_hours']      ?? 0);
    $totalUndertimeHours = floatval($_POST['total_undertime_hours']  ?? 0);
    $totalOtHours        = floatval($_POST['total_ot_hours']         ?? 0);
    $totalAbsentDays     = floatval($_POST['total_absent_days']      ?? 0);
    $totalAbsentDeduct   = floatval($_POST['total_absent_deduct']    ?? 0);
    $totalLateDeduct     = floatval($_POST['total_late_deduct']      ?? 0);
    $totalUtDeduct       = floatval($_POST['total_ut_deduct']        ?? 0);
    $totalHalfDeduct     = floatval($_POST['total_half_deduct']      ?? 0);
    $totalOtPay          = floatval($_POST['total_ot_pay']           ?? 0);
    $sssContribution     = floatval($_POST['sss_contribution']       ?? 0);
    $philhealthContrib   = floatval($_POST['philhealth_contribution'] ?? 0);
    $pagibigContrib      = floatval($_POST['pagibig_contribution']   ?? 0);
    
    // New payroll summary fields
    $daysOffice = intval($_POST['days_office'] ?? 0);
    $grossPay = floatval($_POST['gross_pay'] ?? 0);
    $trainingsCount = intval($_POST['trainings_count'] ?? 0);
    $paymentPerTrainee = floatval($_POST['payment_per_trainee'] ?? 0);
    $trainingsCost = floatval($_POST['trainings_cost'] ?? 0);
    
    if (empty($employeeName) && !$directEmployeeId) {
        throw new Exception('Employee name is required');
    }
    
    $dtrRecords = json_decode($dtrRecordsJson, true);
    if (!is_array($dtrRecords) || empty($dtrRecords)) {
        throw new Exception('No DTR records provided');
    }
    
    // Check if employee exists or create new
    $employeeId = null;

    // Use directly supplied employee_id (from dropdown selection)
    if ($directEmployeeId > 0) {
        $stmt = $pdo->prepare("SELECT id, employee_code, full_name FROM employees WHERE id = ?");
        $stmt->execute([$directEmployeeId]);
        $empRow = $stmt->fetch();
        if ($empRow) {
            $employeeId   = $empRow['id'];
            $employeeCode = $empRow['employee_code'];
            $employeeName = $empRow['full_name'];
        }
    }
    
    if (!$employeeId && !empty($employeeCode)) {
        // Try to find by employee code
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE employee_code = ?");
        $stmt->execute([$employeeCode]);
        $existing = $stmt->fetch();
        if ($existing) {
            $employeeId = $existing['id'];
        }
    }
    
    if (!$employeeId) {
        // Try to find by name
        $stmt = $pdo->prepare("SELECT id, employee_code FROM employees WHERE full_name = ?");
        $stmt->execute([$employeeName]);
        $existing = $stmt->fetch();
        if ($existing) {
            $employeeId = $existing['id'];
            $employeeCode = $existing['employee_code'];
        }
    }
    
    if (!$employeeId) {
        // Create new employee with auto-generated code
        if (empty($employeeCode)) {
            $employeeCode = generateNextEmployeeCode($pdo);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO employees (employee_code, full_name, position, department, basic_monthly_salary, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$employeeCode, $employeeName, $position, $department, $salary]);
        $employeeId = $pdo->lastInsertId();
    } else {
        // Update existing employee info if provided
        $updateFields = [];
        $updateParams = [];
        
        if (!empty($position)) {
            $updateFields[] = "position = ?";
            $updateParams[] = $position;
        }
        if (!empty($department)) {
            $updateFields[] = "department = ?";
            $updateParams[] = $department;
        }
        if ($salary > 0) {
            $updateFields[] = "basic_monthly_salary = ?";
            $updateParams[] = $salary;
        }
        
        if (!empty($updateFields)) {
            $updateParams[] = $employeeId;
            $sql = "UPDATE employees SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateParams);
        }
    }
    
    // Find or create payroll period
    $payrollPeriodId = null;
    if ($periodStart && $periodEnd) {
        $stmt = $pdo->prepare("SELECT id FROM payroll_periods WHERE start_date = ? AND end_date = ?");
        $stmt->execute([$periodStart, $periodEnd]);
        $period = $stmt->fetch();
        
        if ($period) {
            $payrollPeriodId = $period['id'];
        } else {
            // Create new period
            $periodName = date('F Y', strtotime($periodStart));
            $stmt = $pdo->prepare("
                INSERT INTO payroll_periods (period_name, start_date, end_date, status, created_by, created_at)
                VALUES (?, ?, ?, 'draft', ?, NOW())
            ");
            $stmt->execute([$periodName, $periodStart, $periodEnd, $createdBy]);
            $payrollPeriodId = $pdo->lastInsertId();
        }
    }
    
    // Save DTR records - uses INSERT...ON DUPLICATE KEY UPDATE (eliminates N+1 SELECT queries)
    $savedCount = 0;
    $upsertStmt = $pdo->prepare("
        INSERT INTO dtr_records (
            employee_id, payroll_period_id, dtr_date,
            am_time_in, am_time_out, pm_time_in, pm_time_out,
            ot_time_out, halfday_in, halfday_out, is_halfday,
            is_absent, remarks,
            total_work_hours, late_minutes, late_hours,
            undertime_minutes, undertime_hours, daily_ot_hours,
            govt_deduct, net_salary,
            created_at, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
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
            remarks = VALUES(remarks),
            total_work_hours = VALUES(total_work_hours),
            late_minutes = VALUES(late_minutes),
            late_hours = VALUES(late_hours),
            undertime_minutes = VALUES(undertime_minutes),
            undertime_hours = VALUES(undertime_hours),
            daily_ot_hours = VALUES(daily_ot_hours),
            govt_deduct = VALUES(govt_deduct),
            net_salary = VALUES(net_salary),
            updated_at = NOW(),
            updated_by = VALUES(updated_by)
    ");
    foreach ($dtrRecords as $record) {
        $dtrDate = $record['dtr_date'] ?? null;
        if (!$dtrDate) continue;
        
        // Parse times from 12h to 24h format
        $amIn = parseTimeFor24h($record['am_in'] ?? '');
        $amOut = parseTimeFor24h($record['am_out'] ?? '');
        $pmIn = parseTimeFor24h($record['pm_in'] ?? '');
        $pmOut = parseTimeFor24h($record['pm_out'] ?? '');
        $otOut = parseTimeFor24h($record['ot_out'] ?? '');
        $halfIn = parseTimeFor24h($record['half_in'] ?? '');
        $halfOut = parseTimeFor24h($record['half_out'] ?? '');
        $isAbsent = intval($record['is_absent'] ?? 0);
        $remarks = $record['remarks'] ?? '';
        
        // Calculated fields per row
        $rowTotalWorkHours   = floatval($record['total_work_hours']  ?? 0);
        $rowLateMinutes      = intval($record['late_minutes']         ?? 0);
        $rowLateHours        = round($rowLateMinutes / 60, 4);
        $rowUndertimeHours   = floatval($record['undertime_hours']    ?? 0);
        $rowUndertimeMinutes = (int) round($rowUndertimeHours * 60);
        $rowOtHours          = floatval($record['daily_ot_hours']     ?? 0);
        $rowGovtDeduct       = floatval($record['govt_deduct']         ?? 0);
        $rowNetSalary        = isset($record['auto_salary']) && $record['auto_salary'] !== '' 
                                 ? floatval($record['auto_salary']) : null;
        $isHalfday           = (!empty($halfIn) || !empty($halfOut)) ? 1 : 0;

        // Upsert: INSERT or UPDATE using UNIQUE KEY (employee_id, dtr_date)
        $upsertStmt->execute([
            $employeeId, $payrollPeriodId, $dtrDate,
            $amIn, $amOut, $pmIn, $pmOut, $otOut, $halfIn, $halfOut, $isHalfday,
            $isAbsent, $remarks,
            $rowTotalWorkHours, $rowLateMinutes, $rowLateHours,
            $rowUndertimeMinutes, $rowUndertimeHours, $rowOtHours,
            $rowGovtDeduct, $rowNetSalary,
            $createdBy, $createdBy
        ]);
        $savedCount++;
    }
    
    // Save payroll computation with training data
    if ($payrollPeriodId && $employeeId) {
        $perDayRate = $salary / 15; // 15 days per cut-off computation
        $perHourRate = $perDayRate / 8;
        $perMinuteRate = $perHourRate / 60;

        // Derive totals that were not sent separately
        $totalWorkHoursSum  = array_sum(array_column($dtrRecords, 'total_work_hours'));
        $totalLateMinutesSum = (int) round($totalLateHours * 60);

        // Check if payroll computation exists
        $stmt = $pdo->prepare("SELECT id FROM payroll_computations WHERE employee_id = ? AND payroll_period_id = ?");
        $stmt->execute([$employeeId, $payrollPeriodId]);
        $existingComp = $stmt->fetch();
        
        // Total deductions computed (attendance + government contributions)
        $totalDeductions = $totalAbsentDeduct + $totalLateDeduct + $totalUtDeduct + $totalHalfDeduct
                         + $sssContribution + $philhealthContrib + $pagibigContrib;
        // Net Pay = Gross Pay - Total Deductions + OT Pay + Trainee Payment
        $netPay = $grossPay - $totalDeductions + $totalOtPay + $trainingsCost;

        if ($existingComp) {
            // Update existing computation with full totals
            $stmt = $pdo->prepare("
                UPDATE payroll_computations SET
                    basic_monthly_salary = ?,
                    per_day_rate = ?,
                    per_hour_rate = ?,
                    per_minute_rate = ?,
                    total_work_days = ?,
                    total_work_hours = ?,
                    total_late_minutes = ?,
                    total_late_hours = ?,
                    total_undertime_hours = ?,
                    total_ot_hours = ?,
                    total_absent_days = ?,
                    basic_pay = ?,
                    ot_pay = ?,
                    late_deduction = ?,
                    undertime_deduction = ?,
                    absent_deduction = ?,
                    sss_contribution = ?,
                    philhealth_contribution = ?,
                    pagibig_contribution = ?,
                    total_earnings = ?,
                    total_deductions = ?,
                    net_pay = ?,
                    trainings_count = ?,
                    payment_per_trainee = ?,
                    trainings_cost = ?,
                    days_office = ?,
                    status = 'computed',
                    computed_by = ?,
                    computed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $salary, $perDayRate, $perHourRate, $perMinuteRate,
                $daysOffice, $totalWorkHoursSum, $totalLateMinutesSum,
                $totalLateHours, $totalUndertimeHours, $totalOtHours, $totalAbsentDays,
                $grossPay, $totalOtPay,
                $totalLateDeduct, $totalUtDeduct, $totalAbsentDeduct,
                $sssContribution, $philhealthContrib, $pagibigContrib,
                $grossPay + $totalOtPay, $totalDeductions, $netPay,
                $trainingsCount, $paymentPerTrainee, $trainingsCost, $daysOffice,
                $createdBy, $existingComp['id']
            ]);
        } else {
            // Create new payroll computation
            $stmt = $pdo->prepare("
                INSERT INTO payroll_computations (
                    employee_id, payroll_period_id, basic_monthly_salary,
                    per_day_rate, per_hour_rate, per_minute_rate,
                    total_work_days, total_work_hours, total_late_minutes,
                    total_late_hours, total_undertime_hours, total_ot_hours, total_absent_days,
                    basic_pay, ot_pay, late_deduction, undertime_deduction, absent_deduction,
                    sss_contribution, philhealth_contribution, pagibig_contribution,
                    total_earnings, total_deductions, net_pay,
                    trainings_count, payment_per_trainee, trainings_cost, days_office,
                    status, computed_by, computed_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'computed', ?, NOW(), NOW())
            ");
            $stmt->execute([
                $employeeId, $payrollPeriodId, $salary,
                $perDayRate, $perHourRate, $perMinuteRate,
                $daysOffice, $totalWorkHoursSum, $totalLateMinutesSum,
                $totalLateHours, $totalUndertimeHours, $totalOtHours, $totalAbsentDays,
                $grossPay, $totalOtPay, $totalLateDeduct, $totalUtDeduct, $totalAbsentDeduct,
                $sssContribution, $philhealthContrib, $pagibigContrib,
                $grossPay + $totalOtPay, $totalDeductions, $netPay,
                $trainingsCount, $paymentPerTrainee, $trainingsCost, $daysOffice,
                $createdBy
            ]);
        }
    }
    
    // Log the DTR action
    logDTRAction(
        $_SESSION['user_id'],
        $_SESSION['username'],
        'Saved',
        $employeeName,
        "{$savedCount} records saved",
        $pdo
    );
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'DTR saved successfully',
        'employee_id' => $employeeId,
        'employee_code' => $employeeCode,
        'employee_name' => $employeeName,
        'records_count' => $savedCount
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Save DTR Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Generate next available employee code
 * Format: EMP-XXXXX (e.g., EMP-00001, EMP-00002)
 */
function generateNextEmployeeCode($pdo) {
    $stmt = $pdo->query("SELECT employee_code FROM employees WHERE employee_code LIKE 'EMP-%' ORDER BY employee_code DESC LIMIT 1");
    $result = $stmt->fetch();
    
    if ($result && preg_match('/EMP-(\d+)/', $result['employee_code'], $matches)) {
        $nextNum = intval($matches[1]) + 1;
    } else {
        $nextNum = 1;
    }
    
    return 'EMP-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
}

/**
 * Parse time from 12h format to 24h format for database
 */
function parseTimeFor24h($timeStr) {
    if (empty($timeStr)) return null;
    
    $timeStr = trim($timeStr);
    
    // Already in 24h format (HH:MM or HH:MM:SS)
    if (preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $timeStr, $matches)) {
        $hours = intval($matches[1]);
        $mins = $matches[2];
        return sprintf('%02d:%s:00', $hours, $mins);
    }
    
    // 12h format with AM/PM
    if (preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $timeStr, $matches)) {
        $hours = intval($matches[1]);
        $mins = $matches[2];
        $period = strtoupper($matches[3]);
        
        if ($period === 'PM' && $hours !== 12) {
            $hours += 12;
        } elseif ($period === 'AM' && $hours === 12) {
            $hours = 0;
        }
        
        return sprintf('%02d:%s:00', $hours, $mins);
    }
    
    return null;
}
