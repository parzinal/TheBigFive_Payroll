<?php
/**
 * Get Employee Payslips API
 * Returns all payslips for a specific employee
 */

require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

/**
 * Returns true when a DTR remark should be treated as "other deduction"
 * instead of a government contribution/tax label.
 */
function isOtherDeductionRemark(string $remark): bool
{
    $remark = strtoupper(trim($remark));
    if ($remark === '') {
        return false;
    }

    $governmentMarkers = [
        'SSS',
        'PHILHEALTH',
        'PHIL HEALTH',
        'PAGIBIG',
        'PAG-IBIG',
        'HDMF',
        'WITHHOLD',
        'TAX',
    ];

    foreach ($governmentMarkers as $marker) {
        if (strpos($remark, $marker) !== false) {
            return false;
        }
    }

    return true;
}

/**
 * Collect DTR deduction metadata (all remarks + derived other deduction total)
 * for a payslip's employee/period, optionally scoped by cutoff.
 */
function collectPayslipDtrMetadata(PDO $pdo, int $employeeId, int $periodId, ?string $cutoffType = null): array
{
    $sql = "
        SELECT remarks, govt_deduct
        FROM dtr_records
        WHERE employee_id = :employee_id
          AND payroll_period_id = :period_id
    ";

    if ($cutoffType === 'first') {
        $sql .= " AND DAY(dtr_date) <= 15";
    } elseif ($cutoffType === 'second') {
        $sql .= " AND DAY(dtr_date) >= 16";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':employee_id' => $employeeId,
        ':period_id' => $periodId,
    ]);

    $remarks = [];
    $otherDeduction = 0.0;
    $withholdingTax = 0.0;
    $sss = 0.0;
    $philhealth = 0.0;
    $pagibig = 0.0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $remark = trim((string)($row['remarks'] ?? ''));
        $govtDeduct = (float)($row['govt_deduct'] ?? 0);

        if ($remark !== '' && !in_array($remark, $remarks, true)) {
            $remarks[] = $remark;
        }

        if ($govtDeduct > 0 && $remark !== '') {
            $upper = strtoupper($remark);
            if (strpos($upper, 'PHILHEALTH') !== false || strpos($upper, 'PHIL HEALTH') !== false) {
                $philhealth += $govtDeduct;
            } elseif (strpos($upper, 'PAGIBIG') !== false || strpos($upper, 'PAG-IBIG') !== false || strpos($upper, 'HDMF') !== false) {
                $pagibig += $govtDeduct;
            } elseif (strpos($upper, 'WITHHOLD') !== false || strpos($upper, 'TAX') !== false) {
                $withholdingTax += $govtDeduct;
            } elseif (strpos($upper, 'SSS') !== false) {
                $sss += $govtDeduct;
            } elseif (isOtherDeductionRemark($remark)) {
                $otherDeduction += $govtDeduct;
            }
        }
    }

    return [
        'dtr_remarks' => implode(' | ', $remarks),
        'dtr_remarks_list' => $remarks,
        'dtr_other_deduction' => round($otherDeduction, 2),
        'dtr_withholding_tax' => round($withholdingTax, 2),
        'dtr_sss' => round($sss, 2),
        'dtr_philhealth' => round($philhealth, 2),
        'dtr_pagibig' => round($pagibig, 2),
    ];
}

// H1: Require admin or staff role
if (!isAuthenticated() || (!isAdmin() && !isStaff())) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get employee ID from request
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$period_start = isset($_GET['period_start']) ? $_GET['period_start'] : null;
$period_end = isset($_GET['period_end']) ? $_GET['period_end'] : null;

