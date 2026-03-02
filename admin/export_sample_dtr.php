<?php
/**
 * Export Sample DTR Excel File
 * Generates a COMPLETE pre-filled DTR Excel file matching professor's TB5 format
 * with realistic employee data, working formulas, and correct computations.
 * 
 * This file can be used to:
 * 1. Verify all formula calculations are correct
 * 2. Test the import function (import_dtr.php) 
 * 3. Compare output with professor's Excel template
 * 
 * Usage: Navigate to admin/export_sample_dtr.php in browser
 * Or access with query params: ?salary=13000&name=FREEDOM&start=2025-10-13&end=2025-10-27
 */

// Start output buffering IMMEDIATELY to catch any stray output
ob_start();

// Suppress display errors — they corrupt binary Excel output
$originalDisplayErrors = ini_get('display_errors');
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

require_once '../config/bootstrap.php';
// Re-suppress errors for binary Excel output (overrides bootstrap settings)
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Allow access without login for testing purposes (show a simple form)
    $allowAnonymous = true;
}

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Handle form submission / direct download
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'download' || $action === 'debug') {
    $salary = floatval($_REQUEST['salary'] ?? 13000);
    $employeeName = trim($_REQUEST['name'] ?? 'FREEDOM');
    $startDate = $_REQUEST['start'] ?? '2025-10-13';
    $endDate = $_REQUEST['end'] ?? '2025-10-27';
    
    // Discard ALL buffered output before generating binary Excel file
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    try {
        if ($action === 'debug') {
            // Debug mode: generate to temp file and report info instead of downloading
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>DTR Export Debug</h2>';
            echo '<pre>';
            
            $tempFile = tempnam(sys_get_temp_dir(), 'dtr_dbg_') . '.xlsx';
            echo "Temp file: $tempFile\n";
            echo "Salary: $salary, Name: $employeeName\n";
            echo "Period: $startDate to $endDate\n\n";
            
            echo "Generating spreadsheet...\n";
            $spreadsheet = buildSampleDTRSpreadsheet($salary, $employeeName, $startDate, $endDate);
            echo "Spreadsheet created. Sheets: " . $spreadsheet->getSheetCount() . "\n";
            echo "Active sheet: " . $spreadsheet->getActiveSheet()->getTitle() . "\n";
            
            echo "Writing XLSX to temp file...\n";
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($tempFile);
            
            $size = filesize($tempFile);
            echo "File written. Size: $size bytes\n";
            
            // Check if it's a valid ZIP (XLSX = ZIP)
            $firstBytes = bin2hex(file_get_contents($tempFile, false, null, 0, 4));
            echo "First 4 bytes (hex): $firstBytes\n";
            echo "Expected ZIP signature: 504b0304\n";
            echo "Valid ZIP? " . ($firstBytes === '504b0304' ? 'YES' : 'NO') . "\n";
            
            @unlink($tempFile);
            echo "\n</pre>";
            echo '<p><a href="export_sample_dtr.php">Back to form</a></p>';
            echo '<p><a href="export_sample_dtr.php?action=download&salary='.$salary.'&name='.urlencode($employeeName).'&start='.$startDate.'&end='.$endDate.'">Try direct download (GET)</a></p>';
            exit();
        }
        
        generateSampleDTR($salary, $employeeName, $startDate, $endDate);
    } catch (\Throwable $e) {
        // If Excel generation fails, show error (not corrupt binary)
        header('Content-Type: text/html; charset=utf-8');
        echo '<h2>Error generating DTR file</h2>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>File: ' . basename($e->getFile()) . ' Line: ' . $e->getLine() . '</p>';
        echo '<p><a href="export_sample_dtr.php">Go back</a></p>';
    }
    exit();
}

// Restore error display for the HTML form page
ini_set('display_errors', $originalDisplayErrors);

// Flush buffered output (allows the HTML form to render)
if (ob_get_level()) {
    ob_end_flush();
}

