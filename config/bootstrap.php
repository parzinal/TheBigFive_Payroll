<?php
/**
 * Application Bootstrap
 * =====================
 * Central entry point that loads environment variables, configures error handling,
 * sets security headers, and hardens session settings.
 *
 * Include this file ONCE at the very top of every entry point (or let config files
 * include it). All other config files (database.php, smtp.php, auth.php) depend on
 * the environment variables loaded here.
 *
 * @package TheBigFive Payroll System
 */

// Prevent multiple inclusions
if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

// ==========================================================================
// 1. LOAD ENVIRONMENT VARIABLES
// ==========================================================================

$envPath = __DIR__ . '/../.env';

/**
 * Try vlucas/phpdotenv first (industry standard).
 * Falls back to a lightweight built-in parser if the package isn't installed yet.
 *
 * To install the proper library, run on the host machine:
 *   composer require vlucas/phpdotenv
 */
$dotenvLoaded = false;

// Attempt 1: vlucas/phpdotenv (if installed via Composer)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
            $dotenvLoaded = true;
        } catch (Exception $e) {
            error_log('Dotenv load error: ' . $e->getMessage());
        }
    }
}

// Attempt 2: Built-in lightweight parser (no dependencies)
if (!$dotenvLoaded && file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            // Parse KEY=VALUE
            if (strpos($line, '=') === false) {
                continue;
            }
            $parts = explode('=', $line, 2);
            $key   = trim($parts[0]);
            $value = isset($parts[1]) ? trim($parts[1]) : '';
            // Remove surrounding double quotes
            $len = strlen($value);
            if ($len >= 2 && $value[0] === '"' && $value[$len - 1] === '"') {
                $value = substr($value, 1, -1);
            }
            // Remove surrounding single quotes
            if ($len >= 2 && $value[0] === "'" && $value[$len - 1] === "'") {
                $value = substr($value, 1, -1);
            }
            // Set into environment
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }
    $dotenvLoaded = true;
}

if (!$dotenvLoaded) {
    error_log('WARNING: No .env file found. Using hardcoded defaults.');
}

// ==========================================================================
// 2. ENVIRONMENT HELPER
// ==========================================================================

if (!function_exists('env')) {
    /**
     * Get an environment variable with an optional default.
     *
     * @param  string $key     Environment variable name
     * @param  mixed  $default Fallback value
     * @return mixed
     */
    function env($key, $default = null)
    {
        // Check $_ENV first (set by both dotenv and our parser)
        if (isset($_ENV[$key])) {
            return _castEnvValue($_ENV[$key]);
        }
        // Fall back to getenv()
        $value = getenv($key);
        if ($value !== false) {
            return _castEnvValue($value);
        }
        return $default;
    }
}

if (!function_exists('_castEnvValue')) {
    /**
     * Cast env string values to native PHP types.
     *
     * @param  string $value
     * @return mixed
     */
    function _castEnvValue($value)
    {
        $map = array(
            'true'    => true,
            '(true)'  => true,
            'false'   => false,
            '(false)' => false,
            'null'    => null,
            '(null)'  => null,
            'empty'   => '',
            '(empty)' => '',
        );
        $lower = strtolower($value);
        if (array_key_exists($lower, $map)) {
            return $map[$lower];
        }
        return $value;
    }
}

// ==========================================================================
// 3. ERROR DISPLAY CONTROL
// ==========================================================================

$appDebug = env('APP_DEBUG', false);
$appEnv   = env('APP_ENV', 'production');

if ($appDebug === true || $appEnv === 'local') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
}

// ==========================================================================
// 4. SESSION HARDENING
// ==========================================================================

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    $lifetimeMinutes = (int) env('SESSION_LIFETIME', 480);
    $lifetimeSeconds = $lifetimeMinutes * 60;

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_lifetime', '0');
    ini_set('session.gc_maxlifetime', (string)$lifetimeSeconds);

    if ($isSecure) {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

// ==========================================================================
// 5. SECURITY HEADERS
// ==========================================================================

if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header_remove('X-Powered-By');
}

// ==========================================================================
// 6. APP-LEVEL CONSTANTS
// ==========================================================================

if (!defined('APP_ENV')) {
    define('APP_ENV', env('APP_ENV', 'production'));
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', env('APP_DEBUG', false));
}
if (!defined('APP_NAME')) {
    define('APP_NAME', env('APP_NAME', 'TheBigFive Payroll System'));
}
