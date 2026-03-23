<?php
/**
 * Export DTR Records to Excel
 * Generates a filled TB5-format Excel file for a given employee + payroll period.
 * Uses the same column structure / styling as download_dtr_template.php.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

require_once '../config/bootstrap.php';
require_once '../config/auth.php';
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

// H1: Require admin role
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(403);
    die('Unauthorized');
}

require_once '../config/database.php';

$employeeId = intval($_GET['employee_id'] ?? 0);
$periodId   = intval($_GET['period_id']   ?? 0);

if ($employeeId <= 0) {
    die('Invalid employee ID');
}

try {
    $pdo = getDBConnection();

    // ── Fetch employee info
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) die('Employee not found');

    // ── Fetch period info
    $periodInfo = null;
    if ($periodId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM payroll_periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $periodInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Fetch DTR records
    if ($periodId > 0) {
        $stmt = $pdo->prepare("
            SELECT * FROM dtr_records
            WHERE employee_id = ? AND payroll_period_id = ?
            ORDER BY dtr_date ASC
        ");
        $stmt->execute([$employeeId, $periodId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM dtr_records
            WHERE employee_id = ?
            ORDER BY dtr_date ASC
        ");
        $stmt->execute([$employeeId]);
    }
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($records)) die('No DTR records found for this period');

    // ── Fetch payroll computation (for govt deductions)
    $comp = null;
    if ($periodId > 0) {
        $stmt = $pdo->prepare("
            SELECT * FROM payroll_computations
            WHERE employee_id = ? AND payroll_period_id = ?
            LIMIT 1
        ");
        $stmt->execute([$employeeId, $periodId]);
        $comp = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

// ── Check PhpSpreadsheet
$phpSpreadsheetPath = '../vendor/autoload.php';
if (!file_exists($phpSpreadsheetPath)) {
    die('PhpSpreadsheet library not installed. Cannot generate Excel file.');
}
require_once $phpSpreadsheetPath;
if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    die('PhpSpreadsheet not available.');
}

// ── Helpers ──────────────────────────────────────────────────────────
/**
 * Convert "HH:MM" 24-hour string to Excel time decimal (fraction of a day).
 * Returns null if empty / null / "00:00".
 */
function timeToExcel(?string $t): ?float {
    if (empty($t) || trim($t) === '' || $t === '00:00') return null;
    $parts = explode(':', $t);
    if (count($parts) < 2) return null;
    $h = (int)$parts[0];
    $m = (int)$parts[1];
    if ($h === 0 && $m === 0) return null;
    return $h / 24 + $m / 1440;
}

// Group records by date for quick lookup
$recordsByDate = [];
foreach ($records as $rec) {
    $recordsByDate[$rec['dtr_date']] = $rec;
}

// Determine period start / end
$dates     = array_column($records, 'dtr_date');
$startDate = $periodInfo['start_date'] ?? min($dates);
$endDate   = $periodInfo['end_date']   ?? max($dates);
$salary    = floatval($employee['basic_monthly_salary']);

// Govt deduction amounts (from payroll_computations or fallback from remarks)
$sssAmt  = $comp ? floatval($comp['sss_contribution']        ?? 0) : 0;
$philAmt = $comp ? floatval($comp['philhealth_contribution']  ?? 0) : 0;
$pagAmt  = $comp ? floatval($comp['pagibig_contribution']     ?? 0) : 0;

// If not in payroll_computations, try to extract from dtr_records remarks
if ($sssAmt == 0 && $philAmt == 0 && $pagAmt == 0) {
    foreach ($records as $rec) {
        $rem = strtoupper(trim($rec['remarks'] ?? ''));
        $gd  = floatval($rec['govt_deduct'] ?? 0);
        if ($gd > 0) {
            if (strpos($rem, 'SSS') !== false && strpos($rem, 'PHILHEALTH') === false) $sssAmt  = $gd ?: $sssAmt;
            if (strpos($rem, 'PHILHEALTH') !== false || strpos($rem, 'PHIL HEALTH') !== false) $philAmt = $gd ?: $philAmt;
            if (strpos($rem, 'PAGIBIG') !== false || strpos($rem, 'PAG-IBIG') !== false || strpos($rem, 'HDMF') !== false) $pagAmt  = $gd ?: $pagAmt;
        }
    }
}

