<?php
/**
 * Download DTR Excel Template
 * Generates a TB5-format DTR Excel template matching the export layout
 * (same as export_dtr_calculator.php but with empty data rows)
 */

// Start output buffering to catch any stray output
ob_start();

// Suppress display errors — they corrupt binary Excel output
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

require_once '../config/bootstrap.php';
require_once '../config/auth.php';
// Re-suppress errors for binary output (overrides bootstrap settings)
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

// H1: Require admin role
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(403);
    die('Unauthorized');
}

require_once '../config/database.php';

// Get payroll period dates if specified
$payrollPeriodId = intval($_GET['period_id'] ?? 0);
$startDate = null;
$endDate = null;

if ($payrollPeriodId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT start_date, end_date FROM payroll_periods WHERE id = ?");
        $stmt->execute([$payrollPeriodId]);
        $period = $stmt->fetch();
        if ($period) {
            $startDate = $period['start_date'];
            $endDate = $period['end_date'];
        }
    } catch (Exception $e) {
        // Continue with generic template
    }
}

// Check if PhpSpreadsheet is available
$phpSpreadsheetPath = '../vendor/autoload.php';
$usePhpSpreadsheet = false;

if (file_exists($phpSpreadsheetPath)) {
    require_once $phpSpreadsheetPath;
    if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $usePhpSpreadsheet = true;
    }
}

if ($usePhpSpreadsheet) {
    generateExcelTemplate($startDate, $endDate);
} else {
    generateCSVTemplate($startDate, $endDate);
}

/**
 * Generate Excel template using PhpSpreadsheet
 * Creates 10 DTR sheets (DTR1-DTR10) with identical layout.
 * Column layout: A-V (22 columns) matching the manual DTR form
 * A=Date, B=AM IN, C=PM OUT, D=Absent, E=Training, F=OT OUT,
 * G=Tot.Work, H=Late, I=Undertime, J=OT, K=Absent(days),
 * L=Late Deduct, M=Undertime Deduct, N=Halfday Deduct, O=OT Pay,
 * P=Minus OT Total Deductions, Q=Late/min(auto), R=Undertime(auto), S=OT(auto),
 * T=Gov't Benefits, U=Net Salary, V=Remarks
 * Row 2 also has: M2=TRAINER input, N2=FIXRATE input
 */
