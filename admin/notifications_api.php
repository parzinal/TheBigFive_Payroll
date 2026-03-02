<?php
/**
 * Notifications API
 * Handles notification operations: fetch, mark as read, delete
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';

// Require admin authentication
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// CSRF check for POST requests
requireCSRFToken();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'fetch':
            // Fetch notifications
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] == 'true';
            
            $sql = "SELECT id, title, message, type, icon, link, is_read, created_at 
                    FROM notifications 
                    WHERE user_id = ?";
            
            if ($unread_only) {
                $sql .= " AND is_read = 0";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $limit]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get unread count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $countStmt->execute([$user_id]);
            $unread_count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]);
            break;
            
        case 'mark_read':
            // Mark single notification as read
            $notification_id = $_POST['notification_id'] ?? null;
            
            if (!$notification_id) {
                echo json_encode(['success' => false, 'message' => 'Notification ID required']);
                exit();
            }
            
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() 
                                   WHERE id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            break;
            
        case 'mark_all_read':
            // Mark all notifications as read
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() 
                                   WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
            break;
            
        case 'delete_all_read':
            // Delete all read notifications (only delete notifications with is_read = 1)
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
            $stmt->execute([$user_id]);
            
            echo json_encode(['success' => true, 'message' => 'All read notifications deleted']);
            break;
            
        case 'delete':
            // Delete notification
            $notification_id = $_POST['notification_id'] ?? null;
            
            if (!$notification_id) {
                echo json_encode(['success' => false, 'message' => 'Notification ID required']);
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Notification deleted']);
            break;
            
        case 'get_count':
            // Get unread notification count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'count' => $count]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
