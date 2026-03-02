# DTR Calculator Database - Usage Guide

## Quick Start Guide

Based on your DTR Calculator template, here's how to use the database system:

---

## 1. Setup Database

```sql
-- Run the main database creation script
mysql -u root -p < create_database.sql

-- Run helper views and procedures
mysql -u root -p < dtr_helpers.sql
```

---

## 2. Add Employee

```sql
-- Add a new employee
INSERT INTO employees (
    employee_code, 
    full_name, 
    position, 
    department, 
    basic_monthly_salary,
    status
) VALUES (
    'EMP001',
    'FREEDOM',  -- As shown in your DTR template
    'Staff',
    'Operations',
    25000.00,  -- INPUT BASIC MONTHLY SALARY HERE
    'active'
);
```

---

## 3. Create Payroll Period

```sql
-- Create a payroll period (Oct. 13-27, 2025)
INSERT INTO payroll_periods (
    period_name,
    start_date,
    end_date,
    status
) VALUES (
    'Oct. 13-27, 2025',
    '2025-10-13',
    '2025-10-27',
    'processing'
);
```

---

## 4. Record Daily Time (DTR Entry)

### Example 1: Full Day with OT
```sql
-- Based on your template showing: 5:46 PM, 8:05 AM, 5:00 PM
INSERT INTO dtr_records (
    employee_id,
    payroll_period_id,
    dtr_date,
    am_time_in,      -- 8:05 AM
    am_time_out,     -- 12:00 PM
    pm_time_in,      -- 1:00 PM
    pm_time_out,     -- 5:00 PM
    ot_time_in,      -- 5:46 PM
    ot_time_out,     -- 8:00 PM
    is_absent,
    calculation_mode
) VALUES (
    1,  -- employee_id
    1,  -- payroll_period_id
    '2025-10-13',
    '08:05:00',
    '12:00:00',
    '13:00:00',
    '17:00:00',
    '17:46:00',
    '20:00:00',
    FALSE,
    'automatic'
);

-- Calculate work hours and late/undertime
CALL sp_calculate_work_hours(LAST_INSERT_ID());
CALL sp_calculate_late_undertime(
    LAST_INSERT_ID(),
    '08:00:00',  -- Expected AM in
    '12:00:00',  -- Expected AM out
    '13:00:00',  -- Expected PM in
    '17:00:00'   -- Expected PM out
);
```

### Example 2: With Late Time
```sql
-- Employee came in late at 8:16 AM
INSERT INTO dtr_records (
    employee_id,
    payroll_period_id,
    dtr_date,
    am_time_in,
    am_time_out,
    pm_time_in,
    pm_time_out,
    is_absent,
    calculation_mode
) VALUES (
    1,
    1,
    '2025-10-14',
    '08:16:00',  -- 16 minutes late
    '12:00:00',
    '13:00:00',
    '17:00:00',
    FALSE,
    'automatic'
);

-- This will calculate: LATE = 16 minutes (0.27 hours)
CALL sp_calculate_work_hours(LAST_INSERT_ID());
CALL sp_calculate_late_undertime(LAST_INSERT_ID(), '08:00:00', '12:00:00', '13:00:00', '17:00:00');
```

### Example 3: Halfday
```sql
INSERT INTO dtr_records (
    employee_id,
    payroll_period_id,
    dtr_date,
    halfday_in,
    halfday_out,
    is_halfday,
    is_absent
) VALUES (
    1,
    1,
    '2025-10-15',
    '08:00:00',
    '12:00:00',
    TRUE,
    FALSE
);
```

### Example 4: Absent
```sql
INSERT INTO dtr_records (
    employee_id,
    payroll_period_id,
    dtr_date,
    is_absent,
    remarks
) VALUES (
    1,
    1,
    '2025-10-16',
    TRUE,
    'Sick leave'
);
```

---

## 5. View DTR Summary

```sql
-- View all DTR records for an employee
SELECT * FROM vw_daily_dtr_details 
WHERE employee_id = 1 
AND dtr_date BETWEEN '2025-10-13' AND '2025-10-27'
ORDER BY dtr_date;

-- View summary for the period
SELECT * FROM vw_employee_dtr_summary
WHERE employee_id = 1
AND payroll_period_id = 1;
```

