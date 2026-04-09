<?php
/**
 * Import DTR from Excel File
 * Processes uploaded Excel file and extracts ALL data including employee info
 * Stores DTR records in database
 */

// CRITICAL: Suppress ALL errors/warnings/deprecations from being output
// This must be FIRST to prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

// Ensure no output before JSON
ob_start();

// Error handler to catch all errors as JSON (including E_DEPRECATED)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the error instead of outputting it
    error_log("DTR Import Error [$errno]: $errstr in $errfile:$errline");
    
    // Only exit for fatal-type errors, not warnings/deprecations
    if ($errno === E_ERROR || $errno === E_USER_ERROR) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "PHP Error: $errstr",
            'error_details' => "File: " . basename($errfile) . " Line: $errline"
        ]);
        exit();
    }
    
    // Return true to prevent PHP's internal error handler
    return true;
}, E_ALL);

// Exception handler
set_exception_handler(function($e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Exception: " . $e->getMessage(),
        'error_details' => "File: " . basename($e->getFile()) . " Line: " . $e->getLine()
    ]);
    exit();
});

require_once '../config/bootstrap.php';
// Re-suppress errors for clean JSON output (overrides bootstrap settings)
error_reporting(0);
ini_set('display_errors', '0');

// Clear any buffered output
ob_end_clean();
ob_start();

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';
require_once '../config/account_logs_helper.php';
require_once '../config/csrf.php';
require_once '../config/notifications_helper.php';

// CSRF check
requireCSRFToken();

// Check if ZipArchive is available (required for Excel files)
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'PHP ZipArchive extension is not enabled',
        'error_details' => 'To fix: In Laragon, right-click Laragon icon → PHP → Quick settings → Enable "zip" extension, then restart Apache.'
    ]);
    exit();
}

// Check if PhpSpreadsheet is installed
$phpSpreadsheetPath = '../vendor/autoload.php';
$usePhpSpreadsheet = false;

if (file_exists($phpSpreadsheetPath)) {
    require_once $phpSpreadsheetPath;
    if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $usePhpSpreadsheet = true;
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

// Optional employee_id and payroll_period_id - if not provided, extract from Excel
$employeeIdParam = intval($_POST['employee_id'] ?? 0);
$payrollPeriodIdParam = intval($_POST['payroll_period_id'] ?? 0);

$file = $_FILES['excel_file'];
$fileName = $file['name'];
$fileTmpPath = $file['tmp_name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Validate file extension
$allowedExtensions = ['xlsx', 'xls', 'xlsm', 'csv'];
if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only Excel and CSV files are allowed.']);
    exit();
}

// M3: Validate MIME type to prevent disguised uploads
$allowedMimeTypes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
    'application/vnd.ms-excel',                                          // xls
    'application/vnd.ms-excel.sheet.macroEnabled.12',                    // xlsm
    'text/csv',                                                          // csv
    'text/plain',                                                        // csv fallback
    'application/octet-stream',                                          // generic binary (some systems)
    'application/zip',                                                   // xlsx/xlsm are ZIP-based
];
$detectedMime = mime_content_type($fileTmpPath);
if ($detectedMime && !in_array($detectedMime, $allowedMimeTypes)) {
    echo json_encode(['success' => false, 'message' => 'File MIME type mismatch. The uploaded file does not appear to be a valid spreadsheet.']);
    exit();
}

try {
    $extractedData = [];
    
    // Get payroll period dates if provided, for auto-date generation
    $periodStart = null;
    $periodEnd = null;
    if ($payrollPeriodIdParam > 0) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT start_date, end_date FROM payroll_periods WHERE id = ?");
            $stmt->execute([$payrollPeriodIdParam]);
            $period = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($period) {
                $periodStart = $period['start_date'];
                $periodEnd = $period['end_date'];
            }
        } catch (Exception $e) {
            // Continue without period dates
        }
    }
    
    if ($fileExtension === 'csv') {
        $extractedData = parseCSVFileComplete($fileTmpPath, $periodStart, $periodEnd);
    } elseif ($usePhpSpreadsheet) {
        $extractedData = parseExcelComplete($fileTmpPath, $fileExtension, $periodStart, $periodEnd);
    } else {
        if ($fileExtension === 'xlsx' || $fileExtension === 'xlsm') {
            $extractedData = parseXlsxComplete($fileTmpPath, $periodStart, $periodEnd);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'PhpSpreadsheet library not installed. Please run: composer require phpoffice/phpspreadsheet'
            ]);
            exit();
        }
    }
    
    if (empty($extractedData['dtr_records']) && empty($extractedData['multi_sheet'])) {
        // Provide diagnostic info to help debug
        $diagInfo = [];
        if (!empty($extractedData['employee_info']['full_name'])) {
            $diagInfo[] = 'Employee: ' . $extractedData['employee_info']['full_name'];
        } else {
            $diagInfo[] = 'No employee name in row 2';
        }
        $diagInfo[] = 'Format: ' . ($extractedData['debug_format'] ?? 'unknown');
        $diagInfo[] = 'Data starts: row ' . ($extractedData['debug_data_start_row'] ?? '?');
        $diagInfo[] = 'Total rows in file: ' . ($extractedData['debug_rows_scanned'] ?? '0');
        $diagInfo[] = 'Data rows parsed: ' . ($extractedData['debug_rows_processed'] ?? '0');
        $diagInfo[] = 'Data rows skipped: ' . ($extractedData['debug_data_rows_skipped'] ?? '0');
        $diagInfo[] = 'Key type: ' . (($extractedData['debug_has_letter_keys'] ?? false) ? 'letter (A,B,C)' : 'numeric (0,1,2)');
        
        // Show data row skip reasons (not header rows)
        if (!empty($extractedData['debug_skipped_details'])) {
            $diagInfo[] = 'SKIP REASONS: ' . implode(' || ', array_slice($extractedData['debug_skipped_details'], 0, 10));
        }
        
        // Show actual sample row data
        if (!empty($extractedData['debug_sample_rows'])) {
            foreach ($extractedData['debug_sample_rows'] as $i => $sr) {
                $rowNum = $sr['row'] ?? '?';
                unset($sr['row']);
                $diagInfo[] = "ROW {$rowNum} data: " . implode(', ', array_map(function($k, $v) { return "$k=$v"; }, array_keys($sr), $sr));
            }
        }
        
        $errorMsg = 'No DTR data found in the file. ';
        if (($extractedData['debug_rows_scanned'] ?? 0) < 6) {
            $errorMsg .= 'File appears to have too few rows. ';
        }
        $errorMsg .= 'Make sure dates are entered in column A (rows 6+) in the TB5 template format.';
        
        echo json_encode(array_merge([
            'success' => false, 
            'message' => $errorMsg
        ], APP_DEBUG ? [
            'debug_info' => implode(' | ', $diagInfo),
            'column_map' => ($extractedData['debug_column_map'] ?? [])
        ] : []));
        exit();
    }
    
    // ============================================================
    // MULTI-SHEET MODE: If file has multiple DTR sheets with data
    // ============================================================
    if (!empty($extractedData['multi_sheet'])) {
        $pdo = getDBConnection();
        $sheetsData = [];
        $totalRecords = 0;
        $importedBy = $_SESSION['username'] ?? '';
        
        foreach ($extractedData['sheets'] as $sheetResult) {
            $employeeInfo = $sheetResult['employee_info'];
            $dtrRecords = $sheetResult['dtr_records'];
            $totalRecords += count($dtrRecords);
            
            // Try to find existing employee
            $employeeDetails = null;
            $employeeId = null;
            $empName = trim($employeeInfo['full_name'] ?? '');
            $empCode = trim($employeeInfo['employee_code'] ?? '');
            
            if ($empName) {
                $stmt = $pdo->prepare("SELECT * FROM employees WHERE LOWER(full_name) = LOWER(?) LIMIT 1");
                $stmt->execute([$empName]);
                $employeeDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$employeeDetails && $empCode) {
                $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_code = ? LIMIT 1");
                $stmt->execute([$empCode]);
                $employeeDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if ($employeeDetails) {
                $employeeId = $employeeDetails['id'];
            }
            
            $dates = array_column($dtrRecords, 'dtr_date');
            $sheetStartDate = !empty($dates) ? min($dates) : date('Y-m-d');
            $sheetEndDate = !empty($dates) ? max($dates) : date('Y-m-d');
            
            $sheetsData[] = [
                'sheet_name' => $sheetResult['debug_sheet'] ?? 'Unknown',
                'records_count' => count($dtrRecords),
                'employee_info' => [
                    'id' => $employeeId,
                    'employee_code' => $employeeDetails['employee_code'] ?? ($employeeInfo['employee_code'] ?? ''),
                    'full_name' => $employeeDetails['full_name'] ?? ($employeeInfo['full_name'] ?? ''),
                    'position' => $employeeDetails['position'] ?? ($employeeInfo['position'] ?? ''),
                    'department' => $employeeDetails['department'] ?? ($employeeInfo['department'] ?? ''),
                    'basic_monthly_salary' => ($employeeInfo['basic_monthly_salary'] > 0 ? $employeeInfo['basic_monthly_salary'] : ($employeeDetails['basic_monthly_salary'] ?? 0)),
                    'per_day_rate' => $employeeInfo['per_day_rate'] ?? 0,
                    'is_trainer' => $employeeInfo['is_trainer'] ?? '',
                    'is_fixrate' => $employeeInfo['is_fixrate'] ?? '',
                    'is_existing' => $employeeDetails !== null
                ],
                'period_info' => [
                    'id' => null,
                    'period_name' => '',
                    'start_date' => $sheetStartDate,
                    'end_date' => $sheetEndDate,
                    'pay_date' => null
                ],
                'schedule_thresholds' => $sheetResult['schedule_thresholds'] ?? [],
                'trainee_summary' => $sheetResult['trainee_summary'] ?? ['total_count' => 0, 'total_cost' => 0, 'pay_per_unit' => 0],
                'dtr_data' => $dtrRecords,
            ];
        }
        
        // Notify for the multi-sheet import
        notifyDTRImported(count($sheetsData) . ' employees (multi-sheet)', $totalRecords, $importedBy);
        
        echo json_encode([
            'success' => true,
            'message' => 'DTR data extracted from ' . count($sheetsData) . ' sheets (' . $totalRecords . ' total records). Click "Save to Payroll List" to save.',
            'preview_mode' => true,
            'multi_sheet' => true,
            'sheet_count' => count($sheetsData),
            'records_count' => $totalRecords,
            'sheets' => $sheetsData,
        ]);
        exit();
    }

    // ============================================================
    // PREVIEW MODE: Extract data only, DO NOT save to database
    // User must click "Save" button to save to database
    // ============================================================
    
    $pdo = getDBConnection();
    
    // Get employee info from Excel data
    $employeeInfo = $extractedData['employee_info'];
    $dtrRecords = $extractedData['dtr_records'];
    
    // Try to find existing employee by name or code (for matching/update purposes)
    $employeeDetails = null;
    $employeeId = null;
    
    if ($employeeIdParam > 0) {
        // Use provided employee ID
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employeeIdParam]);
        $employeeDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        $employeeId = $employeeIdParam;
    } else {
        // Try to find existing employee by name from Excel
        $empName = trim($employeeInfo['full_name'] ?? '');
        $empCode = trim($employeeInfo['employee_code'] ?? '');
        
        if ($empName) {
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE LOWER(full_name) = LOWER(?) LIMIT 1");
            $stmt->execute([$empName]);
            $employeeDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$employeeDetails && $empCode) {
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_code = ? LIMIT 1");
            $stmt->execute([$empCode]);
            $employeeDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($employeeDetails) {
            $employeeId = $employeeDetails['id'];
        }
    }
    
    // Determine period info from dates (preview only, don't create)
    $dates = array_column($dtrRecords, 'dtr_date');
    $startDate = !empty($dates) ? min($dates) : date('Y-m-d');
    $endDate = !empty($dates) ? max($dates) : date('Y-m-d');
    
    // Notify admins and staff that a DTR Excel file was imported
    $importedEmployeeName = $employeeDetails['full_name'] ?? ($employeeInfo['full_name'] ?? 'Unknown Employee');
    $importedBy = $_SESSION['username'] ?? '';
    notifyDTRImported($importedEmployeeName, count($dtrRecords), $importedBy);

    // Return extracted data for preview (NO database save)
    echo json_encode([
        'success' => true,
        'message' => 'DTR data extracted successfully. Click "Save to Payroll List" to save.',
        'preview_mode' => true,  // Indicates data is NOT saved yet
        'records_count' => count($dtrRecords),
        'employee_info' => [
            'id' => $employeeId,  // null if new employee
            'employee_code' => $employeeDetails['employee_code'] ?? ($employeeInfo['employee_code'] ?? ''),
            'full_name' => $employeeDetails['full_name'] ?? ($employeeInfo['full_name'] ?? ''),
            'position' => $employeeDetails['position'] ?? ($employeeInfo['position'] ?? ''),
            'department' => $employeeDetails['department'] ?? ($employeeInfo['department'] ?? ''),
            'basic_monthly_salary' => ($employeeInfo['basic_monthly_salary'] > 0 ? $employeeInfo['basic_monthly_salary'] : ($employeeDetails['basic_monthly_salary'] ?? 0)),
            'per_day_rate' => $employeeInfo['per_day_rate'] ?? 0,
            'is_trainer' => $employeeInfo['is_trainer'] ?? '',
            'is_fixrate' => $employeeInfo['is_fixrate'] ?? '',
            'is_existing' => $employeeDetails !== null  // Flag if employee exists
        ],
        'period_info' => [
            'id' => null,  // Will be determined on save
            'period_name' => '',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pay_date' => null
        ],
        'schedule_thresholds' => $extractedData['schedule_thresholds'] ?? [],
        'trainee_summary' => $extractedData['trainee_summary'] ?? ['total_count' => 0, 'total_cost' => 0, 'pay_per_unit' => 0],
        'dtr_data' => $dtrRecords,
        'debug_salary' => [
            'excel_basic' => $employeeInfo['basic_monthly_salary'],
            'excel_perday' => $employeeInfo['per_day_rate'] ?? 0,
            'db_basic' => $employeeDetails['basic_monthly_salary'] ?? 'N/A',
            'final_basic' => ($employeeInfo['basic_monthly_salary'] > 0 ? $employeeInfo['basic_monthly_salary'] : ($employeeDetails['basic_monthly_salary'] ?? 0))
        ]
    ] + (APP_DEBUG ? ['debug_time_values' => $extractedData['debug_time_values'] ?? []] : [])
    );
    
} catch (Exception $e) {
    // Log detailed error for debugging
    error_log("DTR Import Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode(array_merge([
        'success' => false,
        'message' => $e->getMessage()
    ], APP_DEBUG ? [
        'error_detail' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ] : []));
}

