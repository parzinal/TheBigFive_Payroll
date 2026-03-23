<?php
require_once '../config/auth.php';
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isAuthenticated() || (!isAdmin() && !isStaff())) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$employeeId = intval($_GET['employee_id'] ?? 0);
$periodId   = intval($_GET['period_id'] ?? 0);

if ($employeeId <= 0 || $periodId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id FROM payroll_computations WHERE employee_id = ? AND payroll_period_id = ? LIMIT 1');
    $stmt->execute([$employeeId, $periodId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'payslip_id' => intval($row['id'])]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payslip not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
