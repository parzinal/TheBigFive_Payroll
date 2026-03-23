<?php
/**
 * Get DTR Records for Employee
 * Returns existing DTR records from database
 */

require_once '../config/bootstrap.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// H1: Require admin role
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

$employeeId = intval($_GET['employee_id'] ?? 0);
$payrollPeriodId = intval($_GET['payroll_period_id'] ?? 0);
$checkOnly = isset($_GET['check_only']) && $_GET['check_only'] == '1';
$checkPeriods = isset($_GET['check_periods']) && $_GET['check_periods'] == '1';
$year = intval($_GET['year'] ?? 0);
$month = intval($_GET['month'] ?? 0);

// Check periods mode - returns which cutoffs already have records
if ($checkPeriods && $employeeId && $year && $month) {
    try {
        $pdo = getDBConnection();
        
        // Create date ranges for both periods
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
        $lastDay = date('t', strtotime("$year-$monthStr-01"));
        
        $period1Start = "$year-$monthStr-01";
        $period1End = "$year-$monthStr-15";
        $period2Start = "$year-$monthStr-16";
        $period2End = "$year-$monthStr-$lastDay";
        
        // Check for records in first period (1-15)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM dtr_records 
            WHERE employee_id = ? 
            AND dtr_date BETWEEN ? AND ?
        ");
        $stmt->execute([$employeeId, $period1Start, $period1End]);
        $period1Exists = $stmt->fetch()['count'] > 0;
        
        // Check for records in second period (16-end)
        $stmt->execute([$employeeId, $period2Start, $period2End]);
        $period2Exists = $stmt->fetch()['count'] > 0;
        
        $existingPeriods = [];
        if ($period1Exists) $existingPeriods[] = 12; // 12th cutoff
        if ($period2Exists) $existingPeriods[] = 27; // 27th cutoff
        
        echo json_encode([
            'success' => true,
            'existing_periods' => $existingPeriods
        ]);
        exit();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error checking periods: ' . $e->getMessage()
        ]);
        exit();
    }
}

if (!$employeeId || !$payrollPeriodId) {
    echo json_encode(['success' => false, 'message' => 'Employee ID and Payroll Period ID are required']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    if ($checkOnly) {
        // Just check if records exist
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM dtr_records 
            WHERE employee_id = ? AND payroll_period_id = ?
        ");
        $stmt->execute([$employeeId, $payrollPeriodId]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'exists' => $result['count'] > 0
        ]);
    } else {
        // Get all DTR records for the employee and period
        $stmt = $pdo->prepare("
            SELECT 
                id,
                dtr_date,
                am_time_in,
                am_time_out,
                pm_time_in,
                pm_time_out,
                ot_time_in,
                ot_time_out,
                halfday_in,
                halfday_out,
                is_halfday,
                total_work_hours,
                late_minutes,
                late_hours,
                undertime_minutes,
                undertime_hours,
                daily_ot_hours,
                is_absent,
                is_variable,
                remarks,
                calculation_mode,
                created_at,
                updated_at
            FROM dtr_records 
            WHERE employee_id = ? AND payroll_period_id = ?
            ORDER BY dtr_date ASC
        ");
        $stmt->execute([$employeeId, $payrollPeriodId]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format time values for display
        foreach ($records as &$record) {
            // Format times to HH:MM
            $timeFields = ['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out', 
                          'ot_time_in', 'ot_time_out', 'halfday_in', 'halfday_out'];
            
            foreach ($timeFields as $field) {
                if (!empty($record[$field])) {
                    // Ensure time is in HH:MM format
                    $time = $record[$field];
                    if (strlen($time) > 5) {
                        $record[$field] = substr($time, 0, 5);
                    }
                } else {
                    $record[$field] = '';
                }
            }
            
            // Ensure numeric values
            $record['total_work_hours'] = floatval($record['total_work_hours'] ?? 0);
            $record['late_minutes'] = intval($record['late_minutes'] ?? 0);
            $record['undertime_hours'] = floatval($record['undertime_hours'] ?? 0);
            $record['daily_ot_hours'] = floatval($record['daily_ot_hours'] ?? 0);
            $record['is_absent'] = boolval($record['is_absent'] ?? false);
            $record['is_halfday'] = boolval($record['is_halfday'] ?? false);
        }
        
        echo json_encode([
            'success' => true,
            'dtr_data' => $records,
            'records_count' => count($records)
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving DTR records: ' . $e->getMessage()
    ]);
}
