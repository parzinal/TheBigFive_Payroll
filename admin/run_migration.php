<?php
/**
 * Database Migration: Add Training Columns
 * Run this once to add trainings_count, trainings_cost, days_office columns
 */

require_once '../config/database.php';

echo "<h2>Running Database Migration: Add Training Columns</h2>\n";

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
