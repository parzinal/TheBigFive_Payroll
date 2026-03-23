<?php
/**
 * Generate Payslip PDF – TB5 Format
 *
 * Renders an official payslip that exactly matches the TB5 layout image.
 * Uses DomPDF (dompdf/dompdf ^2.0 – install via: composer require dompdf/dompdf).
 *
 * DB columns read
 * ───────────────
 * payroll_computations : basic_pay, ot_pay, total_work_hours, total_ot_hours,
 *                        per_hour_rate, withholding_tax, sss_contribution,
 *                        philhealth_contribution, pagibig_contribution,
 *                        other_deductions, other_deductions_notes,
 *                        total_earnings, total_deductions, net_pay, status
 * employees            : full_name, position, department
 * payroll_periods      : period_name, start_date, pay_date
 *
 * other_deductions_notes JSON keys used
 * ──────────────────────────────────────
 * commission   → INCENTIVE adjustment
 * sick_pay     → PAID LEAVES adjustment
 * holiday_pay  → HOLIDAY PAY adjustment
 * expense      → OTHERS adjustment
 * student_loan → PAG-IBIG deduction
 * union_fees   → LOAN deduction (part)
 * pension      → LOAN deduction (part)
 * other_deductions → OTHERS/C.A. deduction (part)
 * dtr_data.{late_deduct, undertime_deduct, halfday_deduct, absent_deduct} → TARDINESS
 */

// ── STEP 1: Load root Composer autoloader FIRST ───────────────────────────────
// dirname(__FILE__) = admin/   │   dirname(dirname(__FILE__)) = project root
// Using __FILE__ (not __DIR__) is the most portable on Windows (avoids backslash
// vs forward-slash path deduplication bugs inside PHP's require_once internal table).
// This MUST happen before auth.php/bootstrap.php so no other autoloader
// (e.g. admin/vendor/autoload.php) gets registered first without DomPDF.
$_projectRoot       = dirname(dirname(__FILE__));
$_composerAutoload  = $_projectRoot . DIRECTORY_SEPARATOR . 'vendor'
                    . DIRECTORY_SEPARATOR . 'autoload.php';

if (!file_exists($_composerAutoload)) {
    http_response_code(500);
    die('Composer autoload not found at: ' . htmlspecialchars($_composerAutoload)
        . '<br>Run: <code>composer install</code> in the project root.');
}
require_once $_composerAutoload;

// ── STEP 2: DomPDF PSR-4 fallback autoloader ─────────────────────────────────
// DomPDF v3 removed its own Autoloader.php, so if the Composer classmap somehow
// missed DomPDF (stale cache, etc.), register a direct PSR-4 autoloader as backup.
if (!class_exists('Dompdf\\Dompdf', false)) {
    spl_autoload_register(function (string $class) use ($_projectRoot): void {
        $nsMap = [
            'Dompdf\\'   => $_projectRoot . '/vendor/dompdf/dompdf/src/',
            'FontLib\\'  => $_projectRoot . '/vendor/dompdf/php-font-lib/src/FontLib/',
            'Svg\\'      => $_projectRoot . '/vendor/dompdf/php-svg-lib/src/Svg/',
        ];
        foreach ($nsMap as $prefix => $baseDir) {
            if (strpos($class, $prefix) === 0) {
                $relative = substr($class, strlen($prefix));
                $file     = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                }
                return;
            }
        }
    });
}

if (!class_exists('Dompdf\\Dompdf')) {
    http_response_code(500);
    die(
        '<b>DomPDF not installed.</b><br>' .
        'Run these commands in the project root:<br><pre>' .
        'composer require dompdf/dompdf' . "\n" .
        'composer dump-autoload --optimize</pre>'
    );
}

// ── STEP 3: App bootstrap (auth + DB) ────────────────────────────────────────
// bootstrap.php also calls require_once on vendor/autoload.php — PHP's require_once
// will skip it since it's already in the inclusion table. No double-loading occurs.
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// ── Auth check ────────────────────────────────────────────────────────────────
if (!isAuthenticated() || (!isAdmin() && !isStaff())) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$payslip_id = isset($_GET['payslip_id']) ? intval($_GET['payslip_id']) : 0;
if ($payslip_id <= 0) {
    die('Invalid payslip ID.');
}

