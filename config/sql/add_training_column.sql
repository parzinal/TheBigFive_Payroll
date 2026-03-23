-- Add is_training column to dtr_records table
-- This allows tracking which days were marked as training days

USE thebigfive_payroll;

-- Add is_training column if it doesn't exist
ALTER TABLE dtr_records 
ADD COLUMN IF NOT EXISTS is_training BOOLEAN DEFAULT FALSE AFTER is_absent;

-- Add comment
ALTER TABLE dtr_records 
MODIFY COLUMN is_training BOOLEAN DEFAULT FALSE COMMENT 'Whether this day is a training day';
