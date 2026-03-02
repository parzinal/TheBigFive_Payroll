<?php
/**
 * Database Configuration for TheBigFive Payroll System
 * 
 * Credentials are loaded from the .env file via bootstrap.php.
 * Do NOT hardcode sensitive values here.
 */

// Load bootstrap (environment variables, session, headers)
require_once __DIR__ . '/bootstrap.php';

// Database credentials from environment
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USERNAME', 'root'));
define('DB_PASS', env('DB_PASSWORD', ''));
define('DB_NAME', env('DB_DATABASE', 'thebigfive_payroll'));

// Create database connection
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        die("Connection failed. Please try again later.");
    }
}

// Test connection function
function testConnection() {
    try {
        $pdo = getDBConnection();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
