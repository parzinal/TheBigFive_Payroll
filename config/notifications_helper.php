<?php
/**
 * Notifications Helper
 * Functions to create and manage system notifications
 */

require_once __DIR__ . '/database.php';

/**
 * Create a new notification for a user
 * 
 * @param int $user_id - The ID of the user to receive the notification
 * @param string $title - Notification title
 * @param string $message - Notification message
 * @param string $type - Type: 'info', 'success', 'warning', 'danger'
 * @param string $icon - Font Awesome icon class (e.g., 'fa-user-plus')
 * @param string $link - Optional link to redirect when clicked
 * @return bool - True on success, false on failure
 */
function createNotification($user_id, $title, $message, $type = 'info', $icon = 'fa-bell', $link = '') {
    try {
        $pdo = getDBConnection();
        
        $sql = "INSERT INTO notifications (user_id, title, message, type, icon, link, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$user_id, $title, $message, $type, $icon, $link]);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notifications for multiple users
 * 
 * @param array $user_ids - Array of user IDs
 * @param string $title - Notification title
 * @param string $message - Notification message
 * @param string $type - Type: 'info', 'success', 'warning', 'danger'
 * @param string $icon - Font Awesome icon class
 * @param string $link - Optional link
 * @return bool - True if all successful
 */
function createBulkNotifications($user_ids, $title, $message, $type = 'info', $icon = 'fa-bell', $link = '') {
    $success = true;
    
    foreach ($user_ids as $user_id) {
        if (!createNotification($user_id, $title, $message, $type, $icon, $link)) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Create notification for all users with a specific role
 * 
 * @param string $role - Role: 'admin', 'staff', 'user'
 * @param string $title - Notification title
 * @param string $message - Notification message
 * @param string $type - Type: 'info', 'success', 'warning', 'danger'
 * @param string $icon - Font Awesome icon class
 * @param string $link - Optional link
 * @return bool - True if successful
 */
function createNotificationByRole($role, $title, $message, $type = 'info', $icon = 'fa-bell', $link = '') {
    try {
        $pdo = getDBConnection();
        
        // Get all users with the specified role
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND status = 'active'");
        $stmt->execute([$role]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return createBulkNotifications($users, $title, $message, $type, $icon, $link);
    } catch (PDOException $e) {
        error_log("Error creating role-based notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification for all admins
 */
function notifyAdmins($title, $message, $type = 'info', $icon = 'fa-bell', $link = '') {
    return createNotificationByRole('admin', $title, $message, $type, $icon, $link);
}

/**
 * Create notification for all staff
 */
function notifyStaff($title, $message, $type = 'info', $icon = 'fa-bell', $link = '') {
    return createNotificationByRole('staff', $title, $message, $type, $icon, $link);
}

/**
 * Create notification for all employees/users
 */
function notifyAllEmployees($title, $message, $type = 'info', $icon = 'fa-bell', $link = '') {
    return createNotificationByRole('user', $title, $message, $type, $icon, $link);
}

/**
 * Delete old read notifications (older than X days)
 * 
 * @param int $days - Number of days to keep read notifications
 * @return int - Number of deleted notifications
 */
function cleanupOldNotifications($days = 30) {
    try {
        $pdo = getDBConnection();
        
        $sql = "DELETE FROM notifications 
                WHERE is_read = 1 AND read_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$days]);
        
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Error cleaning up notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get unread notification count for a user
 * 
 * @param int $user_id - User ID
 * @return int - Count of unread notifications
 */
function getUnreadCount($user_id) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}

// ===== Specific Notification Templates =====

/**
 * Notify about new employee added
 */
function notifyEmployeeAdded($employee_name, $employee_id) {
    return notifyAdmins(
        'New Employee Added',
        "$employee_name has been added to the system.",
        'success',
        'fa-user-plus',
        "user_management.php"
    );
}

/**
 * Notify about payroll processing
 */
function notifyPayrollProcessed($period, $employee_count) {
    // Notify admins
    notifyAdmins(
        'Payroll Processed',
        "Payroll for $period has been processed successfully for $employee_count employees.",
        'success',
        'fa-money-bill-wave',
        'Generatepayroll.php'
    );
    
    // Notify all employees
    notifyAllEmployees(
        'Payslip Available',
        "Your payslip for $period is now available.",
        'info',
        'fa-file-invoice-dollar',
        'payslip.php'
    );
}

/**
 * Notify about leave request
 */
function notifyLeaveRequest($employee_name, $employee_id, $leave_type) {
    return notifyAdmins(
        'New Leave Request',
        "$employee_name has submitted a $leave_type request.",
        'info',
        'fa-calendar-check',
        "leave_management.php?employee_id=$employee_id"
    );
}

/**
 * Notify employee about leave approval/rejection
 */
function notifyLeaveStatus($employee_id, $leave_type, $status) {
    $type = $status === 'approved' ? 'success' : 'danger';
    $icon = $status === 'approved' ? 'fa-check-circle' : 'fa-times-circle';
    
    return createNotification(
        $employee_id,
        'Leave Request ' . ucfirst($status),
        "Your $leave_type request has been $status.",
        $type,
        $icon,
        'leaves.php'
    );
}

/**
 * Notify about attendance marked
 */
function notifyAttendanceMarked($user_id, $date) {
    return createNotification(
        $user_id,
        'Attendance Recorded',
        "Your attendance for $date has been recorded.",
        'info',
        'fa-clock',
        'attendance.php'
    );
}

/**
 * Notify admins and staff when a DTR file is imported/saved
 */
function notifyDTRImported($employee_name, $records_count, $actor = '') {
    $by = $actor ? " by {$actor}" : '';
    notifyAdmins(
        'DTR File Imported',
        "DTR records for {$employee_name} ({$records_count} entries) were imported{$by}.",
        'info',
        'fa-file-import',
        'import_dtr.php'
    );
    return notifyStaff(
        'DTR File Imported',
        "DTR records for {$employee_name} ({$records_count} entries) were imported{$by}.",
        'info',
        'fa-file-import',
        'import_dtr.php'
    );
}

/**
 * Notify admins and staff when a payslip is generated for an employee
 */
function notifyPayslipGenerated($employee_name, $period, $net_pay, $action = 'Generated') {
    $formatted = '₱' . number_format($net_pay, 2);
    notifyAdmins(
        "Payslip {$action}",
        "Payslip for {$employee_name} ({$period}) — Net Pay: {$formatted}.",
        'success',
        'fa-file-invoice-dollar',
        'payroll_list.php'
    );
    return notifyStaff(
        "Payslip {$action}",
        "Payslip for {$employee_name} ({$period}) — Net Pay: {$formatted}.",
        'success',
        'fa-file-invoice-dollar',
        'payroll_list.php'
    );
}

/**
 * Notify about system maintenance
 */
function notifySystemMaintenance($start_time, $end_time) {
    // Notify all users
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE status = 'active'");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return createBulkNotifications(
            $users,
            'System Maintenance Scheduled',
            "The system will be under maintenance from $start_time to $end_time.",
            'warning',
            'fa-tools',
            ''
        );
    } catch (PDOException $e) {
        error_log("Error notifying maintenance: " . $e->getMessage());
        return false;
    }
}
?>
