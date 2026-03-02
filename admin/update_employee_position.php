<?php
/**
 * Update Employee Position
 * Adds or removes an employee from a position
 */

require_once '../config/bootstrap.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once '../config/database.php';

// Include account logs helper
require_once '../config/account_logs_helper.php';

require_once '../config/csrf.php';

header('Content-Type: application/json');

// CSRF check
requireCSRFToken();

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['employee_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Employee ID is required'
        ]);
        exit;
    }
    
    $employeeId = $data['employee_id'];
    $position = $data['position'] ?? null; // null means remove from position
    
    $pdo = getDBConnection();
    
    // First check if employee exists
    $checkStmt = $pdo->prepare("SELECT id, position FROM employees WHERE id = ?");
    $checkStmt->execute([$employeeId]);
    $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found'
        ]);
        exit;
    }
    
    // Update employee position
    $stmt = $pdo->prepare("UPDATE employees SET position = ? WHERE id = ?");
    $stmt->execute([$position, $employeeId]);
    
    // Log position update
    $positionChange = $employee['position'] . " → " . ($position ?? 'None');
    logUpdateAction(
        $_SESSION['user_id'],
        $_SESSION['username'],
        'Employee Position',
        "Employee ID: $employeeId",
        "Position changed: $positionChange",
        $pdo
    );
    
    $action = $position === null ? 'removed from position' : 'assigned to position';
    echo json_encode([
        'success' => true,
        'message' => "Employee successfully $action",
        'employee_id' => $employeeId,
        'position' => $position,
        'previous_position' => $employee['position']
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
