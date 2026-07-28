<?php
// Einziger Einstiegspunkt fuer Config, Session und Security-Header.
// Config-Pfad ueberschreibbar: SetEnv PFBT_CONFIG /pfad/zu/config.php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$configPath = getenv('PFBT_CONFIG') ?: dirname(__DIR__) . '/../private/config.php';
$configReal = realpath($configPath);
$docRoot    = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

if ($configReal === false) {
    http_response_code(500);
    error_log("PFBT: config nicht gefunden: $configPath");
    exit('Konfiguration fehlt.');
}
// Bei Installation im Unterordner (xyz.de/pftb) landet der Default-Pfad sonst
// im Docroot - die DB-Zugangsdaten waeren dann per Browser abrufbar.
if ($docRoot !== false && strpos($configReal, $docRoot . DIRECTORY_SEPARATOR) === 0) {
    http_response_code(500);
    error_log("PFBT: config liegt im Docroot ($configReal) - verschieben und PFBT_CONFIG setzen.");
    exit('Konfiguration liegt im Webroot. Bitte verschieben.');
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

// Cookie nur fuer das App-Verzeichnis, nicht fuer die ganze Domain.
// '/public' wird abgeschnitten: bei der .htaccess-Umleitung bleibt der Browser
// auf /pftb/, waehrend SCRIPT_NAME schon /pftb/public/... ist - sonst wuerde der
// Cookie nie zurueckgeschickt.
$cookiePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$cookiePath = preg_replace('#/public$#', '', $cookiePath) . '/';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $cookiePath,
    'httponly' => true,
    'secure'   => $https,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' https: data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

require $configPath;
require __DIR__ . '/helpers.php';

// X-Forwarded-For nur von bekannten Proxies akzeptieren - sonst faelscht jeder
// den Header und umgeht damit das Rate Limit.
$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
if (!empty($trustedProxies) && in_array($clientIp, (array)$trustedProxies, true)) {
    $fwd = array_filter(array_map('trim', explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))));
    if ($fwd) {
        $clientIp = (string)end($fwd);
    }
}

// Defaults, damit bestehende Configs ohne $rateLimits weiterlaufen: [Versuche, Fenster]
$rateLimits = ($rateLimits ?? []) + [
    'admin'  => [5, 900],
    'pin'    => [10, 900],
    'rating' => [5, 3600],
];

/** Sperrdauer in Sekunden fuer diesen Scope und diese IP, 0 = frei. */
function rl_check(string $scope): int
{
    global $pdo, $rateLimits, $clientIp;
    [$max, $window] = $rateLimits[$scope];
    return rate_limit_retry_after($pdo, $scope . '|' . $clientIp, $max, $window);
}

function rl_hit(string $scope): void
{
    global $pdo, $clientIp;
    rate_limit_hit($pdo, $scope . '|' . $clientIp);
}
