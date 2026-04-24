<?php
/**
 * Backup & Restore API Endpoint
 * Handles AJAX requests for backup operations
 * 
 * @package TheBigFive Payroll System
 */

// =====================================================
// COMPREHENSIVE ERROR HANDLING
// Catches ALL PHP errors and returns JSON
// =====================================================
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Custom error handler to convert PHP warnings/notices to exceptions
set_error_handler(function($severity, $message, $file, $line) {
    // Don't throw for suppressed errors (@)
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clear any output that might have been generated
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        // Ensure JSON header
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode(array_merge([
            'success' => false, 
            'message' => 'PHP Fatal error: ' . $error['message']
        ], (defined('APP_DEBUG') && APP_DEBUG) ? [
            'debug' => [
                'file' => basename($error['file']),
                'line' => $error['line'],
                'type' => $error['type']
            ]
        ] : []));
    }
});

// Start output buffering to catch any stray output
ob_start();

// Start session via bootstrap (hardened settings)
require_once '../config/bootstrap.php';
// Re-suppress errors for clean JSON API output
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Set JSON response header
header('Content-Type: application/json');

// Auth check - admin only
require_once '../config/auth.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin access required.']);
    exit;
}

// Include dependencies
require_once '../config/database.php';
require_once '../config/account_logs_helper.php';

// CSRF check for POST requests
requireCSRFToken();

// Try to load BackupManager (may fail if vendor deps are corrupted)
$backupManagerAvailable = false;
try {
    require_once '../config/BackupManager.php';
    $backupManagerAvailable = class_exists('BackupManager');
} catch (\Throwable $e) {
    error_log("BackupManager load failed: " . $e->getMessage());
    $backupManagerAvailable = false;
}

// =====================================================
// FALLBACK BACKUP FUNCTION (no spatie dependency)
// Used when BackupManager fails to load
// =====================================================
function fallbackCreateBackup($pdo) {
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    // Ensure backup tables exist
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS backup_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT NULL,
                updated_by INT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insert default settings if empty
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM backup_settings");
        $count = $stmt->fetch();
        if ((int)($count['cnt'] ?? 0) === 0) {
            $pdo->exec("
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
            ");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS backup_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                file_size BIGINT NOT NULL DEFAULT 0,
                backup_type ENUM('manual', 'automatic') NOT NULL DEFAULT 'manual',
                status ENUM('completed', 'failed', 'in_progress') NOT NULL DEFAULT 'completed',
                tables_count INT NOT NULL DEFAULT 0,
                notes TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\PDOException $e) {
        error_log("Fallback: table creation note: " . $e->getMessage());
    }

    $timestamp = date('Y-m-d_H-i-s');
    $dbName = DB_NAME;
    $filename = "backup_{$dbName}_{$timestamp}.sql.gz";
    $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;

    // Find mysqldump
    $mysqldumpPath = findMysqldumpPath();
    if (empty($mysqldumpPath)) {
        return ['success' => false, 'message' => 'mysqldump not found. Please ensure MySQL tools are installed.'];
    }

    // Build command
    $command = sprintf(
        '"%s" --host=%s --user=%s %s --routines --triggers --single-transaction --quick %s',
        $mysqldumpPath,
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        !empty(DB_PASS) ? '--password=' . escapeshellarg(DB_PASS) : '',
        escapeshellarg($dbName)
    );

    // Add gzip compression
    $command .= ' | gzip > ' . escapeshellarg($filepath);

    // On Windows, use cmd /c for piping
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: use direct dump without pipe (gzip might not be available)
        $filepathSql = str_replace('.sql.gz', '.sql', $filepath);
        $filename = str_replace('.sql.gz', '.sql', $filename);
        $command = sprintf(
            '"%s" --host=%s --user=%s %s --routines --triggers --single-transaction --quick --result-file="%s" %s 2>&1',
            $mysqldumpPath,
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            !empty(DB_PASS) ? '--password=' . escapeshellarg(DB_PASS) : '',
            $filepathSql,
            escapeshellarg($dbName)
        );
        $filepath = $filepathSql;
    }

    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        $errorMsg = implode("\n", $output);
        error_log("Fallback backup failed (code $returnCode): " . $errorMsg);
        return ['success' => false, 'message' => 'Backup failed: ' . ($errorMsg ?: 'mysqldump returned error code ' . $returnCode)];
    }

    if (!file_exists($filepath) || filesize($filepath) === 0) {
        return ['success' => false, 'message' => 'Backup file was not created or is empty'];
    }

    $fileSize = filesize($filepath);

    // Get tables count
    $stmt = $pdo->query("SHOW TABLES");
    $tablesCount = $stmt->rowCount();

    // Record history
    try {
        $stmt = $pdo->prepare("INSERT INTO backup_history (filename, file_size, backup_type, status, tables_count, created_by) VALUES (?, ?, 'manual', 'completed', ?, ?)");
        $stmt->execute([$filename, $fileSize, $tablesCount, $_SESSION['user_id'] ?? null]);
    } catch (\PDOException $e) {
        error_log("Fallback: history insert note: " . $e->getMessage());
    }

    return [
        'success' => true,
        'filename' => $filename,
        'file_size' => $fileSize,
        'tables_count' => $tablesCount,
        'message' => 'Backup created successfully (fallback mode)'
    ];
}