function generateExcelTemplate($startDate, $endDate) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

    // Create 10 DTR sheets
    for ($sheetIdx = 0; $sheetIdx < 10; $sheetIdx++) {
        if ($sheetIdx === 0) {
            $sheet = $spreadsheet->getActiveSheet();
        } else {
            $sheet = $spreadsheet->createSheet($sheetIdx);
        }
        $sheet->setTitle('DTR' . ($sheetIdx + 1));
        buildDTRSheet($sheet, $startDate, $endDate);
    }

    // ── Instructions sheet (after all DTR sheets) ──
    $instructionSheet = $spreadsheet->createSheet();
    $instructionSheet->setTitle('Instructions');

    $instructions = [
        'TB5 DTR Template — 22-Column Layout (A-V) — 10 DTR Sheets',
        '',
        'HOW TO USE THIS TEMPLATE:',
        '1. Each sheet (DTR1-DTR10) is for ONE employee',
        '2. Enter Employee Name in cell B2 (replace [ENTER NAME HERE])',
        '3. Enter Basic Monthly Salary in cell H2 (reference only)',
        '4. Enter Per Day Rate in cell J2 (green background — drives all calculations)',
        '   → OT RATE auto-calculates in L2 as (Per Day / 8) × 1.25',
        '5. TRAINER field (M2): Enter YES if employee is a trainer',
        '6. FIXRATE field (N2): Enter YES or a fixed rate amount if applicable',
        '7. Scheduled start is 8:00 AM — grace ends at 8:05 AM (arrival at or before 8:05 is NOT late)',
        '8. Fill in time data for each working day:',
        '   • Column B: AM IN (e.g. 8:00)',
        '   • Column C: PM OUT (e.g. 17:00)',
        '   • Column D: Type X if employee is absent',
        '   • Column E: Type X if training day',
        '   • Column F: OT OUT time if overtime (e.g. 19:00)',
        '   • Column T: Gov\'t deductions (SSS, PhilHealth, Pag-IBIG)',
        '   • Column V: Remarks',
        '9. All calculation columns (G-U) auto-compute via Excel formulas',
        '10. Summary section below row 37 shows NET SALARY breakdown',
        '',
        'COLUMN LAYOUT (A-V, 22 columns):',
        'A: DATE (auto-filled M/D format)',
        'B: AM IN (user input — time)',
        'C: PM OUT (user input — time)',
        'D: ABSENT — type X if absent',
        'E: TRAINING — type X if training day',
        'F: OT OUT — overtime out time (user input)',
        'G: TOT.WORK — total work hours (formula)',
        'H: LATE — late minutes (formula)',
        'I: UNDERTIME — undertime hours (formula)',
        'J: OT — overtime hours (formula)',
        'K: ABSENT — absent day count (formula)',
        'L: LATE DEDUCT (formula)',
        'M: UNDERTIME DEDUCT (formula)',
        'N: HALFDAY DEDUCT (formula)',
        'O: OT PAY (formula)',
        'P: MINUS OT TOTAL DEDUCTIONS (formula)',
        'Q: AUTO LATE/min (formula)',
        'R: AUTO UNDERTIME (formula)',
        'S: AUTO OT (formula)',
        'T: GOV\'T BENEFITS (user input)',
        'U: NET SALARY (formula)',
        'V: REMARKS (user input)',
        '',
        'EMPLOYEE INFO (Row 2):',
        '  H2: Basic Monthly Salary (reference only)',
        '  J2: Per Day Rate (enter directly, e.g. 500.00)',
        '  L2: OT Rate = (Per Day / 8) × 1.25 (auto-calculated)',
        '  M2: TRAINER — Enter YES if employee is a trainer',
        '  N2: FIXRATE — Enter YES or fixed rate amount',
        '  O2/P2: START time (8:00), Q2/R2: END time (17:00)',
        '',
        'IMPORTING:',
        '  Save and upload this Excel file to the system.',
        '  The import will process ALL DTR sheets that have data.',
        '  Empty sheets (no employee name or time entries) are automatically skipped.',
        '  Each sheet is imported as a separate employee DTR.',
    ];

    foreach ($instructions as $i => $line) {
        $instructionSheet->setCellValue('A' . ($i + 1), $line);
    }
    $instructionSheet->getColumnDimension('A')->setWidth(90);
    $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $instructionSheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A23')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A47')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A55')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A1:A65')->getAlignment()->setWrapText(true);

    // Set first sheet as active
    $spreadsheet->setActiveSheetIndex(0);

    // ── Output ──
    $filename = 'DTR_Template_10sheets_' . date('Y-m-d') . '.xlsx';
    $tempFile = tempnam(sys_get_temp_dir(), 'dtr_tpl_') . '.xlsx';

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save($tempFile);

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    readfile($tempFile);
    @unlink($tempFile);
    exit();
}

/**
 * Build a single DTR sheet with all formulas, styles, headers, and data rows.
 */
