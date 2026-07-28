<?php
require __DIR__ . '/../private/config.php';
require __DIR__ . '/lib/phpqrcode.php';

$id = (string)($_GET['m'] ?? '');
$stmt = $pdo->prepare('SELECT 1 FROM movies WHERE public_id = ?');
$stmt->execute([$id]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
QRcode::png($baseUrl . '/rate.php?m=' . $id, false, QR_ECLEVEL_M, 6, 2);