/**
 * Find or create employee based on extracted info
 */
function findOrCreateEmployee($pdo, $employeeInfo, $createdBy) {
    $employeeName = $employeeInfo['full_name'] ?? null;
    $employeeCode = $employeeInfo['employee_code'] ?? null;
    
    if (!$employeeName && !$employeeCode) {
        throw new Exception('No employee name or code found in the Excel file');
    }
    
    // Try to find by employee code first
    if ($employeeCode) {
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE employee_code = ?");
        $stmt->execute([$employeeCode]);
        $result = $stmt->fetch();
        if ($result) {
            return $result['id'];
        }
    }
    
    // Try to find by name
    if ($employeeName) {
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE full_name = ?");
        $stmt->execute([$employeeName]);
        $result = $stmt->fetch();
        if ($result) {
            return $result['id'];
        }
    }
    
    // Create new employee
    $newCode = $employeeCode ?: generateEmployeeCode($pdo);
    
    $stmt = $pdo->prepare("
        INSERT INTO employees (
            employee_code, full_name, position, department, 
            basic_monthly_salary, status, created_at
        ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
    ");
    $stmt->execute([
        $newCode,
        $employeeName,
        $employeeInfo['position'] ?? null,
        $employeeInfo['department'] ?? null,
        $employeeInfo['basic_monthly_salary'] ?? 0
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Generate a unique employee code
 */
function generateEmployeeCode($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM employees WHERE employee_code LIKE ?");
    $stmt->execute(["EMP-{$year}-%"]);
    $result = $stmt->fetch();
    $nextNum = ($result['count'] ?? 0) + 1;
    return "EMP-{$year}-" . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/**
 * Find or create payroll period based on date range
 */
function findOrCreatePayrollPeriod($pdo, $startDate, $endDate, $createdBy) {
    // Try to find existing period that contains these dates
    $stmt = $pdo->prepare("
        SELECT id FROM payroll_periods 
        WHERE start_date <= ? AND end_date >= ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$startDate, $endDate]);
    $result = $stmt->fetch();
    
    if ($result) {
        return $result['id'];
    }
    
    // Create new period
    $startDt = new DateTime($startDate);
    $endDt = new DateTime($endDate);
    $periodName = $startDt->format('M d') . ' - ' . $endDt->format('M d, Y');
    
    // Calculate pay date (usually 5 days after end date)
    $payDate = clone $endDt;
    $payDate->modify('+5 days');
    
    $stmt = $pdo->prepare("
        INSERT INTO payroll_periods (
            period_name, start_date, end_date, pay_date, 
            status, created_by, created_at
        ) VALUES (?, ?, ?, ?, 'draft', ?, NOW())
    ");
    $stmt->execute([
        $periodName,
        $startDate,
        $endDate,
        $payDate->format('Y-m-d'),
        $createdBy
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Parse CSV file - extracts ALL data including employee info
 */
function parseCSVFileComplete($filePath, $periodStart = null, $periodEnd = null) {
    $rows = [];
    $rowNum = 1;
    
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowData = [];
            foreach ($data as $colIndex => $value) {
                $colLetter = numberToColumnLetter($colIndex);
                $rowData[$colLetter] = $value;
            }
            $rows[$rowNum] = $rowData;
            $rowNum++;
        }
        fclose($handle);
    }
    
    return extractAllDataFromRows($rows, true, $periodStart, $periodEnd);
}

/**
 * Parse Excel file using PhpSpreadsheet - extracts ALL data from ALL sheets
 * Supports multi-sheet DTR files (e.g., 10 DTR sheets in one Excel file).
 * Skips worksheets that have no meaningful data (no employee name or time entries).
 */
function parseExcelComplete($filePath, $extension, $periodStart = null, $periodEnd = null) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
    
    $allResults = [];
    
    foreach ($spreadsheet->getSheetNames() as $sheetName) {
        // Skip known non-DTR sheets
        $sheetNameUpper = strtoupper(trim($sheetName));
        if (in_array($sheetNameUpper, ['INSTRUCTIONS', 'README', 'HELP', 'NOTES', 'SUMMARY'])) {
            continue;
        }
        
        $worksheet = $spreadsheet->getSheetByName($sheetName);
        $result = parseWorksheet($worksheet, $periodStart, $periodEnd);
        
        // Skip worksheets that have no DTR data
        if (empty($result['dtr_records'])) {
            continue;
        }
        
        // Skip worksheets where employee name is still the placeholder or empty
        $empName = trim($result['employee_info']['full_name'] ?? '');
        if (empty($empName) || $empName === '[ENTER NAME HERE]') {
            continue;
        }
        
        $result['debug_sheet'] = $sheetName;
        $allResults[] = $result;
    }
    
    // If we found multiple sheets with data, return multi-sheet result
    if (count($allResults) > 1) {
        return [
            'multi_sheet' => true,
            'sheets' => $allResults,
            'sheet_count' => count($allResults),
        ];
    }
    
    // If exactly one sheet found, return it directly (backward compatible)
    if (count($allResults) === 1) {
        return $allResults[0];
    }
    
    // No data found in any sheet — return empty result for the active sheet (for debug info)
    $activeSheet = $spreadsheet->getActiveSheet();
    return parseWorksheet($activeSheet, $periodStart, $periodEnd);
}

/**
 * Parse a single worksheet for DTR data
 */
function parseWorksheet($worksheet, $periodStart = null, $periodEnd = null) {
    // Try with formula evaluation first; fall back to raw values if formulas error out
    // toArray() signature: (nullValue, calculateFormulas, formatData, returnCellRef)
    try {
        $rows = $worksheet->toArray(null, true, true, true);
    } catch (\Exception $e) {
        error_log('DTR Import: formula eval failed on toArray, retrying without calc: ' . $e->getMessage());
        // calculateFormulas=FALSE so broken formulas are never evaluated
        try {
            $rows = $worksheet->toArray(null, false, true, true);
        } catch (\Exception $e2) {
            $rows = [];
        }
    }
    
    // Raw (unformatted, no formula calc) data for reliable numeric time serial values
    // calculateFormulas=FALSE, formatData=FALSE returns underlying Excel numeric types
    try {
        $rowsRaw = $worksheet->toArray(null, false, false, true);
    } catch (\Exception $e) {
        $rowsRaw = $rows;
    }
    
    // Pass both formatted & raw arrays + worksheet object for maximum reliability
    return extractAllDataFromRows($rows, true, $periodStart, $periodEnd, $worksheet, $rowsRaw);
}

/**
 * Simple XLSX parser - extracts ALL data
 */
function parseXlsxComplete($filePath, $periodStart = null, $periodEnd = null) {
    $zip = new ZipArchive;
    
    if ($zip->open($filePath) !== true) {
        throw new Exception('Cannot open xlsx file');
    }
    
    // Read shared strings
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml) {
        $xml = simplexml_load_string($sharedStringsXml);
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string)$si->t;
            } elseif (isset($si->r)) {
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
                $sharedStrings[] = $text;
            }
        }
    }
    
    // Read worksheet
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetXml) {
        $zip->close();
        throw new Exception('Cannot find worksheet in xlsx file');
    }
    
    $xml = simplexml_load_string($sheetXml);
    $rows = [];
    
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $rowNum = (int)$row['r'];
        
        foreach ($row->c as $cell) {
            $value = '';
            
            if (isset($cell['t']) && (string)$cell['t'] === 's') {
                $index = (int)$cell->v;
                $value = $sharedStrings[$index] ?? '';
            } else {
                $value = isset($cell->v) ? (string)$cell->v : '';
            }
            
            $cellRef = (string)$cell['r'];
            $colLetter = preg_replace('/[0-9]/', '', $cellRef);
            
            $rowData[$colLetter] = $value;
        }
        $rows[$rowNum] = $rowData;
    }
    
    $zip->close();
    
    return extractAllDataFromRows($rows, true, $periodStart, $periodEnd);
}

