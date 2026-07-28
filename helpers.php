<?php
// Reine Hilfsfunktionen, keine DB, keine Config -> von test_rating.php testbar.

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

function fmt_avg($avg)
{
    return $avg === null ? '–' : number_format($avg, 1, ',', '');
}
