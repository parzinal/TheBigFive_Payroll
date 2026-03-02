-- DTR Helper Views and Procedures
-- Based on DTR Calculator Template

USE thebigfive_payroll;

-- =====================================================
-- VIEW: Employee DTR Summary per Period
-- =====================================================
CREATE OR REPLACE VIEW vw_employee_dtr_summary AS
SELECT 
    e.id AS employee_id,
    e.employee_code,
    e.full_name AS employee_name,
    pp.id AS payroll_period_id,
    pp.period_name,
    pp.start_date,
    pp.end_date,
    
    -- Work Summary
    COUNT(DISTINCT d.dtr_date) AS total_work_days,
    SUM(d.total_work_hours) AS total_work_hours,
    SUM(d.late_minutes) AS total_late_minutes,
    SUM(d.late_hours) AS total_late_hours,
    SUM(d.undertime_hours) AS total_undertime_hours,
    SUM(d.daily_ot_hours) AS total_ot_hours,
    SUM(CASE WHEN d.is_absent = TRUE THEN 1 ELSE 0 END) AS total_absent_days,
    SUM(CASE WHEN d.is_halfday = TRUE THEN 0.5 ELSE 0 END) AS total_halfdays,
    
    -- Salary Information
    e.basic_monthly_salary,
    ROUND(e.basic_monthly_salary / 30, 2) AS per_day_rate,
    ROUND(e.basic_monthly_salary / 30 / 8, 4) AS per_hour_rate,
    ROUND(e.basic_monthly_salary / 30 / 8 / 60, 6) AS per_minute_rate
    
FROM employees e
LEFT JOIN dtr_records d ON e.id = d.employee_id
LEFT JOIN payroll_periods pp ON d.payroll_period_id = pp.id
WHERE e.status = 'active'
GROUP BY e.id, e.employee_code, e.full_name, pp.id, pp.period_name, pp.start_date, pp.end_date, e.basic_monthly_salary;

-- =====================================================
-- VIEW: Daily DTR Details with Calculations
-- =====================================================
CREATE OR REPLACE VIEW vw_daily_dtr_details AS
SELECT 
    d.id,
    d.employee_id,
    e.employee_code,
    e.full_name AS employee_name,
    d.dtr_date,
    DATE_FORMAT(d.dtr_date, '%W') AS day_of_week,
    
    -- Time In/Out
    TIME_FORMAT(d.am_time_in, '%h:%i %p') AS am_in,
    TIME_FORMAT(d.am_time_out, '%h:%i %p') AS am_out,
    TIME_FORMAT(d.pm_time_in, '%h:%i %p') AS pm_in,
    TIME_FORMAT(d.pm_time_out, '%h:%i %p') AS pm_out,
    TIME_FORMAT(d.ot_time_in, '%h:%i %p') AS ot_in,
    TIME_FORMAT(d.ot_time_out, '%h:%i %p') AS ot_out,
    
    -- Working Hours
    d.total_work_hours,
    d.late_minutes,
    d.late_hours,
    d.undertime_hours,
    d.daily_ot_hours,
    
    -- Status
    CASE 
        WHEN d.is_absent THEN 'ABSENT'
        WHEN d.is_halfday THEN 'HALFDAY'
        WHEN d.is_variable THEN 'VARIABLE'
        ELSE 'PRESENT'
    END AS attendance_status,
    
    d.calculation_mode,
    d.remarks
    
FROM dtr_records d
JOIN employees e ON d.employee_id = e.id
ORDER BY d.dtr_date DESC, e.full_name;

