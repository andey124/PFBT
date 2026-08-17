<?php
// php tests/test_rating.php   (Exit-Code != 0 bei Fehler)
require dirname(__DIR__) . '/src/helpers.php';

function ok($cond, $what)
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $what\n");
        exit(1);
    }
}

ok(valid_score('7') === 7, 'score 7');
ok(valid_score('1') === 1, 'score 1');
ok(valid_score('10') === 10, 'score 10');
ok(valid_score('0') === false, 'score 0 abgelehnt');
ok(valid_score('11') === false, 'score 11 abgelehnt');
ok(valid_score('7abc') === false, 'score 7abc abgelehnt');
ok(valid_score('') === false, 'leerer score abgelehnt');
ok(valid_score('7.5') === false, 'score 7.5 abgelehnt');

ok(valid_name(' Max ') === 'Max', 'name wird getrimmt');
ok(valid_name('   ') === false, 'leerer name abgelehnt');

ok(movie_avg(['rating_count' => 0, 'rating_avg' => null, 'legacy_avg' => '6.5']) === 6.5, 'fallback auf legacy_avg');
ok(movie_avg(['rating_count' => 0, 'rating_avg' => null, 'legacy_avg' => null]) === null, 'keine Wertung');
ok(movie_avg(['rating_count' => 2, 'rating_avg' => '7.5', 'legacy_avg' => '3.0']) === 7.5, 'echte Wertung schlaegt legacy');
ok(movie_avg(['rating_count' => 3, 'rating_avg' => '7.6666', 'legacy_avg' => null]) === 7.7, 'rundet auf 1 Stelle');

ok(fmt_avg(null) === '–', 'leerer Durchschnitt');
ok(fmt_avg(7.0) === '7,0', 'deutsches Komma');

ok(valid_poster_url('https://x.de/p.jpg') === 'https://x.de/p.jpg', 'https erlaubt');
ok(valid_poster_url(' http://x.de/p.jpg ') === 'http://x.de/p.jpg', 'http erlaubt, getrimmt');
ok(valid_poster_url('javascript:alert(1)') === null, 'javascript: abgelehnt');
ok(valid_poster_url('data:image/png;base64,AAAA') === null, 'data: abgelehnt');
ok(valid_poster_url('') === null, 'leere URL');
ok(valid_poster_url('kein-url') === null, 'Muell abgelehnt');

ok(retry_msg(1) === 'in 1 Sekunde', 'eine Sekunde');
ok(retry_msg(20) === 'in 20 Sekunden', 'Sekunden');
ok(retry_msg(60) === 'in 1 Minute', 'eine Minute');
ok(retry_msg(61) === 'in 2 Minuten', 'Minuten aufgerundet');

// --- Rate Limiting, SQLite im Speicher, Zeit injiziert (kein sleep) ---
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE rate_limits (id INTEGER PRIMARY KEY AUTOINCREMENT, bucket TEXT NOT NULL, created_at INT NOT NULL)');

$t = 1000000;
ok(rate_limit_retry_after($pdo, 'admin|1.2.3.4', 3, 900, $t) === 0, 'frisch = frei');

rate_limit_hit($pdo, 'admin|1.2.3.4', $t);
rate_limit_hit($pdo, 'admin|1.2.3.4', $t + 1);
ok(rate_limit_retry_after($pdo, 'admin|1.2.3.4', 3, 900, $t + 2) === 0, 'unter Limit frei');

rate_limit_hit($pdo, 'admin|1.2.3.4', $t + 2);
ok(rate_limit_retry_after($pdo, 'admin|1.2.3.4', 3, 900, $t + 3) === 897, 'am Limit gesperrt, Restzeit');
ok(rate_limit_retry_after($pdo, 'admin|1.2.3.4', 3, 900, $t + 800) === 100, 'Countdown laeuft runter');
ok(rate_limit_retry_after($pdo, 'admin|1.2.3.4', 3, 900, $t + 901) === 0, 'nach Fenster wieder frei');

ok(rate_limit_retry_after($pdo, 'admin|9.9.9.9', 3, 900, $t + 3) === 0, 'andere IP nicht betroffen');
ok(rate_limit_retry_after($pdo, 'pin|1.2.3.4', 3, 900, $t + 3) === 0, 'anderer Scope nicht betroffen');

ok($pdo->query('SELECT COUNT(*) FROM rate_limits WHERE bucket = ' . $pdo->quote(rate_limit_bucket('admin|1.2.3.4')))->fetchColumn() == 3, 'Zeilen protokolliert');
ok($pdo->query('SELECT COUNT(*) FROM rate_limits WHERE created_at = ' . $t)->fetchColumn() == 1, 'IP nicht im Klartext, Hash als bucket');

rate_limit_hit($pdo, 'admin|1.2.3.4', $t + 90000); // raeumt alles aelter als 24h weg
ok($pdo->query('SELECT COUNT(*) FROM rate_limits')->fetchColumn() == 1, 'alte Zeilen aufgeraeumt');

echo "ok\n";
