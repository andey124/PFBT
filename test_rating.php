<?php
// php test_rating.php   (Exit-Code != 0 bei Fehler)
require __DIR__ . '/helpers.php';

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

ok(movie_avg(['rating_count' => 0, 'rating_avg' => null, 'legacy_avg' => '6.5']) === 6.5, 'fallback auf legacy_avg');
ok(movie_avg(['rating_count' => 0, 'rating_avg' => null, 'legacy_avg' => null]) === null, 'keine Wertung');
ok(movie_avg(['rating_count' => 2, 'rating_avg' => '7.5', 'legacy_avg' => '3.0']) === 7.5, 'echte Wertung schlaegt legacy');
ok(movie_avg(['rating_count' => 3, 'rating_avg' => '7.6666', 'legacy_avg' => null]) === 7.7, 'rundet auf 1 Stelle');

ok(fmt_avg(null) === '–', 'leerer Durchschnitt');
ok(fmt_avg(7.0) === '7,0', 'deutsches Komma');

echo "ok\n";
