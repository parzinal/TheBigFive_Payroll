<?php
/**
 * Save Payslip from DTR
 * Generate and save payslip based on DTR calculations
 */

require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/csrf.php';

header('Content-Type: application/json');

// Require staff authentication
if (!isAuthenticated() || !isStaff()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// CSRF check
requireCSRFToken();

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Extract month and year from month_key (format: YYYY-MM)
    $monthKey = $data['month_key'] ?? '';
    if (empty($monthKey)) {
        echo json_encode(['success' => false, 'message' => 'Month key is required']);
        exit;
    }
    
    list($year, $month) = explode('-', $monthKey);
    
    // Find or create payroll period for this month
    $stmt = $pdo->prepare("
        SELECT id, period_name, start_date, end_date, pay_date 
        FROM payroll_periods 
        WHERE YEAR(start_date) = ? AND MONTH(start_date) = ?
        LIMIT 1
    ");
    $stmt->execute([$year, $month]);
    $period = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no period exists, create one
    if (!$period) {
        $periodName = date('F Y', strtotime($monthKey . '-01'));
        $startDate = date('Y-m-01', strtotime($monthKey . '-01'));
        $endDate = date('Y-m-t', strtotime($monthKey . '-01'));
        $payDate = date('Y-m-15', strtotime($monthKey . '-01')); // 15th of the month
        
        $stmt = $pdo->prepare("
            INSERT INTO payroll_periods (period_name, start_date, end_date, pay_date, status, created_by)
            VALUES (?, ?, ?, ?, 'open', ?)
        ");
        $stmt->execute([$periodName, $startDate, $endDate, $payDate, $_SESSION['user_id']]);
        $payrollPeriodId = $pdo->lastInsertId();
    } else {
        $payrollPeriodId = $period['id'];
    }
    
    // Check if payslip already exists for this employee and period
    $stmt = $pdo->prepare("
        SELECT id FROM payroll_computations 
        WHERE employee_id = ? AND payroll_period_id = ?
    ");
    $stmt->execute([$data['employee_id'], $payrollPeriodId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing payslip
        $stmt = $pdo->prepare("
            UPDATE payroll_computations SET
                basic_monthly_salary = ?,
                per_day_rate = ?,
                per_hour_rate = ?,
                total_work_days = ?,
                total_work_hours = ?,
                total_late_hours = ?,
                total_undertime_hours = ?,
                total_ot_hours = ?,
                total_absent_days = ?,
                basic_pay = ?,
                ot_pay = ?,
                late_deduction = ?,
                undertime_deduction = ?,
                absent_deduction = ?,
                cash_advance = ?,
                sss_contribution = ?,
                philhealth_contribution = ?,
                pagibig_contribution = ?,
                withholding_tax = ?,
                other_deductions = ?,
                total_earnings = ?,
                total_deductions = ?,
                net_pay = ?,
                status = 'computed',
                computed_at = NOW(),
                computed_by = ?
            WHERE id = ?
        ");
        
        $params = [
            $data['basic_monthly_salary'],
            $data['per_day_rate'],
            $data['per_hour_rate'],
            $data['total_work_days'],
            $data['total_work_hours'],
            $data['total_late_hours'],
            $data['total_undertime_hours'],
            $data['total_ot_hours'],
            $data['total_absent_days'],
            $data['basic_pay'],
            $data['ot_pay'],
            $data['late_deduction'],
            $data['undertime_deduction'],
            $data['absent_deduction'],
            $data['cash_advance'],
            $data['sss_contribution'],
            $data['philhealth_contribution'],
            $data['pagibig_contribution'],
            $data['withholding_tax'],
            $data['other_deductions'],
            $data['total_earnings'],
            $data['total_deductions'],
            $data['net_pay'],
            $_SESSION['user_id'],
            $existing['id']
        ];
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'DB update error in staff/save_payslip',
                'error' => $e->getMessage(),
                'query' => $stmt->queryString,
                'params' => $params
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Payslip updated successfully',
            'payslip_id' => $existing['id'],
            'action' => 'updated'
        ]);
        
    } else {
        // Insert new payslip
        $stmt = $pdo->prepare("
            INSERT INTO payroll_computations (
                employee_id,
                payroll_period_id,
                basic_monthly_salary,
                per_day_rate,
                per_hour_rate,
                total_work_days,
                total_work_hours,
                total_late_hours,
                total_undertime_hours,
                total_ot_hours,
                total_absent_days,
                basic_pay,
                ot_pay,
                late_deduction,
                undertime_deduction,
                absent_deduction,
                cash_advance,
                sss_contribution,
                philhealth_contribution,
                pagibig_contribution,
                withholding_tax,
                other_deductions,
                total_earnings,
                total_deductions,
                net_pay,
                status,
                computed_at,
                computed_by
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, 'computed', NOW(), ?
            )
        ");
        
        $params = [
            $data['employee_id'],
            $payrollPeriodId,
            $data['basic_monthly_salary'],
            $data['per_day_rate'],
            $data['per_hour_rate'],
            $data['total_work_days'],
            $data['total_work_hours'],
            $data['total_late_hours'],
            $data['total_undertime_hours'],
            $data['total_ot_hours'],
            $data['total_absent_days'],
            $data['basic_pay'],
            $data['ot_pay'],
            $data['late_deduction'],
            $data['undertime_deduction'],
            $data['absent_deduction'],
            $data['cash_advance'],
            $data['sss_contribution'],
            $data['philhealth_contribution'],
            $data['pagibig_contribution'],
            $data['withholding_tax'],
            $data['other_deductions'],
            $data['total_earnings'],
            $data['total_deductions'],
            $data['net_pay'],
            $_SESSION['user_id']
        ];
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'DB insert error in staff/save_payslip',
                'error' => $e->getMessage(),
                'query' => $stmt->queryString,
                'params' => $params
            ]);
            exit;
        }
        
        $payslipId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Payslip generated successfully',
            'payslip_id' => $payslipId,
            'action' => 'created'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
