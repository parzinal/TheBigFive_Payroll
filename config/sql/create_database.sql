-- Create Database for TheBigFive Payroll System
CREATE DATABASE IF NOT EXISTS thebigfive_payroll;

USE thebigfive_payroll;

-- Create users table with role support
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'staff', 'user') NOT NULL DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
-- Password: admin123 (hashed using password_hash with PASSWORD_DEFAULT)
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('admin', 'admin@thebigfive.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'active');

-- Insert sample staff user
-- Password: staff123 (hashed using password_hash with PASSWORD_DEFAULT)
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('staff', 'staff@thebigfive.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff Member', 'staff', 'active');

-- Insert sample regular user
-- Password: user123 (hashed using password_hash with PASSWORD_DEFAULT)
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('user', 'user@thebigfive.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Regular User', 'user', 'active');

-- =====================================================
-- EMPLOYEE INFORMATION TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    employee_code VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    position VARCHAR(100) NULL,
    department VARCHAR(100) NULL,
    basic_monthly_salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    hire_date DATE NULL,
    email VARCHAR(100) NULL,
    contact_number VARCHAR(20) NULL,
    address TEXT NULL,
    status ENUM('active', 'inactive', 'resigned', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_code (employee_code),
    INDEX idx_status (status),
    INDEX idx_full_name (full_name),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PAYROLL PERIODS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS payroll_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    pay_date DATE NULL,
    status ENUM('draft', 'processing', 'completed', 'paid') DEFAULT 'draft',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_period_dates (start_date, end_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DTR (DAILY TIME RECORD) TABLE
-- Based on the DTR Calculator template
-- =====================================================
CREATE TABLE IF NOT EXISTS dtr_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_period_id INT NULL,
    dtr_date DATE NOT NULL,
    
    -- AM Schedule
    am_time_in TIME NULL,
    am_time_out TIME NULL,
    
    -- PM Schedule
    pm_time_in TIME NULL,
    pm_time_out TIME NULL,
    
    -- OT (Overtime) Column
    ot_time_in TIME NULL,
    ot_time_out TIME NULL,
    
    -- Halfday tracking
    halfday_in TIME NULL,
    halfday_out TIME NULL,
    is_halfday BOOLEAN DEFAULT FALSE,
    
    -- Calculated Time Values (in hours and minutes)
    total_work_hours DECIMAL(5, 2) DEFAULT 0.00,  -- Total work hours
    late_minutes INT DEFAULT 0,  -- Late in minutes
    late_hours DECIMAL(5, 2) DEFAULT 0.00,  -- Late in hours
    undertime_minutes INT DEFAULT 0,  -- Undertime in minutes
    undertime_hours DECIMAL(5, 2) DEFAULT 0.00,  -- Undertime in hours
    daily_ot_hours DECIMAL(5, 2) DEFAULT 0.00,  -- Daily overtime hours
    
    -- Attendance Status
    is_absent BOOLEAN DEFAULT FALSE,
    is_variable BOOLEAN DEFAULT FALSE,  -- Variable schedule flag
    
    -- Notes and Remarks
    remarks TEXT NULL,
    calculation_mode ENUM('automatic', 'manual') DEFAULT 'automatic',
    
    -- Audit fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_by INT NULL,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_employee_date (employee_id, dtr_date),
    INDEX idx_employee_date (employee_id, dtr_date),
    INDEX idx_payroll_period (payroll_period_id),
    INDEX idx_dtr_date (dtr_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PAYROLL COMPUTATION TABLE
-- Stores computed payroll data per employee per period
-- =====================================================
CREATE TABLE IF NOT EXISTS payroll_computations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_period_id INT NOT NULL,
    
    -- Basic Salary Information
    basic_monthly_salary DECIMAL(10, 2) NOT NULL,
    per_day_rate DECIMAL(10, 2) NOT NULL,
    per_hour_rate DECIMAL(10, 4) NOT NULL,
    per_minute_rate DECIMAL(10, 6) NOT NULL,
    
    -- Work Summary
    total_work_days DECIMAL(5, 2) DEFAULT 0.00,
    total_work_hours DECIMAL(7, 2) DEFAULT 0.00,
    total_late_minutes INT DEFAULT 0,
    total_late_hours DECIMAL(5, 2) DEFAULT 0.00,
    total_undertime_hours DECIMAL(5, 2) DEFAULT 0.00,
    total_ot_hours DECIMAL(5, 2) DEFAULT 0.00,
    total_absent_days DECIMAL(5, 2) DEFAULT 0.00,
    
    -- Basic Pay Calculation
    basic_pay DECIMAL(10, 2) DEFAULT 0.00,
    
    -- Overtime Pay
    ot_pay DECIMAL(10, 2) DEFAULT 0.00,
    
    -- Deductions
    late_deduction DECIMAL(10, 2) DEFAULT 0.00,
    undertime_deduction DECIMAL(10, 2) DEFAULT 0.00,
    absent_deduction DECIMAL(10, 2) DEFAULT 0.00,
    cash_advance DECIMAL(10, 2) DEFAULT 0.00,
    
    -- Government Benefits/Deductions
    sss_contribution DECIMAL(10, 2) DEFAULT 0.00,
    philhealth_contribution DECIMAL(10, 2) DEFAULT 0.00,
    pagibig_contribution DECIMAL(10, 2) DEFAULT 0.00,
    withholding_tax DECIMAL(10, 2) DEFAULT 0.00,
    
    -- Other Deductions
    other_deductions DECIMAL(10, 2) DEFAULT 0.00,
    other_deductions_notes TEXT NULL,
    
    -- Total Calculations
    total_earnings DECIMAL(10, 2) DEFAULT 0.00,
    total_deductions DECIMAL(10, 2) DEFAULT 0.00,
    net_pay DECIMAL(10, 2) DEFAULT 0.00,
    
    -- Additional fields for F1 and F2 from template
    f1_value DECIMAL(10, 2) DEFAULT 0.00,
    f2_value DECIMAL(10, 2) DEFAULT 0.00,
    
    -- Status and Notes
    status ENUM('draft', 'computed', 'approved', 'paid') DEFAULT 'draft',
    remarks TEXT NULL,
    
    -- Audit fields
    computed_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    computed_by INT NULL,
    approved_by INT NULL,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (computed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_employee_period (employee_id, payroll_period_id),
    INDEX idx_employee (employee_id),
    INDEX idx_period (payroll_period_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DEDUCTIONS TABLE
-- Manages various deduction types
-- =====================================================
CREATE TABLE IF NOT EXISTS deduction_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deduction_name VARCHAR(100) NOT NULL,
    deduction_code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT NULL,
    is_mandatory BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_deduction_code (deduction_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default deduction types
INSERT INTO deduction_types (deduction_name, deduction_code, description, is_mandatory) VALUES
('SSS Contribution', 'SSS', 'Social Security System contribution', TRUE),
('PhilHealth Contribution', 'PHILHEALTH', 'Philippine Health Insurance Corporation', TRUE),
('Pag-IBIG Contribution', 'PAGIBIG', 'Home Development Mutual Fund', TRUE),
('Withholding Tax', 'WTAX', 'Income tax withholding', TRUE),
('Cash Advance', 'CA', 'Cash advance deduction', FALSE),
('Late Deduction', 'LATE', 'Deduction for tardiness', FALSE),
('Undertime Deduction', 'UT', 'Deduction for undertime', FALSE),
('Absent Deduction', 'ABSENT', 'Deduction for absences', FALSE);

-- =====================================================
-- EMPLOYEE DEDUCTIONS TABLE
-- Tracks specific deductions per employee
-- =====================================================
CREATE TABLE IF NOT EXISTS employee_deductions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_period_id INT NULL,
    deduction_type_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    notes TEXT NULL,
    deduction_date DATE NOT NULL,
    status ENUM('pending', 'applied', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE SET NULL,
    FOREIGN KEY (deduction_type_id) REFERENCES deduction_types(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_employee (employee_id),
    INDEX idx_period (payroll_period_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- OVERTIME RECORDS TABLE
-- Detailed overtime tracking
-- =====================================================
CREATE TABLE IF NOT EXISTS overtime_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    dtr_record_id INT NULL,
    overtime_date DATE NOT NULL,
    overtime_hours DECIMAL(5, 2) NOT NULL,
    overtime_type ENUM('regular', 'rest_day', 'holiday', 'special_holiday') DEFAULT 'regular',
    multiplier DECIMAL(3, 2) DEFAULT 1.25,  -- Overtime pay multiplier
    overtime_pay DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
    remarks TEXT NULL,
    
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (dtr_record_id) REFERENCES dtr_records(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_employee (employee_id),
    INDEX idx_overtime_date (overtime_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- LEAVE RECORDS TABLE
-- Track employee leaves and absences
-- =====================================================
CREATE TABLE IF NOT EXISTS leave_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('sick', 'vacation', 'emergency', 'maternity', 'paternity', 'unpaid') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(5, 2) NOT NULL,
    reason TEXT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_employee (employee_id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- HOLIDAYS TABLE
-- Manage company holidays for payroll calculations
-- =====================================================
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(100) NOT NULL,
    holiday_date DATE NOT NULL,
    holiday_type ENUM('regular', 'special', 'special_working') DEFAULT 'regular',
    multiplier DECIMAL(3, 2) DEFAULT 2.00,  -- Pay multiplier for working on holiday
    is_recurring BOOLEAN DEFAULT FALSE,
    year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_holiday_date (holiday_date),
    INDEX idx_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PAYROLL HISTORY TABLE
-- Audit trail for payroll changes
-- =====================================================
CREATE TABLE IF NOT EXISTS payroll_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_computation_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    field_changed VARCHAR(100) NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    changed_by INT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    
    FOREIGN KEY (payroll_computation_id) REFERENCES payroll_computations(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_payroll_computation (payroll_computation_id),
    INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
