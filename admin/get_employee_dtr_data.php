<?php
/**
 * Get Employee DTR Data
 * Returns DTR records for a specific employee and month
 */

require_once '../config/bootstrap.php';
header('Content-Type: application/json');

require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$employeeId = intval($_GET['employee_id'] ?? 0);
$periodId   = intval($_GET['period_id'] ?? 0);   // preferred: group by period
$month      = $_GET['month'] ?? '';               // fallback: YYYY-MM

if ($employeeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit();
}

if ($periodId <= 0 && (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month))) {
    echo json_encode(['success' => false, 'message' => 'Invalid period or month']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get employee info
    $stmt = $pdo->prepare("SELECT id, employee_code, full_name, position, department, basic_monthly_salary FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit();
    }
    
    // Get DTR records for the period (include govt_deduct and net_salary)
    if ($periodId > 0) {
        // Group all records belonging to the same import/payroll period
        $stmt = $pdo->prepare("
            SELECT 
                d.id,
                d.dtr_date,
                TIME_FORMAT(d.am_time_in, '%H:%i') as am_time_in,
                TIME_FORMAT(d.am_time_out, '%H:%i') as am_time_out,
                TIME_FORMAT(d.pm_time_in, '%H:%i') as pm_time_in,
                TIME_FORMAT(d.pm_time_out, '%H:%i') as pm_time_out,
                TIME_FORMAT(d.ot_time_out, '%H:%i') as ot_time_out,
                TIME_FORMAT(d.halfday_in, '%H:%i') as halfday_in,
                TIME_FORMAT(d.halfday_out, '%H:%i') as halfday_out,
                d.is_halfday,
                d.is_absent,
                d.total_work_hours,
                d.late_minutes,
                d.late_hours,
                d.undertime_minutes,
                d.undertime_hours,
                d.daily_ot_hours,
                d.govt_deduct,
                d.net_salary,
                d.remarks,
                d.payroll_period_id
            FROM dtr_records d
            WHERE d.employee_id = ? AND d.payroll_period_id = ?
            ORDER BY d.dtr_date ASC
        ");
        $stmt->execute([$employeeId, $periodId]);
    } else {
        // Fallback: filter by calendar month using date range (index-friendly, avoids DATE_FORMAT on column)
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month'));
        $stmt = $pdo->prepare("
            SELECT 
                d.id,
                d.dtr_date,
                TIME_FORMAT(d.am_time_in, '%H:%i') as am_time_in,
                TIME_FORMAT(d.am_time_out, '%H:%i') as am_time_out,
                TIME_FORMAT(d.pm_time_in, '%H:%i') as pm_time_in,
                TIME_FORMAT(d.pm_time_out, '%H:%i') as pm_time_out,
                TIME_FORMAT(d.ot_time_out, '%H:%i') as ot_time_out,
                TIME_FORMAT(d.halfday_in, '%H:%i') as halfday_in,
                TIME_FORMAT(d.halfday_out, '%H:%i') as halfday_out,
                d.is_halfday,
                d.is_absent,
                d.total_work_hours,
                d.late_minutes,
                d.late_hours,
                d.undertime_minutes,
                d.undertime_hours,
                d.daily_ot_hours,
                d.govt_deduct,
                d.net_salary,
                d.remarks,
                d.payroll_period_id
            FROM dtr_records d
            WHERE d.employee_id = ? AND d.dtr_date >= ? AND d.dtr_date < ?
            ORDER BY d.dtr_date ASC
        ");
        $stmt->execute([$employeeId, $monthStart, $monthEnd]);
    }
    
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get the payroll computation for this period (if exists)
    $payrollComp = null;
    $activePeriodId = $periodId > 0 ? $periodId : ((!empty($records) && !empty($records[0]['payroll_period_id'])) ? $records[0]['payroll_period_id'] : null);
    if ($activePeriodId) {
        $stmtComp = $pdo->prepare("
            SELECT * FROM payroll_computations 
            WHERE employee_id = ? AND payroll_period_id = ?
            LIMIT 1
        ");
        $stmtComp->execute([$employeeId, $activePeriodId]);
        $payrollComp = $stmtComp->fetch(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'employee_info' => $employee,
        'records' => $records,
        'payroll_computation' => $payrollComp,
        'month' => $month,
        'period_id' => $activePeriodId
    ]);
    
} catch (Exception $e) {
    error_log("Get DTR Data Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
