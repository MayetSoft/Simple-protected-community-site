<?php
declare(strict_types=1);

/**
 * admin.php
 * - First-run setup forces you to set passwords (no default passwords)
 * - CSRF-protected actions, login throttling
 * - Rich text editor (TinyMCE) with base64 image upload button
 * - Maintenance / read-only mode toggle
 *
 * This file can be renamed (e.g. gestion-3fA9kP.php): redirects follow
 * the current script name automatically.
 */

require __DIR__ . '/common.php';

function isAdminLoggedIn(): bool {
  return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

$config = loadConfig();
$errors = [];
$info   = '';
$self   = selfUrl();

// Post/Redirect/Get success messages
$okMessages = [
  'settings' => "Paramètres mis à jour.",
  'content'  => "Contenu enregistré.",
];
$ok = (string)($_GET['ok'] ?? '');
if (isset($okMessages[$ok])) $info = $okMessages[$ok];

$action = (string)($_POST['action'] ?? '');

/** All POST actions require a valid CSRF token. */
if ($action !== '' && !csrfCheck()) {
  $errors[] = "Jeton de sécurité invalide ou session expirée. Réessaie.";
  $action = '';
}

/** Logout */
if ($action === 'logout') {
  $_SESSION = [];
  session_destroy();
  header('Location: ' . $self);
  exit;
}

/** First run setup: force password set */
if (!$config['setup_done']) {
  if ($action === 'setup') {
    $adminPwd  = trim((string)($_POST['setup_admin_password'] ?? ''));
    $adminPwd2 = trim((string)($_POST['setup_admin_password2'] ?? ''));
    $userPwd   = trim((string)($_POST['setup_user_password'] ?? ''));
    $userPwd2  = trim((string)($_POST['setup_user_password2'] ?? ''));

    if (pwdLen($adminPwd) < ADMIN_PWD_MIN) $errors[] = "Mot de passe admin : minimum " . ADMIN_PWD_MIN . " caractères.";
    if ($adminPwd !== $adminPwd2) $errors[] = "Les deux saisies du mot de passe admin ne correspondent pas.";
    if (pwdLen($userPwd) < USER_PWD_MIN) $errors[] = "Mot de passe utilisateur : minimum " . USER_PWD_MIN . " caractères.";
    if ($userPwd !== $userPwd2) $errors[] = "Les deux saisies du mot de passe utilisateur ne correspondent pas.";

    if (!$errors) {
      $config['admin_password_hash'] = password_hash($adminPwd, PASSWORD_DEFAULT);
      $config['user_password_hash']  = password_hash($userPwd, PASSWORD_DEFAULT);
      $config['setup_done'] = true;
      $config['maintenance_mode'] = false;
      $config['readonly_mode'] = false;
      saveConfig($config);

      $_SESSION['is_admin'] = true;
      session_regenerate_id(true);

      if (!file_exists(CONTENT_FILE)) {
        file_put_contents(CONTENT_FILE, "<h2>Infos Résidence</h2><p>Bienvenue. Modifiez cette page via l'admin.</p>", LOCK_EX);
      }

      header('Location: ' . $self);
      exit;
    }
  }
} else {
  /** Admin login gate (throttled) */
  if (!isAdminLoggedIn() && $action === 'login') {
    $wait = throttleRetryAfter();
    if ($wait > 0) {
      $errors[] = "Trop de tentatives. Réessaie dans " . ceil($wait / 60) . " minute(s).";
    } else {
      $pwd = (string)($_POST['admin_password'] ?? '');
      if ($config['admin_password_hash'] && password_verify($pwd, $config['admin_password_hash'])) {
        throttleClear();
        $_SESSION['is_admin'] = true;
        session_regenerate_id(true);
        header('Location: ' . $self);
        exit;
      } else {
        throttleFail();
        $errors[] = "Mot de passe admin incorrect.";
      }
    }
  }

  /** Change passwords / toggles */
  if (isAdminLoggedIn() && $action === 'update_settings') {
    $newAdmin = trim((string)($_POST['new_admin_password'] ?? ''));
    $newUser  = trim((string)($_POST['new_user_password'] ?? ''));

    $maintenance = isset($_POST['maintenance_mode']) && $_POST['maintenance_mode'] === '1';
    $readonly    = isset($_POST['readonly_mode']) && $_POST['readonly_mode'] === '1';

    if ($newAdmin !== '') {
      if (pwdLen($newAdmin) < ADMIN_PWD_MIN) $errors[] = "Mot de passe admin : minimum " . ADMIN_PWD_MIN . " caractères.";
      else $config['admin_password_hash'] = password_hash($newAdmin, PASSWORD_DEFAULT);
    }
    if ($newUser !== '') {
      if (pwdLen($newUser) < USER_PWD_MIN) $errors[] = "Mot de passe utilisateur : minimum " . USER_PWD_MIN . " caractères.";
      else $config['user_password_hash'] = password_hash($newUser, PASSWORD_DEFAULT);
    }

    if (!$errors) {
      $config['maintenance_mode'] = $maintenance;
      $config['readonly_mode'] = $readonly;
      saveConfig($config);
      header('Location: ' . $self . '?ok=settings');
      exit;
    }
  }

  /** Save content */
  if (isAdminLoggedIn() && $action === 'save_content') {
    if (!empty($config['readonly_mode'])) {
      $errors[] = "Mode lecture seule activé : enregistrement désactivé.";
    } else {
      $html = (string)($_POST['content_html'] ?? '');
      // Defense in depth: never store PHP tags in content
      $html = str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $html);
      if (@file_put_contents(CONTENT_FILE, $html, LOCK_EX) === false) {
        $errors[] = "Impossible d'écrire le contenu. Vérifie les droits d'écriture sur ce dossier.";
      } else {
        header('Location: ' . $self . '?ok=content');
        exit;
      }
    }
  }
}

