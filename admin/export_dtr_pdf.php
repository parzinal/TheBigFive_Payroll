<?php
/**
 * Export DTR as PDF
 *
 * Generates a PDF containing the DTR table, training payment, and payroll summary
 * for a given employee + payroll period. Uses DomPDF.
 */

// ── Load Composer autoloader ──
$_projectRoot      = dirname(dirname(__FILE__));
$_composerAutoload = $_projectRoot . DIRECTORY_SEPARATOR . 'vendor'
                   . DIRECTORY_SEPARATOR . 'autoload.php';

if (!file_exists($_composerAutoload)) {
    http_response_code(500);
    die('Composer autoload not found. Run: composer install');
}
require_once $_composerAutoload;

// PSR-4 fallback for DomPDF
if (!class_exists('Dompdf\\Dompdf', false)) {
    spl_autoload_register(function (string $class) use ($_projectRoot): void {
        $nsMap = [
            'Dompdf\\'  => $_projectRoot . '/vendor/dompdf/dompdf/src/',
            'FontLib\\' => $_projectRoot . '/vendor/dompdf/php-font-lib/src/FontLib/',
            'Svg\\'     => $_projectRoot . '/vendor/dompdf/php-svg-lib/src/Svg/',
        ];
        foreach ($nsMap as $prefix => $baseDir) {
            if (strpos($class, $prefix) === 0) {
                $relative = substr($class, strlen($prefix));
                $file     = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                }
                return;
            }
        }
    });
}

if (!class_exists('Dompdf\\Dompdf')) {
    http_response_code(500);
    die('DomPDF not installed. Run: composer require dompdf/dompdf');
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isAuthenticated() || (!isAdmin() && !isStaff())) {
    http_response_code(401);
    die('Unauthorized');
}

$employeeId = intval($_GET['employee_id'] ?? 0);
$periodId   = intval($_GET['period_id'] ?? 0);

if ($employeeId <= 0 || $periodId <= 0) {
    die('Invalid employee ID or period ID.');
}

