<?php
/**
 * Staff/Admin Salary Rules API (read-only)
 * Returns Shift 1/Shift 2 rule values and late equivalency rules
 * consumed by payroll list computations.
 */

require_once '../config/bootstrap.php';
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isAuthenticated() || (!isAdmin() && !isStaff())) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function ensureSalaryRulesTable(PDO $pdo): void {
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS payroll_shift_rules (\n            shift_code VARCHAR(20) PRIMARY KEY,\n            shift_name VARCHAR(100) NOT NULL,\n            per_day_rate DECIMAL(12,2) NOT NULL DEFAULT 0,\n            ot_rate DECIMAL(12,2) NOT NULL DEFAULT 0,\n            time_in TIME NOT NULL DEFAULT '08:00:00',\n            time_out TIME NOT NULL DEFAULT '17:00:00',\n            updated_by INT NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");

    try {
        $pdo->exec("ALTER TABLE payroll_shift_rules ADD COLUMN ot_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER per_day_rate");
    } catch (Throwable $e) {
        // Ignore if column already exists.
    }

    $pdo->exec("\n        INSERT IGNORE INTO payroll_shift_rules (shift_code, shift_name, per_day_rate, ot_rate, time_in, time_out) VALUES\n        ('shift_1', 'Shift 1', 0.00, 0.00, '08:00:00', '17:00:00'),\n        ('shift_2', 'Shift 2', 0.00, 0.00, '08:00:00', '17:00:00')\n    ");
}

function ensurePayrollRuleSettingsTable(PDO $pdo): void {
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS payroll_rule_settings (\n            id TINYINT UNSIGNED PRIMARY KEY,\n            late_rule_1_actual_minutes DECIMAL(10,2) NULL,\n            late_rule_1_equivalent_minutes DECIMAL(10,2) NULL,\n            late_rule_2_actual_minutes DECIMAL(10,2) NULL,\n            late_rule_2_equivalent_minutes DECIMAL(10,2) NULL,\n            late_rule_3_actual_minutes DECIMAL(10,2) NULL,\n            late_rule_3_equivalent_minutes DECIMAL(10,2) NULL,\n            fixed_per_day_rate DECIMAL(12,2) NOT NULL DEFAULT 0,\n            trainer_per_day_rate DECIMAL(12,2) NOT NULL DEFAULT 500,\n            grace_period_minutes INT NOT NULL DEFAULT 5,\n            updated_by INT NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");

    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_1_actual_minutes DECIMAL(10,2) NULL AFTER id");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_1_equivalent_minutes DECIMAL(10,2) NULL AFTER late_rule_1_actual_minutes");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_2_actual_minutes DECIMAL(10,2) NULL AFTER late_rule_1_equivalent_minutes");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_2_equivalent_minutes DECIMAL(10,2) NULL AFTER late_rule_2_actual_minutes");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_3_actual_minutes DECIMAL(10,2) NULL AFTER late_rule_2_equivalent_minutes");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN late_rule_3_equivalent_minutes DECIMAL(10,2) NULL AFTER late_rule_3_actual_minutes");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN fixed_per_day_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER late_rule_3_equivalent_minutes");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN trainer_per_day_rate DECIMAL(12,2) NOT NULL DEFAULT 500 AFTER fixed_per_day_rate");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE payroll_rule_settings ADD COLUMN grace_period_minutes INT NOT NULL DEFAULT 5 AFTER trainer_per_day_rate");
    } catch (Throwable $e) {
    }

    $pdo->exec("INSERT IGNORE INTO payroll_rule_settings (id) VALUES (1)");
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

function normalizeRuleRate($value): float {
    $num = floatval($value);
    if (!is_finite($num) || $num < 0) {
        return 0.0;
    }
    return round($num, 2);
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

try {
    $pdo = getDBConnection();
    ensureSalaryRulesTable($pdo);
    ensurePayrollRuleSettingsTable($pdo);

    $stmt = $pdo->query("\n        SELECT
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
        'late_rule' => $legacyLateRule,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load salary rules',
    ]);
}
