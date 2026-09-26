<?php
declare(strict_types=1);

/**
 * index.php
 * - Shared password gate (no accounts), CSRF-protected, throttled
 * - Content is served through PHP only (content.html itself is denied by .htaccess)
 * - Shows maintenance message if enabled
 */

require __DIR__ . '/common.php';

function isUserAuthed(): bool {
  return isset($_SESSION['user_authed']) && $_SESSION['user_authed'] === true;
}

$config = loadConfig();
$errors = [];
$self   = selfUrl();

$content = file_exists(CONTENT_FILE)
  ? (string)file_get_contents(CONTENT_FILE)
  : "<p>Le contenu n'a pas encore été publié.</p>";

$action = (string)($_POST['action'] ?? '');

if ($action !== '' && !csrfCheck()) {
  $errors[] = "Jeton de sécurité invalide ou session expirée. Réessayez.";
  $action = '';
}

if ($action === 'logout') {
  unset($_SESSION['user_authed']);
  session_regenerate_id(true);
  header('Location: ' . $self);
  exit;
}

if (!$config['setup_done']) {
  $errors[] = "Le site n'est pas encore initialisé. Ouvrez la page d'administration pour terminer l'installation.";
} else {
  if (!empty($config['maintenance_mode'])) {
    $errors[] = "Le service est temporairement indisponible (maintenance).";
  } else {
    if (!isUserAuthed() && $action === 'login') {
      $wait = throttleRetryAfter();
      if ($wait > 0) {
        $errors[] = "Trop de tentatives. Réessayez dans " . ceil($wait / 60) . " minute(s).";
      } else {
        $pwd = (string)($_POST['user_password'] ?? '');
        if ($config['user_password_hash'] && password_verify($pwd, $config['user_password_hash'])) {
          throttleClear();
          $_SESSION['user_authed'] = true;
          session_regenerate_id(true);
          header('Location: ' . $self);
          exit;
        } else {
          throttleFail();
          $errors[] = "Code d'accès incorrect.";
        }
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
  <title><?=h(SITE_TITLE)?></title>
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

<h1><?=h(SITE_TITLE)?></h1>

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
        <?=csrfField()?>
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
      <?=csrfField()?>
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