**This will show:**
- Total Work Days
- Total Work Hours (TOT.WORK HOURS)
- Total Late Hours (LATE)
- Total Undertime (UNDERTM)
- Total OT Hours (DAILY OT)
- Total Absent Days (ABSENT)

---

## 6. Compute Payroll

```sql
-- Run payroll computation for an employee
CALL sp_compute_payroll(
    1,  -- employee_id
    1,  -- payroll_period_id
    1   -- computed_by (admin user_id)
);

-- View computed payroll
SELECT * FROM vw_payroll_summary
WHERE employee_id = 1
AND payroll_period_id = 1;
```

**This calculates:**
- Basic Pay (BASIC)
- Per Day/Hour/Minute rates (PER/DAY, PER/HOUR, PER/MIN)
- OT Pay (OT PAY)
- Late Deduction (LATE deduction)
- Undertime Deduction (UNDERTIM deduction)
- Absent Deduction
- Net Pay (NET SALARY)

---

## 7. Add Deductions

### Cash Advance
```sql
INSERT INTO employee_deductions (
    employee_id,
    payroll_period_id,
    deduction_type_id,
    amount,
    notes,
    deduction_date,
    status
) VALUES (
    1,
    1,
    (SELECT id FROM deduction_types WHERE deduction_code = 'CA'),
    500.00,  -- CA ADV. (Cash Advance)
    'Cash advance request',
    '2025-10-13',
    'applied'
);
```

### Government Benefits
```sql
-- SSS Contribution
UPDATE payroll_computations 
SET sss_contribution = 250.00 
WHERE employee_id = 1 AND payroll_period_id = 1;

-- PhilHealth
UPDATE payroll_computations 
SET philhealth_contribution = 150.00 
WHERE employee_id = 1 AND payroll_period_id = 1;

-- Pag-IBIG
UPDATE payroll_computations 
SET pagibig_contribution = 100.00 
WHERE employee_id = 1 AND payroll_period_id = 1;
```

---

## 8. Final Payroll Calculation

```sql
UPDATE payroll_computations pc
SET 
    -- Add government benefits to total deductions
    total_deductions = late_deduction + undertime_deduction + absent_deduction + 
                       cash_advance + sss_contribution + philhealth_contribution + 
                       pagibig_contribution + withholding_tax + other_deductions,
    
    -- Recalculate net pay
    net_pay = total_earnings - (late_deduction + undertime_deduction + absent_deduction + 
              cash_advance + sss_contribution + philhealth_contribution + 
              pagibig_contribution + withholding_tax + other_deductions)
              
WHERE employee_id = 1 AND payroll_period_id = 1;
```

---

## 9. Generate Payroll Report

```sql
-- Complete payroll report matching DTR template
SELECT 
    e.employee_code,
    e.full_name AS 'EMPLOYEE NAME',
    pp.period_name AS 'Period (Oct. 13-27, 2025)',
    
    -- Salary Information
    pc.basic_monthly_salary AS 'INPUT BASIC MONTHLY SALARY',
    pc.per_day_rate AS 'PER/DAY',
    pc.per_hour_rate AS 'PER/HOUR',
    pc.per_minute_rate AS 'PER/MIN',
    
    -- Work Summary
    pc.total_work_days AS 'TOT.WORK Days',
    pc.total_work_hours AS 'TOT.WORK HOURS',
    pc.total_late_hours AS 'LATE [in hours]',
    pc.total_undertime_hours AS 'UNDERTM [in hours]',
    pc.total_ot_hours AS 'DAILY OT [in hours]',
    pc.total_absent_days AS 'ABSENT',
    
    -- Earnings
    pc.basic_pay AS 'BASIC',
    pc.ot_pay AS 'OT PAY',
    pc.total_earnings AS 'Total Earnings',
    
    -- Deductions
    pc.late_deduction AS 'LATE Deduction',
    pc.undertime_deduction AS 'UNDERTIM Deduction',
    pc.absent_deduction AS 'ABSENT Deduction',
    pc.cash_advance AS 'CA ADV.',
    
    -- Government Benefits
    pc.sss_contribution AS 'SSS',
    pc.philhealth_contribution AS 'PhilHealth',
    pc.pagibig_contribution AS 'Pag-IBIG',
    pc.withholding_tax AS 'Tax',
    
    -- Totals
    pc.total_deductions AS 'TOTAL DEDUCTIONS',
    pc.net_pay AS 'NET SALARY',
    
    pc.status AS 'Status'
    
FROM payroll_computations pc
JOIN employees e ON pc.employee_id = e.id
JOIN payroll_periods pp ON pc.payroll_period_id = pp.id
WHERE pc.employee_id = 1
AND pc.payroll_period_id = 1;
```