/**
 * Convert column number to letter
 */
function numberToColumnLetter($num) {
    $letter = '';
    while ($num >= 0) {
        $letter = chr(65 + ($num % 26)) . $letter;
        $num = floor($num / 26) - 1;
    }
    return $letter;
}

/**
 * Extract employee info and DTR records from rows
 * Handles the TB5 DTR Calculator format:
 * - Row 2: "EMPLOYEE NAME: [name]" + "BASIC" with salary
 * - Row 4-5: Headers (main + sub headers)
 * - Row 6+: Data rows
 */
function extractAllDataFromRows($rows, $hasLetterKeys = true, $periodStart = null, $periodEnd = null, $worksheet = null, $rowsRaw = null) {
    $employeeInfo = [
        'full_name' => null,
        'employee_code' => null,
        'position' => null,
        'department' => null,
        'basic_monthly_salary' => 0,
        'per_day_rate' => 0,
        'ot_rate' => 0,
        'is_trainer' => '',
        'is_fixrate' => ''
    ];
    $dtrRecords = [];
    
    // Schedule thresholds from Excel (I2 = late threshold, J2 = end time)
    $scheduleThresholds = [
        'late_threshold' => null,
        'end_time' => null
    ];
    
    // ========================================
    // STEP 1: Extract employee info from rows 1-3
    // ========================================
    
    // PRIORITY: Read salary & per-day directly from worksheet cells (most reliable)
    // This avoids any toArray formatting/calculation issues
    if ($worksheet !== null) {
        // OLD format: G2='BASIC:', H2=salary, I2='PER/DAY:', J2=perDay
        // NEW format: K2='BASIC', N2=salary, O2=perDay
        $directSalaryCells = ['H', 'N'];   // Possible salary cells
        $directPerDayCells = ['J', 'O'];   // Possible per-day cells
        
        foreach ($directSalaryCells as $sc) {
            try {
                $cell = $worksheet->getCell($sc . '2');
                $rawVal = $cell->getValue();
                error_log("DTR Import: Direct read {$sc}2 getValue=" . var_export($rawVal, true));
                if ($rawVal !== null && $rawVal !== '' && is_numeric($rawVal)) {
                    $salNum = floatval($rawVal);
                    if ($salNum >= 1000 && $salNum <= 500000) {
                        $employeeInfo['basic_monthly_salary'] = $salNum;
                        error_log("DTR Import: Set basic_monthly_salary={$salNum} from cell {$sc}2");
                        break;
                    }
                }
                // Also try getFormattedValue
                $fmtVal = $cell->getFormattedValue();
                if ($fmtVal !== null && $fmtVal !== '') {
                    $cleaned = floatval(preg_replace('/[^0-9.]/', '', $fmtVal));
                    if ($cleaned >= 1000 && $cleaned <= 500000) {
                        $employeeInfo['basic_monthly_salary'] = $cleaned;
                        error_log("DTR Import: Set basic_monthly_salary={$cleaned} from formatted {$sc}2 ('{$fmtVal}')");
                        break;
                    }
                }
            } catch (\Exception $e) {
                error_log("DTR Import: Error reading {$sc}2: " . $e->getMessage());
            }
        }
        
        foreach ($directPerDayCells as $pc) {
            try {
                $cell = $worksheet->getCell($pc . '2');
                $rawVal = $cell->getValue();
                error_log("DTR Import: Direct read {$pc}2 getValue=" . var_export($rawVal, true));
                if ($rawVal !== null && $rawVal !== '' && is_numeric($rawVal)) {
                    $rateNum = floatval($rawVal);
                    if ($rateNum > 0 && $rateNum <= 100000) {
                        $employeeInfo['per_day_rate'] = $rateNum;
                        error_log("DTR Import: Set per_day_rate={$rateNum} from cell {$pc}2");
                        break;
                    }
                }
                $fmtVal = $cell->getFormattedValue();
                if ($fmtVal !== null && $fmtVal !== '') {
                    $cleaned = floatval(preg_replace('/[^0-9.]/', '', $fmtVal));
                    if ($cleaned > 0 && $cleaned <= 100000) {
                        $employeeInfo['per_day_rate'] = $cleaned;
                        error_log("DTR Import: Set per_day_rate={$cleaned} from formatted {$pc}2");
                        break;
                    }
                }
            } catch (\Exception $e) {
                error_log("DTR Import: Error reading {$pc}2: " . $e->getMessage());
            }
        }
        
        // Read TRAINER (M2) and FIXRATE (N2) fields
        $trainerFixrateCells = ['M' => 'is_trainer', 'N' => 'is_fixrate'];
        foreach ($trainerFixrateCells as $tfCol => $tfField) {
            try {
                $cell = $worksheet->getCell($tfCol . '2');
                $rawVal = $cell->getValue();
                if ($rawVal !== null && $rawVal !== '') {
                    $employeeInfo[$tfField] = trim((string)$rawVal);
                } else {
                    $fmtVal = $cell->getFormattedValue();
                    if ($fmtVal !== null && $fmtVal !== '') {
                        $employeeInfo[$tfField] = trim((string)$fmtVal);
                    }
                }
            } catch (\Exception $e) {
                error_log("DTR Import: Error reading {$tfCol}2: " . $e->getMessage());
            }
        }
    }
    
    // Check row 2 for "EMPLOYEE NAME: [name]"
    if (isset($rows[2])) {
        $row2 = $rows[2];
        
        // Look for employee name in column A or B
        foreach ($row2 as $col => $val) {
            $valStr = trim($val ?? '');
            
            // Check for "EMPLOYEE NAME:" label
            if (stripos($valStr, 'EMPLOYEE NAME:') !== false) {
                // Extract name from same cell or next cell
                if (strpos($valStr, ':') !== false) {
                    $parts = explode(':', $valStr, 2);
                    if (isset($parts[1])) {
                        $employeeInfo['full_name'] = trim($parts[1]);
                    }
                }
            }
            
            // If column A is "EMPLOYEE NAME:" then column B should have the name
            if (in_array($col, ['A', '0', 0]) && stripos($valStr, 'EMPLOYEE NAME') !== false) {
                $nextCol = $hasLetterKeys ? 'B' : 1;
                if (isset($row2[$nextCol])) {
                    $employeeInfo['full_name'] = trim($row2[$nextCol]);
                }
            }
            
            // Look for BASIC salary (column N or nearby)
            $upperVal = strtoupper(rtrim($valStr, ':'));
            if ($upperVal === 'BASIC' || $upperVal === 'VARIABLE') {
                // Check next few columns for numeric value
                $colIndex = is_numeric($col) ? $col : ord($col) - ord('A');
                for ($i = 1; $i <= 3; $i++) {
                    $checkCol = $hasLetterKeys ? chr(ord($col) + $i) : $colIndex + $i;
                    if (isset($row2[$checkCol])) {
                        $salaryVal = trim($row2[$checkCol]);
                        // Remove P, comma, and extract number
                        $salaryNum = floatval(preg_replace('/[^0-9.]/', '', $salaryVal));
                        if ($salaryNum >= 1000 && $salaryNum <= 500000) {
                            $employeeInfo['basic_monthly_salary'] = $salaryNum;
                            break;
                        }
                    }
                }
            }
            
            // Look for PER/DAY rate (OLD format: I2='PER/DAY:', J2=value)
            if (in_array($upperVal, ['PER/DAY', 'PER DAY', 'PERDAY', 'PER/DAY RATE'])) {
                $colIndex = is_numeric($col) ? $col : ord($col) - ord('A');
                for ($i = 1; $i <= 3; $i++) {
                    $checkCol = $hasLetterKeys ? chr(ord($col) + $i) : $colIndex + $i;
                    if (isset($row2[$checkCol])) {
                        $rateVal = trim($row2[$checkCol]);
                        $rateNum = floatval(preg_replace('/[^0-9.]/', '', $rateVal));
                        if ($rateNum > 0 && $rateNum <= 100000) {
                            $employeeInfo['per_day_rate'] = $rateNum;
                            break;
                        }
                    }
                }
            }

            // Also check for direct numeric salary values in row 2
            if ($employeeInfo['basic_monthly_salary'] == 0) {
                $cleanVal = preg_replace('/[^0-9.]/', '', $valStr);
                $numVal = floatval($cleanVal);
                if ($numVal >= 5000 && $numVal <= 500000 && strlen($cleanVal) >= 4) {
                    $employeeInfo['basic_monthly_salary'] = $numVal;
                }
            }
        }

        // Fallback: try known cell positions for per_day_rate
        // OLD format: J2. NEW format: O2.
        if ($employeeInfo['per_day_rate'] == 0) {
            $perDayCols = $hasLetterKeys ? ['J', 'O'] : [9, 14];
            foreach ($perDayCols as $pdCol) {
                // Try worksheet direct access first
                if ($worksheet !== null && is_string($pdCol)) {
                    try {
                        $cell = $worksheet->getCell($pdCol . '2');
                        $rawVal = $cell->getValue();
                        if ($rawVal !== null && $rawVal !== '' && is_numeric($rawVal)) {
                            $rateNum = floatval($rawVal);
                            if ($rateNum > 0 && $rateNum <= 100000) {
                                $employeeInfo['per_day_rate'] = $rateNum;
                                break;
                            }
                        }
                    } catch (\Exception $e) {}
                }
                // Fallback to toArray
                if (isset($row2[$pdCol])) {
                    $rateVal = trim($row2[$pdCol] ?? '');
                    $rateNum = floatval(preg_replace('/[^0-9.]/', '', $rateVal));
                    if ($rateNum > 0 && $rateNum <= 100000) {
                        $employeeInfo['per_day_rate'] = $rateNum;
                        break;
                    }
                }
            }
        }

        // Fallback: try known cell positions for basic_monthly_salary
        // OLD format: H2. NEW format: N2.
        if ($employeeInfo['basic_monthly_salary'] == 0) {
            $salaryCols = $hasLetterKeys ? ['H', 'N'] : [7, 13];
            foreach ($salaryCols as $sCol) {
                if ($worksheet !== null && is_string($sCol)) {
                    try {
                        $cell = $worksheet->getCell($sCol . '2');
                        $rawVal = $cell->getValue();
                        if ($rawVal !== null && $rawVal !== '' && is_numeric($rawVal)) {
                            $salNum = floatval($rawVal);
                            if ($salNum >= 1000 && $salNum <= 500000) {
                                $employeeInfo['basic_monthly_salary'] = $salNum;
                                break;
                            }
                        }
                    } catch (\Exception $e) {}
                }
                if (isset($row2[$sCol])) {
                    $salVal = trim($row2[$sCol] ?? '');
                    $salNum = floatval(preg_replace('/[^0-9.]/', '', $salVal));
                    if ($salNum >= 1000 && $salNum <= 500000) {
                        $employeeInfo['basic_monthly_salary'] = $salNum;
                        break;
                    }
                }
            }
        }
        
        // Extract schedule thresholds from row 2
        // OLD format: M2 = 'START:', N2 = '8:00', O2 = 'END:', P2 = '17:00'
        // Try multiple column positions for schedule thresholds:
        // NEW 10-sheet format: O2='START:', P2=threshold, Q2='END:', R2=endtime
        // OLD format: M2='START:', N2=threshold, O2='END:', P2=endtime
        // Older NEW format: I2 = late threshold, J2 = end time
        $thresholdColSets = [
            ['late_threshold' => $hasLetterKeys ? 'P' : 15, 'end_time' => $hasLetterKeys ? 'R' : 17],
            ['late_threshold' => $hasLetterKeys ? 'N' : 13, 'end_time' => $hasLetterKeys ? 'P' : 15],
            ['late_threshold' => $hasLetterKeys ? 'I' : 8,  'end_time' => $hasLetterKeys ? 'J' : 9],
        ];
        foreach ($thresholdColSets as $thresholdCols) {
        foreach ($thresholdCols as $thKey => $thCol) {
            if (!empty($scheduleThresholds[$thKey])) continue; // Already found from a previous column set
            $thVal = null;
            // Try worksheet direct access first
            if ($worksheet !== null && is_string($thCol)) {
                try {
                    $cell = $worksheet->getCell($thCol . '2');
                    $rawVal = $cell->getValue();
                    if ($rawVal !== null && $rawVal !== '') {
                        $thVal = parseTime($rawVal);
                    }
                    if ($thVal === null) {
                        $formatted = $cell->getFormattedValue();
                        if ($formatted !== null && $formatted !== '') {
                            $thVal = parseTime($formatted);
                        }
                    }
                } catch (\Exception $e) {}
            }
            // Fallback to toArray value
            if ($thVal === null && isset($row2[$thCol])) {
                $arrVal = $row2[$thCol];
                if ($arrVal !== null && $arrVal !== '') {
                    $thVal = parseTime($arrVal);
                }
            }
            // Fallback to raw toArray
            if ($thVal === null && $rowsRaw !== null && isset($rowsRaw[2][$thCol])) {
                $rawArr = $rowsRaw[2][$thCol];
                if ($rawArr !== null && $rawArr !== '') {
                    $thVal = parseTime($rawArr);
                }
            }
            if ($thVal !== null) {
                $scheduleThresholds[$thKey] = $thVal;
            }
        }
        } // end foreach thresholdColSets
        
        // Read TRAINER/FIXRATE from M2/N2 (fallback for non-worksheet path)
        if (empty($employeeInfo['is_trainer'])) {
            $mKey = $hasLetterKeys ? 'M' : 12;
            $trainerVal = trim((string)($row2[$mKey] ?? ''));
            // Skip if it's the old "START:" label
            if (!empty($trainerVal) && stripos($trainerVal, 'START') === false) {
                $employeeInfo['is_trainer'] = $trainerVal;
            }
        }
        if (empty($employeeInfo['is_fixrate'])) {
            $nKey = $hasLetterKeys ? 'N' : 13;
            $fixrateVal = trim((string)($row2[$nKey] ?? ''));
            // Skip if it looks like a time value (old threshold position)
            if (!empty($fixrateVal) && !preg_match('/^\d{1,2}:\d{2}$/', $fixrateVal)) {
                $employeeInfo['is_fixrate'] = $fixrateVal;
            }
        }
    }

        // Read OT rate only when explicitly provided as a non-formula value.
        // Do not auto-derive OT from formulas or per-day defaults.
        if ($employeeInfo['ot_rate'] == 0) {
            // Try direct worksheet cell first (L2 or other common positions)
            $otCandidates = ['L', 'P'];
            foreach ($otCandidates as $otCol) {
                if ($worksheet !== null) {
                    try {
                        $cell = $worksheet->getCell($otCol . '2');
                        // Ignore formula-driven OT values so OT stays manual unless explicitly set.
                        if ($cell && method_exists($cell, 'isFormula') && $cell->isFormula()) {
                            continue;
                        }

                        $val = $cell ? $cell->getValue() : null;
                        if ($val !== null && $val !== '' && is_numeric($val)) {
                            $employeeInfo['ot_rate'] = floatval($val);
                            break;
                        }

                        // Try formatted value for plain numeric text cells
                        $fmt = $cell ? $cell->getFormattedValue() : null;
                        if ($fmt !== null && $fmt !== '') {
                            $clean = floatval(preg_replace('/[^0-9.]/', '', $fmt));
                            if ($clean > 0) {
                                $employeeInfo['ot_rate'] = $clean;
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        // ignore and continue
                    }
                }
                // Fallback to array value (row2) only when worksheet object is unavailable.
                if ($worksheet === null && isset($row2[$otCol])) {
                    $clean = floatval(preg_replace('/[^0-9.]/', '', $row2[$otCol] ?? ''));
                    if ($clean > 0) {
                        $employeeInfo['ot_rate'] = $clean;
                        break;
                    }
                }
            }
        }
    
    // ========================================
    // STEP 2: Detect TB5 header rows (4-5) and build column map
    // ========================================
    
    $columnMap = [];
    
    // TB5 format has fixed column positions (simplified structure)
    // Row 4: MO/YR(A), AM(B), PM(C), ABSENT(D), OT(E), ...
    // Row 5: DATE(A), IN(B), OUT(C), (if absent)(D), OUT(E), ...
    
    $detectTB5 = false;
    if (isset($rows[4]) && isset($rows[5])) {
        $row4 = $rows[4];
        $row5 = $rows[5];
        
        // Check if row 4 or row 5 has TB5 indicators
        // Build text from all cells in row 4 and row 5
        $row4Cells = array_filter(array_map(function($v) { return strtoupper(trim($v ?? '')); }, $row4));
        $row5Cells = array_filter(array_map(function($v) { return strtoupper(trim($v ?? '')); }, $row5));
        
        $row4Text = implode(' ', $row4Cells);
        $row5Text = implode(' ', $row5Cells);
        
        // Check for TB5 indicators - be more flexible
        $hasMoYr = (strpos($row4Text, 'MO/YR') !== false || strpos($row4Text, 'MO YR') !== false || 
                    strpos($row4Text, 'MOYR') !== false || strpos($row5Text, 'DATE') !== false);
        $hasTimeColumns = (strpos($row4Text, 'AM') !== false || strpos($row4Text, 'PM') !== false || 
                           strpos($row5Text, 'IN') !== false || strpos($row5Text, 'OUT') !== false);
        $hasDtrIndicators = (strpos($row4Text, 'ABSENT') !== false ||
                            strpos($row4Text, 'LATE') !== false || strpos($row4Text, 'WORK') !== false);
        
        // Detect TB5 if we have at least 2 out of 3 indicators
        $indicatorCount = ($hasMoYr ? 1 : 0) + ($hasTimeColumns ? 1 : 0) + ($hasDtrIndicators ? 1 : 0);
        
        if ($indicatorCount >= 2) {
            $detectTB5 = true;
        }
        
        // Additional check: if row 4 has "DATE" in column A, that's also TB5
        $firstColKey = $hasLetterKeys ? 'A' : 0;
        if (isset($row4[$firstColKey])) {
            $row4FirstCell = strtoupper(trim($row4[$firstColKey]));
            if ($row4FirstCell === 'DATE' || $row4FirstCell === 'MO/YR' || strpos($row4FirstCell, 'MO/YR') !== false) {
                $detectTB5 = true;
            }
        }
        if (isset($row5[$firstColKey])) {
            $row5FirstCell = strtoupper(trim($row5[$firstColKey]));
            if ($row5FirstCell === 'DATE') {
                $detectTB5 = true;
            }
        }
    }
    
    // Track which TB5 sub-format is detected
    $isNewTB5Format = false;

    if ($detectTB5) {
        // Detect OLD vs NEW TB5 column layout:
        // OLD (simplified): A=Date, B=AM IN, C=PM OUT, D=ABSENT, E=TRAINING, F=OT OUT, ..., V=REMARKS, W=SHIFT 1, X=SHIFT 2
        // NEW (full/export): A=Date, B=AM IN, C=AM OUT, D=PM IN, E=PM OUT, F=ABSENT, G=OT OUT, ..., Z=REMARKS
        // Detection: In the NEW format, column F row 4 = 'ABSENT'. In the OLD format, D = 'ABSENT'.
        $fKey = $hasLetterKeys ? 'F' : 5;
        $row4F = strtoupper(trim($row4[$fKey] ?? ''));

        if (strpos($row4F, 'ABSENT') !== false) {
            // NEW full TB5 format (matches export_dtr_calculator.php / download_dtr_template.php)
            $isNewTB5Format = true;
            $columnMap = [
                'dtr_date' => $hasLetterKeys ? 'A' : 0,         // Column A - DATE
                'am_time_in' => $hasLetterKeys ? 'B' : 1,       // Column B - AM IN
                'am_time_out' => $hasLetterKeys ? 'C' : 2,      // Column C - AM OUT
                'pm_time_in' => $hasLetterKeys ? 'D' : 3,       // Column D - PM IN
                'pm_time_out' => $hasLetterKeys ? 'E' : 4,      // Column E - PM OUT
                'is_absent' => $hasLetterKeys ? 'F' : 5,        // Column F - ABSENT
                'ot_time_out' => $hasLetterKeys ? 'G' : 6,      // Column G - OT OUT
                'halfday_in' => $hasLetterKeys ? 'H' : 7,       // Column H - HALFDAY IN
                'halfday_out' => $hasLetterKeys ? 'I' : 8,      // Column I - HALFDAY OUT
                'total_work_hours' => $hasLetterKeys ? 'J' : 9,  // Column J - TOT.WORK
                'late_minutes' => $hasLetterKeys ? 'K' : 10,     // Column K - LATE (in minutes)
                'undertime_hours' => $hasLetterKeys ? 'L' : 11,  // Column L - UNDERTM (in hours)
                'daily_ot_hours' => $hasLetterKeys ? 'M' : 12,   // Column M - OT
                'remarks' => $hasLetterKeys ? 'AB' : 27         // Column AB - REMARKS
            ];
        } else {
            // OLD simplified TB5 format (export_dtr_calculator.php layout)
            $columnMap = [
                'dtr_date' => $hasLetterKeys ? 'A' : 0,        // Column A - DATE
                'am_time_in' => $hasLetterKeys ? 'B' : 1,      // Column B - AM IN
                'pm_time_out' => $hasLetterKeys ? 'C' : 2,     // Column C - PM OUT
                'is_absent' => $hasLetterKeys ? 'D' : 3,       // Column D - ABSENT
                'is_training' => $hasLetterKeys ? 'E' : 4,     // Column E - TRAINING
                'ot_time_out' => $hasLetterKeys ? 'F' : 5,     // Column F - OT OUT
                'total_work_hours' => $hasLetterKeys ? 'G' : 6, // Column G - TOT.WORK (in hours)
                'late_minutes' => $hasLetterKeys ? 'H' : 7,    // Column H - LATE (in minutes)
                'undertime_hours' => $hasLetterKeys ? 'I' : 8, // Column I - UNDERTM (in hours)
                'daily_ot_hours' => $hasLetterKeys ? 'J' : 9,  // Column J - OT
                'remarks' => $hasLetterKeys ? 'V' : 21,        // Column V - REMARKS
                'shift_1_selector' => $hasLetterKeys ? 'W' : 22, // Column W - SHIFT 1
                'shift_2_selector' => $hasLetterKeys ? 'X' : 23  // Column X - SHIFT 2
            ];
        }
    } else {
        // Fallback: Try to detect headers dynamically (original logic)
        // Look for a row with common DTR headers
        $dtrHeaderPatterns = ['date', 'day', 'am in', 'pm out', 'in', 'out', 'mo/yr', 'mo yr', 'absent', 'ot'];
        
        foreach ($rows as $rowNum => $row) {
            if (!is_array($row)) continue;
            
            $rowLower = array_map(function($v) { return strtolower(trim($v ?? '')); }, $row);
            $matchCount = 0;
            
            foreach ($rowLower as $val) {
                foreach ($dtrHeaderPatterns as $pattern) {
                    if (strpos($val, $pattern) !== false) {
                        $matchCount++;
                        break;
                    }
                }
            }
            
            if ($matchCount >= 3) {
                // Found header row - build column map
                foreach ($row as $col => $val) {
                    $header = strtolower(trim($val ?? ''));
                    
                    if (in_array($header, ['date', 'dtr_date', 'day', 'mo/yr', 'mo yr'])) {
                        $columnMap['dtr_date'] = $col;
                    } elseif (in_array($header, ['am in', 'am_in', 'time in am', 'in']) && !isset($columnMap['am_time_in'])) {
                        $columnMap['am_time_in'] = $col;
                    } elseif (in_array($header, ['pm out', 'pm_out', 'time out pm', 'out']) && !isset($columnMap['pm_time_out'])) {
                        $columnMap['pm_time_out'] = $col;
                    } elseif (in_array($header, ['absent', 'is_absent', 'abs', 'if absent', '(if absent)'])) {
                        $columnMap['is_absent'] = $col;
                    } elseif (in_array($header, ['training', 'is_training', 'train'])) {
                        $columnMap['is_training'] = $col;
                    } elseif (in_array($header, ['ot out', 'ot_out', 'overtime out', 'ot'])) {
                        $columnMap['ot_time_out'] = $col;
                    } elseif (in_array($header, ['shift 1', 'shift1', 'shift-1', 's1'])) {
                        $columnMap['shift_1_selector'] = $col;
                    } elseif (in_array($header, ['shift 2', 'shift2', 'shift-2', 's2'])) {
                        $columnMap['shift_2_selector'] = $col;
                    } elseif (in_array($header, ['remarks', 'notes'])) {
                        $columnMap['remarks'] = $col;
                    }
                }
                break;
            }
        }
    } // Close else block
    
    // Ensure we have at least date column
    if (empty($columnMap['dtr_date'])) {
        // Last resort: assume column A is date
        $columnMap['dtr_date'] = 'A';
    }
    
    // ========================================
    // STEP 3: Parse DTR data rows
    // ========================================
    
    // For TB5 format, data starts at row 6, ends at row 36 (31 days)
    $dataStartRow = $detectTB5 ? 6 : 1;
    $dataEndRow = $detectTB5 ? 36 : 9999; // TB5 template: rows 6-36
    
    $skippedRows = [];
    $dataSkippedRows = []; // Only reasons for rows >= dataStartRow
    $processedRows = 0;
    $autoDateCount = 0;
    $sampleRows = []; // Actual row data for debugging
    
    // Helper function: get cell value safely (handles null from toArray)
    $getCell = function($row, $key) {
        if (array_key_exists($key, $row)) {
            return $row[$key]; // returns null, '', 0, or actual value
        }
        return null;
    };

    // Read a cell from formatted rows, then raw rows, then worksheet direct value.
    $getCellFromSources = function($rowNum, $row, $colKey) use ($getCell, $rowsRaw, $worksheet) {
        $value = $getCell($row, $colKey);

        if (($value === null || $value === '') && $rowsRaw !== null && isset($rowsRaw[$rowNum])) {
            if (array_key_exists($colKey, $rowsRaw[$rowNum])) {
                $value = $rowsRaw[$rowNum][$colKey];
            }
        }

        if (($value === null || $value === '') && $worksheet !== null && is_string($colKey)) {
            try {
                $wsValue = $worksheet->getCell($colKey . $rowNum)->getValue();
                if ($wsValue !== null && $wsValue !== '') {
                    $value = $wsValue;
                }
            } catch (\Exception $e) {
                // Ignore and return best-effort value from other sources.
            }
        }

        return $value;
    };

    $parseMarkedFlag = function($value) {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return floatval($value) > 0;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return false;
        }

        return !in_array($normalized, ['0', 'no', 'n', 'false', 'off', 'none', 'na', 'n/a'], true);
    };
    
    // Input column keys for TB5 format (user-editable cells)
    if ($isNewTB5Format) {
        // New format: B=AM IN, C=AM OUT, D=PM IN, E=PM OUT, F=Absent, G=OT OUT, H=Halfday IN, I=Halfday OUT
        $inputCols = $hasLetterKeys 
            ? ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] 
            : [1, 2, 3, 4, 5, 6, 7, 8];
    } else {
        // Old format: B=AM IN, C=PM OUT, D=Absent, E=Training, F=OT OUT, W=Shift 1, X=Shift 2, H-I=Halfday
        $inputCols = $hasLetterKeys 
            ? ['B', 'C', 'D', 'E', 'F', 'H', 'I', 'W', 'X'] 
            : [1, 2, 3, 4, 5, 7, 8, 22, 23];
    }
    
    foreach ($rows as $rowNum => $row) {
        if (!is_array($row)) {
            continue;
        }
        
        if ($rowNum < $dataStartRow) {
            continue; // silently skip header rows
        }
        
        // For TB5 format, stop at the expected data end row
        if ($detectTB5 && $rowNum > $dataEndRow) {
            break;
        }
        
        // Capture sample data from first 3 data rows for debugging
        if (count($sampleRows) < 3) {
            $sample = ['row' => $rowNum];
            $sampleCols = $hasLetterKeys ? ['A','B','C','D','E','F','G','J'] : [0,1,2,3,4,5,6,9];
            foreach ($sampleCols as $sc) {
                $colLabel = is_numeric($sc) ? chr(65 + $sc) : $sc;
                $cellVal = $getCell($row, $sc);
                // Include type info for debugging time parsing issues
                $typeStr = gettype($cellVal);
                $valStr = $cellVal === null ? 'NULL' : (string)$cellVal;
                
                // Also get raw value for comparison
                $rawStr = '';
                if ($rowsRaw !== null && isset($rowsRaw[$rowNum][$sc])) {
                    $rawCellVal = $rowsRaw[$rowNum][$sc];
                    $rawStr = '|raw=' . ($rawCellVal === null ? 'NULL' : (string)$rawCellVal);
                }
                
                // Get worksheet getValue() for comparison
                $wsStr = '';
                if ($worksheet !== null && is_string($sc)) {
                    try {
                        $wsVal = $worksheet->getCell($sc . $rowNum)->getValue();
                        $wsType = gettype($wsVal);
                        $wsStr = '|ws=' . ($wsVal === null ? 'NULL' : var_export($wsVal, true)) . "($wsType)";
                    } catch (\Exception $e) {
                        $wsStr = '|ws=ERR';
                    }
                }
                
                $sample[$colLabel] = "{$valStr}({$typeStr}){$rawStr}{$wsStr}";
            }
            $sampleRows[] = $sample;
        }
        
        // Check if row has meaningful INPUT data (user-entered cells B-E, F, H-I)
        // With formatData=false, time values come as floats (e.g., 0.333 for 8:00 AM)
        $hasInputData = false;
        foreach ($inputCols as $ic) {
            // Check formatted data array
            $val = $getCell($row, $ic);
            if ($val !== null && $val !== '') {
                if (is_numeric($val)) {
                    $numVal = floatval($val);
                    if ($numVal > 0) {
                        $hasInputData = true;
                        break;
                    }
                } else {
                    $strVal = trim((string)$val);
                    if ($strVal !== '' && $strVal !== '0') {
                        $hasInputData = true;
                        break;
                    }
                }
            }
            // Also check raw (unformatted) data array - catches numeric time fractions 
            // that might be formatted as empty/zero in the formatted array
            if (!$hasInputData && $rowsRaw !== null && isset($rowsRaw[$rowNum])) {
                $rawVal = $rowsRaw[$rowNum][$ic] ?? null;
                if ($rawVal !== null && $rawVal !== '') {
                    if (is_numeric($rawVal) && floatval($rawVal) > 0) {
                        $hasInputData = true;
                        break;
                    } elseif (!is_numeric($rawVal)) {
                        $strRaw = trim((string)$rawVal);
                        if ($strRaw !== '' && $strRaw !== '0') {
                            $hasInputData = true;
                            break;
                        }
                    }
                }
            }
            // Also check worksheet cell directly
            if (!$hasInputData && $worksheet !== null && is_string($ic)) {
                try {
                    $cellVal = $worksheet->getCell($ic . $rowNum)->getValue();
                    if ($cellVal !== null && $cellVal !== '') {
                        if (is_numeric($cellVal) && floatval($cellVal) > 0) {
                            $hasInputData = true;
                            break;
                        } elseif (!is_numeric($cellVal) && is_object($cellVal) === false) {
                            $strCell = trim((string)$cellVal);
                            if ($strCell !== '' && $strCell !== '0') {
                                $hasInputData = true;
                                break;
                            }
                        } elseif ($cellVal instanceof \DateTimeInterface) {
                            $hasInputData = true;
                            break;
                        }
                    }
                } catch (\Exception $e) {}
            }
        }
        
        // Also check if ANY cell has non-null, non-zero data
        $hasAnyData = false;
        foreach ($row as $col => $val) {
            if ($val !== null && $val !== '' && (string)$val !== '') {
                $hasAnyData = true;
                break;
            }
        }
        
        if (!$hasAnyData) {
            $dataSkippedRows[] = "Row $rowNum: completely empty";
            continue;
        }
        
        // Skip totals row
        $firstCellKey = $hasLetterKeys ? 'A' : 0;
        $firstCell = strtolower(trim($getCell($row, $firstCellKey) ?? ''));
        if (in_array($firstCell, ['total', 'total:', 'sum', 'totals', 'grand total'])) {
            $dataSkippedRows[] = "Row $rowNum: totals row";
            // For TB5 layouts, anything below TOTALS is summary metadata, not DTR day rows.
            if ($detectTB5) {
                break;
            }
            continue;
        }
        
        // Get date value from column A
        $dateKey = $columnMap['dtr_date'] ?? ($hasLetterKeys ? 'A' : 0);
        $dateValue = $getCell($row, $dateKey);
        $parsedDate = parseDate($dateValue);

        // In TB5, skip rows with no date and no editable-input data.
        // This prevents formula-only/summary rows from being auto-converted into fake dates.
        if ($detectTB5 && empty($parsedDate) && !$hasInputData) {
            $dataSkippedRows[] = "Row $rowNum: no date and no input data";
            continue;
        }
        
        // TB5 AUTO-DATE: If date is missing but we're in TB5 format, generate date from row position
        if (empty($parsedDate) && $detectTB5) {
            $dayOffset = $rowNum - $dataStartRow; // row 6=0, row 7=1, etc.
            
            if ($periodStart) {
                // Use payroll period start date + offset
                $baseDate = new DateTime($periodStart);
                $baseDate->modify("+{$dayOffset} days");
                
                // Don't exceed period end date if specified
                if ($periodEnd && $baseDate > new DateTime($periodEnd)) {
                    $dataSkippedRows[] = "Row $rowNum: auto-date exceeds period end ({$baseDate->format('Y-m-d')} > {$periodEnd})";
                    continue;
                }
                $parsedDate = $baseDate->format('Y-m-d');
            } else {
                // Use 1st of current month + offset, but stay within the same month
                $baseDate = new DateTime(date('Y-m-01'));
                $originalMonth = (int)$baseDate->format('n');
                $baseDate->modify("+{$dayOffset} days");
                
                // Stop if we've crossed into next month
                if ((int)$baseDate->format('n') !== $originalMonth) {
                    $dataSkippedRows[] = "Row $rowNum: auto-date crosses month boundary ({$baseDate->format('Y-m-d')})";
                    continue;
                }
                $parsedDate = $baseDate->format('Y-m-d');
            }
            $autoDateCount++;
        }
        
        // For non-TB5 format, skip if no valid date
        if (empty($parsedDate)) {
            $dataSkippedRows[] = "Row $rowNum: no valid date (raw=" . var_export($dateValue, true) . ")";
            continue;
        }

        // If a payroll period was explicitly selected, enforce strict period boundaries.
        if (!empty($periodStart) && !empty($periodEnd)) {
            if ($parsedDate < $periodStart || $parsedDate > $periodEnd) {
                $dataSkippedRows[] = "Row $rowNum: date {$parsedDate} outside selected period {$periodStart} to {$periodEnd}";
                continue;
            }
        }
        
        $processedRows++;
        
        // Build DTR record
        $record = [
            'dtr_date' => $parsedDate,
            'am_time_in' => null,
            'am_time_out' => null,
            'pm_time_in' => null,
            'pm_time_out' => null,
            'is_absent' => 0,
            'is_training' => 0,
            'day_override_enabled' => 0,
            'ot_time_out' => null,
            'halfday_in' => null,
            'halfday_out' => null,
            'total_work_hours' => 0,
            'late_minutes' => 0,
            'undertime_hours' => 0,
            'daily_ot_hours' => 0,
            'remarks' => null
        ];
        
        // Parse time fields - use worksheet directly if available for maximum reliability
        $timeFields = ['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out', 'ot_time_out', 'halfday_in', 'halfday_out'];
        foreach ($timeFields as $field) {
            if (isset($columnMap[$field])) {
                $colKey = $columnMap[$field];
                $parsedTime = null;
                
                // METHOD 1: Read directly from worksheet cell (most reliable for PhpSpreadsheet)
                if ($worksheet !== null && is_string($colKey)) {
                    try {
                        $cellRef = $colKey . $rowNum;
                        $cell = $worksheet->getCell($cellRef);
                        
                        // Try raw getValue() first (returns float for time values)
                        $rawValue = $cell->getValue();
                        if ($rawValue !== null && $rawValue !== '') {
                            $parsedTime = parseTime($rawValue);
                        }
                        
                        // If raw didn't work, try getFormattedValue() (returns "8:00 AM" style string)
                        if ($parsedTime === null) {
                            $formattedValue = $cell->getFormattedValue();
                            if ($formattedValue !== null && $formattedValue !== '' && $formattedValue !== '0') {
                                $parsedTime = parseTime($formattedValue);
                            }
                        }
                        
                        // If still null, try getCalculatedValue() (for formula cells)
                        if ($parsedTime === null) {
                            $calcValue = $cell->getCalculatedValue();
                            if ($calcValue !== null && $calcValue !== '' && $calcValue !== $rawValue) {
                                $parsedTime = parseTime($calcValue);
                            }
                        }
                    } catch (\Exception $e) {
                        // Fall through to toArray method below
                    }
                }
                
                // METHOD 2: Fallback to toArray formatted value
                if ($parsedTime === null) {
                    $timeValue = $getCell($row, $colKey);
                    if ($timeValue !== null && $timeValue !== '') {
                        $parsedTime = parseTime($timeValue);
                    }
                }
                
                // METHOD 3: Fallback to toArray RAW (unformatted) value - reliable for numeric time fractions
                if ($parsedTime === null && $rowsRaw !== null && isset($rowsRaw[$rowNum])) {
                    $rawTimeValue = $rowsRaw[$rowNum][$colKey] ?? null;
                    if ($rawTimeValue !== null && $rawTimeValue !== '') {
                        $parsedTime = parseTime($rawTimeValue);
                    }
                }
                
                $record[$field] = $parsedTime;
            }
        }
        
        // Fix PM times: TB5 format uses 12-hour notation in PM columns
        // e.g., user types "5:00" in PM OUT column → Excel stores as 5:00 AM (0.20833)
        // We need to convert to 24-hour: "05:00" → "17:00"
        if (!empty($record['pm_time_out'])) {
            $parts = explode(':', $record['pm_time_out']);
            $hour = intval($parts[0]);
            if ($hour > 0 && $hour < 12) {
                $record['pm_time_out'] = sprintf('%02d:%s', $hour + 12, $parts[1] ?? '00');
            }
        }
        
        // Parse absent status
        if (isset($columnMap['is_absent'])) {
            $absentValue = strtolower(trim((string)($getCell($row, $columnMap['is_absent']) ?? '')));
            $record['is_absent'] = in_array($absentValue, ['yes', 'y', '1', 'true', 'absent', 'x', '✓', '✔', 'checked']) ? 1 : 0;
        }
        
        // Parse training status
        if (isset($columnMap['is_training'])) {
            $trainingValue = strtolower(trim((string)($getCell($row, $columnMap['is_training']) ?? '')));
            $record['is_training'] = in_array($trainingValue, ['yes', 'y', '1', 'true', 'training', 'x', '✓', '✔', 'checked']) ? 1 : 0;
        }
        
        // For NEW TB5 format: detect training from remarks column (Z)
        // The export writes "TRAINING" in the remarks column for training rows
        if ($isNewTB5Format && isset($columnMap['remarks']) && $record['is_training'] == 0) {
            $remarkVal = strtolower(trim((string)($getCell($row, $columnMap['remarks']) ?? '')));
            if (strpos($remarkVal, 'training') !== false) {
                $record['is_training'] = 1;
            }
        }

        // Shift selector import support:
        // - Marking Shift 2 enables day override for that row.
        // - Shift 1 (or both blank) defaults to no day override.
        $shift1Marked = false;
        $shift2Marked = false;

        if (isset($columnMap['shift_1_selector'])) {
            $shift1Marked = $parseMarkedFlag($getCellFromSources($rowNum, $row, $columnMap['shift_1_selector']));
        }
        if (isset($columnMap['shift_2_selector'])) {
            $shift2Marked = $parseMarkedFlag($getCellFromSources($rowNum, $row, $columnMap['shift_2_selector']));
        }

        $record['day_override_enabled'] = $shift2Marked ? 1 : 0;
        
        // Parse numeric fields (calculated values from Excel)
        $numericFields = ['total_work_hours', 'late_minutes', 'undertime_hours', 'daily_ot_hours'];
        foreach ($numericFields as $field) {
            if (isset($columnMap[$field])) {
                $val = $getCell($row, $columnMap[$field]);
                $record[$field] = ($val !== null && is_numeric($val)) ? floatval($val) : 0;
            }
        }
        
        // Parse remarks
        if (isset($columnMap['remarks'])) {
            $remarkVal = $getCell($row, $columnMap['remarks']);
            $record['remarks'] = ($remarkVal !== null) ? trim((string)$remarkVal) : null;
        }
        
        // Parse trainee columns — only for OLD format where X=count, Y=per-trainee, Z=total
        // In NEW format, X=Gov't, Y=Salary, Z=Remarks (no per-row trainee data)
        if (!$isNewTB5Format) {
            $hasShiftSelectors = isset($columnMap['shift_1_selector']) || isset($columnMap['shift_2_selector']);

            if ($hasShiftSelectors) {
                // Current simplified template reserves W/X for Shift 1/Shift 2 selectors.
                // Disable legacy trainee-column parsing to avoid cross-field collisions.
                $record['trainee_count']     = 0;
                $record['trainee_pay_each']  = 0;
                $record['trainee_total_pay'] = 0;
            } else {
                $traineeCountKey = $hasLetterKeys ? 'X' : 23;
                $traineePayKey   = $hasLetterKeys ? 'Y' : 24;
                $traineeTotalKey = $hasLetterKeys ? 'Z' : 25;

                $traineeCount = floatval($getCell($row, $traineeCountKey) ?? 0);
                $traineePayPerUnit = floatval($getCell($row, $traineePayKey) ?? 0);
                $traineeTotal = floatval($getCell($row, $traineeTotalKey) ?? 0);

                if ($traineeTotal == 0 && $traineeCount > 0 && $traineePayPerUnit > 0) {
                    $traineeTotal = $traineeCount * $traineePayPerUnit;
                }

                $record['trainee_count']     = (int)$traineeCount;
                $record['trainee_pay_each']  = round($traineePayPerUnit, 2);
                $record['trainee_total_pay'] = round($traineeTotal, 2);
            }
        } else {
            $record['trainee_count']     = 0;
            $record['trainee_pay_each']  = 0;
            $record['trainee_total_pay'] = 0;
        }

        // For new format, extract training payment from summary cell (e.g., N44/O44/P44)
        // Only do this once per import (after all rows processed)
        // This logic will be used after the foreach loop below
        
        // SERVER-SIDE work_hours calculation (belt-and-suspenders with JS recalculation)
        // This ensures work_hours has a value even if JS recalculation fails
        if (empty($record['total_work_hours']) || $record['total_work_hours'] == 0) {
            $workHours = computeTimeDiffHours($record['am_time_in'], $record['pm_time_out']);
            // Subtract 1 hour for lunch if both times present
            if ($workHours > 1 && !empty($record['am_time_in']) && !empty($record['pm_time_out'])) {
                $workHours = $workHours - 1;
            }
            $record['total_work_hours'] = round($workHours, 2);
        }
        
        $dtrRecords[] = $record;
    } // Close foreach ($rows as $rowNum => $row)
    
    // Build time debug info from first 3 processed records
    $timeDebug = [];
    foreach (array_slice($dtrRecords, 0, 3) as $idx => $rec) {
        $timeDebug[] = sprintf(
            "Rec%d date=%s am_in=%s pm_out=%s",
            $idx + 1,
            $rec['dtr_date'] ?? 'NULL',
            $rec['am_time_in'] ?? 'NULL',
            $rec['pm_time_out'] ?? 'NULL'
        );
    }
    
    // For new format, extract training payment from summary section below totals.
    // The export/template writes "TRAINING PAYMENT:" label in column A, with the value in column B.
    // The row varies depending on how many data rows exist, so we scan for it.
    $training_payment = 0;
    $training_remarks = '';
    // Scan for training payment in BOTH old and new formats
    {
        $aKey = $hasLetterKeys ? 'A' : 0;
        $bKey = $hasLetterKeys ? 'B' : 1;
        $cKey = $hasLetterKeys ? 'C' : 2;
        
        // Method 1: Scan rows after data section for "TRAINING PAYMENT" label in column A
        $scanStart = $dataEndRow + 1; // Start after data rows (row 37+)
        $scanEnd = min($dataEndRow + 15, count($rows)); // Scan up to 15 rows past data
        
        $fKey = $hasLetterKeys ? 'F' : 5;
        $hKey = $hasLetterKeys ? 'H' : 7;
        
        for ($scanRow = $scanStart; $scanRow <= $scanEnd; $scanRow++) {
            if (!isset($rows[$scanRow])) continue;
            $cellA = strtoupper(trim((string)($rows[$scanRow][$aKey] ?? '')));
            if (strpos($cellA, 'TRAINING') !== false && strpos($cellA, 'PAYMENT') !== false) {
                // Found the training payment label row — read column B first (summary format)
                $tpVal = null;
                
                // Try worksheet directly first (most reliable for formulas)
                if ($worksheet !== null && is_string($bKey)) {
                    try {
                        $tpCell = $worksheet->getCell($bKey . $scanRow);
                        $tpVal = $tpCell ? $tpCell->getCalculatedValue() : null;
                    } catch (\Exception $e) {}
                }
                
                // Fallback to array value
                if ($tpVal === null || !is_numeric($tpVal)) {
                    $tpVal = $rows[$scanRow][$bKey] ?? null;
                }
                
                if ($tpVal !== null && is_numeric($tpVal) && floatval($tpVal) > 0) {
                    $training_payment = floatval($tpVal);
                }
                
                // New export format: amount is in column F (after "Amount:" label in E)
                if ($training_payment == 0) {
                    $tpValF = null;
                    if ($worksheet !== null && is_string($fKey)) {
                        try {
                            $tpCellF = $worksheet->getCell($fKey . $scanRow);
                            $tpValF = $tpCellF ? $tpCellF->getCalculatedValue() : null;
                        } catch (\Exception $e) {}
                    }
                    if ($tpValF === null || !is_numeric($tpValF)) {
                        $tpValF = $rows[$scanRow][$fKey] ?? null;
                    }
                    if ($tpValF !== null && is_numeric($tpValF) && floatval($tpValF) > 0) {
                        $training_payment = floatval($tpValF);
                    }
                }
                
                // Extract training remarks — try column C first (summary format)
                // But skip if column C contains the literal label "Remarks" or similar
                $trRemarks = trim((string)($rows[$scanRow][$cKey] ?? ''));
                if (!empty($trRemarks) && !preg_match('/^remarks?:?$/i', $trRemarks)) {
                    $training_remarks = $trRemarks;
                } elseif ($worksheet !== null && is_string($cKey)) {
                    try {
                        $trCell = $worksheet->getCell($cKey . $scanRow);
                        $trRemarks = $trCell ? trim((string)$trCell->getValue()) : '';
                        if (!empty($trRemarks) && !preg_match('/^remarks?:?$/i', $trRemarks)) {
                            $training_remarks = $trRemarks;
                        }
                    } catch (\Exception $e) {}
                }

                // New export format: remarks may be spread across columns H..L (e.g., H,I,J,K,L)
                    if (empty($training_remarks)) {
                    // Build candidate columns depending on letter/key mode
                    // Include column D (template's remarks input) to be robust
                    if ($hasLetterKeys) {
                        $remarksCols = ['C','D','H','I','J','K','L'];
                    } else {
                        $remarksCols = [2,3,7,8,9,10,11];
                    }
                    $colRemarks = [];
                    foreach ($remarksCols as $rKey) {
                        // 1) Try the rows array (already formatted by toArray)
                        if (isset($rows[$scanRow][$rKey]) && trim((string)$rows[$scanRow][$rKey]) !== '') {
                            $colRemarks[] = trim((string)$rows[$scanRow][$rKey]);
                            continue;
                        }

                        // 2) Try worksheet methods if available (calculated/formatted/raw)
                        if ($worksheet !== null && is_string($rKey)) {
                            try {
                                $cellRef = $rKey . $scanRow;
                                $cell = $worksheet->getCell($cellRef);
                                // Prefer calculated value for formula cells
                                $val = null;
                                try {
                                    $val = $cell ? $cell->getCalculatedValue() : null;
                                } catch (\Exception $e) {
                                    // ignore
                                }
                                if ($val === null || $val === '') {
                                    $fmt = null;
                                    try { $fmt = $cell ? $cell->getFormattedValue() : null; } catch (\Exception $e) { }
                                    if ($fmt !== null && $fmt !== '') $val = $fmt;
                                }
                                if ($val === null || $val === '') {
                                    try { $val = $cell ? $cell->getValue() : null; } catch (\Exception $e) { }
                                }
                                if ($val !== null && trim((string)$val) !== '') {
                                    $colRemarks[] = trim((string)$val);
                                    continue;
                                }
                            } catch (\Exception $e) {
                                // ignore and continue
                            }
                        }
                    }
                    if (!empty($colRemarks)) {
                        // Filter out any cells that are just the label 'Remarks' and prefer user input
                        $filtered = array_filter($colRemarks, function($v){ return !preg_match('/^remarks?:?$/i', trim($v)); });
                        if (!empty($filtered)) {
                            $training_remarks = implode(' ', array_filter($filtered));
                        } else {
                            $training_remarks = implode(' ', array_filter($colRemarks));
                        }
                    }
                }
                
                // If we found the amount, stop scanning
                if ($training_payment > 0) break;
                // Otherwise continue scanning for the summary row which has B=amount
            }
        }
        
        // Method 2: If scan didn't find it, try well-known fixed positions (template = B43)
        if ($training_payment == 0 && $worksheet !== null) {
            $fixedCells = ['B43', 'B44', 'B45'];
            foreach ($fixedCells as $cellRef) {
                try {
                    $rowNum43 = intval(substr($cellRef, 1));
                    $labelCell = $worksheet->getCell('A' . $rowNum43);
                    $labelVal = $labelCell ? strtoupper(trim((string)$labelCell->getValue())) : '';
                    if (strpos($labelVal, 'TRAINING') !== false) {
                        $cell = $worksheet->getCell($cellRef);
                        $val = $cell ? $cell->getCalculatedValue() : null;
                        if ($val !== null && is_numeric($val) && floatval($val) > 0) {
                            $training_payment = floatval($val);
                        }
                        // Also get remarks from column C
                        if (empty($training_remarks)) {
                            $trCell = $worksheet->getCell('C' . $rowNum43);
                            $trRemarks = $trCell ? trim((string)$trCell->getValue()) : '';
                            if (!empty($trRemarks)) {
                                $training_remarks = $trRemarks;
                            }
                        }
                        break;
                    }
                } catch (\Exception $e) {}
            }
        }
    }
    return [
        'employee_info' => $employeeInfo,
        'dtr_records' => $dtrRecords,
        'schedule_thresholds' => $scheduleThresholds,
        // Trainee summary: sum all trainee_total_pay across rows (old format) or use summary cell (new format)
        'trainee_summary' => [
            'total_count'   => $isNewTB5Format ? 0 : array_sum(array_column($dtrRecords, 'trainee_count')),
            'total_cost'    => $training_payment > 0 ? $training_payment : round(array_sum(array_column($dtrRecords, 'trainee_total_pay')), 2),
            'pay_per_unit'  => $isNewTB5Format ? 0 : (function() use ($dtrRecords) {
                foreach ($dtrRecords as $r) {
                    if (($r['trainee_pay_each'] ?? 0) > 0) return $r['trainee_pay_each'];
                }
                return 0;
            })(),
            'training_remarks' => $training_remarks
        ],
        'debug_format' => $detectTB5 ? 'TB5 detected (rows 4-5 headers, data from row 6)' : 'Generic/fallback detection',
        'debug_data_start_row' => $dataStartRow,
        'debug_rows_scanned' => count($rows),
        'debug_rows_processed' => $processedRows,
        'debug_auto_dates' => $autoDateCount,
        'debug_data_rows_skipped' => count($dataSkippedRows),
        'debug_skipped_details' => array_slice($dataSkippedRows, 0, 15),
        'debug_sample_rows' => $sampleRows,
        'debug_column_map' => $columnMap,
        'debug_has_letter_keys' => $hasLetterKeys,
        'debug_has_worksheet' => ($worksheet !== null ? 'yes' : 'no'),
        'debug_has_raw_rows' => ($rowsRaw !== null ? 'yes' : 'no'),
        'debug_time_values' => $timeDebug
    ];
} // Close function extractAllDataFromRows

