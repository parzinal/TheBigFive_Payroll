<?php
/**
 * Database Migration: Add is_training column to dtr_records
 * Run this file once to update the database schema
 * Access via browser: http://yourdomain.com/admin/migrate_training_column.php
 */

require_once '../config/bootstrap.php';
require_once '../config/database.php';
require_once '../config/auth.php';

// Require admin authentication
if (!isAuthenticated() || !isAdmin()) {
    die('Error: Admin authentication required');
}

// Set headers
header('Content-Type: text/html; charset=UTF-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Add Training Column</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 50px auto; 
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #2c3e50; 
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-weight: bold;
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .info { 
            background: #d1ecf1; 
            color: #0c5460; 
            border: 1px solid #bee5eb;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #ddd;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover { background: #2980b9; }
        code { 
            background: #f8f9fa; 
            padding: 2px 6px; 
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Migration: Add Training Column</h1>
        
        <div class="info">
            <strong>Migration Purpose:</strong> Add <code>is_training</code> column to <code>dtr_records</code> table to fix the training checkbox persistence issue.
        </div>

<?php
try {
    $pdo = getDBConnection();
    
    // Check if column already exists
    $checkStmt = $pdo->query("
        SELECT COUNT(*) as col_exists 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = 'thebigfive_payroll' 
        AND TABLE_NAME = 'dtr_records' 
        AND COLUMN_NAME = 'is_training'
    ");
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['col_exists'] > 0) {
        echo '<div class="warning">';
        echo '<strong>⚠️ Column Already Exists</strong><br>';
        echo 'The <code>is_training</code> column already exists in the <code>dtr_records</code> table.<br>';
        echo 'No migration needed. Your database is already up to date!';
        echo '</div>';
    } else {
        echo '<div class="info">';
        echo '<strong>📋 Running Migration...</strong><br>';
        echo 'Adding <code>is_training</code> column to <code>dtr_records</code> table...';
        echo '</div>';
        
        // Run the migration
        $pdo->exec("
            ALTER TABLE dtr_records 
            ADD COLUMN is_training BOOLEAN DEFAULT FALSE 
            COMMENT 'Whether this day is a training day'
            AFTER is_absent
        ");
        
        echo '<div class="success">';
        echo '<strong>✅ Migration Successful!</strong><br>';
        echo 'The <code>is_training</code> column has been added successfully.<br>';
        echo '<br><strong>Changes made:</strong><br>';
        echo '• Added column: <code>is_training</code><br>';
        echo '• Type: BOOLEAN (TINYINT(1))<br>';
        echo '• Default value: FALSE (0)<br>';
        echo '• Position: After <code>is_absent</code> column<br>';
        echo '</div>';
        
        // Verify the column was added
        $verifyStmt = $pdo->query("SHOW COLUMNS FROM dtr_records LIKE 'is_training'");
        $column = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($column) {
            echo '<div class="info">';
            echo '<strong>✓ Verification Passed</strong><br>';
            echo 'Column details:<br>';
            echo '<pre>' . print_r($column, true) . '</pre>';
            echo '</div>';
        }
    }
    
    // Show current table structure
    echo '<h2>📊 Current Table Structure</h2>';
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM dtr_records");
    $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<pre>';
    echo "dtr_records table columns:\n";
    echo str_repeat('-', 80) . "\n";
    printf("%-30s %-20s %-10s %-10s\n", "Field", "Type", "Null", "Default");
    echo str_repeat('-', 80) . "\n";
    foreach ($columns as $col) {
        printf(
            "%-30s %-20s %-10s %-10s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'], 
            $col['Default'] ?? 'NULL'
        );
    }
    echo '</pre>';
    
    // Show record count
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM dtr_records");
    $count = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    echo '<div class="info">';
    echo '<strong>📈 Database Statistics</strong><br>';
    echo "Total DTR records: <strong>{$count['total']}</strong><br>";
    echo "All existing records now have <code>is_training = 0</code> (not training) by default.";
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="error">';
    echo '<strong>❌ Migration Failed</strong><br>';
    echo 'Error: ' . htmlspecialchars($e->getMessage());
    echo '</div>';
    
    echo '<h3>Troubleshooting</h3>';
    echo '<ul>';
    echo '<li>Check database connection settings in <code>.env</code></li>';
    echo '<li>Ensure the database user has ALTER TABLE permissions</li>';
    echo '<li>Verify the database name is correct: <code>thebigfive_payroll</code></li>';
    echo '</ul>';
}
?>
        
        <h2>📝 What's Next?</h2>
        <ol>
            <li><strong>Test the fix:</strong> Open the DTR calculator and check/uncheck training checkboxes</li>
            <li><strong>Save DTR:</strong> Save a payroll with training days marked</li>
            <li><strong>Reload:</strong> Reload the same employee and verify training checkboxes are still checked</li>
            <li><strong>Verify in Payroll List:</strong> Check that training data displays correctly in the payroll list</li>
        </ol>
        
        <div class="warning">
            <strong>🗑️ Security Note:</strong> After successful migration, you can delete this file (<code>migrate_training_column.php</code>) to prevent unauthorized access.
        </div>
        
        <a href="dashboard.php" class="btn">← Back to Dashboard</a>
        <a href="Generatepayroll.php" class="btn">Test DTR Calculator →</a>
    </div>
</body>
</html>
