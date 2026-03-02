# TheBigFive Payroll System - Comprehensive Analysis

## 1. System Overview

This is a **PHP-based web payroll management system** for "TheBigFive Training and Assessment Center Inc.", built using:

- **Backend:** PHP (procedural, no framework)
- **Database:** MySQL (`thebigfive_payroll`) via PDO + legacy MySQLi
- **Frontend:** HTML/CSS/JavaScript with Font Awesome icons, custom CSS
- **Libraries:** PHPMailer (email/OTP), PhpSpreadsheet (Excel import/export via Composer)
- **Server:** Designed for Laragon (local XAMPP-style environment)

---

## 2. Database Schema (12 Tables)

| Table | Purpose |
|---|---|
| `users` | System accounts (admin, staff, user roles) |
| `employees` | Employee records (code, name, position, dept, salary) |
| `payroll_periods` | Pay periods (start/end dates, status: draft/processing/completed/paid) |
| `dtr_records` | Daily Time Records (AM/PM in/out, OT, halfday, late, absent, work hours) |
| `payroll_computations` | Computed payroll per employee per period (earnings, deductions, net pay) |
| `deduction_types` | SSS, PhilHealth, Pag-IBIG, Withholding Tax, Cash Advance, Late, UT, Absent |
| `employee_deductions` | Per-employee deduction records per period |
| `overtime_records` | Detailed OT tracking with type-based multipliers |
| `leave_records` | Leave requests (sick, vacation, emergency, maternity, paternity, unpaid) |
| `holidays` | Company holidays for pay calculations (regular, special, special_working) |
| `notifications` | In-app notification system per user |
| `account_logs` | Audit trail for all user actions (login, logout, CRUD, profile changes) |

### Key Relationships:
- `employees.user_id` -> `users.id` (optional link to system account)
- `dtr_records.employee_id` -> `employees.id`
- `dtr_records.payroll_period_id` -> `payroll_periods.id`
- `payroll_computations.employee_id` -> `employees.id`
- `payroll_computations.payroll_period_id` -> `payroll_periods.id`

---

## 3. User Roles & Access Flow

```
Login (login.php) --> Role Check --> Route
   |
   +--> admin  --> admin/dashboard.php
   +--> staff  --> staff/dashboard_staff.php
   +--> user   --> user/dashboard_user.php (⚠ folder does NOT exist)
```

- **Admin:** Full access - employees CRUD, payroll generation, DTR import, user/account management, logs
- **Staff:** Limited access - view employees, attendance, leave management, payroll generation, profile, logs
- **User:** Referenced in login routing but the `user/` directory does NOT exist in the codebase

---

## 4. Authentication Flow

1. **Login** (`login.php`) - Email/username + password via `password_verify()`, session-based auth
2. **Register** (`register.php`) - Self-registration with role selection (⚠ security issue: users can register as admin)
3. **Forgot Password** (`forgotpass.php`) - 3-step process: Email → OTP verification → Password reset (via Gmail SMTP)
4. **Logout** (`logout.php`) - Session destroy + activity logging via `logUserLogout()`
5. **Session Guard** - Each header.php checks `$_SESSION['user_id']` and role, redirects to login if unauthorized

---

## 5. Admin Module Pages

| Page | Purpose |
|---|---|
| `dashboard.php` | Stats overview: total employees, monthly payroll, attendance, recent employees, dept breakdown |
| `employee_list.php` | View/filter/search/edit all employees (AJAX inline editing) |
| `Add_emplooyees.php` | Add new employee form (links to user accounts) |
| `employee_positions.php` | Position-based grouping, create positions, assign employees |
| `Generatepayroll.php` | **Core page** - DTR Excel import, TB5-style 31-day DTR table, payroll computation (6232 lines) |
| `payroll_list.php` | Employee cards with DTR summaries, click to view monthly DTR modal |
| `import_dtr.php` | API - Excel file upload & parsing (PhpSpreadsheet or native XLSX) |
| `save_dtr.php` | API - Save DTR records + auto-create employee/period |
| `process_payroll.php` | API - Compute earnings/deductions/net pay, save to payroll_computations |
| `user_management.php` | CRUD for system accounts (admin, staff) |
| `add_user.php` | Create new user account |
| `profile.php` | View/edit own profile + change password |
| `account_logs.php` | View personal activity logs with date filters and pagination |
| `notifications_api.php` | REST API for notification CRUD (fetch, mark_read, delete, get_count) |
| `get_employee_details.php` | API - Fetch employee info for payroll |
| `get_dtr_records.php` | API - Fetch DTR by employee + period |
| `get_employee_dtr_data.php` | API - Fetch DTR by employee + month |
| `get_employee_dtr_months.php` | API - Available months with DTR data |
| `get_employee_dtr_cards.php` | API - All employees with DTR counts |
| `download_dtr_template.php` | Generate sample DTR Excel template |

---

## 6. Staff Module Pages

| Page | Purpose |
|---|---|
| `dashboard_staff.php` | Placeholder dashboard (hardcoded zeros, no real data) |
| `Generatepayroll.php` | Different payroll implementation using MySQLi + stored procedures |
| `profile.php` | Profile management |
| `account_logs.php` | Activity logs |
| `notifications_api.php` | Same notification API |

