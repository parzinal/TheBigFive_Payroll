<?php
/**
 * Authentication Security Helper
 * Centralized session validation and role-based access control
 * Prevents unauthorized access even if users manually change URLs
 */

// Load bootstrap (env, session hardening, security headers)
// Bootstrap handles session_start() with secure cookie params
require_once __DIR__ . '/bootstrap.php';

// Include CSRF protection
require_once __DIR__ . '/csrf.php';

/**
 * Check if user is authenticated
 * @return bool
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['role']) && 
           isset($_SESSION['auth_token']) &&
           isset($_SESSION['login_time']);
}

/**
 * Validate session security token
 * Prevents session hijacking
 * @return bool
 */
function validateSessionToken() {
    if (!isset($_SESSION['auth_token']) || !isset($_SESSION['user_agent'])) {
        return false;
    }
    
    // Verify user agent hasn't changed (basic hijacking prevention)
    $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return $_SESSION['user_agent'] === $currentUserAgent;
}

/**
 * Check if user has required role
 * @param string $requiredRole - Role required to access the page
 * @return bool
 */
function hasRole($requiredRole) {
    if (!isAuthenticated()) {
        return false;
    }
    
    return $_SESSION['role'] === $requiredRole;
}

/**
 * Require authentication - redirect to login if not authenticated
 * @param string $requiredRole - Role required to access the page (admin, staff, user)
 * @param string $redirectUrl - URL to redirect if authentication fails (default: ../login.php)
 */
function requireAuth($requiredRole = null, $redirectUrl = '../login.php') {
    // Check if user is authenticated
    if (!isAuthenticated()) {
        // Destroy invalid session
        session_unset();
        session_destroy();
        header('Location: ' . $redirectUrl);
        exit();
    }
    
    // Validate session token to prevent hijacking
    if (!validateSessionToken()) {
        // Session may be hijacked - destroy and redirect
        session_unset();
        session_destroy();
        header('Location: ' . $redirectUrl . '?error=session_invalid');
        exit();
    }
    
    // Check session timeout (optional - 8 hours)
    $sessionTimeout = 8 * 60 * 60; // 8 hours in seconds
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $sessionTimeout) {
        session_unset();
        session_destroy();
        header('Location: ' . $redirectUrl . '?error=session_expired');
        exit();
    }
    
    // If a specific role is required, check it
    if ($requiredRole !== null && !hasRole($requiredRole)) {
        // User is logged in but doesn't have the required role
        // Redirect to their proper dashboard
        redirectToProperDashboard($_SESSION['role']);
        exit();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Redirect user to their proper dashboard based on role
 * @param string $role
 */
function redirectToProperDashboard($role) {
    switch ($role) {
        case 'admin':
            header('Location: ' . getBaseUrl() . 'admin/dashboard.php');
            break;
        case 'staff':
            header('Location: ' . getBaseUrl() . 'staff/dashboard_staff.php');
            break;
        case 'user':
            header('Location: ' . getBaseUrl() . 'user/dashboard_user.php');
            break;
        default:
            // Unknown role - destroy session and redirect to login
            session_unset();
            session_destroy();
            header('Location: ' . getBaseUrl() . 'login.php');
    }
}

/**
 * Get base URL for redirects
 * @return string
 */
function getBaseUrl() {
    // Get the current script path
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    
    // Count how many levels deep we are
    $levels = substr_count($scriptPath, '/');
    
    // If we're in a subdirectory (admin, staff, etc.), go up one level
    if (strpos($scriptPath, '/admin') !== false || 
        strpos($scriptPath, '/staff') !== false || 
        strpos($scriptPath, '/user') !== false) {
        return '../';
    }
    
    return './';
}

/**
 * Initialize secure session for new login
 * @param array $userData - User data from database
 */
function initializeSecureSession($userData) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    // Set session data
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['username'] = $userData['username'];
    $_SESSION['email'] = $userData['email'];
    $_SESSION['full_name'] = $userData['full_name'];
    $_SESSION['role'] = $userData['role'];
    
    // Security tokens
    $_SESSION['auth_token'] = bin2hex(random_bytes(32));
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';

    // Generate fresh CSRF token for the new session
    regenerateCSRFToken();
}

/**
 * Destroy session completely
 */
function destroySecureSession() {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Check if user is admin
 * @return bool
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if user is staff
 * @return bool
 */
function isStaff() {
    return hasRole('staff');
}

/**
 * Check if user is regular user
 * @return bool
 */
function isUser() {
    return hasRole('user');
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}