try {
    $pdo = getDBConnection();

    // Pull every relevant column from the three tables
    $stmt = $pdo->prepare("
        SELECT
            pc.id,
            pc.employee_id,
            e.employee_code,
            e.full_name                 AS employee_name,
            e.position,
            e.department,
            pp.period_name,
            pp.start_date,
            pp.end_date,
            pp.pay_date,
            -- Salary / rate
            pc.basic_monthly_salary,
            pc.per_day_rate,
            pc.per_hour_rate,
            -- Hours worked
            pc.total_work_hours,
            pc.total_ot_hours,
            -- Earnings (direct DB columns)
            pc.basic_pay,
            pc.ot_pay,
            -- Deductions (direct DB columns)
            pc.late_deduction,
            pc.undertime_deduction,
            pc.absent_deduction,
            pc.withholding_tax,
            pc.sss_contribution,
            pc.philhealth_contribution,
            pc.pagibig_contribution,
            pc.other_deductions,
            -- Totals
            pc.total_earnings,
            pc.total_deductions,
            pc.net_pay,
            -- Training payment
            pc.trainings_cost,
            pc.training_amount,
            -- Extra JSON payload (adjustments, dtr breakdown, etc.)
            pc.other_deductions_notes,
            pc.status,
            pc.created_at
        FROM payroll_computations pc
        INNER JOIN employees       e  ON pc.employee_id      = e.id
        INNER JOIN payroll_periods pp ON pc.payroll_period_id = pp.id
        WHERE pc.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $payslip_id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        die('Payslip not found.');
    }

} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}

// ── Decode other_deductions_notes JSON ───────────────────────────────────────
$notes = [];
if (!empty($p['other_deductions_notes'])) {
    $decoded = json_decode($p['other_deductions_notes'], true);
    if (is_array($decoded)) {
        $notes = $decoded;
    }
}

// DTR breakdown lives inside the dtr_data sub-key
$dtr = $notes['dtr_data'] ?? [];

// ── Earnings ──────────────────────────────────────────────────────────────────
// basic_pay  = regular (standard + basic allowance) — stored directly in payroll_computations
// ot_pay     = overtime pay — stored directly in payroll_computations
$regular_pay = floatval($p['basic_pay']);
$ot_pay      = floatval($p['ot_pay']);
$ot_hours    = floatval($p['total_ot_hours']);
$work_hours  = floatval($p['total_work_hours']);

// Adjustments (from JSON notes)
$incentive   = floatval($notes['commission']  ?? 0);   // commission   → INCENTIVE
$paid_leaves = floatval($notes['sick_pay']    ?? 0);   // sick_pay     → PAID LEAVES
$holiday_pay = floatval($notes['holiday_pay'] ?? 0);   // holiday_pay  → HOLIDAY PAY
$others_adj  = floatval($notes['expense']     ?? 0);   // expense      → OTHERS (adjustments)
$training_pay = floatval($p['trainings_cost'] ?? 0)
             ?: floatval($p['training_amount'] ?? 0);   // fallback to training_amount column

// ── Deductions ────────────────────────────────────────────────────────────────
// Direct DB columns
$wh_tax    = floatval($p['withholding_tax']);          // withholding_tax        → W/H TAX
$sss       = floatval($p['sss_contribution']);         // sss_contribution       → SSS
$philheath = floatval($p['philhealth_contribution']);  // philhealth_contribution → PHILHEALTH

// PAG-IBIG: stored as student_loan in JSON (TB5 mapping); fallback to DB column
$pagibig = floatval($notes['student_loan'] ?? null)
         ?: floatval($p['pagibig_contribution'] ?? 0);

// TARDINESS = sum of all DTR deductions (from JSON breakdown, with DB column fallback)
$tardiness = floatval($dtr['late_deduct']      ?? 0)
           + floatval($dtr['undertime_deduct']  ?? 0)
           + floatval($dtr['halfday_deduct']    ?? 0)
           + floatval($dtr['absent_deduct']     ?? 0);

// Fallback: if no JSON dtr_data breakdown, use the direct DB columns
if ($tardiness == 0 && empty($dtr)) {
    $tardiness = floatval($p['late_deduction']      ?? 0)
               + floatval($p['undertime_deduction']  ?? 0)
               + floatval($p['absent_deduction']     ?? 0);
}

// LOAN = union fees + pension contributions
$loan = floatval($notes['union_fees'] ?? 0)
      + floatval($notes['pension']    ?? 0);

// OTHERS / C.A. = miscellaneous JSON deductions + DB other_deductions
$others_ca = floatval($notes['other_deductions'] ?? 0)
           + floatval($p['other_deductions']      ?? 0);

// Totals (taken directly from DB — already computed by process_payroll.php)
$gross_pay    = floatval($p['total_earnings']);
$total_deduct = floatval($p['total_deductions']);
$net_pay      = floatval($p['net_pay']);

