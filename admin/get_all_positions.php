<?php
/**
 * Get All Positions
 * Returns all unique positions from the employees table
 */

require_once '../config/bootstrap.php';
require_once '../config/auth.php';

// H1: Require admin role
if (!isAuthenticated() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Create positions table if it doesn't exist (for backward compatibility)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS positions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            position_name VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_position_name (position_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Get positions from both the positions table and employees table
    // Union to get all unique positions
    $stmt = $pdo->query("
        SELECT position_name as position FROM positions
        UNION
        SELECT DISTINCT position FROM employees 
        WHERE position IS NOT NULL AND position != ''
        ORDER BY position ASC
    ");
    
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'positions' => $positions
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