try {
    $pdo = getDBConnection();

    // Get employee info
    $stmt = $pdo->prepare("SELECT id, employee_code, full_name, position, department, basic_monthly_salary, classification FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) die('Employee not found.');

    // Get period info
    $stmt = $pdo->prepare("SELECT period_name, start_date, end_date FROM payroll_periods WHERE id = ?");
    $stmt->execute([$periodId]);
    $period = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get DTR records
    $stmt = $pdo->prepare(<<<'SQL'
SELECT d.dtr_date,
       TIME_FORMAT(d.am_time_in, '%H:%i') as am_time_in,
       TIME_FORMAT(d.pm_time_out, '%H:%i') as pm_time_out,
       TIME_FORMAT(d.ot_time_out, '%H:%i') as ot_time_out,
       d.is_halfday, d.is_absent, d.is_training,
       d.total_work_hours, d.late_minutes, d.undertime_hours,
       d.daily_ot_hours, d.govt_deduct, d.net_salary, d.remarks
FROM dtr_records d
WHERE d.employee_id = ? AND d.payroll_period_id = ?
ORDER BY d.dtr_date ASC
SQL
    );
    $stmt->execute([$employeeId, $periodId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get payroll computation
    $stmt = $pdo->prepare("SELECT * FROM payroll_computations WHERE employee_id = ? AND payroll_period_id = ? LIMIT 1");
    $stmt->execute([$employeeId, $periodId]);
    $comp = $stmt->fetch(PDO::FETCH_ASSOC);

    // ── Calculate rates ──
    $salary = $comp ? floatval($comp['basic_monthly_salary']) : floatval($emp['basic_monthly_salary']);
    $classification = strtolower(trim(str_replace(' ', '', $emp['classification'] ?? '')));
    $isTrainer = ($classification === 'trainer');

    if ($comp && floatval($comp['per_day_rate']) > 0) {
        $perDay  = floatval($comp['per_day_rate']);
        $perHour = floatval($comp['per_hour_rate']) ?: ($perDay / 8);
        $otRateNotes = null;
        if (!empty($comp['other_deductions_notes'])) {
            $notes = json_decode($comp['other_deductions_notes'], true);
            $otRateNotes = $notes['ot_rate'] ?? null;
        }
        $otRate = $otRateNotes ?: ($perHour * 1.25);
    } elseif ($isTrainer) {
        $perDay  = 500;
        $perHour = $perDay / 8;
        $otRate  = $perHour * 1.25;
    } else {
        $perDay  = ($salary > 0) ? $salary / 26 : 0;
        $perHour = $perDay / 8;
        $otRate  = $perHour * 1.25;
    }

    // Late equivalency rules (default is 1:1 when no valid rules are configured)
    $lateRuleItems = [];
    try {
        $lateRuleStmt = $pdo->prepare("SELECT * FROM payroll_rule_settings WHERE id = 1 LIMIT 1");
        $lateRuleStmt->execute();
        $lateRule = $lateRuleStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        for ($i = 1; $i <= 3; $i++) {
            $actualMinutes = floatval($lateRule["late_rule_{$i}_actual_minutes"] ?? 0);
            $chargedMinutes = floatval($lateRule["late_rule_{$i}_equivalent_minutes"] ?? 0);
            if ($actualMinutes > 0 && $chargedMinutes > 0) {
                $lateRuleItems[] = [
                    'actual' => $actualMinutes,
                    'multiplier' => $chargedMinutes / $actualMinutes,
                ];
            }
        }
    } catch (\Throwable $e) {
        $lateRuleItems = [];
    }

    usort($lateRuleItems, static function ($a, $b) {
        return ($a['actual'] <=> $b['actual']);
    });

    $resolveEquivalentLateMinutes = static function (float $lateMins, array $rules): float {
        if ($lateMins <= 0) {
            return 0.0;
        }
        $multiplier = 1.0;
        foreach ($rules as $rule) {
            if ($lateMins >= floatval($rule['actual'] ?? 0)) {
                $multiplier = floatval($rule['multiplier'] ?? 1.0);
            }
        }
        return $lateMins * $multiplier;
    };

    // ── Process DTR rows ──
    $totWorkHrs = 0; $totLateMins = 0; $totUtHrs = 0; $totOtHrs = 0;
    $totAbsentDays = 0; $totTrainingDays = 0; $totLateDed = 0; $totUtDed = 0;
    $totHalfDed = 0; $totOtPay = 0; $totNetSalary = 0; $daysWorked = 0;

    $rows = [];
    foreach ($records as $rec) {
        $isAbsent   = intval($rec['is_absent']) === 1;
        $isTraining = intval($rec['is_training']) === 1;
        $isHalf     = intval($rec['is_halfday']) === 1;

        $workHrs  = ($isAbsent || $isTraining) ? 0 : floatval($rec['total_work_hours']);
        $lateMins = ($isAbsent || $isTraining) ? 0 : floatval($rec['late_minutes']);
        $utHrs    = ($isAbsent || $isTraining || $isHalf) ? 0 : floatval($rec['undertime_hours']);
        $otHrs    = ($isAbsent || $isTraining) ? 0 : floatval($rec['daily_ot_hours']);

        $equivalentLateMins = ($isAbsent || $isTraining) ? 0 : $resolveEquivalentLateMinutes($lateMins, $lateRuleItems);
        $lateDed  = ($isAbsent || $isTraining) ? 0 : ($equivalentLateMins / 60) * $perHour;
        $utDed    = ($isAbsent || $isTraining || $isHalf) ? 0 : $utHrs * $perHour;
        $halfDed  = $isHalf ? ($perDay / 2) : 0;
        $otPayRow = ($isAbsent || $isTraining) ? 0 : $otHrs * $otRate;

        if ($isAbsent) $rowNet = 0;
        elseif ($isTraining) $rowNet = 0;
        elseif ($isHalf) $rowNet = ($perDay / 2) - $lateDed;
        elseif ($workHrs == 0 && $otHrs == 0) $rowNet = 0;
        else $rowNet = $perDay - $lateDed - $utDed;

        if (!$isAbsent && !$isTraining && ($rec['am_time_in'] || $rec['pm_time_out'] || $workHrs > 0)) $daysWorked++;
        $totAbsentDays   += $isAbsent ? 1 : 0;
        $totTrainingDays += $isTraining ? 1 : 0;
        $totWorkHrs  += $workHrs;
        $totLateMins += $lateMins;
        $totUtHrs    += $utHrs;
        $totOtHrs    += $otHrs;
        $totLateDed  += $lateDed;
        $totUtDed    += $utDed;
        $totHalfDed  += $halfDed;
        $totOtPay    += $otPayRow;
        $totNetSalary += $rowNet;

        $rows[] = [
            'date'     => date('M d (D)', strtotime($rec['dtr_date'])),
            'am_in'    => ($isAbsent || $isTraining) ? '' : ($rec['am_time_in'] ?: ''),
            'pm_out'   => ($isAbsent || $isTraining) ? '' : ($rec['pm_time_out'] ?: ''),
            'ot_out'   => ($isAbsent || $isTraining) ? '' : ($rec['ot_time_out'] ?: ''),
            'absent'   => $isAbsent,
            'training' => $isTraining,
            'halfday'  => $isHalf,
            'work_hrs' => $workHrs,
            'late_mins'=> $lateMins,
            'ut_hrs'   => $utHrs,
            'ot_hrs'   => $otHrs,
            'late_ded' => $lateDed,
            'ut_ded'   => $utDed,
            'half_ded' => $halfDed,
            'ot_pay'   => $otPayRow,
            'net'      => $rowNet,
            'remarks'  => $rec['remarks'] ?? '',
        ];
    }

    // ── Payroll summary values ──
    $sss        = floatval($comp['sss_contribution'] ?? 0);
    $philhealth = floatval($comp['philhealth_contribution'] ?? 0);
    $pagibig    = floatval($comp['pagibig_contribution'] ?? 0);
    $totalGovt  = $sss + $philhealth + $pagibig;
    $trainingAmt     = floatval($comp['trainings_cost'] ?? $comp['training_amount'] ?? 0);
    $trainingRemarks = $comp['training_remarks'] ?? '';
    $totalAllDeduct  = $totLateDed + $totUtDed + $totHalfDed + $totalGovt;
    $finalNetPay     = $totNetSalary + $trainingAmt + $totOtPay - $totalGovt;

    // Helper
    // Use ASCII 'P' to avoid encoding issues with the peso symbol in PDFs
    function peso($v) { return 'P' . number_format($v, 2); }
    function num($v, $d = 2) { return number_format($v, $d); }

    $periodName = $period['period_name'] ?? '';
    $empName    = htmlspecialchars($emp['full_name']);

    // ── Build HTML ──
    $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 15mm 10mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 7pt; color: #1a202c; }
    .header { text-align: center; margin-bottom: 10px; }
    .header h2 { font-size: 12pt; color: #1a3a6e; margin: 0; }
    .header .sub { font-size: 8pt; color: #666; }
    .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; padding: 6px 10px; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 4px; }
    .info-row .label { font-weight: 700; color: #4a5568; font-size: 7pt; }
    .info-row .value { font-weight: 700; color: #1a202c; font-size: 7pt; }
    .rates-row { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
    .rate-box { padding: 4px 8px; border-radius: 4px; font-size: 7pt; font-weight: 700; text-align: center; }
    .rate-green { background: #c6f6d5; border: 1px solid #38a169; color: #276749; }
    .rate-red { background: #fed7d7; border: 1px solid #e53e3e; color: #c53030; }
    .rate-blue { background: #bee3f8; border: 1px solid #3182ce; color: #2b6cb0; }

    table.dtr { width: 100%; border-collapse: collapse; font-size: 6.5pt; margin-bottom: 12px; }
    table.dtr th, table.dtr td { border: 0.5px solid #a0aec0; padding: 2px 3px; text-align: center; }
    table.dtr th { font-weight: 700; font-size: 6pt; }
    .th-time { background: #ed8936; color: #fff; }
    .th-absent { background: #e53e3e; color: #fff; }
    .th-calc { background: #edf2f7; color: #2d3748; }
    .th-deduct { background: #fff5f5; color: #c53030; }
    .th-pay { background: #f0fff4; color: #276749; }
    .th-net { background: #c6f6d5; color: #276749; }
    .th-remarks { background: #f7fafc; color: #4a5568; }
    .absent-row td { background: #fff5f5 !important; }
    .training-row td { background: #fefce8 !important; }
    .halfday-row td { background: #fef9c3 !important; }
    .val-late { color: #e53e3e; font-weight: 600; }
    .val-ut { color: #dd6b20; font-weight: 600; }
    .val-ot { color: #38a169; font-weight: 600; }
    .val-deduct { color: #e53e3e; }
    .val-pay { color: #276749; font-weight: 600; }
    .val-net { font-weight: 700; }
    .val-net.positive { color: #276749; }
    .val-net.negative { color: #e53e3e; }
    .totals-row td { background: #2d3748 !important; color: #fff !important; font-weight: 700; font-size: 7pt; }

    .section { margin-top: 12px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
    .section-header { padding: 6px 12px; font-weight: 700; font-size: 8pt; color: #fff; }
    .section-header.training { background: #6366f1; }
    .section-header.summary { background: #2563eb; }
    .section-body { padding: 8px 12px; }
    .section-body table { width: 100%; font-size: 7.5pt; border-collapse: collapse; }
    .section-body table td { padding: 3px 8px; border-bottom: 1px solid #edf2f7; }
    .section-body table td:last-child { text-align: right; font-weight: 600; }

    .summary-grid { display: flex; gap: 20px; }
    .summary-col { flex: 1; }
    .summary-col h4 { font-size: 7.5pt; color: #e53e3e; margin-bottom: 4px; padding-bottom: 3px; border-bottom: 1px solid #e2e8f0; }
    .neg { color: #e53e3e; }
    .pos { color: #276749; }
    .final-row td { background: #38a169 !important; color: #fff !important; font-weight: 700; font-size: 9pt; padding: 6px 12px !important; }
</style>
</head>
<body>
HTML;

    // Header
    $html .= '<div class="header">';
    $html .= '<h2>DAILY TIME RECORD</h2>';
    $html .= '<div class="sub">THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.</div>';
    $html .= '</div>';

    // Info row - use a table for DomPDF compatibility
    $html .= '<table style="width:100%; margin-bottom:8px; font-size:7.5pt; border:1px solid #e2e8f0; border-radius:4px; background:#f7fafc;"><tr>';
    $html .= '<td style="padding:5px 10px;"><b>Employee:</b> ' . $empName . '</td>';
    $html .= '<td style="padding:5px 10px;"><b>Position:</b> ' . htmlspecialchars($emp['position'] ?? '') . '</td>';
    $html .= '<td style="padding:5px 10px;"><b>Period:</b> ' . htmlspecialchars($periodName) . '</td>';
    $html .= '</tr></table>';

    // Rates row
    $html .= '<table style="width:100%; margin-bottom:10px; font-size:7pt;"><tr>';
    if (!$isTrainer) {
        $html .= '<td style="padding:3px;"><span class="rate-box rate-green">Basic Salary: ' . peso($salary) . '</span></td>';
    }
    $html .= '<td style="padding:3px;"><span class="rate-box rate-red">Per/Day: ' . peso($perDay) . '</span></td>';
    $html .= '<td style="padding:3px;"><span class="rate-box rate-blue">Per/Hour: ' . peso($perHour) . '</span></td>';
    $html .= '<td style="padding:3px;"><span class="rate-box rate-blue">OT Rate: ' . peso($otRate) . '</span></td>';
    $html .= '</tr></table>';

    // DTR Table
    $html .= '<table class="dtr">';
    $html .= '<thead><tr>';
    $html .= '<th class="th-time" style="width:12%">DATE</th>';
    $html .= '<th class="th-time">AM IN</th>';
    $html .= '<th class="th-time">PM OUT</th>';
    $html .= '<th class="th-absent">ABS</th>';
    $html .= '<th class="th-absent" style="font-size:5pt">TRNG</th>';
    $html .= '<th class="th-time">OT OUT</th>';
    $html .= '<th class="th-calc">Work Hrs</th>';
    $html .= '<th class="th-calc">Late Min</th>';
    $html .= '<th class="th-calc">UT Hrs</th>';
    $html .= '<th class="th-calc">OT Hrs</th>';
    $html .= '<th class="th-deduct">Late Ded</th>';
    $html .= '<th class="th-deduct">UT Ded</th>';
    $html .= '<th class="th-deduct">Half Ded</th>';
    $html .= '<th class="th-pay">OT Pay</th>';
    $html .= '<th class="th-net">Net Salary</th>';
    $html .= '<th class="th-remarks">Remarks</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $r) {
        $cls = '';
        if ($r['absent']) $cls = 'absent-row';
        elseif ($r['training']) $cls = 'training-row';
        elseif ($r['halfday']) $cls = 'halfday-row';

        $netCls = $r['net'] >= 0 ? 'positive' : 'negative';

        $html .= '<tr class="' . $cls . '">';
        $html .= '<td style="text-align:left; font-weight:600;">' . $r['date'] . '</td>';
        $html .= '<td>' . $r['am_in'] . '</td>';
        $html .= '<td>' . $r['pm_out'] . '</td>';
        $html .= '<td>' . ($r['absent'] ? '✓' : '') . '</td>';
        $html .= '<td>' . ($r['training'] ? '✓' : '') . '</td>';
        $html .= '<td>' . $r['ot_out'] . '</td>';
        $html .= '<td>' . ($r['work_hrs'] > 0 ? num($r['work_hrs']) : '-') . '</td>';
        $html .= '<td class="val-late">' . ($r['late_mins'] > 0 ? num($r['late_mins'], 0) : '-') . '</td>';
        $html .= '<td class="val-ut">' . ($r['ut_hrs'] > 0 ? num($r['ut_hrs']) : '-') . '</td>';
        $html .= '<td class="val-ot">' . ($r['ot_hrs'] > 0 ? num($r['ot_hrs']) : '-') . '</td>';
        $html .= '<td class="val-deduct">' . ($r['late_ded'] > 0 ? num($r['late_ded']) : '-') . '</td>';
        $html .= '<td class="val-deduct">' . ($r['ut_ded'] > 0 ? num($r['ut_ded']) : '-') . '</td>';
        $html .= '<td class="val-deduct">' . ($r['half_ded'] > 0 ? num($r['half_ded']) : '-') . '</td>';
        $html .= '<td class="val-pay">' . ($r['ot_pay'] > 0 ? num($r['ot_pay']) : '-') . '</td>';
        $html .= '<td class="val-net ' . $netCls . '">' . num($r['net']) . '</td>';
        $html .= '<td style="text-align:left; font-size:6pt;">' . htmlspecialchars($r['remarks']) . '</td>';
        $html .= '</tr>';
    }

    // Totals row
    $html .= '</tbody><tfoot><tr class="totals-row">';
    $html .= '<td colspan="6" style="text-align:right; padding-right:8px;">TOTALS:</td>';
    $html .= '<td>' . num($totWorkHrs) . '</td>';
    $html .= '<td>' . num($totLateMins, 0) . '</td>';
    $html .= '<td>' . num($totUtHrs) . '</td>';
    $html .= '<td>' . num($totOtHrs) . '</td>';
    $html .= '<td>' . num($totLateDed) . '</td>';
    $html .= '<td>' . num($totUtDed) . '</td>';
    $html .= '<td>' . num($totHalfDed) . '</td>';
    $html .= '<td>' . num($totOtPay) . '</td>';
    $html .= '<td>' . num($totNetSalary) . '</td>';
    $html .= '<td></td>';
    $html .= '</tr></tfoot></table>';

    // Training Payment section
    $html .= '<div class="section">';
    $html .= '<div class="section-header training">Training Payment</div>';
    $html .= '<div class="section-body"><table>';
    $html .= '<tr><td>Amount</td><td>' . peso($trainingAmt) . '</td></tr>';
    if ($trainingRemarks) {
        $html .= '<tr><td>Remarks</td><td>' . htmlspecialchars($trainingRemarks) . '</td></tr>';
    }
    $html .= '</table></div></div>';

    // Payroll Summary section
    $html .= '<div class="section">';
    $html .= '<div class="section-header summary">Payroll Summary</div>';
    $html .= '<div class="section-body">';

    // Use a two-column table layout for DomPDF
    $html .= '<table style="width:100%;"><tr>';

    // Left column
    $html .= '<td style="width:50%; vertical-align:top; padding-right:10px; border-right:1px solid #e2e8f0;">';
    $html .= '<table style="width:100%;">';
    $html .= '<tr><td>Days Worked</td><td>' . $daysWorked . ' days</td></tr>';
    $html .= '<tr><td>Absent</td><td>' . $totAbsentDays . ' days</td></tr>';
    $html .= '<tr><td>Training</td><td>' . $totTrainingDays . ' days</td></tr>';
    $html .= '<tr><td>Late Deduction</td><td class="neg">-' . peso($totLateDed) . '</td></tr>';
    $html .= '<tr><td>Undertime Deduction</td><td class="neg">-' . peso($totUtDed) . '</td></tr>';
    $html .= '<tr><td>Halfday Deduction</td><td class="neg">-' . peso($totHalfDed) . '</td></tr>';
    $html .= '<tr><td>OT Pay</td><td class="pos">+' . peso($totOtPay) . '</td></tr>';
    $html .= '<tr><td>Net Salary</td><td>' . peso($totNetSalary) . '</td></tr>';
    $html .= '<tr><td>Training Payment</td><td class="pos">+' . peso($trainingAmt) . '</td></tr>';
    $html .= '</table></td>';

    // Right column
    $html .= '<td style="width:50%; vertical-align:top; padding-left:10px;">';
    $html .= '<table style="width:100%;">';
    $html .= '<tr><td colspan="2" style="font-weight:700; color:#e53e3e; border-bottom:1px solid #e2e8f0; padding-bottom:3px;">Government Deductions</td></tr>';
    $html .= '<tr><td>SSS</td><td class="neg">-' . peso($sss) . '</td></tr>';
    $html .= '<tr><td>PhilHealth</td><td class="neg">-' . peso($philhealth) . '</td></tr>';
    $html .= '<tr><td>Pag-IBIG</td><td class="neg">-' . peso($pagibig) . '</td></tr>';
    $html .= '<tr><td style="font-weight:700;">Total Deduction</td><td class="neg" style="font-weight:700;">-' . peso($totalAllDeduct) . '</td></tr>';
    $html .= '</table></td>';

    $html .= '</tr></table>';

    // Final Net Pay
    $html .= '<table style="width:100%; margin-top:8px;"><tr class="final-row">';
    $html .= '<td>FINAL NET PAY</td><td>' . peso($finalNetPay) . '</td>';
    $html .= '</tr></table>';

    $html .= '</div></div>';

    $html .= '</body></html>';

    // ── Render PDF ──
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'Helvetica');
    $options->set('chroot', $_projectRoot);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $emp['full_name']);
    $filename = 'DTR_' . $safeName . '_' . ($period['period_name'] ?? date('Y-m')) . '.pdf';

    $dompdf->stream($filename, ['Attachment' => true]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Error generating DTR PDF: ' . htmlspecialchars($e->getMessage()));
}
