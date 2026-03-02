<?php
// Delete user endpoint (supports GET redirect from UI)
require_once '../config/bootstrap.php';

require_once '../config/database.php';
require_once '../config/account_logs_helper.php';

// Only admins may delete users
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: user_management.php?error=' . urlencode('Unauthorized'));
    exit;
}

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($userId <= 0) {
    header('Location: user_management.php?error=' . urlencode('Invalid user id'));
    exit;
}

// Prevent deleting self
if ($userId == ($_SESSION['user_id'] ?? 0)) {
    header('Location: user_management.php?error=' . urlencode('You cannot delete your own account'));
    exit;
}

try {
    $pdo = getDBConnection();

    // Fetch user for logging
    $stmt = $pdo->prepare('SELECT username, full_name, role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        header('Location: user_management.php?error=' . urlencode('User not found'));
        exit;
    }

    // Optionally prevent deleting other admins if needed (preserve at least one admin)
    if ($user['role'] === 'admin') {
        // Check count of admins
        $c = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($c <= 1) {
            header('Location: user_management.php?error=' . urlencode('Cannot delete the last administrator'));
            exit;
        }
    }

    // Delete user
    $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $ok = $del->execute([$userId]);

    if ($ok) {
        // Log deletion
        logDeleteAction(
            $_SESSION['user_id'],
            $_SESSION['username'] ?? '',
            'User',
            "{$user['full_name']} ({$user['username']})",
            $pdo
        );

        header('Location: user_management.php?success=' . urlencode('User deleted'));
        exit;
    } else {
        header('Location: user_management.php?error=' . urlencode('Failed to delete user'));
        exit;
    }

} catch (PDOException $e) {
    header('Location: user_management.php?error=' . urlencode('Database error: ' . $e->getMessage()));
    exit;
}

?>
