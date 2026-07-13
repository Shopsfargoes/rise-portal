<?php
// ============================================================
// RISE CAPITAL GROUP — Login Page
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;

// If already logged in, send to correct dashboard
Auth::redirectIfLoggedIn();

$error = '';

// Handle form submission
if (isPost()) {
    verifyCsrf();

    $email    = post('email');
    $password = post('password');

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $result = Auth::attempt($email, $password);

        if ($result === true) {
            // Redirect to intended URL or role dashboard
            $intended = $_SESSION['intended_url'] ?? '';
            unset($_SESSION['intended_url']);

            if ($intended && str_contains($intended, '/admin/')) {
                redirect('/admin/dashboard.php');
            } else {
                redirect(Auth::isAdmin() ? '/admin/dashboard.php' : '/investor/dashboard.php');
            }
        } else {
            $error = $result; // Error message from Auth::attempt()
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Sign In — <?= APP_NAME ?></title>
    <style>
        :root {
            --gold:        #C9922A;
            --gold-light:  #E5B04A;
            --gold-dim:    #2a1f00;
            --bg:          #0a0a0a;
            --surface:     #141414;
            --surface2:    #1e1e1e;
            --border:      #2a2a2a;
            --text:        #f0f0f0;
            --muted:       #777;
            --red:         #e53935;
            --red-bg:      #2a0a0a;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Background grid pattern */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 40px 40px;
            opacity: 0.3;
            pointer-events: none;
            z-index: 0;
        }

        .login-wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
        }

        /* Logo */
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 36px;
            justify-content: center;
        }

        .logo-mark {
            width: 44px; height: 44px;
            background: var(--gold);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 22px; color: #000;
        }

        .logo-text { line-height: 1.2; }
        .logo-name { font-size: 18px; font-weight: 800; color: var(--gold); letter-spacing: 0.5px; }
        .logo-sub  { font-size: 10px; color: var(--muted); letter-spacing: 2px; text-transform: uppercase; }

        /* Card */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 36px 32px;
        }

        .card-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .card-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 28px;
        }

        /* Error alert */
        .alert-error {
            background: var(--red-bg);
            border: 1px solid var(--red);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
            color: #ff6b6b;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        /* Form */
        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            color: var(--text);
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus {
            border-color: var(--gold);
        }

        input::placeholder { color: var(--muted); }

        /* Password toggle wrapper */
        .input-wrap {
            position: relative;
        }

        .input-wrap input { padding-right: 44px; }

        .toggle-pw {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--muted); cursor: pointer;
            font-size: 16px; padding: 4px;
            transition: color 0.2s;
        }

        .toggle-pw:hover { color: var(--gold); }

        /* Submit button */
        .btn-primary {
            width: 100%;
            background: var(--gold);
            color: #000;
            border: none;
            border-radius: 8px;
            padding: 13px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-top: 8px;
            letter-spacing: 0.3px;
        }

        .btn-primary:hover  { background: var(--gold-light); }
        .btn-primary:active { transform: scale(0.99); }

        /* Footer note */
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
        }

        .login-footer a {
            color: var(--gold);
            text-decoration: none;
        }

        .login-footer a:hover { text-decoration: underline; }

        /* Ticker strip at the top (decorative) */
        .ticker {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 36px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            overflow: hidden;
            z-index: 10;
        }

        .ticker-inner {
            display: flex;
            gap: 32px;
            animation: ticker-scroll 30s linear infinite;
            white-space: nowrap;
            padding-left: 100%;
        }

        .ticker-item {
            font-size: 12px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ticker-item .up   { color: #4caf50; }
        .ticker-item .down { color: var(--red); }

        @keyframes ticker-scroll {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
    </style>
</head>
<body>

    <!-- Decorative market ticker -->
    <div class="ticker">
        <div class="ticker-inner">
            <span class="ticker-item">WTI Crude <span class="down">$89.42 ▼ –4.45%</span></span>
            <span class="ticker-item">Nat Gas <span class="up">$11.18 ▲ +2.47%</span></span>
            <span class="ticker-item">Brent Crude <span class="down">$92.94 ▼ –3.48%</span></span>
            <span class="ticker-item">Gasoline <span class="down">$106.40 ▼ –1.85%</span></span>
            <span class="ticker-item">Refiners <span class="down">$47.50 ▼ –0.81%</span></span>
            <span class="ticker-item">Exxon <span class="up">$147.90 ▲ +0.62%</span></span>
            <span class="ticker-item">WTI Crude <span class="down">$89.42 ▼ –4.45%</span></span>
            <span class="ticker-item">Nat Gas <span class="up">$11.18 ▲ +2.47%</span></span>
            <span class="ticker-item">Brent Crude <span class="down">$92.94 ▼ –3.48%</span></span>
            <span class="ticker-item">Gasoline <span class="down">$106.40 ▼ –1.85%</span></span>
        </div>
    </div>

    <div class="login-wrap" style="margin-top:36px;">

        <div class="logo">
            <div class="logo-mark">R</div>
            <div class="logo-text">
                <div class="logo-name">RISE Capital Group</div>
                <div class="logo-sub">Investor Portal</div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Welcome back</div>
            <div class="card-sub">Sign in to access your investment portal</div>

            <?php if ($error): ?>
            <div class="alert-error">
                <span>⚠</span>
                <span><?= e($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        value="<?= e(post('email')) ?>"
                        autocomplete="email"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Your password"
                            autocomplete="current-password"
                            required
                        />
                        <button type="button" class="toggle-pw" onclick="togglePassword()" title="Show/hide password">
                            👁
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Sign In →</button>
            </form>
        </div>

        <div class="login-footer">
            Access is by invitation only.<br>
            Contact your fund manager if you haven't received an invite.
        </div>

    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        // Auto-focus email field
        document.getElementById('email').focus();
    </script>
</body>
</html>