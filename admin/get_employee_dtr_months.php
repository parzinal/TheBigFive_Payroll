<?php
/**
 * Get Employee DTR Months
 * Returns available months with DTR records for an employee
 */

require_once '../config/bootstrap.php';
require_once '../config/auth.php';
header('Content-Type: application/json');

require_once '../config/database.php';

// Allow admin and staff roles
if (!isAuthenticated() || (!isAdmin() && !isStaff())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$employeeId = intval($_GET['employee_id'] ?? 0);

if ($employeeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Group by payroll_period_id so one import = one entry (not split by calendar month)
    // Handle NULL payroll_period_id by using date-based grouping as fallback
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(d.payroll_period_id, 0) as period_id,
            CASE
                WHEN p.id IS NOT NULL THEN CONCAT(
                    DATE_FORMAT(p.start_date, '%b %d'), ' - ',
                    DATE_FORMAT(p.end_date, '%b %d, %Y')
                )
                ELSE DATE_FORMAT(MIN(d.dtr_date), '%M %Y')
            END as month_name,
            COUNT(*) as record_count,
            MIN(d.dtr_date) as first_date,
            MAX(d.dtr_date) as last_date
        FROM dtr_records d
        LEFT JOIN payroll_periods p ON d.payroll_period_id = p.id
        WHERE d.employee_id = ?
        GROUP BY COALESCE(d.payroll_period_id, 0), p.id, p.start_date, p.end_date
        ORDER BY MAX(d.dtr_date) DESC
    ");
    $stmt->execute([$employeeId]);
    
    $months = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'months' => $months
    ]);
    
} catch (Exception $e) {
    error_log("Get DTR Months Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
