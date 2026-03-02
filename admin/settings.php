<?php
/**
 * System Settings - Backup & Restore
 * Admin-only page for managing database backups
 * 
 * @package TheBigFive Payroll System
 */

// Set page title
$page_title = 'Settings';

// Include header
require_once 'include/header.php';

// Include database connection
require_once '../config/database.php';

// Include sidebar
require_once 'include/sidebar.php';

// Load BackupManager to get current settings and status
require_once '../config/BackupManager.php';

$backupManager = null;
$settings = [];
$mysqldumpAvailable = false;
$dbSize = '0 B';
$tablesCount = 0;
$backups = [];

try {
    $backupManager = new BackupManager();
    $settings = $backupManager->getAllSettings();
    $testResult = $backupManager->testMysqlDump();
    $mysqldumpAvailable = $testResult['success'];
    $dbSize = $backupManager->getDatabaseSize();
    $tablesCount = $backupManager->getTablesCount();
    $backups = $backupManager->getBackupList();
} catch (\Throwable $e) {
    $error = $e->getMessage();
}
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-header-left">
                    <div class="page-title-row">
                        <div class="page-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="page-title-text">
                            <h1>System Settings</h1>
                            <div class="page-breadcrumb">
                                <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                                <span class="page-breadcrumb-separator">/</span>
                                <span>Settings</span>
                                <span class="page-breadcrumb-separator">/</span>
                                <span>Backup & Restore</span>
                            </div>
                        </div>
                    </div>
                    <p class="page-subtitle">Manage database backups, restore points, and automatic backup schedules</p>
                </div>
            </div>
        </div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger-modern">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Database Overview Stats -->
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-details">
                    <p class="stat-label">Database Size</p>
                    <p class="stat-value" id="dbSize"><?php echo htmlspecialchars($dbSize); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-info">
                <div class="stat-icon">
                    <i class="fas fa-table"></i>
                </div>
                <div class="stat-details">
                    <p class="stat-label">Total Tables</p>
                    <p class="stat-value" id="tablesCount"><?php echo (int)$tablesCount; ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <i class="fas fa-archive"></i>
                </div>
                <div class="stat-details">
                    <p class="stat-label">Backup Files</p>
                    <p class="stat-value" id="backupCount"><?php echo count($backups); ?></p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $mysqldumpAvailable ? 'stat-success' : 'stat-warning'; ?>">
                <div class="stat-icon">
                    <i class="fas <?php echo $mysqldumpAvailable ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                </div>
                <div class="stat-details">
                    <p class="stat-label">mysqldump Status</p>
                    <p class="stat-value" style="font-size: 16px;"><?php echo $mysqldumpAvailable ? 'Available' : 'Not Found'; ?></p>
                </div>
            </div>
        </div>

        <!-- Settings Tabs -->
        <div class="settings-tabs">
            <button class="tab-btn active" data-tab="backup-settings" onclick="switchTab('backup-settings')">
                <i class="fas fa-cog"></i> Backup Settings
            </button>
            <button class="tab-btn" data-tab="manual-backup" onclick="switchTab('manual-backup')">
                <i class="fas fa-download"></i> Manual Backup
            </button>
            <button class="tab-btn" data-tab="backup-history" onclick="switchTab('backup-history')">
                <i class="fas fa-history"></i> Backup History
            </button>
            <button class="tab-btn" data-tab="restore" onclick="switchTab('restore')">
                <i class="fas fa-upload"></i> Restore
            </button>
        </div>

        <!-- ============================================= -->
        <!-- TAB 1: BACKUP SETTINGS                        -->
        <!-- ============================================= -->
        <div class="tab-content active" id="tab-backup-settings">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-title">
                        <i class="fas fa-sliders-h"></i>
                        <h3>Automatic Backup Configuration</h3>
                    </div>
                    <p class="settings-card-desc">Configure automatic database backups to run on a schedule</p>
                </div>
                <div class="settings-card-body">
                    <form id="backupSettingsForm" onsubmit="return saveSettings(event)">
                        <!-- Auto Backup Toggle -->
                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Enable Automatic Backups</h4>
                                <p>When enabled, the system will automatically create database backups based on your schedule.</p>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="auto_backup_enabled" id="autoBackupEnabled" 
                                           value="1" <?php echo ($settings['auto_backup_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Frequency -->
                        <div class="setting-row auto-setting" id="frequencyRow">
                            <div class="setting-info">
                                <h4>Backup Frequency</h4>
                                <p>How often should automatic backups be created?</p>
                            </div>
                            <div class="setting-control">
                                <select name="auto_backup_frequency" id="backupFrequency" class="form-input-modern">
                                    <option value="daily" <?php echo ($settings['auto_backup_frequency'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo ($settings['auto_backup_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo ($settings['auto_backup_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                        </div>

                        <!-- Day of Week (for weekly) -->
                        <div class="setting-row auto-setting" id="dayOfWeekRow" style="display: none;">
                            <div class="setting-info">
                                <h4>Day of Week</h4>
                                <p>Which day should the weekly backup run?</p>
                            </div>
                            <div class="setting-control">
                                <select name="auto_backup_day_of_week" id="dayOfWeek" class="form-input-modern">
                                    <option value="1" <?php echo ($settings['auto_backup_day_of_week'] ?? '1') === '1' ? 'selected' : ''; ?>>Monday</option>
                                    <option value="2" <?php echo ($settings['auto_backup_day_of_week'] ?? '') === '2' ? 'selected' : ''; ?>>Tuesday</option>
                                    <option value="3" <?php echo ($settings['auto_backup_day_of_week'] ?? '') === '3' ? 'selected' : ''; ?>>Wednesday</option>
                                    <option value="4" <?php echo ($settings['auto_backup_day_of_week'] ?? '') === '4' ? 'selected' : ''; ?>>Thursday</option>
                                    <option value="5" <?php echo ($settings['auto_backup_day_of_week'] ?? '') === '5' ? 'selected' : ''; ?>>Friday</option>
                                    <option value="6" <?php echo ($settings['auto_backup_day_of_week'] ?? '') === '6' ? 'selected' : ''; ?>>Saturday</option>
                                    <option value="0" <?php echo ($settings['auto_backup_day_of_week'] ?? '') === '0' ? 'selected' : ''; ?>>Sunday</option>
                                </select>
                            </div>
                        </div>

                        <!-- Day of Month (for monthly) -->
                        <div class="setting-row auto-setting" id="dayOfMonthRow" style="display: none;">
                            <div class="setting-info">
                                <h4>Day of Month</h4>
                                <p>Which day of the month should the backup run?</p>
                            </div>
                            <div class="setting-control">
                                <select name="auto_backup_day_of_month" id="dayOfMonth" class="form-input-modern">
                                    <?php for ($d = 1; $d <= 28; $d++): ?>
                                    <option value="<?php echo $d; ?>" <?php echo ($settings['auto_backup_day_of_month'] ?? '1') === (string)$d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Backup Time -->
                        <div class="setting-row auto-setting">
                            <div class="setting-info">
                                <h4>Backup Time</h4>
                                <p>What time should the backup run? (Recommended: off-peak hours)</p>
                            </div>
                            <div class="setting-control">
                                <input type="time" name="auto_backup_time" id="backupTime" class="form-input-modern"
                                       value="<?php echo htmlspecialchars($settings['auto_backup_time'] ?? '02:00'); ?>">
                            </div>
                        </div>

                        <!-- Compression -->
                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Enable Compression</h4>
                                <p>Compress backup files using gzip to save disk space (recommended)</p>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="backup_compression" id="backupCompression" 
                                           value="1" <?php echo ($settings['backup_compression'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Retention -->
                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Backup Retention</h4>
                                <p>Maximum number of automatic backups to keep. Oldest backups will be deleted when this limit is reached.</p>
                            </div>
                            <div class="setting-control">
                                <select name="auto_backup_retention" id="backupRetention" class="form-input-modern">
                                    <option value="5" <?php echo ($settings['auto_backup_retention'] ?? '10') === '5' ? 'selected' : ''; ?>>5 backups</option>
                                    <option value="10" <?php echo ($settings['auto_backup_retention'] ?? '10') === '10' ? 'selected' : ''; ?>>10 backups</option>
                                    <option value="15" <?php echo ($settings['auto_backup_retention'] ?? '') === '15' ? 'selected' : ''; ?>>15 backups</option>
                                    <option value="20" <?php echo ($settings['auto_backup_retention'] ?? '') === '20' ? 'selected' : ''; ?>>20 backups</option>
                                    <option value="30" <?php echo ($settings['auto_backup_retention'] ?? '') === '30' ? 'selected' : ''; ?>>30 backups</option>
                                    <option value="0" <?php echo ($settings['auto_backup_retention'] ?? '') === '0' ? 'selected' : ''; ?>>Unlimited</option>
                                </select>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="settings-actions">
                            <button type="submit" class="btn-primary-modern" id="saveSettingsBtn">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cron Setup Card -->
            <div class="settings-card" style="margin-top: 1.5rem;">
                <div class="settings-card-header">
                    <div class="settings-card-title">
                        <i class="fas fa-clock"></i>
                        <h3>Scheduled Task Setup</h3>
                    </div>
                    <p class="settings-card-desc">Configure your server or Windows Task Scheduler to trigger automatic backups</p>
                </div>
                <div class="settings-card-body">
                    <div class="cron-info">
                        <div class="cron-url-section">
                            <label><i class="fas fa-link"></i> Cron / Task Scheduler URL</label>
                            <div class="cron-url-row">
                                <input type="text" id="cronUrl" class="form-input-modern cron-url-input" readonly 
                                       value="Loading..." placeholder="Cron URL will appear here">
                                <button type="button" class="btn-secondary-modern" onclick="copyCronUrl()" title="Copy URL">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button type="button" class="btn-secondary-modern" onclick="regenerateToken()" title="Regenerate Token">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="cron-instructions">
                            <h4><i class="fas fa-info-circle"></i> Setup Instructions</h4>
                            
                            <div class="instruction-block">
                                <h5><i class="fab fa-windows"></i> Windows Task Scheduler (Laragon)</h5>
                                <ol>
                                    <li>Open <strong>Task Scheduler</strong> (search "Task Scheduler" in Start)</li>
                                    <li>Click <strong>Create Basic Task</strong></li>
                                    <li>Set the trigger to match your backup frequency</li>
                                    <li>For the action, select <strong>Start a Program</strong></li>
                                    <li>Program: <code>curl</code></li>
                                    <li>Arguments: <code id="cronCurlArgs">-s "YOUR_CRON_URL"</code></li>
                                </ol>
                            </div>
                            
                            <div class="instruction-block">
                                <h5><i class="fab fa-linux"></i> Linux Cron Job</h5>
                                <div class="code-block">
                                    <code id="cronCommand"># Run daily at 2:00 AM
0 2 * * * curl -s "YOUR_CRON_URL" > /dev/null 2>&1</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================= -->
        <!-- TAB 2: MANUAL BACKUP                          -->
        <!-- ============================================= -->
        <div class="tab-content" id="tab-manual-backup">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-title">
                        <i class="fas fa-download"></i>
                        <h3>Create Manual Backup</h3>
                    </div>
                    <p class="settings-card-desc">Create an instant backup of your entire database</p>
                </div>
                <div class="settings-card-body">
                    <div class="manual-backup-section">
                        <div class="backup-info-grid">
                            <div class="backup-info-item">
                                <i class="fas fa-database"></i>
                                <div>
                                    <strong>Database</strong>
                                    <span><?php echo htmlspecialchars(DB_NAME); ?></span>
                                </div>
                            </div>
                            <div class="backup-info-item">
                                <i class="fas fa-table"></i>
                                <div>
                                    <strong>Tables</strong>
                                    <span id="manualTablesCount"><?php echo (int)$tablesCount; ?> tables</span>
                                </div>
                            </div>
                            <div class="backup-info-item">
                                <i class="fas fa-hdd"></i>
                                <div>
                                    <strong>Estimated Size</strong>
                                    <span id="manualDbSize"><?php echo htmlspecialchars($dbSize); ?></span>
                                </div>
                            </div>
                            <div class="backup-info-item">
                                <i class="fas fa-file-archive"></i>
                                <div>
                                    <strong>Compression</strong>
                                    <span id="manualCompression"><?php echo ($settings['backup_compression'] ?? '1') === '1' ? 'Enabled (gzip)' : 'Disabled'; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="backup-actions-center">
                            <button type="button" class="btn-backup-now" id="backupNowBtn" onclick="createBackup()">
                                <i class="fas fa-database"></i>
                                <span>Create Backup Now</span>
                            </button>
                            <p class="backup-hint">This will create a complete backup of all database tables including stored routines and triggers.</p>
                        </div>

                        <!-- Backup Progress -->
                        <div class="backup-progress" id="backupProgress" style="display: none;">
                            <div class="progress-bar-container">
                                <div class="progress-bar" id="progressBar">
                                    <div class="progress-bar-fill"></div>
                                </div>
                                <span class="progress-text" id="progressText">Creating backup...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$mysqldumpAvailable): ?>
            <div class="alert alert-warning-modern" style="margin-top: 1.5rem;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>mysqldump not found</strong>
                    <p style="margin: 0.25rem 0 0;">The system could not locate the mysqldump binary. Backups will use PDO fallback method which may be slower for large databases. 
                    If you're using Laragon, ensure MySQL bin directory is in your system PATH.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============================================= -->
        <!-- TAB 3: BACKUP HISTORY                         -->
        <!-- ============================================= -->
        <div class="tab-content" id="tab-backup-history">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-title">
                        <i class="fas fa-history"></i>
                        <h3>Backup History</h3>
                    </div>
                    <p class="settings-card-desc">View and manage all existing database backups</p>
                </div>
                <div class="settings-card-body">
                    <div class="backup-table-container">
                        <table class="backup-table" id="backupHistoryTable">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-file"></i> Filename</th>
                                    <th><i class="fas fa-tag"></i> Type</th>
                                    <th><i class="fas fa-hdd"></i> Size</th>
                                    <th><i class="fas fa-table"></i> Tables</th>
                                    <th><i class="fas fa-calendar"></i> Created</th>
                                    <th><i class="fas fa-check-circle"></i> Status</th>
                                    <th><i class="fas fa-cogs"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody id="backupListBody">
                                <tr class="loading-row">
                                    <td colspan="7">
                                        <div class="table-loading">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            <span>Loading backups...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="empty-state" id="noBackupsState" style="display: none;">
                        <i class="fas fa-archive"></i>
                        <h3>No Backups Found</h3>
                        <p>Create your first backup using the Manual Backup tab</p>
                        <button class="btn-primary-modern" style="margin-top: 1rem;" onclick="switchTab('manual-backup')">
                            <i class="fas fa-plus"></i> Create Backup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================= -->
        <!-- TAB 4: RESTORE                                -->
        <!-- ============================================= -->
        <div class="tab-content" id="tab-restore">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-title">
                        <i class="fas fa-upload"></i>
                        <h3>Restore Database</h3>
                    </div>
                    <p class="settings-card-desc">Restore your database from a backup file</p>
                </div>
                <div class="settings-card-body">
                    <!-- Warning -->
                    <div class="restore-warning">
                        <div class="warning-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="warning-text">
                            <h4>Warning: Database Restore</h4>
                            <p>Restoring a database will <strong>overwrite all current data</strong> with the data from the backup file. 
                               A safety backup will be automatically created before the restore begins.</p>
                        </div>
                    </div>

                    <!-- Option 1: Upload File -->
                    <div class="restore-section">
                        <h4><i class="fas fa-file-upload"></i> Upload Backup File</h4>
                        <p>Upload a .sql or .sql.gz backup file from your computer</p>
                        
                        <div class="upload-area" id="uploadArea">
                            <input type="file" id="restoreFileInput" accept=".sql,.gz" style="display: none;" onchange="handleFileSelect(this)">
                            <div class="upload-placeholder" onclick="document.getElementById('restoreFileInput').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h4>Click to browse or drag & drop</h4>
                                <p>Accepted formats: .sql, .sql.gz</p>
                            </div>
                            <div class="upload-preview" id="uploadPreview" style="display: none;">
                                <div class="file-info">
                                    <i class="fas fa-file-code"></i>
                                    <div>
                                        <strong id="uploadFileName">filename.sql</strong>
                                        <span id="uploadFileSize">0 KB</span>
                                    </div>
                                </div>
                                <button type="button" class="btn-remove-file" onclick="clearFileUpload()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <button type="button" class="btn-danger-modern" id="restoreUploadBtn" onclick="restoreFromUpload()" disabled>
                            <i class="fas fa-upload"></i> Restore from Uploaded File
                        </button>
                    </div>

                    <!-- Divider -->
                    <div class="restore-divider">
                        <span>OR</span>
                    </div>

                    <!-- Option 2: Restore from existing backup -->
                    <div class="restore-section">
                        <h4><i class="fas fa-history"></i> Restore from Existing Backup</h4>
                        <p>Select a previously created backup to restore</p>
                        
                        <select id="restoreBackupSelect" class="form-input-modern" style="max-width: 500px;">
                            <option value="">-- Select a backup file --</option>
                        </select>

                        <div class="backup-preview-card" id="backupPreviewCard" style="display: none;">
                            <div class="preview-grid">
                                <div class="preview-item">
                                    <span class="preview-label">File</span>
                                    <span class="preview-value" id="previewFilename">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">Size</span>
                                    <span class="preview-value" id="previewSize">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">Created</span>
                                    <span class="preview-value" id="previewDate">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">Tables</span>
                                    <span class="preview-value" id="previewTables">-</span>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn-danger-modern" id="restoreExistingBtn" onclick="restoreFromExisting()" disabled>
                            <i class="fas fa-undo"></i> Restore Selected Backup
                        </button>
                    </div>
                </div>
            </div>

            <!-- Restore Progress -->
            <div class="settings-card" id="restoreProgressCard" style="display: none; margin-top: 1.5rem;">
                <div class="settings-card-body">
                    <div class="restore-progress-content">
                        <div class="progress-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <h3 id="restoreProgressTitle">Restoring Database...</h3>
                        <p id="restoreProgressDesc">Please wait while the database is being restored. Do not close this page.</p>
                        <div class="progress-bar-container" style="margin-top: 1rem;">
                            <div class="progress-bar">
                                <div class="progress-bar-fill restoring"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="confirm-modal-overlay" id="confirmModal" style="display: none;">
    <div class="confirm-modal">
        <div class="confirm-modal-header">
            <div class="confirm-modal-icon" id="confirmIcon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 id="confirmTitle">Confirm Action</h2>
        </div>
        <div class="confirm-modal-body">
            <p id="confirmMessage">Are you sure you want to proceed?</p>
        </div>
        <div class="confirm-modal-footer">
            <button onclick="closeConfirmModal()" class="btn-secondary-modern">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button id="confirmActionBtn" class="btn-danger-modern" onclick="">
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

<?php require_once 'include/footer.php'; ?>

<style>
/* ============================================= */
/* SETTINGS PAGE STYLES                          */
/* ============================================= */

.content-wrapper {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border-left: 4px solid;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-card.stat-primary { border-left-color: #3b82f6; }
.stat-card.stat-success { border-left-color: #10b981; }
.stat-card.stat-info { border-left-color: #06b6d4; }
.stat-card.stat-warning { border-left-color: #f59e0b; }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-primary .stat-icon { background: #eff6ff; color: #3b82f6; }
.stat-success .stat-icon { background: #d1fae5; color: #10b981; }
.stat-info .stat-icon { background: #cffafe; color: #06b6d4; }
.stat-warning .stat-icon { background: #fef3c7; color: #f59e0b; }

.stat-details { flex: 1; }
.stat-label { font-size: 13px; color: #64748b; margin: 0 0 0.25rem 0; font-weight: 500; }
.stat-value { font-size: 24px; font-weight: 700; margin: 0; color: #1e293b; }

/* Alert Styles */
.alert-danger-modern,
.alert-warning-modern,
.alert-success-modern {
    border-radius: 8px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.alert-danger-modern {
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-left: 4px solid #ef4444;
    color: #991b1b;
}

.alert-warning-modern {
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-left: 4px solid #f59e0b;
    color: #92400e;
}

.alert-success-modern {
    background: #d1fae5;
    border: 1px solid #a7f3d0;
    border-left: 4px solid #10b981;
    color: #065f46;
}

.alert-danger-modern i,
.alert-warning-modern i,
.alert-success-modern i {
    font-size: 20px;
    margin-top: 2px;
}

/* Tabs */
.settings-tabs {
    display: flex;
    gap: 0;
    background: white;
    border-radius: 12px 12px 0 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
    margin-bottom: 0;
}

.tab-btn {
    padding: 1rem 1.5rem;
    border: none;
    background: none;
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.tab-btn:hover {
    color: #3b82f6;
    background: #f8fafc;
}

.tab-btn.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
    background: #eff6ff;
}

.tab-btn i {
    font-size: 16px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Settings Card */
.settings-card {
    background: white;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.settings-card + .settings-card,
.tab-content .settings-card:not(:first-child) {
    border-radius: 12px;
    margin-top: 1.5rem;
}

.settings-card-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
}

.settings-card-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.settings-card-title i {
    font-size: 20px;
    color: #3b82f6;
}

.settings-card-title h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.settings-card-desc {
    color: #64748b;
    font-size: 14px;
    margin: 0.5rem 0 0 2.25rem;
}

.settings-card-body {
    padding: 1.5rem;
}

/* Setting Rows */
.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.setting-row:last-of-type {
    border-bottom: none;
}

.setting-info {
    flex: 1;
    padding-right: 2rem;
}

.setting-info h4 {
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 0.25rem 0;
}

.setting-info p {
    font-size: 13px;
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}

.setting-control {
    flex-shrink: 0;
    min-width: 180px;
}

.setting-control select,
.setting-control input[type="time"] {
    width: 100%;
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: 0.3s;
    border-radius: 28px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

/* Form Input */
.form-input-modern {
    padding: 0.625rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
    background: white;
    color: #1e293b;
}

.form-input-modern:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Settings Actions */
.settings-actions {
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 0.5rem;
}

/* Buttons */
.btn-primary-modern,
.btn-secondary-modern,
.btn-danger-modern,
.btn-success-modern {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.btn-primary-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.btn-secondary-modern {
    background: #f1f5f9;
    color: #475569;
}

.btn-secondary-modern:hover {
    background: #e2e8f0;
}

.btn-danger-modern {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.btn-danger-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.btn-danger-modern:disabled,
.btn-primary-modern:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-success-modern {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-success-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

/* Cron Section */
.cron-url-section {
    margin-bottom: 1.5rem;
}

.cron-url-section label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.5rem;
}

.cron-url-section label i {
    color: #3b82f6;
    margin-right: 0.25rem;
}

.cron-url-row {
    display: flex;
    gap: 0.5rem;
}

.cron-url-input {
    flex: 1;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 13px !important;
    background: #f8fafc !important;
}

.cron-instructions {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1.5rem;
    border: 1px solid #e2e8f0;
}

.cron-instructions > h4 {
    margin: 0 0 1rem 0;
    font-size: 15px;
    color: #1e293b;
}

.cron-instructions > h4 i {
    color: #3b82f6;
    margin-right: 0.5rem;
}

.instruction-block {
    margin-bottom: 1.5rem;
}

.instruction-block:last-child {
    margin-bottom: 0;
}

.instruction-block h5 {
    font-size: 14px;
    color: #475569;
    margin: 0 0 0.75rem 0;
}

.instruction-block h5 i {
    margin-right: 0.5rem;
    width: 18px;
    text-align: center;
}

.instruction-block ol {
    margin: 0;
    padding-left: 1.5rem;
    line-height: 1.8;
    font-size: 13px;
    color: #475569;
}

.instruction-block code {
    background: #e2e8f0;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12px;
}

.code-block {
    background: #1e293b;
    border-radius: 8px;
    padding: 1rem 1.25rem;
    overflow-x: auto;
}

.code-block code {
    color: #a5f3fc;
    background: none;
    padding: 0;
    font-size: 13px;
    white-space: pre;
}

/* Manual Backup Section */
.manual-backup-section {
    text-align: center;
}

.backup-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.backup-info-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    text-align: left;
}

.backup-info-item i {
    font-size: 24px;
    color: #3b82f6;
    width: 32px;
    text-align: center;
}

.backup-info-item strong {
    display: block;
    font-size: 13px;
    color: #475569;
    margin-bottom: 0.125rem;
}

.backup-info-item span {
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}

.backup-actions-center {
    padding: 2rem 0;
}

.btn-backup-now {
    padding: 1rem 2.5rem;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.35);
}

.btn-backup-now:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.45);
}

.btn-backup-now:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-backup-now i {
    font-size: 20px;
}

.backup-hint {
    color: #94a3b8;
    font-size: 13px;
    margin-top: 1rem;
}

/* Progress Bar */
.backup-progress {
    margin-top: 2rem;
}

.progress-bar-container {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #2563eb, #3b82f6);
    background-size: 200% 100%;
    animation: progressAnimation 1.5s ease-in-out infinite;
    border-radius: 4px;
    width: 100%;
}

.progress-bar-fill.restoring {
    background: linear-gradient(90deg, #f59e0b, #d97706, #f59e0b);
    background-size: 200% 100%;
    animation: progressAnimation 1.5s ease-in-out infinite;
}

@keyframes progressAnimation {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.progress-text {
    font-size: 14px;
    font-weight: 600;
    color: #3b82f6;
    white-space: nowrap;
}

/* Backup Table */
.backup-table-container {
    overflow-x: auto;
}

.backup-table {
    width: 100%;
    border-collapse: collapse;
}

.backup-table th {
    background: #f8fafc;
    padding: 0.875rem 1rem;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}

.backup-table th i {
    color: #94a3b8;
    margin-right: 0.375rem;
}

.backup-table td {
    padding: 0.875rem 1rem;
    font-size: 14px;
    color: #1e293b;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.backup-table tr:hover {
    background: #f8fafc;
}

.backup-table .filename-cell {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 13px;
    word-break: break-all;
    max-width: 280px;
}

.badge-type {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-type.manual {
    background: #eff6ff;
    color: #3b82f6;
}

.badge-type.automatic {
    background: #d1fae5;
    color: #059669;
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-status.success {
    background: #d1fae5;
    color: #059669;
}

.badge-status.failed {
    background: #fee2e2;
    color: #dc2626;
}

.badge-status.pending {
    background: #fef3c7;
    color: #d97706;
}

.action-btns {
    display: flex;
    gap: 0.375rem;
}

.btn-action {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-action.btn-download {
    background: #eff6ff;
    color: #3b82f6;
}

.btn-action.btn-download:hover {
    background: #3b82f6;
    color: white;
}

.btn-action.btn-restore-action {
    background: #fef3c7;
    color: #d97706;
}

.btn-action.btn-restore-action:hover {
    background: #f59e0b;
    color: white;
}

.btn-action.btn-delete-action {
    background: #fee2e2;
    color: #dc2626;
}

.btn-action.btn-delete-action:hover {
    background: #ef4444;
    color: white;
}

.table-loading {
    text-align: center;
    padding: 2rem;
    color: #64748b;
}

.table-loading i {
    margin-right: 0.5rem;
    font-size: 18px;
}

/* Restore Section */
.restore-warning {
    display: flex;
    gap: 1rem;
    padding: 1.25rem;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-radius: 12px;
    margin-bottom: 2rem;
    border: 1px solid #fbbf24;
}

.warning-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(245, 158, 11, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.warning-icon i {
    font-size: 24px;
    color: #d97706;
}

.warning-text h4 {
    font-size: 16px;
    font-weight: 700;
    color: #92400e;
    margin: 0 0 0.375rem 0;
}

.warning-text p {
    font-size: 14px;
    color: #a16207;
    margin: 0;
    line-height: 1.6;
}

.restore-section {
    margin-bottom: 2rem;
}

.restore-section h4 {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 0.375rem 0;
}

.restore-section h4 i {
    color: #3b82f6;
    margin-right: 0.5rem;
}

.restore-section > p {
    color: #64748b;
    font-size: 14px;
    margin: 0 0 1rem 0;
}

.restore-section select {
    margin-bottom: 1rem;
}

.restore-section .btn-danger-modern {
    margin-top: 0.5rem;
}

/* Upload Area */
.upload-area {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s;
    margin-bottom: 1rem;
    background: #fafbfc;
}

.upload-area:hover,
.upload-area.drag-over {
    border-color: #3b82f6;
    background: #eff6ff;
}

.upload-placeholder {
    cursor: pointer;
}

.upload-placeholder i {
    font-size: 48px;
    color: #94a3b8;
    margin-bottom: 0.75rem;
}

.upload-area:hover .upload-placeholder i {
    color: #3b82f6;
}

.upload-placeholder h4 {
    font-size: 16px;
    color: #475569;
    margin: 0 0 0.25rem 0;
}

.upload-placeholder p {
    font-size: 13px;
    color: #94a3b8;
    margin: 0;
}

.upload-preview {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.file-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.file-info i {
    font-size: 32px;
    color: #3b82f6;
}

.file-info strong {
    display: block;
    color: #1e293b;
    font-size: 14px;
}

.file-info span {
    font-size: 12px;
    color: #94a3b8;
}

.btn-remove-file {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: #fee2e2;
    color: #dc2626;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-remove-file:hover {
    background: #dc2626;
    color: white;
}

/* Restore Divider */
.restore-divider {
    text-align: center;
    position: relative;
    margin: 2rem 0;
}

.restore-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e2e8f0;
}

.restore-divider span {
    position: relative;
    background: white;
    padding: 0 1rem;
    color: #94a3b8;
    font-size: 14px;
    font-weight: 600;
}

/* Backup Preview Card */
.backup-preview-card {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    margin-bottom: 1rem;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.75rem;
}

.preview-item {
    display: flex;
    flex-direction: column;
}

.preview-label {
    font-size: 12px;
    color: #94a3b8;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
}

.preview-value {
    font-size: 14px;
    color: #1e293b;
    font-weight: 500;
}

/* Restore Progress */
.restore-progress-content {
    text-align: center;
    padding: 2rem;
}

.progress-spinner {
    font-size: 48px;
    color: #f59e0b;
    margin-bottom: 1rem;
}

.restore-progress-content h3 {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.5rem 0;
}

.restore-progress-content p {
    color: #64748b;
    font-size: 14px;
    margin: 0;
}

/* Confirmation Modal */
.confirm-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    backdrop-filter: blur(4px);
}

.confirm-modal {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    max-width: 440px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    animation: modalAppear 0.3s ease;
}

@keyframes modalAppear {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

.confirm-modal-header {
    text-align: center;
    margin-bottom: 1rem;
}

.confirm-modal-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #fef3c7;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
}

.confirm-modal-icon i {
    font-size: 28px;
    color: #f59e0b;
}

.confirm-modal-icon.danger {
    background: #fee2e2;
}

.confirm-modal-icon.danger i {
    color: #ef4444;
}

.confirm-modal-header h2 {
    font-size: 20px;
    color: #1e293b;
    margin: 0;
}

.confirm-modal-body {
    text-align: center;
    margin-bottom: 1.5rem;
}

.confirm-modal-body p {
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
}

.confirm-modal-footer {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
}

.confirm-modal-footer button {
    flex: 1;
    justify-content: center;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #64748b;
}

.empty-state i {
    font-size: 64px;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 20px;
    font-weight: 600;
    color: #475569;
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
    .content-wrapper {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-value {
        font-size: 20px;
    }
    
    .settings-tabs {
        overflow-x: auto;
    }
    
    .tab-btn {
        padding: 0.75rem 1rem;
        font-size: 13px;
    }
    
    .setting-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .setting-info {
        padding-right: 0;
    }
    
    .setting-control {
        width: 100%;
    }
    
    .cron-url-row {
        flex-direction: column;
    }
    
    .backup-info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .confirm-modal-footer {
        flex-direction: column;
    }
    
    .action-btns {
        flex-wrap: wrap;
    }
    
    .restore-warning {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .backup-info-grid {
        grid-template-columns: 1fr;
    }
    
    .tab-btn span {
        display: none;
    }
    
    .tab-btn i {
        font-size: 18px;
    }
}
</style>

<script>
// =============================================
// SETTINGS PAGE JAVASCRIPT
// =============================================

// Global variable to track selected file
let selectedRestoreFile = null;

// =============================================
// TAB SWITCHING
// =============================================
function switchTab(tabId) {
    // Remove active from all tabs and contents
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Activate selected tab
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
    document.getElementById(`tab-${tabId}`).classList.add('active');
    
    // Load data when switching to certain tabs
    if (tabId === 'backup-history') {
        loadBackupList();
    }
    if (tabId === 'restore') {
        loadBackupOptions();
    }
}

// =============================================
// ALERTS
// =============================================
function showAlert(type, message, duration = 5000) {
    const container = document.getElementById('alertContainer');
    const alertHtml = `
        <div class="alert alert-${type}-modern" style="animation: fadeIn 0.3s ease;">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-exclamation-circle' : 'fa-exclamation-triangle'}"></i>
            <span>${message}</span>
        </div>
    `;
    container.innerHTML = alertHtml;
    
    if (duration > 0) {
        setTimeout(() => {
            container.innerHTML = '';
        }, duration);
    }
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// =============================================
// SAVE SETTINGS
// =============================================
function saveSettings(event) {
    event.preventDefault();
    
    const btn = document.getElementById('saveSettingsBtn');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('backupSettingsForm'));
    formData.append('action', 'save_settings');
    
    // Handle unchecked checkboxes
    if (!document.getElementById('autoBackupEnabled').checked) {
        formData.set('auto_backup_enabled', '0');
    }
    if (!document.getElementById('backupCompression').checked) {
        formData.set('backup_compression', '0');
    }
    
    fetch('backup_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', '<strong>Settings saved!</strong> Your backup configuration has been updated.');
        } else {
            showAlert('danger', '<strong>Error:</strong> ' + (data.message || 'Failed to save settings.'));
        }
    })
    .catch(error => {
        showAlert('danger', '<strong>Error:</strong> Could not save settings. Please try again.');
        console.error('Save settings error:', error);
    })
    .finally(() => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    });
    
    return false;
}

// =============================================
// CREATE BACKUP
// =============================================
function createBackup() {
    const btn = document.getElementById('backupNowBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Creating Backup...</span>';
    
    // Show progress
    document.getElementById('backupProgress').style.display = 'block';
    document.getElementById('progressText').textContent = 'Creating backup...';
    
    const formData = new FormData();
    formData.append('action', 'create_backup');
    
    fetch('backup_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        return response.text().then(text => {
            console.log('Backup API raw response (HTTP ' + response.status + '):', text);
            try {
                return JSON.parse(text);
            } catch(e) {
                console.error('Backup API returned non-JSON:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response (HTTP ' + response.status + ')');
            }
        });
    })
    .then(data => {
        document.getElementById('backupProgress').style.display = 'none';
        
        if (data.success) {
            showAlert('success', 
                `<strong>Backup created successfully!</strong><br>
                 File: ${data.filename}<br>
                 Size: ${formatFileSize(data.file_size)}<br>
                 Tables: ${data.tables_count}`
            , 8000);
            
            // Update backup count
            const countEl = document.getElementById('backupCount');
            countEl.textContent = parseInt(countEl.textContent) + 1;
        } else {
            showAlert('danger', '<strong>Backup failed:</strong> ' + (data.message || 'Unknown error'));
            if (data.debug) console.error('Backup debug info:', data.debug);
        }
    })
    .catch(error => {
        document.getElementById('backupProgress').style.display = 'none';
        showAlert('danger', '<strong>Error:</strong> ' + (error.message || 'Failed to create backup. Please check the server logs.'));
        console.error('Backup error:', error);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-database"></i> <span>Create Backup Now</span>';
    });
}

// =============================================
// LOAD BACKUP LIST
// =============================================
function loadBackupList() {
    const tbody = document.getElementById('backupListBody');
    tbody.innerHTML = `
        <tr class="loading-row">
            <td colspan="7">
                <div class="table-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading backups...</span>
                </div>
            </td>
        </tr>
    `;
    
    fetch('backup_api.php?action=list_backups')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.backups && data.backups.length > 0) {
            document.getElementById('noBackupsState').style.display = 'none';
            document.getElementById('backupHistoryTable').style.display = '';
            
            let html = '';
            data.backups.forEach(backup => {
                const date = backup.created_at ? new Date(backup.created_at).toLocaleString() : 
                             (backup.modified ? new Date(backup.modified * 1000).toLocaleString() : 'Unknown');
                const type = backup.backup_type || 'manual';
                const status = backup.status || 'completed';
                
                html += `
                    <tr>
                        <td class="filename-cell">${escapeHtml(backup.filename)}</td>
                        <td>
                            <span class="badge-type ${type}">
                                <i class="fas ${type === 'automatic' ? 'fa-clock' : 'fa-hand-pointer'}"></i>
                                ${type}
                            </span>
                        </td>
                        <td>${backup.file_size_formatted || formatFileSize(backup.file_size || 0)}</td>
                        <td>${backup.tables_count || '-'}</td>
                        <td>${date}</td>
                        <td>
                            <span class="badge-status ${status}">
                                <i class="fas ${status === 'completed' ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                                ${status}
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-action btn-download" title="Download" onclick="downloadBackup('${escapeHtml(backup.filename)}')">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn-action btn-restore-action" title="Restore" onclick="confirmRestore('${escapeHtml(backup.filename)}')">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button class="btn-action btn-delete-action" title="Delete" onclick="confirmDelete('${escapeHtml(backup.filename)}')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        } else {
            document.getElementById('backupHistoryTable').style.display = 'none';
            document.getElementById('noBackupsState').style.display = '';
        }
    })
    .catch(error => {
        tbody.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="table-loading" style="color: #dc2626;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Failed to load backups</span>
                    </div>
                </td>
            </tr>
        `;
        console.error('Load backups error:', error);
    });
}

// =============================================
// LOAD BACKUP OPTIONS (for restore select)
// =============================================
function loadBackupOptions() {
    const select = document.getElementById('restoreBackupSelect');
    
    fetch('backup_api.php?action=list_backups')
    .then(response => response.json())
    .then(data => {
        select.innerHTML = '<option value="">-- Select a backup file --</option>';
        
        if (data.success && data.backups) {
            data.backups.forEach(backup => {
                const date = backup.created_at ? new Date(backup.created_at).toLocaleString() : '';
                const size = backup.file_size_formatted || formatFileSize(backup.file_size || 0);
                const option = document.createElement('option');
                option.value = backup.filename;
                option.textContent = `${backup.filename} (${size}) - ${date}`;
                option.dataset.size = size;
                option.dataset.date = date;
                option.dataset.tables = backup.tables_count || '?';
                select.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Load backup options error:', error);
    });
}

// =============================================
// DOWNLOAD BACKUP
// =============================================
function downloadBackup(filename) {
    window.location.href = `backup_api.php?action=download_backup&filename=${encodeURIComponent(filename)}`;
}

// =============================================
// CONFIRM & DELETE BACKUP
// =============================================
function confirmDelete(filename) {
    showConfirmModal(
        'Delete Backup',
        `Are you sure you want to permanently delete <strong>${escapeHtml(filename)}</strong>? This action cannot be undone.`,
        'danger',
        () => deleteBackup(filename)
    );
}

function deleteBackup(filename) {
    closeConfirmModal();
    
    const formData = new FormData();
    formData.append('action', 'delete_backup');
    formData.append('filename', filename);
    
    fetch('backup_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', `<strong>Backup deleted:</strong> ${escapeHtml(filename)}`);
            loadBackupList();
            
            // Update count
            const countEl = document.getElementById('backupCount');
            countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);
        } else {
            showAlert('danger', '<strong>Delete failed:</strong> ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        showAlert('danger', '<strong>Error:</strong> Could not delete backup.');
        console.error('Delete error:', error);
    });
}

// =============================================
// CONFIRM & RESTORE FROM EXISTING
// =============================================
function confirmRestore(filename) {
    showConfirmModal(
        'Restore Database',
        `Are you sure you want to restore the database from <strong>${escapeHtml(filename)}</strong>?<br><br>
         <strong>This will overwrite all current data.</strong> A safety backup will be created automatically before the restore begins.`,
        'danger',
        () => restoreBackup(filename)
    );
}

function restoreBackup(filename) {
    closeConfirmModal();
    
    // Show restore progress
    document.getElementById('restoreProgressCard').style.display = '';
    document.getElementById('restoreProgressTitle').textContent = 'Restoring Database...';
    document.getElementById('restoreProgressDesc').textContent = `Restoring from ${filename}. Please wait...`;
    
    const formData = new FormData();
    formData.append('action', 'restore_backup');
    formData.append('filename', filename);
    
    fetch('backup_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('restoreProgressCard').style.display = 'none';
        
        if (data.success) {
            let msg = `<strong>Database restored successfully!</strong><br>Restored from: ${escapeHtml(filename)}`;
            if (data.safety_backup) {
                msg += `<br>Safety backup: ${escapeHtml(data.safety_backup)}`;
            }
            showAlert('success', msg, 10000);
        } else {
            showAlert('danger', '<strong>Restore failed:</strong> ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        document.getElementById('restoreProgressCard').style.display = 'none';
        showAlert('danger', '<strong>Error:</strong> Failed to restore database. Please check the server logs.');
        console.error('Restore error:', error);
    });
}

// =============================================
// RESTORE FROM EXISTING (select dropdown)
// =============================================
function restoreFromExisting() {
    const select = document.getElementById('restoreBackupSelect');
    const filename = select.value;
    
    if (!filename) {
        showAlert('warning', 'Please select a backup file to restore.');
        return;
    }
    
    confirmRestore(filename);
}

// =============================================
// FILE UPLOAD HANDLING
// =============================================
function handleFileSelect(input) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file extension
    const validExtensions = ['.sql', '.gz'];
    const hasValidExt = validExtensions.some(ext => file.name.toLowerCase().endsWith(ext));
    
    if (!hasValidExt) {
        showAlert('danger', 'Invalid file format. Please upload a .sql or .sql.gz file.');
        clearFileUpload();
        return;
    }
    
    selectedRestoreFile = file;
    
    // Show preview
    document.querySelector('.upload-placeholder').style.display = 'none';
    document.getElementById('uploadPreview').style.display = 'flex';
    document.getElementById('uploadFileName').textContent = file.name;
    document.getElementById('uploadFileSize').textContent = formatFileSize(file.size);
    document.getElementById('restoreUploadBtn').disabled = false;
}

function clearFileUpload() {
    selectedRestoreFile = null;
    document.getElementById('restoreFileInput').value = '';
    document.querySelector('.upload-placeholder').style.display = '';
    document.getElementById('uploadPreview').style.display = 'none';
    document.getElementById('restoreUploadBtn').disabled = true;
}

function restoreFromUpload() {
    if (!selectedRestoreFile) {
        showAlert('danger', 'No file selected.');
        return;
    }
    
    showConfirmModal(
        'Restore from Upload',
        `Are you sure you want to restore the database from <strong>${escapeHtml(selectedRestoreFile.name)}</strong>?<br><br>
         <strong>This will overwrite all current data.</strong> A safety backup will be created automatically.`,
        'danger',
        () => performUploadRestore()
    );
}

function performUploadRestore() {
    closeConfirmModal();
    
    document.getElementById('restoreProgressCard').style.display = '';
    document.getElementById('restoreProgressTitle').textContent = 'Uploading & Restoring...';
    document.getElementById('restoreProgressDesc').textContent = `Uploading ${selectedRestoreFile.name} and restoring database...`;
    
    const formData = new FormData();
    formData.append('action', 'restore_upload');
    formData.append('backup_file', selectedRestoreFile);
    
    fetch('backup_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('restoreProgressCard').style.display = 'none';
        
        if (data.success) {
            let msg = '<strong>Database restored successfully from uploaded file!</strong>';
            if (data.safety_backup) {
                msg += `<br>Safety backup: ${escapeHtml(data.safety_backup)}`;
            }
            showAlert('success', msg, 10000);
            clearFileUpload();
        } else {
            showAlert('danger', '<strong>Restore failed:</strong> ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        document.getElementById('restoreProgressCard').style.display = 'none';
        showAlert('danger', '<strong>Error:</strong> Failed to restore from uploaded file.');
        console.error('Upload restore error:', error);
    });
}

// Drag and drop support
const uploadArea = document.getElementById('uploadArea');
if (uploadArea) {
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        
        const file = e.dataTransfer.files[0];
        if (file) {
            const input = document.getElementById('restoreFileInput');
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            handleFileSelect(input);
        }
    });
}

// =============================================
// BACKUP SELECT CHANGE
// =============================================
document.getElementById('restoreBackupSelect').addEventListener('change', function() {
    const btn = document.getElementById('restoreExistingBtn');
    const previewCard = document.getElementById('backupPreviewCard');
    
    if (this.value) {
        btn.disabled = false;
        
        // Show preview from option data
        const option = this.options[this.selectedIndex];
        document.getElementById('previewFilename').textContent = this.value;
        document.getElementById('previewSize').textContent = option.dataset.size || '-';
        document.getElementById('previewDate').textContent = option.dataset.date || '-';
        document.getElementById('previewTables').textContent = option.dataset.tables || '-';
        previewCard.style.display = '';
    } else {
        btn.disabled = true;
        previewCard.style.display = 'none';
    }
});

// =============================================
// CONFIRMATION MODAL
// =============================================
function showConfirmModal(title, message, type, onConfirm) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').innerHTML = message;
    
    const iconEl = document.getElementById('confirmIcon');
    iconEl.className = 'confirm-modal-icon' + (type === 'danger' ? ' danger' : '');
    
    const confirmBtn = document.getElementById('confirmActionBtn');
    confirmBtn.onclick = onConfirm;
    
    document.getElementById('confirmModal').style.display = 'flex';
}

function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

// Close modal on overlay click
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirmModal();
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConfirmModal();
});

// =============================================
// CRON URL
// =============================================
function loadCronUrl() {
    fetch('backup_api.php?action=get_cron_url')
    .then(response => {
        return response.text().then(text => {
            console.log('Cron URL API raw response (HTTP ' + response.status + '):', text);
            try {
                return JSON.parse(text);
            } catch(e) {
                console.error('Cron URL API returned non-JSON:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response (HTTP ' + response.status + ')');
            }
        });
    })
    .then(data => {
        if (data.success) {
            document.getElementById('cronUrl').value = data.cron_url;
            document.getElementById('cronCurlArgs').textContent = `-s "${data.cron_url}"`;
            document.getElementById('cronCommand').textContent = 
                `# Run according to your backup schedule\n0 2 * * * curl -s "${data.cron_url}" > /dev/null 2>&1`;
        } else {
            document.getElementById('cronUrl').value = 'Error: ' + (data.message || 'Unknown');
            if (data.debug) console.error('Cron URL debug info:', data.debug);
        }
    })
    .catch(error => {
        document.getElementById('cronUrl').value = 'Failed to load';
        console.error('Load cron URL error:', error);
    });
}

function copyCronUrl() {
    const input = document.getElementById('cronUrl');
    input.select();
    document.execCommand('copy');
    showAlert('success', 'Cron URL copied to clipboard!', 3000);
}

function regenerateToken() {
    showConfirmModal(
        'Regenerate Token',
        'This will generate a new cron token. Your current scheduled task URL will stop working and needs to be updated. Continue?',
        'warning',
        () => {
            closeConfirmModal();
            
            const formData = new FormData();
            formData.append('action', 'regenerate_token');
            
            fetch('backup_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', '<strong>Token regenerated!</strong> Update your scheduled task with the new URL.');
                    loadCronUrl();
                } else {
                    showAlert('danger', 'Failed to regenerate token.');
                }
            });
        }
    );
}

// =============================================
// FREQUENCY TOGGLE
// =============================================
function updateFrequencyFields() {
    const frequency = document.getElementById('backupFrequency').value;
    const autoEnabled = document.getElementById('autoBackupEnabled').checked;
    
    // Show/hide auto settings
    document.querySelectorAll('.auto-setting').forEach(el => {
        el.style.display = autoEnabled ? '' : 'none';
    });
    
    // Show/hide day selectors based on frequency
    document.getElementById('dayOfWeekRow').style.display = (autoEnabled && frequency === 'weekly') ? '' : 'none';
    document.getElementById('dayOfMonthRow').style.display = (autoEnabled && frequency === 'monthly') ? '' : 'none';
}

document.getElementById('autoBackupEnabled').addEventListener('change', updateFrequencyFields);
document.getElementById('backupFrequency').addEventListener('change', updateFrequencyFields);

// =============================================
// UTILITY FUNCTIONS
// =============================================
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// =============================================
// INITIALIZATION
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    updateFrequencyFields();
    loadCronUrl();
    
    // Initial load of backup list if on that tab
    const activeTab = document.querySelector('.tab-btn.active');
    if (activeTab && activeTab.dataset.tab === 'backup-history') {
        loadBackupList();
    }
});
</script>
