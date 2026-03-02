-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 24, 2026 at 07:09 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `thebigfive_payroll`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_late_undertime` (IN `p_dtr_id` INT, IN `p_expected_am_in` TIME, IN `p_expected_am_out` TIME, IN `p_expected_pm_in` TIME, IN `p_expected_pm_out` TIME)   BEGIN
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
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_work_hours` (IN `p_dtr_id` INT)   BEGIN
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
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_compute_payroll` (IN `p_employee_id` INT, IN `p_payroll_period_id` INT, IN `p_computed_by` INT)   BEGIN
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
    
    -- Calculate rates (based on 26 working days per month, 8 hours per day)
    SET v_per_day_rate = ROUND(v_basic_salary / 26, 2);
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
    
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `account_logs`
--

CREATE TABLE `account_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_type` enum('login','logout','profile_update','password_change','create','update','delete','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_logs`
--

INSERT INTO `account_logs` (`id`, `user_id`, `username`, `action`, `action_type`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 4, 'Administrator', 'User Logout', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 01:15:07'),
(2, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 01:15:27'),
(3, 4, 'Administrator', 'User Logout', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 01:15:33'),
(4, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 01:16:00'),
(5, NULL, 'jc', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 03:19:11'),
(6, 8, 'jc_2', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 03:21:03'),
(7, 4, 'Administrator', 'User Logout', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:26:41'),
(8, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:27:53'),
(9, 4, 'Administrator', 'User Logout', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:32:29'),
(10, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:36:57'),
(11, 4, 'Administrator', 'User Logout', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:16:17'),
(12, NULL, 'thebigfivepayroll@gmail.com', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:16:24'),
(13, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:16:35'),
(14, 4, 'Administrator', 'User Logout', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:34:12'),
(15, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:34:27'),
(16, 4, 'Administrator', 'User Logout', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 08:17:17'),
(17, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 08:19:27'),
(18, 8, 'jc_2', 'Imported DTR', 'other', 'Imported DTR for FREEDOM - 11 records from Excel', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 08:23:27'),
(19, 8, 'jc_2', 'Imported DTR', 'other', 'Imported DTR for FREEDOM - 11 records from Excel', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 08:25:47'),
(20, NULL, 'thebigfivepayroll@gmail.com', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:10:39'),
(21, NULL, 'thebigfivepayroll@gmail.com', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:10:49'),
(22, NULL, 'thebigfivepayroll@gmail.com', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:10:59'),
(23, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:11:14'),
(24, 4, 'Administrator', 'Updated Position Assignments', 'update', 'Updated Position Assignments: 1 employee(s) - Bulk position assignment', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:11:27'),
(25, NULL, 'jc_2', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:11:48'),
(26, NULL, 'jc_2', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:12:15'),
(27, 8, 'jc_2', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:12:50'),
(28, 8, 'jc_2', 'User Logout', 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:14:52'),
(29, 8, 'jc_2', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:15:01'),
(30, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:17:26'),
(31, 4, 'Administrator', 'User Logout', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:18:36'),
(32, 2, 'staff', 'User Login', 'login', 'User logged in as staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:24:29'),
(33, NULL, 'thebigfivepayroll@email.com', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 00:30:25'),
(34, NULL, 'thebigfivepayroll@email.com', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 00:31:18'),
(35, 2, 'staff', 'User Logout', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:31:38'),
(36, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 00:32:00'),
(37, 2, 'staff', 'User Login', 'login', 'User logged in as staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:32:09'),
(38, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: Emman (EMP-001-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 00:55:18'),
(39, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: emman (EMP-001-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 00:55:28'),
(40, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: emman (EMP-001-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 00:55:31'),
(41, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: emman (EMP-001-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 00:55:33'),
(42, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: Emman (EMP-001-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 00:55:38'),
(43, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: Emmanuel (EMP-001-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 00:57:32'),
(44, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: Emman (EMP-001-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 01:00:07'),
(45, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: Emman (EMP-001-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 01:00:40'),
(46, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: Emman (EMP-001-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 01:01:02'),
(47, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: John Jared Bueta (EMP-005-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 01:01:36'),
(48, 8, 'jc_2', 'Updated Employee', 'update', 'Updated Employee: John Jared Bueta (EMP-005-2026) - Position: Dev, Status: inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 01:02:57'),
(49, 8, 'jc_2', 'Updated Employee', 'update', 'Updated Employee: John Jared Bueta (EMP-005-2026) - Position: Dev, Status: inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 01:02:59'),
(50, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: John Jared Buetaa (EMP-005-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 01:03:58'),
(51, 4, 'Administrator', 'Updated Employee', 'update', 'Updated Employee: John Jared Bueta (EMP-005-2026) - Position: Dev, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 01:05:21'),
(52, 8, 'jc_2', 'Created Employee', 'create', 'Created new Employee: QWWERT (EMP-005-2027)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 01:06:37'),
(53, 8, 'jc_2', 'Updated Employee', 'update', 'Updated Employee: QWWERT (EMP-005-2027) - Position: JANITOR, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 01:07:30'),
(54, 4, 'Administrator', 'Saved DTR', 'other', 'Saved DTR for Emman - 13 records saved', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 01:12:38'),
(55, 4, 'Administrator', 'Deleted Employee', 'delete', 'Deleted Employee: QWWERT (EMP-005-2027)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 01:14:05'),
(56, 4, 'Administrator', 'Deleted Employee', 'delete', 'Deleted Employee: FREEDOM (EMP-2026-0001)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-24 01:14:32'),
(57, 4, 'Administrator', 'Saved DTR', 'other', 'Saved DTR for Kim Yamamoto - 13 records saved', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 01:24:50'),
(58, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 01:30:13'),
(59, 2, 'staff', 'User Logout', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 01:59:46'),
(60, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:00:01'),
(61, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:02:34'),
(62, 8, 'jc_2', 'User Logout', 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:51:06'),
(63, 2, 'staff', 'User Login', 'login', 'User logged in as staff', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:52:24'),
(64, 2, 'staff', 'User Logout', 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:54:04'),
(65, NULL, 'jc_2', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:54:16'),
(66, 8, 'jc_2', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:54:20'),
(67, NULL, 'jc_2', 'Failed Login Attempt', 'login', 'Invalid credentials or account inactive', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:55:04'),
(68, 8, 'jc_2', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:55:09'),
(69, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:56:19'),
(70, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:57:04'),
(71, 8, 'jc_2', 'User Login', 'login', 'User logged in as admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:59:00'),
(72, 4, 'Administrator', 'Deleted Employee', 'delete', 'Deleted Employee: Kim Yamamoto (EMP-002-2026)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:59:36'),
(73, 4, 'Administrator', 'Deleted User', 'delete', 'Deleted User: kim (kim)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 03:02:48'),
(74, 4, 'Administrator', 'Deleted User', 'delete', 'Deleted User: Regular User (user)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 03:02:56'),
(75, 8, 'jc_2', 'Profile Update', 'profile_update', 'Updated profile information', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 03:23:38'),
(76, 4, 'Administrator', 'Saved DTR', 'other', 'Saved DTR for John Jared Bueta - 13 records saved', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 03:35:42'),
(77, 4, 'Administrator', 'Saved DTR', 'other', 'Saved DTR for John Jared Bueta - 15 records saved', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 03:41:04'),
(78, 8, 'jc_2', 'Profile Update', 'profile_update', 'Updated profile information', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 03:45:38'),
(79, 8, 'jc_2', 'Profile Update', 'profile_update', 'Updated profile information', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 03:45:42'),
(80, 8, 'jc_2', 'Updated Employee', 'update', 'Updated Employee: John Jared Bueta (EMP-005-2026) - Position: Devil, Status: active', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:20:38'),
(81, 4, 'Administrator', 'User Login', 'login', 'User logged in as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:28:11'),
(82, 4, 'Administrator', 'Saved DTR', 'other', 'Saved DTR for Emman - 15 records saved', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:33:44'),
(83, 4, 'Administrator', 'Created Employee', 'create', 'Created new Employee: kimkim (EMP-007-2026)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:35:30'),
(84, 4, 'Administrator', 'Saved DTR', 'other', 'Saved DTR for John Jared Bueta - 15 records saved', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:45:57'),
(85, 4, 'Administrator', 'Saved DTR', 'other', 'Saved DTR for Emman - 15 records saved', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:03:27'),
(86, 4, 'Administrator', 'Saved DTR', 'other', 'Saved DTR for Emman - 16 records saved', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:07:36'),
(87, 4, 'Administrator', 'Imported DTR', 'other', 'Imported DTR for kimyamamotos - 31 records from Excel', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:35:52'),
(88, 4, 'Administrator', 'Imported DTR', 'other', 'Imported DTR for kimyamamotos - 31 records from Excel', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:44:53'),
(89, 4, 'Administrator', 'Imported DTR', 'other', 'Imported DTR for Ejan - 31 records from Excel', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:47:31'),
(90, 4, 'Administrator', 'Saved DTR', 'other', 'Saved DTR for Ejan - 13 records saved', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 07:06:41');

-- --------------------------------------------------------

--
-- Table structure for table `backup_history`
--

CREATE TABLE `backup_history` (
  `id` int NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint NOT NULL DEFAULT '0',
  `backup_type` enum('manual','automatic') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `status` enum('completed','failed','in_progress') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `tables_count` int NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_settings`
--

CREATE TABLE `backup_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `backup_settings`
--

INSERT INTO `backup_settings` (`id`, `setting_key`, `setting_value`, `updated_by`, `updated_at`) VALUES
(1, 'auto_backup_enabled', '0', NULL, '2026-02-24 07:03:01'),
(2, 'auto_backup_frequency', 'daily', NULL, '2026-02-24 07:03:01'),
(3, 'auto_backup_time', '02:00', NULL, '2026-02-24 07:03:01'),
(4, 'auto_backup_retention', '10', NULL, '2026-02-24 07:03:01'),
(5, 'auto_backup_day_of_week', '1', NULL, '2026-02-24 07:03:01'),
(6, 'auto_backup_day_of_month', '1', NULL, '2026-02-24 07:03:01'),
(7, 'backup_compression', '1', NULL, '2026-02-24 07:03:01'),
(8, 'last_auto_backup', NULL, NULL, '2026-02-24 07:03:01'),
(9, 'backup_cron_token', NULL, NULL, '2026-02-24 07:03:01');

-- --------------------------------------------------------

--
-- Table structure for table `deduction_types`
--

CREATE TABLE `deduction_types` (
  `id` int NOT NULL,
  `deduction_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deduction_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_mandatory` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deduction_types`
--

INSERT INTO `deduction_types` (`id`, `deduction_name`, `deduction_code`, `description`, `is_mandatory`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'SSS Contribution', 'SSS', 'Social Security System contribution', 1, 1, '2026-02-20 03:11:00', '2026-02-20 03:11:00'),
(2, 'PhilHealth Contribution', 'PHILHEALTH', 'Philippine Health Insurance Corporation', 1, 1, '2026-02-20 03:11:00', '2026-02-20 03:11:00'),
(3, 'Pag-IBIG Contribution', 'PAGIBIG', 'Home Development Mutual Fund', 1, 1, '2026-02-20 03:11:00', '2026-02-20 03:11:00'),
(4, 'Withholding Tax', 'WTAX', 'Income tax withholding', 1, 1, '2026-02-20 03:11:00', '2026-02-20 03:11:00'),
(5, 'Cash Advance', 'CA', 'Cash advance deduction', 0, 1, '2026-02-20 03:11:00', '2026-02-20 03:11:00'),
(6, 'Late Deduction', 'LATE', 'Deduction for tardiness', 0, 1, '2026-02-20 03:11:00', '2026-02-20 03:11:00'),
(7, 'Undertime Deduction', 'UT', 'Deduction for undertime', 0, 1, '2026-02-20 03:11:00', '2026-02-20 03:11:00'),
(8, 'Absent Deduction', 'ABSENT', 'Deduction for absences', 0, 1, '2026-02-20 03:11:00', '2026-02-20 03:11:00');

-- --------------------------------------------------------

--
-- Table structure for table `dtr_records`
--

CREATE TABLE `dtr_records` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `payroll_period_id` int DEFAULT NULL,
  `dtr_date` date NOT NULL,
  `am_time_in` time DEFAULT NULL,
  `am_time_out` time DEFAULT NULL,
  `pm_time_in` time DEFAULT NULL,
  `pm_time_out` time DEFAULT NULL,
  `ot_time_in` time DEFAULT NULL,
  `ot_time_out` time DEFAULT NULL,
  `halfday_in` time DEFAULT NULL,
  `halfday_out` time DEFAULT NULL,
  `is_halfday` tinyint(1) DEFAULT '0',
  `total_work_hours` decimal(5,2) DEFAULT '0.00',
  `late_minutes` int DEFAULT '0',
  `late_hours` decimal(5,2) DEFAULT '0.00',
  `undertime_minutes` int DEFAULT '0',
  `undertime_hours` decimal(5,2) DEFAULT '0.00',
  `daily_ot_hours` decimal(5,2) DEFAULT '0.00',
  `govt_deduct` decimal(10,2) DEFAULT '0.00' COMMENT 'Government benefit deduction for this row (SSS/PhilHealth/PagIBIG)',
  `net_salary` decimal(10,2) DEFAULT NULL COMMENT 'Computed net salary for this row',
  `is_absent` tinyint(1) DEFAULT '0',
  `is_variable` tinyint(1) DEFAULT '0',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `calculation_mode` enum('automatic','manual') COLLATE utf8mb4_unicode_ci DEFAULT 'automatic',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dtr_records`
--

INSERT INTO `dtr_records` (`id`, `employee_id`, `payroll_period_id`, `dtr_date`, `am_time_in`, `am_time_out`, `pm_time_in`, `pm_time_out`, `ot_time_in`, `ot_time_out`, `halfday_in`, `halfday_out`, `is_halfday`, `total_work_hours`, `late_minutes`, `late_hours`, `undertime_minutes`, `undertime_hours`, `daily_ot_hours`, `govt_deduct`, `net_salary`, `is_absent`, `is_variable`, `remarks`, `calculation_mode`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(16, 1, 1, '2026-10-13', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(17, 1, 1, '2026-10-14', '08:10:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'Late 5 mins', 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(18, 1, 1, '2026-10-15', '08:00:00', '12:00:00', '13:00:00', '18:30:00', NULL, '18:30:00', NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'OT 1.5 hrs', 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(19, 1, 1, '2026-10-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, 'Sick Leave', 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(20, 1, 1, '2026-10-17', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(21, 1, 1, '2026-10-20', '08:15:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'SSS', 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(22, 1, 1, '2026-10-21', '08:00:00', '12:00:00', '13:00:00', '16:30:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'PHILHEALTH', 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(23, 1, 1, '2026-10-22', '08:00:00', '12:00:00', NULL, NULL, NULL, NULL, '08:00:00', '12:00:00', 1, 240.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'PAGIBIG', 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(24, 1, 1, '2026-10-23', '08:00:00', '12:00:00', '13:00:00', '19:00:00', NULL, '19:00:00', NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'CA OCT. 23, 2025', 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(25, 1, 1, '2026-10-24', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(26, 1, 1, '2026-10-27', '08:05:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'On time', 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(27, 1, 1, '2026-10-28', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(28, 1, 1, '2026-10-29', '08:20:00', '12:00:00', '13:00:00', '17:30:00', NULL, '17:30:00', NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'Late, made up', 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(29, 1, 1, '2026-10-30', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(30, 1, 1, '2026-10-31', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-23 06:59:18', '2026-02-23 06:59:18', 4, NULL),
(31, 4, 6, '1899-12-31', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 480.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-23 07:05:59', '2026-02-23 07:05:59', 4, NULL),
(32, 4, 6, '1900-01-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-23 07:05:59', '2026-02-23 07:05:59', 4, NULL),
(33, 4, 6, '1900-01-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-23 07:05:59', '2026-02-23 07:05:59', 4, NULL),
(34, 1, 7, '2026-02-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(35, 1, 7, '2026-02-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(36, 1, 7, '2026-02-17', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 8.00, 25, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(37, 1, 7, '2026-02-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(38, 1, 7, '2026-02-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(39, 1, 7, '2026-02-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(40, 1, 7, '2026-02-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(41, 1, 7, '2026-02-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(42, 1, 7, '2026-02-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(43, 1, 7, '2026-02-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(44, 1, 7, '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(45, 1, 7, '2026-02-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(46, 1, 7, '2026-02-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 01:12:38', '2026-02-24 01:12:38', 4, NULL),
(60, 6, 7, '2026-02-15', '08:05:00', '12:00:00', '13:00:00', '17:00:00', NULL, '19:00:00', NULL, NULL, 0, 7.92, 5, 0.00, 0, 0.00, 2.00, 0.00, NULL, 0, 0, 'SSS', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(61, 6, 7, '2026-02-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, 'PHILHEALTH', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(62, 6, 7, '2026-02-17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, 'PAGIBIG', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(63, 6, 7, '2026-02-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(64, 6, 7, '2026-02-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(65, 6, 7, '2026-02-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(66, 6, 7, '2026-02-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(67, 6, 7, '2026-02-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(68, 6, 7, '2026-02-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(69, 6, 7, '2026-02-24', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 8.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(70, 6, 7, '2026-02-25', '08:00:00', '12:00:00', NULL, NULL, NULL, NULL, '08:00:00', '12:00:00', 0, 4.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(71, 6, 7, '2026-02-26', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, '19:00:00', NULL, NULL, 0, 8.00, 0, 0.00, 0, 0.00, 2.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(72, 6, 7, '2026-02-27', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 8.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 03:35:42', '2026-02-24 03:35:42', 4, NULL),
(73, 6, 8, '2026-02-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, 'SSS', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(74, 6, 8, '2026-03-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, 'PHILHEALTH', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(75, 6, 8, '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, 'PAGIBIG', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(76, 6, 8, '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(77, 6, 8, '2026-03-04', '08:05:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 7.92, 5, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(78, 6, 8, '2026-03-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(79, 6, 8, '2026-03-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(80, 6, 8, '2026-03-07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(81, 6, 8, '2026-03-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(82, 6, 8, '2026-03-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(83, 6, 8, '2026-03-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(84, 6, 8, '2026-03-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(85, 6, 8, '2026-03-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(86, 6, 8, '2026-03-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(87, 6, 8, '2026-03-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 03:41:04', '2026-02-24 03:41:04', 4, NULL),
(88, 1, 8, '2026-02-28', '08:00:00', '12:00:00', NULL, NULL, NULL, '19:00:00', '08:00:00', '12:00:00', 0, 4.00, 0, 0.00, 0, 0.00, 2.00, 317.50, 352.08, 0, 0, 'SSS', 'automatic', '2026-02-24 05:33:43', '2026-02-24 05:33:43', 4, NULL),
(89, 1, 8, '2026-03-01', '08:00:00', '12:00:00', '13:00:00', '15:00:00', NULL, NULL, NULL, NULL, 0, 6.00, 0, 0.00, 0, 2.00, 0.00, 125.00, 325.00, 0, 0, 'PHILHEALTH', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(90, 1, 8, '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 100.00, 0.00, 1, 0, 'PAGIBIG', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(91, 1, 8, '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(92, 1, 8, '2026-03-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(93, 1, 8, '2026-03-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(94, 1, 8, '2026-03-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(95, 1, 8, '2026-03-07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(96, 1, 8, '2026-03-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(97, 1, 8, '2026-03-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(98, 1, 8, '2026-03-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(99, 1, 8, '2026-03-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(100, 1, 8, '2026-03-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(101, 1, 8, '2026-03-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(102, 1, 8, '2026-03-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:33:44', '2026-02-24 05:33:44', 4, NULL),
(103, 6, 9, '2026-03-31', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, '18:00:00', NULL, NULL, 0, 8.00, 0, 0.00, 0, 0.00, 1.00, 317.50, 501.04, 0, 0, 'SSS', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(104, 6, 9, '2026-04-01', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, '18:00:00', NULL, NULL, 0, 8.00, 0, 0.00, 0, 0.00, 1.00, 125.00, 501.04, 0, 0, 'PHILHEALTH', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(105, 6, 9, '2026-04-02', '08:00:00', '12:00:00', '13:00:00', '17:00:00', NULL, NULL, NULL, NULL, 0, 8.00, 0, 0.00, 0, 0.00, 0.00, 100.00, 433.33, 0, 0, 'PAGIBIG', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(106, 6, 9, '2026-04-03', '08:00:00', '12:00:00', NULL, NULL, NULL, NULL, '08:00:00', '12:00:00', 0, 4.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 216.66, 0, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(107, 6, 9, '2026-04-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(108, 6, 9, '2026-04-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(109, 6, 9, '2026-04-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(110, 6, 9, '2026-04-07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(111, 6, 9, '2026-04-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(112, 6, 9, '2026-04-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(113, 6, 9, '2026-04-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(114, 6, 9, '2026-04-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(115, 6, 9, '2026-04-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(116, 6, 9, '2026-04-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(117, 6, 9, '2026-04-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 1, 0, '', 'automatic', '2026-02-24 05:45:57', '2026-02-24 05:45:57', 4, NULL),
(118, 1, 10, '2026-01-31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, 'SSS', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(119, 1, 10, '2026-02-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, 'PHILHEALTH', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(120, 1, 10, '2026-02-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, 'PAGIBIG', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(121, 1, 10, '2026-02-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(122, 1, 10, '2026-02-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(123, 1, 10, '2026-02-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(124, 1, 10, '2026-02-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(125, 1, 10, '2026-02-07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(126, 1, 10, '2026-02-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(127, 1, 10, '2026-02-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(128, 1, 10, '2026-02-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(129, 1, 10, '2026-02-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(130, 1, 10, '2026-02-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(131, 1, 10, '2026-02-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(132, 1, 10, '2026-02-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 1, 0, '', 'automatic', '2026-02-24 06:03:27', '2026-02-24 06:03:27', 4, NULL),
(133, 1, 11, '2026-05-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'SSS', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(134, 1, 11, '2026-05-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'PHILHEALTH', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(135, 1, 11, '2026-05-17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'PAGIBIG', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(136, 1, 11, '2026-05-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(137, 1, 11, '2026-05-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(138, 1, 11, '2026-05-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(139, 1, 11, '2026-05-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(140, 1, 11, '2026-05-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(141, 1, 11, '2026-05-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(142, 1, 11, '2026-05-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(143, 1, 11, '2026-05-25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(144, 1, 11, '2026-05-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(145, 1, 11, '2026-05-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(146, 1, 11, '2026-05-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(147, 1, 11, '2026-05-29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(148, 1, 11, '2026-05-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:07:36', '2026-02-24 06:07:36', 4, NULL),
(149, 9, 12, '2026-02-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(150, 9, 12, '2026-02-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(151, 9, 12, '2026-02-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(152, 9, 12, '2026-02-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(153, 9, 12, '2026-02-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(154, 9, 12, '2026-02-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'SSS', 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(155, 9, 12, '2026-02-07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'PHILHEALTH', 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(156, 9, 12, '2026-02-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'PAGIBIG', 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(157, 9, 12, '2026-02-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'CA OCT. 23, 2025', 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(158, 9, 12, '2026-02-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(159, 9, 12, '2026-02-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(160, 9, 12, '2026-02-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(161, 9, 12, '2026-02-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(162, 9, 12, '2026-02-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(163, 9, 12, '2026-02-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(164, 9, 12, '2026-02-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(165, 9, 12, '2026-02-17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(166, 9, 12, '2026-02-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(167, 9, 12, '2026-02-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(168, 9, 12, '2026-02-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(169, 9, 12, '2026-02-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(170, 9, 12, '2026-02-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(171, 9, 12, '2026-02-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(172, 9, 12, '2026-02-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(173, 9, 12, '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(174, 9, 12, '2026-02-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(175, 9, 12, '2026-02-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(176, 9, 12, '2026-02-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(177, 9, 12, '2026-03-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(178, 9, 12, '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(179, 9, 12, '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:35:52', '2026-02-24 06:44:53', 4, 4),
(180, 10, 12, '2026-02-01', '08:00:00', '12:00:00', NULL, NULL, NULL, NULL, NULL, NULL, 0, 15.00, 25, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(181, 10, 12, '2026-02-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(182, 10, 12, '2026-02-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(183, 10, 12, '2026-02-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(184, 10, 12, '2026-02-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(185, 10, 12, '2026-02-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'SSS', 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(186, 10, 12, '2026-02-07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'PHILHEALTH', 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(187, 10, 12, '2026-02-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'PAGIBIG', 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(188, 10, 12, '2026-02-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'CA OCT. 23, 2025', 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(189, 10, 12, '2026-02-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(190, 10, 12, '2026-02-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(191, 10, 12, '2026-02-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(192, 10, 12, '2026-02-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(193, 10, 12, '2026-02-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(194, 10, 7, '2026-02-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'SSS', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(195, 10, 7, '2026-02-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'PHILHEALTH', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(196, 10, 7, '2026-02-17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, 'PAGIBIG', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(197, 10, 7, '2026-02-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(198, 10, 7, '2026-02-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(199, 10, 7, '2026-02-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(200, 10, 7, '2026-02-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(201, 10, 7, '2026-02-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(202, 10, 7, '2026-02-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(203, 10, 7, '2026-02-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(204, 10, 7, '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(205, 10, 7, '2026-02-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(206, 10, 7, '2026-02-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, '', 'automatic', '2026-02-24 06:47:31', '2026-02-24 07:06:41', 4, 4),
(207, 10, 12, '2026-02-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(208, 10, 12, '2026-03-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(209, 10, 12, '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL),
(210, 10, 12, '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, NULL, 0, 0, NULL, 'automatic', '2026-02-24 06:47:31', '2026-02-24 06:47:31', 4, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int NOT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `basic_monthly_salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `hire_date` date DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive','resigned','terminated') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_code`, `full_name`, `position`, `department`, `basic_monthly_salary`, `hire_date`, `email`, `contact_number`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 'EMP-001-2026', 'Emman', 'Dev', 'IT', 13000.00, '2026-02-20', NULL, NULL, NULL, 'active', '2026-02-20 03:50:10', '2026-02-24 06:07:36'),
(3, 'EMP-003-2026', 'JC', 'Dev', 'IT', 5000.00, '2026-02-10', NULL, NULL, NULL, 'active', '2026-02-20 07:18:46', '2026-02-20 07:18:46'),
(4, 'EMP-004-2026', 'Jared', 'Dev', 'IT', 40000.00, '2026-02-20', NULL, NULL, NULL, 'active', '2026-02-20 08:20:31', '2026-02-20 08:20:31'),
(6, 'EMP-005-2026', 'John Jared Bueta', 'Devil', 'IT', 13000.00, '2026-02-23', 'johnjaredbueta@gmail.com', '09123456789', 'San Pablo City', 'active', '2026-02-23 07:49:41', '2026-02-24 05:45:57'),
(8, 'EMP-007-2026', 'kimkim', 'it', 'dev', 10000.00, '2026-03-05', 'kimkim@gmail.com', '09123781732', 'hsodjsjvodvovpodskpewjjcosjcoisdjcis', 'active', '2026-02-24 05:35:30', '2026-02-24 05:35:30'),
(9, 'EMP-2026-0001', 'kimyamamotos', NULL, NULL, 0.00, NULL, NULL, NULL, NULL, 'active', '2026-02-24 06:35:52', '2026-02-24 06:35:52'),
(10, 'EMP-2026-0002', 'Ejan', NULL, NULL, 13000.00, NULL, NULL, NULL, NULL, 'active', '2026-02-24 06:47:31', '2026-02-24 07:06:41');

-- --------------------------------------------------------

--
-- Table structure for table `employee_deductions`
--

CREATE TABLE `employee_deductions` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `payroll_period_id` int DEFAULT NULL,
  `deduction_type_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `deduction_date` date NOT NULL,
  `status` enum('pending','applied','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int NOT NULL,
  `holiday_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_type` enum('regular','special','special_working') COLLATE utf8mb4_unicode_ci DEFAULT 'regular',
  `multiplier` decimal(3,2) DEFAULT '2.00',
  `is_recurring` tinyint(1) DEFAULT '0',
  `year` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_records`
--

CREATE TABLE `leave_records` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `leave_type` enum('sick','vacation','emergency','maternity','paternity','unpaid') COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(5,2) NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','success','warning','danger') COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fa-info-circle',
  `link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `icon`, `link`, `is_read`, `created_at`, `read_at`) VALUES
(1, 1, 'Welcome to TheBigFive Payroll', 'Your notification system is now active!', 'success', 'fa-check-circle', 'dashboard.php', 0, '2026-02-20 07:40:03', NULL),
(3, 7, 'Welcome to TheBigFive Payroll', 'Your notification system is now active!', 'success', 'fa-check-circle', 'dashboard.php', 0, '2026-02-20 07:40:03', NULL),
(5, 2, 'Welcome to TheBigFive Payroll', 'Your account has been successfully created. Start by exploring the dashboard.', 'success', 'fa-check-circle', 'dashboard.php', 0, '2026-02-23 05:59:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `overtime_records`
--

CREATE TABLE `overtime_records` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `dtr_record_id` int DEFAULT NULL,
  `overtime_date` date NOT NULL,
  `overtime_hours` decimal(5,2) NOT NULL,
  `overtime_type` enum('regular','rest_day','holiday','special_holiday') COLLATE utf8mb4_unicode_ci DEFAULT 'regular',
  `multiplier` decimal(3,2) DEFAULT '1.25',
  `overtime_pay` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','approved','rejected','paid') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_computations`
--

CREATE TABLE `payroll_computations` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `payroll_period_id` int NOT NULL,
  `basic_monthly_salary` decimal(10,2) NOT NULL,
  `per_day_rate` decimal(10,2) NOT NULL,
  `per_hour_rate` decimal(10,4) NOT NULL,
  `per_minute_rate` decimal(10,6) NOT NULL,
  `total_work_days` decimal(5,2) DEFAULT '0.00',
  `total_work_hours` decimal(7,2) DEFAULT '0.00',
  `total_late_minutes` int DEFAULT '0',
  `total_late_hours` decimal(5,2) DEFAULT '0.00',
  `total_undertime_hours` decimal(5,2) DEFAULT '0.00',
  `total_ot_hours` decimal(5,2) DEFAULT '0.00',
  `total_absent_days` decimal(5,2) DEFAULT '0.00',
  `basic_pay` decimal(10,2) DEFAULT '0.00',
  `ot_pay` decimal(10,2) DEFAULT '0.00',
  `late_deduction` decimal(10,2) DEFAULT '0.00',
  `undertime_deduction` decimal(10,2) DEFAULT '0.00',
  `absent_deduction` decimal(10,2) DEFAULT '0.00',
  `cash_advance` decimal(10,2) DEFAULT '0.00',
  `sss_contribution` decimal(10,2) DEFAULT '0.00',
  `philhealth_contribution` decimal(10,2) DEFAULT '0.00',
  `pagibig_contribution` decimal(10,2) DEFAULT '0.00',
  `withholding_tax` decimal(10,2) DEFAULT '0.00',
  `other_deductions` decimal(10,2) DEFAULT '0.00',
  `other_deductions_notes` text COLLATE utf8mb4_unicode_ci,
  `total_earnings` decimal(10,2) DEFAULT '0.00',
  `total_deductions` decimal(10,2) DEFAULT '0.00',
  `net_pay` decimal(10,2) DEFAULT '0.00',
  `f1_value` decimal(10,2) DEFAULT '0.00',
  `f2_value` decimal(10,2) DEFAULT '0.00',
  `status` enum('draft','computed','approved','paid') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `computed_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `computed_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `trainings_count` int DEFAULT '0',
  `payment_per_trainee` decimal(10,2) DEFAULT '0.00',
  `trainings_cost` decimal(10,2) DEFAULT '0.00',
  `days_office` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll_computations`
--

INSERT INTO `payroll_computations` (`id`, `employee_id`, `payroll_period_id`, `basic_monthly_salary`, `per_day_rate`, `per_hour_rate`, `per_minute_rate`, `total_work_days`, `total_work_hours`, `total_late_minutes`, `total_late_hours`, `total_undertime_hours`, `total_ot_hours`, `total_absent_days`, `basic_pay`, `ot_pay`, `late_deduction`, `undertime_deduction`, `absent_deduction`, `cash_advance`, `sss_contribution`, `philhealth_contribution`, `pagibig_contribution`, `withholding_tax`, `other_deductions`, `other_deductions_notes`, `total_earnings`, `total_deductions`, `net_pay`, `f1_value`, `f2_value`, `status`, `remarks`, `computed_at`, `approved_at`, `paid_at`, `created_at`, `updated_at`, `computed_by`, `approved_by`, `trainings_count`, `trainings_cost`, `days_office`) VALUES
(1, 1, 2, 25000.00, 1190.48, 148.8100, 2.480000, 10.00, 80.00, 0, 0.50, 0.00, 5.00, 0.00, 11904.80, 931.06, 74.40, 0.00, 0.00, 0.00, 1125.00, 437.50, 100.00, 0.00, 0.00, NULL, 12835.86, 1662.50, 11173.36, 0.00, 0.00, 'paid', NULL, NULL, NULL, NULL, '2026-01-16 06:30:00', '2026-02-23 06:52:08', NULL, NULL, 0, 0.00, 0),
(2, 1, 3, 25000.00, 1190.48, 148.8100, 2.480000, 11.00, 88.00, 0, 0.00, 0.50, 3.00, 0.00, 13095.28, 558.64, 0.00, 74.40, 0.00, 0.00, 1125.00, 437.50, 100.00, 0.00, 0.00, NULL, 13653.92, 1662.50, 11991.42, 0.00, 0.00, 'paid', NULL, NULL, NULL, NULL, '2026-02-01 06:30:00', '2026-02-23 06:52:08', NULL, NULL, 0, 0.00, 0),
(3, 1, 4, 25000.00, 833.33, 104.1667, 2.480000, 11.00, 88.00, 0, 0.42, 0.00, 0.00, 2.00, 9166.67, 0.00, 43.40, 0.00, 1666.67, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, 9166.67, 1710.07, 7456.60, 0.00, 0.00, 'computed', NULL, '2026-02-24 01:42:55', NULL, NULL, '2026-02-16 06:30:00', '2026-02-24 01:42:55', 2, NULL, 0, 0.00, 0),
(7, 3, 2, 40000.00, 1904.76, 238.1000, 3.970000, 10.00, 80.00, 0, 0.00, 0.00, 8.00, 0.00, 19047.60, 2380.80, 0.00, 0.00, 0.00, 0.00, 1760.00, 700.00, 100.00, 500.00, 0.00, NULL, 21428.40, 3060.00, 18368.40, 0.00, 0.00, 'paid', NULL, NULL, NULL, NULL, '2026-01-16 06:40:00', '2026-02-23 06:52:08', NULL, NULL, 0, 0.00, 0),
(8, 3, 3, 40000.00, 1904.76, 238.1000, 3.970000, 11.00, 88.00, 0, 0.00, 0.00, 6.00, 0.00, 20952.36, 1785.60, 0.00, 0.00, 0.00, 0.00, 1760.00, 700.00, 100.00, 500.00, 0.00, NULL, 22737.96, 3060.00, 19677.96, 0.00, 0.00, 'paid', NULL, NULL, NULL, NULL, '2026-02-01 06:40:00', '2026-02-23 06:52:08', NULL, NULL, 0, 0.00, 0),
(9, 4, 3, 5000.00, 238.10, 29.7600, 0.500000, 14.00, 112.00, 0, 0.00, 0.00, 0.00, 0.00, 3333.40, 0.00, 0.00, 0.00, 0.00, 0.00, 135.00, 175.00, 100.00, 0.00, 0.00, NULL, 3333.40, 410.00, 2923.40, 0.00, 0.00, 'paid', NULL, NULL, NULL, NULL, '2026-02-01 06:45:00', '2026-02-23 06:52:08', NULL, NULL, 0, 0.00, 0),
(10, 4, 4, 5000.00, 238.10, 29.7600, 0.500000, 10.00, 80.00, 0, 0.00, 0.00, 0.00, 0.00, 2381.00, 0.00, 0.00, 0.00, 0.00, 0.00, 135.00, 175.00, 100.00, 0.00, 0.00, NULL, 2381.00, 410.00, 1971.00, 0.00, 0.00, 'paid', NULL, NULL, NULL, NULL, '2026-02-16 06:45:00', '2026-02-23 06:52:08', NULL, NULL, 0, 0.00, 0),
(14, 1, 7, 25000.00, 833.33, 104.1667, 1.736111, 11.00, 0.00, 0, 25.00, 0.00, 0.00, 2.00, 789.93, 0.00, 43.40, 0.00, 1666.66, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, 789.93, 1710.06, -920.13, 0.00, 0.00, 'computed', NULL, NULL, NULL, NULL, '2026-02-24 01:12:38', '2026-02-24 01:12:38', NULL, NULL, 0, 0.00, 11),
(16, 6, 7, 13000.00, 433.33, 54.1667, 0.902778, 5.00, 0.00, 0, 5.00, 0.00, 4.00, 8.50, 2216.30, 270.84, 4.51, 0.00, 3466.64, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, 2487.14, 3687.81, -1200.67, 0.00, 0.00, 'computed', NULL, NULL, NULL, NULL, '2026-02-24 03:35:42', '2026-02-24 03:35:42', NULL, NULL, 0, 0.00, 5),
(17, 6, 8, 13000.00, 433.33, 54.1667, 0.902778, 1.00, 0.00, 0, 5.00, 0.00, 0.00, 14.00, 428.82, 0.00, 4.51, 0.00, 6066.62, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, 428.82, 6071.13, -5642.31, 0.00, 0.00, 'computed', NULL, NULL, NULL, NULL, '2026-02-24 03:41:04', '2026-02-24 03:41:04', NULL, NULL, 0, 0.00, 1),
(18, 1, 8, 13000.00, 433.33, 54.1667, 0.902778, 2.00, 0.00, 0, 0.00, 2.00, 2.00, 13.50, 677.08, 135.42, 0.00, 108.33, 5633.29, 0.00, 317.50, 125.00, 100.00, 0.00, 0.00, NULL, 812.50, 6500.78, -5688.28, 0.00, 0.00, 'computed', NULL, NULL, NULL, NULL, '2026-02-24 05:33:44', '2026-02-24 05:33:44', NULL, NULL, 0, 0.00, 2),
(19, 6, 9, 13000.00, 433.33, 54.1667, 0.902778, 4.00, 0.00, 0, 0.00, 0.00, 2.00, 11.50, 1652.07, 135.42, 0.00, 0.00, 4766.63, 0.00, 317.50, 125.00, 100.00, 0.00, 0.00, NULL, 1787.49, 5525.79, -3738.30, 0.00, 0.00, 'computed', NULL, NULL, NULL, NULL, '2026-02-24 05:45:57', '2026-02-24 05:45:57', NULL, NULL, 0, 0.00, 4),
(20, 1, 10, 13000.00, 433.33, 54.1667, 0.902778, 0.00, 0.00, 0, 0.00, 0.00, 0.00, 15.00, 0.00, 0.00, 0.00, 0.00, 6499.95, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, 6499.95, -6499.95, 0.00, 0.00, 'computed', NULL, NULL, NULL, NULL, '2026-02-24 06:03:27', '2026-02-24 06:03:27', NULL, NULL, 0, 0.00, 0),
(21, 1, 11, 13000.00, 433.33, 54.1667, 0.902778, 16.00, 0.00, 0, 0.00, 0.00, 0.00, 4.00, 0.00, 0.00, 0.00, 0.00, 1733.32, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, 1733.32, -1733.32, 0.00, 0.00, 'computed', NULL, NULL, NULL, NULL, '2026-02-24 06:07:36', '2026-02-24 06:07:36', NULL, NULL, 0, 0.00, 16),
(22, 10, 7, 13000.00, 866.67, 108.3333, 1.805556, 13.00, 0.00, 0, 0.00, 0.00, 0.00, 3.00, 0.00, 0.00, 0.00, 0.00, 2600.01, 0.00, 317.50, 125.00, 100.00, 0.00, 0.00, NULL, 0.00, 3142.51, -3142.51, 0.00, 0.00, 'computed', NULL, '2026-02-24 07:06:41', NULL, NULL, '2026-02-24 07:06:41', '2026-02-24 07:06:41', 4, NULL, 0, 0.00, 13);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_history`
--

CREATE TABLE `payroll_history` (
  `id` int NOT NULL,
  `payroll_computation_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_changed` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `changed_by` int DEFAULT NULL,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_periods`
--

CREATE TABLE `payroll_periods` (
  `id` int NOT NULL,
  `period_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `pay_date` date DEFAULT NULL,
  `status` enum('draft','processing','completed','paid') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll_periods`
--

INSERT INTO `payroll_periods` (`id`, `period_name`, `start_date`, `end_date`, `pay_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Oct 13 - Oct 31, 2026', '2026-10-13', '2026-10-31', '2026-11-05', 'draft', 4, '2026-02-22 12:02:46', '2026-02-22 12:02:46'),
(2, 'January 2026 - 1st Half', '2026-01-01', '2026-01-15', '2026-01-20', 'completed', NULL, '2026-01-16 02:00:00', '2026-02-23 06:52:08'),
(3, 'January 2026 - 2nd Half', '2026-01-16', '2026-01-31', '2026-02-05', 'completed', NULL, '2026-02-01 02:00:00', '2026-02-23 06:52:08'),
(4, 'February 2026 - 1st Half', '2026-02-01', '2026-02-15', '2026-02-20', 'completed', NULL, '2026-02-16 02:00:00', '2026-02-23 06:52:08'),
(5, 'February 2026 - 2nd Half', '2026-02-16', '2026-02-28', '2026-03-05', 'processing', NULL, '2026-02-23 02:00:00', '2026-02-23 06:52:08'),
(6, 'Dec 31 - Jan 02, 1900', '1899-12-31', '1900-01-02', '1900-01-07', 'draft', 4, '2026-02-23 07:05:59', '2026-02-23 07:05:59'),
(7, 'February 2026', '2026-02-15', '2026-02-27', NULL, 'draft', 4, '2026-02-24 01:12:38', '2026-02-24 01:12:38'),
(8, 'February 2026', '2026-02-28', '2026-03-14', NULL, 'draft', 4, '2026-02-24 03:41:04', '2026-02-24 03:41:04'),
(9, 'March 2026', '2026-03-31', '2026-04-14', NULL, 'draft', 4, '2026-02-24 05:45:57', '2026-02-24 05:45:57'),
(10, 'January 2026', '2026-01-31', '2026-02-14', NULL, 'draft', 4, '2026-02-24 06:03:27', '2026-02-24 06:03:27'),
(11, 'May 2026', '2026-05-15', '2026-05-30', NULL, 'draft', 4, '2026-02-24 06:07:36', '2026-02-24 06:07:36'),
(12, 'Feb 01 - Mar 03, 2026', '2026-02-01', '2026-03-03', '2026-03-08', 'draft', 4, '2026-02-24 06:35:52', '2026-02-24 06:35:52');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int NOT NULL,
  `position_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `position_name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'cashier', '', '2026-02-23 01:45:49', '2026-02-23 01:45:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','staff','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `status`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'admin', 'admin@thebigfive.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'active', '2026-02-20 00:34:00', '2026-02-20 00:34:00', NULL),
(2, 'staff', 'staff@thebigfive.com', '$2y$12$1IdNgPE7doaCKyqgFB.Vm.oMzHNR3Wsh7DYjOO/qojp0/Zkv72bqW', 'Staff Member', 'staff', 'active', '2026-02-20 00:34:00', '2026-02-24 02:52:24', '2026-02-24 02:52:24'),
(4, 'Administrator', 'thebigfivepayroll@gmail.com', '$2y$10$B/xrWS27K8gfssowctRw8.txvJRpznV2iWhVUK6oC6.P2smJFEPtq', 'Big Five Admin', 'admin', 'active', '2026-02-20 01:37:53', '2026-02-24 05:28:11', '2026-02-24 05:28:11'),
(7, 'Emmanuel', 'emmandalitespiritu66@gmail.com', '$2y$10$00jOzBuKOAqYVtRyc5m/NuHsuNp6WDwucJwwTsd702yVEgmki2kWa', 'Emman', 'admin', 'active', '2026-02-20 07:04:34', '2026-02-20 07:04:41', '2026-02-20 07:04:41'),
(8, 'jc_2', 'jc@testing.com', '$2y$12$EQXyw/3CTNUOqXRN2te4xeRI4uSmq/1LtTFqDQUQaLxQePFgIaynK', 'jc', 'admin', 'active', '2026-02-23 03:20:34', '2026-02-24 03:45:42', '2026-02-24 02:59:00');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_daily_dtr_details`
-- (See below for the actual view)
--
CREATE TABLE `vw_daily_dtr_details` (
`id` int
,`employee_id` int
,`employee_code` varchar(50)
,`employee_name` varchar(100)
,`dtr_date` date
,`day_of_week` varchar(64)
,`am_in` varchar(8)
,`am_out` varchar(8)
,`pm_in` varchar(8)
,`pm_out` varchar(8)
,`ot_in` varchar(8)
,`ot_out` varchar(8)
,`total_work_hours` decimal(5,2)
,`late_minutes` int
,`late_hours` decimal(5,2)
,`undertime_hours` decimal(5,2)
,`daily_ot_hours` decimal(5,2)
,`attendance_status` varchar(8)
,`calculation_mode` enum('automatic','manual')
,`remarks` text
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_employee_dtr_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_employee_dtr_summary` (
`employee_id` int
,`employee_code` varchar(50)
,`employee_name` varchar(100)
,`payroll_period_id` int
,`period_name` varchar(100)
,`start_date` date
,`end_date` date
,`total_work_days` bigint
,`total_work_hours` decimal(27,2)
,`total_late_minutes` decimal(32,0)
,`total_late_hours` decimal(27,2)
,`total_undertime_hours` decimal(27,2)
,`total_ot_hours` decimal(27,2)
,`total_absent_days` decimal(23,0)
,`total_halfdays` decimal(24,1)
,`basic_monthly_salary` decimal(10,2)
,`per_day_rate` decimal(11,2)
,`per_hour_rate` decimal(13,4)
,`per_minute_rate` decimal(15,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_payroll_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_payroll_summary` (
`id` int
,`employee_id` int
,`employee_code` varchar(50)
,`employee_name` varchar(100)
,`period_name` varchar(100)
,`start_date` date
,`end_date` date
,`basic_monthly_salary` decimal(10,2)
,`per_day_rate` decimal(10,2)
,`per_hour_rate` decimal(10,4)
,`per_minute_rate` decimal(10,6)
,`total_work_days` decimal(5,2)
,`total_work_hours` decimal(7,2)
,`total_late_hours` decimal(5,2)
,`total_undertime_hours` decimal(5,2)
,`total_ot_hours` decimal(5,2)
,`total_absent_days` decimal(5,2)
,`basic_pay` decimal(10,2)
,`ot_pay` decimal(10,2)
,`total_earnings` decimal(10,2)
,`late_deduction` decimal(10,2)
,`undertime_deduction` decimal(10,2)
,`absent_deduction` decimal(10,2)
,`cash_advance` decimal(10,2)
,`sss_contribution` decimal(10,2)
,`philhealth_contribution` decimal(10,2)
,`pagibig_contribution` decimal(10,2)
,`withholding_tax` decimal(10,2)
,`other_deductions` decimal(10,2)
,`total_deductions` decimal(10,2)
,`net_pay` decimal(10,2)
,`status` enum('draft','computed','approved','paid')
,`computed_at` timestamp
,`approved_at` timestamp
,`paid_at` timestamp
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_logs`
--
ALTER TABLE `account_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_backup_type` (`backup_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `backup_settings`
--
ALTER TABLE `backup_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `deduction_types`
--
ALTER TABLE `deduction_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `deduction_code` (`deduction_code`),
  ADD KEY `idx_deduction_code` (`deduction_code`);

--
-- Indexes for table `dtr_records`
--
ALTER TABLE `dtr_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_date` (`employee_id`,`dtr_date`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_employee_date` (`employee_id`,`dtr_date`),
  ADD KEY `idx_payroll_period` (`payroll_period_id`),
  ADD KEY `idx_dtr_date` (`dtr_date`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD KEY `idx_employee_code` (`employee_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_full_name` (`full_name`);

--
-- Indexes for table `employee_deductions`
--
ALTER TABLE `employee_deductions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deduction_type_id` (`deduction_type_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_period` (`payroll_period_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_holiday_date` (`holiday_date`),
  ADD KEY `idx_year` (`year`);

--
-- Indexes for table `leave_records`
--
ALTER TABLE `leave_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created` (`created_at` DESC);

--
-- Indexes for table `overtime_records`
--
ALTER TABLE `overtime_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dtr_record_id` (`dtr_record_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_overtime_date` (`overtime_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `payroll_computations`
--
ALTER TABLE `payroll_computations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_period` (`employee_id`,`payroll_period_id`),
  ADD KEY `computed_by` (`computed_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_period` (`payroll_period_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `payroll_history`
--
ALTER TABLE `payroll_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_payroll_computation` (`payroll_computation_id`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- Indexes for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_period_dates` (`start_date`,`end_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `position_name` (`position_name`),
  ADD KEY `idx_position_name` (`position_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_logs`
--
ALTER TABLE `account_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `backup_history`
--
ALTER TABLE `backup_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_settings`
--
ALTER TABLE `backup_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `deduction_types`
--
ALTER TABLE `deduction_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `dtr_records`
--
ALTER TABLE `dtr_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=211;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employee_deductions`
--
ALTER TABLE `employee_deductions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_records`
--
ALTER TABLE `leave_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `overtime_records`
--
ALTER TABLE `overtime_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_computations`
--
ALTER TABLE `payroll_computations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `payroll_history`
--
ALTER TABLE `payroll_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

-- --------------------------------------------------------

--
-- Structure for view `vw_daily_dtr_details`
--
DROP TABLE IF EXISTS `vw_daily_dtr_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_daily_dtr_details`  AS SELECT `d`.`id` AS `id`, `d`.`employee_id` AS `employee_id`, `e`.`employee_code` AS `employee_code`, `e`.`full_name` AS `employee_name`, `d`.`dtr_date` AS `dtr_date`, date_format(`d`.`dtr_date`,'%W') AS `day_of_week`, time_format(`d`.`am_time_in`,'%h:%i %p') AS `am_in`, time_format(`d`.`am_time_out`,'%h:%i %p') AS `am_out`, time_format(`d`.`pm_time_in`,'%h:%i %p') AS `pm_in`, time_format(`d`.`pm_time_out`,'%h:%i %p') AS `pm_out`, time_format(`d`.`ot_time_in`,'%h:%i %p') AS `ot_in`, time_format(`d`.`ot_time_out`,'%h:%i %p') AS `ot_out`, `d`.`total_work_hours` AS `total_work_hours`, `d`.`late_minutes` AS `late_minutes`, `d`.`late_hours` AS `late_hours`, `d`.`undertime_hours` AS `undertime_hours`, `d`.`daily_ot_hours` AS `daily_ot_hours`, (case when `d`.`is_absent` then 'ABSENT' when `d`.`is_halfday` then 'HALFDAY' when `d`.`is_variable` then 'VARIABLE' else 'PRESENT' end) AS `attendance_status`, `d`.`calculation_mode` AS `calculation_mode`, `d`.`remarks` AS `remarks` FROM (`dtr_records` `d` join `employees` `e` on((`d`.`employee_id` = `e`.`id`))) ORDER BY `d`.`dtr_date` DESC, `e`.`full_name` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_employee_dtr_summary`
--
DROP TABLE IF EXISTS `vw_employee_dtr_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_employee_dtr_summary`  AS SELECT `e`.`id` AS `employee_id`, `e`.`employee_code` AS `employee_code`, `e`.`full_name` AS `employee_name`, `pp`.`id` AS `payroll_period_id`, `pp`.`period_name` AS `period_name`, `pp`.`start_date` AS `start_date`, `pp`.`end_date` AS `end_date`, count(distinct `d`.`dtr_date`) AS `total_work_days`, sum(`d`.`total_work_hours`) AS `total_work_hours`, sum(`d`.`late_minutes`) AS `total_late_minutes`, sum(`d`.`late_hours`) AS `total_late_hours`, sum(`d`.`undertime_hours`) AS `total_undertime_hours`, sum(`d`.`daily_ot_hours`) AS `total_ot_hours`, sum((case when (`d`.`is_absent` = true) then 1 else 0 end)) AS `total_absent_days`, sum((case when (`d`.`is_halfday` = true) then 0.5 else 0 end)) AS `total_halfdays`, `e`.`basic_monthly_salary` AS `basic_monthly_salary`, round((`e`.`basic_monthly_salary` / 26),2) AS `per_day_rate`, round(((`e`.`basic_monthly_salary` / 26) / 8),4) AS `per_hour_rate`, round((((`e`.`basic_monthly_salary` / 26) / 8) / 60),6) AS `per_minute_rate` FROM ((`employees` `e` left join `dtr_records` `d` on((`e`.`id` = `d`.`employee_id`))) left join `payroll_periods` `pp` on((`d`.`payroll_period_id` = `pp`.`id`))) WHERE (`e`.`status` = 'active') GROUP BY `e`.`id`, `e`.`employee_code`, `e`.`full_name`, `pp`.`id`, `pp`.`period_name`, `pp`.`start_date`, `pp`.`end_date`, `e`.`basic_monthly_salary` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_payroll_summary`
--
DROP TABLE IF EXISTS `vw_payroll_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_payroll_summary`  AS SELECT `pc`.`id` AS `id`, `pc`.`employee_id` AS `employee_id`, `e`.`employee_code` AS `employee_code`, `e`.`full_name` AS `employee_name`, `pp`.`period_name` AS `period_name`, `pp`.`start_date` AS `start_date`, `pp`.`end_date` AS `end_date`, `pc`.`basic_monthly_salary` AS `basic_monthly_salary`, `pc`.`per_day_rate` AS `per_day_rate`, `pc`.`per_hour_rate` AS `per_hour_rate`, `pc`.`per_minute_rate` AS `per_minute_rate`, `pc`.`total_work_days` AS `total_work_days`, `pc`.`total_work_hours` AS `total_work_hours`, `pc`.`total_late_hours` AS `total_late_hours`, `pc`.`total_undertime_hours` AS `total_undertime_hours`, `pc`.`total_ot_hours` AS `total_ot_hours`, `pc`.`total_absent_days` AS `total_absent_days`, `pc`.`basic_pay` AS `basic_pay`, `pc`.`ot_pay` AS `ot_pay`, `pc`.`total_earnings` AS `total_earnings`, `pc`.`late_deduction` AS `late_deduction`, `pc`.`undertime_deduction` AS `undertime_deduction`, `pc`.`absent_deduction` AS `absent_deduction`, `pc`.`cash_advance` AS `cash_advance`, `pc`.`sss_contribution` AS `sss_contribution`, `pc`.`philhealth_contribution` AS `philhealth_contribution`, `pc`.`pagibig_contribution` AS `pagibig_contribution`, `pc`.`withholding_tax` AS `withholding_tax`, `pc`.`other_deductions` AS `other_deductions`, `pc`.`total_deductions` AS `total_deductions`, `pc`.`net_pay` AS `net_pay`, `pc`.`status` AS `status`, `pc`.`computed_at` AS `computed_at`, `pc`.`approved_at` AS `approved_at`, `pc`.`paid_at` AS `paid_at` FROM ((`payroll_computations` `pc` join `employees` `e` on((`pc`.`employee_id` = `e`.`id`))) join `payroll_periods` `pp` on((`pc`.`payroll_period_id` = `pp`.`id`))) ORDER BY `pp`.`end_date` DESC, `e`.`full_name` ASC ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_logs`
--
ALTER TABLE `account_logs`
  ADD CONSTRAINT `account_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `backup_settings`
--
ALTER TABLE `backup_settings`
  ADD CONSTRAINT `backup_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dtr_records`
--
ALTER TABLE `dtr_records`
  ADD CONSTRAINT `dtr_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dtr_records_ibfk_2` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dtr_records_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dtr_records_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_deductions`
--
ALTER TABLE `employee_deductions`
  ADD CONSTRAINT `employee_deductions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_deductions_ibfk_2` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employee_deductions_ibfk_3` FOREIGN KEY (`deduction_type_id`) REFERENCES `deduction_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_deductions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_records`
--
ALTER TABLE `leave_records`
  ADD CONSTRAINT `leave_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_records_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `overtime_records`
--
ALTER TABLE `overtime_records`
  ADD CONSTRAINT `overtime_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `overtime_records_ibfk_2` FOREIGN KEY (`dtr_record_id`) REFERENCES `dtr_records` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `overtime_records_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payroll_computations`
--
ALTER TABLE `payroll_computations`
  ADD CONSTRAINT `payroll_computations_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_computations_ibfk_2` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_computations_ibfk_3` FOREIGN KEY (`computed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payroll_computations_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payroll_history`
--
ALTER TABLE `payroll_history`
  ADD CONSTRAINT `payroll_history_ibfk_1` FOREIGN KEY (`payroll_computation_id`) REFERENCES `payroll_computations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD CONSTRAINT `payroll_periods_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
