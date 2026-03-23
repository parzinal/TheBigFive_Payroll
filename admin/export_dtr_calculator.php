<?php
/**
 * Export DTR Calculator Data to Excel
 * 
 * Receives DTR data from the front-end DTR Calculator form via POST (JSON),
 * generates an Excel (.xlsx) file with the same TB5 layout / styling as
 * the professor's original spreadsheet and the existing export_dtr_data.php.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

require_once '../config/bootstrap.php';
require_once '../config/auth.php';
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

// Require admin role
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(403);
    die('Unauthorized');
}

// Read JSON payload
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !isset($data['rows'])) {
    http_response_code(400);
    die('Invalid data: missing rows');
}

// Extract fields
$employeeName   = trim($data['employeeName'] ?? 'Employee');
$basicSalary    = floatval($data['basicSalary'] ?? 13000);
$dailyRate      = floatval($data['dailyRate'] ?? 500);
$hourlyRate     = floatval($data['hourlyRate'] ?? 62.50);
$minuteRate     = floatval($data['minuteRate'] ?? 1.0417);
$otRate         = floatval($data['otRate'] ?? 78.13);
$lateThreshold  = trim($data['lateThreshold'] ?? '8:00');
$endThreshold   = trim($data['endThreshold'] ?? '17:00');
$periodStart    = trim($data['periodStart'] ?? '');
$periodEnd      = trim($data['periodEnd'] ?? '');
$trainingAmount = floatval($data['trainingAmount'] ?? 0);
$trainingRemarks = trim($data['trainingRemarks'] ?? '');
$rows           = $data['rows']; // array of row objects

// Check PhpSpreadsheet
$phpSpreadsheetPath = '../vendor/autoload.php';
if (!file_exists($phpSpreadsheetPath)) {
    // Try admin vendor
    $phpSpreadsheetPath = 'vendor/autoload.php';
    if (!file_exists($phpSpreadsheetPath)) {
        http_response_code(500);
        die('PhpSpreadsheet library not installed.');
    }
}
require_once $phpSpreadsheetPath;

if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    http_response_code(500);
    die('PhpSpreadsheet not available.');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// ── Helpers ──────────────────────────────────────────────────────────
/**
 * Convert "H:MM" or "HH:MM" 24-hour string to Excel time decimal (fraction of a day).
 */
function timeStrToExcel(?string $t): ?float {
    if (empty($t) || trim($t) === '') return null;
    $parts = explode(':', trim($t));
    if (count($parts) < 2) return null;
    $h = (int)$parts[0];
    $m = (int)$parts[1];
    if ($h === 0 && $m === 0) return null;
    return $h / 24 + $m / 1440;
}

// ── Build spreadsheet ────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('DTR');
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

// Column widths matching the manual DTR form columns (A-V = 22 columns)
// A=Date, B=AM IN, C=PM OUT, D=Absent, E=Training, F=OT OUT,
// G=Tot.Work, H=Late, I=Undertime, J=OT, K=Absent(days),
// L=Late Deduct, M=Undertime Deduct, N=Halfday Deduct, O=OT Pay,
// P=Minus OT Total Deductions, Q=Late/min(auto), R=Undertime(auto), S=OT(auto),
// T=Gov't Benefits, U=Net Salary, V=Remarks
$colWidths = [
    'A'=>12,'B'=>10,'C'=>10,'D'=>9,'E'=>9,'F'=>10,
    'G'=>12,'H'=>10,'I'=>12,'J'=>10,'K'=>10,
    'L'=>12,'M'=>14,'N'=>12,'O'=>12,'P'=>16,
    'Q'=>12,'R'=>12,'S'=>12,'T'=>14,'U'=>14,'V'=>18,
];
foreach ($colWidths as $col => $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
}

// ── Determine period text ──
$periodText = '';
if ($periodStart && $periodEnd) {
    $periodText = date('M. d', strtotime($periodStart)) . ' – ' . date('d, Y', strtotime($periodEnd));
} else {
    $periodText = 'DTR Calculator Export';
}

