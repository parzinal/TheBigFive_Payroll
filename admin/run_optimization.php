<?php
/**
 * Database Optimization Runner
 * Safely applies index optimizations (add composite indexes, drop redundant ones).
 * Can be run multiple times — skips changes already applied.
 *
 * Usage: Access via browser as admin, e.g. /admin/run_optimization.php
 */

require_once '../config/bootstrap.php';
require_once '../config/database.php';
require_once '../config/auth.php';

// Require admin authentication
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(403);
    die('Unauthorized. Admin access required.');
}

header('Content-Type: text/html; charset=utf-8');

$pdo = getDBConnection();
$results = [];

/**
 * Check if an index exists on a table
 */
function indexExists(PDO $pdo, string $table, string $indexName): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $indexName]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Safely add an index (skip if already exists)
 */
function addIndex(PDO $pdo, string $table, string $indexName, string $columns, array &$results): void {
    if (indexExists($pdo, $table, $indexName)) {
        $results[] = ['skip', "Index {$table}.{$indexName} already exists"];
        return;
    }
    try {
        $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columns})");
        $results[] = ['add', "Added index {$table}.{$indexName} ({$columns})"];
    } catch (PDOException $e) {
        $results[] = ['error', "Failed to add {$table}.{$indexName}: " . $e->getMessage()];
    }
}

/**
 * Safely drop an index (skip if doesn't exist)
 */
function dropIndex(PDO $pdo, string $table, string $indexName, array &$results): void {
    if (!indexExists($pdo, $table, $indexName)) {
        $results[] = ['skip', "Index {$table}.{$indexName} does not exist (already removed)"];
        return;
    }
    try {
        $pdo->exec("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        $results[] = ['drop', "Dropped redundant index {$table}.{$indexName}"];
    } catch (PDOException $e) {
        $results[] = ['error', "Failed to drop {$table}.{$indexName}: " . $e->getMessage()];
    }
}

// ============================================================================
// PHASE 1: Add new composite indexes
// ============================================================================
$results[] = ['header', 'PHASE 1: Adding Composite Indexes'];

addIndex($pdo, 'dtr_records', 'idx_emp_period_date',
    '`employee_id`, `payroll_period_id`, `dtr_date`', $results);

addIndex($pdo, 'employees', 'idx_status_name',
    '`status`, `full_name`', $results);

addIndex($pdo, 'account_logs', 'idx_user_created',
    '`user_id`, `created_at` DESC', $results);

addIndex($pdo, 'payroll_computations', 'idx_emp_status_created',
    '`employee_id`, `status`, `created_at` DESC', $results);

// ============================================================================
// PHASE 2: Drop redundant indexes (duplicates of UNIQUE constraints)
// ============================================================================
$results[] = ['header', 'PHASE 2: Removing Redundant Indexes'];

dropIndex($pdo, 'dtr_records', 'idx_employee_date', $results);
dropIndex($pdo, 'employees', 'idx_employee_code', $results);
dropIndex($pdo, 'users', 'idx_username', $results);
dropIndex($pdo, 'users', 'idx_email', $results);
dropIndex($pdo, 'deduction_types', 'idx_deduction_code', $results);
dropIndex($pdo, 'positions', 'idx_position_name', $results);
dropIndex($pdo, 'backup_settings', 'idx_setting_key', $results);

// ============================================================================
// Output results
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Optimization Results</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 24px; margin-bottom: 20px; }
        h1 { color: #333; }
        .result { padding: 8px 12px; margin: 4px 0; border-radius: 4px; font-family: 'Consolas', monospace; font-size: 14px; }
        .result-add { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .result-drop { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .result-skip { background: #e2e3e5; color: #383d41; border-left: 4px solid #6c757d; }
        .result-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .result-header { background: #cce5ff; color: #004085; border-left: 4px solid #007bff; font-weight: bold; margin-top: 16px; }
        .summary { margin-top: 20px; padding: 16px; background: #f0f0f0; border-radius: 4px; }
        .back-link { display: inline-block; margin-top: 16px; color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>&#x1f527; Database Optimization Results</h1>
        <p>Executed on: <?php echo date('Y-m-d H:i:s'); ?></p>
        
        <?php foreach ($results as $r): ?>
            <div class="result result-<?php echo htmlspecialchars($r[0]); ?>">
                <?php echo htmlspecialchars($r[1]); ?>
            </div>
        <?php endforeach; ?>
        
        <div class="summary">
            <?php
            $added = count(array_filter($results, fn($r) => $r[0] === 'add'));
            $dropped = count(array_filter($results, fn($r) => $r[0] === 'drop'));
            $skipped = count(array_filter($results, fn($r) => $r[0] === 'skip'));
            $errors = count(array_filter($results, fn($r) => $r[0] === 'error'));
            ?>
            <strong>Summary:</strong>
            <?php echo $added; ?> indexes added,
            <?php echo $dropped; ?> indexes dropped,
            <?php echo $skipped; ?> skipped (already applied),
            <?php echo $errors; ?> errors
        </div>
        
        <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    </div>
</body>
</html>