/**
 * Check if value matches any of the patterns
 */
function matchesPatterns($value, $patterns) {
    foreach ($patterns as $pattern) {
        if ($value === $pattern || strpos($value, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Parse date from various formats
 */
function parseDate($value) {
    if ($value === null || $value === '' || $value === false) return null;
    
    // Convert to string and trim
    $value = trim((string)$value);
    if ($value === '' || $value === '0') return null;
    
    // If numeric (Excel serial date) - valid serial dates are > 1 (Jan 1, 1900)
    if (is_numeric($value) && floatval($value) > 1) {
        $serialDate = floatval($value);
        // Excel serial dates: days since Jan 0, 1900
        // Only process reasonable date range (year 1900-2100)
        if ($serialDate >= 1 && $serialDate <= 73050) {
            $unixDate = ($serialDate - 25569) * 86400;
            $result = date('Y-m-d', (int)$unixDate);
            // Validate the result is reasonable
            $year = (int)date('Y', (int)$unixDate);
            if ($year >= 1990 && $year <= 2100) {
                return $result;
            }
        }
    }
    
    // Handle M/D format (e.g., "10/13", "2/5") - use current year
    if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
        $month = intval($matches[1]);
        $day = intval($matches[2]);
        $year = date('Y');
        if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    
    // Handle M-D format (e.g., "10-13", "2-5") - use current year
    if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $value, $matches)) {
        $month = intval($matches[1]);
        $day = intval($matches[2]);
        $year = date('Y');
        if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    
    // Handle PhpSpreadsheet formatted date strings (e.g., "2/24/2026", "02/24/2026")
    // Also handle 2-digit year: "2/24/26"
    $formats = [
        'n/j/Y',    // 2/24/2026
        'm/d/Y',    // 02/24/2026
        'n/j/y',    // 2/24/26
        'm/d/y',    // 02/24/26
        'Y-m-d',    // 2026-02-24
        'Y/m/d',    // 2026/02/24
        'd/m/Y',    // 24/02/2026
        'd-m-Y',    // 24-02-2026
        'm-d-Y',    // 02-24-2026
        'n-j-Y',    // 2-24-2026
        'M d, Y',   // Feb 24, 2026
        'F d, Y',   // February 24, 2026
        'M j, Y',   // Feb 5, 2026
        'd-M-Y',    // 24-Feb-2026
        'd-M-y',    // 24-Feb-26
        'M-d',      // Feb-24 (PhpSpreadsheet MMM-DD format)
        'M-j',      // Feb-5
        'n/j',      // 2/24
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat('!' . $format, $value);
        if ($date !== false) {
            $parsed = $date->format('Y-m-d');
            // For formats without year, use current year
            if (strpos($format, 'Y') === false && strpos($format, 'y') === false) {
                $date->setDate((int)date('Y'), (int)$date->format('m'), (int)$date->format('d'));
                $parsed = $date->format('Y-m-d');
            }
            return $parsed;
        }
    }
    
    // Try strtotime as fallback (handles "Feb 24", "October 13, 2025", etc.)
    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        $year = (int)date('Y', $timestamp);
        if ($year >= 1990 && $year <= 2100) {
            return date('Y-m-d', $timestamp);
        }
    }
    
    return null;
}

/**
 * Compute time difference in hours between two HH:MM strings
 * Returns 0 if either time is null/empty or if result would be negative
 */
function computeTimeDiffHours($timeIn, $timeOut) {
    if (empty($timeIn) || empty($timeOut)) return 0;
    
    $inParts = explode(':', $timeIn);
    $outParts = explode(':', $timeOut);
    
    if (count($inParts) < 2 || count($outParts) < 2) return 0;
    
    $inMinutes = intval($inParts[0]) * 60 + intval($inParts[1]);
    $outMinutes = intval($outParts[0]) * 60 + intval($outParts[1]);
    
    $diff = $outMinutes - $inMinutes;
    return $diff > 0 ? $diff / 60 : 0;
}

/**
 * Parse time from various formats
 * Handles: Excel time fractions, HH:MM, H:MM:SS, AM/PM, DateTime objects,
 *          date+time strings, dot-separated times, etc.
 */
function parseTime($value) {
    if ($value === null || $value === '' || $value === false) return null;
    
    // Handle DateTimeInterface objects (some PhpSpreadsheet versions return these)
    if ($value instanceof \DateTimeInterface) {
        return $value->format('H:i');
    }
    
    // Handle PhpSpreadsheet RichText objects
    if (is_object($value)) {
        if (method_exists($value, 'getPlainText')) {
            $value = $value->getPlainText();
        } elseif (method_exists($value, '__toString')) {
            $value = (string)$value;
        } else {
            return null;
        }
    }
    
    // Handle numeric values first (before string conversion)
    // Excel time = fraction of day, e.g., 0.3333 = 8:00 AM
    if (is_numeric($value)) {
        $numVal = floatval($value);
        // Time fraction (0 < val < 1) - standard Excel time
        if ($numVal > 0 && $numVal < 1) {
            $totalSeconds = round($numVal * 86400);
            $hours = floor($totalSeconds / 3600);
            $minutes = floor(($totalSeconds % 3600) / 60);
            return sprintf('%02d:%02d', $hours, $minutes);
        }
        // Value >= 1 could be a date+time serial (e.g., 45678.333 = date + 8:00 AM)
        if ($numVal >= 1 && $numVal < 100000) {
            $timeFraction = $numVal - floor($numVal);
            if ($timeFraction > 0) {
                $totalSeconds = round($timeFraction * 86400);
                $hours = floor($totalSeconds / 3600);
                $minutes = floor(($totalSeconds % 3600) / 60);
                if ($hours >= 0 && $hours <= 23) {
                    return sprintf('%02d:%02d', $hours, $minutes);
                }
            }
        }
        // Integer hours (e.g., 8, 13, 17) - treat as hour value if in valid range
        if ($numVal >= 1 && $numVal <= 23 && $numVal == floor($numVal)) {
            return sprintf('%02d:00', intval($numVal));
        }
        // Zero or negative - not a valid time
        return null;
    }
    
    $value = trim((string)$value);
    if ($value === '' || $value === '0') return null;
    
    // If already in HH:MM or H:MM:SS format (24-hour)
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
        $hour = intval($matches[1]);
        $minute = intval($matches[2]);
        if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
            return sprintf('%02d:%02d', $hour, $minute);
        }
    }
    
    // Parse AM/PM format: "8:00 AM", "8:05:00 AM", "8 AM", "800 AM", "12:30 PM"
    if (preg_match('/^(\d{1,2}):?(\d{2})?(?::(\d{2}))?\s*(AM|PM)$/i', $value, $matches)) {
        $hour = intval($matches[1]);
        $minute = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : 0;
        $period = strtoupper($matches[4]);
        
        if ($period === 'PM' && $hour !== 12) {
            $hour += 12;
        } elseif ($period === 'AM' && $hour === 12) {
            $hour = 0;
        }
        
        return sprintf('%02d:%02d', $hour, $minute);
    }
    
    // Handle date+time string like "1/0/1900 8:00:00 AM" or "2/26/2026 8:00 AM"
    if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)/i', $value, $matches)) {
        $hour = intval($matches[1]);
        $minute = intval($matches[2]);
        $period = strtoupper($matches[4]);
        
        if ($period === 'PM' && $hour !== 12) {
            $hour += 12;
        } elseif ($period === 'AM' && $hour === 12) {
            $hour = 0;
        }
        
        return sprintf('%02d:%02d', $hour, $minute);
    }
    
    // Handle "HH:MM:SS" embedded in longer string (e.g., date-time without AM/PM)
    if (preg_match('/(\d{1,2}):(\d{2}):(\d{2})/', $value, $matches)) {
        $hour = intval($matches[1]);
        if ($hour >= 0 && $hour <= 23) {
            return sprintf('%02d:%02d', $hour, intval($matches[2]));
        }
    }
    
    // Handle "HH:MM" embedded in longer string (e.g., "Date 08:00")
    if (preg_match('/\b(\d{1,2}):(\d{2})\b/', $value, $matches)) {
        $hour = intval($matches[1]);
        $minute = intval($matches[2]);
        if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
            return sprintf('%02d:%02d', $hour, $minute);
        }
    }
    
    // Handle dot-separated time like "8.00" or "8.30"
    if (preg_match('/^(\d{1,2})\.(\d{2})$/', $value, $matches)) {
        $hour = intval($matches[1]);
        $minute = intval($matches[2]);
        if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
            return sprintf('%02d:%02d', $hour, $minute);
        }
    }
    
    // Handle just hour number as string like "8" or "17"
    if (preg_match('/^(\d{1,2})$/', $value, $matches)) {
        $hour = intval($matches[1]);
        if ($hour >= 1 && $hour <= 23) {
            return sprintf('%02d:00', $hour);
        }
    }
    
    // Try strtotime as fallback
    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        $result = date('H:i', $timestamp);
        // Avoid returning 00:00 from strtotime when the input doesn't look like midnight
        if ($result !== '00:00' || stripos($value, '12') !== false || stripos($value, 'midnight') !== false) {
            return $result;
        }
    }
    
    return null;
}
