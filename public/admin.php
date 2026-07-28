<?php
require __DIR__ . '/../src/bootstrap.php';

$error = null;

$locked = 0;

if (isset($_POST['password'])) {
    $locked = rl_check('admin');
    if ($locked > 0) {
        http_response_code(429);
        $error = 'Zu viele Versuche. Bitte ' . retry_msg($locked) . ' erneut probieren.';
    } elseif (password_verify((string)$_POST['password'], $adminPasswordHash)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
    } else {
        rl_hit('admin');
        $error = 'Falsches Passwort.';
        error_log('PFBT: fehlgeschlagener Admin-Login von ' . $clientIp);
        // Sperre sofort anzeigen, wenn das gerade der letzte freie Versuch war.
        $locked = rl_check('admin');
        if ($locked > 0) {
            http_response_code(429);
            $error = 'Zu viele Versuche. Bitte ' . retry_msg($locked) . ' erneut probieren.';
        }
    }
}
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
}

$admin = !empty($_SESSION['is_admin']);

if ($admin) {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    $csrf = $_SESSION['csrf'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            $error = 'Ungültiges Token, bitte neu laden.';
        } elseif ($_POST['action'] === 'create') {
            $title = trim((string)($_POST['title'] ?? ''));
            $year  = filter_var($_POST['year'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1880, 'max_range' => 2100]]);
            $date  = (string)($_POST['screened_on'] ?? '');
            $avgIn = trim((string)($_POST['legacy_avg'] ?? ''));
            $avg   = $avgIn === '' ? null : filter_var(str_replace(',', '.', $avgIn), FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 1, 'max_range' => 10]]);

            if ($title === '' || $year === false || !strtotime($date)) {
                $error = 'Titel, Jahr und Datum sind Pflicht.';
            } elseif ($avg === false) {
                $error = 'Durchschnitt muss zwischen 1 und 10 liegen.';
            } else {
                $pdo->prepare(
                    'INSERT INTO movies (public_id, title, year, screened_on, director, genre, poster_url, note, legacy_avg, is_open)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    bin2hex(random_bytes(6)),
                    mb_substr($title, 0, 200), $year, date('Y-m-d', strtotime($date)),
                    mb_substr(trim((string)($_POST['director'] ?? '')), 0, 120) ?: null,
                    mb_substr(trim((string)($_POST['genre'] ?? '')), 0, 120) ?: null,
                    valid_poster_url($_POST['poster_url'] ?? ''),
                    trim((string)($_POST['note'] ?? '')) ?: null,
                    $avg,
                    $avg === null ? 1 : 0,
                ]);
                header('Location: admin.php', true, 303); // kein Doppel-Anlegen beim Reload
                exit;
            }
        } elseif ($_POST['action'] === 'toggle') {
            $pdo->prepare('UPDATE movies SET is_open = 1 - is_open WHERE id = ?')->execute([(int)$_POST['id']]);
            header('Location: admin.php', true, 303);
            exit;
        } elseif ($_POST['action'] === 'delete') {
            $pdo->prepare('DELETE FROM movies WHERE id = ?')->execute([(int)$_POST['id']]);
            header('Location: admin.php', true, 303);
            exit;
        }
    }

    $movies = $pdo->query(
        'SELECT m.*, COUNT(r.id) AS rating_count, AVG(r.score) AS rating_avg
         FROM movies m LEFT JOIN ratings r ON r.movie_id = m.id
         GROUP BY m.id ORDER BY m.screened_on DESC, m.id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin</title>
<link rel="stylesheet" href="styles.css">
</head>
<body>
<h1>Admin</h1>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

<?php if (!$admin): ?>
  <?php if (!$locked): ?>
  <form method="post">
    <label>Passwort<input type="password" name="password" autofocus required></label>
    <button type="submit">Anmelden</button>
  </form>
  <?php endif; ?>
<?php else: ?>
  <h2>Neuer Film</h2>
  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="create">
    <label>Titel *<input type="text" name="title" required></label>
    <label>Jahr *<input type="number" name="year" min="1880" max="2100" required></label>
    <label>Gezeigt am *<input type="date" name="screened_on" value="<?= date('Y-m-d') ?>" required></label>
    <label>Regie<input type="text" name="director"></label>
    <label>Genre<input type="text" name="genre"></label>
    <label>Poster-URL<input type="url" name="poster_url"></label>
    <label>Notiz<textarea name="note" rows="2"></textarea></label>
    <label>Durchschnitt nachtragen (1–10, leer = neue Bewertungsrunde)
      <input type="text" name="legacy_avg" inputmode="decimal" placeholder="z.B. 6,5"></label>
    <button type="submit">Anlegen</button>
  </form>

  <h2>Filme</h2>
  <table>
    <tr><th>Datum</th><th>Titel</th><th>Ø</th><th>Status</th><th></th></tr>
    <?php foreach ($movies as $m): ?>
    <tr>
      <td><?= e(date('d.m.Y', strtotime($m['screened_on']))) ?></td>
      <td><?= e($m['title']) ?></td>
      <td><?= fmt_avg(movie_avg($m)) ?> <span class="hint">(<?= (int)$m['rating_count'] ?>)</span></td>
      <td><?= $m['is_open'] ? 'offen' : 'geschlossen' ?></td>
      <td class="actions">
        <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button type="submit"><?= $m['is_open'] ? 'Schliessen' : 'Öffnen' ?></button></form>
        <form method="post" data-confirm="Film und alle Bewertungen löschen?">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button type="submit" class="danger">Löschen</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <p class="hint"><a href="index.php">Übersicht</a> · <a href="?logout=1">Abmelden</a></p>
  <script src="admin.js"></script>
<?php endif; ?>
</body>
</html>
