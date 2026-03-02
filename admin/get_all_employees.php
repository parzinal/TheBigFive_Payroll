<?php
/**
 * Get All Employees - AJAX Endpoint
 * Returns all employees from the database
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

try {
    $pdo = getDBConnection();
    
    // Get all employees
    $stmt = $pdo->query("
        SELECT 
            id, 
            employee_code, 
            full_name, 
            department, 
            position,
            basic_monthly_salary, 
            status
        FROM employees 
        ORDER BY full_name ASC
    ");
    
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