// ── Period / Cut-off label ────────────────────────────────────────────────────
$start_day  = (int) date('j', strtotime($p['start_date']));
$cutoff_str = $start_day <= 15
    ? '1st CUT OFF ( 1st / 15th )'
    : '2nd CUT OFF ( 16th / 31st )';

// Date displayed on payslip
$pay_date_fmt = !empty($p['pay_date'])
    ? date('d-M-y', strtotime($p['pay_date']))
    : date('d-M-y', strtotime($p['created_at']));

// OT in minutes (for the MIN column)
$ot_minutes = $ot_hours > 0 ? (int) round($ot_hours * 60) : '';

// ── Helper ────────────────────────────────────────────────────────────────────
function fmt(float $v): string { return number_format($v, 2); }
function fmtSign(float $v): string { return 'P' . number_format(abs($v), 2); }

// ── Build HTML for DomPDF ─────────────────────────────────────────────────────
$html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
/* ════════════════════════════════════════════════════════
   MODERN TB5 PAYSLIP  –  DomPDF A5 Landscape
   Palette
     Navy    #0f3460   Dark-navy  #071e3d
     Blue    #1a56db   Ice-blue   #e8f0fe
     Green   #15803d   Mint       #dcfce7
     Slate   #64748b   Snow       #f8fafc
     Red     #dc2626   Rose       #fef2f2
════════════════════════════════════════════════════════ */

* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 8pt;
    color: #1e293b;
    background: #fff;
}

/* ── Outer card ── */
.card {
    width: 100%;
    border: 1.5px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
}

/* ── Shared table reset ── */
table { width:100%; border-collapse:collapse; }
td    { vertical-align:middle; }

/* ════ 1. BANNER HEADER ════ */
.banner {
    background: #0f3460;
    padding: 0;
}
.banner td { padding: 8px 12px; vertical-align:middle; }
.company-block { }
.company-tag {
    font-size: 6.5pt;
    color: #93c5fd;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: 600;
}
.company-name {
    font-size: 12.5pt;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: 0.3px;
    line-height: 1.1;
    margin-top: 1px;
}
.tb5-badge {
    background: #1a56db;
    color: #fff;
    font-weight: 800;
    font-size: 11pt;
    letter-spacing: 2px;
    text-align: center;
    padding: 6px 14px;
    border-radius: 4px;
    border: 2px solid #3b82f6;
}
.payslip-tag {
    color: #bfdbfe;
    font-size: 7pt;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-top: 2px;
    display: block;
    text-align: center;
}

