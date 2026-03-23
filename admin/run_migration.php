<?php
/**
 * Database Migration: Add Training Columns and Payroll List DTR Columns
 * Run this once to add trainings_count, trainings_cost, days_office, training_amount, training_remarks, late_start, end_time columns
 */

require_once '../config/bootstrap.php';
require_once '../config/auth.php';
require_once '../config/database.php';

// C3: Only admins may run migrations
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(403);
    die('<h2 style="color:red;">Access Denied</h2><p>Admin authentication required.</p><a href="../login.php">Login</a>');
}

echo "<h2>Running Database Migration: Add Training & Payroll List Columns</h2>\n";

try {
    $pdo = getDBConnection();
    
    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE payroll_computations");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $migrations = [];
    
    if (!in_array('trainings_count', $columns)) {
        $migrations[] = "ALTER TABLE payroll_computations ADD COLUMN trainings_count INT DEFAULT 0";
    }
    
    if (!in_array('trainings_cost', $columns)) {
        $migrations[] = "ALTER TABLE payroll_computations ADD COLUMN trainings_cost DECIMAL(10, 2) DEFAULT 0.00";
    }
    
    if (!in_array('days_office', $columns)) {
        $migrations[] = "ALTER TABLE payroll_computations ADD COLUMN days_office INT DEFAULT 0";
    }
    
    if (!in_array('late_start', $columns)) {
        $migrations[] = "ALTER TABLE payroll_computations ADD COLUMN late_start TIME DEFAULT '07:35' COMMENT 'Late start time threshold'";
    }
    
    if (!in_array('end_time', $columns)) {
        $migrations[] = "ALTER TABLE payroll_computations ADD COLUMN end_time TIME DEFAULT '17:00' COMMENT 'End time for work day'";
    }
    
    if (!in_array('training_amount', $columns)) {
        $migrations[] = "ALTER TABLE payroll_computations ADD COLUMN training_amount DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Training payment amount'";
    }
    
    if (!in_array('training_remarks', $columns)) {
        $migrations[] = "ALTER TABLE payroll_computations ADD COLUMN training_remarks TEXT NULL COMMENT 'Training payment remarks'";
    }
    
    if (empty($migrations)) {
        echo "<p style='color:green;'>All columns already exist. No migration needed.</p>\n";
    } else {
        foreach ($migrations as $sql) {
            echo "<p>Running: $sql</p>\n";
            $pdo->exec($sql);
            echo "<p style='color:green;'>✓ Success</p>\n";
        }
    }
    
    echo "<h3 style='color:green;'>Migration completed successfully!</h3>\n";
    echo "<p><a href='Generatepayroll.php'>Go to Generate Payroll</a></p>\n";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
