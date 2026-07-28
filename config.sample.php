<?php
// Kopie als ../private/config.php ablegen (ausserhalb des Webroots!).
session_start();

$adminPassword = 'BITTE-AENDERN';
$ratingPin     = '1234';
$baseUrl       = 'https://example.com/filme'; // ohne Slash am Ende

$pdo = new PDO(
    'mysql:host=localhost;dbname=DBNAME;charset=utf8mb4',
    'DBUSER',
    'DBPASS',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
