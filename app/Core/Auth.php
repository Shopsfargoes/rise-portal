<?php
// ============================================================
// RISE CAPITAL GROUP — Auth
// Session-based authentication and role enforcement
// ============================================================

namespace Rise\Core;

class Auth
{
    // Session keys
    private const KEY_USER_ID   = 'auth_user_id';
    private const KEY_USER_ROLE = 'auth_user_role';
    private const KEY_USER_NAME = 'auth_user_name';

    // Max failed login attempts before lockout
    private const MAX_ATTEMPTS    = 5;
    private const LOCKOUT_MINUTES = 15;

    // ── Login ─────────────────────────────────────────────────

    /**
     * Attempt to log in with email and password.
     * Returns true on success, string error message on failure.
     */
    public static function attempt(string $email, string $password): true|string
    {
        $email = strtolower(trim($email));
        $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // ── Rate limiting (stored in session per IP) ──────────
        $attemptsKey = 'login_attempts_' . md5($ip);
        $lockoutKey  = 'login_lockout_'  . md5($ip);

        if (!empty($_SESSION[$lockoutKey])) {
            $lockedUntil = $_SESSION[$lockoutKey];
            if (time() < $lockedUntil) {
                $remaining = ceil(($lockedUntil - time()) / 60);
                return "Too many failed attempts. Try again in {$remaining} minute(s).";
            } else {
                // Lockout expired — reset
                unset($_SESSION[$lockoutKey], $_SESSION[$attemptsKey]);
            }
        }

        // ── Fetch user ────────────────────────────────────────
        $user = db()->fetchOne(
            "SELECT u.id, u.uuid, u.email, u.password_hash, u.role, u.status,
                    p.first_name, p.last_name
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE u.email = ?
             LIMIT 1",
            [$email]
        );

        // ── Verify password ───────────────────────────────────
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION[$attemptsKey] = ($_SESSION[$attemptsKey] ?? 0) + 1;

            if ($_SESSION[$attemptsKey] >= self::MAX_ATTEMPTS) {
                $_SESSION[$lockoutKey] = time() + (self::LOCKOUT_MINUTES * 60);
                unset($_SESSION[$attemptsKey]);
                return 'Too many failed attempts. You are locked out for ' . self::LOCKOUT_MINUTES . ' minutes.';
            }

            $remaining = self::MAX_ATTEMPTS - $_SESSION[$attemptsKey];
            return "Invalid email or password. {$remaining} attempt(s) remaining.";
        }

        // ── Account status check ──────────────────────────────
        if ($user['status'] === 'pending') {
            return 'Your account is pending activation. Please check your email for the invite link.';
        }

        if ($user['status'] === 'suspended') {
            return 'Your account has been suspended. Please contact support.';
        }

        // ── Rehash if needed (PHP updates bcrypt cost) ────────
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            db()->update('users', ['password_hash' => $newHash], ['id' => $user['id']]);
        }

        // ── Clear failed attempts ─────────────────────────────
        unset($_SESSION[$attemptsKey], $_SESSION[$lockoutKey]);

        // ── Regenerate session ID (session fixation protection) ─
        session_regenerate_id(true);

        // ── Store session data ────────────────────────────────
        $_SESSION[self::KEY_USER_ID]   = $user['id'];
        $_SESSION[self::KEY_USER_ROLE] = $user['role'];
        $_SESSION[self::KEY_USER_NAME] = trim($user['first_name'] . ' ' . $user['last_name']);

        // ── Update last_login ─────────────────────────────────
        db()->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        // ── Audit log ─────────────────────────────────────────
        self::audit($user['id'], 'login', null, null, ['ip' => $ip]);

        return true;
    }

    // ── Logout ────────────────────────────────────────────────

    public static function logout(): void
    {
        $userId = self::id();
        if ($userId) {
            self::audit($userId, 'logout');
        }

        // Clear all session data
        $_SESSION = [];

        // Destroy the session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 3600,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }

    // ── Check helpers ─────────────────────────────────────────

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION[self::KEY_USER_ID]);
    }

    public static function id(): ?int
    {
        return $_SESSION[self::KEY_USER_ID] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION[self::KEY_USER_ROLE] ?? null;
    }

    public static function name(): string
    {
        return $_SESSION[self::KEY_USER_NAME] ?? 'User';
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isInvestor(): bool
    {
        return self::role() === 'investor';
    }

    // ── Full user record ──────────────────────────────────────

    /**
     * Fetch the full user + profile row for the logged-in user.
     * Cached per request in a static variable.
     */
    public static function user(): ?array
    {
        static $cache = null;

        if (!self::isLoggedIn()) return null;

        if ($cache === null) {
            $cache = db()->fetchOne(
                "SELECT u.id, u.uuid, u.email, u.role, u.status,
                        p.first_name, p.last_name, p.phone, p.avatar_path,
                        p.accredited, p.city, p.state, p.country
                 FROM users u
                 LEFT JOIN user_profiles p ON p.user_id = u.id
                 WHERE u.id = ?
                 LIMIT 1",
                [self::id()]
            );
        }

        return $cache;
    }

    // ── Route guards ──────────────────────────────────────────

    /**
     * Require the user to be logged in.
     * Redirects to login if not authenticated.
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            // Store the requested URL to redirect back after login
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
            redirect('/login.php');
        }
    }

    /**
     * Require a specific role. Redirects away if role doesn't match.
     *
     * Usage:
     *   Auth::requireRole('admin');
     *   Auth::requireRole('investor');
     */
    public static function requireRole(string $role): void
    {
        self::requireLogin();

        if (self::role() !== $role) {
            // Redirect to their own dashboard instead of a blank 403
            if (self::isAdmin()) {
                redirect('/admin/dashboard.php');
            } else {
                redirect('/investor/dashboard.php');
            }
        }
    }

    /**
     * Require admin role — shorthand.
     */
    public static function requireAdmin(): void
    {
        self::requireRole('admin');
    }

    /**
     * Require investor role — shorthand.
     */
    public static function requireInvestor(): void
    {
        self::requireRole('investor');
    }

    /**
     * Redirect logged-in users away from guest pages (like login).
     */
    public static function redirectIfLoggedIn(): void
    {
        if (!self::isLoggedIn()) return;

        if (self::isAdmin()) {
            redirect('/admin/dashboard.php');
        } else {
            redirect('/investor/dashboard.php');
        }
    }

    // ── Password utilities ────────────────────────────────────

    /**
     * Hash a plain-text password.
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Validate password strength.
     * Returns array of errors (empty = valid).
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        return $errors;
    }

    // ── Audit log ─────────────────────────────────────────────

    /**
     * Write an entry to the audit_log table.
     * Called internally; also callable from action handlers.
     */
    public static function audit(
        ?int   $userId,
        string $action,
        ?string $targetType = null,
        ?int    $targetId   = null,
        array   $meta       = []
    ): void {
        try {
            db()->insert('audit_log', [
                'user_id'     => $userId,
                'action'      => $action,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'meta'        => !empty($meta) ? json_encode($meta) : null,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Never let audit logging crash the app
        }
    }
}