function findMysqldumpPath() {
    // Search common paths
    $possiblePaths = [];
    
    // Laragon paths
    $laragonDirs = ['C:\\laragon\\bin\\mysql', 'D:\\laragon\\bin\\mysql', 'E:\\laragon\\bin\\mysql'];
    foreach ($laragonDirs as $dir) {
        if (is_dir($dir)) {
            $subdirs = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            if ($subdirs) {
                rsort($subdirs);
                foreach ($subdirs as $subdir) {
                    $possiblePaths[] = $subdir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysqldump.exe';
                    $possiblePaths[] = $subdir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysqldump';
                }
            }
        }
    }
    
    // XAMPP
    $possiblePaths[] = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    $possiblePaths[] = 'D:\\xampp\\mysql\\bin\\mysqldump.exe';
    
    // Linux/Mac
    $possiblePaths = array_merge($possiblePaths, [
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
        '/usr/local/mysql/bin/mysqldump',
    ]);
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Try system PATH
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $result = @shell_exec('where mysqldump 2>NUL');
    } else {
        $result = @shell_exec('which mysqldump 2>/dev/null');
    }
    $result = trim($result ?? '');
    if (!empty($result)) {
        return trim(explode("\n", $result)[0]);
    }
    
    return '';
}

function fallbackFormatFileSize($bytes) {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function ensureSalaryRulesTable(PDO $pdo): void {
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS payroll_shift_rules (\n            shift_code VARCHAR(20) PRIMARY KEY,\n            shift_name VARCHAR(100) NOT NULL,\n            per_day_rate DECIMAL(12,2) NOT NULL DEFAULT 0,\n            ot_rate DECIMAL(12,2) NOT NULL DEFAULT 0,\n            time_in TIME NOT NULL DEFAULT '08:00:00',\n            time_out TIME NOT NULL DEFAULT '17:00:00',\n            updated_by INT NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");

    // Backward-compatible migration for existing tables.
    try {
        $pdo->exec("ALTER TABLE payroll_shift_rules ADD COLUMN ot_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER per_day_rate");
    } catch (\Throwable $e) {
        // Ignore if column already exists.
    }

    $pdo->exec("\n        INSERT IGNORE INTO payroll_shift_rules (shift_code, shift_name, per_day_rate, ot_rate, time_in, time_out) VALUES\n        ('shift_1', 'Shift 1', 0.00, 0.00, '08:00:00', '17:00:00'),\n        ('shift_2', 'Shift 2', 0.00, 0.00, '08:00:00', '17:00:00')\n    ");
}

function ensurePayrollRuleSettingsTable(PDO $pdo): void {
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS payroll_rule_settings (\n            id TINYINT UNSIGNED PRIMARY KEY,\n            late_rule_1_actual_minutes DECIMAL(10,2) NULL,\n            late_rule_1_equivalent_minutes DECIMAL(10,2) NULL,\n            late_rule_2_actual_minutes DECIMAL(10,2) NULL,\n            late_rule_2_equivalent_minutes DECIMAL(10,2) NULL,\n            late_rule_3_actual_minutes DECIMAL(10,2) NULL,\n            late_rule_3_equivalent_minutes DECIMAL(10,2) NULL,\n            fixed_per_day_rate DECIMAL(12,2) NOT NULL DEFAULT 0,\n            trainer_per_day_rate DECIMAL(12,2) NOT NULL DEFAULT 500,\n            grace_period_minutes INT NOT NULL DEFAULT 5,\n            updated_by INT NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");

    // Backward-compatible migration for existing single-rule tables.
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_1_actual_minutes DECIMAL(10,2) NULL AFTER id");
    } catch (\Throwable $e) {
        // Ignore if column already exists.
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_1_equivalent_minutes DECIMAL(10,2) NULL AFTER late_rule_1_actual_minutes");
    } catch (\Throwable $e) {
        // Ignore if column already exists.
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_2_actual_minutes DECIMAL(10,2) NULL AFTER late_rule_1_equivalent_minutes");
    } catch (\Throwable $e) {
        // Ignore if column already exists.
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_2_equivalent_minutes DECIMAL(10,2) NULL AFTER late_rule_2_actual_minutes");
    } catch (\Throwable $e) {
        // Ignore if column already exists.
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_3_actual_minutes DECIMAL(10,2) NULL AFTER late_rule_2_equivalent_minutes");
    } catch (\Throwable $e) {
        // Ignore if column already exists.
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_3_equivalent_minutes DECIMAL(10,2) NULL AFTER late_rule_3_actual_minutes");
    } catch (\Throwable $e) {
        // Ignore if column already exists.
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN fixed_per_day_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER late_rule_3_equivalent_minutes");
    } catch (\Throwable $e) {
        // Ignore if column already exists.
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN trainer_per_day_rate DECIMAL(12,2) NOT NULL DEFAULT 500 AFTER fixed_per_day_rate");
    } catch (\Throwable $e) {
        // Ignore if column already exists.
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN grace_period_minutes INT NOT NULL DEFAULT 5 AFTER trainer_per_day_rate");
    } catch (\Throwable $e) {
        // Ignore if column already exists.
    }

    $pdo->exec("\n        INSERT IGNORE INTO payroll_rule_settings (id)\n        VALUES (1)\n    ");
}

function normalizeRuleTime(string $value, string $fallback): string {
    $value = trim($value);
    if (!preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', $value)) {
        return $fallback;
    }
    [$h, $m] = explode(':', $value);
    return str_pad((string)intval($h), 2, '0', STR_PAD_LEFT) . ':' . $m;
}

function normalizeRuleRate($value): float {
    $num = floatval($value);
    if (!is_finite($num) || $num < 0) {
        return 0.0;
    }
    return round($num, 2);
}

function normalizeRuleMinutesOrNull($value): ?float {
    if ($value === null) {
        return null;
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    $num = floatval($raw);
    if (!is_finite($num) || $num <= 0) {
        return null;
    }
    return round(min(1440.0, $num), 2);
}

function normalizeRuleGraceMinutes($value, int $fallback = 5): int {
    $num = intval($value);
    if (!is_finite($num)) {
        return $fallback;
    }
    if ($num < 0) {
        return 0;
    }
    return min(120, $num);
}

// Get the action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Clean the output buffer before sending response
ob_end_clean();

try {
    // Try to use BackupManager, fall back to direct mysqldump if it fails
    $backupManager = null;
    if ($backupManagerAvailable) {
        try {
            $backupManager = new BackupManager();
        } catch (\Throwable $e) {
            error_log("BackupManager instantiation failed: " . $e->getMessage());
            $backupManager = null;
        }
    }

    switch ($action) {

        // =====================================================
        // CREATE MANUAL BACKUP
        // =====================================================
        case 'create_backup':
            if ($backupManager) {
                try {
                    $result = $backupManager->createBackup('manual', $_SESSION['user_id']);
                } catch (\Throwable $bmError) {
                    // BackupManager/spatie failed at runtime — fall through to direct mysqldump
                    error_log("BackupManager createBackup failed, using fallback: " . $bmError->getMessage());
                    $pdo = getDBConnection();
                    $result = fallbackCreateBackup($pdo);
                }
                // If BackupManager returned failure (caught exception internally), try fallback
                if (!$result['success']) {
                    error_log("BackupManager returned failure, trying fallback: " . ($result['message'] ?? ''));
                    $pdo = getDBConnection();
                    $fallbackResult = fallbackCreateBackup($pdo);
                    if ($fallbackResult['success']) {
                        $result = $fallbackResult;
                    }
                    // If fallback also fails, keep original error message
                }
            } else {
                // Fallback: direct mysqldump
                $pdo = getDBConnection();
                $result = fallbackCreateBackup($pdo);
            }

            if ($result['success']) {
                // Log the action
                try {
                    logAccountActivity(
                        $_SESSION['user_id'],
                        $_SESSION['username'] ?? 'admin',
                        'Created manual database backup: ' . $result['filename'],
                        'other',
                        'File size: ' . ($backupManager ? $backupManager->formatFileSize($result['file_size']) : fallbackFormatFileSize($result['file_size'])) . ', Tables: ' . $result['tables_count'],
                        getDBConnection()
                    );
                } catch (\Throwable $e) {
                    error_log("Backup log error: " . $e->getMessage());
                }
            }

            echo json_encode($result);
            break;

        // =====================================================
        // DOWNLOAD BACKUP FILE
        // =====================================================
        case 'download_backup':
            $filename = $_GET['filename'] ?? '';
            if (empty($filename)) {
                echo json_encode(['success' => false, 'message' => 'No filename specified']);
                break;
            }

            // Sanitize filename (prevent path traversal)
            $filename = basename($filename);
            $filepath = __DIR__ . '/../backups/' . $filename;

            if (!file_exists($filepath)) {
                echo json_encode(['success' => false, 'message' => 'Backup file not found']);
                break;
            }

            // Switch headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: no-cache, must-revalidate');

            readfile($filepath);
            exit;

        // =====================================================
        // DELETE BACKUP
        // =====================================================
        case 'delete_backup':
            $filename = $_POST['filename'] ?? '';
            if (empty($filename)) {
                echo json_encode(['success' => false, 'message' => 'No filename specified']);
                break;
            }

            if ($backupManager) {
                $result = $backupManager->deleteBackup(basename($filename));
            } else {
                // Fallback delete
                $fp = __DIR__ . '/../backups/' . basename($filename);
                if (file_exists($fp) && unlink($fp)) {
                    $result = ['success' => true, 'message' => 'Backup deleted'];
                } else {
                    $result = ['success' => false, 'message' => 'Failed to delete backup file'];
                }
            }

            if ($result['success']) {
                try {
                    logAccountActivity(
                        $_SESSION['user_id'],
                        $_SESSION['username'] ?? 'admin',
                        'Deleted database backup: ' . basename($filename),
                        'delete',
                        null,
                        getDBConnection()
                    );
                } catch (\Throwable $e) {
                    error_log("Delete log error: " . $e->getMessage());
                }
            }

            echo json_encode($result);
            break;

        // =====================================================
        // RESTORE FROM EXISTING BACKUP
        // =====================================================
        case 'restore_backup':
            if (!$backupManager) {
                echo json_encode(['success' => false, 'message' => 'BackupManager not available. Please check server configuration.']);
                break;
            }
            $filename = $_POST['filename'] ?? '';
            if (empty($filename)) {
                echo json_encode(['success' => false, 'message' => 'No filename specified']);
                break;
            }

            // Create a safety backup before restoring
            $safetyBackup = $backupManager->createBackup('manual', $_SESSION['user_id']);

            $result = $backupManager->restoreFromFile(basename($filename));

            if ($result['success']) {
                try {
                    logAccountActivity(
                        $_SESSION['user_id'],
                        $_SESSION['username'] ?? 'admin',
                        'Restored database from backup: ' . basename($filename),
                        'other',
                        'Safety backup created: ' . ($safetyBackup['filename'] ?? 'none'),
                        getDBConnection()
                    );
                } catch (\Throwable $e) {
                    error_log("Restore log error: " . $e->getMessage());
                }
            }

            // Include safety backup info in response
            $result['safety_backup'] = $safetyBackup['success'] ? $safetyBackup['filename'] : null;

            echo json_encode($result);
            break;

        // =====================================================
        // RESTORE FROM UPLOADED FILE
        // =====================================================
        case 'restore_upload':
            if (!$backupManager) {
                echo json_encode(['success' => false, 'message' => 'BackupManager not available. Please check server configuration.']);
                break;
            }
            if (!isset($_FILES['backup_file'])) {
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                break;
            }

            // Create a safety backup before restoring
            $safetyBackup = $backupManager->createBackup('manual', $_SESSION['user_id']);

            $result = $backupManager->restoreFromUpload($_FILES['backup_file']);

            if ($result['success']) {
                try {
                    logAccountActivity(
                        $_SESSION['user_id'],
                        $_SESSION['username'] ?? 'admin',
                        'Restored database from uploaded file: ' . basename($_FILES['backup_file']['name']),
                        'other',
                        'Safety backup created: ' . ($safetyBackup['filename'] ?? 'none'),
                        getDBConnection()
                    );
                } catch (\Throwable $e) {
                    error_log("Upload restore log error: " . $e->getMessage());
                }
            }

            $result['safety_backup'] = $safetyBackup['success'] ? $safetyBackup['filename'] : null;

            echo json_encode($result);
            break;

        // =====================================================
        // GET BACKUP INFO (PREVIEW)
        // =====================================================
        case 'backup_info':
            $filename = $_GET['filename'] ?? $_POST['filename'] ?? '';
            if (empty($filename)) {
                echo json_encode(['success' => false, 'message' => 'No filename specified']);
                break;
            }

            if ($backupManager) {
                $result = $backupManager->getBackupInfo(basename($filename));
            } else {
                $fp = __DIR__ . '/../backups/' . basename($filename);
                if (file_exists($fp)) {
                    $result = ['success' => true, 'filename' => basename($filename), 'file_size' => filesize($fp), 'tables_count' => 0];
                } else {
                    $result = ['success' => false, 'message' => 'File not found'];
                }
            }
            echo json_encode($result);
            break;

        // =====================================================
        // GET BACKUP LIST
        // =====================================================
        case 'list_backups':
            if ($backupManager) {
                $backups = $backupManager->getBackupList();
            } else {
                // Fallback: list files directly
                $backups = [];
                $backupDir = __DIR__ . '/../backups';
                $files = glob($backupDir . '/*.{sql,sql.gz}', GLOB_BRACE);
                if ($files) {
                    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                    foreach ($files as $file) {
                        $backups[] = [
                            'filename' => basename($file),
                            'file_size' => filesize($file),
                            'file_size_formatted' => fallbackFormatFileSize(filesize($file)),
                            'backup_type' => 'unknown',
                            'status' => 'completed',
                            'tables_count' => 0,
                            'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                            'compressed' => substr(basename($file), -3) === '.gz',
                        ];
                    }
                }
            }
            echo json_encode(['success' => true, 'backups' => $backups]);
            break;

        // =====================================================
        // GET BACKUP HISTORY
        // =====================================================
        case 'backup_history':
            if ($backupManager) {
                $history = $backupManager->getBackupHistory();
            } else {
                $history = [];
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->query("SELECT * FROM backup_history ORDER BY created_at DESC LIMIT 50");
                    $history = $stmt->fetchAll();
                } catch (\Throwable $e) {
                    error_log("Fallback history error: " . $e->getMessage());
                }
            }
            echo json_encode(['success' => true, 'history' => $history]);
            break;

        // =====================================================
        // SAVE SETTINGS
        // =====================================================
        case 'save_settings':
            if (!$backupManager) {
                echo json_encode(['success' => false, 'message' => 'BackupManager not available']);
                break;
            }
            $settings = [
                'auto_backup_enabled' => $_POST['auto_backup_enabled'] ?? '0',
                'auto_backup_frequency' => $_POST['auto_backup_frequency'] ?? 'daily',
                'auto_backup_time' => $_POST['auto_backup_time'] ?? '02:00',
                'auto_backup_retention' => $_POST['auto_backup_retention'] ?? '10',
                'auto_backup_day_of_week' => $_POST['auto_backup_day_of_week'] ?? '1',
                'auto_backup_day_of_month' => $_POST['auto_backup_day_of_month'] ?? '1',
                'backup_compression' => $_POST['backup_compression'] ?? '1',
            ];

            $success = true;
            foreach ($settings as $key => $value) {
                if (!$backupManager->updateSetting($key, $value, $_SESSION['user_id'])) {
                    $success = false;
                }
            }

            if ($success) {
                try {
                    logAccountActivity(
                        $_SESSION['user_id'],
                        $_SESSION['username'] ?? 'admin',
                        'Updated backup settings',
                        'update',
                        'Auto backup: ' . ($settings['auto_backup_enabled'] === '1' ? 'enabled' : 'disabled') . 
                        ', Frequency: ' . $settings['auto_backup_frequency'] .
                        ', Retention: ' . $settings['auto_backup_retention'],
                        getDBConnection()
                    );
                } catch (\Throwable $e) {
                    error_log("Settings log error: " . $e->getMessage());
                }
            }

            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Settings saved successfully' : 'Failed to save some settings'
            ]);
            break;

        // =====================================================
        // GET SETTINGS
        // =====================================================
        case 'get_settings':
            if ($backupManager) {
                $settings = $backupManager->getAllSettings();
            } else {
                $settings = [];
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->query("SELECT setting_key, setting_value FROM backup_settings");
                    while ($row = $stmt->fetch()) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                } catch (\Throwable $e) {
                    error_log("Fallback settings error: " . $e->getMessage());
                }
            }
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        // =====================================================
        // GET SALARY RULES (SHIFT 1 / SHIFT 2)
        // =====================================================
        case 'get_salary_rules':
            $pdo = getDBConnection();
            ensureSalaryRulesTable($pdo);
            ensurePayrollRuleSettingsTable($pdo);

            $stmt = $pdo->query("\n                SELECT
                    shift_code,
                    shift_name,
                    per_day_rate,
                    ot_rate,
                    DATE_FORMAT(time_in, '%H:%i') AS time_in,
                    DATE_FORMAT(time_out, '%H:%i') AS time_out
                FROM payroll_shift_rules
                WHERE shift_code IN ('shift_1', 'shift_2')
                ORDER BY shift_code ASC
            ");

            $rules = [
                'shift_1' => [
                    'shift_code' => 'shift_1',
                    'shift_name' => 'Shift 1',
                    'per_day_rate' => 0,
                    'ot_rate' => 0,
                    'time_in' => '08:00',
                    'time_out' => '17:00',
                ],
                'shift_2' => [
                    'shift_code' => 'shift_2',
                    'shift_name' => 'Shift 2',
                    'per_day_rate' => 0,
                    'ot_rate' => 0,
                    'time_in' => '08:00',
                    'time_out' => '17:00',
                ],
            ];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $code = $row['shift_code'] ?? '';
                if (!isset($rules[$code])) {
                    continue;
                }
                $rules[$code] = [
                    'shift_code' => $code,
                    'shift_name' => trim((string)($row['shift_name'] ?? ($code === 'shift_2' ? 'Shift 2' : 'Shift 1'))),
                    'per_day_rate' => round(floatval($row['per_day_rate'] ?? 0), 2),
                    'ot_rate' => round(floatval($row['ot_rate'] ?? 0), 2),
                    'time_in' => trim((string)($row['time_in'] ?? '08:00')),
                    'time_out' => trim((string)($row['time_out'] ?? '17:00')),
                ];
            }

            $lateRuleStmt = $pdo->prepare("SELECT * FROM payroll_rule_settings WHERE id = 1 LIMIT 1");
            $lateRuleStmt->execute();
            $lateRuleRow = $lateRuleStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $lateRules = [];
            for ($i = 1; $i <= 3; $i++) {
                $actual = normalizeRuleMinutesOrNull($lateRuleRow["late_rule_{$i}_actual_minutes"] ?? null);
                $equivalent = normalizeRuleMinutesOrNull($lateRuleRow["late_rule_{$i}_equivalent_minutes"] ?? null);
                $multiplier = ($actual !== null && $equivalent !== null)
                    ? round($equivalent / max(0.01, $actual), 4)
                    : null;

                $lateRules[] = [
                    'actual_minutes' => $actual,
                    'equivalent_minutes' => $equivalent,
                    'multiplier' => $multiplier,
                ];
            }

            $legacyLateRule = null;
            foreach ($lateRules as $ruleItem) {
                if ($ruleItem['actual_minutes'] !== null && $ruleItem['equivalent_minutes'] !== null) {
                    $legacyLateRule = $ruleItem;
                    break;
                }
            }

            $rateProfile = [
                'fixed_per_day_rate' => normalizeRuleRate($lateRuleRow['fixed_per_day_rate'] ?? 0),
                'trainer_per_day_rate' => normalizeRuleRate($lateRuleRow['trainer_per_day_rate'] ?? 500),
                'grace_period_minutes' => normalizeRuleGraceMinutes($lateRuleRow['grace_period_minutes'] ?? 5, 5),
            ];

            echo json_encode([
                'success' => true,
                'rules' => $rules,
                'late_rules' => $lateRules,
                'rate_profile' => $rateProfile,
                // Backward-compatible legacy shape for older consumers.
                'late_rule' => $legacyLateRule,
            ]);
            break;

        // =====================================================
        // SAVE SALARY RULES (SHIFT 1 / SHIFT 2)
        // =====================================================
        case 'save_salary_rules':
            $pdo = getDBConnection();
            ensureSalaryRulesTable($pdo);
            ensurePayrollRuleSettingsTable($pdo);

            $existingPerDayRates = [
                'shift_1' => 0.0,
                'shift_2' => 0.0,
            ];
            $existingRateStmt = $pdo->query("SELECT shift_code, per_day_rate FROM payroll_shift_rules WHERE shift_code IN ('shift_1', 'shift_2')");
            while ($existingRateRow = $existingRateStmt->fetch(PDO::FETCH_ASSOC)) {
                $code = $existingRateRow['shift_code'] ?? '';
                if (isset($existingPerDayRates[$code])) {
                    $existingPerDayRates[$code] = normalizeRuleRate($existingRateRow['per_day_rate'] ?? 0);
                }
            }

            $rateProfile = [
                'fixed_per_day_rate' => normalizeRuleRate($_POST['fixed_per_day_rate'] ?? 0),
                'trainer_per_day_rate' => normalizeRuleRate($_POST['trainer_per_day_rate'] ?? 500),
                'grace_period_minutes' => normalizeRuleGraceMinutes($_POST['grace_period_minutes'] ?? 5, 5),
            ];

            $rulesToSave = [
                'shift_1' => [
                    'shift_name' => trim((string)($_POST['shift_1_name'] ?? 'Shift 1')) ?: 'Shift 1',
                    'per_day_rate' => normalizeRuleRate($_POST['shift_1_per_day_rate'] ?? $existingPerDayRates['shift_1']),
                    'ot_rate' => normalizeRuleRate($_POST['shift_1_ot_rate'] ?? 0),
                    'time_in' => normalizeRuleTime((string)($_POST['shift_1_time_in'] ?? '08:00'), '08:00'),
                    'time_out' => normalizeRuleTime((string)($_POST['shift_1_time_out'] ?? '17:00'), '17:00'),
                ],
                'shift_2' => [
                    'shift_name' => trim((string)($_POST['shift_2_name'] ?? 'Shift 2')) ?: 'Shift 2',
                    'per_day_rate' => normalizeRuleRate($_POST['shift_2_per_day_rate'] ?? $existingPerDayRates['shift_2']),
                    'ot_rate' => normalizeRuleRate($_POST['shift_2_ot_rate'] ?? 0),
                    'time_in' => normalizeRuleTime((string)($_POST['shift_2_time_in'] ?? '08:00'), '08:00'),
                    'time_out' => normalizeRuleTime((string)($_POST['shift_2_time_out'] ?? '17:00'), '17:00'),
                ],
            ];

            $lateRules = [];
            for ($i = 1; $i <= 3; $i++) {
                $actual = normalizeRuleMinutesOrNull($_POST["late_rule_{$i}_actual_minutes"] ?? null);
                $equivalent = normalizeRuleMinutesOrNull($_POST["late_rule_{$i}_equivalent_minutes"] ?? null);
                $multiplier = ($actual !== null && $equivalent !== null)
                    ? round($equivalent / max(0.01, $actual), 4)
                    : null;

                $lateRules[] = [
                    'actual_minutes' => $actual,
                    'equivalent_minutes' => $equivalent,
                    'multiplier' => $multiplier,
                ];
            }

            $stmt = $pdo->prepare("\n                INSERT INTO payroll_shift_rules (shift_code, shift_name, per_day_rate, ot_rate, time_in, time_out, updated_by)\n                VALUES (?, ?, ?, ?, ?, ?, ?)\n                ON DUPLICATE KEY UPDATE\n                    shift_name = VALUES(shift_name),\n                    per_day_rate = VALUES(per_day_rate),\n                    ot_rate = VALUES(ot_rate),\n                    time_in = VALUES(time_in),\n                    time_out = VALUES(time_out),\n                    updated_by = VALUES(updated_by),\n                    updated_at = CURRENT_TIMESTAMP\n            ");

            foreach ($rulesToSave as $shiftCode => $rule) {
                $stmt->execute([
                    $shiftCode,
                    $rule['shift_name'],
                    $rule['per_day_rate'],
                    $rule['ot_rate'],
                    $rule['time_in'] . ':00',
                    $rule['time_out'] . ':00',
                    $_SESSION['user_id'] ?? null,
                ]);
            }

            $lateRuleStmt = $pdo->prepare("\n                INSERT INTO payroll_rule_settings (id, late_rule_1_actual_minutes, late_rule_1_equivalent_minutes, late_rule_2_actual_minutes, late_rule_2_equivalent_minutes, late_rule_3_actual_minutes, late_rule_3_equivalent_minutes, fixed_per_day_rate, trainer_per_day_rate, grace_period_minutes, updated_by)\n                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n                ON DUPLICATE KEY UPDATE\n                    late_rule_1_actual_minutes = VALUES(late_rule_1_actual_minutes),\n                    late_rule_1_equivalent_minutes = VALUES(late_rule_1_equivalent_minutes),\n                    late_rule_2_actual_minutes = VALUES(late_rule_2_actual_minutes),\n                    late_rule_2_equivalent_minutes = VALUES(late_rule_2_equivalent_minutes),\n                    late_rule_3_actual_minutes = VALUES(late_rule_3_actual_minutes),\n                    late_rule_3_equivalent_minutes = VALUES(late_rule_3_equivalent_minutes),\n                    fixed_per_day_rate = VALUES(fixed_per_day_rate),\n                    trainer_per_day_rate = VALUES(trainer_per_day_rate),\n                    grace_period_minutes = VALUES(grace_period_minutes),\n                    updated_by = VALUES(updated_by),\n                    updated_at = CURRENT_TIMESTAMP\n            ");
            $lateRuleStmt->execute([
                $lateRules[0]['actual_minutes'],
                $lateRules[0]['equivalent_minutes'],
                $lateRules[1]['actual_minutes'],
                $lateRules[1]['equivalent_minutes'],
                $lateRules[2]['actual_minutes'],
                $lateRules[2]['equivalent_minutes'],
                $rateProfile['fixed_per_day_rate'],
                $rateProfile['trainer_per_day_rate'],
                $rateProfile['grace_period_minutes'],
                $_SESSION['user_id'] ?? null,
            ]);

            $lateRuleLogItems = [];
            foreach ($lateRules as $idx => $ruleItem) {
                if ($ruleItem['actual_minutes'] === null || $ruleItem['equivalent_minutes'] === null) {
                    continue;
                }
                $lateRuleLogItems[] = sprintf(
                    'R%s %s->%s (x%s)',
                    $idx + 1,
                    number_format($ruleItem['actual_minutes'], 2),
                    number_format($ruleItem['equivalent_minutes'], 2),
                    number_format($ruleItem['multiplier'], 4)
                );
            }
            $lateRuleLog = !empty($lateRuleLogItems) ? implode(', ', $lateRuleLogItems) : 'none';

            try {
                logAccountActivity(
                    $_SESSION['user_id'],
                    $_SESSION['username'] ?? 'admin',
                    'Updated salary rules for Shift 1 and Shift 2',
                    'update',
                    sprintf(
                        'Shift 1: OT %s @ %s-%s | Shift 2: OT %s @ %s-%s | Rate Profile: Fixed %s, Trainer %s, Grace %s min | Late rules: %s',
                        number_format($rulesToSave['shift_1']['ot_rate'], 2),
                        $rulesToSave['shift_1']['time_in'],
                        $rulesToSave['shift_1']['time_out'],
                        number_format($rulesToSave['shift_2']['ot_rate'], 2),
                        $rulesToSave['shift_2']['time_in'],
                        $rulesToSave['shift_2']['time_out'],
                        number_format($rateProfile['fixed_per_day_rate'], 2),
                        number_format($rateProfile['trainer_per_day_rate'], 2),
                        $rateProfile['grace_period_minutes'],
                        $lateRuleLog
                    ),
                    getDBConnection()
                );
            } catch (\Throwable $e) {
                error_log('Salary rules log error: ' . $e->getMessage());
            }

            $legacyLateRule = null;
            foreach ($lateRules as $ruleItem) {
                if ($ruleItem['actual_minutes'] !== null && $ruleItem['equivalent_minutes'] !== null) {
                    $legacyLateRule = $ruleItem;
                    break;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Salary rules saved successfully',
                'rules' => $rulesToSave,
                'late_rules' => $lateRules,
                'rate_profile' => $rateProfile,
                // Backward-compatible legacy shape for older consumers.
                'late_rule' => $legacyLateRule,
            ]);
            break;

        // =====================================================
        // GET DATABASE INFO
        // =====================================================
        case 'db_info':
            if ($backupManager) {
                $tables = $backupManager->getTableInfo();
                $dbSize = $backupManager->getDatabaseSize();
                $tablesCount = $backupManager->getTablesCount();
            } else {
                $pdo = getDBConnection();
                $stmt = $pdo->query("SHOW TABLES");
                $tablesCount = $stmt->rowCount();
                $tables = [];
                $dbSize = '0 B';
            }

            echo json_encode([
                'success' => true,
                'database' => DB_NAME,
                'tables' => $tables,
                'tables_count' => $tablesCount,
                'total_size' => $dbSize
            ]);
            break;

        // =====================================================
        // TEST MYSQLDUMP AVAILABILITY
        // =====================================================
        case 'test_mysqldump':
            if ($backupManager) {
                $result = $backupManager->testMysqlDump();
            } else {
                $path = findMysqldumpPath();
                $result = [
                    'success' => !empty($path),
                    'available' => !empty($path),
                    'path' => $path ?: 'not found',
                    'message' => !empty($path) ? 'mysqldump found at: ' . $path : 'mysqldump not found'
                ];
            }
            echo json_encode($result);
            break;

        // =====================================================
        // GET CRON URL
        // =====================================================
        case 'get_cron_url':
            if ($backupManager) {
                $token = $backupManager->getCronToken();
            } else {
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("SELECT setting_value FROM backup_settings WHERE setting_key = 'backup_cron_token'");
                    $stmt->execute();
                    $row = $stmt->fetch();
                    $token = $row ? $row['setting_value'] : bin2hex(random_bytes(32));
                    if (!$row) {
                        $pdo->prepare("INSERT INTO backup_settings (setting_key, setting_value) VALUES ('backup_cron_token', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$token, $token]);
                    }
                } catch (\Throwable $e) {
                    $token = bin2hex(random_bytes(16));
                }
            }

            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = dirname($_SERVER['SCRIPT_NAME']);
            $cronUrl = $protocol . '://' . $host . $basePath . '/backup_cron.php?token=' . $token;

            echo json_encode([
                'success' => true,
                'cron_url' => $cronUrl,
                'token' => $token
            ]);
            break;

        // =====================================================
        // REGENERATE CRON TOKEN
        // =====================================================
        case 'regenerate_token':
            $token = bin2hex(random_bytes(32));

            if ($backupManager) {
                $backupManager->updateSetting('backup_cron_token', $token, $_SESSION['user_id']);
            } else {
                try {
                    $pdo = getDBConnection();
                    $pdo->prepare("INSERT INTO backup_settings (setting_key, setting_value) VALUES ('backup_cron_token', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$token, $token]);
                } catch (\Throwable $e) {
                    error_log("Fallback token error: " . $e->getMessage());
                }
            }

            echo json_encode([
                'success' => true,
                'token' => $token,
                'message' => 'Cron token regenerated. Update your scheduled task with the new URL.'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (\Throwable $e) {
    error_log("Backup API error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(200); // Return 200 so JS can parse the JSON error message
    echo json_encode(array_merge([
        'success' => false, 
        'message' => 'An error occurred: ' . $e->getMessage()
    ], (defined('APP_DEBUG') && APP_DEBUG) ? [
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ] : []));
}
