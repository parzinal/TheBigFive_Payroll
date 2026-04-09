<?php
/**
 * Save Cutoff Payslip
 *
 * GET  ?check=1&employee_id=N&period_id=N  → check which cutoffs have payslips
 * POST (JSON body)                          → save a cutoff payslip record
 */

@ini_set('display_errors', 0);
@ini_set('html_errors', 0);
error_reporting(0);
ob_start();

header('Content-Type: application/json');

set_exception_handler(function ($e) {
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    exit;
});

require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/csrf.php';

// Allow admin and staff roles
if (!isAuthenticated() || (!isAdmin() && !isStaff())) {
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();

// GET check for existing cutoffs
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['check'])) {
    $employeeId = intval($_GET['employee_id'] ?? 0);
    $periodId   = intval($_GET['period_id']   ?? 0);

    if ($employeeId <= 0 || $periodId <= 0) {
        while (ob_get_level() > 0) ob_end_clean();
        echo json_encode(['success' => false, 'first' => false, 'second' => false]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT other_deductions_notes FROM payroll_computations WHERE employee_id = ? AND payroll_period_id = ? AND other_deductions_notes LIKE '%\"cutoff_type\"%' ");
    $stmt->execute([$employeeId, $periodId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $first  = false;
    $second = false;
    foreach ($rows as $row) {
        $notes = json_decode($row['other_deductions_notes'] ?? '{}', true);
        if (($notes['cutoff_type'] ?? '') === 'first')  $first  = true;
        if (($notes['cutoff_type'] ?? '') === 'second') $second = true;
    }

    $existing = [];
    if ($first)  $existing[] = 'first';
    if ($second) $existing[] = 'second';

    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => true, 'first' => $first, 'second' => $second, 'existing_cutoffs' => $existing]);
    exit;
}

// Only accept POST for saving a cutoff
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// CSRF check
if (!validateCSRFToken()) {
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['employee_id']) || empty($data['period_id']) || empty($data['cutoff_type'])) {
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}
$employeeId  = intval($data['employee_id']);
$periodId    = intval($data['period_id']);
$cutoffType  = in_array($data['cutoff_type'], ['first', 'second']) ? $data['cutoff_type'] : null;
$otherDeductions = floatval($data['other_deductions'] ?? 0);
$deductionRemarks = trim((string)($data['deduction_remarks'] ?? ''));
$deductionRemarksList = $data['deduction_remarks_list'] ?? [];
if (!is_array($deductionRemarksList)) {
    $deductionRemarksList = [];
}
$deductionRemarksList = array_values(array_unique(array_filter(array_map(static function ($value) {
    return trim((string)$value);
}, $deductionRemarksList), static function ($value) {
    return $value !== '';
})));
if ($deductionRemarks === '' && !empty($deductionRemarksList)) {
    $deductionRemarks = implode(' | ', $deductionRemarksList);
}

if (!$cutoffType) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid cutoff type']);
    exit;
}

// Build notes JSON (carries cutoff_type + ot_rate for payslip PDF)
$notes = [
    'cutoff_type'  => $cutoffType,
    'cutoff_label' => $data['cutoff_label'] ?? ($cutoffType === 'first' ? '1st Cutoff (Prev 28 - Curr 12)' : '2nd Cutoff (Curr 13 - 27)'),
    'ot_rate'      => floatval($data['ot_rate'] ?? 0),
    'source_computation_id' => intval($data['source_computation_id'] ?? 0),
    'dtr_remarks' => $deductionRemarks,
    'dtr_remarks_list' => $deductionRemarksList,
    'dtr_other_deduction' => $otherDeductions,
    'dtr_withholding_tax' => floatval($data['withholding_tax'] ?? 0),
    'dtr_sss' => floatval($data['sss_contribution'] ?? 0),
    'dtr_philhealth' => floatval($data['philhealth_contribution'] ?? 0),
    'dtr_pagibig' => floatval($data['pagibig_contribution'] ?? 0),
    'dtr_data' => [
        'late_deduct'      => floatval($data['late_deduct'] ?? 0),
        'undertime_deduct' => floatval($data['undertime_deduct'] ?? 0),
        'halfday_deduct'   => floatval($data['halfday_deduct'] ?? 0),
        'absent_deduct'    => floatval($data['absent_deduct'] ?? 0),
    ],
];

