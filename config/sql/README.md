# Database Setup Instructions

## Database: thebigfive_payroll

### Setup Steps:

1. **Import the SQL file:**
   - Open phpMyAdmin or MySQL command line
   - Import `create_database.sql` file
   - Or run: `mysql -u root -p < create_database.sql`

2. **Database Configuration:**
   - Database credentials are in `config/database.php`
   - Default settings:
     - Host: localhost
     - User: root
     - Password: (empty)
     - Database: thebigfive_payroll

---

## Database Tables Overview

### 1. Users Table
**Purpose:** Authentication and user management

**Fields:**
- `id` - Auto-increment primary key
- `username` - Unique username
- `email` - Unique email address
- `password` - Hashed password (bcrypt)
- `full_name` - User's full name
- `role` - User role: 'admin', 'staff', or 'user'
- `status` - Account status: 'active', 'inactive', or 'suspended'
- `created_at` - Account creation timestamp
- `updated_at` - Last update timestamp
- `last_login` - Last login timestamp

**Default Users:**

| Username | Email | Password | Role |
|----------|-------|----------|------|
| admin | admin@thebigfive.com | admin123 | admin |
| staff | staff@thebigfive.com | staff123 | staff |
| user | user@thebigfive.com | user123 | user |

**Note:** Change these passwords after first login!

---

### 2. Employees Table
**Purpose:** Store employee information and basic salary

**Key Fields:**
- `employee_code` - Unique employee identifier
- `full_name` - Employee name (as shown in DTR: "EMPLOYEE NAME: FREEDOM")
- `position` - Job position
- `department` - Department assignment
- `basic_monthly_salary` - Monthly salary (INPUT BASIC MONTHLY SALARY HERE)
- `hire_date` - Date when employee started
- `email` - Employee's email address
- `contact_number` - Employee's contact number
- `address` - Employee's complete address
- `status` - Employment status

---

### 3. Payroll Periods Table
**Purpose:** Manage payroll calculation periods

**Key Fields:**
- `period_name` - e.g., "Oct. 13-27, 2025"
- `start_date` - Period start (e.g., Oct. 13)
- `end_date` - Period end (e.g., Oct. 27)
- `pay_date` - Payment date
- `status` - draft, processing, completed, paid

---

### 4. DTR Records Table (Daily Time Record)
**Purpose:** Store daily attendance based on DTR Calculator template

**Mapping to DTR Template:**

| Database Field | DTR Template Column |
|----------------|---------------------|
| `dtr_date` | MO/YR DATE |
| `am_time_in` | AM IN |
| `am_time_out` | AM OUT |
| `pm_time_in` | PM IN |
| `pm_time_out` | PM OUT |
| `ot_time_in` | OT IN |
| `ot_time_out` | OT OUT |
| `halfday_in` | HALFDAY IN |
| `halfday_out` | HALFDAY OUT |
| `total_work_hours` | TOT.WORK HOURS |
| `late_minutes` | LATE (in mins) |
| `late_hours` | LATE [in hours] |
| `undertime_hours` | UNDERTM [in hours] |
| `daily_ot_hours` | DAILY OT [in hours] |
| `is_absent` | ABSENT |
| `is_variable` | VARIABLE flag |
| `calculation_mode` | AUTOMATIC/MANUAL |
| `remarks` | REMARKS |

**Example Time Entries:**
- 5:46 PM, 8:05 AM, 5:00 PM (as shown in template)
- 5:45 PM, 8:06 AM, 8:16 AM
- 8:10 AM, 8:15 AM

---

### 5. Payroll Computations Table
**Purpose:** Store calculated payroll data per employee per period

**Mapping to DTR Template:**

