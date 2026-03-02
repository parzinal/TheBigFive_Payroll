<?php
/**
 * Get Employees by Position - AJAX Endpoint
 */

// Start session via bootstrap (hardened settings)
require_once '../config/bootstrap.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Include database connection
require_once '../config/database.php';

// Get position from query parameter
$position = $_GET['position'] ?? '';

try {
    $pdo = getDBConnection();
    
    if ($position === '') {
        // Get employees with no position
        $stmt = $pdo->prepare("
            SELECT id, employee_code, full_name, department, basic_monthly_salary, status
            FROM employees 
            WHERE position IS NULL OR position = ''
            ORDER BY full_name ASC
        ");
        $stmt->execute();
    } else {
        // Get employees with specific position
        $stmt = $pdo->prepare("
            SELECT id, employee_code, full_name, department, basic_monthly_salary, status
            FROM employees 
            WHERE position = ?
            ORDER BY full_name ASC
        ");
        $stmt->execute([$position]);
    }
    
    $employees = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'employees' => $employees
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching employees: ' . $e->getMessage()
    ]);
}
?>