// ── Build spreadsheet ────────────────────────────────────────────────
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('DTR');
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

// Column widths (same as template)
$colWidths = [
    'A'=>12,'B'=>10,'C'=>10,'D'=>10,'E'=>10,'F'=>10,'G'=>10,
    'H'=>10,'I'=>10,'J'=>12,'K'=>10,'L'=>12,'M'=>8,'N'=>10,
    'O'=>12,'P'=>12,'Q'=>12,'R'=>12,'S'=>12,'T'=>14,'U'=>14,
    'V'=>12,'W'=>14,'X'=>12,'Y'=>6,'Z'=>6,'AA'=>6,'AB'=>15,
];
foreach ($colWidths as $col => $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
}

// ── ROW 1: Period ──────────────────────────────────────────────────
$periodText = date('M. d', strtotime($startDate)) . ' – ' . date('d, Y', strtotime($endDate));
$sheet->setCellValue('A1', $periodText);
$sheet->mergeCells('A1:I1');
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>11,'italic'=>true,'color'=>['rgb'=>'0066CC']],
    'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
]);

$sheet->setCellValue('N1', '↓ ↓ ↓ ↓');
$sheet->getStyle('N1')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>12,'color'=>['rgb'=>'FF0000']],
    'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
]);
$sheet->setCellValue('O1', 'INPUT BASIC MONTHLY SALARY HERE');
$sheet->mergeCells('O1:R1');
$sheet->getStyle('O1')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'FF0000']],
    'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(20);

// ── ROW 2: Employee Name + Rates ──────────────────────────────────
$sheet->setCellValue('A2', 'EMPLOYEE NAME:');
$sheet->setCellValue('B2', strtoupper($employee['full_name']));
$sheet->mergeCells('B2:G2');

$sheet->getStyle('A2')->applyFromArray([
    'font' => ['bold'=>true,'size'=>11],
    'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFF00']],
]);
$sheet->getStyle('B2:G2')->applyFromArray([
    'font' => ['bold'=>true,'size'=>12,'color'=>['rgb'=>'FF0000']],
    'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFF00']],
    'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
]);

// Time references (matching manual DTR calculator defaults)
$sheet->setCellValue('H2', 17/24 + 46/1440);
$sheet->setCellValue('I2', 8/24);              // 8:00 AM - late threshold (matches manual DTR calc)
$sheet->setCellValue('J2', 17/24);             // 5:00 PM - end time
$sheet->getStyle('H2:J2')->getNumberFormat()->setFormatCode('h:mm AM/PM');

// Salary input — Basic (reference) and Per Day (independent input driving all calculations)
$sheet->setCellValue('K2', 'BASIC');
$sheet->setCellValue('L2', '►');
$sheet->setCellValue('N2', $salary > 0 ? $salary : 13000);  // Basic salary — reference only
$effSalaryForRates = $salary > 0 ? $salary : 13000;
$perDayRate = round($effSalaryForRates / 26, 6);
$sheet->setCellValue('O2', round($perDayRate, 4));              // Per day — static value
$sheet->setCellValue('P2', round($perDayRate / 8, 4));          // Per hour — static value
$sheet->setCellValue('Q2', round($perDayRate / 480, 6));        // Per min — static value
$sheet->setCellValue('R2', 0.5);
$sheet->setCellValue('S2', 8/24 + 10/1440);
$sheet->getStyle('S2')->getNumberFormat()->setFormatCode('h:mm AM/PM');

$sheet->getStyle('K2:L2')->applyFromArray([
    'font' => ['bold'=>true,'size'=>11,'color'=>['rgb'=>'FF0000']],
    'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFF00']],
]);
$sheet->getStyle('M2:N2')->applyFromArray([
    'font' => ['bold'=>true,'size'=>11],
    'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'00FF00']],
    'numberFormat' => ['formatCode'=>'#,##0'],
]);
$sheet->getStyle('O2:Q2')->applyFromArray([
    'font' => ['size'=>10],
    'numberFormat' => ['formatCode'=>'0.00'],
    'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'E7E6E6']],
]);
$sheet->getRowDimension(2)->setRowHeight(18);

