# Notification System Documentation

## Overview
The TheBigFive Payroll System includes a complete database-backed notification system that allows real-time notifications to users based on their roles and actions.

## Components

### 1. Database
- **Table**: `notifications`
- **Location**: `config/sql/notifications.sql`
- **Columns**:
  - `id` - Primary key
  - `user_id` - Foreign key to users table
  - `title` - Notification title (VARCHAR 255)
  - `message` - Notification message (TEXT)
  - `type` - Notification type: info, success, warning, danger
  - `icon` - Font Awesome icon class (e.g., fa-bell)
  - `link` - Optional redirect URL when clicked
  - `is_read` - Read status (0 = unread, 1 = read)
  - `created_at` - Timestamp when notification was created
  - `read_at` - Timestamp when notification was read

### 2. Backend API
- **Admin**: `admin/notifications_api.php`
- **Staff**: `staff/notifications_api.php`
- **User**: `user/notifications_api.php`

#### API Actions:
- `fetch` - Get notifications with optional limit and unread filter
- `mark_read` - Mark single notification as read
- `mark_all_read` - Mark all user notifications as read
- `delete` - Delete a notification
- `get_count` - Get unread notification count

#### API Usage Examples:
```javascript
// Fetch notifications
fetch('notifications_api.php?action=fetch&limit=10&unread_only=false')
    .then(response => response.json())
    .then(data => console.log(data.notifications));

// Mark as read
const formData = new FormData();
formData.append('action', 'mark_read');
formData.append('notification_id', 123);
fetch('notifications_api.php', { method: 'POST', body: formData });
```

### 3. Frontend JavaScript
- **Location**: `assets/js/dashboard.js`
- **Functions**:
  - `fetchNotifications(limit, unreadOnly)` - Load notifications from API
  - `renderNotifications(notifications)` - Display notifications in dropdown
  - `markAsRead(notificationId, redirectLink)` - Mark single notification as read
  - `markAllAsRead()` - Mark all notifications as read
  - `deleteNotification(notificationId, event)` - Delete a notification
  - `updateBadgeCount(count)` - Update notification badge counter
  - `formatTimeAgo(timestamp)` - Format timestamp to relative time

### 4. Helper Functions
- **Location**: `config/notifications_helper.php`

#### Core Functions:
```php
// Create single notification
createNotification($user_id, $title, $message, $type, $icon, $link);

// Create for multiple users
createBulkNotifications($user_ids, $title, $message, $type, $icon, $link);

// Create by role
createNotificationByRole($role, $title, $message, $type, $icon, $link);
notifyAdmins($title, $message, $type, $icon, $link);
notifyStaff($title, $message, $type, $icon, $link);
notifyAllEmployees($title, $message, $type, $icon, $link);
```

#### Template Functions:
```php
// Employee management
notifyEmployeeAdded($employee_name, $employee_id);

// Payroll
notifyPayrollProcessed($period, $employee_count);

// Leave management
notifyLeaveRequest($employee_name, $employee_id, $leave_type);
notifyLeaveStatus($employee_id, $leave_type, $status);

// Attendance
notifyAttendanceMarked($user_id, $date);

// System
notifySystemMaintenance($start_time, $end_time);
```

## Installation

### Step 1: Create Database Table
Run the SQL script to create the notifications table:
```bash
mysql -u your_username -p your_database < config/sql/notifications.sql
```

Or execute in phpMyAdmin/MySQL client:
```sql
SOURCE config/sql/notifications.sql;
```

### Step 2: Verify API Endpoints
Ensure all three notification API files exist:
- admin/notifications_api.php
- staff/notifications_api.php
- user/notifications_api.php

### Step 3: Include Helper in Your Pages
In any PHP file where you want to create notifications:
```php
require_once __DIR__ . '/../config/notifications_helper.php';
```

### Step 4: Test the System
1. Login to the system
2. Check the notification bell icon in the header
3. Click to view notifications dropdown
4. The system will automatically load notifications via AJAX

## Usage Examples

### Creating Notifications

#### Example 1: Notify when employee is added
```php
// In Createemployees.php after successful insert
require_once __DIR__ . '/../config/notifications_helper.php';

if ($stmt->execute()) {
    // Notify all admins
    notifyEmployeeAdded($full_name, $employee_id);
}
```

#### Example 2: Notify when payroll is processed
```php
// In Generatepayroll.php after processing
require_once __DIR__ . '/../config/notifications_helper.php';

notifyPayrollProcessed('December 2024', 50);
```

#### Example 3: Custom notification
```php
require_once __DIR__ . '/../config/notifications_helper.php';

// Notify specific user
createNotification(
    $user_id,                           // User ID
    'Welcome to the System',            // Title
    'Your account has been activated.', // Message
    'success',                          // Type: info/success/warning/danger
    'fa-check-circle',                  // Icon
    'dashboard.php'                     // Link (optional)
);
```

