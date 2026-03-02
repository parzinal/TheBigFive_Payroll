<?php
/**
 * Save Position Assignments
 * Assigns positions to multiple employees
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
    
    if (!isset($data['assignments']) || !is_array($data['assignments'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid data format'
        ]);
        exit;
    }
    
    $assignments = $data['assignments'];
    
    if (empty($assignments)) {
        echo json_encode([
            'success' => false,
            'message' => 'No assignments provided'
        ]);
        exit;
    }
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE employees SET position = ? WHERE id = ?");
    $count = 0;
    
    foreach ($assignments as $assignment) {
        if (isset($assignment['employee_id']) && isset($assignment['position'])) {
            $stmt->execute([
                $assignment['position'],
                $assignment['employee_id']
            ]);
            $count++;
        }
    }
    
    $pdo->commit();
    
    // Log bulk position assignments
    logUpdateAction(
        $_SESSION['user_id'],
        $_SESSION['username'],
        'Position Assignments',
        "$count employee(s)",
        "Bulk position assignment",
        $pdo
    );
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully assigned positions to $count employee(s)",
        'count' => $count
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
