<?php
// Kopie als ../private/config.php ablegen (ausserhalb des Webroots!).
// Session/Header macht src/bootstrap.php - hier kein session_start().

// Hash erzeugen: php -r "echo password_hash('geheim', PASSWORD_DEFAULT);"
$adminPasswordHash = '$2y$10$BITTE-DURCH-ECHTEN-HASH-ERSETZEN';

// Lieber 6 Stellen: 4-stellig sind nur 10.000 Moeglichkeiten. Das Rate Limit
// bremst das Durchprobieren, aber die Laenge ist der eigentliche Schutz.
$ratingPin = '123456';

$baseUrl = 'https://example.com/filme'; // ohne Slash am Ende

// [erlaubte Versuche, Fenster in Sekunden] pro IP.
// 'admin' und 'pin' zaehlen nur Fehlversuche - wer die PIN kennt, wird nie gesperrt.
// 'rating' zaehlt abgegebene Bewertungen: Achtung, Gaeste im selben WLAN teilen
// sich eine IP. Bei groesseren Runden hochsetzen.
$rateLimits = [
    'admin'  => [5, 900],
    'pin'    => [10, 900],
    'rating' => [50, 3600],
];

// Nur fuellen, wenn ein Reverse-Proxy/CDN davorhaengt (bei netcup-Webspace leer
// lassen). Sonst faelscht jeder X-Forwarded-For und umgeht das Rate Limit.
$trustedProxies = [];

$pdo = new PDO(
    'mysql:host=localhost;dbname=DBNAME;charset=utf8mb4',
    'DBUSER',
    'DBPASS',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
