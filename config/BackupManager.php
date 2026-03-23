<?php
/**
 * BackupManager Class
 * Handles database backup and restore operations using spatie/db-dumper
 * 
 * @package TheBigFive Payroll System
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/database.php';

use Spatie\DbDumper\Databases\MySql;

class BackupManager
{
    private $pdo;
    private $backupDir;
    private $dbHost;
    private $dbUser;
    private $dbPass;
    private $dbName;

    public function __construct()
    {
        $this->pdo = getDBConnection();
        $this->backupDir = __DIR__ . '/../backups';
        $this->dbHost = DB_HOST;
        $this->dbUser = DB_USER;
        $this->dbPass = DB_PASS;
        $this->dbName = DB_NAME;

        // Ensure backup directory exists
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        // Auto-create required tables if they don't exist
        $this->ensureTablesExist();
    }

    /**
     * Create backup_settings and backup_history tables if they don't exist
     */
    private function ensureTablesExist(): void
    {
        try {
            // Check and create backup_settings table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS backup_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT NULL,
                    updated_by INT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_setting_key (setting_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Insert default settings if table is empty
            $stmt = $this->pdo->query("SELECT COUNT(*) as cnt FROM backup_settings");
            $count = $stmt->fetch();
            if ((int)($count['cnt'] ?? 0) === 0) {
                $this->pdo->exec("
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

            // Check and create backup_history table
            $this->pdo->exec("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\PDOException $e) {
            error_log("BackupManager: Failed to ensure tables exist: " . $e->getMessage());
        }
    }

    /**
     * Get the path to mysqldump binary
     * Checks common Laragon and system locations
     */
    private function getMysqlDumpPath(): string
    {
        $possiblePaths = [];

        // Search Laragon MySQL directories dynamically (most common setup)
        $laragonMysqlDirs = [
            'C:\\laragon\\bin\\mysql',
            'D:\\laragon\\bin\\mysql',
            'E:\\laragon\\bin\\mysql',
        ];

        foreach ($laragonMysqlDirs as $laragonMysqlDir) {
            if (is_dir($laragonMysqlDir)) {
                $dirs = glob($laragonMysqlDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                if ($dirs) {
                    // Sort descending so newest version is checked first
                    rsort($dirs);
                    foreach ($dirs as $dir) {
                        $possiblePaths[] = $dir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysqldump.exe';
                        $possiblePaths[] = $dir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysqldump';
                    }
                }
            }
        }

        // XAMPP paths
        $possiblePaths[] = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        $possiblePaths[] = 'D:\\xampp\\mysql\\bin\\mysqldump.exe';

        // WAMP paths
        $wampMysqlDir = 'C:\\wamp64\\bin\\mysql';
        if (is_dir($wampMysqlDir)) {
            $dirs = glob($wampMysqlDir . '\\*', GLOB_ONLYDIR);
            if ($dirs) {
                rsort($dirs);
                foreach ($dirs as $dir) {
                    $possiblePaths[] = $dir . '\\bin\\mysqldump.exe';
                }
            }
        }

        // Common Linux/Mac paths
        $possiblePaths = array_merge($possiblePaths, [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
        ]);

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fallback: try to find via `where` on Windows or `which` on Linux
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $result = @shell_exec('where mysqldump 2>NUL');
        } else {
            $result = @shell_exec('which mysqldump 2>/dev/null');
        }

        $result = trim($result ?? '');
        if (!empty($result)) {
            $lines = explode("\n", $result);
            return trim($lines[0]);
        }

        return 'mysqldump'; // Let it try the system PATH as last resort
    }

    /**
     * Get the path to mysql binary (for restore)
     */
    private function getMysqlPath(): string
    {
        $dumpPath = $this->getMysqlDumpPath();
        $mysqlPath = str_replace('mysqldump', 'mysql', $dumpPath);
        
        if (file_exists($mysqlPath)) {
            return $mysqlPath;
        }

        // Fallback search
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $result = trim(shell_exec('where mysql 2>NUL') ?? '');
        } else {
            $result = trim(shell_exec('which mysql 2>/dev/null') ?? '');
        }

        if (!empty($result)) {
            $lines = explode("\n", $result);
            return trim($lines[0]);
        }

        return 'mysql';
    }

    /**
     * Create a database backup
     * 
     * @param string $type 'manual' or 'automatic'
     * @param int|null $userId User who triggered the backup
     * @return array Result with success status, filename, and message
     */
    public function createBackup(string $type = 'manual', ?int $userId = null): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $useCompression = $this->getSetting('backup_compression', '1') === '1';
        $extension = $useCompression ? '.sql.gz' : '.sql';
        $filename = "backup_{$this->dbName}_{$timestamp}{$extension}";
        $filepath = $this->backupDir . DIRECTORY_SEPARATOR . $filename;

        // Record in-progress status
        $historyId = $this->recordBackupHistory($filename, 0, $type, 'in_progress', 0, null, $userId);

        try {
            $dumper = MySql::create()
                ->setDbName($this->dbName)
                ->setUserName($this->dbUser)
                ->setHost($this->dbHost)
                ->setDumpBinaryPath(dirname($this->getMysqlDumpPath()));

            // Set password (can be empty for Laragon default)
            if (!empty($this->dbPass)) {
                $dumper->setPassword($this->dbPass);
            }

            // Include routines and triggers
            $dumper->addExtraOption('--routines')
                   ->addExtraOption('--triggers')
                   ->addExtraOption('--single-transaction')
                   ->addExtraOption('--quick');

            // Dump with or without compression
            // On Windows, gzip CLI is typically not available, so we dump to .sql
            // first, then compress using PHP's native gzencode()
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

            if ($useCompression && !$isWindows) {
                // Linux/Mac: use spatie's GzipCompressor (pipes through gzip CLI)
                $dumper->useCompressor(new \Spatie\DbDumper\Compressors\GzipCompressor());
                $dumper->dumpToFile($filepath);
            } else if ($useCompression && $isWindows) {
                // Windows: dump to .sql first, then compress with PHP gzencode
                $sqlFilepath = str_replace('.sql.gz', '.sql', $filepath);
                $dumper->dumpToFile($sqlFilepath);

                // Compress with PHP's native zlib
                if (function_exists('gzencode') && file_exists($sqlFilepath)) {
                    $sqlContent = file_get_contents($sqlFilepath);
                    $gzContent = gzencode($sqlContent, 9);
                    file_put_contents($filepath, $gzContent);
                    unlink($sqlFilepath); // Remove uncompressed file
                } else {
                    // gzencode not available, keep as .sql
                    $filepath = $sqlFilepath;
                    $filename = str_replace('.sql.gz', '.sql', $filename);
                }
            } else {
                // No compression requested
                $dumper->dumpToFile($filepath);
            }

            // Get file size and table count
            $fileSize = filesize($filepath);
            $tablesCount = $this->getTablesCount();

            // Update history record
            $this->updateBackupHistory($historyId, $fileSize, 'completed', $tablesCount);

            // Update last auto backup time if automatic
            if ($type === 'automatic') {
                $this->updateSetting('last_auto_backup', date('Y-m-d H:i:s'));
            }

            // Enforce retention policy
            $this->enforceRetention();

            return [
                'success' => true,
                'filename' => $filename,
                'file_size' => $fileSize,
                'tables_count' => $tablesCount,
                'message' => 'Backup created successfully'
            ];

        } catch (\Exception $e) {
            // Update history with failure
            $this->updateBackupHistory($historyId, 0, 'failed', 0, $e->getMessage());

            error_log("Backup failed: " . $e->getMessage());

            return [
                'success' => false,
                'filename' => $filename,
                'message' => 'Backup failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Restore database from a backup file
     * 
     * @param string $filename Backup filename in the backups directory
     * @return array Result with success status and message
     */
    public function restoreFromFile(string $filename): array
    {
        // H4: Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $filepath = $this->backupDir . DIRECTORY_SEPARATOR . $filename;

        // Verify the resolved path is within the backup directory
        $realBackupDir = realpath($this->backupDir);
        $realFilePath = realpath($filepath);
        if ($realFilePath === false || strpos($realFilePath, $realBackupDir) !== 0) {
            return ['success' => false, 'message' => 'Invalid backup file path'];
        }

        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Backup file not found'];
        }

        try {
            $isCompressed = substr($filename, -3) === '.gz';
            $sqlFile = $filepath;

            // If compressed, decompress first
            if ($isCompressed) {
                $sqlFile = $this->backupDir . DIRECTORY_SEPARATOR . 'temp_restore_' . time() . '.sql';
                $this->decompressGzip($filepath, $sqlFile);
            }

            // Validate the SQL file before restoring
            $validation = $this->validateBackupFile($sqlFile);
            if (!$validation['valid']) {
                if ($isCompressed && file_exists($sqlFile)) {
                    unlink($sqlFile);
                }
                return ['success' => false, 'message' => $validation['message']];
            }

            // Restore using mysql binary
            $mysqlPath = $this->getMysqlPath();
            $command = sprintf(
                '"%s" --host=%s --user=%s %s %s < "%s"',
                $mysqlPath,
                escapeshellarg($this->dbHost),
                escapeshellarg($this->dbUser),
                !empty($this->dbPass) ? '--password=' . escapeshellarg($this->dbPass) : '',
                escapeshellarg($this->dbName),
                $sqlFile
            );

            // Execute restore
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);

            // Clean up temp file
            if ($isCompressed && file_exists($sqlFile)) {
                unlink($sqlFile);
            }

            if ($returnCode !== 0) {
                $errorMsg = implode("\n", $output);
                error_log("Restore failed: " . $errorMsg);

                // Fallback: try PDO-based restore
                return $this->restoreViaPDO($isCompressed ? $filepath : $sqlFile, $isCompressed);
            }

            return [
                'success' => true,
                'message' => 'Database restored successfully from ' . $filename
            ];

        } catch (\Exception $e) {
            error_log("Restore error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
        }
    }

    /**
     * Restore database from an uploaded file
     * 
     * @param array $uploadedFile $_FILES array element
     * @return array Result with success status and message
     */
    public function restoreFromUpload(array $uploadedFile): array
    {
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds the maximum upload size',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum form size',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by a PHP extension',
            ];
            $msg = $errorMessages[$uploadedFile['error']] ?? 'Unknown upload error';
            return ['success' => false, 'message' => $msg];
        }

        $originalName = basename($uploadedFile['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Validate file extension
        $allowedExtensions = ['sql', 'gz'];
        if (!in_array($extension, $allowedExtensions)) {
            return ['success' => false, 'message' => 'Invalid file type. Only .sql and .sql.gz files are allowed'];
        }

        // For .gz files, also check the second extension
        if ($extension === 'gz') {
            $nameWithoutGz = pathinfo($originalName, PATHINFO_FILENAME);
            $secondExt = strtolower(pathinfo($nameWithoutGz, PATHINFO_EXTENSION));
            if ($secondExt !== 'sql') {
                return ['success' => false, 'message' => 'Invalid file type. Only .sql and .sql.gz files are allowed'];
            }
        }

        // Move to backups directory with a safe name
        $safeFilename = 'upload_restore_' . date('Y-m-d_H-i-s') . '.' . ($extension === 'gz' ? 'sql.gz' : 'sql');
        $destination = $this->backupDir . DIRECTORY_SEPARATOR . $safeFilename;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $destination)) {
            return ['success' => false, 'message' => 'Failed to save uploaded file'];
        }

        // Perform the restore
        $result = $this->restoreFromFile($safeFilename);

        // Clean up the uploaded file after restore
        if (file_exists($destination)) {
            unlink($destination);
        }

        return $result;
    }

    /**
     * Fallback PDO-based restore (if mysql binary fails)
     */
    private function restoreViaPDO(string $filepath, bool $isCompressed = false): array
    {
        try {
            // Read SQL content
            if ($isCompressed) {
                $sqlContent = '';
                $gz = gzopen($filepath, 'rb');
                if ($gz === false) {
                    return ['success' => false, 'message' => 'Could not open compressed backup file'];
                }
                while (!gzeof($gz)) {
                    $sqlContent .= gzread($gz, 1024 * 1024); // Read 1MB chunks
                }
                gzclose($gz);
            } else {
                $sqlContent = file_get_contents($filepath);
            }

            if (empty($sqlContent)) {
                return ['success' => false, 'message' => 'Backup file is empty'];
            }

            // Disable foreign key checks
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Split into individual statements
            $statements = $this->splitSqlStatements($sqlContent);
            $executed = 0;
            $errors = [];

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) continue;
                
                // Skip comments
                if (substr($statement, 0, 2) === '--' || substr($statement, 0, 2) === '/*') continue;

                try {
                    $this->pdo->exec($statement);
                    $executed++;
                } catch (\PDOException $e) {
                    $errors[] = $e->getMessage();
                    // Continue with other statements
                }
            }

            // Re-enable foreign key checks
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            if (!empty($errors) && $executed === 0) {
                return ['success' => false, 'message' => 'Restore failed. No statements executed. First error: ' . $errors[0]];
            }

            $message = "Database restored successfully. {$executed} statements executed.";
            if (!empty($errors)) {
                $message .= ' ' . count($errors) . ' statements had errors (non-critical).';
            }

            return ['success' => true, 'message' => $message];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'PDO restore failed: ' . $e->getMessage()];
        }
    }

    /**
     * Split SQL content into individual statements
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $currentStatement = '';
        $inString = false;
        $stringChar = '';
        $escaped = false;
        $delimiter = ';';

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if ($escaped) {
                $currentStatement .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $currentStatement .= $char;
                $escaped = true;
                continue;
            }

            if ($inString) {
                $currentStatement .= $char;
                if ($char === $stringChar) {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $currentStatement .= $char;
                continue;
            }

            // Check for DELIMITER command
            if (strtoupper(substr($sql, $i, 9)) === 'DELIMITER') {
                $rest = substr($sql, $i + 9);
                $rest = ltrim($rest);
                $newDelimiter = '';
                for ($j = 0; $j < strlen($rest); $j++) {
                    if ($rest[$j] === "\n" || $rest[$j] === "\r") break;
                    $newDelimiter .= $rest[$j];
                }
                $newDelimiter = trim($newDelimiter);
                if (!empty($newDelimiter)) {
                    $delimiter = $newDelimiter;
                    $i += 9 + $j;
                    continue;
                }
            }

            // Check for delimiter
            if (substr($sql, $i, strlen($delimiter)) === $delimiter) {
                $trimmed = trim($currentStatement);
                if (!empty($trimmed)) {
                    $statements[] = $trimmed;
                }
                $currentStatement = '';
                $i += strlen($delimiter) - 1;
                continue;
            }

            $currentStatement .= $char;
        }

        // Add last statement
        $trimmed = trim($currentStatement);
        if (!empty($trimmed)) {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    /**
     * Validate a backup SQL file
     */
    public function validateBackupFile(string $filepath): array
    {
        if (!file_exists($filepath)) {
            return ['valid' => false, 'message' => 'File not found'];
        }

        $fileSize = filesize($filepath);
        if ($fileSize === 0) {
            return ['valid' => false, 'message' => 'Backup file is empty'];
        }

        // Read first few KB to validate
        $handle = fopen($filepath, 'r');
        $header = fread($handle, 4096);
        fclose($handle);

        // Check if it looks like a MySQL dump
        if (strpos($header, 'MySQL') === false && 
            strpos($header, 'CREATE') === false && 
            strpos($header, 'INSERT') === false &&
            strpos($header, 'DROP') === false &&
            strpos($header, 'SET') === false) {
            return ['valid' => false, 'message' => 'File does not appear to be a valid MySQL backup'];
        }

        return ['valid' => true, 'message' => 'Valid backup file', 'file_size' => $fileSize];
    }

    /**
     * Get info about a backup file (for preview before restore)
     */
    public function getBackupInfo(string $filename): array
    {
        // H4: Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $filepath = $this->backupDir . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'File not found'];
        }

        $isCompressed = substr($filename, -3) === '.gz';
        $fileSize = filesize($filepath);

        // Read content to analyze
        if ($isCompressed) {
            $gz = gzopen($filepath, 'rb');
            $content = gzread($gz, 50000); // Read first 50KB
            gzclose($gz);
        } else {
            $handle = fopen($filepath, 'r');
            $content = fread($handle, 50000);
            fclose($handle);
        }

        // Extract table names
        preg_match_all('/CREATE TABLE[^`]*`([^`]+)`/i', $content, $matches);
        $tables = $matches[1] ?? [];

        // Extract database name from dump header
        preg_match('/Database: ([^\n]+)/', $content, $dbMatch);
        $database = $dbMatch[1] ?? 'Unknown';

        // Get creation date from filename
        preg_match('/(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})/', $filename, $dateMatch);
        $backupDate = isset($dateMatch[1]) ? str_replace('_', ' ', $dateMatch[1]) : 'Unknown';

        return [
            'success' => true,
            'filename' => $filename,
            'file_size' => $fileSize,
            'file_size_formatted' => $this->formatFileSize($fileSize),
            'compressed' => $isCompressed,
            'database' => trim($database),
            'tables' => $tables,
            'tables_count' => count($tables),
            'backup_date' => $backupDate
        ];
    }

    /**
     * Delete a backup file
     */
    public function deleteBackup(string $filename): array
    {
        $filepath = $this->backupDir . DIRECTORY_SEPARATOR . $filename;

        // Prevent path traversal
        $realPath = realpath($filepath);
        $realBackupDir = realpath($this->backupDir);
        if ($realPath === false || strpos($realPath, $realBackupDir) !== 0) {
            return ['success' => false, 'message' => 'Invalid file path'];
        }

        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Backup file not found'];
        }

        if (unlink($filepath)) {
            // Remove from history
            $stmt = $this->pdo->prepare("DELETE FROM backup_history WHERE filename = ?");
            $stmt->execute([$filename]);

            return ['success' => true, 'message' => 'Backup deleted successfully'];
        }

        return ['success' => false, 'message' => 'Failed to delete backup file'];
    }

    /**
     * Get list of all backup files
     */
    public function getBackupList(): array
    {
        $backups = [];
        $files = glob($this->backupDir . DIRECTORY_SEPARATOR . '*.{sql,sql.gz}', GLOB_BRACE);

        if ($files === false) return $backups;

        // Sort by modification time (newest first)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach ($files as $file) {
            $filename = basename($file);
            $fileSize = filesize($file);

            // Get history record if exists
            $stmt = $this->pdo->prepare("SELECT * FROM backup_history WHERE filename = ? LIMIT 1");
            $stmt->execute([$filename]);
            $history = $stmt->fetch();

            $backups[] = [
                'filename' => $filename,
                'file_size' => $fileSize,
                'file_size_formatted' => $this->formatFileSize($fileSize),
                'backup_type' => $history['backup_type'] ?? 'unknown',
                'status' => $history['status'] ?? 'completed',
                'tables_count' => $history['tables_count'] ?? 0,
                'created_by' => $history['created_by'] ?? null,
                'created_at' => $history ? $history['created_at'] : date('Y-m-d H:i:s', filemtime($file)),
                'compressed' => substr($filename, -3) === '.gz',
            ];
        }

        return $backups;
    }

    /**
     * Enforce backup retention policy (delete oldest backups beyond limit)
     */
    private function enforceRetention(): void
    {
        $retention = (int) $this->getSetting('auto_backup_retention', '10');
        if ($retention <= 0) return;

        // Only enforce retention on automatic backups
        $stmt = $this->pdo->prepare("
            SELECT filename FROM backup_history 
            WHERE backup_type = 'automatic' AND status = 'completed'
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $autoBackups = $stmt->fetchAll();

        if (count($autoBackups) > $retention) {
            $toDelete = array_slice($autoBackups, $retention);
            foreach ($toDelete as $backup) {
                $this->deleteBackup($backup['filename']);
            }
        }
    }

    /**
     * Check if auto backup should run now
     */
    public function shouldRunAutoBackup(): bool
    {
        $enabled = $this->getSetting('auto_backup_enabled', '0') === '1';
        if (!$enabled) return false;

        $frequency = $this->getSetting('auto_backup_frequency', 'daily');
        $scheduledTime = $this->getSetting('auto_backup_time', '02:00');
        $lastBackup = $this->getSetting('last_auto_backup');
        $dayOfWeek = (int) $this->getSetting('auto_backup_day_of_week', '1'); // 1=Monday
        $dayOfMonth = (int) $this->getSetting('auto_backup_day_of_month', '1');

        $now = new \DateTime();
        $currentTime = $now->format('H:i');

        // Check if we're within the scheduled time window (±30 minutes)
        $scheduledMinutes = $this->timeToMinutes($scheduledTime);
        $currentMinutes = $this->timeToMinutes($currentTime);
        if (abs($currentMinutes - $scheduledMinutes) > 30) {
            return false;
        }

        // Check if backup already ran today
        if ($lastBackup) {
            $lastBackupDate = new \DateTime($lastBackup);
            $diff = $now->diff($lastBackupDate);

            switch ($frequency) {
                case 'daily':
                    if ($diff->days < 1) return false;
                    break;
                case 'weekly':
                    if ($diff->days < 7) return false;
                    if ((int) $now->format('N') !== $dayOfWeek) return false;
                    break;
                case 'monthly':
                    if ($diff->days < 28) return false;
                    if ((int) $now->format('j') !== $dayOfMonth) return false;
                    break;
            }
        } else {
            // No previous backup, check day constraints for weekly/monthly
            switch ($frequency) {
                case 'weekly':
                    if ((int) $now->format('N') !== $dayOfWeek) return false;
                    break;
                case 'monthly':
                    if ((int) $now->format('j') !== $dayOfMonth) return false;
                    break;
            }
        }

        return true;
    }

    /**
     * Convert time string (HH:MM) to total minutes
     */
    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));
    }

    /**
     * Get number of tables in the database
     */
    public function getTablesCount(): int
    {
        $stmt = $this->pdo->query("SHOW TABLES");
        return $stmt->rowCount();
    }

    /**
     * Get list of tables with row counts
     */
    public function getTableInfo(): array
    {
        $tables = [];
        $stmt = $this->pdo->query("SHOW TABLE STATUS");
        while ($row = $stmt->fetch()) {
            $tables[] = [
                'name' => $row['Name'],
                'rows' => $row['Rows'],
                'size' => $row['Data_length'] + $row['Index_length'],
                'size_formatted' => $this->formatFileSize($row['Data_length'] + $row['Index_length']),
                'engine' => $row['Engine'],
            ];
        }
        return $tables;
    }

    /**
     * Get total database size
     */
    public function getDatabaseSize(): string
    {
        $stmt = $this->pdo->prepare("
            SELECT SUM(data_length + index_length) as total_size 
            FROM information_schema.tables 
            WHERE table_schema = ?
        ");
        $stmt->execute([$this->dbName]);
        $result = $stmt->fetch();
        return $this->formatFileSize($result['total_size'] ?? 0);
    }

    // =====================================================
    // SETTINGS HELPERS
    // =====================================================

    /**
     * Get a backup setting value
     */
    public function getSetting(string $key, ?string $default = null): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM backup_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        } catch (\PDOException $e) {
            return $default;
        }
    }

    /**
     * Update a backup setting
     */
    public function updateSetting(string $key, ?string $value, ?int $userId = null): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO backup_settings (setting_key, setting_value, updated_by) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?
            ");
            return $stmt->execute([$key, $value, $userId, $value, $userId]);
        } catch (\PDOException $e) {
            error_log("Failed to update setting: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all backup settings as associative array
     */
    public function getAllSettings(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM backup_settings");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Generate or get the cron token
     */
    public function getCronToken(): string
    {
        $token = $this->getSetting('backup_cron_token');
        if (empty($token)) {
            $token = bin2hex(random_bytes(32));
            $this->updateSetting('backup_cron_token', $token);
        }
        return $token;
    }

    // =====================================================
    // HISTORY HELPERS
    // =====================================================

    private function recordBackupHistory(string $filename, int $fileSize, string $type, string $status, int $tablesCount, ?string $notes, ?int $userId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO backup_history (filename, file_size, backup_type, status, tables_count, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$filename, $fileSize, $type, $status, $tablesCount, $notes, $userId]);
        return (int) $this->pdo->lastInsertId();
    }

    private function updateBackupHistory(int $id, int $fileSize, string $status, int $tablesCount, ?string $notes = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE backup_history SET file_size = ?, status = ?, tables_count = ?, notes = ? WHERE id = ?
        ");
        $stmt->execute([$fileSize, $status, $tablesCount, $notes, $id]);
    }

    /**
     * Get backup history records
     */
    public function getBackupHistory(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT bh.*, u.full_name as created_by_name
            FROM backup_history bh
            LEFT JOIN users u ON bh.created_by = u.id
            ORDER BY bh.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    // =====================================================
    // UTILITY HELPERS
    // =====================================================

    /**
     * Format file size to human-readable
     */
    public function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    /**
     * Decompress gzip file
     */
    private function decompressGzip(string $source, string $destination): void
    {
        $gz = gzopen($source, 'rb');
        $out = fopen($destination, 'wb');

        if ($gz === false || $out === false) {
            throw new \RuntimeException('Failed to open files for decompression');
        }

        while (!gzeof($gz)) {
            fwrite($out, gzread($gz, 1024 * 1024)); // 1MB chunks
        }

        gzclose($gz);
        fclose($out);
    }

    /**
     * Test if mysqldump is available
     */
    public function testMysqlDump(): array
    {
        $dumpPath = $this->getMysqlDumpPath();
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $command = '"' . $dumpPath . '" --version 2>&1';
        } else {
            $command = escapeshellarg($dumpPath) . ' --version 2>&1';
        }

        $output = [];
        $returnCode = 0;
        @exec($command, $output, $returnCode);

        $version = implode(' ', $output);
        $isAvailable = $returnCode === 0;

        return [
            'success' => $isAvailable,
            'available' => $isAvailable,
            'path' => $dumpPath,
            'version' => $version,
            'message' => $isAvailable ? 'mysqldump is available at: ' . $dumpPath : 'mysqldump not found at: ' . $dumpPath
        ];
    }
}