try {
    $pdo->beginTransaction();

    // Delete any existing cutoff payslip for the same employee + period + cutoff_type
    // (idempotent regeneration — "clear and replace")
    $delStmt = $pdo->prepare("
        DELETE FROM payroll_computations
        WHERE employee_id = ? AND payroll_period_id = ?
          AND other_deductions_notes LIKE ?
    ");
    $cutoffPattern = '%"cutoff_type":"' . $cutoffType . '"%';
    $delStmt->execute([$employeeId, $periodId, $cutoffPattern]);

        // (No deletion) We'll update any existing payroll_computations row for employee+period
        // instead of deleting cutoff rows to avoid creating duplicate entries.
    $basicPay      = floatval($data['basic_pay']      ?? 0);
    $otPay         = floatval($data['ot_pay']          ?? 0);
    $lateDed       = floatval($data['late_deduct']     ?? 0);
    $utDed         = floatval($data['undertime_deduct'] ?? 0);
    $absentDed     = floatval($data['absent_deduct']   ?? 0);
    $halfDed       = floatval($data['halfday_deduct']  ?? 0);
    $withholdingTax = floatval($data['withholding_tax'] ?? 0);
    $sss           = floatval($data['sss_contribution'] ?? 0);
    $philhealth    = floatval($data['philhealth_contribution'] ?? 0);
    $pagibig       = floatval($data['pagibig_contribution'] ?? 0);
    $trainCost     = floatval($data['trainings_cost']  ?? 0);
    $totalEarnings = floatval($data['total_earnings']  ?? ($basicPay + $otPay + $trainCost));
    $totalDed      = floatval($data['total_deductions'] ?? ($halfDed + $withholdingTax + $sss + $philhealth + $pagibig));
    $netPay        = floatval($data['net_pay']          ?? ($totalEarnings - $totalDed));
    $daysWorked    = intval($data['days_worked']        ?? 0);
    $absentDays    = intval($data['absent_days']        ?? 0);
    $perDay        = floatval($data['per_day_rate']     ?? 0);
    $perHour       = floatval($data['per_hour_rate']    ?? ($perDay / 8));
    $perMinute     = $perHour / 60.0;
    $totalOtHours  = floatval($data['total_ot_hours']   ?? 0);
    $totalWorkHrs  = floatval($data['total_work_hours'] ?? 0);
    $lateMins      = floatval($data['late_minutes']     ?? 0);
    $utHrs         = floatval($data['undertime_hours']  ?? 0);

    // Fetch employee's basic_monthly_salary
    $empStmt = $pdo->prepare("SELECT basic_monthly_salary FROM employees WHERE id = ?");
    $empStmt->execute([$employeeId]);
    $basicMonthlySalary = floatval($empStmt->fetchColumn() ?? 0);

    $userId = $_SESSION['user_id'] ?? 0;

    // Check if a cutoff payroll computation already exists for this employee+period
    // Only look for rows that are cutoff-specific (do not match full-month computations)
    $checkStmt = $pdo->prepare("SELECT id FROM payroll_computations WHERE employee_id = ? AND payroll_period_id = ? AND other_deductions_notes LIKE ? LIMIT 1");
    $checkStmt->execute([$employeeId, $periodId, $cutoffPattern]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        // Check if any payroll computation exists for this employee+period (full-month or cutoff)
        $checkStmt = $pdo->prepare("SELECT id, other_deductions_notes FROM payroll_computations WHERE employee_id = ? AND payroll_period_id = ? LIMIT 1");
        $checkStmt->execute([$employeeId, $periodId]);
        $existingRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $existing = $existingRow;

    if ($existing) {
        // Update existing computation (idempotent regeneration)
        $updateStmt = $pdo->prepare(
            "UPDATE payroll_computations SET
                basic_monthly_salary = ?,
                per_day_rate = ?, per_hour_rate = ?, per_minute_rate = ?,
                total_work_days = ?, total_work_hours = ?,
                total_late_minutes = ?, total_late_hours = ?, total_undertime_hours = ?, total_ot_hours = ?,
                total_absent_days = ?,
                basic_pay = ?, ot_pay = ?,
                late_deduction = ?, undertime_deduction = ?, absent_deduction = ?,
                withholding_tax = ?, sss_contribution = ?, philhealth_contribution = ?, pagibig_contribution = ?,
                other_deductions = ?,
                trainings_cost = ?, training_amount = ?,
                total_earnings = ?, total_deductions = ?, net_pay = ?,
                    other_deductions_notes = ?,
                status = 'computed', computed_by = ?, computed_at = NOW(), updated_at = NOW()
             WHERE id = ?"
        );

            // Merge previous notes (if any) with cutoff notes so we don't lose existing metadata
            $prevNotes = json_decode($existingRow['other_deductions_notes'] ?? '{}', true) ?: [];
            // If the existing row is a full-month computation (no cutoff_type), avoid tagging it as a cutoff
            if (!isset($prevNotes['cutoff_type'])) {
                // Remove cutoff markers from $notes before merging so we don't convert the full-month row into a cutoff row
                $notesNoCutoff = $notes;
                unset($notesNoCutoff['cutoff_type'], $notesNoCutoff['cutoff_label'], $notesNoCutoff['ot_rate']);
                // Mark that a payslip was generated so Payslip History can pick it up
                $notesNoCutoff['payslip_generated'] = true;
                $notesNoCutoff['generated_at'] = date('c');
                $notesNoCutoff['generated_by'] = $userId;
                $mergedNotes = array_merge($prevNotes, $notesNoCutoff);
            } else {
                $mergedNotes = array_merge($prevNotes, $notes);
            }

            $updateParams = [
            $basicMonthlySalary,
            $perDay, $perHour, $perMinute,
            $daysWorked, $totalWorkHrs,
            $lateMins, round($lateMins / 60, 4), $utHrs, $totalOtHours,
            $absentDays,
            $basicPay, $otPay,
            $lateDed, $utDed, $absentDed,
            $withholdingTax,
            $sss, $philhealth, $pagibig,
            $otherDeductions,
            $trainCost, $trainCost,
            $totalEarnings, $totalDed, $netPay,
                json_encode($mergedNotes),
            $userId,
            $existing['id']
        ];

        try {
            $updateStmt->execute($updateParams);
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Persist debug info to a log file for inspection
            $log = date('c') . " | UPDATE ERROR | " . $e->getMessage() . "\n";
            $log .= "QUERY: " . $updateStmt->queryString . "\n";
            $log .= "PARAMS: " . json_encode($updateParams) . "\n\n";
            @file_put_contents(__DIR__ . '/../debug_cutoff_payslip.log', $log, FILE_APPEND);
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'message' => 'DB update error on cutoff payslip',
                'error' => $e->getMessage(),
                'query' => $updateStmt->queryString,
                'params' => $updateParams
            ]);
            exit;
        }

        $payslipId = (int)$existing['id'];
    } else {
        $insStmt = $pdo->prepare(
            "INSERT INTO payroll_computations (
                employee_id, payroll_period_id, basic_monthly_salary,
                per_day_rate, per_hour_rate, per_minute_rate,
                total_work_days, total_work_hours,
                total_late_minutes, total_late_hours, total_undertime_hours, total_ot_hours,
                total_absent_days,
                basic_pay, ot_pay,
                late_deduction, undertime_deduction, absent_deduction,
                withholding_tax, sss_contribution, philhealth_contribution, pagibig_contribution,
                other_deductions,
                trainings_cost, training_amount,
                total_earnings, total_deductions, net_pay,
                other_deductions_notes,
                status, computed_at, computed_by, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'computed', NOW(), ?, NOW(), NOW()
            )"
        );

        $insParams = [
            $employeeId, $periodId, $basicMonthlySalary,
            $perDay, $perHour, $perMinute,
            $daysWorked, $totalWorkHrs,
            $lateMins, round($lateMins / 60, 4), $utHrs, $totalOtHours,
            $absentDays,
            $basicPay, $otPay,
            $lateDed, $utDed, $absentDed,
            $withholdingTax,
            $sss, $philhealth, $pagibig,
            $otherDeductions,
            $trainCost, $trainCost,
            $totalEarnings, $totalDed, $netPay,
            json_encode($notes),
            $userId,
        ];

        try {
            $insStmt->execute($insParams);
            $payslipId = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Persist debug info to a log file for inspection
            $log = date('c') . " | INSERT ERROR | " . $e->getMessage() . "\n";
            $log .= "QUERY: " . $insStmt->queryString . "\n";
            $log .= "PARAMS: " . json_encode($insParams) . "\n\n";
            @file_put_contents(__DIR__ . '/../debug_cutoff_payslip.log', $log, FILE_APPEND);
            ob_end_clean();
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'message' => 'DB insert error on cutoff payslip',
                'error' => $e->getMessage(),
                'query' => $insStmt->queryString,
                'params' => $insParams
            ]);
            exit;
        }
    }

    $pdo->commit();
    ob_end_clean();
    echo json_encode(['success' => true, 'payslip_id' => $payslipId]);

} catch (Exception $e) {
    $pdo->rollBack();
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