if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Build query with optional period filtering
    $sql = "
        SELECT 
            pc.id,
            pc.employee_id,
            pc.payroll_period_id,
            e.employee_code,
            e.full_name as employee_name,
            e.position,
            e.department,
            pp.period_name,
            pp.start_date,
            pp.end_date,
            pp.pay_date,
            pc.basic_monthly_salary,
            pc.per_day_rate,
            pc.per_hour_rate,
            pc.total_work_days,
            pc.total_work_hours,
            pc.total_late_hours,
            pc.total_undertime_hours,
            pc.total_ot_hours,
            pc.total_absent_days,
            pc.basic_pay,
            pc.ot_pay,
            pc.late_deduction,
            pc.undertime_deduction,
            pc.absent_deduction,
            pc.cash_advance,
            pc.sss_contribution,
            pc.philhealth_contribution,
            pc.pagibig_contribution,
            pc.withholding_tax,
            pc.other_deductions,
            pc.total_earnings,
            pc.total_deductions,
            pc.net_pay,
            pc.trainings_cost,
            pc.training_amount,
            pc.status,
            pc.created_at,
            pc.computed_at,
            pc.approved_at,
            pc.paid_at,
            pc.other_deductions_notes
        FROM payroll_computations pc
        INNER JOIN employees e ON pc.employee_id = e.id
        INNER JOIN payroll_periods pp ON pc.payroll_period_id = pp.id
        WHERE pc.employee_id = :employee_id
        AND pc.status IN ('computed', 'approved', 'paid')
    ";
    
    $params = [':employee_id' => $employee_id];
    
    // Add period filtering if provided
    if ($period_start && $period_end) {
        $sql .= " AND pp.start_date = :period_start AND pp.end_date = :period_end";
        $params[':period_start'] = $period_start;
        $params[':period_end'] = $period_end;
    }
    
    $sql .= " ORDER BY pc.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($payslips as &$payslip) {
        $notes = [];
        if (!empty($payslip['other_deductions_notes'])) {
            $decodedNotes = json_decode($payslip['other_deductions_notes'], true);
            if (is_array($decodedNotes)) {
                $notes = $decodedNotes;
            }
        }

        $cutoffType = $notes['cutoff_type'] ?? null;
        $metadata = collectPayslipDtrMetadata(
            $pdo,
            (int)$payslip['employee_id'],
            (int)$payslip['payroll_period_id'],
            in_array($cutoffType, ['first', 'second'], true) ? $cutoffType : null
        );

        // Always prefer live DTR values when available.
        if (!empty(trim((string)$metadata['dtr_remarks']))) {
            $notes['dtr_remarks'] = $metadata['dtr_remarks'];
        }
        if ((float)$metadata['dtr_other_deduction'] > 0) {
            $notes['dtr_other_deduction'] = $metadata['dtr_other_deduction'];
        }
        if (!empty($metadata['dtr_remarks_list']) && is_array($metadata['dtr_remarks_list'])) {
            $notes['dtr_remarks_list'] = $metadata['dtr_remarks_list'];
            $notes['dtr_remarks'] = implode(' | ', $metadata['dtr_remarks_list']);
        }

        if ((float)$metadata['dtr_withholding_tax'] > 0) {
            $notes['dtr_withholding_tax'] = (float)$metadata['dtr_withholding_tax'];
            $payslip['withholding_tax'] = (float)$metadata['dtr_withholding_tax'];
        }
        if ((float)$metadata['dtr_sss'] > 0) {
            $notes['dtr_sss'] = (float)$metadata['dtr_sss'];
            $payslip['sss_contribution'] = (float)$metadata['dtr_sss'];
        }
        if ((float)$metadata['dtr_philhealth'] > 0) {
            $notes['dtr_philhealth'] = (float)$metadata['dtr_philhealth'];
            $payslip['philhealth_contribution'] = (float)$metadata['dtr_philhealth'];
        }
        if ((float)$metadata['dtr_pagibig'] > 0) {
            $notes['dtr_pagibig'] = (float)$metadata['dtr_pagibig'];
            $payslip['pagibig_contribution'] = (float)$metadata['dtr_pagibig'];
        }

        $payslip['dtr_remarks'] = (string)($notes['dtr_remarks'] ?? '');
        $payslip['dtr_remarks_list'] = (isset($notes['dtr_remarks_list']) && is_array($notes['dtr_remarks_list']))
            ? $notes['dtr_remarks_list']
            : [];
        $payslip['dtr_other_deduction'] = (float)($notes['dtr_other_deduction'] ?? 0);
        $payslip['other_deductions_notes'] = json_encode($notes);
    }
    unset($payslip);
    
    echo json_encode([
        'success' => true,
        'payslips' => $payslips,
        'count' => count($payslips)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
