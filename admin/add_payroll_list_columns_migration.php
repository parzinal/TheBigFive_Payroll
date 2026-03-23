<?php
/**
 * Run migration to add training payment and DTR configuration columns
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "Adding columns to payroll_computations table...\n\n";
    
    // Add late_start column
    try {
        $pdo->exec("ALTER TABLE payroll_computations ADD COLUMN late_start TIME DEFAULT '07:35' COMMENT 'Late start time threshold'");
        echo "✓ Added late_start column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "- late_start column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Add end_time column
    try {
        $pdo->exec("ALTER TABLE payroll_computations ADD COLUMN end_time TIME DEFAULT '17:00' COMMENT 'End time for work day'");
        echo "✓ Added end_time column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "- end_time column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Add training_amount column
    try {
        $pdo->exec("ALTER TABLE payroll_computations ADD COLUMN training_amount DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Training payment amount'");
        echo "✓ Added training_amount column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "- training_amount column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Add training_remarks column
    try {
        $pdo->exec("ALTER TABLE payroll_computations ADD COLUMN training_remarks TEXT NULL COMMENT 'Training payment remarks'");
        echo "✓ Added training_remarks column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "- training_remarks column already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
