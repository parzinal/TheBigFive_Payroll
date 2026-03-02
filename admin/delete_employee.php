<?php
// AJAX endpoint to delete an employee
require_once '../config/bootstrap.php';

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/account_logs_helper.php';
require_once '../config/csrf.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// CSRF check
requireCSRFToken();

$employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee id']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Fetch employee details for logging
    $stmt = $pdo->prepare('SELECT full_name, employee_code FROM employees WHERE id = ?');
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch();
    if (!$emp) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }

    // Delete employee
    $del = $pdo->prepare('DELETE FROM employees WHERE id = ?');
    $ok = $del->execute([$employee_id]);

    if ($ok) {
        // Log deletion
        logDeleteAction(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'Employee',
            "{$emp['full_name']} ({$emp['employee_code']})",
            $pdo
        );

        echo json_encode(['success' => true, 'message' => 'Employee deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete employee']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

exit;
