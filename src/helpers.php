<?php
// Hilfsfunktionen ohne Config. Die Rate-Limit-Funktionen bekommen PDO und $now
// hereingereicht, damit sie ohne Config und ohne sleep() testbar bleiben.

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Gibt 1..10 zurueck, sonst false. */
function valid_score($v)
{
    return filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
}

/** Echte Bewertungen schlagen den nachgetragenen Durchschnitt. Null = keine Wertung. */
function movie_avg(array $m)
{
    if ((int)$m['rating_count'] > 0) {
        return round((float)$m['rating_avg'], 1);
    }
    return isset($m['legacy_avg']) && $m['legacy_avg'] !== null ? (float)$m['legacy_avg'] : null;
}

/** Nur http/https durchlassen - landet ungeprueft in <img src>. */
function valid_poster_url($url)
{
    $url = trim((string)$url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    return in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
}

function fmt_avg($avg)
{
    return $avg === null ? '–' : number_format($avg, 1, ',', '');
}

/**
 * Sekunden bis zum naechsten erlaubten Versuch, 0 = frei.
 * Zaehlt Zeilen im Fenster; gesperrt wird, bis der aelteste Versuch herausfaellt.
 */
function rate_limit_retry_after(PDO $pdo, $key, $max, $window, $now = null)
{
    $now = $now === null ? time() : (int)$now;
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS hits, MIN(created_at) AS oldest
         FROM rate_limits WHERE bucket = ? AND created_at > ?'
    );
    $stmt->execute([rate_limit_bucket($key), $now - (int)$window]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ((int)$row['hits'] < (int)$max) {
        return 0;
    }
    return max(1, (int)$row['oldest'] + (int)$window - $now);
}

/** Protokolliert einen Versuch und raeumt dabei Altlasten weg. */
function rate_limit_hit(PDO $pdo, $key, $now = null)
{
    $now = $now === null ? time() : (int)$now;
    $pdo->prepare('INSERT INTO rate_limits (bucket, created_at) VALUES (?, ?)')
        ->execute([rate_limit_bucket($key), $now]);
    // ponytail: Aufraeumen huckepack beim Schreiben, bei Last per Cron
    $pdo->prepare('DELETE FROM rate_limits WHERE created_at < ?')->execute([$now - 86400]);
}

/** Nur der Hash landet in der DB - keine IP im Klartext. */
function rate_limit_bucket($key)
{
    return hash('sha256', (string)$key);
}

/** "in 3 Minuten" / "in 20 Sekunden" - Minuten aufgerundet. */
function retry_msg($seconds)
{
    $seconds = (int)$seconds;
    if ($seconds < 60) {
        return 'in ' . $seconds . ' Sekunde' . ($seconds === 1 ? '' : 'n');
    }
    $min = (int)ceil($seconds / 60);
    return 'in ' . $min . ' Minute' . ($min === 1 ? '' : 'n');
}
