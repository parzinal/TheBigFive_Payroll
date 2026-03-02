# Notification System - Quick Troubleshooting Guide

## Problem: Notifications stuck on "Loading..."

If your notifications are taking too long to load or staying stuck on "Loading...", follow these steps:

### Step 1: Run the Diagnostic Tool
1. Open your browser and go to: `http://localhost/TheBigFive_Payroll/config/check_notifications.php`
2. This will check if everything is set up correctly
3. Follow the instructions shown on the diagnostic page

### Step 2: Create the Notifications Table
The most common issue is that the notifications table doesn't exist yet.

**Option A: Automatic Setup (Recommended)**
1. Go to: `http://localhost/TheBigFive_Payroll/config/setup_notifications.php`
2. This will automatically create the table and sample notifications
3. Refresh your dashboard

**Option B: Manual Setup**
1. Open phpMyAdmin
2. Select your database (usually `thebigfive_payroll`)
3. Click "Import" tab
4. Choose file: `config/sql/notifications.sql`
5. Click "Go"

### Step 3: Check Browser Console
1. Open your dashboard
2. Press F12 to open Developer Tools
3. Click "Console" tab
4. Look for any red error messages
5. You should see logs like:
   - "Fetching notifications from: notifications_api.php?action=fetch&limit=10&unread_only=false"
   - "Response status: 200"
   - "Response data: {success: true, notifications: Array(2), unread_count: 2}"

### Common Issues and Solutions

#### Issue 1: "Table 'notifications' doesn't exist"
**Solution:** Run `setup_notifications.php` or import the SQL file manually

#### Issue 2: Request timeout after 5 seconds
**Solution:** 
- Check if MySQL is running
- Check database connection in `config/database.php`
- Table might not exist (see Issue 1)

#### Issue 3: "Unauthorized" message
**Solution:** You're not logged in. Login first, then check notifications

#### Issue 4: Badge shows "0" but you have notifications
**Solution:** 
- Clear browser cache
- Check browser console for errors
- The API might not be returning data correctly

#### Issue 5: API returns JSON error
**Solution:**
- Check if `notifications_api.php` exists in admin/staff/user folders
- Check file permissions (should be readable)
- Check PHP error log for syntax errors

### Performance Optimization

If notifications load slowly but eventually work:

1. **Check database indexes:**
   ```sql
   SHOW INDEX FROM notifications;
   ```
   Should show indexes on `user_id, is_read` and `created_at`

2. **Limit notification count:**
   Edit dashboard.js line 114:
   ```javascript
   fetchNotifications(5);  // Load only 5 instead of 10
   ```

3. **Regular cleanup:**
   Run `test_notifications.php` → Cleanup section to delete old notifications

### Testing the System

1. Go to: `http://localhost/TheBigFive_Payroll/config/test_notifications.php`
2. Create a test notification
3. Check if it appears in the notification dropdown
4. Try clicking "Mark all as read"
5. Try deleting a notification

### File Checklist

Ensure these files exist:
- ✅ `config/sql/notifications.sql` - Database schema
- ✅ `admin/notifications_api.php` - Admin API
- ✅ `staff/notifications_api.php` - Staff API
- ✅ `user/notifications_api.php` - User API
- ✅ `config/notifications_helper.php` - Helper functions
- ✅ `assets/js/dashboard.js` - Frontend JavaScript
- ✅ `assets/css/dashboard.css` - Styling

### Still Having Issues?

1. Check browser console for JavaScript errors
2. Check PHP error log: `laragon/logs/php_error.log`
3. Check MySQL error log: `laragon/bin/mysql/data/*.err`
4. Verify session is active: `print_r($_SESSION)` in any page
5. Test API directly: `admin/notifications_api.php?action=get_count`

### Quick Reset

If nothing works, start fresh:

1. **Drop the table:**
   ```sql
   DROP TABLE IF EXISTS notifications;
   ```

2. **Re-run setup:**
   Visit `config/setup_notifications.php`

3. **Clear browser cache:**
   - Chrome: Ctrl+Shift+Delete
   - Firefox: Ctrl+Shift+Delete
   - Edge: Ctrl+Shift+Delete

4. **Hard refresh:**
   Ctrl+F5 on your dashboard

### Expected Behavior

When working correctly:
1. Page loads → Notification badge shows unread count (or hidden if 0)
2. Click bell icon → Dropdown opens with loading spinner (< 1 second)
3. Notifications appear with relative timestamps ("5 minutes ago")
4. Click notification → Marks as read and redirects (if link provided)
5. Click "Mark all as read" → All become read, badge updates to 0
6. Click delete (×) → Notification disappears

### Developer Debugging

Add this to see detailed logs:
```javascript
// In your browser console
localStorage.debug = true;
```

Then reload the page and check console logs showing:
- API path being called
- Response status
- Response data
- Any errors

---

**Last Updated:** February 2026  
**Version:** 1.0

For more details, see `config/NOTIFICATIONS_README.md`