### Staff Sidebar Links to Non-Existent Pages:
- `employee_list.php`, `employee_departments.php`
- `attendance_list.php`, `mark_attendance.php`
- `leave_requests.php`, `my_leave.php`
- `payroll_list.php`, `reports.php`

---

## 7. Core Business Flow: DTR & Payroll

### Step-by-Step Process:

```
1. IMPORT DTR EXCEL
   User uploads .xlsx/.xls/.xlsm/.csv file
   → admin/import_dtr.php parses it via PhpSpreadsheet
   → Extracts: employee info + 31-day DTR records

2. REVIEW & EDIT IN TB5 TABLE
   Generatepayroll.php displays imported data in TB5-style table
   31 rows (one per day) with columns:
   - Date, AM In/Out, PM In/Out, Absent, OT Out, Halfday In/Out
   - Computed: Work Hours, Late, Undertime, OT Hours
   - Deductions: Absent, Late, Undertime, Halfday deductions
   - OT Pay, Remarks

3. SAVE DTR RECORDS
   admin/save_dtr.php:
   - Finds or creates employee by code/name
   - Finds or creates payroll period
   - Inserts/updates dtr_records (upsert on employee_id + dtr_date)

4. COMPUTE PAYROLL
   admin/process_payroll.php:
   - Calculates earnings (basic, OT, holiday, commission, sick, expense)
   - Calculates deductions (DTR-based + government + other)
   - Net Pay = Total Earnings - Total Deductions
   - Stores in payroll_computations

5. VIEW PAYROLL
   admin/payroll_list.php:
   - Shows employee cards with DTR count, OT hours, estimated gross
   - Click to view monthly DTR in modal
```

### Payroll Calculation Rates:
- **Daily Rate** = Monthly Salary / 30 days ("FOR 30 DAYS A MONTH COMPUTATION")
- **Hourly Rate** = Daily Rate / 8 hours
- **Per-Minute Rate** = Hourly Rate / 60
- **OT Rate** = Hourly Rate × 1.25 multiplier
- Government deductions: SSS, PhilHealth, Pag-IBIG, Withholding Tax

---

## 8. Notification System

- **Helper** (`config/notifications_helper.php`): Create single, bulk, role-based notifications
- **API** (`notifications_api.php`): REST endpoints for fetch, mark_read, mark_all_read, delete, get_count
- **UI**: Bell icon in header with dropdown, dynamically loaded via JavaScript
- **Cleanup**: Auto-delete old read notifications after 30 days

---

## 9. Account Logging / Audit System

- **Helper** (`config/account_logs_helper.php`): Logs all actions with IP, user agent
- **Types**: login, logout, profile_update, password_change, create, update, delete, other
- **UI** (`account_logs.php`): Personal activity view with date filters, pagination, stats cards

---

## 10. UI Architecture

- **Layout Pattern**: `header.php` → `sidebar.php` → Page Content → `footer.php`
- **Shared CSS**: `assets/css/dashboard.css` (all dashboard pages), `assets/css/login.css` (auth pages)
- **Shared JS**: `assets/js/dashboard.js` (sidebar toggle, dropdowns, notifications, search)
- **Icons**: Font Awesome 6.4.0 (CDN)
- **Fonts**: Inter + Montserrat (Google Fonts, login pages only)
- **Currency**: Philippine Peso (₱)
- **Responsive**: Mobile sidebar toggle, responsive grids

---

## 11. Known Issues & Areas for Improvement

### Critical Issues:
1. **Missing `user/` directory** - Login routes users to `user/dashboard_user.php` but folder doesn't exist
2. **Registration security** - Users can self-register as admin/staff (should restrict to 'user' role only)
3. **No CSRF protection** - Forms lack CSRF tokens
4. **SMTP credentials hardcoded** - App password visible in `config/smtp.php`
5. **Dual DB patterns** - Admin uses PDO, Staff Generatepayroll uses MySQLi (inconsistent)

### Feature Gaps:
6. **Staff dashboard placeholder** - Shows hardcoded zeros, no real data queries
7. **Staff pages missing** - Many sidebar links point to non-existent pages (attendance, leave, reports)
8. **No payroll approval workflow** - Payroll goes from computed to paid without approval step
9. **No payslip generation/printing** - No PDF or print-friendly payslip view
10. **No backup/export** - No database backup or data export functionality

### Code Quality:
11. **`Add_emplooyees.php` typo** - Filename has typo ("emplooyees" instead of "employees")
12. **Generatepayroll.php is massive** - 6232 lines with embedded CSS/JS (should be modularized)
13. **Duplicate footer code** - Admin and staff footers are identical (should share)
14. **No input sanitization consistency** - Some pages use `htmlspecialchars()`, others don't
15. **No error handling UI** - Database connection failures show generic messages

### Time Format Issue:
16. **12-hour time picker vs 24-hour Excel data** - DTR time pickers use AM/PM format but imported Excel files use 24-hour format, causing conflicts

---

## 12. Technology Stack Summary

```
Frontend:  HTML5 + CSS3 + Vanilla JavaScript
Backend:   PHP 8.x (procedural)
Database:  MySQL 8.x (InnoDB, utf8mb4)
Email:     PHPMailer via Gmail SMTP
Excel:     PhpSpreadsheet (Composer)
Server:    Laragon (Apache + MySQL + PHP)
```

---

*Analysis completed: February 23, 2026*
