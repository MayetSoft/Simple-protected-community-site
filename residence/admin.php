<?php
declare(strict_types=1);

/**
 * admin.php
 * - First-run setup forces you to set passwords (no default passwords)
 * - Tries to store config outside web root (1 level up), fallback local
 * - Rich text editor (TinyMCE) with base64 image upload button
 * - Maintenance / read-only mode toggle
 */

session_start();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/**
 * Where to store config:
 *  - Prefer parent directory (outside this web folder)
 *  - Fallback to local directory
 */
function getConfigPaths(): array {
  $local = __DIR__ . '/config.json';
  $parent = dirname(__DIR__) . '/config_residence.json';
  return [$parent, $local];
}

function firstWritablePath(array $paths): string {
  foreach ($paths as $p) {
    $dir = dirname($p);
    if (is_dir($dir) && is_writable($dir)) return $p;
  }
  return end($paths);
}

function loadOrInitConfig(string $configPath): array {
  if (!file_exists($configPath)) {
    return [
      'setup_done' => false,
      'admin_password_hash' => '',
      'user_password_hash'  => '',
      'maintenance_mode' => false,
      'readonly_mode' => false,
      'updated_at' => null,
    ];
  }
  $raw = file_get_contents($configPath);
  $cfg = json_decode($raw ?: '', true);
  if (!is_array($cfg)) {
    die("Config invalide (JSON).");
  }
  $cfg += [
    'setup_done' => false,
    'admin_password_hash' => '',
    'user_password_hash'  => '',
    'maintenance_mode' => false,
    'readonly_mode' => false,
    'updated_at' => null,
  ];
  return $cfg;
}

function saveConfig(string $configPath, array $cfg): void {
  $cfg['updated_at'] = date('c');
  $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json === false) die("Erreur encodage JSON.");
  if (@file_put_contents($configPath, $json, LOCK_EX) === false) {
    $dir = dirname($configPath);
    die("Impossible d'écrire la config. Vérifie les droits d'écriture sur: " . h($dir));
  }
}

function isAdminLoggedIn(): bool {
  return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

$configPaths = getConfigPaths();
$configFile  = firstWritablePath($configPaths);
$config      = loadOrInitConfig($configFile);

$contentFile = __DIR__ . '/content.html';

$errors = [];
$info   = '';

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
  ini_set('session.cookie_secure', '1');
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'logout') {
  $_SESSION = [];
  session_destroy();
  header('Location: admin.php');
  exit;
}

