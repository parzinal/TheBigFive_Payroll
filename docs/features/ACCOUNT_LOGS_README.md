# Account Logs Feature

This feature provides a **modern, beautiful interface** for tracking individual user account activities for security monitoring and audit purposes.

## ✨ Key Highlights

- **Personal Activity Tracking**: Each user sees only their own account activity
- **Modern Timeline Design**: Beautiful gradient cards with smooth animations
- **Smart Statistics**: Real-time activity metrics and insights
- **Security Monitoring**: Track logins, profile changes, and suspicious activities
- **Responsive Design**: Works seamlessly on desktop and mobile devices

## Components Created

1. **Database Table**: `config/sql/account_logs.sql`
2. **Admin Page**: `admin/account_logs.php`
3. **Staff Page**: `staff/account_logs.php`
4. **Helper Functions**: `config/account_logs_helper.php`

## Installation

### 1. Create the Database Table

Run the SQL file to create the `account_logs` table:

```sql
SOURCE config/sql/account_logs.sql;
```

Or manually execute:

```bash
mysql -u your_username -p thebigfive_payroll < config/sql/account_logs.sql
```

### 2. Sidebar Links

The sidebar links have been added to:
- `admin/include/sidebar.php`
- `staff/include/sidebar.php`

## Usage

### Using the Helper Functions

Include the helper file in your PHP pages:

```php
require_once '../config/account_logs_helper.php';
```

### Example: Log User Login

In your `login.php` file:

```php
require_once 'config/account_logs_helper.php';

// After successful login
logUserLogin($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
```

### Example: Log User Logout

In your `logout.php` file:

```php
require_once 'config/account_logs_helper.php';

// Before destroying session
logUserLogout($_SESSION['user_id'], $_SESSION['username']);
```

### Example: Log Profile Update

In your profile update handler:

```php
require_once '../config/account_logs_helper.php';

// After successful profile update
logProfileUpdate(
    $_SESSION['user_id'], 
    $_SESSION['username'], 
    'Updated email and full name'
);
```

### Example: Log Password Change

In your password change handler:

```php
require_once '../config/account_logs_helper.php';

// After successful password change
logPasswordChange($_SESSION['user_id'], $_SESSION['username']);
```

### Example: Log Failed Login

In your login validation:

```php
require_once 'config/account_logs_helper.php';

// When login fails
logFailedLogin($username, 'Invalid password');
```

### Example: Custom Log Entry

For custom actions:

```php
require_once '../config/account_logs_helper.php';

logAccountActivity(
    $_SESSION['user_id'],
    $_SESSION['username'],
    'Employee Created',
    'create',
    'Created new employee: John Doe'
);
```

## Action Types

The system supports the following action types:

- `login` - User login events
- `logout` - User logout events
- `profile_update` - Profile changes
- `password_change` - Password modifications
- `create` - Record creation
- `update` - Record updates
- `delete` - Record deletions
- `other` - Other miscellaneous actions

## Features

### Modern Timeline Design

- **Clean, modern UI** with beautiful gradient cards
- **Activity timeline** with color-coded icons for different action types
- **Statistics dashboard** showing:
  - Total Activities
  - Total Logins
  - Today's Activity
  - Last Activity (relative time)
- **Smooth animations** and hover effects
- **Responsive design** for mobile and desktop

### User View (`admin/account_logs.php` and `staff/account_logs.php`)

Both admin and staff see **only their own activity logs** for personal security monitoring.

- View personal account activity
- Filter by:
  - Action Type
  - Date Range
- Pagination support (20 records per page)
- Displays:
  - Action type with color-coded badges and icons
  - IP address
  - Browser/Device information
  - Timestamp
  - Description
- Timeline view with visual indicators

## Database Schema

```sql
CREATE TABLE account_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    action VARCHAR(100) NOT NULL,
    action_type ENUM('login', 'logout', 'profile_update', 'password_change', 'create', 'update', 'delete', 'other') NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
);
```

## Recommended Implementation Points

Add logging at these key points in your application:

1. **login.php**
   - Log successful logins
   - Log failed login attempts

2. **logout.php**
   - Log user logouts

3. **profile.php**
   - Log profile updates
   - Log password changes

4. **admin/Add_emplooyees.php**
   - Log employee creation

5. **admin/employee_list.php**
   - Log employee updates and deletions
