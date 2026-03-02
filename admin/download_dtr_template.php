<?php
/**
 * Download DTR Excel Template
 * Generates a sample DTR Excel template for importing
 */

// Start output buffering to catch any stray output
ob_start();

// Suppress display errors — they corrupt binary Excel output
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

require_once '../config/bootstrap.php';
// Re-suppress errors for binary output (overrides bootstrap settings)
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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
    // Generate Excel using PhpSpreadsheet
    generateExcelTemplate($startDate, $endDate);
} else {
    // Generate CSV as fallback
    generateCSVTemplate($startDate, $endDate);
}

/**
 * Generate sample DTR data with realistic variations
 * Matches TB5 Excel format with computed fields
 */
function generateSampleDTRData($startDate, $endDate) {
    $data = [];
    $perDay = 433.33;   // Based on P13,000/30 days
    $perMin = 0.9028;   // 433.33/480 minutes
    
    // If dates not specified, use Oct 13-27, 2025 like in sample
    if (!$startDate || !$endDate) {
        $startDate = '2025-10-13';
        $endDate = '2025-10-27';
    }
    
    $current = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    // Sample time variations for realistic data
    $variations = [
        ['am_in' => 8/24, 'pm_out' => 17/24],                           // Normal (8:00-17:00)
        ['am_in' => (8+5/60)/24, 'pm_out' => 17/24],                   // Late 5 mins
        ['am_in' => (8+10/60)/24, 'pm_out' => 17/24],                  // Late 10 mins
        ['am_in' => 8/24, 'pm_out' => (16+30/60)/24],                  // Undertime 30 mins
        ['am_in' => '', 'pm_out' => '', 'absent' => 1],                // Absent day
        ['am_in' => 8/24, 'pm_out' => 19/24],                          // OT day (8:00-19:00, +2hr OT)
        ['am_in' => (8+15/60)/24, 'pm_out' => 17/24],                  // Late 15 mins
        ['am_in' => 8/24, 'pm_out' => 17/24],                          // Normal
        ['am_in' => 8/24, 'pm_out' => (17+30/60)/24],                  // Normal + 30min OT
    ];
    
    $variationIndex = 0;
    
    while ($current <= $end) {
        $dayOfWeek = $current->format('N'); // 1=Mon, 7=Sun
        
        // Skip weekends (Saturday=6, Sunday=7) or mark as no work
        if ($dayOfWeek >= 6) {
            $current->modify('+1 day');
            continue;
        }
        
        $v = $variations[$variationIndex % count($variations)];
        $variationIndex++;
        
        $isAbsent = $v['absent'] ?? 0;
        
        $row = [
            'date' => $current->format('n/j'),  // e.g., 10/13
            'am_in' => $v['am_in'],
            'pm_out' => $v['pm_out'],
            'absent' => $isAbsent ? 'X' : '',
            'ot_out' => $otHours > 0 ? '7:00 PM' : '',
            'tot_work' => $totWork,
            'late' => round($lateMin / 60, 2),  // Convert to hours
            'undertime' => round($undertimeMin / 60, 2),
            'ot_hours' => $otHours,
            'absent_day' => $isAbsent,
            'late_deduct' => $lateDeduct,
            'undertime_deduct' => $undertimeDeduct,
            'ot_pay' => $otPay,
            'remarks' => ''
        ];
        
        $data[] = $row;
        $current->modify('+1 day');
    }
    
    return $data;
}

/**
 * Generate Excel template using PhpSpreadsheet - TB5 Format (Exact Match)
 */