function buildDTRSheet($sheet, $startDate, $endDate) {

    // Column widths (same as export_dtr_calculator.php)
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
    if ($startDate && $endDate) {
        $periodText = date('M. d', strtotime($startDate)) . ' – ' . date('d, Y', strtotime($endDate));
    } else {
        $periodText = 'PAYROLL PERIOD: _______________';
    }

    // ── Default time thresholds (scheduled start default 8:00 — grace ends at 8:05) ──
    $lateThreshold = '8:00';
    $endThreshold  = '17:00';

    // ── Determine month for auto-fill dates ──
    if ($startDate) {
        $dt = new \DateTime($startDate);
        $templateMonth = (int)$dt->format('n');
        $templateYear  = (int)$dt->format('Y');
        $daysInMonth   = (int)$dt->format('t');
    } else {
        $templateMonth = (int)date('n');
        $templateYear  = (int)date('Y');
        $daysInMonth   = (int)date('t');
    }

    // ── ROW 1: Company + Period (same as export) ──
    $sheet->setCellValue('A1', 'THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold'=>true,'size'=>9,'italic'=>true],
    ]);
    $sheet->setCellValue('G1', $periodText);
    $sheet->mergeCells('G1:L1');
    $sheet->getStyle('G1')->applyFromArray([
        'font'      => ['bold'=>true,'size'=>11,'italic'=>true,'color'=>['rgb'=>'0066CC']],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    // TRAINER / FIXRATE labels in row 1
    $sheet->setCellValue('M1', 'TRAINER');
    $sheet->setCellValue('N1', 'FIXRATE');
    $sheet->getStyle('M1:N1')->applyFromArray([
        'font'      => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'0066CC']],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(20);

    // ── ROW 2: Employee Name + Rates (same as export) ──
    $sheet->setCellValue('A2', 'EMPLOYEE NAME:');
    $sheet->setCellValue('B2', '[ENTER NAME HERE]');
    $sheet->mergeCells('B2:F2');
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['bold'=>true,'size'=>11],
        'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFF00']],
    ]);
    $sheet->getStyle('B2:F2')->applyFromArray([
        'font' => ['bold'=>true,'size'=>12,'color'=>['rgb'=>'FF0000']],
        'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFF00']],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
    ]);

    // Rate info in row 2 — BASIC with input cell, other rates auto-calculate
    $sheet->setCellValue('G2', 'BASIC:');
    $sheet->setCellValue('H2', '');  // User enters basic salary here
    $sheet->getStyle('G2')->applyFromArray([
        'font' => ['bold'=>true,'size'=>10],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getStyle('H2')->applyFromArray([
        'font' => ['bold'=>true,'size'=>11],
        'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'00FF00']],
        'numberFormat' => ['formatCode'=>'#,##0.00'],
    ]);
    $sheet->setCellValue('I2', 'PER/DAY:');
    $sheet->setCellValue('J2', '');  // User enters per day rate directly
    $sheet->setCellValue('K2', 'OT RATE:');
    $sheet->setCellValue('L2', '=IF($J$2=0,0,($J$2/8)*1.25)');
    $sheet->getStyle('I2:L2')->applyFromArray([
        'font' => ['size'=>9],
        'numberFormat' => ['formatCode'=>'0.00'],
    ]);
    $sheet->getStyle('I2')->applyFromArray(['font'=>['bold'=>true,'size'=>9],'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]]);
    $sheet->getStyle('K2')->applyFromArray(['font'=>['bold'=>true,'size'=>9],'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]]);
    // J2: independent per day input — green background like H2
    $sheet->getStyle('J2')->applyFromArray([
        'font' => ['bold'=>true,'size'=>11],
        'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'00FF00']],
        'numberFormat' => ['formatCode'=>'#,##0.00'],
    ]);

    // TRAINER / FIXRATE fields in M2, N2
    $sheet->setCellValue('M2', '');  // User enters YES/X if trainer
    $sheet->setCellValue('N2', '');  // User enters YES/X or amount if fixrate
    $sheet->getStyle('M2')->applyFromArray([
        'font' => ['bold'=>true,'size'=>11],
        'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'00FF00']],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle('N2')->applyFromArray([
        'font' => ['bold'=>true,'size'=>11],
        'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'00FF00']],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);

    // START / END thresholds shifted to O-R
    $sheet->setCellValue('O2', 'START:');
    $sheet->setCellValue('P2', $lateThreshold);
    $sheet->setCellValue('Q2', 'END:');
    $sheet->setCellValue('R2', $endThreshold);
    $sheet->getStyle('O2:R2')->applyFromArray(['font'=>['size'=>9]]);
    $sheet->getStyle('O2')->applyFromArray(['font'=>['bold'=>true,'size'=>9],'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]]);
    $sheet->getStyle('Q2')->applyFromArray(['font'=>['bold'=>true,'size'=>9],'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]]);
    $sheet->getRowDimension(2)->setRowHeight(18);

    // ── ROW 3: Blank separator (same as export) ──
    $sheet->getRowDimension(3)->setRowHeight(6);

    // ── ROWS 4-5: Headers matching export exactly ──
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
            'horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical'  => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText'  => true,
        ],
        'borders' => ['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ]);

    // Header colour-coding (same as export)
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
            'fill' => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['rgb'=>$rgb]],
        ]);
    }
    $sheet->getRowDimension(4)->setRowHeight(20);
    $sheet->getRowDimension(5)->setRowHeight(18);

    // ── DATA ROWS (rows 6-36 = 31 days) ──
    $calcStyle = [
        'font'      => ['size'=>9],
        'fill'      => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'F2F2F2']],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ];
    $deductStyle = [
        'font'      => ['size'=>9,'color'=>['rgb'=>'CC0000']],
        'fill'      => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF2F2']],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ];
    $autoCalcStyle = [
        'font'      => ['size'=>9,'color'=>['rgb'=>'6600CC']],
        'fill'      => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'F5F0FF']],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ];
    $inputStyle = [
        'fill'      => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFFFF']],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        'font'      => ['size'=>9],
    ];

    $firstData = 6;
    $maxDataRow = 36; // 31 days max

    for ($dayNum = 1; $dayNum <= 31; $dayNum++) {
        $dataRow = $dayNum + 5; // rows 6-36

        // Auto-fill date (M/D format)
        $dateDisp = '';
        if ($dayNum <= $daysInMonth) {
            $dateDisp = $templateMonth . '/' . $dayNum;
        }
        $sheet->setCellValue("A{$dataRow}", $dateDisp);

        // B: AM IN — user input (time)
        // C: PM OUT — user input (time)
        // D: Absent — user types X
        // E: Training — user types X
        // F: OT OUT — user input (time)

        // G: Total work hours (formula - matches manual DTR: AM IN to PM OUT, lunch deducted only if work spans noon)
        // Simplified for compatibility: (C-effectiveStart)*24 minus 1 if leaving after 12:00
        $sheet->setCellValue("G{$dataRow}", "=IF(OR(D{$dataRow}=\"X\",AND(B{$dataRow}=\"\",C{$dataRow}=\"\")),0,IF(AND(B{$dataRow}<>\"\",C{$dataRow}<>\"\"),MAX(0,(C{$dataRow}-IF(B{$dataRow}<=TIME(8,5,0),TIME(8,0,0),B{$dataRow}))*24-IF(C{$dataRow}>TIME(12,0,0),1,0)),0))");
        // H: Late in minutes (formula - 5-min grace period: not late if at or before 8:05)
        $sheet->setCellValue("H{$dataRow}", "=IF(OR(D{$dataRow}=\"X\",B{$dataRow}=\"\"),0,IF(B{$dataRow}>TIME(8,5,0),(B{$dataRow}-TIME(8,0,0))*1440,0))");
        // I: Undertime in hours (formula - matches manual DTR: with lunch break adjustment, 0 if halfday)
        // If halfday (work<4h or PM OUT near noon), undertime=0 (halfday deduction covers it)
        // If left before 13:00 (1PM), subtract lunch hour from undertime
        $sheet->setCellValue("I{$dataRow}", "=IF(OR(D{$dataRow}=\"X\",C{$dataRow}=\"\"),0,IF(OR(AND(G{$dataRow}>0,G{$dataRow}<4),AND(C{$dataRow}>=TIME(11,45,0),C{$dataRow}<=TIME(12,15,0))),0,IF(C{$dataRow}<TIME(17,0,0),IF(C{$dataRow}<TIME(13,0,0),(TIME(17,0,0)-C{$dataRow})*24-1,(TIME(17,0,0)-C{$dataRow})*24),0)))");
        // J: OT in hours (formula)
        $sheet->setCellValue("J{$dataRow}", "=IF(F{$dataRow}=\"\",0,(F{$dataRow}-TIME(17,0,0))*24)");
        // K: Absent in days (formula)
        $sheet->setCellValue("K{$dataRow}", "=IF(D{$dataRow}=\"X\",1,0)");

        // L: Late deduction (formula: late mins * per-min rate)
        $sheet->setCellValue("L{$dataRow}", "=IF(\$J\$2=0,0,Q{$dataRow}*(\$J\$2/8/60))");
        // M: Undertime deduction (formula: undertime hours * hourly rate)
        $sheet->setCellValue("M{$dataRow}", "=IF(\$J\$2=0,0,R{$dataRow}*(\$J\$2/8))");
        // N: Halfday deduction (formula: if PM OUT around noon (11:45-12:15) OR work < 4 hours, half daily rate)
        $sheet->setCellValue("N{$dataRow}", "=IF(OR(AND(G{$dataRow}>0,G{$dataRow}<4),AND(C{$dataRow}>=TIME(11,45,0),C{$dataRow}<=TIME(12,15,0))),\$J\$2/2,0)");
        // O: OT Pay (formula: OT hours * OT rate)
        $sheet->setCellValue("O{$dataRow}", "=IF(\$J\$2=0,0,S{$dataRow}*\$L\$2)");
        // P: Minus OT Total Deductions (OT pay - deductions)
        $sheet->setCellValue("P{$dataRow}", "=O{$dataRow}-(L{$dataRow}+M{$dataRow}+N{$dataRow})");

        // Q: Auto Late/min (capped)
        $sheet->setCellValue("Q{$dataRow}", "=IF(H{$dataRow}>270,0,H{$dataRow})");
        // R: Auto Undertime
        $sheet->setCellValue("R{$dataRow}", "=IF(I{$dataRow}>15,0,I{$dataRow})");
        // S: Auto OT
        $sheet->setCellValue("S{$dataRow}", "=IFERROR(J{$dataRow}*1,0)");

        // T: Gov't Benefits — pre-filled on days 1-3 (SSS, PhilHealth, Pag-IBIG)
        if ($dayNum === 1) {
            $sheet->setCellValue("T{$dataRow}", 317.50);
            $sheet->setCellValue("V{$dataRow}", 'SSS');
        } elseif ($dayNum === 2) {
            $sheet->setCellValue("T{$dataRow}", 125);
            $sheet->setCellValue("V{$dataRow}", 'PHILHEALTH');
        } elseif ($dayNum === 3) {
            $sheet->setCellValue("T{$dataRow}", 100);
            $sheet->setCellValue("V{$dataRow}", 'PAGIBIG');
        }
        // U: Net Salary (formula: daily rate + OT adjustments only — gov't deductions stay in summary, NOT per-row)
        // If absent: 0; if no time data: 0; otherwise: daily rate + P (OT-deductions)
        $sheet->setCellValue("U{$dataRow}", "=IF(K{$dataRow}=1,0,IF(AND(B{$dataRow}=\"\",C{$dataRow}=\"\",F{$dataRow}=\"\"),0,IF(AND(B{$dataRow}=\"\",C{$dataRow}=\"\"),0,IF(\$J\$2=0,0,\$J\$2))))+P{$dataRow}");

        // ── Styles (same as export) ──
        $sheet->getStyle("A{$dataRow}:F{$dataRow}")->applyFromArray($inputStyle);
        $sheet->getStyle("G{$dataRow}:K{$dataRow}")->applyFromArray($calcStyle);
        $sheet->getStyle("L{$dataRow}:N{$dataRow}")->applyFromArray($deductStyle);
        $sheet->getStyle("O{$dataRow}")->applyFromArray([
            'font'=>['size'=>9,'color'=>['rgb'=>'008000']],
            'fill'=>['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'F0FFF0']],
            'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("P{$dataRow}")->applyFromArray($calcStyle);
        $sheet->getStyle("Q{$dataRow}:S{$dataRow}")->applyFromArray($autoCalcStyle);
        $sheet->getStyle("T{$dataRow}")->applyFromArray($inputStyle);
        $sheet->getStyle("U{$dataRow}")->applyFromArray([
            'font'=>['size'=>9,'bold'=>true],
            'fill'=>['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'E8FFE8']],
            'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("V{$dataRow}")->applyFromArray($inputStyle);

        // Time formats
        $sheet->getStyle("B{$dataRow}:C{$dataRow}")->getNumberFormat()->setFormatCode('h:mm');
        $sheet->getStyle("F{$dataRow}")->getNumberFormat()->setFormatCode('h:mm');
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
            'borders' => ['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ]);

        // Add helpful comments on first data row
        if ($dayNum === 1) {
            $sheet->getComment("B{$dataRow}")->getText()->createTextRun('Enter AM IN time (h:mm, e.g. 8:00)');
            $sheet->getComment("B{$dataRow}")->setWidth('200pt')->setHeight('40pt');
            $sheet->getComment("C{$dataRow}")->getText()->createTextRun('Enter PM OUT time (h:mm, e.g. 17:00)');
            $sheet->getComment("C{$dataRow}")->setWidth('200pt')->setHeight('40pt');
            $sheet->getComment("D{$dataRow}")->getText()->createTextRun('Type X if employee is absent');
            $sheet->getComment("D{$dataRow}")->setWidth('180pt')->setHeight('40pt');
            $sheet->getComment("E{$dataRow}")->getText()->createTextRun('Type X if training day');
            $sheet->getComment("E{$dataRow}")->setWidth('180pt')->setHeight('40pt');
            $sheet->getComment("F{$dataRow}")->getText()->createTextRun('Enter OT OUT time if overtime (h:mm, e.g. 19:00)');
            $sheet->getComment("F{$dataRow}")->setWidth('220pt')->setHeight('40pt');
            $sheet->getComment("T{$dataRow}")->getText()->createTextRun("Enter gov't deduction (SSS, PhilHealth, Pag-IBIG)");
            $sheet->getComment("T{$dataRow}")->setWidth('220pt')->setHeight('40pt');
            $sheet->getComment("H2")->getText()->createTextRun('Enter Basic Monthly Salary here (reference only — does not affect calculations)');
            $sheet->getComment("H2")->setWidth('300pt')->setHeight('40pt');
            $sheet->getComment("J2")->getText()->createTextRun('Enter Per Day Rate here (e.g. 500.00). This drives all deduction and salary calculations.');
            $sheet->getComment("J2")->setWidth('300pt')->setHeight('40pt');
            $sheet->getComment("M2")->getText()->createTextRun('TRAINER: Enter YES or X if this employee is a trainer.');
            $sheet->getComment("M2")->setWidth('250pt')->setHeight('40pt');
            $sheet->getComment("N2")->getText()->createTextRun('FIXRATE: Enter YES or a fixed rate amount if this employee has a fixed rate.');
            $sheet->getComment("N2")->setWidth('280pt')->setHeight('40pt');
        }
    }

    // ── TOTALS ROW (row 37, same as export) ──
    $totalRow  = 37;
    $lastData  = $maxDataRow; // 36

    $sheet->setCellValue("A{$totalRow}", 'TOTALS:');
    $sheet->mergeCells("A{$totalRow}:F{$totalRow}");
    $sheet->getStyle("A{$totalRow}")->applyFromArray([
        'font'      => ['bold'=>true,'size'=>11],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
    ]);

    foreach (['G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U'] as $col) {
        $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$firstData}:{$col}{$lastData})");
    }
    $sheet->getStyle("A{$totalRow}:V{$totalRow}")->applyFromArray([
        'font'      => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'FFFFFF']],
        'fill'      => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'4472C4']],
        'borders'   => ['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle("G{$totalRow}")->getNumberFormat()->setFormatCode('0.00');
    $sheet->getStyle("H{$totalRow}")->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle("I{$totalRow}:J{$totalRow}")->getNumberFormat()->setFormatCode('0.00');
    $sheet->getStyle("K{$totalRow}")->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle("L{$totalRow}:P{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("Q{$totalRow}:S{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("T{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("U{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');

    // ── TRAINING PAYMENT SECTION (right below totals, place Remarks in C{row} for reliable import) ──
    $trainRow = $totalRow + 1;
    $sheet->setCellValue("A{$trainRow}", 'TRAINING PAYMENT');
    // Place the training amount in column B (parser expects B for summary amount)
    $sheet->setCellValue("B{$trainRow}", 0);
    // Use column C for a small label and D..L for the remarks text area (D is input cell)
    $sheet->setCellValue("C{$trainRow}", 'Remarks:');
    $sheet->setCellValue("D{$trainRow}", '');
    $sheet->mergeCells("D{$trainRow}:L{$trainRow}");

    $sheet->getStyle("A{$trainRow}")->applyFromArray([
        'font'      => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'FFFFFF']],
        'fill'      => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'2196F3']],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,'vertical'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ]);
    // Amount cell (B) styling
    $sheet->getStyle("B{$trainRow}")->applyFromArray([
        'font'      => ['bold'=>true,'size'=>11,'color'=>['rgb'=>'008000']],
        'fill'      => ['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'E8F5E9']],
        'numberFormat' => ['formatCode'=>'#,##0.00'],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        'borders'   => ['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ]);
    $sheet->getStyle("C{$trainRow}")->applyFromArray([
        'font'      => ['bold'=>true,'size'=>9],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
        'borders'   => ['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ]);
    $sheet->getStyle("D{$trainRow}:L{$trainRow}")->applyFromArray([
        'font'      => ['size'=>9,'italic'=>true],
        'alignment' => ['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
        'borders'   => ['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ]);
    $sheet->getRowDimension($trainRow)->setRowHeight(22);

    // ── NET SALARY SECTION (2 rows below training, same as export) ──
    $r1 = $trainRow + 2;  // Basic Pay
    $r2 = $r1 + 1;        // OT Pay
    $r3 = $r2 + 1;        // DTR Deductions
    $r4 = $r3 + 1;        // Gov't Deductions
    $r5 = $r4 + 1;        // Training Payment
    $r6 = $r5 + 1;        // NET SALARY

    $sheet->setCellValue("A{$r1}", 'BASIC PAY:');
    $sheet->setCellValue("B{$r1}", '=$H$2');
    $sheet->setCellValue("A{$r2}", 'OT PAY:');
    $sheet->setCellValue("B{$r2}", "=O{$totalRow}");
    $sheet->setCellValue("A{$r3}", 'DTR DEDUCTIONS (Late/Under/Halfday):');
    $sheet->setCellValue("B{$r3}", "=L{$totalRow}+M{$totalRow}+N{$totalRow}");

    $sheet->setCellValue("A{$r4}", "GOV'T DEDUCTIONS (SSS+PhilHealth+Pag-IBIG):");
    $sheet->setCellValue("B{$r4}", "=T{$totalRow}");

    $sheet->setCellValue("A{$r5}", 'TRAINING PAYMENT:');
    $sheet->setCellValue("B{$r5}", "=B{$trainRow}");  // References training payment amount (now in B{trainRow})

    $sheet->setCellValue("A{$r6}", 'NET SALARY (Take Home):');
    // NET SALARY now: Net from rows + Training Payment - GOV'T Deductions
    $sheet->setCellValue("B{$r6}", "=U{$totalRow}+B{$r5}-B{$r4}");
    $sheet->mergeCells("B{$r6}:C{$r6}");

    // ── Summary section styling (same as export) ──
    $sheet->getStyle("A{$r1}")->applyFromArray([
        'font'=>['bold'=>true,'size'=>10],
        'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getStyle("B{$r1}")->applyFromArray([
        'font'=>['bold'=>true,'size'=>11],
        'fill'=>['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'E7E6E6']],
        'numberFormat'=>['formatCode'=>'#,##0.00'],
    ]);

    $sheet->getStyle("A{$r2}")->applyFromArray([
        'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getStyle("B{$r2}")->getNumberFormat()->setFormatCode('#,##0.00');

    $sheet->getStyle("A{$r3}")->applyFromArray([
        'font'=>['size'=>10,'color'=>['rgb'=>'FF0000']],
        'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getStyle("B{$r3}")->applyFromArray([
        'font'=>['color'=>['rgb'=>'FF0000']],
        'numberFormat'=>['formatCode'=>'#,##0.00'],
    ]);

    $sheet->getStyle("A{$r4}")->applyFromArray([
        'font'=>['size'=>10,'color'=>['rgb'=>'FF0000']],
        'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getStyle("B{$r4}")->applyFromArray([
        'font'=>['color'=>['rgb'=>'FF0000']],
        'numberFormat'=>['formatCode'=>'#,##0.00'],
        'fill'=>['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFCCCC']],
    ]);

    $sheet->getStyle("A{$r5}")->applyFromArray([
        'font'=>['size'=>10,'color'=>['rgb'=>'008000']],
        'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getStyle("B{$r5}")->applyFromArray([
        'font'=>['color'=>['rgb'=>'008000']],
        'numberFormat'=>['formatCode'=>'#,##0.00'],
        'fill'=>['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'CCFFCC']],
    ]);

    $sheet->getStyle("A{$r6}")->applyFromArray([
        'font'=>['bold'=>true,'size'=>12,'color'=>['rgb'=>'FFFFFF']],
        'fill'=>['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'4472C4']],
        'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
        'borders'=>['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]],
    ]);
    $sheet->getStyle("B{$r6}:C{$r6}")->applyFromArray([
        'font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],
        'fill'=>['fillType'=> \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'00B050']],
        'numberFormat'=>['formatCode'=>'#,##0.00'],
        'alignment'=>['horizontal'=> \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        'borders'=>['allBorders'=>['borderStyle'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]],
    ]);

    // Freeze pane
    $sheet->freezePane('B6');
}

/**
 * Generate CSV template as fallback — matches 22-column A-V layout
 */
function generateCSVTemplate($startDate, $endDate) {
    $filename = 'DTR_Template_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');

    // Period
    $periodText = $startDate && $endDate
        ? date('M. d', strtotime($startDate)) . '-' . date('d, Y', strtotime($endDate))
        : 'PAYROLL PERIOD: _______________';

    // Row 1
    fputcsv($output, ['THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.', '', '', '', '', '', $periodText]);
    // Row 2
    fputcsv($output, ['EMPLOYEE NAME:', '[ENTER NAME HERE]', '', '', '', '', 'BASIC:', '[SALARY]', 'PER/DAY:', '', 'OT RATE:', '', '[TRAINER]', '[FIXRATE]', 'START:', '8:00', 'END:', '17:00']);
    // Row 3 (blank)
    fputcsv($output, []);
    // Row 4: Main headers
    fputcsv($output, ['MO/YR','AM IN','PM OUT','ABSENT','TRAINING','OT OUT','TOT.WORK','LATE','UNDERTIME','OT','ABSENT','LATE','UNDERTIME','HALFDAY','OT PAY','MINUS OT TOTAL','AUTOMATIC CALCULATIONS','','','Government','Net','REMARKS']);
    // Row 5: Sub headers
    fputcsv($output, ['DATE','','','','','','(in hours)','(in mins)','(in hours)','(in hours)','(in days)','DEDUCT','DEDUCT','DEDUCT','','DEDUCTIONS','LATE/min','UNDERTIME','OT','Benefits','Salary','']);

    // Determine month
    $csvMonth = $startDate ? (int)date('n', strtotime($startDate)) : (int)date('n');
    $csvDaysInMonth = $startDate ? (int)date('t', strtotime($startDate)) : (int)date('t');

    // Data rows (31 days)
    for ($i = 1; $i <= 31; $i++) {
        $dateVal = $i <= $csvDaysInMonth ? "{$csvMonth}/{$i}" : '';
        fputcsv($output, array_pad([$dateVal], 22, ''));
    }

    fclose($output);
    exit();
}
