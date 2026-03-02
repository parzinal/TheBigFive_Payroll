<?php
/**
 * CSRF Protection Helper
 * Generates and validates CSRF tokens to prevent cross-site request forgery attacks.
 * 
 * Usage:
 *   - In forms: echo csrfTokenField();
 *   - In AJAX (meta tag): echo csrfMetaTag();
 *   - Validate POST: validateCSRFToken() — returns true/false
 *   - Get token value: getCSRFToken()
 * 
 * @package TheBigFive Payroll System
 */

// Ensure session is started (bootstrap.php handles this with hardened settings)
// Fallback in case csrf.php is loaded without bootstrap
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/bootstrap.php';
}

/**
 * Generate or retrieve the current CSRF token.
 * One token per session, regenerated on login.
 * 
 * @return string The CSRF token
 */
function getCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Regenerate the CSRF token (call on login / privilege change).
 * 
 * @return string The new CSRF token
 */
function regenerateCSRFToken(): string {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden input field for use in HTML forms.
 * 
 * @return string HTML hidden input element
 */
function csrfTokenField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCSRFToken()) . '">';
}

/**
 * Output a <meta> tag for use by JavaScript AJAX calls.
 * Place this in the <head> section of your layout.
 * JS can read it via: document.querySelector('meta[name="csrf-token"]').content
 * 
 * @return string HTML meta element
 */
function csrfMetaTag(): string {
    return '<meta name="csrf-token" content="' . htmlspecialchars(getCSRFToken()) . '">';
}

/**
 * Validate the CSRF token from the current request.
 * Checks $_POST['csrf_token'] first, then the X-CSRF-Token header (for AJAX).
 * 
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken(): bool {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken)) {
        return false;
    }

    // Check POST parameter first
    $requestToken = $_POST['csrf_token'] ?? '';

    // Fall back to X-CSRF-Token header (for AJAX requests)
    if (empty($requestToken)) {
        $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (empty($requestToken)) {
        return false;
    }

    return hash_equals($sessionToken, $requestToken);
}

/**
 * Validate CSRF and send a JSON error response if invalid.
 * Use this at the top of API endpoints that accept POST requests.
 * 
 * @param bool $exitOnFailure Whether to exit after sending error (default: true)
 * @return bool True if valid
 */
function requireCSRFToken(bool $exitOnFailure = true): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true; // Only validate POST requests
    }

    if (!validateCSRFToken()) {
        if ($exitOnFailure) {
            http_response_code(403);
            if (isJsonRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.'
                ]);
            } else {
                // For form submissions, redirect back with error
                $referer = $_SERVER['HTTP_REFERER'] ?? '../login.php';
                header('Location: ' . $referer . '?error=csrf_invalid');
            }
            exit();
        }
        return false;
    }
    return true;
}

/**
 * Detect if the current request expects a JSON response.
 * 
 * @return bool
 */
function isJsonRequest(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $xRequested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return (stripos($accept, 'application/json') !== false)
        || (stripos($contentType, 'application/json') !== false)
        || (strtolower($xRequested) === 'xmlhttprequest')
        || !empty($_SERVER['HTTP_X_CSRF_TOKEN']); // AJAX requests typically send this header
}
