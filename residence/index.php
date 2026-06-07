<?php
declare(strict_types=1);

/**
 * index.php
 * - Shared password gate (no accounts)
 * - Shows maintenance message if enabled
 */

session_start();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function getConfigPaths(): array {
  $local = __DIR__ . '/config.json';
  $parent = dirname(__DIR__) . '/config_residence.json';
  return [$parent, $local];
}

function loadConfig(): array {
  foreach (getConfigPaths() as $p) {
    if (file_exists($p)) {
      $raw = file_get_contents($p);
      $cfg = json_decode($raw ?: '', true);
      if (is_array($cfg)) {
        $cfg += [
          'setup_done' => false,
          'user_password_hash' => '',
          'maintenance_mode' => false,
        ];
        return $cfg;
      }
    }
  }
  return [
    'setup_done' => false,
    'user_password_hash' => '',
    'maintenance_mode' => false,
  ];
}

$config = loadConfig();
$contentFile = __DIR__ . '/content.html';

$errors = [];
$content = file_exists($contentFile)
  ? (string)file_get_contents($contentFile)
  : "<h2>Infos Résidence</h2><p>Le contenu n'a pas encore été publié.</p>";

function isUserAuthed(): bool {
  return isset($_SESSION['user_authed']) && $_SESSION['user_authed'] === true;
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'logout') {
  unset($_SESSION['user_authed']);
  header('Location: index.php');
  exit;
}

if (!$config['setup_done']) {
  $errors[] = "Le site n'est pas encore initialisé. Ouvrez admin.php pour terminer l'installation.";
} else {
  if (!empty($config['maintenance_mode'])) {
    $errors[] = "Le service est temporairement indisponible (maintenance).";
  } else {
    if (!isUserAuthed() && $action === 'login') {
      $pwd = (string)($_POST['user_password'] ?? '');
      if ($config['user_password_hash'] && password_verify($pwd, $config['user_password_hash'])) {
        $_SESSION['user_authed'] = true;
        session_regenerate_id(true);
        header('Location: index.php');
        exit;
      } else {
        $errors[] = "Code d'accès incorrect.";
      }
    }
  }
}

?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Infos Résidence</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 24px; max-width: 980px; }
    .box { border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
    input[type=password] { padding: 10px; border: 1px solid #ccc; border-radius: 8px; min-width: 260px; }
    button { padding: 10px 14px; border-radius: 8px; border: 1px solid #333; background: #111; color: #fff; cursor: pointer; }
    button.secondary { background: #fff; color: #111; border-color: #bbb; }
    .msg { padding: 10px; border-radius: 8px; margin-bottom: 12px; }
    .msg.err { background: #ffe7e7; border: 1px solid #ffb2b2; }
    .content img { max-width: 100%; height: auto; }
  </style>
</head>
<body>

<h1>Infos Résidence</h1>

<?php if ($errors): ?>
  <div class="msg err">
    <ul>
      <?php foreach ($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($config['setup_done'] && empty($config['maintenance_mode'])): ?>

  <?php if (!isUserAuthed()): ?>
    <div class="box">
      <h2>Accès</h2>
      <form method="post">
        <input type="hidden" name="action" value="login" />
        <label for="user_password" style="display:block; font-weight:600; margin-bottom:6px;">Code d'accès</label>
        <input type="password" id="user_password" name="user_password" required />
        <div style="margin-top:12px;">
          <button type="submit">Entrer</button>
        </div>
      </form>
    </div>
  <?php else: ?>
    <form method="post" style="margin-bottom:16px;">
      <input type="hidden" name="action" value="logout" />
      <button class="secondary" type="submit">Quitter</button>
    </form>

    <div class="box content">
      <?php echo $content; ?>
    </div>
  <?php endif; ?>

<?php endif; ?>

</body>
</html>
