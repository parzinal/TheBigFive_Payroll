-- ============================================================================
-- TheBigFive Payroll System - Database Optimization Migration
-- Date: 2026-02-27
-- Description: Adds composite indexes for query performance,
--              removes redundant indexes that duplicate UNIQUE constraints.
-- ============================================================================
-- Run via: admin/run_optimization.php (browser) or paste into phpMyAdmin
-- ============================================================================

-- ============================================================================
-- PHASE 1: ADD NEW COMPOSITE INDEXES
-- These indexes target the most frequent query patterns identified
-- in payroll generation, DTR saving, and employee listing.
-- ============================================================================

-- 1. CRITICAL: Covers WHERE employee_id=? AND payroll_period_id=? ORDER BY dtr_date
--    Used by: get_employee_dtr_data.php, process_payroll.php, sp_compute_payroll
--    Impact: Eliminates full-table scans during payroll aggregation
ALTER TABLE `dtr_records`
  ADD INDEX `idx_emp_period_date` (`employee_id`, `payroll_period_id`, `dtr_date`);

-- 2. HIGH: Covers WHERE status='active' ORDER BY full_name
--    Used by: payroll_list.php, employee_list.php, get_employee_dtr_cards.php
ALTER TABLE `employees`
  ADD INDEX `idx_status_name` (`status`, `full_name`);

-- 3. MEDIUM: Covers WHERE user_id=? ORDER BY created_at DESC
--    Used by: account_logs.php (all 3 role versions)
ALTER TABLE `account_logs`
  ADD INDEX `idx_user_created` (`user_id`, `created_at` DESC);

-- 4. MEDIUM: Covers WHERE employee_id=? AND status=? queries on payroll history
--    Used by: payslip_history.php, payroll summary queries
ALTER TABLE `payroll_computations`
  ADD INDEX `idx_emp_status_created` (`employee_id`, `status`, `created_at` DESC);


-- ============================================================================
-- PHASE 2: DROP REDUNDANT INDEXES
-- These indexes duplicate existing UNIQUE constraints and waste
-- disk space, memory, and slow down INSERT/UPDATE operations.
-- ============================================================================

-- 1. dtr_records.idx_employee_date duplicates UNIQUE unique_employee_date(employee_id, dtr_date)
ALTER TABLE `dtr_records` DROP INDEX `idx_employee_date`;

-- 2. employees.idx_employee_code duplicates UNIQUE employee_code
ALTER TABLE `employees` DROP INDEX `idx_employee_code`;

-- 3. users.idx_username duplicates UNIQUE username
ALTER TABLE `users` DROP INDEX `idx_username`;

-- 4. users.idx_email duplicates UNIQUE email
ALTER TABLE `users` DROP INDEX `idx_email`;

-- 5. deduction_types.idx_deduction_code duplicates UNIQUE deduction_code
ALTER TABLE `deduction_types` DROP INDEX `idx_deduction_code`;

-- 6. positions.idx_position_name duplicates UNIQUE position_name
ALTER TABLE `positions` DROP INDEX `idx_position_name`;

-- 7. backup_settings.idx_setting_key duplicates UNIQUE setting_key
ALTER TABLE `backup_settings` DROP INDEX `idx_setting_key`;
