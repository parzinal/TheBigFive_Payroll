-- Sample Data for Payslip History Testing
-- This file creates sample payroll periods and payroll computations
-- Run this after setting up your database

USE thebigfive_payroll;

-- First, ensure we have some employees (if not already created)
-- These are sample employees - adjust as needed based on your existing data

-- Insert Sample Payroll Periods
INSERT INTO payroll_periods (period_name, start_date, end_date, pay_date, status, created_at) VALUES
('January 2026 - 1st Half', '2026-01-01', '2026-01-15', '2026-01-20', 'completed', '2026-01-16 10:00:00'),
('January 2026 - 2nd Half', '2026-01-16', '2026-01-31', '2026-02-05', 'completed', '2026-02-01 10:00:00'),
('February 2026 - 1st Half', '2026-02-01', '2026-02-15', '2026-02-20', 'completed', '2026-02-16 10:00:00'),
('February 2026 - 2nd Half', '2026-02-16', '2026-02-28', '2026-03-05', 'processing', '2026-02-23 10:00:00');

-- Get the IDs of the periods we just created (for reference)
SET @period1 = (SELECT id FROM payroll_periods WHERE period_name = 'January 2026 - 1st Half' LIMIT 1);
SET @period2 = (SELECT id FROM payroll_periods WHERE period_name = 'January 2026 - 2nd Half' LIMIT 1);
SET @period3 = (SELECT id FROM payroll_periods WHERE period_name = 'February 2026 - 1st Half' LIMIT 1);
SET @period4 = (SELECT id FROM payroll_periods WHERE period_name = 'February 2026 - 2nd Half' LIMIT 1);

-- Sample Payroll Computations for Existing Employees
-- Note: Replace employee IDs with your actual employee IDs
-- You can get employee IDs by running: SELECT id, full_name FROM employees;

-- Example for Employee 1 (adjust employee_id based on your data)
-- Multiple payslips across different periods
INSERT INTO payroll_computations (
    employee_id, payroll_period_id, basic_monthly_salary, per_day_rate, per_hour_rate, per_minute_rate,
    total_work_days, total_work_hours, total_late_hours, total_undertime_hours, total_ot_hours, total_absent_days,
    basic_pay, ot_pay, late_deduction, undertime_deduction, absent_deduction, cash_advance,
    sss_contribution, philhealth_contribution, pagibig_contribution, withholding_tax,
    total_earnings, total_deductions, net_pay, status, created_at
) VALUES
-- Period 1
(1, @period1, 25000.00, 1190.48, 148.81, 2.48,
 10.0, 80.0, 0.5, 0.0, 5.0, 0.0,
 11904.80, 931.06, 74.40, 0.00, 0.00, 0.00,
 1125.00, 437.50, 100.00, 0.00,
 12835.86, 1662.50, 11173.36, 'paid', '2026-01-16 14:30:00'),

-- Period 2
(1, @period2, 25000.00, 1190.48, 148.81, 2.48,
 11.0, 88.0, 0.0, 0.5, 3.0, 0.0,
 13095.28, 558.64, 0.00, 74.40, 0.00, 0.00,
 1125.00, 437.50, 100.00, 0.00,
 13653.92, 1662.50, 11991.42, 'paid', '2026-02-01 14:30:00'),

-- Period 3
(1, @period3, 25000.00, 1190.48, 148.81, 2.48,
 10.5, 84.0, 0.0, 0.0, 4.0, 0.0,
 12500.04, 745.25, 0.00, 0.00, 0.00, 0.00,
 1125.00, 437.50, 100.00, 0.00,
 13245.29, 1662.50, 11582.79, 'paid', '2026-02-16 14:30:00');

-- Example for Employee 2 (adjust employee_id based on your data)
INSERT INTO payroll_computations (
    employee_id, payroll_period_id, basic_monthly_salary, per_day_rate, per_hour_rate, per_minute_rate,
    total_work_days, total_work_hours, total_late_hours, total_undertime_hours, total_ot_hours, total_absent_days,
    basic_pay, ot_pay, late_deduction, undertime_deduction, absent_deduction, cash_advance,
    sss_contribution, philhealth_contribution, pagibig_contribution, withholding_tax,
    total_earnings, total_deductions, net_pay, status, created_at
) VALUES
-- Period 1
(2, @period1, 13000.00, 619.05, 77.38, 1.29,
 10.0, 80.0, 1.0, 0.0, 0.0, 0.0,
 6190.50, 0.00, 77.38, 0.00, 0.00, 0.00,
 581.30, 281.25, 100.00, 0.00,
 6190.50, 962.55, 5227.95, 'paid', '2026-01-16 14:35:00'),

-- Period 2
(2, @period2, 13000.00, 619.05, 77.38, 1.29,
 11.0, 88.0, 0.0, 0.0, 2.0, 0.0,
 6809.55, 193.45, 0.00, 0.00, 0.00, 0.00,
 581.30, 281.25, 100.00, 0.00,
 7003.00, 962.55, 6040.45, 'paid', '2026-02-01 14:35:00'),

-- Period 3
(2, @period3, 13000.00, 619.05, 77.38, 1.29,
 9.0, 72.0, 0.5, 0.5, 1.0, 1.0,
 5571.45, 96.72, 38.69, 38.69, 619.05, 0.00,
 581.30, 281.25, 100.00, 0.00,
 5668.17, 1619.93, 4048.24, 'paid', '2026-02-16 14:35:00');