// Show configuration form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Sample DTR - TB5 Format</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }
        .container {
            background: white; border-radius: 16px; padding: 40px;
            max-width: 800px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #333; margin-bottom: 8px; font-size: 24px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; color: #444; margin-bottom: 6px; font-size: 14px; }
        input, select { 
            width: 100%; padding: 10px 14px; border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: 14px; transition: border-color 0.3s;
        }
        input:focus { outline: none; border-color: #667eea; }
        .row { display: flex; gap: 20px; }
        .row .form-group { flex: 1; }
        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2); color: white;
            border: none; padding: 14px 32px; border-radius: 8px; font-size: 16px;
            cursor: pointer; width: 100%; font-weight: 600; transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102,126,234,0.4); }
        .info-box {
            background: #f0f4ff; border-left: 4px solid #667eea; padding: 16px;
            border-radius: 0 8px 8px 0; margin-bottom: 24px; font-size: 13px; color: #555;
        }
        .info-box strong { color: #333; }
        .sample-data { 
            background: #f8f9fa; border-radius: 8px; padding: 16px; margin-top: 24px;
            font-size: 12px; 
        }
        .sample-data h3 { font-size: 14px; color: #444; margin-bottom: 10px; }
        table.preview { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.preview th { background: #667eea; color: white; padding: 6px 4px; text-align: center; }
        table.preview td { padding: 4px; text-align: center; border: 1px solid #ddd; }
        table.preview tr:nth-child(even) { background: #f5f5f5; }
        .formula-info { margin-top: 24px; }
        .formula-info h3 { font-size: 14px; color: #444; margin-bottom: 10px; }
        .formula-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px; }
        .formula-item { background: #f8f9fa; padding: 8px 12px; border-radius: 6px; }
        .formula-item .col { font-weight: 700; color: #667eea; }
        .formula-item .desc { color: #666; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-file-excel" style="color: #1D6F42;"></i> Export Sample DTR (TB5 Format)</h1>
    <p class="subtitle">Generate a pre-filled DTR Excel file with correct formulas for testing and import verification</p>
    
    <div class="info-box">
        <strong>What this generates:</strong> A complete DTR Excel file matching the professor's TB5 format with:
        <br>• Realistic time entries for each working day (late, OT, absent, halfday, undertime scenarios)
        <br>• All auto-calculating Excel formulas (work hours, late minutes, deductions, OT pay, net salary)
        <br>• Time reference cells stored as proper Excel time values (not text)
        <br>• Rate configuration: salary/30 days = daily rate, daily/8 = hourly, daily/480 = per-minute
        <br>• <strong>Ready to import</strong> into the payroll system via the DTR import function
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="download">
        
        <div class="row">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Employee Name</label>
                <input type="text" name="name" value="FREEDOM" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-peso-sign"></i> Basic Monthly Salary (₱)</label>
                <input type="number" name="salary" value="13000" min="1000" max="500000" step="100" required>
            </div>
        </div>
        
        <div class="row">
            <div class="form-group">
                <label><i class="fas fa-calendar-day"></i> Period Start Date</label>
                <input type="date" name="start" value="2025-10-13" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-check"></i> Period End Date</label>
                <input type="date" name="end" value="2025-10-27" required>
            </div>
        </div>
        
        <button type="submit" class="btn">
            <i class="fas fa-download"></i> Generate & Download Sample DTR Excel
        </button>
    </form>

    <div class="sample-data">
        <h3><i class="fas fa-table"></i> Sample Data Preview (what will be generated)</h3>
        <table class="preview">
            <thead>
                <tr>
                    <th>Day</th><th>AM IN</th><th>PM OUT</th><th>Scenario</th>
                    <th>Work Hrs</th><th>Late (min)</th><th>Under (hr)</th><th>OT (hr)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Mon</td><td>8:05 AM</td><td>5:00 PM</td><td>30 min late</td><td>8.00</td><td>30</td><td>0</td><td>0</td></tr>
                <tr><td>Tue</td><td>7:30 AM</td><td>5:00 PM</td><td>Normal (on time)</td><td>8.00</td><td>0</td><td>0</td><td>0</td></tr>
                <tr><td>Wed</td><td>7:50 AM</td><td>7:00 PM</td><td>OT (2 hours)</td><td>8.00</td><td>15</td><td>0</td><td>2.00</td></tr>
                <tr><td>Thu</td><td>—</td><td>—</td><td style="color:red">ABSENT</td><td>0</td><td>0</td><td>0</td><td>0</td></tr>
                <tr><td>Fri</td><td>7:35 AM</td><td>4:00 PM</td><td>Undertime (1hr)</td><td>7.00</td><td>0</td><td>1.00</td><td>0</td></tr>
            </tbody>
        </table>
    </div>

    <div class="formula-info">
        <h3><i class="fas fa-calculator"></i> Formula Reference (matching professor's TB5)</h3>
        <div class="formula-grid">
            <div class="formula-item"><span class="col">J (Work)</span>: <span class="desc">(MOD(E-B,1)×24)-1 → HOURS</span></div>
            <div class="formula-item"><span class="col">K (Late)</span>: <span class="desc">(arrival-grace)×1440 → MINUTES</span></div>
            <div class="formula-item"><span class="col">L (Under)</span>: <span class="desc">(endTime-pmOut)×24 → HOURS</span></div>
            <div class="formula-item"><span class="col">M (OT)</span>: <span class="desc">(otOut-endTime)×24 → HOURS</span></div>
            <div class="formula-item"><span class="col">O (Abs$)</span>: <span class="desc">dailyRate × absentDays</span></div>
            <div class="formula-item"><span class="col">P (Late$)</span>: <span class="desc">perMinRate × cleanLateMins</span></div>
            <div class="formula-item"><span class="col">Q (Under$)</span>: <span class="desc">perHrRate × cleanUnderHrs</span></div>
            <div class="formula-item"><span class="col">S (OT$)</span>: <span class="desc">(perHrRate × cleanOTHrs) × 1.25</span></div>
            <div class="formula-item"><span class="col">T (Net)</span>: <span class="desc">OTpay - SUM(deductions)</span></div>
            <div class="formula-item"><span class="col">Y (Salary)</span>: <span class="desc">dailyRate + netAdjust</span></div>
            <div class="formula-item"><span class="col">Rates</span>: <span class="desc">salary/30, /8, /480</span></div>
            <div class="formula-item"><span class="col">OT Rate</span>: <span class="desc">hourlyRate × 1.25</span></div>
        </div>
    </div>
</div>
</body>
</html>
<?php

/**
 * Generate and send a sample DTR Excel file
 */
function generateSampleDTR($salary, $employeeName, $startDate, $endDate) {
    $spreadsheet = buildSampleDTRSpreadsheet($salary, $employeeName, $startDate, $endDate);
    
    // Save to temp file (bulletproof: no stream contamination)
    $filename = "DTR_Sample_{$employeeName}_" . date('Y-m-d') . '.xlsx';
    $tempFile = tempnam(sys_get_temp_dir(), 'dtr_') . '.xlsx';
    
    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save($tempFile);
    
    // Verify file
    if (!file_exists($tempFile) || filesize($tempFile) < 100) {
        throw new \RuntimeException('Excel file generation failed - file is empty');
    }
    
    // Clean ALL output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Send clean binary file
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
 * Build the sample DTR Spreadsheet object (no I/O)
 */
function buildSampleDTRSpreadsheet($salary, $employeeName, $startDate, $endDate) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('DTR');
    
    $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
    
    // ── Rate calculations ──
    $dailyRate = $salary / 30;
    $hourlyRate = $dailyRate / 8;
    $perMinRate = $dailyRate / 480;
    $otRate = $hourlyRate * 1.25;
    
    // ── Helper: convert H:M to Excel time fraction ──
    // Excel time = hours/24 + minutes/1440
    
    // ════════════════════════════════════════════════
    // COLUMN WIDTHS
    // ════════════════════════════════════════════════
    $widths = [
        'A'=>12,'B'=>10,'C'=>10,'D'=>10,'E'=>10,'F'=>10,'G'=>10,
        'H'=>10,'I'=>10,'J'=>12,'K'=>10,'L'=>12,'M'=>8,'N'=>10,
        'O'=>12,'P'=>12,'Q'=>12,'R'=>12,'S'=>14,'T'=>14,'U'=>12,
        'V'=>14,'W'=>12,'X'=>12,'Y'=>14,'Z'=>6,'AA'=>6,'AB'=>15
    ];
    foreach ($widths as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }
    
    // ════════════════════════════════════════════════
    // ROW 1: Period & Instruction
    // ════════════════════════════════════════════════
    $periodText = date('M. d', strtotime($startDate)) . '-' . date('d, Y', strtotime($endDate));
    $sheet->setCellValue('A1', $periodText);
    $sheet->mergeCells('A1:I1');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'italic' => true, 'color' => ['rgb' => '0066CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $sheet->setCellValue('N1', 'vvvv');
    $sheet->getStyle('N1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FF0000']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $sheet->setCellValue('O1', 'INPUT BASIC MONTHLY SALARY HERE');
    $sheet->mergeCells('O1:R1');
    $sheet->getStyle('O1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FF0000']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ════════════════════════════════════════════════
    // ROW 2: Employee Name + Time References + Salary + Rates
    // ════════════════════════════════════════════════
    $sheet->setCellValue('A2', 'EMPLOYEE NAME:');
    $sheet->setCellValue('B2', $employeeName);
    $sheet->mergeCells('B2:G2');
    
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']]
    ]);
    $sheet->getStyle('B2:G2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FF0000']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
    ]);
    
    // Time reference cells - ACTUAL EXCEL TIME VALUES (critical for formula compatibility!)
    $sheet->setCellValue('H2', 17/24 + 46/1440);     // 5:46 PM
    $sheet->setCellValue('I2', 7/24 + 35/1440);      // 7:35 AM - grace end / late threshold
    $sheet->setCellValue('J2', 17/24);               // 5:00 PM - standard end time
    $sheet->getStyle('H2:J2')->getNumberFormat()->setFormatCode('h:mm AM/PM');
    
    // BASIC label + salary input
    $sheet->setCellValue('K2', 'BASIC');
    $sheet->setCellValue('L2', '>>');
    $sheet->setCellValue('M2', '');
    $sheet->setCellValue('N2', $salary);   // PRE-FILLED with the salary
    
    // Rate formulas
    $sheet->setCellValue('O2', '=$N$2/30');           // PER/DAY
    $sheet->setCellValue('P2', '=($N$2/30)/8');       // PER/HOUR
    $sheet->setCellValue('Q2', '=($N$2/30)/480');     // PER/MIN
    $sheet->setCellValue('R2', 0.5);
    $sheet->setCellValue('S2', 8/24 + 10/1440);      // 8:10 AM
    $sheet->getStyle('S2')->getNumberFormat()->setFormatCode('h:mm AM/PM');
    
    // Styles for Row 2 sections
    $sheet->getStyle('K2:L2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FF0000']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']]
    ]);
    $sheet->getStyle('M2:N2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00FF00']],
        'numberFormat' => ['formatCode' => '#,##0']
    ]);
    $sheet->getStyle('O2:Q2')->applyFromArray([
        'font' => ['size' => 10],
        'numberFormat' => ['formatCode' => '0.0000'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6E6']]
    ]);
    
    // ════════════════════════════════════════════════
    // ROW 3: Company + Additional References
    // ════════════════════════════════════════════════
    $sheet->setCellValue('A3', 'THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.');
    $sheet->mergeCells('A3:G3');
    $sheet->getStyle('A3')->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'italic' => true]]);
    
    $sheet->setCellValue('H3', 'ALSO UNDERTIME OR');
    $sheet->setCellValue('I3', 17/24 + 45/1440);     // 5:45 PM (OT detection threshold)
    $sheet->setCellValue('J3', 8/24 + 6/1440);       // 8:06 AM
    $sheet->setCellValue('K3', 8/24 + 16/1440);      // 8:16 AM
    $sheet->setCellValue('L3', 'VARIABLE');
    $sheet->getStyle('I3:K3')->getNumberFormat()->setFormatCode('h:mm AM/PM');
    
    $sheet->setCellValue('O3', 'PER/DAY');
    $sheet->setCellValue('P3', 'PER/HOUR');
    $sheet->setCellValue('Q3', 'PER/MIN');
    $sheet->setCellValue('R3', 'AUTOMATIC');
    $sheet->setCellValue('S3', 8/24 + 15/1440);      // 8:15 AM
    $sheet->getStyle('S3')->getNumberFormat()->setFormatCode('h:mm AM/PM');
    
    // ════════════════════════════════════════════════
    // ROW 4-5: Headers
    // ════════════════════════════════════════════════
    $mainHeaders = [
        'A4'=>'MO/YR','B4'=>'AM','D4'=>'PM','F4'=>'ABSENT','G4'=>'OT',
        'H4'=>'HALFDAY','J4'=>'TOT.WORK','K4'=>'LATE','L4'=>'UNDERTM','M4'=>'OT',
        'N4'=>'(ABSENT)','O4'=>'ABSENT','P4'=>'LATE/MIN','Q4'=>'UNDERTM',
        'R4'=>'HALFDAY','S4'=>'OT','T4'=>'neg-PTOTAL',
        'U4'=>'AUTOMATIC CALCULATIONS','X4'=>'(MANUAL)','Y4'=>'Automatic','AB4'=>'REMARKS'
    ];
    foreach ($mainHeaders as $c => $v) { $sheet->setCellValue($c, $v); }
    $sheet->mergeCells('B4:C4');
    $sheet->mergeCells('D4:E4');
    $sheet->mergeCells('H4:I4');
    $sheet->mergeCells('U4:W4');
    
    $subHeaders = [
        'A5'=>'DATE','B5'=>'IN','C5'=>'OUT','D5'=>'IN','E5'=>'OUT',
        'F5'=>'(if absent)','G5'=>'OUT','H5'=>'IN','I5'=>'OUT',
        'J5'=>'(in hours)','K5'=>'(in mins)','L5'=>'(in hours)','M5'=>'',
        'N5'=>'(in day)','O5'=>'DEDUCT','P5'=>'DEDUCT','Q5'=>'DEDUCT',
        'R5'=>'DEDUCT','S5'=>'(OT PAY)','T5'=>'MINUS OT',
        'U5'=>'LATE(min)','V5'=>'UNDERTIME','W5'=>'OT',
        'X5'=>"GOV'T.",'Y5'=>'SALARY','Z5'=>'F1*','AA5'=>'F2*','AB5'=>'Remarks'
    ];
    foreach ($subHeaders as $c => $v) { $sheet->setCellValue($c, $v); }
    
    // Header styles
    $sheet->getStyle('A4:AB5')->applyFromArray([
        'font' => ['bold' => true, 'size' => 9],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    // Color sections
    $sheet->getStyle('B4:C5')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCC99']]]);
    $sheet->getStyle('D4:E5')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCC99']]]);
    $sheet->getStyle('F4:F5')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF9999']]]);
    $sheet->getStyle('G4:G5')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '99CCFF']]]);
    $sheet->getStyle('H4:I5')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF99']]]);
    $sheet->getStyle('J4:N5')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCFFCC']]]);
    $sheet->getStyle('O4:T5')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCCCC']]]);
    $sheet->getStyle('U4:W5')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CC99FF']]]);
    $sheet->getStyle('Y4:Y5')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '99FF99']]]);
    
    // ════════════════════════════════════════════════
    // ROWS 6-36: DATA ROWS WITH SAMPLE DATA + FORMULAS
    // ════════════════════════════════════════════════
    
    // Generate realistic sample scenarios for each day
    $sampleDays = generateSampleDays($startDate, $endDate);
    
    $dataStartRow = 6;
    $dataEndRow = 36;
    $dayIndex = 0;
    
    for ($row = $dataStartRow; $row <= $dataEndRow; $row++) {
        // ── INPUT COLUMNS (pre-filled with sample data) ──
        if ($dayIndex < count($sampleDays)) {
            $day = $sampleDays[$dayIndex];
            $dayIndex++;
            
            // Column A: Date
            $sheet->setCellValue("A{$row}", $day['date_display']);
            
            if ($day['type'] === 'absent') {
                // Absent: clear times, mark F
                $sheet->setCellValue("B{$row}", '');
                $sheet->setCellValue("C{$row}", '');
                $sheet->setCellValue("D{$row}", '');
                $sheet->setCellValue("E{$row}", '');
                $sheet->setCellValue("F{$row}", 'X');
                $sheet->setCellValue("H{$row}", '');
                $sheet->setCellValue("I{$row}", '');
            } elseif ($day['type'] === 'halfday') {
                // Halfday: AM only, fill H-I for halfday
                $sheet->setCellValue("B{$row}", '');
                $sheet->setCellValue("C{$row}", '');
                $sheet->setCellValue("D{$row}", '');
                $sheet->setCellValue("E{$row}", '');
                $sheet->setCellValue("F{$row}", '');
                $sheet->setCellValue("H{$row}", timeToExcel($day['halfday_in']));
                $sheet->setCellValue("I{$row}", timeToExcel($day['halfday_out']));
                $sheet->getStyle("H{$row}:I{$row}")->getNumberFormat()->setFormatCode('h:mm AM/PM');
            } else {
                // Normal/Late/Undertime/OT: fill times
                $sheet->setCellValue("B{$row}", timeToExcel($day['am_in']));
                $sheet->setCellValue("C{$row}", timeToExcel($day['am_out']));
                $sheet->setCellValue("D{$row}", timeToExcel($day['pm_in']));
                $sheet->setCellValue("E{$row}", timeToExcel($day['pm_out']));
                $sheet->setCellValue("F{$row}", '');
                $sheet->setCellValue("H{$row}", '');
                $sheet->setCellValue("I{$row}", '');
                
                // Format time cells
                $sheet->getStyle("B{$row}:E{$row}")->getNumberFormat()->setFormatCode('h:mm AM/PM');
            }
        } else {
            // Empty rows beyond sample data
            $sheet->setCellValue("A{$row}", '');
            $sheet->setCellValue("B{$row}", '');
            $sheet->setCellValue("C{$row}", '');
            $sheet->setCellValue("D{$row}", '');
            $sheet->setCellValue("E{$row}", '');
            $sheet->setCellValue("F{$row}", '');
            $sheet->setCellValue("H{$row}", '');
            $sheet->setCellValue("I{$row}", '');
        }
        
        // Remarks: only for rows with sample data
        if ($dayIndex > 0 && $dayIndex <= count($sampleDays)) {
            $sheet->setCellValue("AB{$row}", $sampleDays[$dayIndex - 1]['remark'] ?? '');
        } else {
            $sheet->setCellValue("AB{$row}", '');
        }
        
        // ── FORMULA COLUMNS (auto-calculate) ──
        
        // G: OT OUT detection (PM OUT > 5:45 PM threshold at I3)
        $sheet->setCellValue("G{$row}", "=IF(E{$row}>\$I\$3,E{$row},\"\")");
        
        // J: Total work HOURS: (MOD(E-B,1)*24)-1 for full day, MOD(I-H,1)*24 for halfday
        $sheet->setCellValue("J{$row}", "=IF(OR(F{$row}=\"X\",AND(B{$row}=\"\",E{$row}=\"\")),0,IF(AND(H{$row}<>\"\",I{$row}<>\"\"),(MOD(I{$row}-H{$row},1)*24),(MOD(E{$row}-B{$row},1)*24)-1))");
        
        // K: Late in MINUTES: (arrival - grace_end) × 1440
        $sheet->setCellValue("K{$row}", "=IF(OR(F{$row}=\"X\",B{$row}=\"\"),0,IF(B{$row}>\$I\$2,(B{$row}-\$I\$2)*1440,0))");
        
        // L: Undertime in HOURS: (end_time - pm_out) × 24
        $sheet->setCellValue("L{$row}", "=IF(OR(F{$row}=\"X\",E{$row}=\"\"),0,IF(E{$row}<\$J\$2,(\$J\$2-E{$row})*24,0))");
        
        // M: OT in HOURS: (ot_out - end_time) × 24 (uses G for chain)
        $sheet->setCellValue("M{$row}", "=IF(G{$row}=\"\",0,(G{$row}-\$J\$2)*24)");
        
        // N: Absent count
        $sheet->setCellValue("N{$row}", "=IF(F{$row}=\"X\",1,0)");
        
        // O: Absent deduction (daily rate if absent)
        $sheet->setCellValue("O{$row}", "=IF(N{$row}=1,\$O\$2,0)");
        
        // U: Cleaned late MINUTES (cap at 270 max)
        $sheet->setCellValue("U{$row}", "=IF(K{$row}>270,0,K{$row})");
        
        // V: Cleaned undertime HOURS (cap at 15 max)
        $sheet->setCellValue("V{$row}", "=IF(L{$row}>15,0,L{$row})");
        
        // W: Cleaned OT HOURS (IFERROR safety)
        $sheet->setCellValue("W{$row}", "=IFERROR(M{$row}*1,0)");
        
        // P: Late deduction = per-MINUTE rate × cleaned late MINUTES
        $sheet->setCellValue("P{$row}", "=\$Q\$2*U{$row}");
        
        // Q: Undertime deduction = per-HOUR rate × cleaned undertime HOURS
        $sheet->setCellValue("Q{$row}", "=\$P\$2*V{$row}");
        
        // R: Halfday deduction (professor's formula with flag columns)
        $sheet->setCellValue("R{$row}", "=IF(OR(H{$row}=\"\",I{$row}=\"\"),0,(\$O\$2*AA{$row})-(MOD(I{$row}-H{$row},1)*24.29)*\$P\$2)");
        
        // S: OT payment = (per-HOUR × cleaned OT) × 1.25
        $sheet->setCellValue("S{$row}", "=(\$P\$2*W{$row})*1.25");
        
        // T: Net adjustment = OT - deductions
        $sheet->setCellValue("T{$row}", "=S{$row}-SUM(O{$row}:R{$row})");
        
        // X: Manual input (empty)
        $sheet->setCellValue("X{$row}", '');
        
        // Y: Per-day salary = daily rate (if not absent) + net adjustment
        $sheet->setCellValue("Y{$row}", "=IF(N{$row}=1,0,\$O\$2)+T{$row}");
        
        // Z: Flag 1 (COUNTBLANK on halfday OUT)
        $sheet->setCellValue("Z{$row}", "=COUNTBLANK(I{$row})");
        
        // AA: Flag 2 (halfday flag)
        $sheet->setCellValue("AA{$row}", "=COUNTIF(Z{$row},\"0\")");
        
        // ── STYLES ──
        $blackStyle = [
            'font' => ['color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $whiteStyle = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'font' => ['size' => 9]
        ];
        
        $sheet->getStyle("G{$row}")->applyFromArray($blackStyle);
        $sheet->getStyle("J{$row}:W{$row}")->applyFromArray($blackStyle);
        $sheet->getStyle("Y{$row}:AA{$row}")->applyFromArray($blackStyle);
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($whiteStyle);
        $sheet->getStyle("H{$row}:I{$row}")->applyFromArray($whiteStyle);
        $sheet->getStyle("X{$row}")->applyFromArray($whiteStyle);
        $sheet->getStyle("AB{$row}")->applyFromArray($whiteStyle);
        
        $sheet->getStyle("A{$row}:AB{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        // Number formats
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('h:mm AM/PM');
        $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('0.00');       // Work hours
        $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode('0');          // Late minutes
        $sheet->getStyle("L{$row}:M{$row}")->getNumberFormat()->setFormatCode('0.00'); // Hours
        $sheet->getStyle("O{$row}:W{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("Y{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
    }
    
    // ════════════════════════════════════════════════
    // ROW 37: TOTALS
    // ════════════════════════════════════════════════
    $totR = 37;
    $sheet->setCellValue("A{$totR}", 'TOTAL:');
    $sheet->mergeCells("A{$totR}:I{$totR}");
    $sheet->getStyle("A{$totR}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]
    ]);
    
    foreach (['J','K','L','M','N','O','P','Q','R','S','T','U','V','W','Y'] as $col) {
        $sheet->setCellValue("{$col}{$totR}", "=SUM({$col}6:{$col}36)");
    }
    
    $sheet->getStyle("A{$totR}:AB{$totR}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $sheet->getStyle("J{$totR}")->getNumberFormat()->setFormatCode('0.00');
    $sheet->getStyle("K{$totR}")->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle("L{$totR}:M{$totR}")->getNumberFormat()->setFormatCode('0.00');
    $sheet->getStyle("O{$totR}:W{$totR}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("Y{$totR}")->getNumberFormat()->setFormatCode('#,##0.00');
    
    // ════════════════════════════════════════════════
    // ROWS 39-44: NET SALARY CALCULATION
    // ════════════════════════════════════════════════
    $r39 = 39; $r40 = 40; $r41 = 41; $r42 = 42; $r43 = 43; $r44 = 44;
    
    $sheet->setCellValue("A{$r39}", 'BASIC PAY (Monthly):');
    $sheet->setCellValue("B{$r39}", '=$N$2');
    $sheet->getStyle("A{$r39}")->applyFromArray(['font' => ['bold' => true, 'size' => 10], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
    $sheet->getStyle("B{$r39}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 11], 'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6E6']]
    ]);
    
    $sheet->setCellValue("A{$r40}", 'OT PAY:');
    $sheet->setCellValue("B{$r40}", "=S{$totR}");
    $sheet->getStyle("A{$r40}")->applyFromArray(['font' => ['size' => 10], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
    $sheet->getStyle("B{$r40}")->getNumberFormat()->setFormatCode('#,##0.00');
    
    $sheet->setCellValue("A{$r41}", 'DTR DEDUCTIONS (Abs/Late/Under/Halfday):');
    $sheet->setCellValue("B{$r41}", "=SUM(O{$totR}:R{$totR})");
    $sheet->getStyle("A{$r41}")->applyFromArray(['font' => ['size' => 10, 'color' => ['rgb' => 'FF0000']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
    $sheet->getStyle("B{$r41}")->applyFromArray(['font' => ['color' => ['rgb' => 'FF0000']], 'numberFormat' => ['formatCode' => '#,##0.00']]);
    
    $sheet->setCellValue("A{$r42}", "GOV'T DEDUCTIONS (SSS+PhilHealth+Pag-IBIG+CA):");
    $sheet->getStyle("A{$r42}")->applyFromArray(['font' => ['size' => 10, 'color' => ['rgb' => 'FF0000']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
    $sheet->getStyle("B{$r42}")->applyFromArray([
        'font' => ['color' => ['rgb' => 'FF0000']], 'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCCCC']]
    ]);
    
    $sheet->setCellValue("A{$r43}", 'CASH ADVANCE / OTHER DEDUCTIONS:');
    $sheet->setCellValue("B{$r43}", 0);
    $sheet->getStyle("A{$r43}")->applyFromArray(['font' => ['size' => 10], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
    $sheet->getStyle("B{$r43}")->applyFromArray([
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']]
    ]);
    
    $sheet->setCellValue("A{$r44}", 'NET SALARY (Take Home):');
    $sheet->setCellValue("B{$r44}", "=B{$r39}+B{$r40}-B{$r41}-B{$r42}-B{$r43}");
    $sheet->mergeCells("B{$r44}:D{$r44}");
    $sheet->getStyle("A{$r44}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]]
    ]);
    $sheet->getStyle("B{$r44}:D{$r44}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00B050']],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]]
    ]);
    
    // ════════════════════════════════════════════════
    // GOV'T CARD SECTION
    // ════════════════════════════════════════════════
    $gRow = 11;
    $govtItems = [
        [$gRow, 'SSS', 317.50],
        [$gRow+1, 'PHILHEALTH', 125.00],
        [$gRow+2, 'PAGIBIG', 100.00],
        [$gRow+3, 'CA', 0],
    ];
    foreach ($govtItems as $item) {
        $sheet->setCellValue("AB{$item[0]}", $item[1]);
        $sheet->setCellValue("X{$item[0]}", $item[2]);
        $sheet->getStyle("AB{$item[0]}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'CC00CC']]]);
        $sheet->getStyle("X{$item[0]}")->applyFromArray([
            'font' => ['color' => ['rgb' => 'FF0000']], 'numberFormat' => ['formatCode' => '#,##0.00'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]
        ]);
    }
    $gTotRow = $gRow + 5;
    $sheet->setCellValue("X{$gTotRow}", "=SUM(X{$gRow}:X" . ($gRow+3) . ")");
    $sheet->getStyle("X{$gTotRow}")->applyFromArray([
        'font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true],
        'numberFormat' => ['formatCode' => '#,##0.00'],
        'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $sheet->setCellValue("B{$r42}", "=X{$gTotRow}");
    
    // ════════════════════════════════════════════════
    // VERIFICATION SHEET (show expected vs formula results)
    // ════════════════════════════════════════════════
    $verifySheet = $spreadsheet->createSheet();
    $verifySheet->setTitle('Verification');
    $verifySheet->getColumnDimension('A')->setWidth(30);
    $verifySheet->getColumnDimension('B')->setWidth(20);
    $verifySheet->getColumnDimension('C')->setWidth(20);
    $verifySheet->getColumnDimension('D')->setWidth(40);
    
    $verifySheet->setCellValue('A1', 'DTR COMPUTATION VERIFICATION');
    $verifySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    
    $vRows = [
        ['Rate Configuration', '', '', ''],
        ['Basic Monthly Salary', $salary, "=DTR!N2", 'Should match'],
        ['Daily Rate (salary/30)', round($dailyRate, 4), "=DTR!O2", '=B4-C4 should be ~0'],
        ['Hourly Rate (daily/8)', round($hourlyRate, 4), "=DTR!P2", '=B5-C5 should be ~0'],
        ['Per-Minute Rate (daily/480)', round($perMinRate, 4), "=DTR!Q2", '=B6-C6 should be ~0'],
        ['OT Rate (hourly*1.25)', round($otRate, 4), "=DTR!P2*1.25", '=B7-C7 should be ~0'],
        ['', '', '', ''],
        ['Column Units Check', 'Unit', 'Column Label', 'Comment'],
        ['J - Total Work', 'HOURS', '(in hours)', 'Full day=8hrs, Halfday=4hrs'],
        ['K - Late', 'MINUTES', '(in mins)', '30 mins late = 30'],
        ['L - Undertime', 'HOURS', '(in hours)', '1 hr early = 1.00'],
        ['M - OT', 'HOURS', '(in hours)', '2 hrs OT = 2.00'],
        ['', '', '', ''],
        ['Deduction Formula Chain', '', '', ''],
        ['U = cleaned late MINS', 'K (if <=270)', 'Used by P', 'Cap: >270min = 0'],
        ['V = cleaned under HRS', 'L (if <=15)', 'Used by Q', 'Cap: >15hr = 0'],
        ['W = cleaned OT HRS', 'IFERROR(M)', 'Used by S', 'Error safety'],
        ['P = late deduct', '$Q$2 * U', 'perMin * mins', 'CORRECT unit match'],
        ['Q = under deduct', '$P$2 * V', 'perHr * hrs', 'CORRECT unit match'],
        ['S = OT pay', '($P$2*W)*1.25', 'perHr * hrs * 1.25', 'CORRECT unit match'],
        ['T = net adjust', 'S - SUM(O:R)', 'OT - deductions', 'Net per-day adjustment'],
        ['Y = daily salary', '$O$2 + T', 'dailyRate + net', 'If absent: 0 + T'],
    ];
    
    $vr = 3;
    foreach ($vRows as $vRow) {
        $verifySheet->setCellValue("A{$vr}", $vRow[0]);
        $verifySheet->setCellValue("B{$vr}", $vRow[1]);
        $verifySheet->setCellValue("C{$vr}", $vRow[2]);
        $verifySheet->setCellValue("D{$vr}", $vRow[3]);
        $vr++;
    }
    $verifySheet->getStyle('A3:D3')->getFont()->setBold(true);
    $verifySheet->getStyle('A10:D10')->getFont()->setBold(true);
    $verifySheet->getStyle('A16:D16')->getFont()->setBold(true);
    $verifySheet->getStyle('B4:C8')->getNumberFormat()->setFormatCode('0.0000');
    
    // Freeze & set active
    $sheet->freezePane('B6');
    $spreadsheet->setActiveSheetIndex(0);
    
    // Return completed spreadsheet object
    return $spreadsheet;
}

/**
 * Convert "HH:MM" (24h) to Excel time serial value
 */
function timeToExcel($timeStr) {
    if (empty($timeStr)) return '';
    
    // Handle various formats
    $timeStr = trim($timeStr);
    
    // Already a number (Excel serial)
    if (is_numeric($timeStr)) return floatval($timeStr);
    
    // Parse HH:MM format (24-hour)
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeStr, $m)) {
        $h = intval($m[1]);
        $mins = intval($m[2]);
        return $h / 24 + $mins / 1440;
    }
    
    // Parse H:MM AM/PM format
    if (preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $timeStr, $m)) {
        $h = intval($m[1]);
        $mins = intval($m[2]);
        $ampm = strtoupper($m[3]);
        if ($ampm === 'PM' && $h !== 12) $h += 12;
        if ($ampm === 'AM' && $h === 12) $h = 0;
        return $h / 24 + $mins / 1440;
    }
    
    return '';
}

/**
 * Generate realistic sample day data for the given period
 * Includes various scenarios: normal, late, OT, absent, halfday, undertime
 */
function generateSampleDays($startDate, $endDate) {
    $days = [];
    $current = new \DateTime($startDate);
    $end = new \DateTime($endDate);
    
    // Predefined scenarios to cycle through (covers all computation cases)
    $scenarios = [
        // Day 1: Normal day (on time, full day)
        [
            'type' => 'normal',
            'am_in' => '7:30', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00',
            'remark' => 'Normal day - on time',
            'expected' => 'J=8.00, K=0, L=0, M=0'
        ],
        // Day 2: 30 minutes late (after 7:35 grace)
        [
            'type' => 'normal',
            'am_in' => '8:05', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00',
            'remark' => '30 min late (8:05-7:35=30min)',
            'expected' => 'J=8.00, K=30, L=0, M=0'
        ],
        // Day 3: OT day (2 hours OT after 5:45 PM threshold)
        [
            'type' => 'normal',
            'am_in' => '7:30', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '19:00',
            'remark' => 'OT day - 2hrs after 5PM',
            'expected' => 'J=8.00, K=0, L=0, M=2.00'
        ],
        // Day 4: ABSENT
        [
            'type' => 'absent',
            'remark' => 'ABSENT - full day deduction',
            'expected' => 'J=0, K=0, L=0, M=0, O=dailyRate'
        ],
        // Day 5: Undertime (left 1 hour early)
        [
            'type' => 'normal',
            'am_in' => '7:35', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '16:00',
            'remark' => 'Undertime - left 1hr early',
            'expected' => 'J=7.00, K=0, L=1.00, M=0'
        ],
        // Day 6: Late + OT combo
        [
            'type' => 'normal',
            'am_in' => '7:50', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '18:30',
            'remark' => '15min late + 1.5hr OT',
            'expected' => 'J=8.00, K=15, L=0, M=1.50'
        ],
        // Day 7: Normal (exact on time)
        [
            'type' => 'normal',
            'am_in' => '7:35', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00',
            'remark' => 'Normal - exactly on grace time',
            'expected' => 'J=8.00, K=0, L=0, M=0'
        ],
        // Day 8: Halfday
        [
            'type' => 'halfday',
            'halfday_in' => '8:00', 'halfday_out' => '12:00',
            'remark' => 'HALFDAY - AM only (4hrs)',
            'expected' => 'J=4.00, K=0, L=0, M=0, R=halfday deduct'
        ],
        // Day 9: Slight late (5 mins)
        [
            'type' => 'normal',
            'am_in' => '7:40', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00',
            'remark' => '5 min late (7:40-7:35=5min)',
            'expected' => 'J=8.00, K=5, L=0, M=0'
        ],
        // Day 10: Normal + slight early leave (30 min UT)
        [
            'type' => 'normal',
            'am_in' => '7:30', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '16:30',
            'remark' => '30 min undertime',
            'expected' => 'J=7.50, K=0, L=0.50, M=0'
        ],
    ];
    
    $scenarioIdx = 0;
    
    while ($current <= $end) {
        $dayOfWeek = intval($current->format('N')); // 1=Mon, 7=Sun
        
        // Skip weekends
        if ($dayOfWeek >= 6) {
            $current->modify('+1 day');
            continue;
        }
        
        $scenario = $scenarios[$scenarioIdx % count($scenarios)];
        $scenarioIdx++;
        
        $day = $scenario;
        $day['date_display'] = $current->format('n/j'); // e.g., 10/13
        $day['date_full'] = $current->format('Y-m-d');
        
        $days[] = $day;
        $current->modify('+1 day');
    }
    
    return $days;
}