function generateExcelTemplate($startDate, $endDate) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('DTR');
    
    // Set default font
    $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
    
    // ============ SET COLUMN WIDTHS (wider for readability) ============
    $columnWidths = [
        'A' => 12,   // DATE
        'B' => 10,   // AM IN
        'C' => 10,   // PM OUT
        'D' => 10,   // ABSENT
        'E' => 10,   // OT OUT
        'F' => 12,   // TOT.WORK
        'G' => 10,   // LATE
        'H' => 12,   // UNDERTIME
        'I' => 8,    // OT
        'J' => 10,   // ABSENT (day)
        'K' => 12,   // ABSENT DEDUCT
        'L' => 12,   // LATE DEDUCT
        'M' => 12,   // UNDERTIME DEDUCT
        'N' => 12,   // HALFDAY DEDUCT
        'O' => 12,   // OT PAY
        'P' => 14,   // TOTAL DEDUCTIONS
        'Q' => 14,   // AUTOMATIC DEDUCTIONS
        'R' => 12,   // AUTO CA ADV
        'S' => 14,   // AUTO TOTAL
        'T' => 12,   // MANUAL GOV'T
        'U' => 14,   // SALARY
        'V' => 6,    // F1*
        'W' => 6,    // F2*
        'X' => 15,   // Remarks
        'Y' => 12,   // No. of Trainees
        'Z' => 14,   // Payment Per Trainee
        'AA' => 14   // Total Trainee Payment
    ];
    
    foreach ($columnWidths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }
    
    // ============ ROW 1: Period & Input Salary Instruction ============
    $periodText = $startDate && $endDate ? 
        date('M. d', strtotime($startDate)) . '-' . date('d, Y', strtotime($endDate)) : 
        'PAYROLL PERIOD: _______________';
    
    $sheet->setCellValue('A1', $periodText);
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'italic' => true, 'color' => ['rgb' => '0066CC']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // Salary input instruction
    $sheet->setCellValue('J1', '↓ ↓ ↓ ↓');
    $sheet->getStyle('J1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FF0000']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    $sheet->setCellValue('K1', 'INPUT BASIC MONTHLY SALARY HERE');
    $sheet->mergeCells('K1:M1');
    $sheet->getStyle('O1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FF0000']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    $sheet->getRowDimension(1)->setRowHeight(20);
    
    // ============ ROW 2: Employee Name & Rate Configuration ============
    $sheet->setCellValue('A2', 'EMPLOYEE NAME:');
    $sheet->setCellValue('B2', 'SAMPLE EMPLOYEE');  // Sample name for testing - user can change
    $sheet->mergeCells('B2:D2');
    
    // Style employee name section (yellow background)
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']]
    ]);
    $sheet->getStyle('B2:D2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FF0000']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT]
    ]);
    
    // Time reference cells - stored as Excel time serial values for formula compatibility
    $sheet->setCellValue('E2', 7/24 + 35/1440);      // 7:35 AM (grace end / late threshold)
    $sheet->setCellValue('F2', 17/24);               // 5:00 PM (standard end time)
    $sheet->getStyle('E2:F2')->getNumberFormat()->setFormatCode('h:mm AM/PM');
    
    // BASIC label and salary input
    $sheet->setCellValue('G2', 'BASIC');
    $sheet->setCellValue('H2', '►');
    $sheet->setCellValue('I2', '');          // Empty for user to see format
    $sheet->setCellValue('J2', 13000);          // Sample salary for testing - user can change
    
    // Rate calculations (30-day computation)
    $sheet->setCellValue('K2', '=$J$2/30');           // PER/DAY = 433.33
    $sheet->setCellValue('L2', '=($J$2/30)/8');       // PER/HOUR = 54.17
    $sheet->setCellValue('M2', '=($J$2/30)/480');     // PER/MIN = 0.9028
    
    // Style BASIC section
    $sheet->getStyle('G2:H2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FF0000']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']]
    ]);
    $sheet->getStyle('I2:J2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '00FF00']],
        'numberFormat' => ['formatCode' => '#,##0']
    ]);
    $sheet->getStyle('K2:M2')->applyFromArray([
        'font' => ['size' => 10],
        'numberFormat' => ['formatCode' => '0.00'],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6E6']]
    ]);
    
    $sheet->getRowDimension(2)->setRowHeight(18);
    
    // ============ ROW 3: Company Name & Additional Info ============
    $sheet->setCellValue('A3', 'THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.');
    $sheet->mergeCells('A3:D3');
    $sheet->getStyle('A3')->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'italic' => true]
    ]);
    
    $sheet->setCellValue('E3', 17/24 + 45/1440);     // 5:45 PM (OT detection threshold)
    $sheet->getStyle('E3')->getNumberFormat()->setFormatCode('h:mm AM/PM');
    
    // Rate labels
    $sheet->setCellValue('K3', 'PER/DAY');
    $sheet->setCellValue('L3', 'PER/HOUR');
    $sheet->setCellValue('M3', 'PER/MIN');
    
    $sheet->getRowDimension(3)->setRowHeight(16);
    
    // ============ ROW 4: Column Headers (Main Categories) ============
    $mainHeaders = [
        'A4' => 'MO/YR',
        'B4' => 'AM IN',
        'C4' => 'PM OUT',
        'D4' => 'ABSENT',
        'E4' => 'OT',
        'F4' => 'TOT.WORK',
        'G4' => 'LATE',
        'H4' => 'UNDERTM',
        'I4' => 'OT',
        'J4' => '(ABSENT)',
        'K4' => 'ABSENT',
        'L4' => 'LATE/MIN',
        'M4' => 'UNDERTM',
        'N4' => 'HALFDAY',
        'O4' => 'OT',
        'P4' => 'neg-PTOTAL',
        'Q4' => 'AUTOMATIC CALCULATIONS',
        'R4' => '',
        'S4' => '',
        'T4' => '(MANUAL)',
        'U4' => 'Automatic',
        'V4' => '',
        'W4' => '',
        'X4' => 'REMARKS',
        'Y4' => 'TRAINEE',
        'Z4' => 'PAYMENT',
        'AA4' => 'TOTAL'
    ];
    
    foreach ($mainHeaders as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }
    
    // Merge header cells
    $sheet->mergeCells('Q4:S4');  // AUTOMATIC CALCULATIONS across 3 columns
    
    // ============ ROW 5: Column Headers (Sub-categories) ============
    $subHeaders = [
        'A5' => 'DATE',
        'B5' => '',
        'C5' => '',
        'D5' => '(if absent)',
        'E5' => 'OUT',
        'F5' => '(in hours)',
        'G5' => '(in mins)',
        'H5' => '(in hours)',
        'I5' => '',
        'J5' => '(in day)',
        'K5' => 'DEDUCT',
        'L5' => 'DEDUCT',
        'M5' => 'DEDUCT',
        'N5' => 'DEDUCT',
        'O5' => '(OT PAY)',
        'P5' => 'MINUS OT',
        'Q5' => 'LATE/min',
        'R5' => 'UNDERTIME',
        'S5' => 'OT',
        'T5' => 'GOV\'T.',
        'U5' => 'SALARY',
        'V5' => 'F1*',
        'W5' => 'F2*',
        'X5' => 'Remarks',
        'Y5' => 'COUNT',
        'Z5' => 'PER TRAINEE',
        'AA5' => 'TRAINEE PAY'
    ];
    
    foreach ($subHeaders as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }
    
    // Style header rows (4-5)
    $sheet->getStyle('A4:AA5')->applyFromArray([
        'font' => ['bold' => true, 'size' => 9],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
    ]);
    
    // Color-code header sections
    $sheet->getStyle('B4:B5')->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCC99']]]);  // AM IN - orange
    $sheet->getStyle('C4:C5')->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCC99']]]);  // PM OUT - orange
    $sheet->getStyle('D4:D5')->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF9999']]]);  // ABSENT - red
    $sheet->getStyle('E4:E5')->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '99CCFF']]]);  // OT - blue
    $sheet->getStyle('F4:J5')->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCFFCC']]]);  // Calculations - green
    $sheet->getStyle('K4:O5')->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCCCC']]]);  // Deductions - pink
    $sheet->getStyle('P4:R5')->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CC99FF']]]);  // Auto Copy - purple
    $sheet->getStyle('S4:S5')->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCFFFF']]]);  // Manual - cyan
    $sheet->getStyle('Y4:AA5')->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCE5FF']]]);  // Trainee - light blue
    $sheet->getStyle('T4:T5')->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '99FF99']]]);  // Salary - green
    
    $sheet->getRowDimension(4)->setRowHeight(20);
    $sheet->getRowDimension(5)->setRowHeight(18);
    
    // ============ DATA ROWS (6-36) with AUTO-FILLED DATES ============
    // Determine month and days based on start/end date or current month
    $templateMonth = null;
    $templateYear = null;
    $daysInMonth = 31;
    
    if ($startDate && $endDate) {
        // Use the start date's month
        $dt = new DateTime($startDate);
        $templateMonth = (int)$dt->format('n');
        $templateYear = (int)$dt->format('Y');
        $daysInMonth = (int)$dt->format('t'); // actual days in that month
    } else {
        // Use current month
        $templateMonth = (int)date('n');
        $templateYear = (int)date('Y');
        $daysInMonth = (int)date('t'); // days in current month
    }
    
    $dataStartRow = 6;
    $dataEndRow = $dataStartRow + $daysInMonth - 1;  // Rows based on actual days in month
    // Always generate rows up to row 36 (31 rows) for template consistency
    $maxDataRow = 36;
    
    for ($row = $dataStartRow; $row <= $maxDataRow; $row++) {
        $dayNum = $row - $dataStartRow + 1; // Day 1, 2, 3... 
        
        // Auto-fill date in column A (M/D format) only for valid days in the month
        if ($dayNum <= $daysInMonth) {
            $dateValue = $templateMonth . '/' . $dayNum;
            $sheet->setCellValue("A{$row}", $dateValue);
        } else {
            $sheet->setCellValue("A{$row}", '');  // Empty for days beyond month
        }
        
        // Add sample time data for first 5 weekdays (like working test file)
        // This helps users see the correct format AND test imports immediately
        $sampleData = [
            1 => ['am_in' => 8/24,        'pm_out' => 17/24],        // Day 1: 8:00-5:00 (normal)
            2 => ['am_in' => 8/24 + 5/1440, 'pm_out' => 17/24],        // Day 2: 8:05 (5min late)
            3 => ['am_in' => 8/24,        'pm_out' => 17/24 + 120/1440], // Day 3: OT 2hrs (out 7:00pm)
            4 => ['am_in' => 8/24,        'pm_out' => 16/24 + 30/1440],  // Day 4: Leave early 4:30pm
            5 => ['am_in' => 8/24,        'pm_out' => 17/24],        // Day 5: normal
        ];
        
        if (isset($sampleData[$dayNum])) {
            // Store as Excel time decimals with h:mm format
            $sheet->setCellValue("B{$row}", $sampleData[$dayNum]['am_in']);   // AM IN
            $sheet->setCellValue("C{$row}", $sampleData[$dayNum]['pm_out']);  // PM OUT
        } else {
            $sheet->setCellValue("B{$row}", '');  // AM IN
            $sheet->setCellValue("C{$row}", '');  // PM OUT
        }
        
        // Column D: Absent marker - EMPTY
        $sheet->setCellValue("D{$row}", '');
        
        // Column X: Remarks - EMPTY
        $sheet->setCellValue("X{$row}", '');
        
        // ── Column E: OT OUT detection (show PM OUT if after OT threshold $E$3 = 5:45 PM)
        $sheet->setCellValue("E{$row}", "=IF(C{$row}>\$E\$3,C{$row},\"\")");
        
        // ── Column F: Total work HOURS (formula: (MOD(C-B,1)*24)-1, minus 1hr lunch)
        $sheet->setCellValue("F{$row}", "=IF(OR(D{$row}=\"X\",AND(B{$row}=\"\",C{$row}=\"\")),0,(MOD(C{$row}-B{$row},1)*24)-1)");
        
        // ── Column G: Late in MINUTES (formula: (arrival - grace_end) * 1440)
        $sheet->setCellValue("G{$row}", "=IF(OR(D{$row}=\"X\",B{$row}=\"\"),0,IF(B{$row}>\$E\$2,(B{$row}-\$E\$2)*1440,0))");
        
        // ── Column H: Undertime in HOURS (if PM OUT < 5:00 PM end time)
        $sheet->setCellValue("H{$row}", "=IF(OR(D{$row}=\"X\",C{$row}=\"\"),0,IF(C{$row}<\$F\$2,(\$F\$2-C{$row})*24,0))");
        
        // ── Column I: OT in HOURS (if PM OUT > 5:00 PM, uses E for error safety)
        $sheet->setCellValue("I{$row}", "=IF(E{$row}=\"\",0,(E{$row}-\$F\$2)*24)");
        
        // ── Column J: Absent count (1 if D="X", else 0)
        $sheet->setCellValue("J{$row}", "=IF(D{$row}=\"X\",1,0)");
        
        // ── Column K: ABSENT DEDUCT (daily rate × absent count)
        $sheet->setCellValue("K{$row}", "=IF(J{$row}=1,\$K\$2,0)");
        
        // ── Column Q: Cleaned late MINUTES (helper: cap at 270 mins max)
        $sheet->setCellValue("Q{$row}", "=IF(G{$row}>270,0,G{$row})");
        
        // ── Column R: Cleaned undertime HOURS (helper: cap at 15 hrs max)
        $sheet->setCellValue("R{$row}", "=IF(H{$row}>15,0,H{$row})");
        
        // ── Column S: Cleaned OT HOURS (helper: IFERROR safety)
        $sheet->setCellValue("S{$row}", "=IFERROR(I{$row}*1,0)");
        
        // ── Column L: Late deduction (per-MINUTE rate × cleaned late MINUTES)
        $sheet->setCellValue("L{$row}", "=\$M\$2*Q{$row}");
        
        // ── Column M: Undertime deduction (per-HOUR rate × cleaned undertime HOURS)
        $sheet->setCellValue("M{$row}", "=\$L\$2*R{$row}");
        
        // ── Column N: HALFDAY DEDUCT (auto-detect: if work hours < 4, deduct half daily rate)
        $sheet->setCellValue("N{$row}", "=IF(F{$row}<4,\$K\$2/2,0)");
        
        // ── Column O: OT PAYMENT (per-HOUR rate × cleaned OT HOURS × 1.25)
        $sheet->setCellValue("O{$row}", "=(\$L\$2*S{$row})*1.25");
        
        // ── Column P: Net adjustment = OT pay MINUS deductions
        $sheet->setCellValue("P{$row}", "=O{$row}-SUM(K{$row}:N{$row})");
        
        // ── Column T: (MANUAL) GOV'T - Benefits (manual input)
        $sheet->setCellValue("T{$row}", '');
        
        // ── Column T: Per-day salary = (daily rate if not absent) + net adjustment
        $sheet->setCellValue("T{$row}", "=IF(J{$row}=1,0,\$K\$2)+O{$row}");
        
        // ── Column U: Flag 1 (for compatibility)
        $sheet->setCellValue("U{$row}", "0");
        
        // ── Column V: Flag 2 (for compatibility)
        $sheet->setCellValue("V{$row}", "0");
        
        // ── Column X: Remarks (user input)
        $sheet->setCellValue("X{$row}", '');
        
        // ── Column Y: Number of Trainees (user input, default 0)
        $sheet->setCellValue("Y{$row}", 0);
        
        // ── Column Z: Payment Per Trainee (user input, default 0)
        $sheet->setCellValue("Z{$row}", 0);
        
        // ── Column AA: Total Trainee Payment (formula: Y × Z)
        $sheet->setCellValue("AA{$row}", "=Y{$row}*Z{$row}");
        
        // Apply styles
        $blackStyle = [
            'font' => ['color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        $whiteStyle = [
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'font' => ['size' => 9]
        ];
        
        // Black background for formula cells
        $sheet->getStyle("E{$row}")->applyFromArray($blackStyle);
        $sheet->getStyle("F{$row}:R{$row}")->applyFromArray($blackStyle);
        $sheet->getStyle("T{$row}:V{$row}")->applyFromArray($blackStyle);
        $sheet->getStyle("AA{$row}")->applyFromArray($blackStyle);  // Trainee total (formula)
        
        // White background for input cells
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($whiteStyle);
        $sheet->getStyle("S{$row}")->applyFromArray($whiteStyle);
        $sheet->getStyle("W{$row}:Z{$row}")->applyFromArray($whiteStyle);  // Remarks + Trainee inputs
        
        // Apply borders to all cells in row
        $sheet->getStyle("A{$row}:AA{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
        ]);
        
        // Number formats
        $sheet->getStyle("B{$row}:C{$row}")->getNumberFormat()->setFormatCode('h:mm');  // Time inputs - h:mm format for reliable import
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('0.00');  // Work hours - 2 decimals
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('0');     // Late mins - integer
        $sheet->getStyle("H{$row}:I{$row}")->getNumberFormat()->setFormatCode('0.00');  // Hours - 2 decimals
        $sheet->getStyle("K{$row}:S{$row}")->getNumberFormat()->setFormatCode('#,##0.00');  // Currency - 2 decimals
        $sheet->getStyle("U{$row}")->getNumberFormat()->setFormatCode('#,##0.00');  // Net salary
    }
    
    // ============ ROW 37: TOTALS ROW ============
    $totalsRow = 37;
    $sheet->setCellValue("A{$totalsRow}", 'TOTAL:');
    $sheet->mergeCells("A{$totalsRow}:E{$totalsRow}");
    $sheet->getStyle("A{$totalsRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]
    ]);
    
    // Sum formulas for totals (rows 6-36 = 31 days)
    $sheet->setCellValue("F{$totalsRow}", "=SUM(F6:F36)");     // Total work hours
    $sheet->setCellValue("G{$totalsRow}", "=SUM(G6:G36)");     // Total late mins
    $sheet->setCellValue("H{$totalsRow}", "=SUM(H6:H36)");     // Total undertime hrs
    $sheet->setCellValue("I{$totalsRow}", "=SUM(I6:I36)");     // Total OT hrs
    $sheet->setCellValue("J{$totalsRow}", "=SUM(J6:J36)");     // Total absent days
    $sheet->setCellValue("K{$totalsRow}", "=SUM(K6:K36)");     // Total absent deduct
    $sheet->setCellValue("L{$totalsRow}", "=SUM(L6:L36)");     // Total late deduct
    $sheet->setCellValue("M{$totalsRow}", "=SUM(M6:M36)");     // Total undertime deduct
    $sheet->setCellValue("N{$totalsRow}", "=SUM(N6:N36)");     // Total halfday deduct
    $sheet->setCellValue("O{$totalsRow}", "=SUM(O6:O36)");     // Total OT pay
    $sheet->setCellValue("P{$totalsRow}", "=SUM(P6:P36)");     // Total deductions
    $sheet->setCellValue("Q{$totalsRow}", "=SUM(Q6:Q36)");     // Total late copy
    $sheet->setCellValue("R{$totalsRow}", "=SUM(R6:R36)");     // Total undertime copy
    $sheet->setCellValue("S{$totalsRow}", "=SUM(S6:S36)");     // Total OT copy
    $sheet->setCellValue("Y{$totalsRow}", "=SUM(Y6:Y36)");     // Total trainees count
    $sheet->setCellValue("Z{$totalsRow}", "");                  // Average payment (empty)
    $sheet->setCellValue("AA{$totalsRow}", "=SUM(AA6:AA36)");  // Total trainee payment
    
    // Style totals row
    $sheet->getStyle("A{$totalsRow}:AA{$totalsRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // Number formats for totals
    $sheet->getStyle("F{$totalsRow}")->getNumberFormat()->setFormatCode('0.00');  // Work hours
    $sheet->getStyle("G{$totalsRow}")->getNumberFormat()->setFormatCode('0');     // Late mins
    $sheet->getStyle("H{$totalsRow}:I{$totalsRow}")->getNumberFormat()->setFormatCode('0.00');
    $sheet->getStyle("K{$totalsRow}:S{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("Y{$totalsRow}")->getNumberFormat()->setFormatCode('0');     // Trainee count
    $sheet->getStyle("AA{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');  // Trainee total
    
    // ============ ROWS 39-45: NET SALARY CALCULATION ============
    $row23 = 39;  // Basic Pay
    $row24 = 40;  // OT Pay
    $row25 = 41;  // DTR Deductions
    $row26 = 42;  // Gov't Deductions
    $row27 = 43;  // Cash Advance
    $rowTrainee = 44;  // Trainee Payment (ADDED)
    $row28 = 45;  // NET SALARY
    
    // Row 23: Basic Pay
    $sheet->setCellValue("A{$row23}", 'BASIC PAY (Semi-Monthly):');
    $sheet->setCellValue("B{$row23}", "=\$J\$2");
    $sheet->mergeCells("A{$row23}:A{$row23}");
    $sheet->getStyle("A{$row23}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 10],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]
    ]);
    $sheet->getStyle("B{$row23}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6E6']]
    ]);
    
    // Row 24: OT Pay
    $sheet->setCellValue("A{$row24}", 'OT PAY:');
    $sheet->setCellValue("B{$row24}", "=O{$totalsRow}");
    $sheet->getStyle("A{$row24}")->applyFromArray([
        'font' => ['size' => 10],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]
    ]);
    $sheet->getStyle("B{$row24}")->getNumberFormat()->setFormatCode('#,##0.00');
    
    // Row 25: DTR Deductions
    $sheet->setCellValue("A{$row25}", 'DTR DEDUCTIONS (Abs/Late/Under/Halfday):');
    $sheet->setCellValue("B{$row25}", "=P{$totalsRow}");
    $sheet->getStyle("A{$row25}")->applyFromArray([
        'font' => ['size' => 10, 'color' => ['rgb' => 'FF0000']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]
    ]);
    $sheet->getStyle("B{$row25}")->applyFromArray([
        'font' => ['color' => ['rgb' => 'FF0000']],
        'numberFormat' => ['formatCode' => '#,##0.00']
    ]);
    
    // Row 26: Government Deductions (linked to GOV'T CARD)
    $sheet->setCellValue("A{$row26}", 'GOV\'T DEDUCTIONS (SSS+PhilHealth+Pag-IBIG+CA):');
    // B26 will be set by GOV'T CARD section formula
    $sheet->getStyle("A{$row26}")->applyFromArray([
        'font' => ['size' => 10, 'color' => ['rgb' => 'FF0000']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]
    ]);
    $sheet->getStyle("B{$row26}")->applyFromArray([
        'font' => ['color' => ['rgb' => 'FF0000']],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCCCC']]
    ]);
    
    // Row 27: Cash Advance / Other Deductions
    $sheet->setCellValue("A{$row27}", 'CASH ADVANCE / OTHER DEDUCTIONS:');
    $sheet->setCellValue("B{$row27}", 0);
    $sheet->getStyle("A{$row27}")->applyFromArray([
        'font' => ['size' => 10],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]
    ]);
    $sheet->getStyle("B{$row27}")->applyFromArray([
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']]
    ]);
    
    // Trainee Payment Row (ADDED - earnings added to net pay)
    $sheet->setCellValue("A{$rowTrainee}", 'TRAINEE PAYMENT (Earnings):');
    $sheet->setCellValue("B{$rowTrainee}", "=AA{$totalsRow}");  // Use trainee total from sum row
    $sheet->getStyle("A{$rowTrainee}")->applyFromArray([
        'font' => ['size' => 10, 'color' => ['rgb' => '2F5496']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]
    ]);
    $sheet->getStyle("B{$rowTrainee}")->applyFromArray([
        'font' => ['color' => ['rgb' => '2F5496']],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCE5FF']]
    ]);
    
    // Row 28: NET SALARY (includes Trainee Payment)
    $sheet->setCellValue("A{$row28}", 'NET SALARY (Take Home):');
    $sheet->setCellValue("B{$row28}", "=B{$row23}+B{$row24}+B{$rowTrainee}-B{$row25}-B{$row26}-B{$row27}");
    $sheet->mergeCells("B{$row28}:D{$row28}");
    $sheet->getStyle("A{$row28}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]]
    ]);
    $sheet->getStyle("B{$row28}:D{$row28}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '00B050']],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]]
    ]);
    
    // ============ GOV'T CARD SECTION (Right side) ============
    // Add GOV'T deduction breakdown in columns X-AB area (rows matching data)
    // SSS, PHILHEALTH, PAGIBIG, CA with values
    
    $govCardStartRow = 11;  // Start after some data rows
    
    // SSS
    $sheet->setCellValue("AB{$govCardStartRow}", 'SSS');
    $sheet->setCellValue("X{$govCardStartRow}", 317.50);
    $sheet->getStyle("AB{$govCardStartRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'CC00CC']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT]
    ]);
    $sheet->getStyle("X{$govCardStartRow}")->applyFromArray([
        'font' => ['color' => ['rgb' => 'FF0000']],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]
    ]);
    
    // PHILHEALTH
    $sheet->setCellValue("AB" . ($govCardStartRow + 1), 'PHILHEALTH');
    $sheet->setCellValue("X" . ($govCardStartRow + 1), 125.00);
    $sheet->getStyle("AB" . ($govCardStartRow + 1))->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'CC00CC']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT]
    ]);
    $sheet->getStyle("X" . ($govCardStartRow + 1))->applyFromArray([
        'font' => ['color' => ['rgb' => 'FF0000']],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]
    ]);
    
    // PAGIBIG
    $sheet->setCellValue("AB" . ($govCardStartRow + 2), 'PAGIBIG');
    $sheet->setCellValue("X" . ($govCardStartRow + 2), 100.00);
    $sheet->getStyle("AB" . ($govCardStartRow + 2))->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'CC00CC']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT]
    ]);
    $sheet->getStyle("X" . ($govCardStartRow + 2))->applyFromArray([
        'font' => ['color' => ['rgb' => 'FF0000']],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]
    ]);
    
    // CA (Cash Advance) with date
    $sheet->setCellValue("AB" . ($govCardStartRow + 3), 'CA OCT. 23, 2025');
    $sheet->setCellValue("X" . ($govCardStartRow + 3), 3000.00);
    $sheet->getStyle("AB" . ($govCardStartRow + 3))->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'CC00CC']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT]
    ]);
    $sheet->getStyle("X" . ($govCardStartRow + 3))->applyFromArray([
        'font' => ['color' => ['rgb' => 'FF0000']],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]
    ]);
    
    // Total GOV'T deductions (sum of SSS, PHILHEALTH, PAGIBIG, CA)
    $govTotalRow = $govCardStartRow + 5;
    $sheet->setCellValue("X{$govTotalRow}", "=SUM(X{$govCardStartRow}:X" . ($govCardStartRow + 3) . ")");
    $sheet->getStyle("X{$govTotalRow}")->applyFromArray([
        'font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
    ]);
    
    // Link B26 (GOV'T deductions) to the sum of GOV'T card
    $sheet->setCellValue("B{$row26}", "=X{$govTotalRow}");
    
    // Freeze panes (freeze top 5 rows and first column)
    $sheet->freezePane('B6');
    
    // Add instructions sheet
    $instructionSheet = $spreadsheet->createSheet();
    $instructionSheet->setTitle('Instructions');
    
    $instructions = [
        ['TB5 DTR Calculator Template - Complete Guide'],
        [''],
        ['TEMPLATE STRUCTURE (Matching DTR-Sample-TB5.xlsm):'],
        ['- Row 1: Period date range (left) and "INPUT BASIC MONTHLY SALARY HERE" instruction (right)'],
        ['- Row 2: EMPLOYEE NAME + Time references + BASIC salary input (₱13,000 default) + Auto-calculated rates'],
        ['- Row 3: Company name + Time threshold references'],
        ['- Row 4-5: Column headers (27 columns A-AA, including trainee columns)'],
        ['- Row 6-36: Data rows (31 days) with auto-calculation formulas'],
        ['- Row 37: TOTALS row with sum formulas (includes trainee totals)'],
        ['- Row 39-45: Net Salary calculation section (includes Trainee Payment)'],
        [''],
        ['COLUMN STRUCTURE (27 columns total):'],
        ['- A: DATE (M/D format)'],
        ['- B-C: AM IN, PM OUT (user input time)'],
        ['- D: ABSENT (mark "X" if absent)'],
        ['- E: OT OUT (formula: auto-shows if PM OUT > 5:00 PM)'],
        ['- F: TOTAL WORK in HOURS (formula: (MOD(C-B,1)*24)-1 = hours worked minus 1hr lunch)'],
        ['- G: LATE in MINUTES (formula: (arrival - grace_end) × 1440 minutes)'],
        ['- H: UNDERTIME in HOURS (formula: if PM OUT < 5:00 PM, hours short)'],
        ['- I: OT in HOURS (formula: if PM OUT > 5:45 PM threshold, hours after 5:00PM)'],
        ['- J: ABSENT count (formula: 1 if D="X", else 0)'],
        ['- K: ABSENT DEDUCT (formula: daily_rate if absent)'],
        ['- L: LATE deduction (formula: per-MINUTE rate × cleaned late MINUTES from Q)'],
        ['- M: UNDERTIME deduction (formula: per-HOUR rate × cleaned undertime HOURS from R)'],
        ['- N: HALFDAY DEDUCT (formula: daily_rate/2 if work hours < 4, auto-calculated)'],
        ['- O: OT PAYMENT (formula: (per-HOUR rate × cleaned OT HOURS from S) × 1.25)'],
        ['- P: NET ADJUSTMENT = OT payment minus SUM of deductions (K+L+M+N)'],
        ['- Q: HELPER - Cleaned late MINUTES (capped at 270 max, else 0)'],
        ['- R: HELPER - Cleaned undertime HOURS (capped at 15 max, else 0)'],
        ['- S: HELPER - Cleaned OT HOURS (IFERROR safety net)'],
        ['- T: (MANUAL) GOV\'T. BENEFITS'],
        ['- U: SALARY (final calculated)'],
        ['- V-W: Flags (F1*, F2*)'],
        ['- X: REMARKS'],
        ['- Y: No. of Trainees (user input, count of trainees handled)'],
        ['- Z: Payment Per Trainee (user input, amount paid per trainee)'],
        ['- AA: Total Trainee Payment (formula: Y × Z, auto-calculated)'],
        [''],
        ['GOV\'T CARD SECTION (Column X/AB, rows 11-16):'],
        ['- SSS: 317.50 (default)'],
        ['- PHILHEALTH: 125.00 (default)'],
        ['- PAGIBIG: 100.00 (default)'],
        ['- CA (Cash Advance): 0 or amount with date'],
        ['- Total: Sum of govt deductions (linked to row 26)'],
        [''],
        ['RATE CONFIGURATION (Row 2):'],
        ['- Cell J2: Basic Monthly Salary (user input, default ₱13,000) - GREEN BACKGROUND'],
        ['- Cell K2: =J2/30 (per-day rate → ₱433.33)'],
        ['- Cell L2: =(J2/30)/8 (per-hour rate → ₱54.17)'],
        ['- Cell M2: =(J2/30)/480 (per-minute rate → ₱0.9028)'],
        [''],
        ['TIME THRESHOLDS (Row 2):'],
        ['- E2: 7:35 AM (grace end / late start threshold)'],
        ['- F2: 5:00 PM (standard end time / OT threshold)'],
        [''],
        ['BOTTOM SECTION (Rows 39-45):'],
        ['- Row 39: BASIC PAY (formula: =J2)'],
        ['- Row 40: OT PAY (formula: =O37 from totals)'],
        ['- Row 41: DTR DEDUCTIONS (formula: =P37 from totals, includes Abs/Late/Under/Halfday)'],
        ['- Row 42: GOV\'T DEDUCTIONS (linked to GOV\'T CARD = SSS+PhilHealth+Pag-IBIG+CA)'],
        ['- Row 43: CASH ADVANCE / OTHER (user input, default ₱0)'],
        ['- Row 44: TRAINEE PAYMENT (formula: =AA37 from totals, sum of all trainee payments)'],
        ['- Row 45: NET SALARY (formula: Basic + OT + Trainee - DTR Ded - Gov\'t - Cash Adv)'],
        [''],
        ['VISUAL STYLING:'],
        ['- All formula cells (E-R, AA): BLACK background with WHITE text'],
        ['- Input cells (A-D, W-Z): White background for easy identification'],
        ['- Headers color-coded: Orange(AM IN/PM OUT), Red(Absent), Blue(OT), Light Blue(Trainee)'],
        ['- Rate input (J2): GREEN background to highlight'],
        ['- Net Salary (row 45): Bold green background'],
        [''],
        ['TRAINEE PAYMENT:'],
        ['- Column Y: Enter number of trainees handled each day'],
        ['- Column Z: Enter payment amount per trainee'],
        ['- Column AA: Auto-calculates (Y × Z) per day'],
        ['- Row 37 (AA37): Shows total trainee payment for the period'],
        ['- Row 44: Trainee payment is included in NET SALARY calculation'],
        [''],
        ['IMPORT INSTRUCTIONS:'],
        ['1. Fill in employee name in cell B2 (replaces "FREEDOM")'],
        ['2. Edit basic salary in cell J2 if different from ₱13,000 (rates auto-calculate)'],
        ['3. Fill time data: AM IN (col B), PM OUT (col C)'],
        ['4. Mark X in col D for absent days'],
        ['5. Enter trainee data: Count (col Y), Payment per trainee (col Z)'],
        ['6. All calculations are automatic via Excel formulas'],
        ['7. Review totals in row 37 (includes trainee payment total)'],
        ['8. Adjust govt deductions (row 42) and cash advance (row 43) as needed'],
        ['9. Final net salary shows in row 45 (green box, includes trainee payment)'],
        ['10. Save and upload the completed Excel file to system'],
        [''],
        ['FORMULA NOTES:'],
        ['- Late is calculated in MINUTES for precise per-minute deduction'],
        ['- Undertime and OT are in HOURS for per-hour rate calculations'],
        ['- OT rate is 1.25× the hourly rate (premium)'],
        ['- Halfday deduction is AUTO-CALCULATED: if work hours < 4, deduct half of daily rate'],
        ['- Absent day = full daily rate deduction'],
        ['- Work hours calculated from AM IN to PM OUT, minus 1 hour for lunch'],
        ['- All $ signs in formulas lock references for copy/paste safety'],
        [''],
        ['TROUBLESHOOTING:'],
        ['- If formulas show #VALUE, check that times are entered as valid Excel time format'],
        ['- Late won\'t calculate if AM IN is blank or marked absent'],
        ['- OT requires PM OUT time after 5:00 PM threshold'],
        ['- Net salary negative? Check deductions aren\'t exceeding basic pay']
    ];
    
    $row = 1;
    foreach ($instructions as $line) {
        $instructionSheet->setCellValue("A{$row}", $line[0] ?? '');
        $row++;
    }
    
    $instructionSheet->getColumnDimension('A')->setWidth(90);
    $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $instructionSheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A11')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A43')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A52')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A59')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A66')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A72')->getFont()->setBold(true)->setSize(11);
    $instructionSheet->getStyle('A83')->getFont()->setBold(true)->setSize(11);
    
    // Wrap text for readability
    $instructionSheet->getStyle('A1:A100')->getAlignment()->setWrapText(true);
    
    // Set first sheet as active
    $spreadsheet->setActiveSheetIndex(0);
    
    // Output file via temp file (bulletproof: no stream contamination)
    $filename = 'DTR_Template_' . date('Y-m-d') . '.xlsx';
    
    // Step 1: Save to temp file first
    $tempFile = tempnam(sys_get_temp_dir(), 'dtr_tpl_') . '.xlsx';
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save($tempFile);
    
    // Step 2: Clean ALL output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Step 3: Send clean binary file
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
 * Generate CSV template as fallback - TB5 Format
 */
