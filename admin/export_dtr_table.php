<?php
/**
 * Export DTR Records to Excel (Simple Table)
 * Produces a straightforward tabular spreadsheet with one row per DTR date.
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

require_once '../config/bootstrap.php';
require_once '../config/auth.php';

// Require admin or staff
if (!isAuthenticated() || (!isAdmin() && !isStaff())) {
    http_response_code(403);
    die('Unauthorized');
}

require_once '../config/database.php';

$employeeId = intval($_GET['employee_id'] ?? 0);
$periodId   = intval($_GET['period_id'] ?? 0);

if ($employeeId <= 0) die('Invalid employee ID');

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, full_name, employee_code FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) die('Employee not found');

    if ($periodId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM dtr_records WHERE employee_id = ? AND payroll_period_id = ? ORDER BY dtr_date ASC");
        $stmt->execute([$employeeId, $periodId]);
        $periodStmt = $pdo->prepare("SELECT start_date, end_date, period_name FROM payroll_periods WHERE id = ? LIMIT 1");
        $periodStmt->execute([$periodId]);
        $periodInfo = $periodStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM dtr_records WHERE employee_id = ? ORDER BY dtr_date ASC");
        $stmt->execute([$employeeId]);
        $periodInfo = null;
    }
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($records)) die('No DTR records found');

} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

$phpSpreadsheetPath = '../vendor/autoload.php';
if (!file_exists($phpSpreadsheetPath)) die('PhpSpreadsheet not installed');
require_once $phpSpreadsheetPath;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('DTR Table');

// Header row
$headers = [
    'Date','AM In','AM Out','PM In','PM Out','OT In','OT Out','Absent','Work Hours','Late (mins)','Undertime (hrs)','OT Hours','Remarks','Govt Deduct','Net Salary'
];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '1', $h);
    $col++;
}

$row = 2;
foreach ($records as $r) {
    $sheet->setCellValue('A' . $row, $r['dtr_date']);
    $sheet->setCellValue('B' . $row, $r['am_time_in'] ?? '');
    $sheet->setCellValue('C' . $row, $r['am_time_out'] ?? '');
    $sheet->setCellValue('D' . $row, $r['pm_time_in'] ?? '');
    $sheet->setCellValue('E' . $row, $r['pm_time_out'] ?? '');
    $sheet->setCellValue('F' . $row, $r['ot_time_in'] ?? '');
    $sheet->setCellValue('G' . $row, $r['ot_time_out'] ?? '');
    $sheet->setCellValue('H' . $row, ($r['is_absent'] ?? 0) ? 'Yes' : '');
    $sheet->setCellValue('I' . $row, $r['total_work_hours'] ?? 0);
    $sheet->setCellValue('J' . $row, $r['late_minutes'] ?? 0);
    $sheet->setCellValue('K' . $row, $r['undertime_hours'] ?? 0);
    $sheet->setCellValue('L' . $row, $r['daily_ot_hours'] ?? 0);
    $sheet->setCellValue('M' . $row, $r['remarks'] ?? '');
    $sheet->setCellValue('N' . $row, $r['govt_deduct'] ?? 0);
    $sheet->setCellValue('O' . $row, $r['net_salary'] ?? 0);
    $row++;
}

// Auto-size columns
foreach (range('A', 'O') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);

$empSafe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $employee['full_name']);
$fileName = 'DTR_Table_' . $empSafe;
if ($periodInfo) {
    $fileName .= '_' . date('Ymd', strtotime($periodInfo['start_date'])) . '_' . date('Ymd', strtotime($periodInfo['end_date']));
}
$fileName .= '.xlsx';

$writer = new Xlsx($spreadsheet);
$temp = tempnam(sys_get_temp_dir(), 'dtr_tbl_') . '.xlsx';
$writer->save($temp);

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($temp));
readfile($temp);
@unlink($temp);
exit();