-- =====================================================
-- VIEW: Payroll Computation Summary
-- =====================================================
CREATE OR REPLACE VIEW vw_payroll_summary AS
SELECT 
    pc.id,
    pc.employee_id,
    e.employee_code,
    e.full_name AS employee_name,
    pp.period_name,
    pp.start_date,
    pp.end_date,
    
    -- Salary Rates
    pc.basic_monthly_salary,
    pc.per_day_rate,
    pc.per_hour_rate,
    pc.per_minute_rate,
    
    -- Work Summary
    pc.total_work_days,
    pc.total_work_hours,
    pc.total_late_hours,
    pc.total_undertime_hours,
    pc.total_ot_hours,
    pc.total_absent_days,
    
    -- Earnings
    pc.basic_pay,
    pc.ot_pay,
    pc.total_earnings,
    
    -- Deductions
    pc.late_deduction,
    pc.undertime_deduction,
    pc.absent_deduction,
    pc.cash_advance,
    pc.sss_contribution,
    pc.philhealth_contribution,
    pc.pagibig_contribution,
    pc.withholding_tax,
    pc.other_deductions,
    pc.total_deductions,
    
    -- Net Pay
    pc.net_pay,
    
    -- Status
    pc.status,
    pc.computed_at,
    pc.approved_at,
    pc.paid_at
    
FROM payroll_computations pc
JOIN employees e ON pc.employee_id = e.id
JOIN payroll_periods pp ON pc.payroll_period_id = pp.id
ORDER BY pp.end_date DESC, e.full_name;

-- =====================================================
-- STORED PROCEDURE: Calculate Work Hours
-- =====================================================
DELIMITER //

CREATE PROCEDURE sp_calculate_work_hours(
    IN p_dtr_id INT
)
BEGIN
    DECLARE v_am_hours DECIMAL(5,2);
    DECLARE v_pm_hours DECIMAL(5,2);
    DECLARE v_ot_hours DECIMAL(5,2);
    DECLARE v_total_hours DECIMAL(5,2);
    DECLARE v_am_in TIME;
    DECLARE v_am_out TIME;
    DECLARE v_pm_in TIME;
    DECLARE v_pm_out TIME;
    DECLARE v_ot_in TIME;
    DECLARE v_ot_out TIME;
    
    -- Get time values
    SELECT am_time_in, am_time_out, pm_time_in, pm_time_out, ot_time_in, ot_time_out
    INTO v_am_in, v_am_out, v_pm_in, v_pm_out, v_ot_in, v_ot_out
    FROM dtr_records
    WHERE id = p_dtr_id;
    
    -- Calculate AM hours
    SET v_am_hours = IF(v_am_in IS NOT NULL AND v_am_out IS NOT NULL,
        TIMESTAMPDIFF(MINUTE, v_am_in, v_am_out) / 60,
        0);
    
    -- Calculate PM hours
    SET v_pm_hours = IF(v_pm_in IS NOT NULL AND v_pm_out IS NOT NULL,
        TIMESTAMPDIFF(MINUTE, v_pm_in, v_pm_out) / 60,
        0);
    
    -- Calculate OT hours
    SET v_ot_hours = IF(v_ot_in IS NOT NULL AND v_ot_out IS NOT NULL,
        TIMESTAMPDIFF(MINUTE, v_ot_in, v_ot_out) / 60,
        0);
    
    -- Total work hours (excluding OT)
    SET v_total_hours = v_am_hours + v_pm_hours;
    
    -- Update the record
    UPDATE dtr_records
    SET 
        total_work_hours = v_total_hours,
        daily_ot_hours = v_ot_hours
    WHERE id = p_dtr_id;
    
END //

DELIMITER ;

-- =====================================================
-- STORED PROCEDURE: Calculate Late and Undertime
-- =====================================================
DELIMITER //