function generateCSVTemplate($startDate, $endDate) {
    $filename = 'DTR_Template_TB5_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // Row 1: Title and Period
    $periodText = $startDate && $endDate ? 
        date('M. d', strtotime($startDate)) . '-' . date('d, Y', strtotime($endDate)) : 
        'PAYROLL PERIOD: _______________';
    fputcsv($output, ['DTR CALCULATOR', '', '', '', $periodText]);
    
    // Row 2: Employee info - Empty for user to fill
    fputcsv($output, ['EMPLOYEE NAME:', '', '', '', '5:46 PM', '7:35 AM', '5:00 PM', 'BASIC']);
    
    // Row 3: Company info  
    fputcsv($output, ['THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.', '', '', '', 'ALSO UNDERTIME OR', '', '', '', 'VARIABLE']);
    
    // Row 4: Header level 1
    fputcsv($output, ['MO/YR', 'AM', 'PM', 'ABSENT', 'OT', 'TOT.WORK', 'LATE', 'UNDERTM', 'OT', '(ABSENT)', 'LATE/MIN', 'UNDERTM', 'OT', 'F1*', 'F2*', 'REMARKS']);
    
    // Row 5: Header level 2
    fputcsv($output, ['DATE', 'IN', 'OUT', 'Column', 'OUT', '(in hours)', '(in mins)', '(in hours)', '(in hours)', '(in day)', 'DEDUCT', 'DEDUCT', '(OT PAY)', '', '', '']);
    
    // Determine month and days for CSV template
    $csvMonth = $startDate ? (int)date('n', strtotime($startDate)) : (int)date('n');
    $csvYear = $startDate ? (int)date('Y', strtotime($startDate)) : (int)date('Y');
    $csvDaysInMonth = $startDate ? (int)date('t', strtotime($startDate)) : (int)date('t');
    
    // Data rows with auto-filled dates (1-31 or based on month)
    for ($i = 1; $i <= 31; $i++) {
        $dateVal = $i <= $csvDaysInMonth ? "{$csvMonth}/{$i}" : '';
        fputcsv($output, [
            $dateVal,  // date (auto-filled M/D format)
            '',  // am_in
            '',  // pm_out
            '',  // absent
            '',  // ot_out
            '',  // tot_work
            '',  // late
            '',  // undertime
            '',  // ot_hours
            '',  // absent_day
            '',  // late_deduct
            '',  // undertime_deduct
            '',  // ot_pay
            '',  // f1 flag
            '',  // f2 flag
            ''   // remarks
        ]);
    }
    
    fclose($output);
    exit();
}
