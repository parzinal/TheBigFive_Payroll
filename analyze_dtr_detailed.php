<?php
/**
 * DTR Sample Excel File Analyzer - Enhanced Version
 * Outputs detailed analysis to a text file
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$filePath = 'DTR-Sample-TB5.xlsm';

if (!file_exists($filePath)) {
    die("Error: File not found: $filePath\n");
}

// Start output
$output = [];
$output[] = "=======================================================";
$output[] = "  DTR-Sample-TB5.xlsm COMPREHENSIVE STRUCTURE ANALYSIS";
$output[] = "=======================================================";
$output[] = "File: $filePath";
$output[] = "Size: " . number_format(filesize($filePath)) . " bytes";
$output[] = "Modified: " . date('Y-m-d H:i:s', filemtime($filePath));
$output[] = "";

try {
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    
    $output[] = "Sheet Name: " . $sheet->getTitle();
    $output[] = "Highest Row: " . $sheet->getHighestRow();
    $output[] = "Highest Column: " . $sheet->getHighestColumn();
    $output[] = "";
    
    // === HEADER SECTION (First 10 rows, all columns) ===
    $output[] = "=== DETAILED VIEW: ROWS 1-10 (ALL COLUMNS A-AB) ===";
    $output[] = "";
    
    for ($row = 1; $row <= 10; $row++) {
        $output[] = ">>> ROW $row <<<";
        $colRange = range('A', 'Z');
        $colRange[] = 'AA';
        $colRange[] = 'AB';
        
        foreach ($colRange as $col) {
            $cell = $sheet->getCell($col . $row);
            $value = $cell->getValue();
            
            if ($value !== null && $value !== '') {
                $formattedValue = $cell->getFormattedValue();
                $dataType = $cell->getDataType();
                
                // Build the output line
                $line = "  $col$row: ";
                
                if ($dataType === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
                    $line .= "[FORMULA] " . $value . " = \"$formattedValue\"";
                } else {
                    $line .= "\"$value\"";
                    if ($value !== $formattedValue) {
                        $line .= " (displays as: \"$formattedValue\")";
                    }
                }
                
                // Add style info
                $style = $cell->getStyle();
                $bgColor = $style->getFill()->getStartColor()->getRGB();
                if ($bgColor && $bgColor !== 'FFFFFF' && $bgColor !== '000000') {
                    $line .= " [BG:#$bgColor]";
                }
                
                $fontColor = $style->getFont()->getColor()->getRGB();
                if ($fontColor && $fontColor !== '000000') {
                    $line .= " [FONT:#$fontColor]";
                }
                
                if ($style->getFont()->getBold()) {
                    $line .= " [BOLD]";
                }
                
                $output[] = $line;
            }
        }
        $output[] = "";
    }
    
    // === RATE CONFIGURATION IN ROW 3 ===
    $output[] = "=== RATE CONFIGURATION (ROW 3) ===";
    $output[] = "This row contains the calculation parameters:";
    $output[] = "  N3 (Basic Monthly): " . $sheet->getCell('N3')->getFormattedValue();
    $output[] = "  O3 (Per Day): " . $sheet->getCell('O3')->getValue() . " = " . $sheet->getCell('O3')->getFormattedValue();
    $output[] = "  P3 (Per Minute): " . $sheet->getCell('P3')->getValue() . " = " . $sheet->getCell('P3')->getFormattedValue();
    $output[] = "  Q3 (Per Hour): " . $sheet->getCell('Q3')->getValue() . " = " . $sheet->getCell('Q3')->getFormattedValue();
    $output[] = "";
    
    // === ROW 4 ADDITIONAL INFO ===
    $output[] = "=== ROW 4 - REFERENCE TIMES ===";
    $output[] = "  J4: " . $sheet->getCell('J4')->getFormattedValue() . " (Referenced as \$J\$4 in formulas)";
    $output[] = "  K4: " . $sheet->getCell('K4')->getFormattedValue() . " (Referenced as \$K\$3 in formulas - shift start time)";
    $output[] = "  L4: " . $sheet->getCell('L4')->getFormattedValue() . " (Referenced as \$L\$3 in formulas - shift end time)";
    $output[] = "  S4: " . $sheet->getCell('S4')->getFormattedValue();
    $output[] = "";
    
    // === COLUMN HEADERS (Rows 5-6) ===
    $output[] = "=== COLUMN HEADERS (ROWS 5-6) ===";
    $output[] = "";
    $output[] = "ROW 5 (Main category headers):";
    for ($col = 'A'; $col <= 'Z'; $col++) {
        $val = $sheet->getCell($col . '5')->getFormattedValue();
        if ($val) {
            $output[] = "  $col: $val";
        }
    }
    $output[] = "";
    
    $output[] = "ROW 6 (Detailed column headers):";
    for ($col = 'A'; $col <= 'Z'; $col++) {
        $val = $sheet->getCell($col . '6')->getFormattedValue();
        if ($val) {
            $output[] = "  $col: $val";
        }
    }
    $output[] = "  AA: " . $sheet->getCell('AA6')->getFormattedValue();
    $output[] = "  AB: " . $sheet->getCell('AB6')->getFormattedValue();
    $output[] = "";
    
    // === SAMPLE DATA (Rows 7-12) ===
    $output[] = "=== SAMPLE DATA ROWS 7-12 ===";
    $output[] = "";
    
    for ($row = 7; $row <= 12; $row++) {
        $output[] = "Row $row:";
        $output[] = "  A (Date): " . $sheet->getCell('A' . $row)->getFormattedValue();
        $output[] = "  B (AM In): " . $sheet->getCell('B' . $row)->getFormattedValue();
        $output[] = "  C (AM Out): " . $sheet->getCell('C' . $row)->getFormattedValue();
        $output[] = "  D (PM In): " . $sheet->getCell('D' . $row)->getFormattedValue();
        $output[] = "  E (PM Out): " . $sheet->getCell('E' . $row)->getFormattedValue();
        $output[] = "  F (Absent): " . $sheet->getCell('F' . $row)->getFormattedValue();
        $output[] = "  G (OT Out): " . $sheet->getCell('G' . $row)->getValue();
        $output[] = "  H (Halfday In): " . $sheet->getCell('H' . $row)->getFormattedValue();
        $output[] = "  I (Halfday Out): " . $sheet->getCell('I' . $row)->getFormattedValue();
        $output[] = "  J (Tot Work Hours): " . $sheet->getCell('J' . $row)->getValue();
        $output[] = "  K (Late mins): " . $sheet->getCell('K' . $row)->getValue();
        $output[] = "  L (Undertime hrs): " . $sheet->getCell('L' . $row)->getValue();
        $output[] = "  M (OT hours): " . $sheet->getCell('M' . $row)->getValue();
        $output[] = "";
    }
    
    // === KEY FORMULAS EXPLAINED ===
    $output[] = "=== KEY FORMULAS (Using Row 7 as example) ===";
    $output[] = "";
    $output[] = "Column G (OT Out):";
    $output[] = "  " . $sheet->getCell('G7')->getValue();
    $output[] = "  Logic: Display OT out time if PM out time exceeds threshold";
    $output[] = "";
    
    $output[] = "Column J (Total Work Hours):";
    $output[] = "  " . $sheet->getCell('J7')->getValue();
    $output[] = "  Logic: Calculate work hours from time entries";
    $output[] = "";
    
    $output[] = "Column K (Late in Minutes):";
    $output[] = "  " . $sheet->getCell('K7')->getValue();
    $output[] = "  Logic: Calculate minutes late based on shift start time";
    $output[] = "";
    
    $output[] = "Column L (Undertime in Hours):";
    $output[] = "  " . $sheet->getCell('L7')->getValue();
    $output[] = "  Logic: Calculate undertime hours";
    $output[] = "";
    
    $output[] = "Column M (OT Hours):";
    $output[] = "  " . $sheet->getCell('M7')->getValue();
    $output[] = "  Logic: Complex OT calculation with adjustments";
    $output[] = "";
    
    $output[] = "Column N (Absent Days):";
    $output[] = "  " . $sheet->getCell('N7')->getValue();
    $output[] = "  Logic: Count ABSENT markers in time entry columns";
    $output[] = "";
    
    $output[] = "Column O (Absent Deduction):";
    $output[] = "  " . $sheet->getCell('O7')->getValue();
    $output[] = "  Logic: Per-day rate × absent days";
    $output[] = "";
    
    $output[] = "Column P (Late Deduction):";
    $output[] = "  " . $sheet->getCell('P7')->getValue();
    $output[] = "  Logic: Per-minute rate × late minutes (from column U)";
    $output[] = "";
    
    $output[] = "Column Q (Undertime Deduction):";
    $output[] = "  " . $sheet->getCell('Q7')->getValue();
    $output[] = "  Logic: Per-minute rate × undertime minutes (from column V)";
    $output[] = "";
    
    $output[] = "Column R (Halfday Deduction):";
    $output[] = "  " . $sheet->getCell('R7')->getValue();
    $output[] = "  Logic: Complex calculation involving halfday entries";
    $output[] = "";
    
    $output[] = "Column S (OT Payment):";
    $output[] = "  " . $sheet->getCell('S7')->getValue();
    $output[] = "  Logic: Per-minute rate × OT minutes × 125% (from column W)";
    $output[] = "";
    
    $output[] = "Column T (Total Day Adjustment):";
    $output[] = "  " . $sheet->getCell('T7')->getValue();
    $output[] = "  Logic: OT payment minus all deductions";
    $output[] = "";
    
    $output[] = "Columns U, V, W (Helper calculations):";
    $output[] = "  U7: " . $sheet->getCell('U7')->getValue() . " (Late minutes for calculation)";
    $output[] = "  V7: " . $sheet->getCell('V7')->getValue() . " (Undertime minutes)";
    $output[] = "  W7: " . $sheet->getCell('W7')->getValue() . " (OT hours clean)";
    $output[] = "";
    
    // === MERGED CELLS ===
    $output[] = "=== MERGED CELLS ===";
    $mergedCells = $sheet->getMergeCells();
    foreach ($mergedCells as $range) {
        $topLeft = explode(':', $range)[0];
        $value = $sheet->getCell($topLeft)->getFormattedValue();
        $output[] = "  $range: \"$value\"";
    }
    $output[] = "";
    
    // === COLOR SCHEME SUMMARY ===
    $output[] = "=== COLOR SCHEME SUMMARY ===";
    $output[] = "  - Gray (#D9D9D9): Employee info row";
    $output[] = "  - Light Blue (#B6DEE8): AM time columns";
    $output[] = "  - Orange (#FABF8F): PM time columns";
    $output[] = "  - Dark Gray (#808080): Absent column";
    $output[] = "  - Peach (#FDEADA): Halfday columns";
    $output[] = "  - Dark Gray (#A6A6A6): Calculation columns";
    $output[] = "  - Black (#000000): Data rows (cells with formulas)";
    $output[] = "  - Light Gray (#EAEAEA): Total column (T)";
    $output[] = "";
    
    // === STRUCTURE SUMMARY ===
    $output[] = "=== STRUCTURE SUMMARY ===";
    $output[] = "Row 1: Title 'DTR CALCULATOR'";
    $output[] = "Row 2: Date range (merged A2:G2) + Input instruction in O2";
    $output[] = "Row 3: Employee name (merged A3:I3) + Rate configuration (N3:Q3)";
    $output[] = "Row 4: Company name (merged A4:G4) + Reference times (J4, K4, L4, S4)";
    $output[] = "Row 5: Main category headers";
    $output[] = "Row 6: Detailed column headers";
    $output[] = "Rows 7-37: Data rows (31 days of DTR)";
    $output[] = "Row 38-39: Totals and summaries";
    $output[] = "Row 41-44: Additional calculations (office days, training costs)";
    $output[] = "";
    
    // Write to file
    file_put_contents('DTR_ANALYSIS_REPORT.txt', implode("\n", $output));
    
    echo implode("\n", $output);
    echo "\n\n=== Report saved to DTR_ANALYSIS_REPORT.txt ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