// Load current content
$currentContent = file_exists(CONTENT_FILE)
  ? (string)file_get_contents(CONTENT_FILE)
  : "<h2>Infos Résidence</h2><p>Écrivez ici…</p>";

?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin — Infos Résidence</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 24px; max-width: 980px; }
    .box { border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
    .row { display: flex; gap: 12px; flex-wrap: wrap; }
    label { display:block; font-weight: 600; margin-bottom: 6px; }
    input[type=password] { padding: 10px; border: 1px solid #ccc; border-radius: 8px; min-width: 280px; }
    button { padding: 10px 14px; border-radius: 8px; border: 1px solid #333; background: #111; color: #fff; cursor: pointer; }
    button.secondary { background: #fff; color: #111; border-color: #bbb; }
    .msg { padding: 10px; border-radius: 8px; margin-bottom: 12px; }
    .msg.err { background: #ffe7e7; border: 1px solid #ffb2b2; }
    .msg.ok  { background: #e8ffee; border: 1px solid #b6f2c3; }
    code.inline { background:#f4f4f4; padding:2px 6px; border-radius:6px; }
    small { color:#666; }
    .warn { background:#fff8e6; border:1px solid #ffe4a3; padding:10px; border-radius:8px; margin-bottom:16px; }
  </style>

  <?php if ($config['setup_done'] && isAdminLoggedIn()): ?>
  <!-- TinyMCE (CDN, version pinned) -->
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
  <?php endif; ?>
</head>
<body>

<h1>Admin — Infos Résidence</h1>

<?php if ($errors): ?>
  <div class="msg err">
    <ul>
      <?php foreach ($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($info): ?>
  <div class="msg ok"><?=h($info)?></div>
<?php endif; ?>

<?php if (!$config['setup_done']): ?>

  <div class="box">
    <h2>Installation (1ère fois)</h2>
    <p>Choisis maintenant les 2 mots de passe. Aucun mot de passe par défaut n'est utilisé.</p>
    <form method="post">
      <?=csrfField()?>
      <input type="hidden" name="action" value="setup" />
      <div class="row">
        <div>
          <label for="setup_admin_password">Mot de passe admin (≥ <?=ADMIN_PWD_MIN?> caractères)</label>
          <input type="password" id="setup_admin_password" name="setup_admin_password" required />
        </div>
        <div>
          <label for="setup_admin_password2">Confirmer le mot de passe admin</label>
          <input type="password" id="setup_admin_password2" name="setup_admin_password2" required />
        </div>
      </div>
      <div class="row" style="margin-top:12px;">
        <div>
          <label for="setup_user_password">Mot de passe utilisateur (≥ <?=USER_PWD_MIN?> caractères)</label>
          <input type="password" id="setup_user_password" name="setup_user_password" required />
        </div>
        <div>
          <label for="setup_user_password2">Confirmer le mot de passe utilisateur</label>
          <input type="password" id="setup_user_password2" name="setup_user_password2" required />
        </div>
      </div>
      <div style="margin-top:12px;">
        <button type="submit">Initialiser</button>
      </div>
      <p><small>Si tu as une erreur de droits d'écriture, il faudra ajuster les permissions côté hébergeur.</small></p>
    </form>
  </div>

<?php elseif (!isAdminLoggedIn()): ?>

  <div class="box">
    <h2>Accès admin</h2>
    <form method="post">
      <?=csrfField()?>
      <input type="hidden" name="action" value="login" />
      <label for="admin_password">Mot de passe admin</label>
      <input type="password" id="admin_password" name="admin_password" autocomplete="current-password" required />
      <div style="margin-top:12px;">
        <button type="submit">Entrer</button>
      </div>
    </form>
  </div>

<?php else: ?>

  <div class="warn">
    <div><b>Dossier de données :</b> <code class="inline"><?=h(DATA_DIR)?></code></div>
    <div><small>Les fichiers <code class="inline">config.json</code> et <code class="inline">content.html</code> y sont stockés, protégés par <code class="inline">.htaccess</code>. Pour plus de sûreté, ce dossier peut être déplacé hors du web (voir README).</small></div>
  </div>

  <div class="box">
    <div class="row" style="justify-content:space-between; align-items:center;">
      <h2 style="margin:0;">Paramètres</h2>
      <form method="post" style="margin:0;">
        <?=csrfField()?>
        <input type="hidden" name="action" value="logout" />
        <button class="secondary" type="submit">Se déconnecter</button>
      </form>
    </div>

    <form method="post">
      <?=csrfField()?>
      <input type="hidden" name="action" value="update_settings" />

      <div class="row">
        <div>
          <label for="new_admin_password">Nouveau mot de passe admin (laisser vide = inchangé)</label>
          <input type="password" id="new_admin_password" name="new_admin_password" autocomplete="new-password" />
        </div>
        <div>
          <label for="new_user_password">Nouveau mot de passe utilisateur (laisser vide = inchangé)</label>
          <input type="password" id="new_user_password" name="new_user_password" autocomplete="new-password" />
        </div>
      </div>

      <div class="row" style="margin-top:12px; align-items:center;">
        <label style="display:flex; gap:10px; align-items:center; font-weight:600; margin:0;">
          <input type="checkbox" name="maintenance_mode" value="1" <?= !empty($config['maintenance_mode']) ? 'checked' : '' ?> />
          Mode maintenance (côté utilisateur : message "indisponible")
        </label>

        <label style="display:flex; gap:10px; align-items:center; font-weight:600; margin:0;">
          <input type="checkbox" name="readonly_mode" value="1" <?= !empty($config['readonly_mode']) ? 'checked' : '' ?> />
          Mode lecture seule (bloque l'enregistrement du contenu)
        </label>
      </div>

      <div style="margin-top:12px;">
        <button type="submit">Enregistrer paramètres</button>
        <a class="secondary"
           style="margin-left:10px; display:inline-block; padding:10px 14px; border:1px solid #bbb; border-radius:8px; text-decoration:none; color:#111;"
           href="index.php" target="_blank">Voir la page utilisateur</a>
      </div>
    </form>
  </div>

  <div class="box">
    <h2>Contenu</h2>
    <p><small>Utilise "Téléverser image (base64)" pour embarquer une image directement dans la page (pas de fichier à gérer).</small></p>

    <?php if (!empty($config['readonly_mode'])): ?>
      <div class="warn"><b>Lecture seule :</b> l'édition est possible mais l'enregistrement est désactivé.</div>
    <?php endif; ?>

    <form method="post">
      <?=csrfField()?>
      <input type="hidden" name="action" value="save_content" />
      <textarea id="editor" name="content_html"><?=h($currentContent)?></textarea>
      <div style="margin-top:12px;">
        <button type="submit" <?= !empty($config['readonly_mode']) ? 'disabled' : '' ?>>Enregistrer</button>
      </div>
    </form>
  </div>

  <script>
    tinymce.init({
      selector: '#editor',
      height: 560,
      menubar: true,
      plugins: 'lists link table code',
      toolbar: [
        'undo redo | styles | bold italic underline | alignleft aligncenter alignright | bullist numlist | link table | code | base64image'
      ].join(' '),
      setup: function (editor) {
        editor.ui.registry.addButton('base64image', {
          text: 'Téléverser image (base64)',
          onAction: function () {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = () => {
              const file = input.files && input.files[0];
              if (!file) return;

              const maxBytes = 2 * 1024 * 1024;
              if (file.size > maxBytes) {
                alert("Image trop lourde (> 2MB). Réduis-la avant (ou compresse).");
                return;
              }

              const reader = new FileReader();
              reader.onload = () => {
                const dataUrl = reader.result;
                editor.insertContent('<img src="' + dataUrl + '" alt="" style="max-width:100%; height:auto;" />');
              };
              reader.readAsDataURL(file);
            };
            input.click();
          }
        });
      }
    });
  </script>

<?php endif; ?>

</body>
</html>
