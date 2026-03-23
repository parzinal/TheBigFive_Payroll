<?php
// Delete user endpoint — POST + CSRF required (C2 fix)
require_once '../config/bootstrap.php';

require_once '../config/database.php';
require_once '../config/account_logs_helper.php';
require_once '../config/auth.php';
require_once '../config/csrf.php';

header('Content-Type: application/json');

// Only admins may delete users
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Validate CSRF
requireCSRFToken();

// Accept JSON body or form data
$input = json_decode(file_get_contents('php://input'), true);
$userId = intval($input['id'] ?? ($_POST['id'] ?? 0));

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user id']);
    exit;
}

// Prevent deleting self
if ($userId == ($_SESSION['user_id'] ?? 0)) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Fetch user for logging
    $stmt = $pdo->prepare('SELECT username, full_name, role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Prevent deleting the last admin
    if ($user['role'] === 'admin') {
        $c = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($c <= 1) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete the last administrator']);
            exit;
        }
    }

    // Delete user
    $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $ok = $del->execute([$userId]);

    if ($ok) {
        logDeleteAction(
            $_SESSION['user_id'],
            $_SESSION['username'] ?? '',
            'User',
            "{$user['full_name']} ({$user['username']})",
            $pdo
        );
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
