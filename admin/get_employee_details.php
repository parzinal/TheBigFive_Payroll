<?php
/**
 * Get Employee Details for Payroll
 * AJAX endpoint to fetch employee information
 */

require_once '../config/bootstrap.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Employee ID is required']);
    exit();
}

$employeeId = intval($_GET['id']);

try {
    $pdo = getDBConnection();
    
    // Get employee details with user information
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.employee_code,
            e.full_name,
            e.position,
            e.department,
            e.basic_monthly_salary,
            e.hire_date,
            u.email,
            u.phone,
            u.address
        FROM employees e
        LEFT JOIN users u ON e.user_id = u.id
        WHERE e.id = ? AND e.status = 'active'
    ");
    
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode(['error' => 'Employee not found']);
        exit();
    }
    
    // Calculate rates based on monthly salary
    $monthlySalary = floatval($employee['basic_monthly_salary']);
    $perDayRate = $monthlySalary / 26; // Assuming 26 working days per month
    $perHourRate = $perDayRate / 8; // Assuming 8 hours per day
    
    // Prepare response
    $response = [
        'id' => $employee['id'],
        'employee_code' => $employee['employee_code'],
        'full_name' => $employee['full_name'],
        'position' => $employee['position'] ?? 'Not specified',
        'department' => $employee['department'] ?? 'Not specified',
        'basic_monthly_salary' => number_format($monthlySalary, 2),
        'per_day_rate' => number_format($perDayRate, 2),
        'per_hour_rate' => number_format($perHourRate, 2),
        'hire_date' => $employee['hire_date'] ?? '',
        'email' => $employee['email'] ?? 'name@provider.com',
        'phone' => $employee['phone'] ?? '+44 00 0000 0000',
        'address' => $employee['address'] ?? '123 Any Court Road, London W1T 1JY, UK'
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error fetching employee details: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}
?>
