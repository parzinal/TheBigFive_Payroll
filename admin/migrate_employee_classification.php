<?php
/**
 * Database Migration: Add Employee Classification Column
 * Run this once to add the classification column to the employees table.
 */

require_once '../config/bootstrap.php';
require_once '../config/auth.php';
require_once '../config/database.php';

// C3: Only admins may run migrations
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(403);
    die('<h2 style="color:red;">Access Denied</h2><p>Admin authentication required.</p><a href="../login.php">Login</a>');
}

echo "<h2>Running Database Migration: Add Employee Classification</h2>\n";

try {
    $pdo = getDBConnection();

    // Check if column already exists
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('classification', $columns)) {
        echo "<p style='color:green;'>Column <strong>classification</strong> already exists in <strong>employees</strong> table. No migration needed.</p>\n";
    } else {
        $sql = "ALTER TABLE employees ADD COLUMN classification ENUM('Fix Rate', 'Trainer') NULL DEFAULT NULL AFTER status";
        echo "<p>Running: <code>" . htmlspecialchars($sql) . "</code></p>\n";
        $pdo->exec($sql);
        echo "<p style='color:green;'>&#10003; Column <strong>classification</strong> added successfully.</p>\n";
    }

    echo "<h3 style='color:green;'>Migration completed successfully!</h3>\n";
    echo "<p><a href='Add_emplooyees.php'>Go to Add Employee</a> &nbsp;|&nbsp; <a href='employee_list.php'>View Employee List</a></p>\n";

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
