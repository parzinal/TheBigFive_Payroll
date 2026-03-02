<?php
/**
 * DTR Sample Excel File Analyzer
 * Reads and documents the exact structure of DTR-Sample-TB5.xlsm
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$filePath = 'DTR-Sample-TB5.xlsm';

if (!file_exists($filePath)) {
    die("Error: File not found: $filePath\n");
}

echo "=== DTR-Sample-TB5.xlsm STRUCTURE ANALYSIS ===\n";
echo "File: $filePath\n";
echo "Size: " . number_format(filesize($filePath)) . " bytes\n";
echo "Modified: " . date('Y-m-d H:i:s', filemtime($filePath)) . "\n\n";

try {
    // Load the spreadsheet
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $sheetTitle = $sheet->getTitle();
    
    echo "Sheet Name: $sheetTitle\n";
    echo "Highest Row: " . $sheet->getHighestRow() . "\n";
    echo "Highest Column: " . $sheet->getHighestColumn() . "\n\n";
    
    // === SECTION 1: ROWS 1-5 DETAILED ANALYSIS ===
    echo "=== ROWS 1-5: HEADER SECTION ===\n";
    for ($row = 1; $row <= 5; $row++) {
        echo "\n--- ROW $row ---\n";
        for ($col = 'A'; $col <= 'Z'; $col++) {
            $cell = $sheet->getCell($col . $row);
            $value = $cell->getValue();
            
            if ($value !== null && $value !== '') {
                $formattedValue = $cell->getFormattedValue();
                $dataType = $cell->getDataType();
                
                // Check for merged cells
                $mergedRanges = $sheet->getMergeCells();
                $isMerged = false;
                $mergeRange = '';
                foreach ($mergedRanges as $range) {
                    if ($sheet->getCell($col . $row)->isInRange($range)) {
                        $isMerged = true;
                        $mergeRange = $range;
                        break;
                    }
                }
                
                // Get style info
                $style = $cell->getStyle();
                $font = $style->getFont();
                $fill = $style->getFill();
                $alignment = $style->getAlignment();
                
                echo "  $col$row: \"$value\"";
                if ($value !== $formattedValue) {
                    echo " (formatted: \"$formattedValue\")";
                }
                echo "\n";
                
                if ($isMerged) {
                    echo "    - MERGED: $mergeRange\n";
                }
                
                echo "    - Font: " . $font->getName() . ", Size: " . $font->getSize();
                if ($font->getBold()) echo " [BOLD]";
                if ($font->getItalic()) echo " [ITALIC]";
                echo "\n";
                
                $bgColor = $fill->getStartColor()->getRGB();
                if ($bgColor && $bgColor !== 'FFFFFF') {
                    echo "    - Background: #$bgColor\n";
                }
                
                $hAlign = $alignment->getHorizontal();
                $vAlign = $alignment->getVertical();
                if ($hAlign || $vAlign) {
                    echo "    - Alignment: H=$hAlign, V=$vAlign\n";
                }
            }
        }
    }
    
    // === SECTION 2: COLUMN HEADERS (Row 6 typically) ===
    echo "\n\n=== COLUMN HEADERS ANALYSIS ===\n";
    echo "Searching for header row...\n\n";
    
    // Check rows 5-8 for headers
    for ($row = 5; $row <= 8; $row++) {
        echo "Row $row:\n";
        $hasHeaders = false;
        for ($col = 'A'; $col <= 'Z'; $col++) {
            $value = $sheet->getCell($col . $row)->getValue();
            if ($value !== null && $value !== '') {
                echo "  $col: \"$value\"\n";
                $hasHeaders = true;
            }
        }
        if (!$hasHeaders) {
            echo "  (empty)\n";
        }
        echo "\n";
    }
    
    // === SECTION 3: SAMPLE DATA ROWS (Rows 6-15 or next 10 data rows) ===
    echo "\n=== SAMPLE DATA ROWS (First 10 data rows) ===\n";
    echo "Starting from row 7 (assuming row 6 is headers):\n\n";
    
    for ($row = 7; $row <= 16; $row++) {
        echo "Row $row:\n";
        $rowData = [];
        for ($col = 'A'; $col <= 'Z'; $col++) {
            $cell = $sheet->getCell($col . $row);
            $value = $cell->getValue();
            $formattedValue = $cell->getFormattedValue();
            
            if ($value !== null && $value !== '') {
                // Check if it's a formula
                $formula = '';
                if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
                    $formula = $cell->getValue();
                    echo "  $col: FORMULA: $formula = \"$formattedValue\"\n";
                } else {
                    echo "  $col: \"$value\"";
                    if ($value !== $formattedValue) {
                        echo " (formatted: \"$formattedValue\")";
                    }
                    echo "\n";
                }
                
                // Check background color for this data cell
                $bgColor = $cell->getStyle()->getFill()->getStartColor()->getRGB();
                if ($bgColor && $bgColor !== 'FFFFFF') {
                    echo "      Color: #$bgColor\n";
                }
            }
        }
        echo "\n";
    }
    
    // === SECTION 4: ALL FORMULAS IN THE SHEET ===
    echo "\n=== ALL FORMULAS IN SPREADSHEET ===\n";
    $formulaCount = 0;
    for ($row = 1; $row <= min($sheet->getHighestRow(), 50); $row++) {
        for ($col = 'A'; $col <= $sheet->getHighestColumn(); $col++) {
            $cell = $sheet->getCell($col . $row);
            if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
                $formula = $cell->getValue();
                $calculatedValue = $cell->getCalculatedValue();
                echo "$col$row: $formula = $calculatedValue\n";
                $formulaCount++;
            }
        }
    }
    echo "\nTotal formulas found (first 50 rows): $formulaCount\n";
    
    // === SECTION 5: COLOR CODING SCHEME ===
    echo "\n\n=== COLOR CODING SCHEME ===\n";
    $colorMap = [];
    
    for ($row = 1; $row <= min($sheet->getHighestRow(), 30); $row++) {
        for ($col = 'A'; $col <= $sheet->getHighestColumn(); $col++) {
            $cell = $sheet->getCell($col . $row);
            $value = $cell->getValue();
            if ($value === null || $value === '') continue;
            
            $bgColor = $cell->getStyle()->getFill()->getStartColor()->getRGB();
            $fontColor = $cell->getStyle()->getFont()->getColor()->getRGB();
            
            if ($bgColor && $bgColor !== 'FFFFFF') {
                $key = "BG:#$bgColor";
                if (!isset($colorMap[$key])) {
                    $colorMap[$key] = ['cells' => [], 'example' => $value];
                }
                $colorMap[$key]['cells'][] = $col . $row;
            }
            
            if ($fontColor && $fontColor !== '000000') {
                $key = "FONT:#$fontColor";
                if (!isset($colorMap[$key])) {
                    $colorMap[$key] = ['cells' => [], 'example' => $value];
                }
                $colorMap[$key]['cells'][] = $col . $row;
            }
        }
    }
    
    foreach ($colorMap as $color => $info) {
        echo "$color:\n";
        echo "  Example value: \"{$info['example']}\"\n";
        echo "  Used in: " . implode(', ', array_slice($info['cells'], 0, 10));
        if (count($info['cells']) > 10) {
            echo " ... (" . count($info['cells']) . " total)";
        }
        echo "\n\n";
    }
    
    // === SECTION 6: MERGED CELLS ===
    echo "\n=== MERGED CELLS ===\n";
    $mergedCells = $sheet->getMergeCells();
    foreach ($mergedCells as $range) {
        $topLeft = explode(':', $range)[0];
        $value = $sheet->getCell($topLeft)->getValue();
        echo "$range: \"$value\"\n";
    }
    
    // === SECTION 7: RATE CALCULATION AREA ===
    echo "\n\n=== RATE CALCULATION AREA (Looking for rate-related cells) ===\n";
    echo "Searching for keywords: rate, daily, monthly, deduction, basic, salary...\n\n";
    
    for ($row = 1; $row <= min($sheet->getHighestRow(), 50); $row++) {
        for ($col = 'A'; $col <= $sheet->getHighestColumn(); $col++) {
            $cell = $sheet->getCell($col . $row);
            $value = strtolower((string)$cell->getValue());
            
            if ($value && (
                strpos($value, 'rate') !== false ||
                strpos($value, 'daily') !== false ||
                strpos($value, 'monthly') !== false ||
                strpos($value, 'deduction') !== false ||
                strpos($value, 'basic') !== false ||
                strpos($value, 'salary') !== false ||
                strpos($value, 'per day') !== false ||
                strpos($value, 'per min') !== false
            )) {
                $actualValue = $cell->getValue();
                $formattedValue = $cell->getFormattedValue();
                echo "$col$row: \"$actualValue\"";
                if ($actualValue !== $formattedValue) {
                    echo " (formatted: \"$formattedValue\")";
                }
                
                // Check adjacent cells for values
                $nextCol = chr(ord($col) + 1);
                $nextValue = $sheet->getCell($nextCol . $row)->getValue();
                if ($nextValue) {
                    echo " -> " . $nextCol . $row . ": \"$nextValue\"";
                }
                echo "\n";
            }
        }
    }
    
    // === SECTION 8: COMPLETE ROW-BY-ROW STRUCTURE ===
    echo "\n\n=== COMPLETE STRUCTURE: ROWS 1-20 ===\n";
    echo "Format: [Column Letter][Row Number] = Value\n\n";
    
    for ($row = 1; $row <= 20; $row++) {
        $rowHasData = false;
        $rowOutput = "Row $row: ";
        $cellData = [];
        
        for ($col = 'A'; $col <= 'Z'; $col++) {
            $cell = $sheet->getCell($col . $row);
            $value = $cell->getValue();
            
            if ($value !== null && $value !== '') {
                $rowHasData = true;
                $formattedValue = $cell->getFormattedValue();
                $bgColor = $cell->getStyle()->getFill()->getStartColor()->getRGB();
                
                $cellInfo = "$col=\"$formattedValue\"";
                if ($bgColor && $bgColor !== 'FFFFFF') {
                    $cellInfo .= " [#$bgColor]";
                }
                if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
                    $cellInfo .= " [FORMULA]";
                }
                
                $cellData[] = $cellInfo;
            }
        }
        
        if ($rowHasData) {
            echo $rowOutput . implode(" | ", $cellData) . "\n";
        } else {
            echo $rowOutput . "(empty)\n";
        }
    }
    
    // === SECTION 9: EMPLOYEE INFO SECTION ===
    echo "\n\n=== EMPLOYEE INFORMATION SECTION ===\n";
    echo "Looking for Name, Position, Department, Rate fields...\n\n";
    
    for ($row = 1; $row <= 15; $row++) {
        for ($col = 'A'; $col <= 'F'; $col++) {
            $cell = $sheet->getCell($col . $row);
            $value = strtolower((string)$cell->getValue());
            
            if ($value && (
                strpos($value, 'name') !== false ||
                strpos($value, 'position') !== false ||
                strpos($value, 'department') !== false ||
                strpos($value, 'employee') !== false ||
                strpos($value, 'period') !== false ||
                strpos($value, 'date range') !== false
            )) {
                $actualValue = $cell->getValue();
                echo "$col$row: \"$actualValue\"";
                
                // Check next 3 cells for the actual data
                for ($offset = 1; $offset <= 3; $offset++) {
                    $nextCol = chr(ord($col) + $offset);
                    if ($nextCol <= 'Z') {
                        $nextCell = $sheet->getCell($nextCol . $row);
                        $nextValue = $nextCell->getValue();
                        if ($nextValue !== null && $nextValue !== '') {
                            echo " -> " . $nextCol . $row . ": \"$nextValue\"";
                        }
                    }
                }
                echo "\n";
            }
        }
    }
    
    echo "\n=== ANALYSIS COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