// ── ROW 3: Company name ───────────────────────────────────────────
$sheet->setCellValue('A3', 'THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.');
$sheet->mergeCells('A3:G3');
$sheet->getStyle('A3')->applyFromArray([
    'font' => ['bold'=>true,'size'=>9,'italic'=>true],
]);
$sheet->setCellValue('H3', 'ALSO UNDERTIME OR');
$sheet->setCellValue('I3', 17/24 + 45/1440);
$sheet->setCellValue('J3', 8/24  + 10/1440);   // 8:10 AM - grace period end (matches manual DTR calc)
$sheet->setCellValue('K3', 8/24  + 16/1440);
$sheet->setCellValue('L3', 'VARIABLE');
$sheet->getStyle('I3:K3')->getNumberFormat()->setFormatCode('h:mm AM/PM');
$sheet->setCellValue('O3', 'PER/DAY');
$sheet->setCellValue('P3', 'PER/HOUR');
$sheet->setCellValue('Q3', 'PER/MIN');
$sheet->setCellValue('R3', 'AUTOMATIC');
$sheet->setCellValue('S3', 8/24 + 15/1440);
$sheet->getStyle('S3')->getNumberFormat()->setFormatCode('h:mm AM/PM');
$sheet->getRowDimension(3)->setRowHeight(16);

// ── ROWS 4-5: Headers ──────────────────────────────────────────────
$mainHeaders = [
    'A4'=>'MO/YR','B4'=>'AM','D4'=>'PM','F4'=>'ABSENT','G4'=>'OT',
    'H4'=>'HALFDAY','J4'=>'TOT.WORK','K4'=>'LATE','L4'=>'UNDERTM',
    'M4'=>'OT','N4'=>'(ABSENT)','O4'=>'ABSENT','P4'=>'LATE/MIN',
    'Q4'=>'UNDERTM','R4'=>'HALFDAY','S4'=>'OT','T4'=>'neg-PTOTAL',
    'U4'=>'AUTOMATIC CALCULATIONS','X4'=>'(MANUAL)','Y4'=>'Automatic',
    'AB4'=>'REMARKS',
];
foreach ($mainHeaders as $c => $v) $sheet->setCellValue($c, $v);
foreach (['B4:C4','D4:E4','H4:I4','U4:W4'] as $m) $sheet->mergeCells($m);

$subHeaders = [
    'A5'=>'DATE','B5'=>'IN','C5'=>'OUT','D5'=>'IN','E5'=>'OUT',
    'F5'=>'(if absent)','G5'=>'OUT','H5'=>'IN','I5'=>'OUT',
    'J5'=>'(in hours)','K5'=>'(in mins)','L5'=>'(in hours)',
    'M5'=>'','N5'=>'(in day)','O5'=>'DEDUCT','P5'=>'DEDUCT',
    'Q5'=>'DEDUCT','R5'=>'DEDUCT','S5'=>'(OT PAY)','T5'=>'MINUS OT',
    'U5'=>'LATE/min','V5'=>'UNDERTIME','W5'=>'OT','X5'=>"GOV'T.",
    'Y5'=>'SALARY','Z5'=>'F1*','AA5'=>'F2*','AB5'=>'Remarks',
];
foreach ($subHeaders as $c => $v) $sheet->setCellValue($c, $v);

$sheet->getStyle('A4:AB5')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>9],
    'alignment' => [
        'horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical'  => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        'wrapText'  => true,
    ],
    'borders' => ['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
]);

