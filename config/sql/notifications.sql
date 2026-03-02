-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    icon VARCHAR(50) DEFAULT 'fa-info-circle',
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample notifications
INSERT INTO notifications (user_id, title, message, type, icon, link) 
SELECT 
    u.id,
    'Welcome to TheBigFive Payroll',
    'Your account has been successfully created. Start by exploring the dashboard.',
    'success',
    'fa-check-circle',
    'dashboard.php'
FROM users u
WHERE NOT EXISTS (
    SELECT 1 FROM notifications WHERE user_id = u.id
)
LIMIT 3;
