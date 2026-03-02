<?php
/**
 * Update DTR Records API
 * Saves edited DTR records back to the database
 */

// MUST be first - suppress HTML errors before ANY includes
@ini_set('display_errors', 0);
@ini_set('html_errors', 0);
error_reporting(0);

// Start output buffering BEFORE any includes
ob_start();

// Set JSON header early
header('Content-Type: application/json');

// Custom exception handler
set_exception_handler(function($e) {
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
    exit;
});

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'PHP Error: ' . $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

// Now safe to include files
require_once '../config/database.php';
require_once '../config/auth.php';

// RE-disable error display AFTER bootstrap overrides (bootstrap may re-enable based on APP_DEBUG)
@ini_set('display_errors', 0);
@ini_set('html_errors', 0);
error_reporting(0);

// Initialize database connection
$pdo = getDBConnection();

// Check authentication (use isAuthenticated instead of isLoggedIn)
if (!isAuthenticated() || !isAdmin()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['employee_id']) || !isset($data['records']) || !is_array($data['records'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$employeeId = (int)$data['employee_id'];
$records = $data['records'];
$periodId = isset($data['period_id']) ? (int)$data['period_id'] : 0;

// Get rate fields
$basicSalary = isset($data['basic_salary']) ? (float)$data['basic_salary'] : null;
$perDay = isset($data['per_day']) ? (float)$data['per_day'] : null;
$lateStart = isset($data['late_start']) ? trim($data['late_start']) : null;
$endTime = isset($data['end_time']) ? trim($data['end_time']) : null;

if ($employeeId <= 0 || empty($records)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'No records to update']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update employee basic salary if provided
    if ($basicSalary !== null && $basicSalary > 0) {
        $updateSalaryStmt = $pdo->prepare("UPDATE employees SET basic_monthly_salary = ? WHERE id = ?");
        $updateSalaryStmt->execute([$basicSalary, $employeeId]);
    }
    
    // Update payroll computation settings if period_id is provided
    if ($periodId > 0 && ($lateStart || $endTime)) {
        // Update payroll computation (late_start and end_time columns confirmed in schema)
        $updateCompStmt = $pdo->prepare("
            UPDATE payroll_computations 
            SET late_start = COALESCE(?, late_start, '07:35'),
                end_time = COALESCE(?, end_time, '17:00')
            WHERE payroll_period_id = ? AND employee_id = ?
        ");
        $updateCompStmt->execute([$lateStart, $endTime, $periodId, $employeeId]);
    }
    
    $updateStmt = $pdo->prepare("
        UPDATE dtr_records SET
            am_time_in = :am_in,
            am_time_out = :am_out,
            pm_time_in = :pm_in,
            pm_time_out = :pm_out,
            ot_time_out = :ot_out,
            halfday_in = :half_in,
            halfday_out = :half_out,
            is_halfday = :is_halfday,
            is_absent = :is_absent,
            total_work_hours = :work_hrs,
            late_minutes = :late_mins,
            undertime_hours = :ut_hrs,
            daily_ot_hours = :ot_hrs,
            remarks = :remarks,
            updated_at = NOW()
        WHERE id = :id AND employee_id = :emp_id
    ");
    
    $updatedCount = 0;
    
    foreach ($records as $rec) {
        if (!isset($rec['id'])) continue;
        
        // Convert empty strings to null for time fields
        $amIn = !empty($rec['am_time_in']) ? $rec['am_time_in'] : null;
        $amOut = !empty($rec['am_time_out']) ? $rec['am_time_out'] : null;
        $pmIn = !empty($rec['pm_time_in']) ? $rec['pm_time_in'] : null;
        $pmOut = !empty($rec['pm_time_out']) ? $rec['pm_time_out'] : null;
        $otOut = !empty($rec['ot_time_out']) ? $rec['ot_time_out'] : null;
        $halfIn = !empty($rec['halfday_in']) ? $rec['halfday_in'] : null;
        $halfOut = !empty($rec['halfday_out']) ? $rec['halfday_out'] : null;
        
        // Determine is_halfday based on halfday times
        $isHalfday = ($halfIn && $halfOut) ? 1 : 0;
        
        $updateStmt->execute([
            ':id' => (int)$rec['id'],
            ':emp_id' => $employeeId,
            ':am_in' => $amIn,
            ':am_out' => $amOut,
            ':pm_in' => $pmIn,
            ':pm_out' => $pmOut,
            ':ot_out' => $otOut,
            ':half_in' => $halfIn,
            ':half_out' => $halfOut,
            ':is_halfday' => $isHalfday,
            ':is_absent' => isset($rec['is_absent']) ? (int)$rec['is_absent'] : 0,
            ':work_hrs' => isset($rec['total_work_hours']) ? (float)$rec['total_work_hours'] : 0,
            ':late_mins' => isset($rec['late_minutes']) ? (int)$rec['late_minutes'] : 0,
            ':ut_hrs' => isset($rec['undertime_hours']) ? (float)$rec['undertime_hours'] : 0,
            ':ot_hrs' => isset($rec['daily_ot_hours']) ? (float)$rec['daily_ot_hours'] : 0,
            ':remarks' => isset($rec['remarks']) ? trim($rec['remarks']) : ''
        ]);
        
        $updatedCount += $updateStmt->rowCount();
    }
    
    $pdo->commit();
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => "Updated {$updatedCount} record(s) successfully",
        'updated_count' => $updatedCount
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('DTR Update Error: ' . $e->getMessage());
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
