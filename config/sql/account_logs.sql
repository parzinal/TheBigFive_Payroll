-- =====================================================
-- ACCOUNT LOGS TABLE
-- Track all user account activities for audit purposes
-- =====================================================

CREATE TABLE IF NOT EXISTS account_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    action VARCHAR(100) NOT NULL,
    action_type ENUM('login', 'logout', 'profile_update', 'password_change', 'create', 'update', 'delete', 'other') NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add some sample logs (optional - for testing)
-- These will be added automatically as users perform actions