CREATE PROCEDURE sp_calculate_late_undertime(
    IN p_dtr_id INT,
    IN p_expected_am_in TIME,
    IN p_expected_am_out TIME,
    IN p_expected_pm_in TIME,
    IN p_expected_pm_out TIME
)
BEGIN
    DECLARE v_late_minutes INT DEFAULT 0;
    DECLARE v_late_hours DECIMAL(5,2) DEFAULT 0;
    DECLARE v_undertime_minutes INT DEFAULT 0;
    DECLARE v_undertime_hours DECIMAL(5,2) DEFAULT 0;
    DECLARE v_am_in TIME;
    DECLARE v_am_out TIME;
    DECLARE v_pm_in TIME;
    DECLARE v_pm_out TIME;
    
    -- Get actual time values
    SELECT am_time_in, am_time_out, pm_time_in, pm_time_out
    INTO v_am_in, v_am_out, v_pm_in, v_pm_out
    FROM dtr_records
    WHERE id = p_dtr_id;
    
    -- Calculate AM late
    IF v_am_in IS NOT NULL AND v_am_in > p_expected_am_in THEN
        SET v_late_minutes = v_late_minutes + TIMESTAMPDIFF(MINUTE, p_expected_am_in, v_am_in);
    END IF;
    
    -- Calculate PM late
    IF v_pm_in IS NOT NULL AND v_pm_in > p_expected_pm_in THEN
        SET v_late_minutes = v_late_minutes + TIMESTAMPDIFF(MINUTE, p_expected_pm_in, v_pm_in);
    END IF;
    
    -- Calculate AM undertime
    IF v_am_out IS NOT NULL AND v_am_out < p_expected_am_out THEN
        SET v_undertime_minutes = v_undertime_minutes + TIMESTAMPDIFF(MINUTE, v_am_out, p_expected_am_out);
    END IF;
    
    -- Calculate PM undertime
    IF v_pm_out IS NOT NULL AND v_pm_out < p_expected_pm_out THEN
        SET v_undertime_minutes = v_undertime_minutes + TIMESTAMPDIFF(MINUTE, v_pm_out, p_expected_pm_out);
    END IF;
    
    -- Convert to hours
    SET v_late_hours = v_late_minutes / 60;
    SET v_undertime_hours = v_undertime_minutes / 60;
    
    -- Update the record
    UPDATE dtr_records
    SET 
        late_minutes = v_late_minutes,
        late_hours = v_late_hours,
        undertime_minutes = v_undertime_minutes,
        undertime_hours = v_undertime_hours
    WHERE id = p_dtr_id;
    
END //

DELIMITER ;

-- =====================================================
-- STORED PROCEDURE: Compute Payroll for Employee
-- =====================================================
DELIMITER //