// Header colour-coding
$Fill = \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID;
$hc   = [
    'B4:C5'=>'FFCC99', 'D4:E5'=>'FFCC99',
    'F4:F5'=>'FF9999', 'G4:G5'=>'99CCFF',
    'H4:I5'=>'FFFF99', 'J4:N5'=>'CCFFCC',
    'O4:T5'=>'FFCCCC', 'U4:W5'=>'CC99FF',
    'X4:X5'=>'CCFFFF', 'Y4:Y5'=>'99FF99',
];
foreach ($hc as $range => $rgb) {
    $sheet->getStyle($range)->applyFromArray([
        'fill' => ['fillType'=>$Fill, 'startColor'=>['rgb'=>$rgb]],
    ]);
}
$sheet->getRowDimension(4)->setRowHeight(20);
$sheet->getRowDimension(5)->setRowHeight(18);

// ── DATA ROWS (row 6 onwards, one row per calendar date in period) ──
$startDt  = new DateTime($startDate);
$endDt    = new DateTime($endDate);
$dataRow  = 6;
$interval = new DateInterval('P1D');
$datePeriod = new DatePeriod($startDt, $interval, (clone $endDt)->modify('+1 day'));

$blackStyle = [
    'font'      => ['color'=>['rgb'=>'FFFFFF'],'size'=>9],
    'fill'      => ['fillType'=>$Fill,'startColor'=>['rgb'=>'000000']],
    'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
];
$whiteStyle = [
    'fill'      => ['fillType'=>$Fill,'startColor'=>['rgb'=>'FFFFFF']],
    'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    'font'      => ['size'=>9],
];

