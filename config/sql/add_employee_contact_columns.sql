-- Add employee contact information columns
-- For existing databases that need to be updated with email, contact_number, and address fields
-- MySQL-compatible version (run each statement separately, ignore errors if column exists)

USE thebigfive_payroll;

-- Check and add columns using a stored procedure
DELIMITER //

DROP PROCEDURE IF EXISTS AddEmployeeContactColumns//

CREATE PROCEDURE AddEmployeeContactColumns()
BEGIN
    -- Add email column if it doesn't exist
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = 'thebigfive_payroll' 
        AND TABLE_NAME = 'employees' 
        AND COLUMN_NAME = 'email'
    ) THEN
        ALTER TABLE employees ADD COLUMN email VARCHAR(100) NULL AFTER hire_date;
    END IF;
    
    -- Add contact_number column if it doesn't exist
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = 'thebigfive_payroll' 
        AND TABLE_NAME = 'employees' 
        AND COLUMN_NAME = 'contact_number'
    ) THEN
        ALTER TABLE employees ADD COLUMN contact_number VARCHAR(20) NULL AFTER email;
    END IF;
    
    -- Add address column if it doesn't exist
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = 'thebigfive_payroll' 
        AND TABLE_NAME = 'employees' 
        AND COLUMN_NAME = 'address'
    ) THEN
        ALTER TABLE employees ADD COLUMN address TEXT NULL AFTER contact_number;
    END IF;
END//

DELIMITER ;

-- Execute the procedure
CALL AddEmployeeContactColumns();

-- Clean up
DROP PROCEDURE IF EXISTS AddEmployeeContactColumns;
    
-- Display confirmation
SELECT 'Employee contact columns added successfully!' AS status;
