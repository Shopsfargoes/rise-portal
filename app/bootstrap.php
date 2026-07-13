<?php
// ============================================================
// RISE CAPITAL GROUP — Bootstrap
// Every public PHP file starts with: require_once __DIR__ . '/../app/bootstrap.php';
// (adjust the relative path depending on folder depth)
// ============================================================

// ── 1. Config (defines constants, loads .env) ─────────────
require_once __DIR__ . '/config.php';

// ── 2. Autoloader ─────────────────────────────────────────
// Maps namespace Rise\Core\Database → app/Core/Database.php etc.
spl_autoload_register(function (string $class): void {
    // Our namespace prefix
    $prefix = 'Rise\\';
    $baseDir = APP_PATH . '/';

    if (!str_starts_with($class, $prefix)) return;

    // Strip prefix, convert namespace separators to directory separators
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// ── 3. Composer autoloader (PHPMailer, etc.) ──────────────
$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// ── 4. Helpers ────────────────────────────────────────────
require_once APP_PATH . '/helpers.php';

// ── 5. Session ────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (APP_ENV === 'production'),  // HTTPS only in prod
        'httponly' => true,                         // No JS access
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── 6. Regenerate session ID on first load ────────────────
// Helps prevent session fixation attacks
if (empty($_SESSION['_initiated'])) {
    session_regenerate_id(true);
    $_SESSION['_initiated'] = true;
}

// ── 7. CSRF token ─────────────────────────────────────────
// Generate once per session; used by all POST forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── 8. Timezone ───────────────────────────────────────────
date_default_timezone_set('America/Chicago');

// ── 9. DB connection (lazy — only when first accessed) ────
// Just make the class available; getInstance() is called in models
use Rise\Core\Database;

// ── 10. Global shortcut ───────────────────────────────────
// Use db() anywhere instead of Database::getInstance()
function db(): Database
{
    return Database::getInstance();
}