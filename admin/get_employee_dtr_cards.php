<?php
/**
 * Get Employee DTR Cards
 * Returns all employees with their DTR record counts
 */

require_once '../config/bootstrap.php';
require_once '../config/auth.php';
header('Content-Type: application/json');

require_once '../config/database.php';

// H1: Require admin role
if (!isAuthenticated() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get all employees with DTR record counts
    $stmt = $pdo->query("
        SELECT 
            e.id,
            e.employee_code,
            e.full_name,
            e.position,
            e.department,
            e.basic_monthly_salary,
            e.status,
            COUNT(d.id) as dtr_count,
            MAX(d.dtr_date) as last_dtr_date
        FROM employees e
        LEFT JOIN dtr_records d ON e.id = d.employee_id
        WHERE e.status = 'active'
        GROUP BY e.id, e.employee_code, e.full_name, e.position, e.department, e.basic_monthly_salary, e.status
        HAVING dtr_count > 0
        ORDER BY e.full_name ASC
    ");
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'employees' => $employees
    ]);
    
} catch (Exception $e) {
    error_log("Get Employee Cards Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