| Database Field | DTR Template Column |
|----------------|---------------------|
| `basic_monthly_salary` | INPUT BASIC MONTHLY SALARY HERE |
| `per_day_rate` | BASIC PER/DAY |
| `per_hour_rate` | PER/HOUR |
| `per_minute_rate` | PER/MIN |
| `total_work_hours` | TOT.WORK HOURS (sum) |
| `total_late_hours` | LATE/min total |
| `total_undertime_hours` | UNDERTIM total |
| `total_ot_hours` | DAILY OT total |
| `total_absent_days` | ABSENT days total |
| `basic_pay` | BASIC calculation |
| `ot_pay` | OT PAY |
| `late_deduction` | LATE deduction |
| `undertime_deduction` | UNDERTIM deduction |
| `cash_advance` | CA ADV. |
| `sss_contribution` | GOV'T BENEFITS - SSS |
| `philhealth_contribution` | GOV'T BENEFITS - PhilHealth |
| `pagibig_contribution` | GOV'T BENEFITS - Pag-IBIG |
| `total_earnings` | Total before deductions |
| `total_deductions` | TOTAL DEDUCTIONS |
| `net_pay` | NET SALARY |
| `f1_value` | F1* field |
| `f2_value` | F2* field |

**Calculation Fields:**
- `ITOMATI` - Automatic calculation field
- `neg PTOTAL` - Negative partial total handling
- `ALSO UNDERTIME OR` - Alternative undertime calculation

---

### 6. Deduction Types Table
**Purpose:** Manage deduction categories

**Default Deductions:**
- SSS Contribution
- PhilHealth Contribution
- Pag-IBIG Contribution
- Withholding Tax
- Cash Advance (CA ADV)
- Late Deduction
- Undertime Deduction
- Absent Deduction

---

### 7. Employee Deductions Table
**Purpose:** Track individual deduction instances

---

### 8. Overtime Records Table
**Purpose:** Detailed overtime tracking and approval

**Fields:**
- `overtime_hours` - Total OT hours
- `overtime_type` - regular, rest_day, holiday, special_holiday
- `multiplier` - Pay multiplier (1.25, 1.5, 2.0, etc.)
- `overtime_pay` - Calculated OT pay
- `status` - pending, approved, rejected, paid

---

### 9. Leave Records Table
**Purpose:** Employee leave management

**Leave Types:**
- Sick Leave
- Vacation Leave
- Emergency Leave
- Maternity/Paternity Leave
- Unpaid Leave

---

### 10. Holidays Table
**Purpose:** Holiday calendar for payroll calculations

**Fields:**
- `holiday_type` - regular, special, special_working
- `multiplier` - Pay multiplier for working on holidays

---

### 11. Payroll History Table
**Purpose:** Audit trail for all payroll changes

---

## DTR Calculator Workflow

1. **Employee Time Entry**
   - Record AM IN/OUT times
   - Record PM IN/OUT times
   - Record OT if applicable
   - Mark halfday if needed

2. **Automatic Calculations**
   - System calculates total work hours
   - Identifies late arrivals (in minutes and hours)
   - Calculates undertime
   - Computes daily OT hours
   - Marks absences

3. **Payroll Period Processing**
   - Sum all daily records per period
   - Calculate basic pay based on work hours
   - Add OT pay
   - Subtract deductions (late, undertime, absent)
   - Apply government benefits deductions
   - Subtract cash advances
   - Calculate net pay

4. **Rate Calculations**
   ```
   Per Day Rate = Monthly Salary / Days in Month
   Per Hour Rate = Per Day Rate / 8 hours
   Per Minute Rate = Per Hour Rate / 60 minutes
   ```

---

## User Roles:

- **admin** - Full system access, can manage all users, payroll, and settings
- **staff** - Can process payroll, manage DTR, and employee records
- **user** - Limited access, can view own DTR and payroll information

---

## Security Notes:

- Passwords are hashed using PHP's `password_hash()` function with bcrypt
- All passwords should be changed after initial setup
- Use prepared statements to prevent SQL injection
- Database connection uses PDO with exception handling

---

## Database Migrations & Updates

### Available Migration Files:

1. **add_training_columns.sql** - Adds training-related columns to payroll computations
2. **add_employee_contact_columns.sql** - Adds email, contact_number, and address columns to employees table
3. **notifications.sql** - Creates notifications system tables
4. **account_logs.sql** - Creates account activity logging tables
5. **dtr_helpers.sql** - Creates helper functions for DTR calculations

### How to Apply Migrations:

For existing databases that need updates:

```bash
# Run individual migration
mysql -u root -p thebigfive_payroll < config/sql/add_employee_contact_columns.sql

# Or using phpMyAdmin: Import the SQL file directly
```

**Note:** Migrations use `ADD COLUMN IF NOT EXISTS` to prevent errors if columns already exist.