-- Example for Employee 3 (adjust employee_id based on your data)
INSERT INTO payroll_computations (
    employee_id, payroll_period_id, basic_monthly_salary, per_day_rate, per_hour_rate, per_minute_rate,
    total_work_days, total_work_hours, total_late_hours, total_undertime_hours, total_ot_hours, total_absent_days,
    basic_pay, ot_pay, late_deduction, undertime_deduction, absent_deduction, cash_advance,
    sss_contribution, philhealth_contribution, pagibig_contribution, withholding_tax,
    total_earnings, total_deductions, net_pay, status, created_at
) VALUES
-- Period 1
(3, @period1, 40000.00, 1904.76, 238.10, 3.97,
 10.0, 80.0, 0.0, 0.0, 8.0, 0.0,
 19047.60, 2380.80, 0.00, 0.00, 0.00, 0.00,
 1760.00, 700.00, 100.00, 500.00,
 21428.40, 3060.00, 18368.40, 'paid', '2026-01-16 14:40:00'),

-- Period 2  
(3, @period2, 40000.00, 1904.76, 238.10, 3.97,
 11.0, 88.0, 0.0, 0.0, 6.0, 0.0,
 20952.36, 1785.60, 0.00, 0.00, 0.00, 0.00,
 1760.00, 700.00, 100.00, 500.00,
 22737.96, 3060.00, 19677.96, 'paid', '2026-02-01 14:40:00');

-- Example for Employee 4 (adjust employee_id based on your data)
INSERT INTO payroll_computations (
    employee_id, payroll_period_id, basic_monthly_salary, per_day_rate, per_hour_rate, per_minute_rate,
    total_work_days, total_work_hours, total_late_hours, total_undertime_hours, total_ot_hours, total_absent_days,
    basic_pay, ot_pay, late_deduction, undertime_deduction, absent_deduction, cash_advance,
    sss_contribution, philhealth_contribution, pagibig_contribution, withholding_tax,
    total_earnings, total_deductions, net_pay, status, created_at
) VALUES
-- Period 2
(4, @period2, 5000.00, 238.10, 29.76, 0.50,
 14.0, 112.0, 0.0, 0.0, 0.0, 0.0,
 3333.40, 0.00, 0.00, 0.00, 0.00, 0.00,
 135.00, 175.00, 100.00, 0.00,
 3333.40, 410.00, 2923.40, 'paid', '2026-02-01 14:45:00'),

-- Period 3
(4, @period3, 5000.00, 238.10, 29.76, 0.50,
 10.0, 80.0, 0.0, 0.0, 0.0, 0.0,
 2381.00, 0.00, 0.00, 0.00, 0.00, 0.00,
 135.00, 175.00, 100.00, 0.00,
 2381.00, 410.00, 1971.00, 'paid', '2026-02-16 14:45:00');

-- Example for Employee 5 (adjust employee_id based on your data)
INSERT INTO payroll_computations (
    employee_id, payroll_period_id, basic_monthly_salary, per_day_rate, per_hour_rate, per_minute_rate,
    total_work_days, total_work_hours, total_late_hours, total_undertime_hours, total_ot_hours, total_absent_days,
    basic_pay, ot_pay, late_deduction, undertime_deduction, absent_deduction, cash_advance,
    sss_contribution, philhealth_contribution, pagibig_contribution, withholding_tax,
    total_earnings, total_deductions, net_pay, status, created_at
) VALUES
-- Period 1
(5, @period1, 2000.00, 95.24, 11.90, 0.20,
 10.0, 80.0, 2.0, 0.0, 0.0, 0.0,
 952.40, 0.00, 23.80, 0.00, 0.00, 0.00,
 125.00, 100.00, 100.00, 0.00,
 952.40, 325.00, 627.40, 'paid', '2026-01-16 14:50:00'),

-- Period 2
(5, @period2, 2000.00, 95.24, 11.90, 0.20,
 11.0, 88.0, 0.0, 0.0, 0.0, 0.0,
 1047.64, 0.00, 0.00, 0.00, 0.00, 0.00,
 125.00, 100.00, 100.00, 0.00,
 1047.64, 325.00, 722.64, 'paid', '2026-02-01 14:50:00'),

-- Period 3
(5, @period3, 2000.00, 95.24, 11.90, 0.20,
 10.0, 80.0, 0.0, 0.0, 0.0, 0.0,
 952.40, 0.00, 0.00, 0.00, 0.00, 0.00,
 125.00, 100.00, 100.00, 0.00,
 952.40, 325.00, 627.40, 'paid', '2026-02-16 14:50:00');

-- Verify the data was inserted
SELECT 
    e.full_name,
    COUNT(pc.id) as payslip_count,
    SUM(pc.net_pay) as total_net_pay
FROM employees e
LEFT JOIN payroll_computations pc ON e.id = pc.employee_id
GROUP BY e.id, e.full_name
HAVING payslip_count > 0
ORDER BY e.full_name;

-- You can also view all payslips
SELECT 
    e.full_name,
    pp.period_name,
    pc.net_pay,
    pc.status,
    pc.created_at
FROM payroll_computations pc
INNER JOIN employees e ON pc.employee_id = e.id
INNER JOIN payroll_periods pp ON pc.payroll_period_id = pp.id
ORDER BY pc.created_at DESC;
