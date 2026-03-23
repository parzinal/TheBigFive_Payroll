# DTR Excel Import Feature

This document describes the DTR (Daily Time Record) Excel import functionality for the Payroll system.

## Overview

The DTR Import feature allows administrators to:
1. Import employee DTR data from Excel files (.xlsx, .xls, .xlsm)
2. Store DTR records in the database
3. Load existing DTR records for viewing/editing
4. Download a DTR template for data entry

## Installation

### PhpSpreadsheet (Recommended)

For full Excel support including .xls files, install PhpSpreadsheet via Composer:

```bash
cd c:\laragon\www\TheBigFive_Payroll
composer require phpoffice/phpspreadsheet
```

Without PhpSpreadsheet, the system will use a basic XLSX parser that supports .xlsx and .xlsm files, but not older .xls files.

## Usage

### Importing DTR from Excel

1. Go to **Admin > Generate Payroll**
2. Select an **Employee** and **Payroll Period**
3. In the DTR section, use the import area to:
   - Drag and drop an Excel file, or
   - Click to browse and select a file
4. Click **Import DTR** to process the file
5. The DTR table will be populated with the imported data
6. Data is automatically saved to the database

### Excel File Format

The Excel file should have the following columns (header row required):

| Column | Description | Format |
|--------|-------------|--------|
| Date | DTR date | YYYY-MM-DD or MM/DD/YYYY |
| AM In | Morning time in | HH:MM or H:MM AM/PM |
| AM Out | Morning time out | HH:MM or H:MM AM/PM |
| PM In | Afternoon time in | HH:MM or H:MM AM/PM |
| PM Out | Afternoon time out | HH:MM or H:MM AM/PM |
| OT Out | Overtime end time | HH:MM (optional) |
| Halfday In | Half-day start | HH:MM (optional) |
| Halfday Out | Half-day end | HH:MM (optional) |
| Absent | Absent flag | Yes/Y/1/X if absent |
| Remarks | Notes | Text (optional) |

### Downloading Template

Click **Download Template** to get a pre-formatted Excel file with:
- Correct column headers
- Sample data rows
- Instructions sheet

### Loading Saved DTR

Click **Load Saved DTR** to retrieve previously imported/entered DTR records for the selected employee and period.

## Database Schema

DTR records are stored in the `dtr_records` table:

```sql
CREATE TABLE IF NOT EXISTS dtr_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_period_id INT NULL,
    dtr_date DATE NOT NULL,
    am_time_in TIME NULL,
    am_time_out TIME NULL,
    pm_time_in TIME NULL,
    pm_time_out TIME NULL,
    ot_time_out TIME NULL,
    halfday_in TIME NULL,
    halfday_out TIME NULL,
    is_halfday BOOLEAN DEFAULT FALSE,
    is_absent BOOLEAN DEFAULT FALSE,
    is_training BOOLEAN DEFAULT FALSE,
    total_work_hours DECIMAL(5, 2) DEFAULT 0.00,
    late_minutes INT DEFAULT 0,
    undertime_hours DECIMAL(5, 2) DEFAULT 0.00,
    daily_ot_hours DECIMAL(5, 2) DEFAULT 0.00,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_by INT NULL,
    UNIQUE KEY unique_employee_date (employee_id, dtr_date)
);
```

## Files

| File | Description |
|------|-------------|
| `admin/Generatepayroll.php` | Main payroll form with DTR import UI |
| `admin/import_dtr.php` | Backend handler for Excel import |
| `admin/get_dtr_records.php` | API to retrieve existing DTR records |
| `admin/download_dtr_template.php` | Download DTR Excel template |
| `admin/process_payroll.php` | Saves DTR when payroll is submitted |

## Troubleshooting

### Import not working
- Ensure the Excel file has a header row
- Check that column names match expected values (Date, AM In, etc.)
- Verify dates are in a recognizable format

### PhpSpreadsheet not found
Run: `composer require phpoffice/phpspreadsheet`

### .xls files not supported
Older .xls format requires PhpSpreadsheet. Convert to .xlsx or install the library.