// ── ROW 1: Company + Period ──────────────────────────────────────
$sheet->setCellValue('A1', 'THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.');
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold'=>true,'size'=>9,'italic'=>true],
]);
$sheet->setCellValue('G1', $periodText);
$sheet->mergeCells('G1:L1');
$sheet->getStyle('G1')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>11,'italic'=>true,'color'=>['rgb'=>'0066CC']],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(20);

// ── ROW 2: Employee Name + Rates ──────────────────────────────────
$sheet->setCellValue('A2', 'EMPLOYEE NAME:');
$sheet->setCellValue('B2', strtoupper($employeeName));
$sheet->mergeCells('B2:F2');
$sheet->getStyle('A2')->applyFromArray([
    'font' => ['bold'=>true,'size'=>11],
    'fill' => ['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFF00']],
]);
$sheet->getStyle('B2:F2')->applyFromArray([
    'font' => ['bold'=>true,'size'=>12,'color'=>['rgb'=>'FF0000']],
    'fill' => ['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFF00']],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_LEFT],
]);

// Rate info in row 2
$sheet->setCellValue('G2', 'BASIC:');
$sheet->setCellValue('H2', $basicSalary);
$sheet->getStyle('G2')->applyFromArray([
    'font' => ['bold'=>true,'size'=>10],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_RIGHT],
]);
$sheet->getStyle('H2')->applyFromArray([
    'font' => ['bold'=>true,'size'=>11],
    'fill' => ['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'00FF00']],
    'numberFormat' => ['formatCode'=>'#,##0.00'],
]);
$sheet->setCellValue('I2', 'PER/DAY:');
$sheet->setCellValue('J2', $dailyRate);
$sheet->setCellValue('K2', 'OT RATE:');
$sheet->setCellValue('L2', $otRate);
$sheet->getStyle('I2:L2')->applyFromArray([
    'font' => ['size'=>9],
    'numberFormat' => ['formatCode'=>'0.00'],
]);
$sheet->getStyle('I2')->applyFromArray(['font'=>['bold'=>true,'size'=>9],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_RIGHT]]);
$sheet->getStyle('K2')->applyFromArray(['font'=>['bold'=>true,'size'=>9],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_RIGHT]]);

$sheet->setCellValue('M2', 'START:');
$sheet->setCellValue('N2', $lateThreshold);
$sheet->setCellValue('O2', 'END:');
$sheet->setCellValue('P2', $endThreshold);
$sheet->getStyle('M2:P2')->applyFromArray(['font'=>['size'=>9]]);
$sheet->getStyle('M2')->applyFromArray(['font'=>['bold'=>true,'size'=>9],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_RIGHT]]);
$sheet->getStyle('O2')->applyFromArray(['font'=>['bold'=>true,'size'=>9],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_RIGHT]]);
$sheet->getRowDimension(2)->setRowHeight(18);

// ── ROW 3: Blank separator ───────────────────────────────────────
$sheet->getRowDimension(3)->setRowHeight(6);

// ── ROWS 4-5: Headers matching manual DTR form exactly ────────────
// Row 4: Main headers
$mainHeaders4 = [
    'A4'=>'MO/YR',
    'B4'=>'AM IN',
    'C4'=>'PM OUT',
    'D4'=>'ABSENT',
    'E4'=>'TRAINING',
    'F4'=>'OT OUT',
    'G4'=>'TOT.WORK',
    'H4'=>'LATE',
    'I4'=>'UNDERTIME',
    'J4'=>'OT',
    'K4'=>'ABSENT',
    'L4'=>'LATE',
    'M4'=>'UNDERTIME',
    'N4'=>'HALFDAY',
    'O4'=>'OT PAY',
    'P4'=>'MINUS OT TOTAL',
    'Q4'=>'AUTOMATIC CALCULATIONS',
    'T4'=>'Government',
    'U4'=>'Net',
    'V4'=>'REMARKS',
];
foreach ($mainHeaders4 as $c => $v) $sheet->setCellValue($c, $v);
$sheet->mergeCells('Q4:S4');

