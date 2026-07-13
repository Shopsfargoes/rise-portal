<?php
// ============================================================
// RISE CAPITAL GROUP — Global Helpers
// ============================================================

// ── Identity & Security ───────────────────────────────────

/**
 * Generate a UUID v4 string.
 */
function uuid4(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Verify a CSRF token from a POST request.
 * Call at the top of every action handler.
 */
function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid security token. Please go back and try again.');
    }
}

/**
 * Output the CSRF hidden input field.
 * Use inside every <form>: <?= csrfField() ?>
 */
function csrfField(): string
{
    $token = htmlspecialchars($_SESSION['csrf_token'] ?? '');
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}

/**
 * Sanitize a string for safe HTML output.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Redirects & Flash Messages ────────────────────────────

/**
 * Redirect to a URL and exit.
 * Use absolute paths: redirect('/admin/dashboard.php')
 */
function redirect(string $url): never
{
    header('Location: ' . APP_URL . $url);
    exit;
}

/**
 * Store a flash message in the session.
 * Types: 'success' | 'error' | 'warning' | 'info'
 */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

/**
 * Retrieve and clear all flash messages.
 * Called once in the layout partial.
 */
function getFlash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ── Formatting ────────────────────────────────────────────

/**
 * Format a number as USD currency.
 * formatCurrency(44782000) → "$44,782,000"
 */
function formatCurrency(float $amount, string $symbol = '$', int $decimals = 0): string
{
    return $symbol . number_format($amount, $decimals);
}

/**
 * Format a currency with cents when needed.
 * formatMoney(3162521.50) → "$3,162,521.50"
 */
function formatMoney(float $amount): string
{
    return '$' . number_format($amount, 2);
}

/**
 * Human-readable file size.
 * humanFileSize(1048576) → "1.00 MB"
 */
function humanFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Time ago string.
 * timeAgo('2026-05-01 10:00:00') → "2 months ago"
 */
function timeAgo(string $datetime): string
{
    $now  = new \DateTime();
    $then = new \DateTime($datetime);
    $diff = $now->diff($then);

    if ($diff->y > 0)  return $diff->y . ' year'   . ($diff->y  > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0)  return $diff->m . ' month'  . ($diff->m  > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0)  return $diff->d . ' day'    . ($diff->d  > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0)  return $diff->h . ' hour'   . ($diff->h  > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0)  return $diff->i . ' minute' . ($diff->i  > 1 ? 's' : '') . ' ago';
    return 'just now';
}

/**
 * Format a datetime for display.
 * formatDate('2026-05-01 10:00:00') → "May 1, 2026"
 */
function formatDate(string $datetime, string $format = 'M j, Y'): string
{
    return (new \DateTime($datetime))->format($format);
}

/**
 * Format datetime with time.
 * formatDateTime('2026-05-01 10:00:00') → "May 1, 2026 at 10:00 AM"
 */
function formatDateTime(string $datetime): string
{
    return (new \DateTime($datetime))->format('M j, Y \a\t g:i A');
}

// ── String Utilities ──────────────────────────────────────

/**
 * Generate a URL slug from a string.
 * slugify('Doty Jackson Project') → "doty-jackson-project"
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Truncate a string to a given length, appending ellipsis.
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
}

/**
 * Generate a random token string (for invite links, etc.)
 */
function generateToken(int $length = 64): string
{
    return bin2hex(random_bytes($length / 2));
}

// ── Request Helpers ───────────────────────────────────────

/**
 * Get a POST value, sanitized.
 */
function post(string $key, mixed $default = ''): mixed
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/**
 * Get a GET value, sanitized.
 */
function get(string $key, mixed $default = ''): mixed
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/**
 * Check if the current request is POST.
 */
function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Return JSON response and exit. Used in AJAX action handlers.
 */
function jsonResponse(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get the current page URL path.
 */
function currentPath(): string
{
    return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
}

/**
 * Check if a nav link is the current active page.
 * Returns 'active' class string or empty.
 */
function activeNav(string $path): string
{
    return str_contains(currentPath(), $path) ? 'active' : '';
}

// ── Validation ────────────────────────────────────────────

/**
 * Validate required fields from $_POST.
 * Returns array of error messages (empty = all good).
 *
 * Usage:
 *   $errors = validateRequired(['email', 'password']);
 */
function validateRequired(array $fields): array
{
    $errors = [];
    foreach ($fields as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $label = ucfirst(str_replace('_', ' ', $field));
            $errors[] = "{$label} is required.";
        }
    }
    return $errors;
}

/**
 * Simple email format check.
 */
function isValidEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Check if a numeric value is positive.
 */
function isPositiveNumber(mixed $value): bool
{
    return is_numeric($value) && (float)$value > 0;
}