---

## 10. Approve and Mark as Paid

```sql
-- Approve payroll
UPDATE payroll_computations 
SET 
    status = 'approved',
    approved_at = NOW(),
    approved_by = 1  -- admin user_id
WHERE employee_id = 1 
AND payroll_period_id = 1;

-- Mark as paid
UPDATE payroll_computations 
SET 
    status = 'paid',
    paid_at = NOW()
WHERE employee_id = 1 
AND payroll_period_id = 1;

UPDATE payroll_periods
SET status = 'paid'
WHERE id = 1;
```

---

## Common Queries

### Check who's late today
```sql
SELECT e.full_name, d.dtr_date, d.am_time_in, d.late_minutes
FROM dtr_records d
JOIN employees e ON d.employee_id = e.id
WHERE d.dtr_date = CURDATE()
AND d.late_minutes > 0
ORDER BY d.late_minutes DESC;
```

### Calculate total OT pay for the month
```sql
SELECT 
    e.full_name,
    SUM(d.daily_ot_hours) AS total_ot_hours,
    ROUND(SUM(d.daily_ot_hours) * (e.basic_monthly_salary / 26 / 8) * 1.25, 2) AS ot_pay
FROM dtr_records d
JOIN employees e ON d.employee_id = e.id
WHERE MONTH(d.dtr_date) = 10
AND YEAR(d.dtr_date) = 2025
GROUP BY e.id, e.full_name;
```

### Find employees with most absences
```sql
SELECT 
    e.full_name,
    COUNT(*) AS absent_days
FROM dtr_records d
JOIN employees e ON d.employee_id = e.id
WHERE d.is_absent = TRUE
AND MONTH(d.dtr_date) = 10
GROUP BY e.id, e.full_name
ORDER BY absent_days DESC;
```

---

## DTR Template Field Mapping

| DTR Template Column | Database Table/Field |
|-------------------|---------------------|
| EMPLOYEE NAME | `employees.full_name` |
| MO/YR DATE | `dtr_records.dtr_date` |
| AM IN/OUT | `dtr_records.am_time_in/am_time_out` |
| PM IN/OUT | `dtr_records.pm_time_in/pm_time_out` |
| OT IN/OUT | `dtr_records.ot_time_in/ot_time_out` |
| HALFDAY | `dtr_records.is_halfday` |
| TOT.WORK HOURS | `dtr_records.total_work_hours` |
| LATE (mins) | `dtr_records.late_minutes` |
| LATE [hours] | `dtr_records.late_hours` |
| UNDERTM | `dtr_records.undertime_hours` |
| DAILY OT | `dtr_records.daily_ot_hours` |
| ABSENT | `dtr_records.is_absent` |
| BASIC SALARY | `employees.basic_monthly_salary` |
| PER/DAY | `payroll_computations.per_day_rate` |
| PER/HOUR | `payroll_computations.per_hour_rate` |
| PER/MIN | `payroll_computations.per_minute_rate` |
| OT PAY | `payroll_computations.ot_pay` |
| CA ADV. | `payroll_computations.cash_advance` |
| GOV'T BENEFITS | SSS + PhilHealth + Pag-IBIG columns |
| NET SALARY | `payroll_computations.net_pay` |
| REMARKS | `dtr_records.remarks` |

---

## Notes

- **Automatic Calculations**: Set `calculation_mode = 'automatic'` for auto-computed fields
- **Manual Override**: Set `calculation_mode = 'manual'` and input values directly
- **Variable Schedule**: Set `is_variable = TRUE` for employees with flexible schedules
- **Rate Calculation**: Default is 26 working days/month, 8 hours/day (configurable in stored procedure)
- **OT Multiplier**: Default 1.25x (regular OT), can be adjusted for holidays/rest days

---

## Support

For questions or issues, refer to the main README.md file in the `/config/sql/` directory.
