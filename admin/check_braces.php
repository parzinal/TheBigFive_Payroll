<?php
// H2: Auth guard for diagnostic tool
require_once '../config/bootstrap.php';
require_once '../config/auth.php';
if (!isAuthenticated() || !isAdmin()) {
    http_response_code(403);
    die('Access Denied. Admin authentication required.');
}

// Count braces in the extractAllDataFromRows function with detailed line-by-line output
$file = file_get_contents(__DIR__ . '/import_dtr.php');
$lines = explode("\n", $file);
$openCount = 0;
$inFunction = false;

foreach ($lines as $num => $line) {
    $lineNum = $num + 1;
    if (strpos($line, 'function extractAllDataFromRows') !== false) {
        $inFunction = true;
        echo "Function starts at line $lineNum\n";
    }
    if ($inFunction) {
        $opens = substr_count($line, '{');
        $closes = substr_count($line, '}');
        $openCount += $opens - $closes;
        
        if ($opens > 0 || $closes > 0) {
            echo sprintf("Line %4d: %+2d (opens: %d, closes: %d) Total: %2d | %s\n", 
                $lineNum, $opens - $closes, $opens, $closes, $openCount, trim(substr($line, 0, 60)));
        }
        
        if ($openCount < 0) {
            echo "ERROR: Extra closing brace at line $lineNum\n";
            break;
        }
        if ($openCount == 0 && $lineNum > 540) {
            echo "\nFunction ends at line $lineNum\n";
            echo "Final brace count: $open Count\n";
            break;
        }
    }
}
if ($openCount != 0) {
    echo "\nWARNING: Unclosed braces in function. Open count: $openCount\n";
}