#### Example 4: Notify by role
```php
// Notify all staff members
notifyStaff(
    'Team Meeting',
    'Team meeting scheduled for tomorrow at 10 AM.',
    'info',
    'fa-calendar',
    'meetings.php'
);
```

### Frontend Usage

The notification system automatically loads when:
1. Page loads (shows unread count badge)
2. Notification dropdown is opened (refreshes list)

User interactions:
- Click notification → Mark as read and redirect to link
- Click "Mark all as read" → Mark all notifications as read
- Click delete button (×) → Remove notification
- Hover over notification → Show delete button

### Notification Types and Icons

#### Types:
- `info` - Blue notification (general information)
- `success` - Green notification (positive actions)
- `warning` - Orange notification (warnings)
- `danger` - Red notification (errors, critical alerts)

#### Common Icons:
- User: `fa-user`, `fa-user-plus`, `fa-users`
- Money: `fa-money-bill-wave`, `fa-dollar-sign`, `fa-file-invoice-dollar`
- Calendar: `fa-calendar`, `fa-calendar-check`, `fa-calendar-times`
- Time: `fa-clock`, `fa-history`
- Status: `fa-check-circle`, `fa-times-circle`, `fa-exclamation-triangle`
- General: `fa-bell`, `fa-info-circle`, `fa-cog`

## Maintenance

### Cleanup Old Notifications
```php
require_once 'config/notifications_helper.php';

// Delete read notifications older than 30 days
$deleted = cleanupOldNotifications(30);
echo "Deleted $deleted old notifications";
```

You can set up a cron job to run this periodically:
```bash
# Run cleanup every day at 2 AM
0 2 * * * php /path/to/cleanup_notifications.php
```

### Get Unread Count
```php
require_once 'config/notifications_helper.php';

$count = getUnreadCount($_SESSION['user_id']);
echo "You have $count unread notifications";
```

## Troubleshooting

### Notifications not loading
1. Check browser console for JavaScript errors
2. Verify API endpoint is accessible (test in browser: `admin/notifications_api.php?action=fetch`)
3. Check session is active (user is logged in)
4. Verify database table exists and has correct structure

### Badge count incorrect
- The badge auto-updates when fetching notifications
- Manually refresh by calling `updateBadgeCount(count)` in JavaScript

### Notifications not being created
1. Check if helper file is included: `require_once 'config/notifications_helper.php';`
2. Verify database connection in `config/database.php`
3. Check error logs for PDO exceptions
4. Ensure user ID exists in users table

### Delete button not showing
- Delete button shows on hover
- Check CSS is loaded correctly
- Verify `notification-delete` class exists in rendered HTML

## Security

- All API requests check for active session (`$_SESSION['user_id']`)
- SQL queries use prepared statements (PDO) to prevent injection
- User can only access their own notifications (WHERE user_id = ?)
- XSS protection: HTML special characters are escaped in rendering

## Performance

- Notifications limited to 10 by default (configurable)
- Indexed on `(user_id, is_read)` for fast queries
- Old read notifications should be cleaned periodically
- AJAX loading prevents page reload overhead

## Customization

### Change notification limit
```javascript
// In dashboard.js or inline script
fetchNotifications(20);  // Load 20 instead of default 10
```

### Add custom notification template
```php
// In notifications_helper.php
function notifyCustomEvent($user_id, $details) {
    return createNotification(
        $user_id,
        'Custom Event Title',
        "Custom message: $details",
        'info',
        'fa-custom-icon',
        'custom-page.php'
    );
}
```

### Modify notification styling
Edit `assets/css/dashboard.css`:
```css
.notification-item.unread {
    background-color: #f0f9ff; /* Custom color */
}

.notification-icon.custom-type {
    background-color: #8b5cf6; /* Purple */
}
```

## Best Practices

1. **Use appropriate types**: Match notification type to action severity
2. **Keep titles short**: 3-7 words maximum
3. **Messages concise**: One sentence describing the action
4. **Provide links**: Always link to relevant page when possible
5. **Choose meaningful icons**: Icons should match the action
6. **Batch notifications**: Use bulk functions for multiple users
7. **Clean regularly**: Remove old read notifications to maintain performance
8. **Test before deploying**: Verify notifications render correctly

## Future Enhancements

Potential improvements to consider:
- Real-time notifications using WebSockets
- Email notifications for critical alerts
- Push notifications (browser API)
- Notification preferences per user
- Notification categories and filtering
- Search functionality in notification list
- Export notifications to CSV

---

**Version**: 1.0  
**Last Updated**: December 2024  
**Author**: TheBigFive Development Team
