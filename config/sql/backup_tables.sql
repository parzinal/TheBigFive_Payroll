-- =====================================================
-- BACKUP & RESTORE FEATURE TABLES
-- Run this migration to add backup/restore support
-- =====================================================

USE thebigfive_payroll;

-- =====================================================
-- BACKUP SETTINGS TABLE
-- Stores automatic backup configuration
-- =====================================================
CREATE TABLE IF NOT EXISTS backup_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO backup_settings (setting_key, setting_value) VALUES
('auto_backup_enabled', '0'),
('auto_backup_frequency', 'daily'),
('auto_backup_time', '02:00'),
('auto_backup_retention', '10'),
('auto_backup_day_of_week', '1'),
('auto_backup_day_of_month', '1'),
('backup_compression', '1'),
('last_auto_backup', NULL),
('backup_cron_token', NULL)
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =====================================================
-- BACKUP HISTORY TABLE
-- Tracks all backups created (manual & automatic)
-- =====================================================
CREATE TABLE IF NOT EXISTS backup_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    backup_type ENUM('manual', 'automatic') NOT NULL DEFAULT 'manual',
    status ENUM('completed', 'failed', 'in_progress') NOT NULL DEFAULT 'completed',
    tables_count INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_backup_type (backup_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
