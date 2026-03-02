<?php
/**
 * Logout Handler
 * Securely destroys user session
 */

// Include helpers (bootstrap.php starts session with hardened settings)
require_once 'config/auth.php';
require_once 'config/account_logs_helper.php';

// Log logout before destroying session
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    logUserLogout($_SESSION['user_id'], $_SESSION['username']);
}

// Destroy session securely
destroySecureSession();

// Redirect to login page
header('Location: login.php?logged_out=1');
exit();
?>
