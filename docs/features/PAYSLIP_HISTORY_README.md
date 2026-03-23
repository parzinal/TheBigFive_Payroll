# Payslip History Feature

## Overview
The Payslip History feature allows administrators to view all generated payslips for employees in a beautiful, organized interface. Each employee's payslips are displayed as professional-looking documents that can be viewed, downloaded, or printed.

## Files Created

### 1. `admin/payslip_history.php`
Main page that displays all employees who have generated payslips with statistics:
- Employee cards showing name, code, position, and department
- Number of payslips generated per employee
- Last payslip generation date
- Total amount paid to each employee
- "View All Payslips" button for each employee

### 2. `admin/get_employee_payslips.php`
API endpoint that fetches all payslips for a specific employee
- Returns payslip data in JSON format
- Includes all payroll computation details
- Used by the modal to display payslips

### 3. `config/sql/sample_payslip_data.sql`
Sample data for testing the feature
- Creates 4 payroll periods (Jan-Feb 2026)
- Generates sample payroll computations for the first 5 employees
- Includes realistic salary calculations with deductions

## Features

### Employee Cards
- **Professional Design**: Modern card-based layout with gradient headers
- **Employee Avatar**: Displays employee initials in a circular badge
- **Statistics**: Shows payslip count, last generated date, and total paid
- **System Blue Theme**: Matches the updated color scheme (#2563EB)

### Payslip Viewer
- **Modal Display**: Opens in a full-screen modal overlay
- **Professional Payslip Design**: 
  - Company header with branding
  - Employee information section
  - Detailed earnings breakdown
  - Comprehensive deductions list
  - Highlighted net pay section
  - Work summary (days, hours, OT, absences)
  - Computer-generated footer
  
### Payslip Details Include:
- **Earnings**:
  - Basic Pay
  - Overtime Pay
  - Total Earnings
  
- **Deductions**:
  - Late Deduction
  - Undertime Deduction
  - Absent Deduction
  - SSS Contribution
  - PhilHealth Contribution
  - Pag-IBIG Contribution
  - Withholding Tax
  - Total Deductions
  
- **Work Summary**:
  - Total Days Worked
  - Total Hours Worked
  - Overtime Hours
  - Late Hours
  - Undertime Hours
  - Absent Days

## Usage

### Accessing Payslip History
1. Log in as an admin
2. Click on "Payroll" in the sidebar
3. Select "Payslip History"
4. View the dashboard with all employees who have payslips

### Viewing Employee Payslips
1. Find the employee card
2. Click "View All Payslips" button
3. A modal will open showing all payslips for that employee
4. Each payslip is displayed as a professional document
5. Scroll through multiple payslips if available
6. Click the X or press ESC to close the modal

### Adding Sample Data
To test the feature with sample data:

```bash
# Run the SQL file in your MySQL/MariaDB
mysql -u your_username -p thebigfive_payroll < config/sql/sample_payslip_data.sql
```

**Important**: Before running the sample data SQL:
- Make sure you have at least 5 employees in your database
- The SQL uses employee IDs 1-5 by default
- Adjust the employee_id values in the SQL if your IDs are different
- Check existing employee IDs with: `SELECT id, full_name FROM employees;`

## Sidebar Update
The menu item has been updated:
- **Old**: "Payroll History" with history icon
- **New**: "Payslip History" with receipt icon (fa-receipt)
- **Link**: Points to `payslip_history.php`

## Design Features

### Color Scheme
- **Primary**: #2563EB (System Blue)
- **Primary Dark**: #1d4ed8
- **Success**: #10b981 (Green for net pay)
- **Danger**: #dc2626 (Red for deductions)

### Responsive Design
- Desktop: Multi-column grid layout
- Tablet: Adjusts column count
- Mobile: Single column, full-width cards

### User Experience
- **Hover Effects**: Cards lift and highlight on hover
- **Smooth Animations**: Transitions for all interactions
- **Loading States**: Spinner while fetching payslips
- **Error Handling**: Graceful error messages
- **Keyboard Support**: ESC key to close modal

## Database Requirements

The feature uses these tables:
- `employees` - Employee information
- `payroll_periods` - Pay period definitions
- `payroll_computations` - Calculated payroll data

Only payroll computations with status of 'computed', 'approved', or 'paid' are displayed.

## Future Enhancements

Potential improvements:
1. **Download as PDF**: Export payslips to PDF format
2. **Email Payslips**: Send payslips directly to employees
3. **Print Functionality**: Print-optimized version
4. **Filtering**: Filter by date range, status, department
5. **Search**: Search employees by name or code
6. **Bulk Actions**: Download multiple payslips at once
7. **Payslip Comparison**: Compare payslips across periods

## Troubleshooting

### No Payslips Showing
- Verify payroll computations exist in database
- Check that status is 'computed', 'approved', or 'paid'
- Ensure employees are linked to payroll computations

### Modal Not Loading
- Check browser console for JavaScript errors
- Verify `get_employee_payslips.php` is accessible
- Check database connection

### Sample Data Not Inserting
- Verify employee IDs exist (1-5)
- Check payroll_periods table is empty or adjust period names
- Review MySQL error messages

## Technical Notes

### JavaScript Functions
- `viewEmployeePayslips(employeeId, employeeName)` - Opens modal and fetches payslips
- `closePayslipsModal()` - Closes the modal
- `formatDate(dateString)` - Formats dates for display
- `generatePayslipHTML(payslip)` - Generates payslip document HTML

### API Response Format
```json
{
  "success": true,
  "payslips": [
    {
      "id": 1,
      "employee_name": "John Doe",
      "employee_code": "EMP-001",
      "period_name": "January 2026 - 1st Half",
      "net_pay": "11173.36",
      ...
    }
  ],
  "count": 3
}
```

## Support
For issues or questions about this feature, refer to the main system documentation or contact the development team.