// Row 5: Sub headers
$subHeaders5 = [
    'A5'=>'DATE',
    'B5'=>'',
    'C5'=>'',
    'D5'=>'',
    'E5'=>'',
    'F5'=>'',
    'G5'=>'(in hours)',
    'H5'=>'(in mins)',
    'I5'=>'(in hours)',
    'J5'=>'(in hours)',
    'K5'=>'(in days)',
    'L5'=>'DEDUCT',
    'M5'=>'DEDUCT',
    'N5'=>'DEDUCT',
    'O5'=>'',
    'P5'=>'DEDUCTIONS',
    'Q5'=>'LATE/min',
    'R5'=>'UNDERTIME',
    'S5'=>'OT',
    'T5'=>'Benefits',
    'U5'=>'Salary',
    'V5'=>'',
];
foreach ($subHeaders5 as $c => $v) $sheet->setCellValue($c, $v);

$sheet->getStyle('A4:V5')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>9],
    'alignment' => [
        'horizontal'=> Alignment::HORIZONTAL_CENTER,
        'vertical'  => Alignment::VERTICAL_CENTER,
        'wrapText'  => true,
    ],
    'borders' => ['allBorders'=>['borderStyle'=> Border::BORDER_THIN]],
]);

// Header colour-coding matching the DTR form
$hc = [
    'B4:B5'=>'FFCC99', 'C4:C5'=>'FFCC99',
    'D4:D5'=>'FF9999', 'E4:E5'=>'CCFFCC',
    'F4:F5'=>'99CCFF',
    'G4:K5'=>'CCFFCC',
    'L4:N5'=>'FFCCCC', 'O4:O5'=>'99FF99', 'P4:P5'=>'FFCCCC',
    'Q4:S5'=>'CC99FF',
    'T4:T5'=>'CCFFFF', 'U4:U5'=>'99FF99',
];
foreach ($hc as $range => $rgb) {
    $sheet->getStyle($range)->applyFromArray([
        'fill' => ['fillType'=> Fill::FILL_SOLID, 'startColor'=>['rgb'=>$rgb]],
    ]);
}
$sheet->getRowDimension(4)->setRowHeight(20);
$sheet->getRowDimension(5)->setRowHeight(18);

// ── DATA ROWS (row 6 onwards) ────────────────────────────────────
$calcStyle = [
    'font'      => ['size'=>9],
    'fill'      => ['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'F2F2F2']],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_CENTER],
];
$deductStyle = [
    'font'      => ['size'=>9,'color'=>['rgb'=>'CC0000']],
    'fill'      => ['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF2F2']],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_CENTER],
];
$autoCalcStyle = [
    'font'      => ['size'=>9,'color'=>['rgb'=>'6600CC']],
    'fill'      => ['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'F5F0FF']],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_CENTER],
];
$inputStyle = [
    'fill'      => ['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFFFF']],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_CENTER],
    'font'      => ['size'=>9],
];

$dataRow = 6;

