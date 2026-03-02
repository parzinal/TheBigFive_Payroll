<?php
/**
 * PHP Extensions Diagnostic Page
 * Check if required extensions are enabled
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Extensions Check - TheBigFive Payroll</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;h
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2d3748;
            margin-top: 0;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status.success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        .status.error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        .icon {
            font-size: 24px;
        }
        .instructions {
            background: #ebf8ff;
            border-left: 4px solid #4299e1;
            padding: 20px;
            margin: 20px 0;
        }
        .instructions h3 {
            margin-top: 0;
            color: #2c5282;
        }
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .instructions li {
            margin: 8px 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .info-table th,
        .info-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4299e1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #3182ce;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>📊 PHP Extensions Diagnostic</h1>
        
        <?php
        $zipEnabled = class_exists('ZipArchive');
        $phpVersion = phpversion();
        $phpIni = php_ini_loaded_file();
        $extensions = get_loaded_extensions();
        ?>
        
        <h2>Required Extension Status</h2>
        
        <div class="status <?php echo $zipEnabled ? 'success' : 'error'; ?>">
            <span class="icon"><?php echo $zipEnabled ? '✅' : '❌'; ?></span>
            <div>
                <strong>ZipArchive Extension:</strong> 
                <?php echo $zipEnabled ? 'ENABLED ✓' : 'NOT ENABLED ✗'; ?>
                <br>
                <small><?php echo $zipEnabled ? 'Excel import will work' : 'Excel import will NOT work'; ?></small>
            </div>
        </div>
        
        <?php if (!$zipEnabled): ?>
        <div class="instructions">
            <h3>🔧 How to Enable ZIP Extension in Laragon</h3>
            <ol>
                <li>Locate the <strong>Laragon icon</strong> in your system tray (bottom-right corner)</li>
                <li><strong>Right-click</strong> the Laragon icon</li>
                <li>Click <strong>"Stop All"</strong></li>
                <li>Wait for all services to stop (icon turns gray)</li>
                <li><strong>Right-click</strong> Laragon icon again</li>
                <li>Click <strong>"Start All"</strong></li>
                <li><strong>Refresh this page</strong> to verify the extension is loaded</li>
            </ol>
            
            <p><strong>Note:</strong> The zip extension is already enabled in your php.ini file. You just need to restart Apache to load it.</p>
        </div>
        <?php else: ?>
        <div class="status success">
            <span class="icon">🎉</span>
            <div>
                <strong>All systems ready!</strong> You can now import Excel files.
                <br>
                <a href="Generatepayroll.php" class="btn">Go to Payroll Generation</a>
            </div>
        </div>
        <?php endif; ?>
        
        <h2>System Information</h2>
        <table class="info-table">
            <tr>
                <th>PHP Version</th>
                <td><?php echo $phpVersion; ?></td>
            </tr>
            <tr>
                <th>Loaded php.ini</th>
                <td><?php echo $phpIni ?: 'None'; ?></td>
            </tr>
            <tr>
                <th>Server Software</th>
                <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
            </tr>
            <tr>
                <th>Total Extensions Loaded</th>
                <td><?php echo count($extensions); ?></td>
            </tr>
            <tr>
                <th>ZIP in Loaded Extensions</th>
                <td><?php echo in_array('zip', $extensions) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
        </table>
        
        <details style="margin-top: 20px;">
            <summary style="cursor: pointer; padding: 10px; background: #f7fafc; border-radius: 4px;">
                <strong>View All Loaded Extensions (<?php echo count($extensions); ?>)</strong>
            </summary>
            <div style="padding: 15px; background: #f7fafc; margin-top: 10px; border-radius: 4px; max-height: 300px; overflow-y: auto;">
                <?php echo implode(', ', $extensions); ?>
            </div>
        </details>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e2e8f0; text-align: center; color: #718096;">
            <small>TheBigFive Payroll System | PHP Extensions Diagnostic</small>
        </div>
    </div>
</body>
</html>
