-- Add employee_classification column to employees table
-- Run this migration to add the Employee Classification field

ALTER TABLE employees 
ADD COLUMN IF NOT EXISTS classification ENUM('Fix Rate', 'Trainer') NULL DEFAULT NULL AFTER status;

-- If the above fails (MySQL version doesn't support IF NOT EXISTS for ALTER), use this:
-- ALTER TABLE employees ADD COLUMN classification ENUM('Fix Rate', 'Trainer') NULL DEFAULT NULL AFTER status;