foreach ($datePeriod as $dt) {
    $dateStr  = $dt->format('Y-m-d');
    $dateDisp = $dt->format('n/j');      // M/D format matching TB5
    $rec      = $recordsByDate[$dateStr] ?? null;

    $amIn  = $rec ? timeToExcel($rec['am_time_in']  ?? null) : null;
    $amOut = $rec ? timeToExcel($rec['am_time_out'] ?? null) : null;
    $pmIn  = $rec ? timeToExcel($rec['pm_time_in']  ?? null) : null;
    $pmOut = $rec ? timeToExcel($rec['pm_time_out'] ?? null) : null;
    $otOut = $rec ? timeToExcel($rec['ot_time_out'] ?? null) : null;
    $hdIn  = $rec ? timeToExcel($rec['halfday_in']  ?? null) : null;
    $hdOut = $rec ? timeToExcel($rec['halfday_out'] ?? null) : null;

    $isAbsent = $rec ? ($rec['is_absent'] == 1) : false;
    $absFlag  = $isAbsent ? 'X' : '';
    $remarks  = $rec ? ($rec['remarks'] ?? '') : '';
    $govtDed  = $rec ? floatval($rec['govt_deduct'] ?? 0) : 0;

    // Column A: Date
    $sheet->setCellValue("A{$dataRow}", $dateDisp);

    // Time input columns B-E (AM IN/OUT, PM IN/OUT)
    if ($amIn  !== null) $sheet->setCellValue("B{$dataRow}", $amIn);
    if ($amOut !== null) $sheet->setCellValue("C{$dataRow}", $amOut);
    if ($pmIn  !== null) $sheet->setCellValue("D{$dataRow}", $pmIn);
    if ($pmOut !== null) $sheet->setCellValue("E{$dataRow}", $pmOut);

    // Column F: Absent
    $sheet->setCellValue("F{$dataRow}", $absFlag);

    // Column G: OT OUT — only write actual stored OT value, no phantom formula
    if ($otOut !== null) {
        $sheet->setCellValue("G{$dataRow}", $otOut);
        $sheet->getStyle("G{$dataRow}")->getNumberFormat()->setFormatCode('h:mm');
    }

    // Columns H-I: Halfday
    if ($hdIn  !== null) $sheet->setCellValue("H{$dataRow}", $hdIn);
    if ($hdOut !== null) $sheet->setCellValue("I{$dataRow}", $hdOut);

    // Columns J-W: Write actual DB values (reliable for import) with formulas as visual fallback
    // J: Total work hours (from DB)
    $dbWorkHours = $rec ? floatval($rec['total_work_hours'] ?? 0) : 0;
    $dbLateMins  = $rec ? floatval($rec['late_minutes'] ?? 0) : 0;
    $dbUtHours   = $rec ? floatval($rec['undertime_hours'] ?? 0) : 0;
    $dbOtHours   = $rec ? floatval($rec['daily_ot_hours'] ?? 0) : 0;
    $dbNetSalary = $rec ? floatval($rec['net_salary'] ?? 0) : 0;
    $dbIsHalfday = $rec ? intval($rec['is_halfday'] ?? 0) : 0;

    $sheet->setCellValue("J{$dataRow}", $dbWorkHours);
    // K: Late in minutes (with 10-min grace period, matching manual DTR calc)
    $sheet->setCellValue("K{$dataRow}", $dbLateMins);
    // L: Undertime in hours (with lunch adjustment, matching manual DTR calc)
    $sheet->setCellValue("L{$dataRow}", $dbUtHours);
    // M: OT hours
    $sheet->setCellValue("M{$dataRow}", $dbOtHours);
    // N: Absent day count
    $sheet->setCellValue("N{$dataRow}", $isAbsent ? 1 : 0);

    // Calculate rates from salary for deduction formulas
    $effSalary = $salary > 0 ? $salary : 13000;
    $dailyRate = $effSalary / 26;
    $hourlyRate = $dailyRate / 8;
    $perMin = $hourlyRate / 60;
    $otRateVal = $hourlyRate * 1.25;

    // O: Absent deduction
    $sheet->setCellValue("O{$dataRow}", $isAbsent ? $dailyRate : 0);
    // P: Late deduction = late_minutes * per_min
    $lateDeduct = $dbLateMins * $perMin;
    $sheet->setCellValue("P{$dataRow}", round($lateDeduct, 2));
    // Q: Undertime deduction = undertime_hours * hourly_rate
    $utDeduct = $dbUtHours * $hourlyRate;
    $sheet->setCellValue("Q{$dataRow}", round($utDeduct, 2));
    // R: Halfday deduction
    $hdDeduct = $dbIsHalfday ? ($dailyRate / 2) : 0;
    $sheet->setCellValue("R{$dataRow}", round($hdDeduct, 2));
    // S: OT Pay = OT hours * OT rate
    $otPay = $dbOtHours * $otRateVal;
    $sheet->setCellValue("S{$dataRow}", round($otPay, 2));
    // T: Net deductions (OT pay minus total deductions)
    $totalDeductions = $lateDeduct + $utDeduct + $hdDeduct + ($isAbsent ? $dailyRate : 0);
    $netDeductVal = $otPay - $totalDeductions;
    $sheet->setCellValue("T{$dataRow}", round($netDeductVal, 2));

    // U-W: Automatic calculation columns (same as K, L, M)
    $sheet->setCellValue("U{$dataRow}", $dbLateMins);
    $sheet->setCellValue("V{$dataRow}", $dbUtHours);
    $sheet->setCellValue("W{$dataRow}", $dbOtHours);

    // Column X: Gov't deduction (from DB)
    if ($govtDed > 0) {
        $sheet->setCellValue("X{$dataRow}", $govtDed);
        $sheet->getStyle("X{$dataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    }

    // Column Y: Auto salary (from DB or calculated)
    $autoSalary = $dbNetSalary;
    if ($autoSalary == 0 && !$isAbsent && ($amIn !== null || $pmOut !== null)) {
        $autoSalary = $dailyRate - ($totalDeductions - $otPay);
    }
    $sheet->setCellValue("Y{$dataRow}", round($autoSalary, 2));

    // Columns Z-AA: Helper flags
    $sheet->setCellValue("Z{$dataRow}",  ($hdOut === null) ? 1 : 0);
    $sheet->setCellValue("AA{$dataRow}", ($hdOut !== null) ? 1 : 0);

    // Column AB: Remarks (actual text from DB)
    $sheet->setCellValue("AB{$dataRow}", $remarks ?: '');

    // Styles
    $sheet->getStyle("J{$dataRow}:W{$dataRow}")->applyFromArray($blackStyle);
    $sheet->getStyle("Y{$dataRow}")->applyFromArray($blackStyle);
    $sheet->getStyle("A{$dataRow}:F{$dataRow}")->applyFromArray($whiteStyle);
    $sheet->getStyle("H{$dataRow}:I{$dataRow}")->applyFromArray($whiteStyle);
    $sheet->getStyle("X{$dataRow}")->applyFromArray($whiteStyle);

    // Time formats for input columns
    $sheet->getStyle("B{$dataRow}:E{$dataRow}")->getNumberFormat()->setFormatCode('h:mm');
    $sheet->getStyle("H{$dataRow}:I{$dataRow}")->getNumberFormat()->setFormatCode('h:mm');
    $sheet->getStyle("J{$dataRow}")->getNumberFormat()->setFormatCode('0.00');
    $sheet->getStyle("K{$dataRow}")->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle("L{$dataRow}:M{$dataRow}")->getNumberFormat()->setFormatCode('0.00');
    $sheet->getStyle("O{$dataRow}:W{$dataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("Y{$dataRow}")->getNumberFormat()->setFormatCode('#,##0.00');

    // Borders per data row
    $sheet->getStyle("A{$dataRow}:AB{$dataRow}")->applyFromArray([
        'borders' => ['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ]);

    $dataRow++;
}

// ── TOTALS ROW ────────────────────────────────────────────────────
$totalRow  = $dataRow;
$lastData  = $dataRow - 1;
$firstData = 6;

$sheet->setCellValue("A{$totalRow}", 'TOTAL:');
$sheet->mergeCells("A{$totalRow}:I{$totalRow}");
$sheet->getStyle("A{$totalRow}")->applyFromArray([
    'font'      => ['bold'=>true,'size'=>11],
    'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
]);

foreach (['J','K','L','M','N','O','P','Q','R','S','T','U','V','W','Y'] as $col) {
    $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$firstData}:{$col}{$lastData})");
}
$sheet->getStyle("A{$totalRow}:AB{$totalRow}")->applyFromArray([
    'font'      => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'FFFFFF']],
    'fill'      => ['fillType'=>$Fill,'startColor'=>['rgb'=>'4472C4']],
    'borders'   => ['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]],
    'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
]);
$sheet->getStyle("J{$totalRow}")->getNumberFormat()->setFormatCode('0.00');
$sheet->getStyle("K{$totalRow}")->getNumberFormat()->setFormatCode('0');
$sheet->getStyle("L{$totalRow}:M{$totalRow}")->getNumberFormat()->setFormatCode('0.00');
$sheet->getStyle("O{$totalRow}:W{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("Y{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');

// ── NET SALARY SECTION (2 rows below totals) ──────────────────────
$r1 = $totalRow + 2;  // Basic Pay
$r2 = $r1 + 1;        // OT Pay
$r3 = $r2 + 1;        // DTR Deductions
$r4 = $r3 + 1;        // Gov't Deductions
$r5 = $r4 + 1;        // Cash Advance
$r6 = $r5 + 1;        // NET SALARY

$sheet->setCellValue("A{$r1}", 'BASIC PAY (Semi-Monthly):');
$sheet->setCellValue("B{$r1}", '=$N$2');
$sheet->setCellValue("A{$r2}", 'OT PAY:');
$sheet->setCellValue("B{$r2}", "=S{$totalRow}");
$sheet->setCellValue("A{$r3}", 'DTR DEDUCTIONS (Abs/Late/Under/Halfday):');
$sheet->setCellValue("B{$r3}", "=O{$totalRow}+P{$totalRow}+Q{$totalRow}+R{$totalRow}");
$sheet->setCellValue("A{$r4}", "GOV'T DEDUCTIONS (SSS+PhilHealth+Pag-IBIG+CA):");
$sheet->setCellValue("B{$r4}", $sssAmt + $philAmt + $pagAmt);
$sheet->setCellValue("A{$r5}", 'CASH ADVANCE / OTHER DEDUCTIONS:');
$sheet->setCellValue("B{$r5}", 0);
$sheet->setCellValue("A{$r6}", 'NET SALARY (Take Home):');
$sheet->setCellValue("B{$r6}", "=B{$r1}+B{$r2}-B{$r3}-B{$r4}-B{$r5}");
$sheet->mergeCells("B{$r6}:D{$r6}");

$sheet->getStyle("A{$r1}")->applyFromArray(['font'=>['bold'=>true,'size'=>10],'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]]);
$sheet->getStyle("B{$r1}")->applyFromArray(['font'=>['bold'=>true,'size'=>11],'fill'=>['fillType'=>$Fill,'startColor'=>['rgb'=>'E7E6E6']],'numberFormat'=>['formatCode'=>'#,##0.00']]);
$sheet->getStyle("A{$r2}:A{$r3}:A{$r4}")->applyFromArray(['alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]]);
$sheet->getStyle("B{$r2}:B{$r5}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("A{$r3}")->applyFromArray(['font'=>['size'=>10,'color'=>['rgb'=>'FF0000']],'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]]);
$sheet->getStyle("B{$r3}")->applyFromArray(['font'=>['color'=>['rgb'=>'FF0000']],'numberFormat'=>['formatCode'=>'#,##0.00']]);
$sheet->getStyle("A{$r4}")->applyFromArray(['font'=>['size'=>10,'color'=>['rgb'=>'FF0000']],'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]]);
$sheet->getStyle("B{$r4}")->applyFromArray(['font'=>['color'=>['rgb'=>'FF0000']],'numberFormat'=>['formatCode'=>'#,##0.00'],'fill'=>['fillType'=>$Fill,'startColor'=>['rgb'=>'FFCCCC']]]);
$sheet->getStyle("B{$r5}")->applyFromArray(['numberFormat'=>['formatCode'=>'#,##0.00'],'fill'=>['fillType'=>$Fill,'startColor'=>['rgb'=>'FFFFCC']]]);
$sheet->getStyle("A{$r6}")->applyFromArray(['font'=>['bold'=>true,'size'=>12,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>$Fill,'startColor'=>['rgb'=>'4472C4']],'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],'borders'=>['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]]]);
$sheet->getStyle("B{$r6}:D{$r6}")->applyFromArray(['font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>$Fill,'startColor'=>['rgb'=>'00B050']],'numberFormat'=>['formatCode'=>'#,##0.00'],'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],'borders'=>['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]]]);

// ── GOV'T CARD (right side, below data) ──────────────────────────
$govRow = $totalRow + 2;
$govLabels = ['SSS' => $sssAmt, 'PHILHEALTH' => $philAmt, 'PAGIBIG' => $pagAmt];
$gi = 0;
foreach ($govLabels as $label => $amt) {
    $gr = $govRow + $gi;
    $sheet->setCellValue("AA{$gr}", $label);
    $sheet->setCellValue("X{$gr}", $amt > 0 ? $amt : 0);
    $sheet->getStyle("AA{$gr}")->applyFromArray(['font'=>['bold'=>true,'size'=>9,'color'=>['rgb'=>'CC00CC']]]);
    $sheet->getStyle("X{$gr}")->applyFromArray(['font'=>['color'=>['rgb'=>'FF0000']],'numberFormat'=>['formatCode'=>'#,##0.00'],'fill'=>['fillType'=>$Fill,'startColor'=>['rgb'=>'FFFFFF']]]);
    $gi++;
}

// Freeze pane
$sheet->freezePane('B6');

// ── Output ────────────────────────────────────────────────────────
$empSafe  = preg_replace('/[^A-Za-z0-9_\-]/', '_', $employee['full_name']);
$filename = 'DTR_' . $empSafe . '_' . date('Y-m-d', strtotime($startDate)) . '_to_' . date('Y-m-d', strtotime($endDate)) . '.xlsx';
$tempFile = tempnam(sys_get_temp_dir(), 'dtr_exp_') . '.xlsx';

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save($tempFile);

while (ob_get_level()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: max-age=0');
header('Pragma: public');

readfile($tempFile);
@unlink($tempFile);
exit();
