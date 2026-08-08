<?php
declare(strict_types=1);

/**
 * common.php — shared helpers for index.php / admin.php
 * Not web-accessible (denied in .htaccess).
 */

/**
 * Data directory: config + content + throttle data are stored here.
 * Default: this folder (protected by .htaccess).
 * For extra safety on shared hosting you can set an ABSOLUTE path
 * outside the web root, e.g. '/home/LOGIN/residence_data'
 * (create the folder first, writable by PHP).
 */
define('DATA_DIR', __DIR__);

define('CONFIG_FILE',   DATA_DIR . '/config.json');
define('CONTENT_FILE',  DATA_DIR . '/content.html');
define('THROTTLE_FILE', DATA_DIR . '/throttle.json');

define('ADMIN_PWD_MIN', 10);
define('USER_PWD_MIN', 8);

define('THROTTLE_MAX_FAILURES', 5);
define('THROTTLE_WINDOW', 15 * 60); // seconds

/* ---- Session hardening: must run BEFORE session_start() ---- */
ini_set('session.use_strict_mode', '1');
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => $secureCookie,
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

/* ---- Basic security headers ---- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Current script name, so admin.php keeps working if renamed. */
function selfUrl(): string {
  return basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
}

function pwdLen(string $s): int {
  return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

/* ---- Config ---- */

function configDefaults(): array {
  return [
    'setup_done' => false,
    'admin_password_hash' => '',
    'user_password_hash'  => '',
    'maintenance_mode' => false,
    'readonly_mode' => false,
    'updated_at' => null,
  ];
}

function loadConfig(): array {
  if (!file_exists(CONFIG_FILE)) {
    return configDefaults();
  }
  $raw = file_get_contents(CONFIG_FILE);
  $cfg = json_decode($raw ?: '', true);
  if (!is_array($cfg)) {
    http_response_code(500);
    die("Config invalide (JSON).");
  }
  return $cfg + configDefaults();
}

function saveConfig(array $cfg): void {
  $cfg['updated_at'] = date('c');
  $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json === false) die("Erreur encodage JSON.");
  if (@file_put_contents(CONFIG_FILE, $json, LOCK_EX) === false) {
    http_response_code(500);
    die("Impossible d'écrire la config. Vérifie les droits d'écriture sur : " . h(DATA_DIR));
  }
}

/* ---- CSRF ---- */

function csrfToken(): string {
  if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrfField(): string {
  return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '" />';
}

function csrfCheck(): bool {
  $sent = $_POST['csrf_token'] ?? '';
  return is_string($sent) && $sent !== ''
    && hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}

/* ---- Login throttling (per IP, shared between user and admin logins) ---- */

function throttleKey(): string {
  return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function throttleLoad(): array {
  if (!file_exists(THROTTLE_FILE)) return [];
  $d = json_decode((string)@file_get_contents(THROTTLE_FILE), true);
  return is_array($d) ? $d : [];
}

function throttleSave(array $d): void {
  @file_put_contents(THROTTLE_FILE, json_encode($d), LOCK_EX);
}

function throttlePrune(array $d): array {
  $now = time();
  foreach ($d as $k => $times) {
    $times = array_values(array_filter(
      is_array($times) ? $times : [],
      static fn($t) => is_int($t) && ($now - $t) < THROTTLE_WINDOW
    ));
    if ($times) $d[$k] = $times; else unset($d[$k]);
  }
  return $d;
}

/** Seconds to wait before the next attempt is allowed (0 = allowed). */
function throttleRetryAfter(): int {
  $d = throttlePrune(throttleLoad());
  $times = $d[throttleKey()] ?? [];
  if (count($times) < THROTTLE_MAX_FAILURES) return 0;
  return max(1, THROTTLE_WINDOW - (time() - min($times)));
}

function throttleFail(): void {
  $d = throttlePrune(throttleLoad());
  $d[throttleKey()][] = time();
  throttleSave($d);
}

function throttleClear(): void {
  $d = throttlePrune(throttleLoad());
  unset($d[throttleKey()]);
  throttleSave($d);
}
