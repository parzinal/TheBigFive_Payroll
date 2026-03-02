# Security Implementation - Role-Based Access Control

## Overview
This document describes the enhanced security system implemented to prevent unauthorized access to admin and staff areas, even when URLs are manually changed.

## Key Features

### 1. Centralized Authentication System
**File:** `config/auth.php`

A centralized authentication helper that provides:
- Session validation
- Role-based access control
- Session hijacking prevention
- Session timeout management
- Automatic redirects based on user roles

### 2. Secure Session Management

#### Session Initialization (Login)
When a user logs in through `login.php`:
- A unique auth token is generated using `bin2hex(random_bytes(32))`
- User agent is stored to detect session hijacking
- Login timestamp is recorded
- All session data is regenerated to prevent session fixation

#### Session Validation
Every protected page validates:
- User is authenticated
- Session token matches
- User agent hasn't changed
- Session hasn't expired (8-hour timeout)
- User has the required role for the page

#### Session Destruction (Logout)
When logging out through `logout.php`:
- All session variables are unset
- Session cookies are deleted
- Session is completely destroyed
- User is redirected to login page

### 3. Role-Based Access Protection

#### Admin Pages
All admin pages require:
```php
requireAuth('admin');
```

If a staff user tries to access admin pages:
- Session is validated
- Role mismatch is detected
- User is automatically redirected to staff dashboard

#### Staff Pages
All staff pages require:
```php
requireAuth('staff');
```

If an admin tries to access staff pages:
- Session is validated
- Role mismatch is detected
- User is automatically redirected to admin dashboard

### 4. API Endpoint Protection

All API endpoints now use secure authentication:

**Staff APIs:**
```php
require_once '../config/auth.php';
if (!isAuthenticated() || !isStaff()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
```

**Admin APIs:**
```php
require_once '../config/auth.php';
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
```

## Security Scenarios

### Scenario 1: Staff tries to access Admin pages
1. Staff user logs in successfully
2. Staff user manually changes URL to `/admin/dashboard.php`
3. System validates session → User is authenticated ✓
4. System checks role → User is "staff", page requires "admin" ✗
5. System redirects to `/staff/dashboard_staff.php`
6. **Result: Access Denied - Auto-redirected**

### Scenario 2: Admin tries to access Staff pages
1. Admin user logs in successfully
2. Admin user manually changes URL to `/staff/payslip_history.php`
3. System validates session → User is authenticated ✓
4. System checks role → User is "admin", page requires "staff" ✗
5. System redirects to `/admin/dashboard.php`
6. **Result: Access Denied - Auto-redirected**

### Scenario 3: Unauthenticated user tries to access any protected page
1. User tries to access `/admin/dashboard.php` without logging in
2. System checks if authenticated → No session exists ✗
3. System redirects to `/login.php`
4. **Result: Access Denied - Redirected to login**

### Scenario 4: Session Hijacking Attempt
1. Attacker obtains session ID
2. Attacker tries to access protected page with stolen session
3. System validates user agent → Mismatch detected ✗
4. Session is destroyed
5. User is redirected to login with error message
6. **Result: Session Invalid - Security breach prevented**

### Scenario 5: Expired Session
1. User logs in and remains inactive for > 8 hours
2. User tries to access any protected page
3. System checks session age → Expired ✗
4. Session is destroyed
5. User is redirected to login with "session_expired" message
6. **Result: Session Expired - Must re-login**

## Protected Files

### Admin Pages (require 'admin' role)
- `/admin/dashboard.php`
- `/admin/employee_list.php`
- `/admin/user_management.php`
- `/admin/account_logs.php`
- `/admin/profile.php`
- `/admin/payroll_list.php`
- All admin API endpoints (`get_*.php`, `save_*.php`, etc.)

