<?php
// Adi-tehnic-debug STEP 1: Setare cale absolută către debug.log
$logFile = __DIR__ . '/wp-content/debug.log';

// Adi-tehnic-debug STEP 2: Verificare existență fișier
if (!file_exists($logFile)) {
    die("debug.log does not exist.");
}

// Adi-tehnic-debug STEP 3: Afișare conținut, protejat HTML
header('Content-Type: text/plain; charset=utf-8');
readfile($logFile);
?>
