-- Add missing columns for payroll_list DTR functionality
-- These columns are needed for payroll_list.php DTR view features

USE thebigfive_payroll;

-- Add late_start and end_time columns for DTR configuration
ALTER TABLE payroll_computations 
ADD COLUMN IF NOT EXISTS late_start TIME DEFAULT '07:35' COMMENT 'Late start time threshold',
ADD COLUMN IF NOT EXISTS end_time TIME DEFAULT '17:00' COMMENT 'End time for work day';

-- Add training payment columns
ALTER TABLE payroll_computations 
ADD COLUMN IF NOT EXISTS training_amount DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Training payment amount',
ADD COLUMN IF NOT EXISTS training_remarks TEXT NULL COMMENT 'Training payment remarks';

-- Show current structure
DESCRIBE payroll_computations;
