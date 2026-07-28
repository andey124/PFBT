<?php
require __DIR__ . '/../src/bootstrap.php';

$movies = $pdo->query(
    'SELECT m.*, COUNT(r.id) AS rating_count, AVG(r.score) AS rating_avg
     FROM movies m LEFT JOIN ratings r ON r.movie_id = m.id
     GROUP BY m.id
     ORDER BY m.screened_on DESC, m.id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

// ponytail: laedt alle Bewertungen auf einmal, bei >paar tausend Zeilen pro Film abfragen
$byMovie = [];
foreach ($pdo->query('SELECT movie_id, name, score FROM ratings ORDER BY id') as $r) {
    $byMovie[$r['movie_id']][] = $r;
}

$open = array_filter($movies, fn($m) => $m['is_open']);
$past = array_filter($movies, fn($m) => !$m['is_open']);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Filmabend</title>
<link rel="stylesheet" href="styles.css">
</head>
<body>
<h1>Filmabend</h1>

<?php if ($open): ?>
<h2>Läuft gerade</h2>
<?php foreach ($open as $m): ?>
  <div class="card current">
    <h3><?= e($m['title']) ?> <span class="year">(<?= (int)$m['year'] ?>)</span></h3>
    <a href="rate.php?m=<?= e($m['public_id']) ?>">
      <img class="qr" src="qr.php?m=<?= e($m['public_id']) ?>" alt="QR-Code zum Bewerten">
    </a>
    <p class="hint">Scannen und bewerten – bisher <?= (int)$m['rating_count'] ?> Bewertung(en)</p>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<h2>Archiv</h2>
<?php if (!$past): ?><p class="hint">Noch nichts gelaufen.</p><?php endif; ?>
<?php foreach ($past as $m): $avg = movie_avg($m); ?>
  <div class="card">
    <?php if ($m['poster_url']): ?>
      <img class="poster" src="<?= e($m['poster_url']) ?>" alt="">
    <?php endif; ?>
    <div class="body">
      <h3><?= e($m['title']) ?> <span class="year">(<?= (int)$m['year'] ?>)</span></h3>
      <p class="meta">
        <?= e(date('d.m.Y', strtotime($m['screened_on']))) ?>
        <?= $m['director'] ? ' · ' . e($m['director']) : '' ?>
        <?= $m['genre'] ? ' · ' . e($m['genre']) : '' ?>
      </p>
      <p class="score"><strong><?= fmt_avg($avg) ?></strong> / 10
        <span class="hint"><?= (int)$m['rating_count'] ?> Bewertung(en)<?= $m['rating_count'] == 0 && $avg !== null ? ', nachgetragen' : '' ?></span>
      </p>
      <?php if ($m['note']): ?><p class="note"><?= nl2br(e($m['note'])) ?></p><?php endif; ?>
      <?php if (!empty($byMovie[$m['id']])): ?>
        <ul class="ratings">
        <?php foreach ($byMovie[$m['id']] as $r): ?>
          <li><?= e($r['name'] !== null && $r['name'] !== '' ? $r['name'] : 'Anonym') ?>: <?= (int)$r['score'] ?></li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<p class="hint"><a href="admin.php">Admin</a></p>
</body>
</html>
