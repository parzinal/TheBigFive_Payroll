<?php
/**
 * Setup Notifications Table
 * Run this file once to create the notifications table in your database
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

// H2: Only admins may run setup scripts
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(403);
    die('Access Denied. Admin authentication required.');
}

require_once __DIR__ . '/database.php';

try {
    $pdo = getDBConnection();
    
    // Check if notifications table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'notifications'");
    
    if ($tableCheck->rowCount() > 0) {
        echo "<h2>✅ Notifications table already exists!</h2>";
        echo "<p>No action needed. The notification system is ready to use.</p>";
        echo "<p><a href='test_notifications.php'>Test Notifications</a></p>";
    } else {
        // Create notifications table
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
            icon VARCHAR(50) DEFAULT 'fa-bell',
            link VARCHAR(255) DEFAULT '',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL DEFAULT NULL,
            
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_created (created_at DESC),
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        
        echo "<h2>✅ Success!</h2>";
        echo "<p>Notifications table has been created successfully.</p>";
        
        // Create sample notifications for testing
        echo "<h3>Creating sample notifications...</h3>";
        
        // Get first user for testing
        $stmt = $pdo->query("SELECT id, full_name FROM users LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $sampleNotifications = [
                [
                    'title' => 'Welcome to TheBigFive Payroll',
                    'message' => 'Your notification system is now active and ready to use!',
                    'type' => 'success',
                    'icon' => 'fa-check-circle'
                ],
                [
                    'title' => 'System Notification',
                    'message' => 'This is a test notification to verify the system is working.',
                    'type' => 'info',
                    'icon' => 'fa-info-circle'
                ]
            ];
            
            $insertStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, icon, is_read) VALUES (?, ?, ?, ?, ?, 0)");
            
            foreach ($sampleNotifications as $notif) {
                $insertStmt->execute([
                    $user['id'],
                    $notif['title'],
                    $notif['message'],
                    $notif['type'],
                    $notif['icon']
                ]);
            }
            
            echo "<p>✅ Created 2 sample notifications for user: " . htmlspecialchars($user['full_name']) . "</p>";
        }
        
        echo "<h3>Next Steps:</h3>";
        echo "<ul>";
        echo "<li>Login to your account</li>";
        echo "<li>Check the notification bell icon in the header</li>";
        echo "<li>Click to view your notifications</li>";
        echo "<li>Use <a href='test_notifications.php'>Test Notifications</a> to create more</li>";
        echo "</ul>";
    }
    
} catch (PDOException $e) {
    echo "<h2>❌ Error</h2>";
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Check your database connection in config/database.php</li>";
    echo "<li>Make sure the 'users' table exists</li>";
    echo "<li>Verify your database user has CREATE TABLE permissions</li>";
    echo "</ul>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Notifications - TheBigFive Payroll</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h2 {
            color: #333;
            margin-bottom: 16px;
            font-size: 28px;
        }
        
        h3 {
            color: #666;
            margin: 24px 0 12px 0;
            font-size: 18px;
        }
        
        p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        
        ul {
            margin-left: 24px;
            color: #555;
            line-height: 1.8;
        }
        
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 24px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- PHP output appears here -->
        <a href="../admin/dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>
</html>
