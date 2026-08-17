<?php
require __DIR__ . '/../src/bootstrap.php';

$id = (string)($_GET['m'] ?? '');
$stmt = $pdo->prepare('SELECT id, title, year, is_open FROM movies WHERE public_id = ?');
$stmt->execute([$id]);
$movie = $stmt->fetch(PDO::FETCH_ASSOC);

$error  = null;
$done   = false;
$locked = 0;
$cookie = 'rated_' . preg_replace('/[^a-f0-9]/', '', $id);

if ($movie && $movie['is_open']) {
    if (isset($_POST['pin'])) {
        $locked = rl_check('pin');
        if ($locked > 0) {
            http_response_code(429);
            $error = 'Zu viele Fehlversuche. Bitte ' . retry_msg($locked) . ' erneut probieren.';
        } elseif (hash_equals((string)$ratingPin, (string)$_POST['pin'])) {
            session_regenerate_id(true);
            $_SESSION['pin_ok'] = true;
        } else {
            rl_hit('pin');
            $error = 'Falsche PIN.';
            $locked = rl_check('pin');
            if ($locked > 0) {
                http_response_code(429);
                $error = 'Zu viele Fehlversuche. Bitte ' . retry_msg($locked) . ' erneut probieren.';
            }
        }
    } elseif (isset($_POST['score'])) {
        $score = valid_score($_POST['score']);
        $locked = rl_check('rating');
        if (empty($_SESSION['pin_ok'])) {
            $error = 'Bitte zuerst die PIN eingeben.';
        } elseif (isset($_COOKIE[$cookie])) {
            $error = 'Du hast diesen Film schon bewertet.';
        } elseif ($locked > 0) {
            http_response_code(429);
            $error = 'Zu viele Bewertungen aus diesem Netz. Bitte ' . retry_msg($locked) . ' erneut probieren.';
        } elseif ($score === false) {
            $error = 'Bitte eine Zahl von 1 bis 10 wählen.';
        } else {
            $name = valid_name($_POST['name'] ?? '');
            if ($name === false) {
                $error = 'Bitte einen Namen eingeben.';
            } else {
                $pdo->prepare('INSERT INTO ratings (movie_id, name, score) VALUES (?, ?, ?)')
                    ->execute([$movie['id'], $name, $score]);
                rl_hit('rating');
                setcookie($cookie, '1', [
                    'expires'  => time() + 31536000,
                    'path'     => $cookiePath,
                    'httponly' => true,
                    'secure'   => $https,
                    'samesite' => 'Lax',
                ]);
                $done = true;
            }
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bewerten</title>
<link rel="stylesheet" href="styles.css">
</head>
<body>
<?php if (!$movie): ?>
  <h1>Film nicht gefunden</h1>
<?php elseif (!$movie['is_open']): ?>
  <h1><?= e($movie['title']) ?></h1>
  <p>Die Bewertung ist geschlossen.</p>
  <p><a href="index.php">Zur Übersicht</a></p>
<?php else: ?>
  <h1><?= e($movie['title']) ?> <span class="year">(<?= (int)$movie['year'] ?>)</span></h1>

  <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

  <?php if ($done): ?>
    <p class="ok">Danke! Deine Bewertung ist gespeichert.</p>
    <p><a href="index.php">Zur Übersicht</a></p>
  <?php elseif (isset($_COOKIE[$cookie])): ?>
    <p>Du hast diesen Film schon bewertet.</p>
    <p><a href="index.php">Zur Übersicht</a></p>
  <?php elseif ($locked): ?>
    <p><a href="index.php">Zur Übersicht</a></p>
  <?php elseif (empty($_SESSION['pin_ok'])): ?>
    <form method="post">
      <label>PIN<input type="text" name="pin" inputmode="numeric" autocomplete="off" autofocus required></label>
      <button type="submit">Weiter</button>
    </form>
  <?php else: ?>
    <form method="post">
      <label>Name<input type="text" name="name" maxlength="60" required></label>
      <label>Bewertung
        <select name="score" required>
          <option value="">–</option>
          <?php for ($i = 10; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
        </select>
      </label>
      <button type="submit">Abschicken</button>
    </form>
  <?php endif; ?>
<?php endif; ?>
</body>
</html>
