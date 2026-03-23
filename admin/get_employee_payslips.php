<?php
/**
 * Get Employee Payslips API
 * Returns all payslips for a specific employee
 */

require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// H1: Require admin or staff role
if (!isAuthenticated() || (!isAdmin() && !isStaff())) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get employee ID from request
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$period_start = isset($_GET['period_start']) ? $_GET['period_start'] : null;
$period_end = isset($_GET['period_end']) ? $_GET['period_end'] : null;

if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Build query with optional period filtering
    $sql = "
        SELECT 
            pc.id,
            pc.employee_id,
            e.employee_code,
            e.full_name as employee_name,
            e.position,
            e.department,
            pp.period_name,
            pp.start_date,
            pp.end_date,
            pp.pay_date,
            pc.basic_monthly_salary,
            pc.per_day_rate,
            pc.per_hour_rate,
            pc.total_work_days,
            pc.total_work_hours,
            pc.total_late_hours,
            pc.total_undertime_hours,
            pc.total_ot_hours,
            pc.total_absent_days,
            pc.basic_pay,
            pc.ot_pay,
            pc.late_deduction,
            pc.undertime_deduction,
            pc.absent_deduction,
            pc.cash_advance,
            pc.sss_contribution,
            pc.philhealth_contribution,
            pc.pagibig_contribution,
            pc.withholding_tax,
            pc.other_deductions,
            pc.total_earnings,
            pc.total_deductions,
            pc.net_pay,
            pc.trainings_cost,
            pc.training_amount,
            pc.status,
            pc.created_at,
            pc.computed_at,
            pc.approved_at,
            pc.paid_at,
            pc.other_deductions_notes
        FROM payroll_computations pc
        INNER JOIN employees e ON pc.employee_id = e.id
        INNER JOIN payroll_periods pp ON pc.payroll_period_id = pp.id
        WHERE pc.employee_id = :employee_id
        AND pc.status IN ('computed', 'approved', 'paid')
    ";
    
    $params = [':employee_id' => $employee_id];
    
    // Add period filtering if provided
    if ($period_start && $period_end) {
        $sql .= " AND pp.start_date = :period_start AND pp.end_date = :period_end";
        $params[':period_start'] = $period_start;
        $params[':period_end'] = $period_end;
    }
    
    $sql .= " ORDER BY pc.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'payslips' => $payslips,
        'count' => count($payslips)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
