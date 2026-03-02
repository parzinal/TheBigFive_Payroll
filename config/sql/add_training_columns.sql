-- Add training columns to payroll_computations table
-- Run this migration to add training tracking fields

ALTER TABLE payroll_computations 
ADD COLUMN IF NOT EXISTS trainings_count INT DEFAULT 0 AFTER other_deductions_notes,
ADD COLUMN IF NOT EXISTS trainings_cost DECIMAL(10, 2) DEFAULT 0.00 AFTER trainings_count,
ADD COLUMN IF NOT EXISTS days_office INT DEFAULT 0 AFTER trainings_cost;

-- If the above fails (MySQL version doesn't support IF NOT EXISTS), use this:
-- ALTER TABLE payroll_computations ADD COLUMN trainings_count INT DEFAULT 0;
-- ALTER TABLE payroll_computations ADD COLUMN trainings_cost DECIMAL(10, 2) DEFAULT 0.00;
-- ALTER TABLE payroll_computations ADD COLUMN days_office INT DEFAULT 0;