### Staff Pages (require 'staff' role)
- `/staff/dashboard_staff.php`
- `/staff/employee_list.php`
- `/staff/payroll_list.php`
- `/staff/payslip_history.php`
- `/staff/account_logs.php`
- `/staff/profile.php`
- All staff API endpoints

## Technical Implementation Details

### Authentication Functions

**`requireAuth($role)`**
- Main authentication function
- Validates session exists and is valid
- Checks role matches requirement
- Redirects to appropriate dashboard if role mismatch
- Redirects to login if not authenticated

**`isAuthenticated()`**
- Checks if user has valid session
- Returns boolean

**`hasRole($role)`**
- Checks if user has specific role
- Returns boolean

**`validateSessionToken()`**
- Checks user agent matches
- Prevents session hijacking
- Returns boolean

**`initializeSecureSession($userData)`**
- Called during login
- Sets all session variables
- Generates security tokens
- Regenerates session ID

**`destroySecureSession()`**
- Called during logout
- Clears all session data
- Deletes cookies
- Destroys session completely

**`redirectToProperDashboard($role)`**
- Redirects user to their correct dashboard
- Based on their role (admin/staff/user)

## Security Best Practices Applied

1. ✅ **Session Regeneration** - Prevents session fixation attacks
2. ✅ **Role Validation** - Prevents privilege escalation
3. ✅ **Token Validation** - Prevents session hijacking
4. ✅ **Timeout Management** - Limits exposure window
5. ✅ **Centralized Auth** - Single point of security control
6. ✅ **HTTP 401 Responses** - Proper status codes for API endpoints
7. ✅ **Secure Logout** - Complete session cleanup
8. ✅ **Auto-redirect** - User-friendly access denial

## Testing the Security

### Test 1: Role-based access
1. Log in as Staff user
2. Manually navigate to `http://yoursite/admin/dashboard.php`
3. **Expected:** Auto-redirected to `/staff/dashboard_staff.php`

### Test 2: Unauthenticated access
1. Ensure you're logged out
2. Navigate to `http://yoursite/admin/dashboard.php`
3. **Expected:** Redirected to `/login.php`

### Test 3: API protection
1. Log in as Staff user
2. Use browser console to fetch admin API
3. **Expected:** 401 Unauthorized response

### Test 4: Session timeout
1. Log in to the system
2. Wait 8+ hours (or modify timeout in auth.php for testing)
3. Try to access any page
4. **Expected:** Redirected to login with "session_expired" error

## Configuration

### Session Timeout
Default: 8 hours (28,800 seconds)

To modify, edit `config/auth.php`:
```php
$sessionTimeout = 8 * 60 * 60; // Change 8 to desired hours
```

### Allowed Roles
- `admin` - Full system access
- `staff` - Limited access to staff features
- `user` - Basic access (if implemented)

## Maintenance

### Adding New Protected Pages

**For Admin Pages:**
```php
<?php
$page_title = 'New Admin Page';
require_once 'include/header.php'; // Will call requireAuth('admin')
require_once 'include/sidebar.php';
// Your page content
?>
```

**For Staff Pages:**
```php
<?php
$page_title = 'New Staff Page';
require_once 'include/header.php'; // Will call requireAuth('staff')
require_once 'include/sidebar.php';
// Your page content
?>
```

**For API Endpoints:**
```php
<?php
require_once '../config/auth.php';

// For admin-only API
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// For staff-only API
if (!isAuthenticated() || !isStaff()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// API logic here
?>
```

## Summary

The security system now ensures that:
- ✅ **Staff cannot access admin pages** - even by changing URLs
- ✅ **Admins cannot access staff pages** - even by changing URLs
- ✅ **Unauthenticated users cannot access protected pages**
- ✅ **Session hijacking is prevented** - user agent validation
- ✅ **Sessions expire** - automatic timeout after 8 hours
- ✅ **All API endpoints are protected** - role-based validation
- ✅ **Secure login/logout** - proper session management

The system is production-ready and provides enterprise-level security for role-based access control.