if (!$config['setup_done']) {
  if ($action === 'setup') {
    $adminPwd = trim((string)($_POST['setup_admin_password'] ?? ''));
    $userPwd  = trim((string)($_POST['setup_user_password'] ?? ''));

    if (mb_strlen($adminPwd) < 10) $errors[] = "Mot de passe admin : minimum 10 caractères.";
    if (mb_strlen($userPwd) < 4)  $errors[] = "Mot de passe utilisateur : minimum 4 caractères.";

    if (!$errors) {
      $config['admin_password_hash'] = password_hash($adminPwd, PASSWORD_DEFAULT);
      $config['user_password_hash']  = password_hash($userPwd, PASSWORD_DEFAULT);
      $config['setup_done'] = true;
      $config['maintenance_mode'] = false;
      $config['readonly_mode'] = false;
      saveConfig($configFile, $config);

      $_SESSION['is_admin'] = true;
      session_regenerate_id(true);

      if (!file_exists($contentFile)) {
        file_put_contents($contentFile, "<h2>Infos Résidence</h2><p>Bienvenue. Modifiez cette page via l'admin.</p>", LOCK_EX);
      }

      header('Location: admin.php');
      exit;
    }
  }
} else {
  if (!isAdminLoggedIn() && $action === 'login') {
    $pwd = (string)($_POST['admin_password'] ?? '');
    if ($config['admin_password_hash'] && password_verify($pwd, $config['admin_password_hash'])) {
      $_SESSION['is_admin'] = true;
      session_regenerate_id(true);
      header('Location: admin.php');
      exit;
    } else {
      $errors[] = "Mot de passe admin incorrect.";
    }
  }

  if (isAdminLoggedIn() && $action === 'update_settings') {
    $newAdmin = trim((string)($_POST['new_admin_password'] ?? ''));
    $newUser  = trim((string)($_POST['new_user_password'] ?? ''));

    $maintenance = isset($_POST['maintenance_mode']) && $_POST['maintenance_mode'] === '1';
    $readonly    = isset($_POST['readonly_mode']) && $_POST['readonly_mode'] === '1';

    if ($newAdmin !== '') {
      if (mb_strlen($newAdmin) < 10) $errors[] = "Mot de passe admin : minimum 10 caractères.";
      else $config['admin_password_hash'] = password_hash($newAdmin, PASSWORD_DEFAULT);
    }
    if ($newUser !== '') {
      if (mb_strlen($newUser) < 4) $errors[] = "Mot de passe utilisateur : minimum 4 caractères.";
      else $config['user_password_hash'] = password_hash($newUser, PASSWORD_DEFAULT);
    }

    if (!$errors) {
      $config['maintenance_mode'] = $maintenance;
      $config['readonly_mode'] = $readonly;
      saveConfig($configFile, $config);
      $info = "Paramètres mis à jour.";
    }
  }

  if (isAdminLoggedIn() && $action === 'save_content') {
    if (!empty($config['readonly_mode'])) {
      $errors[] = "Mode lecture seule activé : enregistrement désactivé.";
    } else {
      $html = (string)($_POST['content_html'] ?? '');
      $html = str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $html);
      if (@file_put_contents($contentFile, $html, LOCK_EX) === false) {
        $errors[] = "Impossible d'écrire le contenu. Vérifie les droits d'écriture sur ce dossier.";
      } else {
        $info = "Contenu enregistré.";
      }
    }
  }
}

$currentContent = file_exists($contentFile)
  ? (string)file_get_contents($contentFile)
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
    .warn { background:#fff8e6; border:1px solid #ffe4a3; padding:10px; border-radius:8px; }
  </style>

  <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
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

<div class="warn">
  <div><b>Fichier config utilisé :</b> <code class="inline"><?=h($configFile)?></code></div>
  <div><small>Idéalement ce fichier est hors du dossier web. Sinon, protège-le avec <code class="inline">.htaccess</code>.</small></div>
</div>

<?php if (!$config['setup_done']): ?>

  <div class="box">
    <h2>Installation (1ère fois)</h2>
    <p>Choisis maintenant les 2 mots de passe. Aucun mot de passe par défaut n'est utilisé.</p>
    <form method="post">
      <input type="hidden" name="action" value="setup" />
      <div class="row">
        <div>
          <label for="setup_admin_password">Mot de passe admin (≥ 10 caractères)</label>
          <input type="password" id="setup_admin_password" name="setup_admin_password" required />
        </div>
        <div>
          <label for="setup_user_password">Mot de passe utilisateur (≥ 4 caractères)</label>
          <input type="password" id="setup_user_password" name="setup_user_password" required />
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
      <input type="hidden" name="action" value="login" />
      <label for="admin_password">Mot de passe admin</label>
      <input type="password" id="admin_password" name="admin_password" autocomplete="current-password" required />
      <div style="margin-top:12px;">
        <button type="submit">Entrer</button>
      </div>
    </form>
  </div>

<?php else: ?>

  <div class="box">
    <div class="row" style="justify-content:space-between; align-items:center;">
      <h2 style="margin:0;">Paramètres</h2>
      <form method="post" style="margin:0;">
        <input type="hidden" name="action" value="logout" />
        <button class="secondary" type="submit">Se déconnecter</button>
      </form>
    </div>

    <form method="post">
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
