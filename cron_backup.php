<?php
/**
 * Automatic Backup Cron Endpoint
 * 
 * This file should be called by a scheduled task (Windows Task Scheduler / Linux cron).
 * It checks if an automatic backup should run and creates one if needed.
 * 
 * Authentication: Requires a valid cron token passed as a GET parameter.
 * 
 * Usage:
 *   curl -s "http://localhost/your-app/cron_backup.php?token=YOUR_TOKEN"
 *   
 * Windows Task Scheduler:
 *   Program: curl
 *   Arguments: -s "http://localhost/your-app/cron_backup.php?token=YOUR_TOKEN"
 *
 * Linux Cron (daily at 2am):
 *   0 2 * * * curl -s "http://localhost/your-app/cron_backup.php?token=YOUR_TOKEN" > /dev/null 2>&1
 * 
 * @package TheBigFive Payroll System
 */

// Prevent session from being started for cron requests
ini_set('session.use_cookies', '0');

// Set JSON response header
header('Content-Type: application/json');

// Include dependencies
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/BackupManager.php';

// Validate token
$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Authentication token is required.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    $backupManager = new BackupManager();
    
    // Verify token
    $storedToken = $backupManager->getCronToken();
    
    if (empty($storedToken) || !hash_equals($storedToken, $token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid authentication token.',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Check if auto backup is enabled
    $settings = $backupManager->getAllSettings();
    $autoEnabled = ($settings['auto_backup_enabled'] ?? '0') === '1';
    
    if (!$autoEnabled) {
        echo json_encode([
            'success' => true,
            'message' => 'Automatic backups are disabled. No action taken.',
            'auto_backup_enabled' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Check if it's time to run a backup
    if (!$backupManager->shouldRunAutoBackup()) {
        echo json_encode([
            'success' => true,
            'message' => 'Not yet time for the next automatic backup.',
            'auto_backup_enabled' => true,
            'frequency' => $settings['auto_backup_frequency'] ?? 'daily',
            'scheduled_time' => $settings['auto_backup_time'] ?? '02:00',
            'last_backup' => $settings['last_auto_backup'] ?? 'never',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Perform the backup
    $result = $backupManager->createBackup('automatic', null);
    
    if ($result['success']) {
        // Update last auto backup time
        $backupManager->updateSetting('last_auto_backup', date('Y-m-d H:i:s'));
        
        // Log the automatic backup
        try {
            require_once __DIR__ . '/config/account_logs_helper.php';
            logAccountActivity(
                0, // System user
                'SYSTEM',
                'Automatic database backup created: ' . $result['filename'],
                'other',
                'Triggered by scheduled task. Size: ' . $backupManager->formatFileSize($result['file_size']),
                getDBConnection()
            );
        } catch (Exception $logError) {
            // Don't fail the backup if logging fails
            error_log("Cron backup: Failed to log activity: " . $logError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Automatic backup completed successfully.',
            'filename' => $result['filename'],
            'file_size' => $result['file_size'],
            'file_size_formatted' => $backupManager->formatFileSize($result['file_size']),
            'tables_count' => $result['tables_count'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Backup failed: ' . ($result['message'] ?? 'Unknown error'),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Cron backup error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
