<?php
/**
 * Account Logs Helper
 * Helper functions for logging account activities
 */

/**
 * Log an account activity
 * 
 * @param int $user_id User ID (can be null for failed login attempts)
 * @param string $username Username
 * @param string $action Action performed
 * @param string $action_type Type of action (login, logout, profile_update, password_change, create, update, delete, other)
 * @param string $description Optional description of the action
 * @param PDO $pdo Database connection (optional, will create new if not provided)
 * @return bool Success status
 */
function logAccountActivity($user_id, $username, $action, $action_type, $description = null, $pdo = null) {
    try {
        // Create database connection if not provided
        $close_connection = false;
        if ($pdo === null) {
            require_once __DIR__ . '/database.php';
            $pdo = getDBConnection();
            $close_connection = true;
        }
        
        // Get IP address
        $ip_address = getClientIP();
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Insert log
        $stmt = $pdo->prepare("
            INSERT INTO account_logs 
            (user_id, username, action, action_type, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $user_id,
            $username,
            $action,
            $action_type,
            $description,
            $ip_address,
            $user_agent
        ]);
        
        return $result;
        
    } catch (PDOException $e) {
        // Log error silently, don't break application flow
        error_log("Failed to log account activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get client IP address
 * 
 * @return string IP address
 */
function getClientIP() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    // Handle multiple IPs (take the first one)
    if (strpos($ip, ',') !== false) {
        $ip = explode(',', $ip)[0];
    }
    
    return trim($ip);
}

/**
 * Log user login
 * 
 * @param int $user_id User ID
 * @param string $username Username
 * @param string $role User role
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logUserLogin($user_id, $username, $role, $pdo = null) {
    return logAccountActivity(
        $user_id,
        $username,
        'User Login',
        'login',
        "User logged in as {$role}",
        $pdo
    );
}

/**
 * Log user logout
 * 
 * @param int $user_id User ID
 * @param string $username Username
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logUserLogout($user_id, $username, $pdo = null) {
    return logAccountActivity(
        $user_id,
        $username,
        'User Logout',
        'logout',
        'User logged out',
        $pdo
    );
}

/**
 * Log profile update
 * 
 * @param int $user_id User ID
 * @param string $username Username
 * @param string $changes Description of changes made
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logProfileUpdate($user_id, $username, $changes, $pdo = null) {
    return logAccountActivity(
        $user_id,
        $username,
        'Profile Update',
        'profile_update',
        $changes,
        $pdo
    );
}

/**
 * Log password change
 * 
 * @param int $user_id User ID
 * @param string $username Username
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logPasswordChange($user_id, $username, $pdo = null) {
    return logAccountActivity(
        $user_id,
        $username,
        'Password Change',
        'password_change',
        'User changed their password',
        $pdo
    );
}

/**
 * Log failed login attempt
 * 
 * @param string $username Username attempted
 * @param string $reason Reason for failure
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logFailedLogin($username, $reason, $pdo = null) {
    return logAccountActivity(
        null,
        $username,
        'Failed Login Attempt',
        'login',
        $reason,
        $pdo
    );
}

/**
 * Log create action (employee, user, etc.)
 * 
 * @param int $user_id User ID who performed the action
 * @param string $username Username
 * @param string $entity Type of entity created (e.g., "Employee", "User")
 * @param string $entity_name Name/identifier of the created entity
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logCreateAction($user_id, $username, $entity, $entity_name, $pdo = null) {
    return logAccountActivity(
        $user_id,
        $username,
        "Created {$entity}",
        'create',
        "Created new {$entity}: {$entity_name}",
        $pdo
    );
}

/**
 * Log update action
 * 
 * @param int $user_id User ID who performed the action
 * @param string $username Username
 * @param string $entity Type of entity updated
 * @param string $entity_name Name/identifier of the updated entity
 * @param string $changes Description of changes (optional)
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logUpdateAction($user_id, $username, $entity, $entity_name, $changes = null, $pdo = null) {
    $description = "Updated {$entity}: {$entity_name}";
    if ($changes) {
        $description .= " - {$changes}";
    }
    return logAccountActivity(
        $user_id,
        $username,
        "Updated {$entity}",
        'update',
        $description,
        $pdo
    );
}

/**
 * Log delete action
 * 
 * @param int $user_id User ID who performed the action
 * @param string $username Username
 * @param string $entity Type of entity deleted
 * @param string $entity_name Name/identifier of the deleted entity
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logDeleteAction($user_id, $username, $entity, $entity_name, $pdo = null) {
    return logAccountActivity(
        $user_id,
        $username,
        "Deleted {$entity}",
        'delete',
        "Deleted {$entity}: {$entity_name}",
        $pdo
    );
}

/**
 * Log payroll action
 * 
 * @param int $user_id User ID who performed the action
 * @param string $username Username
 * @param string $action Action performed (e.g., "Generated", "Approved", "Paid")
 * @param string $employee_name Employee name
 * @param string $period_name Payroll period
 * @param float $net_pay Net pay amount
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logPayrollAction($user_id, $username, $action, $employee_name, $period_name, $net_pay = null, $pdo = null) {
    $description = "{$action} payroll for {$employee_name} - Period: {$period_name}";
    if ($net_pay !== null) {
        $description .= " - Net Pay: ₱" . number_format($net_pay, 2);
    }
    return logAccountActivity(
        $user_id,
        $username,
        "{$action} Payroll",
        'other',
        $description,
        $pdo
    );
}

/**
 * Log DTR action
 * 
 * @param int $user_id User ID who performed the action
 * @param string $username Username
 * @param string $action Action performed (e.g., "Imported", "Updated")
 * @param string $employee_name Employee name
 * @param string $details Additional details
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logDTRAction($user_id, $username, $action, $employee_name, $details = null, $pdo = null) {
    $description = "{$action} DTR for {$employee_name}";
    if ($details) {
        $description .= " - {$details}";
    }
    return logAccountActivity(
        $user_id,
        $username,
        "{$action} DTR",
        'other',
        $description,
        $pdo
    );
}

/**
 * Log general action
 * 
 * @param int $user_id User ID who performed the action
 * @param string $username Username
 * @param string $action Action name
 * @param string $action_type Action type (login, logout, profile_update, password_change, create, update, delete, other)
 * @param string $description Description of the action
 * @param PDO $pdo Database connection (optional)
 * @return bool Success status
 */
function logAction($user_id, $username, $action, $action_type, $description, $pdo = null) {
    return logAccountActivity($user_id, $username, $action, $action_type, $description, $pdo);
}