foreach ($rows as $rowData) {
    $dateStr  = $rowData['date'] ?? '';
    $amIn     = trim($rowData['amIn'] ?? '');
    $pmOut    = trim($rowData['pmOut'] ?? '');
    $otOut    = trim($rowData['otOut'] ?? '');
    $isAbsent = !empty($rowData['absent']);
    $isTraining = !empty($rowData['training']);
    $govtDed  = floatval($rowData['govt'] ?? 0);
    $remarks  = trim($rowData['remarks'] ?? '');

    // Computed values from the form
    $workHours       = floatval($rowData['workHours'] ?? 0);
    $lateMins        = floatval($rowData['lateMins'] ?? 0);
    $undertime       = floatval($rowData['undertime'] ?? 0);
    $otHours         = floatval($rowData['otHours'] ?? 0);
    $absentDay       = floatval($rowData['absentDay'] ?? 0);
    $lateDeduct      = floatval($rowData['lateDeduct'] ?? 0);
    $undertimeDeduct = floatval($rowData['undertimeDeduct'] ?? 0);
    $halfdayDeduct   = floatval($rowData['halfdayDeduct'] ?? 0);
    $otPay           = floatval($rowData['otPay'] ?? 0);
    $netDeduct       = floatval($rowData['netDeduct'] ?? 0);
    $lateMinCalc     = floatval($rowData['lateMinCalc'] ?? 0);
    $undertimeCalc   = floatval($rowData['undertimeCalc'] ?? 0);
    $otCalc          = floatval($rowData['otCalc'] ?? 0);
    $autoSalary      = floatval($rowData['autoSalary'] ?? 0);

    // Date display (M/D format)
    $dateDisp = '';
    if ($dateStr) {
        $dt = strtotime($dateStr);
        if ($dt) {
            $dateDisp = date('n/j', $dt);
        }
    }

    // Convert times to Excel decimals
    $amInExcel  = timeStrToExcel($amIn);
    $pmOutExcel = timeStrToExcel($pmOut);
    $otOutExcel = timeStrToExcel($otOut);

    // A: Date
    $sheet->setCellValue("A{$dataRow}", $dateDisp);

    // B: AM IN
    if ($amInExcel !== null) {
        $sheet->setCellValue("B{$dataRow}", $amInExcel);
        $sheet->getStyle("B{$dataRow}")->getNumberFormat()->setFormatCode('h:mm');
    }

    // C: PM OUT
    if ($pmOutExcel !== null) {
        $sheet->setCellValue("C{$dataRow}", $pmOutExcel);
        $sheet->getStyle("C{$dataRow}")->getNumberFormat()->setFormatCode('h:mm');
    }

    // D: Absent
    $sheet->setCellValue("D{$dataRow}", $isAbsent ? 'X' : '');

    // E: Training
    $sheet->setCellValue("E{$dataRow}", $isTraining ? 'X' : '');

    // F: OT OUT
    if ($otOutExcel !== null) {
        $sheet->setCellValue("F{$dataRow}", $otOutExcel);
        $sheet->getStyle("F{$dataRow}")->getNumberFormat()->setFormatCode('h:mm');
    }

    // G: Total work hours
    $sheet->setCellValue("G{$dataRow}", $workHours);
    // H: Late (in mins)
    $sheet->setCellValue("H{$dataRow}", $lateMins);
    // I: Undertime (in hours)
    $sheet->setCellValue("I{$dataRow}", $undertime);
    // J: OT (in hours)
    $sheet->setCellValue("J{$dataRow}", $otHours);
    // K: Absent (in days)
    $sheet->setCellValue("K{$dataRow}", $absentDay);

    // L: Late deduction
    $sheet->setCellValue("L{$dataRow}", $lateDeduct);
    // M: Undertime deduction
    $sheet->setCellValue("M{$dataRow}", $undertimeDeduct);
    // N: Halfday deduction
    $sheet->setCellValue("N{$dataRow}", $halfdayDeduct);
    // O: OT Pay
    $sheet->setCellValue("O{$dataRow}", $otPay);
    // P: Minus OT Total Deductions (net deduct from form)
    $sheet->setCellValue("P{$dataRow}", $netDeduct);

    // Q: Late/min (auto calc)
    $sheet->setCellValue("Q{$dataRow}", $lateMinCalc);
    // R: Undertime (auto calc)
    $sheet->setCellValue("R{$dataRow}", $undertimeCalc);
    // S: OT (auto calc)
    $sheet->setCellValue("S{$dataRow}", $otCalc);

    // T: Gov't Benefits
    if ($govtDed > 0) {
        $sheet->setCellValue("T{$dataRow}", $govtDed);
    }

    // U: Net Salary
    $sheet->setCellValue("U{$dataRow}", $autoSalary);

    // V: Remarks
    $remarkText = $remarks;
    if ($isTraining && empty($remarkText)) {
        $remarkText = 'TRAINING';
    }
    $sheet->setCellValue("V{$dataRow}", $remarkText);

    // Styles - matching the form's visual grouping
    $sheet->getStyle("A{$dataRow}:F{$dataRow}")->applyFromArray($inputStyle);
    $sheet->getStyle("G{$dataRow}:K{$dataRow}")->applyFromArray($calcStyle);
    $sheet->getStyle("L{$dataRow}:N{$dataRow}")->applyFromArray($deductStyle);
    $sheet->getStyle("O{$dataRow}")->applyFromArray([
        'font'=>['size'=>9,'color'=>['rgb'=>'008000']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F0FFF0']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle("P{$dataRow}")->applyFromArray($calcStyle);
    $sheet->getStyle("Q{$dataRow}:S{$dataRow}")->applyFromArray($autoCalcStyle);
    $sheet->getStyle("T{$dataRow}")->applyFromArray($inputStyle);
    $sheet->getStyle("U{$dataRow}")->applyFromArray([
        'font'=>['size'=>9,'bold'=>true],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E8FFE8']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle("V{$dataRow}")->applyFromArray($inputStyle);

    // Number formats
    $sheet->getStyle("G{$dataRow}")->getNumberFormat()->setFormatCode('0.00');
    $sheet->getStyle("H{$dataRow}")->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle("I{$dataRow}:J{$dataRow}")->getNumberFormat()->setFormatCode('0.00');
    $sheet->getStyle("K{$dataRow}")->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle("L{$dataRow}:P{$dataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("Q{$dataRow}:S{$dataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("T{$dataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("U{$dataRow}")->getNumberFormat()->setFormatCode('#,##0.00');

    // Borders
    $sheet->getStyle("A{$dataRow}:V{$dataRow}")->applyFromArray([
        'borders' => ['allBorders'=>['borderStyle'=> Border::BORDER_THIN]],
    ]);

    $dataRow++;
}

// ── TOTALS ROW ────────────────────────────────────────────────────
$totalRow  = $dataRow;
$lastData  = $dataRow - 1;
$firstData = 6;

$sheet->setCellValue("A{$totalRow}", 'TOTALS:');
$sheet->mergeCells("A{$totalRow}:F{$totalRow}");
$sheet->getStyle("A{$totalRow}")->applyFromArray([
    'font'      => ['bold'=>true,'size'=>11],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_RIGHT],
]);

// Sum columns G-U (matching form tfoot)
foreach (['G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U'] as $col) {
    $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$firstData}:{$col}{$lastData})");
}
$sheet->getStyle("A{$totalRow}:V{$totalRow}")->applyFromArray([
    'font'      => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'FFFFFF']],
    'fill'      => ['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'4472C4']],
    'borders'   => ['allBorders'=>['borderStyle'=> Border::BORDER_MEDIUM]],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_CENTER],
]);
$sheet->getStyle("G{$totalRow}")->getNumberFormat()->setFormatCode('0.00');
$sheet->getStyle("H{$totalRow}")->getNumberFormat()->setFormatCode('0');
$sheet->getStyle("I{$totalRow}:J{$totalRow}")->getNumberFormat()->setFormatCode('0.00');
$sheet->getStyle("K{$totalRow}")->getNumberFormat()->setFormatCode('0');
$sheet->getStyle("L{$totalRow}:P{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("Q{$totalRow}:S{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("T{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("U{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');

// ── TRAINING PAYMENT SECTION (right below totals, matching the form's Training Payment card) ──
$trainRow = $totalRow + 1;
$sheet->setCellValue("A{$trainRow}", 'TRAINING PAYMENT');
$sheet->mergeCells("A{$trainRow}:D{$trainRow}");
$sheet->setCellValue("E{$trainRow}", 'Amount:');
$sheet->setCellValue("F{$trainRow}", $trainingAmount);
$sheet->setCellValue("G{$trainRow}", 'Remarks:');
$sheet->setCellValue("H{$trainRow}", $trainingRemarks);
$sheet->mergeCells("H{$trainRow}:L{$trainRow}");


$sheet->getStyle("A{$trainRow}:D{$trainRow}")->applyFromArray([
    'font'      => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'FFFFFF']],
    'fill'      => ['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'2196F3']],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_CENTER,'vertical'=> Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders'=>['borderStyle'=> Border::BORDER_THIN]],
]);
$sheet->getStyle("E{$trainRow}")->applyFromArray([
    'font'      => ['bold'=>true,'size'=>9],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_RIGHT],
    'borders'   => ['allBorders'=>['borderStyle'=> Border::BORDER_THIN]],
]);
$sheet->getStyle("F{$trainRow}")->applyFromArray([
    'font'      => ['bold'=>true,'size'=>11,'color'=>['rgb'=>'008000']],
    'fill'      => ['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'E8F5E9']],
    'numberFormat' => ['formatCode'=>'#,##0.00'],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_CENTER],
    'borders'   => ['allBorders'=>['borderStyle'=> Border::BORDER_THIN]],
]);
$sheet->getStyle("G{$trainRow}")->applyFromArray([
    'font'      => ['bold'=>true,'size'=>9],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_RIGHT],
    'borders'   => ['allBorders'=>['borderStyle'=> Border::BORDER_THIN]],
]);
$sheet->getStyle("H{$trainRow}:L{$trainRow}")->applyFromArray([
    'font'      => ['size'=>9,'italic'=>true],
    'alignment' => ['horizontal'=> Alignment::HORIZONTAL_LEFT],
    'borders'   => ['allBorders'=>['borderStyle'=> Border::BORDER_THIN]],
]);
$sheet->getRowDimension($trainRow)->setRowHeight(22);

// ── NET SALARY SECTION (2 rows below training row) ──────────────────────
$r1 = $trainRow + 2;  // Basic Pay
$r2 = $r1 + 1;        // OT Pay
$r3 = $r2 + 1;        // DTR Deductions
$r4 = $r3 + 1;        // Gov't Deductions
$r5 = $r4 + 1;        // Training / Cash Advance
$r6 = $r5 + 1;        // NET SALARY

$sheet->setCellValue("A{$r1}", 'BASIC PAY (Semi-Monthly):');
$sheet->setCellValue("B{$r1}", $basicSalary);
$sheet->setCellValue("A{$r2}", 'OT PAY:');
$sheet->setCellValue("B{$r2}", "=O{$totalRow}");
$sheet->setCellValue("A{$r3}", 'DTR DEDUCTIONS (Late/Under/Halfday):');
$sheet->setCellValue("B{$r3}", "=L{$totalRow}+M{$totalRow}+N{$totalRow}");

// Calculate total govt deductions from rows
$totalGovt = 0;
foreach ($rows as $rowData) {
    $totalGovt += floatval($rowData['govt'] ?? 0);
}

$sheet->setCellValue("A{$r4}", "GOV'T DEDUCTIONS (SSS+PhilHealth+Pag-IBIG):");
$sheet->setCellValue("B{$r4}", $totalGovt);

$sheet->setCellValue("A{$r5}", 'TRAINING PAYMENT:');
$sheet->setCellValue("B{$r5}", $trainingAmount);
if ($trainingRemarks) {
    $sheet->setCellValue("C{$r5}", $trainingRemarks);
    $sheet->mergeCells("C{$r5}:E{$r5}");
}

$sheet->setCellValue("A{$r6}", 'NET SALARY (Take Home):');
// NET = Basic + OT - DTR Deductions - Govt + Training
$sheet->setCellValue("B{$r6}", "=B{$r1}+B{$r2}-B{$r3}-B{$r4}+B{$r5}");
$sheet->mergeCells("B{$r6}:C{$r6}");

// Styling for summary section
$sheet->getStyle("A{$r1}")->applyFromArray([
    'font'=>['bold'=>true,'size'=>10],
    'alignment'=>['horizontal'=> Alignment::HORIZONTAL_RIGHT],
]);
$sheet->getStyle("B{$r1}")->applyFromArray([
    'font'=>['bold'=>true,'size'=>11],
    'fill'=>['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'E7E6E6']],
    'numberFormat'=>['formatCode'=>'#,##0.00'],
]);

$sheet->getStyle("A{$r2}")->applyFromArray([
    'alignment'=>['horizontal'=> Alignment::HORIZONTAL_RIGHT],
]);
$sheet->getStyle("B{$r2}")->getNumberFormat()->setFormatCode('#,##0.00');

$sheet->getStyle("A{$r3}")->applyFromArray([
    'font'=>['size'=>10,'color'=>['rgb'=>'FF0000']],
    'alignment'=>['horizontal'=> Alignment::HORIZONTAL_RIGHT],
]);
$sheet->getStyle("B{$r3}")->applyFromArray([
    'font'=>['color'=>['rgb'=>'FF0000']],
    'numberFormat'=>['formatCode'=>'#,##0.00'],
]);

$sheet->getStyle("A{$r4}")->applyFromArray([
    'font'=>['size'=>10,'color'=>['rgb'=>'FF0000']],
    'alignment'=>['horizontal'=> Alignment::HORIZONTAL_RIGHT],
]);
$sheet->getStyle("B{$r4}")->applyFromArray([
    'font'=>['color'=>['rgb'=>'FF0000']],
    'numberFormat'=>['formatCode'=>'#,##0.00'],
    'fill'=>['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFCCCC']],
]);

$sheet->getStyle("A{$r5}")->applyFromArray([
    'font'=>['size'=>10,'color'=>['rgb'=>'008000']],
    'alignment'=>['horizontal'=> Alignment::HORIZONTAL_RIGHT],
]);
$sheet->getStyle("B{$r5}")->applyFromArray([
    'font'=>['color'=>['rgb'=>'008000']],
    'numberFormat'=>['formatCode'=>'#,##0.00'],
    'fill'=>['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'CCFFCC']],
]);

$sheet->getStyle("A{$r6}")->applyFromArray([
    'font'=>['bold'=>true,'size'=>12,'color'=>['rgb'=>'FFFFFF']],
    'fill'=>['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'4472C4']],
    'alignment'=>['horizontal'=> Alignment::HORIZONTAL_RIGHT],
    'borders'=>['allBorders'=>['borderStyle'=> Border::BORDER_MEDIUM]],
]);
$sheet->getStyle("B{$r6}:C{$r6}")->applyFromArray([
    'font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],
    'fill'=>['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'00B050']],
    'numberFormat'=>['formatCode'=>'#,##0.00'],
    'alignment'=>['horizontal'=> Alignment::HORIZONTAL_CENTER],
    'borders'=>['allBorders'=>['borderStyle'=> Border::BORDER_MEDIUM]],
]);

// ── GOV'T deduction breakdown on the right side ──────────────────────
// Extract SSS/PhilHealth/PagIBIG from rows based on remarks
$sssAmt = 0; $philAmt = 0; $pagAmt = 0;
foreach ($rows as $rowData) {
    $rem = strtoupper(trim($rowData['remarks'] ?? ''));
    $gd  = floatval($rowData['govt'] ?? 0);
    if ($gd > 0) {
        if (strpos($rem, 'SSS') !== false && strpos($rem, 'PHILHEALTH') === false) $sssAmt = $gd;
        if (strpos($rem, 'PHILHEALTH') !== false || strpos($rem, 'PHIL HEALTH') !== false) $philAmt = $gd;
        if (strpos($rem, 'PAGIBIG') !== false || strpos($rem, 'PAG-IBIG') !== false || strpos($rem, 'HDMF') !== false) $pagAmt = $gd;
    }
}

$govRow = $trainRow + 2;
$govLabels = ['SSS' => $sssAmt, 'PHILHEALTH' => $philAmt, 'PAGIBIG' => $pagAmt];
$gi = 0;
foreach ($govLabels as $label => $amt) {
    $gr = $govRow + $gi;
    $sheet->setCellValue("V{$gr}", $label);
    $sheet->setCellValue("T{$gr}", $amt > 0 ? $amt : 0);
    $sheet->getStyle("V{$gr}")->applyFromArray([
        'font'=>['bold'=>true,'size'=>9,'color'=>['rgb'=>'CC00CC']],
    ]);
    $sheet->getStyle("T{$gr}")->applyFromArray([
        'font'=>['color'=>['rgb'=>'FF0000']],
        'numberFormat'=>['formatCode'=>'#,##0.00'],
        'fill'=>['fillType'=> Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFFFF']],
    ]);
    $gi++;
}

// Freeze pane
$sheet->freezePane('B6');

// ── Output ────────────────────────────────────────────────────────
$empSafe  = preg_replace('/[^A-Za-z0-9_\-]/', '_', $employeeName);
$pStart = $periodStart ? date('Y-m-d', strtotime($periodStart)) : date('Y-m-d');
$pEnd   = $periodEnd   ? date('Y-m-d', strtotime($periodEnd))   : date('Y-m-d');
$filename = 'DTR_' . $empSafe . '_' . $pStart . '_to_' . $pEnd . '.xlsx';
$tempFile = tempnam(sys_get_temp_dir(), 'dtr_calc_') . '.xlsx';

$writer = new Xlsx($spreadsheet);
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