/* ════ 2. INFO STRIP ════ */
.info-strip {
    background: #e8f0fe;
    border-top: 1.5px solid #cbd5e1;
    border-bottom: 1.5px solid #cbd5e1;
}
.info-strip td { padding: 5px 12px; }
.info-label {
    font-size: 6.5pt;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    display: block;
}
.info-value {
    font-size: 9pt;
    font-weight: 700;
    color: #0f3460;
    display: block;
    margin-top: 1px;
}
.emp-pill {
    background: #15803d;
    color: #fff;
    font-weight: 700;
    font-size: 9pt;
    padding: 2px 10px;
    border-radius: 20px;
    display: inline-block;
}
.cutoff-pill {
    font-size: 7.5pt;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    display: inline-block;
}
.cutoff-1st { background:#fef9c3; color:#854d0e; border:1px solid #fbbf24; }
.cutoff-2nd { background:#fce7f3; color:#9d174d; border:1px solid #f472b6; }
.status-pill {
    background: #dcfce7;
    color: #15803d;
    font-weight: 700;
    font-size: 7pt;
    padding: 1px 7px;
    border-radius: 20px;
    border: 1px solid #86efac;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.divider-v { border-left: 1.5px solid #cbd5e1; }

/* ════ 3. BODY ════ */
.body-wrap td { vertical-align: top; padding: 0; }

/* Section headers (EARNINGS / DEDUCTIONS / SUMMARY) */
.sec-hdr {
    background: #071e3d;
    color: #fff;
    font-size: 7pt;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    padding: 4px 10px;
}
.sec-hdr-green  { background: #15803d; }
.sec-hdr-red    { background: #991b1b; }
.sec-hdr-navy   { background: #1e40af; }

/* Sub-section label rows (OVERTIME | ADJUSTMENTS) */
.sub-hdr td {
    background: #f1f5f9;
    font-size: 7pt;
    font-weight: 700;
    color: #475569;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    padding: 3px 10px;
    border-bottom: 1px solid #e2e8f0;
}
.sub-hdr-right { text-align: right; }

/* Data rows */
.data-row td {
    font-size: 8pt;
    padding: 3px 10px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}
.data-row:last-child td { border-bottom: none; }
.row-label { color: #334155; font-weight: 600; }
.row-value { text-align: right; font-weight: 600; color: #0f3460; }
.row-value-ded { text-align: right; font-weight: 600; color: #dc2626; }
.row-value-muted { text-align: right; color: #94a3b8; font-size: 7.5pt; }

/* Pane borders */
.pane-earn { width:38%; border-right:1.5px solid #e2e8f0; }
.pane-ded  { width:35%; border-right:1.5px solid #e2e8f0; }
.pane-sum  { width:27%; background:#f8fafc; }

/* ════ 4. SUMMARY PANE ════ */
.sum-row td { padding: 4px 10px; font-size: 8pt; }
.sum-label { color:#475569; font-weight:700; }
.sum-val   { text-align:right; font-weight:700; color:#0f3460; border-bottom:1px solid #e2e8f0; }
.net-block {
    background: #0f3460;
    margin: 6px;
    border-radius: 4px;
    padding: 7px 10px;
    text-align: center;
}
.net-label-t { color:#93c5fd; font-size:6.5pt; font-weight:700; letter-spacing:1px; text-transform:uppercase; display:block; }
.net-amount  { color:#ffffff; font-size:14pt; font-weight:800; display:block; margin-top:2px; }
.hours-block {
    margin: 0 6px 6px 6px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 4px 8px;
    background: #fff;
    text-align: center;
}
.hours-label { color:#64748b; font-size:6pt; font-weight:700; letter-spacing:0.8px; text-transform:uppercase; display:block; }
.hours-row   { margin-top:3px; }
.hours-row td { padding:1px 4px; font-size:7.5pt; font-weight:700; text-align:center; }
.h-ot   { color:#1a56db; }
.h-reg  { color:#15803d; }
.h-sep  { color:#cbd5e1; }

/* ════ 5. FOOTER ════ */
.footer-bar {
    background: #f8fafc;
    border-top: 1.5px solid #e2e8f0;
}
.footer-bar td { padding: 6px 14px; font-size: 8pt; }
.sig-label { color:#64748b; font-size:7pt; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; display:block; }
.sig-name  { color:#0f3460; font-weight:700; font-size:8.5pt; margin-top:1px; display:block; }
.sig-line  { border-bottom:1.5px solid #94a3b8; display:inline-block; min-width:100px; margin-top:1px; }
.approved-name { color:#0f3460; font-weight:700; font-style:italic; }

</style>
</head>
<body>
<div class="card">

<!-- ══ 1. BANNER ═══════════════════════════════════════════════════════════ -->
<table class="banner">
  <tr>
    <td style="width:70%;">
      <div class="company-tag">Official Payslip Document</div>
      <div class="company-name">The Big Five Training and Assessment Center</div>
    </td>
    <td style="width:30%; text-align:center; border-left:1px solid #1a3a6e;">
      <div class="tb5-badge">TB5 COPY</div>
      <span class="payslip-tag">Semi-Monthly Payroll</span>
    </td>
  </tr>
</table>

<!-- ══ 2. INFO STRIP ═══════════════════════════════════════════════════════ -->
<table class="info-strip">
  <tr>
    <td style="width:38%;">
      <span class="info-label">Employee</span>
      <span class="emp-pill">' . htmlspecialchars($p['employee_name']) . '</span>
    </td>
    <td class="divider-v" style="width:20%;">
      <span class="info-label">Status</span>
      <span class="status-pill">' . htmlspecialchars($p['status']) . '</span>
    </td>
    <td class="divider-v" style="width:24%;">
      <span class="info-label">Cut-off Period</span>
      <span class="cutoff-pill ' . ($start_day <= 15 ? 'cutoff-1st' : 'cutoff-2nd') . '">'
        . htmlspecialchars($cutoff_str) . '</span>
    </td>
    <td class="divider-v" style="width:18%;">
      <span class="info-label">Pay Date</span>
      <span class="info-value">' . htmlspecialchars($pay_date_fmt) . '</span>
    </td>
  </tr>
</table>

<!-- ══ 3. BODY ════════════════════════════════════════════════════════════ -->
<table class="body-wrap">
<tr>

  <!-- ── EARNINGS PANE ── -->
  <td class="pane-earn">
    <div class="sec-hdr sec-hdr-green">Earnings</div>

    <!-- OT / PAY sub-section -->
    <table>
      <tr class="sub-hdr">
        <td style="width:40%;">Type</td>
        <td style="width:30%;" class="sub-hdr-right">OT Min</td>
        <td style="width:30%;" class="sub-hdr-right">Amount</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Regular Pay</td>
        <td class="row-value-muted">&mdash;</td>
        <td class="row-value">P&nbsp;' . fmt($regular_pay) . '</td>
      </tr>
      ' . ($ot_pay > 0
        ? '<tr class="data-row">
             <td class="row-label">Overtime</td>
             <td class="row-value-muted" style="text-align:right;">' . $ot_minutes . ' min</td>
             <td class="row-value">P&nbsp;' . fmt($ot_pay) . '</td>
           </tr>'
        : '<tr class="data-row"><td class="row-label" style="color:#94a3b8;">Overtime</td><td></td><td class="row-value-muted">&mdash;</td></tr>'
      ) . '
    </table>

    <!-- Adjustments sub-section -->
    <table style="margin-top:4px;">
      <tr class="sub-hdr">
        <td style="width:70%;">Adjustment</td>
        <td style="width:30%;" class="sub-hdr-right">Amount</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Incentive</td>
        <td class="' . ($incentive > 0 ? 'row-value' : 'row-value-muted') . '">'
          . ($incentive > 0 ? 'P&nbsp;' . fmt($incentive) : '&mdash;') . '</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Paid Leaves</td>
        <td class="' . ($paid_leaves > 0 ? 'row-value' : 'row-value-muted') . '">'
          . ($paid_leaves > 0 ? 'P&nbsp;' . fmt($paid_leaves) : '&mdash;') . '</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Holiday Pay</td>
        <td class="' . ($holiday_pay > 0 ? 'row-value' : 'row-value-muted') . '">'
          . ($holiday_pay > 0 ? 'P&nbsp;' . fmt($holiday_pay) : '&mdash;') . '</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Others</td>
        <td class="' . ($others_adj > 0 ? 'row-value' : 'row-value-muted') . '">'
          . ($others_adj > 0 ? 'P&nbsp;' . fmt($others_adj) : '&mdash;') . '</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Training Pay</td>
        <td class="' . ($training_pay > 0 ? 'row-value' : 'row-value-muted') . '">'
          . ($training_pay > 0 ? 'P&nbsp;' . fmt($training_pay) : '&mdash;') . '</td>
      </tr>
    </table>
  </td>

  <!-- ── DEDUCTIONS PANE ── -->
  <td class="pane-ded">
    <div class="sec-hdr sec-hdr-red">Deductions</div>
    <table>
      <tr class="sub-hdr">
        <td style="width:65%;">Description</td>
        <td style="width:35%;" class="sub-hdr-right">Amount</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Withholding Tax</td>
        <td class="' . ($wh_tax > 0 ? 'row-value-ded' : 'row-value-muted') . '">'
          . ($wh_tax > 0 ? 'P&nbsp;' . fmt($wh_tax) : '&mdash;') . '</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">SSS</td>
        <td class="' . ($sss > 0 ? 'row-value-ded' : 'row-value-muted') . '">'
          . ($sss > 0 ? 'P&nbsp;' . fmt($sss) : '&mdash;') . '</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">PhilHealth</td>
        <td class="' . ($philheath > 0 ? 'row-value-ded' : 'row-value-muted') . '">'
          . ($philheath > 0 ? 'P&nbsp;' . fmt($philheath) : '&mdash;') . '</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Pag-IBIG</td>
        <td class="' . ($pagibig > 0 ? 'row-value-ded' : 'row-value-muted') . '">'
          . ($pagibig > 0 ? 'P&nbsp;' . fmt($pagibig) : '&mdash;') . '</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Tardiness</td>
        <td class="' . ($tardiness > 0 ? 'row-value-ded' : 'row-value-muted') . '">'
          . ($tardiness > 0 ? 'P&nbsp;' . fmt($tardiness) : '&mdash;') . '</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Loan</td>
        <td class="' . ($loan > 0 ? 'row-value-ded' : 'row-value-muted') . '">'
          . ($loan > 0 ? 'P&nbsp;' . fmt($loan) : '&mdash;') . '</td>
      </tr>
      <tr class="data-row">
        <td class="row-label">Others / C.A.</td>
        <td class="' . ($others_ca > 0 ? 'row-value-ded' : 'row-value-muted') . '">'
          . ($others_ca > 0 ? 'P&nbsp;' . fmt($others_ca) : '&mdash;') . '</td>
      </tr>
    </table>
  </td>

  <!-- ── SUMMARY PANE ── -->
  <td class="pane-sum" style="vertical-align:top;">
    <div class="sec-hdr sec-hdr-navy">Summary</div>

    <!-- Hours rendered -->
    <div class="hours-block">
      <span class="hours-label">Hours Rendered</span>
      <table class="hours-row">
        <tr>
          <td class="h-reg" style="text-align:right;">' . fmt($work_hours) . '</td>
          <td class="h-sep" style="text-align:center;">/</td>
          <td class="h-ot" style="text-align:left;">' . fmt($ot_hours) . ' OT</td>
        </tr>
      </table>
    </div>

    <!-- Gross / Deduction rows -->
    <table style="margin-top:4px;">
      <tr class="sum-row">
        <td class="sum-label">Gross Pay</td>
        <td class="sum-val">P&nbsp;' . fmt($gross_pay) . '</td>
      </tr>
      <tr class="sum-row">
        <td class="sum-label">Total Deductions</td>
        <td class="sum-val" style="color:#dc2626;">P&nbsp;' . fmt($total_deduct) . '</td>
      </tr>
    </table>

    <!-- Net Pay highlighted block -->
    <div class="net-block">
      <span class="net-label-t">Net Pay</span>
      <span class="net-amount">P&nbsp;' . fmt($net_pay) . '</span>
    </div>
  </td>

</tr>
</table>

<!-- ══ 4. FOOTER ══════════════════════════════════════════════════════════ -->
<table class="footer-bar">
  <tr>
    <td style="width:50%;">
      <span class="sig-label">Received By</span>
      <span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
    </td>
    <td class="divider-v" style="width:50%;">
      <span class="sig-label">Approved By</span>
      <span class="sig-name approved-name">Danver S. Reyes</span>
    </td>
  </tr>
</table>

</div>
</body>
</html>';

// ── Duplicate card (2-up: office copy + employee copy) ───────────────────────
$_card_start = strpos($html, '<div class="card">');
$_card_end   = strrpos($html, '</div>') + 6;
$_card_block = substr($html, $_card_start, $_card_end - $_card_start);
$_cut_line   = '<div style="border-top:2px dashed #94a3b8; margin:10px 0 8px 0; text-align:center; font-size:6pt; color:#94a3b8; letter-spacing:1.5px; font-family:Arial,sans-serif;">- - - - - - - - - - EMPLOYEE COPY - - - - - - - - - -</div>';
$html = str_replace('</body>', $_cut_line . $_card_block . '</body>', $html);

// ── Render via DomPDF ─────────────────────────────────────────────────────────
$_fontDir = $_projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'dompdf' . DIRECTORY_SEPARATOR . 'dompdf' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'fonts';
$options = new \Dompdf\Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('fontDir', $_fontDir);
$options->set('fontCache', $_fontDir);
$options->set('chroot', $_projectRoot);

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $p['employee_name']);
$filename  = 'Payslip_' . $safe_name . '_' . $pay_date_fmt . '.pdf';

$dompdf->stream($filename, ['Attachment' => false]);
exit;
?>
      color: #000;
      font-weight: 700;
      font-size: 9pt;
      padding: 3px 8px;
      min-width: 130px;
  }
  .status-label { font-weight: 700; color: #1a3a6e; }
  .working-hrs-header {
      font-weight: 700;
      color: #1a3a6e;
      font-size: 8pt;
      text-align: right;
      border-left: 1.5px solid #000;
      padding-right: 8px;
  }
  .working-hrs-val {
      text-align: right;
      font-size: 9pt;
      border-left: 1.5px solid #000;
      padding-right: 8px;
  }
  .working-hrs-ot {
      color: #1a3a6e;
  }

  /* ── Body columns ── */
  .body-table > tbody > tr > td {
      vertical-align: top;
      padding: 0;
  }

  /* Left pane – OT / MIN / PAY + Adjustments */
  .left-pane { width: 38%; border-right: 1.5px solid #000; }
  /* Center pane – Deductions */
  .center-pane { width: 34%; border-right: 1.5px solid #000; }
  /* Right pane – Gross/Net */
  .right-pane { width: 28%; }

  /* Column header row inside panes */
  .col-header {
      font-weight: 700;
      color: #1a3a6e;
      font-size: 8pt;
      border-bottom: 1px solid #000;
      text-align: center;
      padding: 3px 4px;
  }
  .col-header-left   { border-right: 1px solid #000; }
  .col-header-adj    { border-right: 1px solid #000; }

  /* Inner sub-tables */
  .ot-table, .adj-table, .ded-table, .summary-table {
      width: 100%;
      border-collapse: collapse;
  }
  .ot-table td, .adj-table td, .ded-table td, .summary-table td {
      padding: 2px 5px;
      font-size: 8.5pt;
  }
  .ot-table td { border-right: 1px solid #ccc; }
  .ot-table td:last-child { border-right: none; }

  /* REGULAR row */
  .regular-row .lbl {
      color: #1a3a6e;
      font-weight: 700;
  }

  /* Adjustment rows */
  .adj-label { color: #1a3a6e; font-weight: 700; }
  .adj-amount { text-align: right; }

  /* Deduction rows */
  .ded-label { color: #1a3a6e; font-weight: 700; }
  .ded-amount { text-align: right; }

  /* Right summary */
  .sum-label { color: #1a3a6e; font-weight: 700; font-size: 8.5pt; }
  .sum-value { text-align: right; font-weight: 700; font-size: 9pt; border-bottom: 1.5px solid #000; }
  .net-label { color: #1a3a6e; font-weight: 800; font-size: 10pt; }
  .net-value { text-align: right; font-weight: 800; font-size: 10pt; border-bottom: 2px solid #000; }

  /* ── Footer ── */
  .footer-row {
      border-top: 1.5px solid #000;
      padding: 5px 8px;
  }
  .footer-table td { font-size: 8.5pt; padding: 2px 8px; }
  .footer-label { font-weight: 700; color: #1a3a6e; }
  .sig-line {
      border-bottom: 1px solid #000;
      display: inline-block;
      min-width: 110px;
  }

  /* Separator between left ot block and adj block */
  .left-divider { border-top: 1px solid #000; }
</style>
</head>
<body>
<div class="payslip-wrap">

  <!-- ══ HEADER ══════════════════════════════════════════════════════════════ -->
  <table class="hdr-main">
    <tr>
      <td style="width:72%; padding:6px 10px;">
        <div class="company-name">THE BIG FIVE TRAINING AND ASSESSMENT CENTER</div>
      </td>
      <td style="width:28%; text-align:center; border-left:1.5px solid #000;">
        <div class="tb5-copy">TB5 COPY</div>
      </td>
    </tr>
  </table>

  <!-- ══ PAYSLIP LABEL + PERIOD ══════════════════════════════════════════════ -->
  <table style="border-bottom:1.5px solid #000;">
    <tr>
      <td style="width:35%; padding:4px 10px;">
        <div class="payslip-label">PAYSLIP - SEMI-MONTHLY PAYROLL</div>
      </td>
      <td style="width:65%; border-left:1.5px solid #000;">
        <table class="period-table">
          <tr>
            <td class="period-key">PERIOD :</td>
            <td><span class="period-val">' . htmlspecialchars($cutoff_str) . '</span></td>
          </tr>
          <tr>
            <td class="period-key">DATE :</td>
            <td style="padding-left:20px;">' . htmlspecialchars($pay_date_fmt) . '</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- ══ EMPLOYEE ROW ════════════════════════════════════════════════════════ -->
  <table class="emp-row">
    <tr>
      <td style="width:12%; padding:5px 8px;">
        <span class="emp-label">EMPLOYEE:</span>
      </td>
      <td style="width:30%;">
        <span class="emp-name-cell">' . htmlspecialchars($p['employee_name']) . '</span>
      </td>
      <td style="width:30%; padding:5px 8px;">
        <span class="status-label">STATUS:</span>
        &nbsp;
        <span style="text-transform:uppercase; font-size:8pt;">' . htmlspecialchars($p['status']) . '</span>
      </td>
      <td class="working-hrs-header" rowspan="3" style="width:28%; border-left:1.5px solid #000; vertical-align:top; padding-top:5px;">
        WORKING HOURS RENDERED<br>
        <span class="working-hrs-ot" style="display:block; text-align:right; margin-top:6px; font-size:9pt; font-weight:700;">'
          . fmt($ot_hours) . '</span>
        <span style="display:block; text-align:right; margin-top:2px; color:#1a3a6e; font-size:9pt; font-weight:700;">'
          . fmt($work_hours) . '</span>
      </td>
    </tr>
  </table>

  <!-- ══ MAIN BODY ═══════════════════════════════════════════════════════════ -->
  <table class="body-table">
    <tr>

      <!-- ── LEFT PANE ── -->
      <td class="left-pane">

        <!-- OT column headers -->
        <table class="ot-table">
          <tr>
            <td class="col-header col-header-left" style="width:33%;">OVERTIME</td>
            <td class="col-header col-header-left" style="width:33%;">MIN</td>
            <td class="col-header" style="width:34%;">PAY</td>
          </tr>
          <!-- REGULAR row -->
          <tr class="regular-row">
            <td class="lbl">REGULAR</td>
            <td></td>
            <td style="text-align:right;">' . fmt($regular_pay) . '</td>
          </tr>
          <!-- OT row (if any) -->
          ' . ($ot_pay > 0 ? '
          <tr>
            <td style="color:#1a3a6e; font-weight:700;">OT</td>
            <td style="text-align:center;">' . $ot_minutes . '</td>
            <td style="text-align:right;">' . fmt($ot_pay) . '</td>
          </tr>' : '<tr><td colspan="3">&nbsp;</td></tr>') . '
        </table>

        <!-- Divider + Adjustments header -->
        <table class="adj-table left-divider">
          <tr>
            <td class="col-header col-header-adj" style="width:70%;">ADJUSTMENTS</td>
            <td class="col-header" style="width:30%;">AMOUNT</td>
          </tr>
          <tr>
            <td class="adj-label">INCENTIVE</td>
            <td class="adj-amount">' . ($incentive > 0 ? fmt($incentive) : '') . '</td>
          </tr>
          <tr>
            <td class="adj-label">PAID LEAVES</td>
            <td class="adj-amount">' . ($paid_leaves > 0 ? fmt($paid_leaves) : '') . '</td>
          </tr>
          <tr>
            <td class="adj-label">HOLIDAY PAY</td>
            <td class="adj-amount">' . ($holiday_pay > 0 ? fmt($holiday_pay) : '') . '</td>
          </tr>
          <tr>
            <td class="adj-label">OTHERS</td>
            <td class="adj-amount">' . ($others_adj > 0 ? fmt($others_adj) : '') . '</td>
          </tr>
          <tr><td colspan="2">&nbsp;</td></tr>
        </table>
      </td>

      <!-- ── CENTER PANE (Deductions) ── -->
      <td class="center-pane">
        <table class="ded-table">
          <tr>
            <td class="col-header col-header-adj" style="width:65%;">DEDUCTION</td>
            <td class="col-header" style="width:35%;">AMOUNT</td>
          </tr>
          <tr>
            <td class="ded-label">W/H TAX</td>
            <td class="ded-amount">' . fmt($wh_tax) . '</td>
          </tr>
          <tr>
            <td class="ded-label">SSS</td>
            <td class="ded-amount">' . fmt($sss) . '</td>
          </tr>
          <tr>
            <td class="ded-label">PHILHEALTH</td>
            <td class="ded-amount">' . fmt($philhealth) . '</td>
          </tr>
          <tr>
            <td class="ded-label">PAG-IBIG</td>
            <td class="ded-amount">' . fmt($pagibig) . '</td>
          </tr>
          <tr>
            <td class="ded-label">TARDINESS</td>
            <td class="ded-amount">' . fmt($tardiness) . '</td>
          </tr>
          <tr>
            <td class="ded-label">LOAN</td>
            <td class="ded-amount">' . fmt($loan) . '</td>
          </tr>
          <tr>
            <td class="ded-label">OTHERS / C.A.</td>
            <td class="ded-amount">' . fmt($others_ca) . '</td>
          </tr>
          <tr><td colspan="2">&nbsp;</td></tr>
        </table>
      </td>

      <!-- ── RIGHT PANE (Summary) ── -->
      <td class="right-pane" style="vertical-align:middle; padding:8px 10px;">
        <table class="summary-table">
          <tr>
            <td class="sum-label">GROSS PAY:</td>
            <td class="sum-value">' . fmt($gross_pay) . '</td>
          </tr>
          <tr>
            <td class="sum-label">DEDUCTION :</td>
            <td class="sum-value">' . fmt($total_deduct) . '</td>
          </tr>
          <tr style="height:10px;"><td colspan="2"></td></tr>
          <tr>
            <td class="net-label">NET PAY :</td>
            <td class="net-value">' . fmt($net_pay) . '</td>
          </tr>
        </table>
      </td>

    </tr>
  </table>

  <!-- ══ FOOTER ══════════════════════════════════════════════════════════════ -->
  <table style="border-top:1.5px solid #000; width:100%;">
    <tr>
      <td style="width:50%; padding:6px 10px;">
        <span class="footer-label">RECEIVED BY:</span>
        &nbsp;&nbsp;&nbsp;
        <span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
      </td>
      <td style="width:50%; padding:6px 10px; border-left:1.5px solid #000;">
        <span style="color:#1a3a6e; font-weight:700;">APPROVED BY:</span>
        &nbsp; <span style="font-style:italic;">Danver S. Reyes</span>
      </td>
    </tr>
  </table>

</div>
</body>
</html>';


