<?php
// Proxy that forwards query params to export_dtr_pdf.php by including it
// This helps avoid certain URL routing issues on some local setups.

// Accept employee_id and period_id via GET
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$period_id   = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;

if ($employee_id <= 0 || $period_id <= 0) {
    http_response_code(400);
    echo 'Missing parameters';
    exit;
}

// Make sure we run from admin directory
chdir(__DIR__);

// Set globals expected by the included script
$_GET['employee_id'] = $employee_id;
$_GET['period_id']   = $period_id;

require_once __DIR__ . '/export_dtr_pdf.php';