CREATE PROCEDURE sp_compute_payroll(
    IN p_employee_id INT,
    IN p_payroll_period_id INT,
    IN p_computed_by INT
)
BEGIN
    DECLARE v_basic_salary DECIMAL(10,2);
    DECLARE v_per_day_rate DECIMAL(10,2);
    DECLARE v_per_hour_rate DECIMAL(10,4);
    DECLARE v_per_minute_rate DECIMAL(10,6);
    DECLARE v_total_work_days DECIMAL(5,2);
    DECLARE v_total_work_hours DECIMAL(7,2);
    DECLARE v_total_late_hours DECIMAL(5,2);
    DECLARE v_total_undertime_hours DECIMAL(5,2);
    DECLARE v_total_ot_hours DECIMAL(5,2);
    DECLARE v_total_absent_days DECIMAL(5,2);
    DECLARE v_basic_pay DECIMAL(10,2);
    DECLARE v_ot_pay DECIMAL(10,2);
    DECLARE v_late_deduction DECIMAL(10,2);
    DECLARE v_undertime_deduction DECIMAL(10,2);
    DECLARE v_absent_deduction DECIMAL(10,2);
    DECLARE v_total_earnings DECIMAL(10,2);
    DECLARE v_total_deductions DECIMAL(10,2);
    DECLARE v_net_pay DECIMAL(10,2);
    
    -- Get employee basic salary
    SELECT basic_monthly_salary INTO v_basic_salary
    FROM employees
    WHERE id = p_employee_id;
    
    -- Calculate rates (based on 30 days per month, 8 hours per day - TB5 standard)
    SET v_per_day_rate = ROUND(v_basic_salary / 30, 2);
    SET v_per_hour_rate = ROUND(v_per_day_rate / 8, 4);
    SET v_per_minute_rate = ROUND(v_per_hour_rate / 60, 6);
    
    -- Get DTR summary
    SELECT 
        COALESCE(SUM(CASE WHEN is_absent = FALSE THEN 1 ELSE 0 END), 0),
        COALESCE(SUM(total_work_hours), 0),
        COALESCE(SUM(late_hours), 0),
        COALESCE(SUM(undertime_hours), 0),
        COALESCE(SUM(daily_ot_hours), 0),
        COALESCE(SUM(CASE WHEN is_absent = TRUE THEN 1 ELSE 0 END), 0)
    INTO 
        v_total_work_days,
        v_total_work_hours,
        v_total_late_hours,
        v_total_undertime_hours,
        v_total_ot_hours,
        v_total_absent_days
    FROM dtr_records
    WHERE employee_id = p_employee_id
    AND payroll_period_id = p_payroll_period_id;
    
    -- Calculate earnings
    SET v_basic_pay = ROUND(v_total_work_hours * v_per_hour_rate, 2);
    SET v_ot_pay = ROUND(v_total_ot_hours * v_per_hour_rate * 1.25, 2); -- 25% premium
    SET v_total_earnings = v_basic_pay + v_ot_pay;
    
    -- Calculate deductions
    SET v_late_deduction = ROUND(v_total_late_hours * v_per_hour_rate, 2);
    SET v_undertime_deduction = ROUND(v_total_undertime_hours * v_per_hour_rate, 2);
    SET v_absent_deduction = ROUND(v_total_absent_days * v_per_day_rate, 2);
    
    -- Total deductions (basic deductions only, other deductions added separately)
    SET v_total_deductions = v_late_deduction + v_undertime_deduction + v_absent_deduction;
    
    -- Net pay
    SET v_net_pay = v_total_earnings - v_total_deductions;
    
    -- Insert or update payroll computation
    INSERT INTO payroll_computations (
        employee_id, payroll_period_id, 
        basic_monthly_salary, per_day_rate, per_hour_rate, per_minute_rate,
        total_work_days, total_work_hours, 
        total_late_hours, total_undertime_hours, total_ot_hours, total_absent_days,
        basic_pay, ot_pay,
        late_deduction, undertime_deduction, absent_deduction,
        total_earnings, total_deductions, net_pay,
        status, computed_at, computed_by
    ) VALUES (
        p_employee_id, p_payroll_period_id,
        v_basic_salary, v_per_day_rate, v_per_hour_rate, v_per_minute_rate,
        v_total_work_days, v_total_work_hours,
        v_total_late_hours, v_total_undertime_hours, v_total_ot_hours, v_total_absent_days,
        v_basic_pay, v_ot_pay,
        v_late_deduction, v_undertime_deduction, v_absent_deduction,
        v_total_earnings, v_total_deductions, v_net_pay,
        'computed', NOW(), p_computed_by
    )
    ON DUPLICATE KEY UPDATE
        basic_monthly_salary = v_basic_salary,
        per_day_rate = v_per_day_rate,
        per_hour_rate = v_per_hour_rate,
        per_minute_rate = v_per_minute_rate,
        total_work_days = v_total_work_days,
        total_work_hours = v_total_work_hours,
        total_late_hours = v_total_late_hours,
        total_undertime_hours = v_total_undertime_hours,
        total_ot_hours = v_total_ot_hours,
        total_absent_days = v_total_absent_days,
        basic_pay = v_basic_pay,
        ot_pay = v_ot_pay,
        late_deduction = v_late_deduction,
        undertime_deduction = v_undertime_deduction,
        absent_deduction = v_absent_deduction,
        total_earnings = v_total_earnings,
        total_deductions = v_total_deductions,
        net_pay = v_net_pay,
        status = 'computed',
        computed_at = NOW(),
        computed_by = p_computed_by;
    
    SELECT 'Payroll computed successfully' AS message;
    
END //

DELIMITER ;

-- =====================================================
-- Example Usage
-- =====================================================

-- Calculate work hours for a DTR record
-- CALL sp_calculate_work_hours(1);

-- Calculate late and undertime (8:00 AM - 12:00 PM, 1:00 PM - 5:00 PM schedule)
-- CALL sp_calculate_late_undertime(1, '08:00:00', '12:00:00', '13:00:00', '17:00:00');

-- Compute payroll for an employee
-- CALL sp_compute_payroll(1, 1, 1);

-- View employee DTR summary
-- SELECT * FROM vw_employee_dtr_summary WHERE employee_id = 1;

-- View daily DTR details
-- SELECT * FROM vw_daily_dtr_details WHERE employee_id = 1 ORDER BY dtr_date DESC;

-- View payroll summary
-- SELECT * FROM vw_payroll_summary WHERE employee_id = 1;
