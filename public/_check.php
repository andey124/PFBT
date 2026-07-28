<?php
// Wegwerf-Diagnose. NACH DEM TEST LOESCHEN.
header('Content-Type: text/plain; charset=utf-8');
$p = dirname(__DIR__, 3) . '/filmconfig.php';
echo "__DIR__:        " . __DIR__ . "\n";
echo "erwartet:       $p\n";
echo "file_exists:    " . var_export(file_exists($p), true) . "\n";
echo "is_readable:    " . var_export(is_readable($p), true) . "\n";
echo "realpath:       " . var_export(realpath($p), true) . "\n";
echo "DOCUMENT_ROOT:  " . ($_SERVER['DOCUMENT_ROOT'] ?? '-') . "\n";
echo "open_basedir:   " . (ini_get('open_basedir') ?: '(aus)') . "\n";
