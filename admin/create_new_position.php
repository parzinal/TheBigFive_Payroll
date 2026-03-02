<?php
/**
 * Create New Position
 * Adds a new position to the positions reference table
 */

require_once '../config/bootstrap.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once '../config/database.php';

// Include account logs helper
require_once '../config/account_logs_helper.php';

require_once '../config/csrf.php';

header('Content-Type: application/json');

// CSRF check
requireCSRFToken();

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['position_name']) || empty(trim($data['position_name']))) {
        echo json_encode([
            'success' => false,
            'message' => 'Position name is required'
        ]);
        exit;
    }
    
    $positionName = trim($data['position_name']);
    $description = isset($data['description']) ? trim($data['description']) : null;
    
    $pdo = getDBConnection();
    
    // Create positions table if it doesn't exist
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
    
    // Check if position already exists
    $stmt = $pdo->prepare("SELECT id FROM positions WHERE position_name = ?");
    $stmt->execute([$positionName]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'This position already exists'
        ]);
        exit;
    }
    
    // Insert new position
    $stmt = $pdo->prepare("
        INSERT INTO positions (position_name, description) 
        VALUES (?, ?)
    ");
    
    $stmt->execute([$positionName, $description]);
    
    // Log position creation
    logCreateAction(
        $_SESSION['user_id'],
        $_SESSION['username'],
        'Position',
        $positionName,
        $pdo
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Position created successfully',
        'position_id' => $pdo->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        // Duplicate entry error
        echo json_encode([
            'success' => false,
            'message' => 'This position already exists'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
