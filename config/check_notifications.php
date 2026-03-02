<?php
/**
 * Notification System Diagnostic
 * Check if everything is set up correctly
 */

// Allow access without login for troubleshooting
require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/database.php';

$results = [];
$allGood = true;

// 1. Check database connection
try {
    $pdo = getDBConnection();
    $results['database'] = ['status' => 'success', 'message' => 'Database connection successful'];
} catch (Exception $e) {
    $results['database'] = ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
    $allGood = false;
}

// 2. Check if notifications table exists
try {
    $pdo = getDBConnection();
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'notifications'");
    if ($tableCheck->rowCount() > 0) {
        // Check table structure
        $columns = $pdo->query("DESCRIBE notifications")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        $requiredColumns = ['id', 'user_id', 'title', 'message', 'type', 'icon', 'link', 'is_read', 'created_at', 'read_at'];
        $missingColumns = array_diff($requiredColumns, $columnNames);
        
        if (empty($missingColumns)) {
            $results['table'] = ['status' => 'success', 'message' => 'Notifications table exists with correct structure'];
        } else {
            $results['table'] = ['status' => 'warning', 'message' => 'Table exists but missing columns: ' . implode(', ', $missingColumns)];
            $allGood = false;
        }
    } else {
        $results['table'] = ['status' => 'error', 'message' => 'Notifications table does NOT exist. Run setup_notifications.php'];
        $allGood = false;
    }
} catch (Exception $e) {
    $results['table'] = ['status' => 'error', 'message' => 'Error checking table: ' . $e->getMessage()];
    $allGood = false;
}

// 3. Check if API files exist
$apiFiles = [
    'admin' => __DIR__ . '/../admin/notifications_api.php',
    'staff' => __DIR__ . '/../staff/notifications_api.php',
    'user' => __DIR__ . '/../user/notifications_api.php'
];

$missingApiFiles = [];
foreach ($apiFiles as $role => $file) {
    if (!file_exists($file)) {
        $missingApiFiles[] = "$role/notifications_api.php";
    }
}

if (empty($missingApiFiles)) {
    $results['api_files'] = ['status' => 'success', 'message' => 'All API files exist'];
} else {
    $results['api_files'] = ['status' => 'error', 'message' => 'Missing API files: ' . implode(', ', $missingApiFiles)];
    $allGood = false;
}

// 4. Check if helper file exists
$helperFile = __DIR__ . '/notifications_helper.php';
if (file_exists($helperFile)) {
    $results['helper'] = ['status' => 'success', 'message' => 'Helper file exists'];
} else {
    $results['helper'] = ['status' => 'warning', 'message' => 'Helper file not found (optional)'];
}

// 5. Test API endpoint (if logged in)
if (isset($_SESSION['user_id'])) {
    try {
        // Determine role
        $role = $_SESSION['role'] ?? 'admin';
        $apiUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/../$role/notifications_api.php?action=get_count";
        
        $results['api_test'] = ['status' => 'info', 'message' => 'Testing API at: ' . $apiUrl];
    } catch (Exception $e) {
        $results['api_test'] = ['status' => 'warning', 'message' => 'Could not test API: ' . $e->getMessage()];
    }
} else {
    $results['api_test'] = ['status' => 'info', 'message' => 'Not logged in - cannot test API endpoint'];
}

// 6. Check notifications count
if ($results['table']['status'] === 'success') {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $results['data'] = ['status' => 'success', 'message' => "Database has $count notification(s)"];
    } catch (Exception $e) {
        $results['data'] = ['status' => 'error', 'message' => 'Error counting notifications: ' . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification System Diagnostic</title>
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
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        
        .overall-status {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .overall-status.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .overall-status.error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .check-item {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            align-items: start;
            gap: 12px;
        }
        
        .check-item.success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
        }
        
        .check-item.error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
        }
        
        .check-item.warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
        }
        
        .check-item.info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
        }
        
        .check-icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .check-content {
            flex: 1;
        }
        
        .check-title {
            font-weight: 600;
            margin-bottom: 4px;
            color: #333;
        }
        
        .check-message {
            font-size: 14px;
            color: #555;
        }
        
        .actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin-right: 12px;
            margin-bottom: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Notification System Diagnostic</h1>
        <p class="subtitle">Checking your notification system setup...</p>
        
        <div class="overall-status <?php echo $allGood ? 'success' : 'error'; ?>">
            <?php if ($allGood): ?>
                ✅ All checks passed! Your notification system is ready to use.
            <?php else: ?>
                ⚠️ Some issues detected. Please review the details below.
            <?php endif; ?>
        </div>
        
        <?php foreach ($results as $key => $result): ?>
            <div class="check-item <?php echo $result['status']; ?>">
                <div class="check-icon">
                    <?php
                    switch ($result['status']) {
                        case 'success': echo '✅'; break;
                        case 'error': echo '❌'; break;
                        case 'warning': echo '⚠️'; break;
                        case 'info': echo 'ℹ️'; break;
                    }
                    ?>
                </div>
                <div class="check-content">
                    <div class="check-title"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></div>
                    <div class="check-message"><?php echo $result['message']; ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="actions">
            <h3 style="margin-bottom: 16px; color: #333;">Quick Actions</h3>
            
            <?php if ($results['table']['status'] !== 'success'): ?>
                <a href="setup_notifications.php" class="btn btn-primary">🔧 Run Setup Script</a>
            <?php endif; ?>
            
            <a href="test_notifications.php" class="btn btn-primary">🧪 Test Notifications</a>
            <a href="../admin/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            
            <?php if ($results['table']['status'] !== 'success'): ?>
                <div style="margin-top: 20px; padding: 16px; background: #f3f4f6; border-radius: 8px;">
                    <h4 style="margin-bottom: 8px; color: #333;">Manual Setup:</h4>
                    <p style="font-size: 14px; color: #555; margin-bottom: 8px;">
                        If the setup script doesn't work, import the SQL file manually:
                    </p>
                    <ol style="margin-left: 20px; font-size: 14px; color: #555; line-height: 1.8;">
                        <li>Open phpMyAdmin</li>
                        <li>Select your database</li>
                        <li>Go to "Import" tab</li>
                        <li>Choose file: <code>config/sql/notifications.sql</code></li>
                        <li>Click "Go"</li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
