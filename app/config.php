<?php
// ============================================================
// RISE CAPITAL GROUP — Configuration
// Load environment variables from .env and define app constants
// ============================================================

// ── Load .env ───────────────────────────────────────────────
$envFile = dirname(__DIR__) . '/.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Strip surrounding quotes if present
        if (preg_match('/^"(.*)"$/', $value, $m) || preg_match("/^'(.*)'$/", $value, $m)) {
            $value = $m[1];
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// ── Helper to read .env values with a fallback ───────────────
function env(string $key, mixed $default = null): mixed {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── Database ─────────────────────────────────────────────────
define('DB_HOST',    env('DB_HOST',    'localhost'));
define('DB_PORT',    env('DB_PORT',    '3306'));
define('DB_NAME',    env('DB_NAME',    'rise_portal'));
define('DB_USER',    env('DB_USER',    'root'));
define('DB_PASS',    env('DB_PASS',    ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// ── Application ───────────────────────────────────────────────
define('APP_NAME',    env('APP_NAME',    'RISE Capital Group'));
define('APP_URL',     rtrim(env('APP_URL', 'https://rise-portal.onrender.com'), '/'));
define('APP_ENV',     env('APP_ENV',     'production'));  // 'development' | 'production'
define('APP_DEBUG',   env('APP_DEBUG',   'false') === 'true');

// ── Paths (server-side absolute) ─────────────────────────────
// On Render config.php lives at /var/www/html/app/config.php
// dirname(__DIR__) = /var/www/html = repo root
define('BASE_PATH',      dirname(__DIR__));
define('APP_PATH',       BASE_PATH . '/app');
define('PUBLIC_PATH',    BASE_PATH . '/public');
define('VIEWS_PATH',     BASE_PATH . '/views');
define('UPLOAD_PATH',    BASE_PATH . '/public/assets/uploads');    // web-accessible uploads
define('STORAGE_PATH',   BASE_PATH . '/storage');                  // private files (documents)

// ── Upload limits ─────────────────────────────────────────────
define('MAX_UPLOAD_MB',  env('MAX_UPLOAD_MB', 20));                // megabytes
define('ALLOWED_DOC_TYPES', ['application/pdf', 'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME',     env('SESSION_NAME',     'rise_session'));
define('SESSION_LIFETIME', env('SESSION_LIFETIME', 7200));          // seconds (2 hours)

// ── Mail ──────────────────────────────────────────────────────
define('MAIL_HOST',       env('MAIL_HOST',       'smtp.mailtrap.io'));
define('MAIL_PORT',       env('MAIL_PORT',       '587'));
define('MAIL_USERNAME',   env('MAIL_USERNAME',   ''));
define('MAIL_PASSWORD',   env('MAIL_PASSWORD',   ''));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls'));
define('MAIL_FROM_EMAIL', env('MAIL_FROM_EMAIL', 'noreply@risecapitalgroup.com'));
define('MAIL_FROM_NAME',  env('MAIL_FROM_NAME',  'RISE Capital Group'));

// ── Error handling ────────────────────────────────────────────
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    $logPath = BASE_PATH . '/storage/logs';
    if (!is_dir($logPath)) {
        @mkdir($logPath, 0755, true);
    }
    $logFile = is_writable($logPath)
        ? $logPath . '/error.log'
        : sys_get_temp_dir() . '/rise_error.log'; // fallback for Render
    ini_set('error_log', $logFile);
}