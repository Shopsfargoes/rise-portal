<?php
// ============================================================
// RISE CAPITAL GROUP — Accept Invite
// Investor lands here from their email invite link.
// They set their password and activate their account.
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;

// Already logged in — go to dashboard
Auth::redirectIfLoggedIn();

$token  = trim(get('token'));
$error  = '';
$user   = null;
$done   = false;

// ── Validate token ────────────────────────────────────────
if (empty($token)) {
    $error = 'Invalid or missing invite link. Please contact your fund manager.';
} else {
    $user = db()->fetchOne(
        "SELECT u.id, u.email, u.status, u.invite_expires,
                p.first_name, p.last_name
         FROM users u
         LEFT JOIN user_profiles p ON p.user_id = u.id
         WHERE u.invite_token = ?
         LIMIT 1",
        [$token]
    );

    if (!$user) {
        $error = 'This invite link is invalid. It may have already been used.';
    } elseif ($user['status'] === 'active') {
        $error = 'This invite has already been accepted. Please sign in.';
    } elseif (strtotime($user['invite_expires']) < time()) {
        $error = 'This invite link has expired (links are valid for 48 hours). Please ask your fund manager to resend the invite.';
    }
}

// ── Handle password setup form ────────────────────────────
if (isPost() && $user && empty($error)) {
    verifyCsrf();

    $password        = post('password');
    $passwordConfirm = post('password_confirm');

    $errors = Auth::validatePassword($password);

    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            db()->transaction(function() use ($user, $password) {
                $now = date('Y-m-d H:i:s');

                // Activate the account and set password
                db()->update('users', [
                    'password_hash'  => Auth::hashPassword($password),
                    'status'         => 'active',
                    'invite_token'   => null,    // invalidate token
                    'invite_expires' => null,
                    'updated_at'     => $now,
                ], ['id' => $user['id']]);

                // Audit
                Auth::audit($user['id'], 'invite_accepted', 'user', $user['id']);
            });

            $done = true;

        } catch (\Throwable $e) {
            error_log('accept-invite error: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again or contact support.';
        }
    } else {
        $error = implode(' ', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Accept Invitation — <?= APP_NAME ?></title>
    <style>
        :root {
            --gold:       #C9922A;
            --gold-light: #E5B04A;
            --bg:         #0a0a0a;
            --surface:    #141414;
            --surface2:   #1e1e1e;
            --border:     #2a2a2a;
            --text:       #f0f0f0;
            --muted:      #777;
            --green:      #4caf50;
            --green-bg:   #0a2a10;
            --red:        #e53935;
            --red-bg:     #2a0a0a;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

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
        }

        .wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
            justify-content: center;
        }

        .logo-mark {
            width: 44px; height: 44px;
            background: var(--gold);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 22px; color: #000;
        }

        .logo-name { font-size: 18px; font-weight: 800; color: var(--gold); }
        .logo-sub  { font-size: 10px; color: var(--muted); letter-spacing: 2px; text-transform: uppercase; }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 36px 32px;
        }

        /* Success state */
        .success-icon {
            width: 64px; height: 64px;
            background: var(--green-bg);
            border: 2px solid var(--green);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
        }

        .card-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .card-sub   { font-size: 13px; color: var(--muted); margin-bottom: 26px; }

        /* Greeting badge */
        .greeting {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #2a1f00;
            border: 1px solid #3a2a00;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 22px;
            font-size: 13px;
            color: var(--gold-light);
            width: 100%;
        }

        /* Alert */
        .alert-error {
            background: var(--red-bg);
            border: 1px solid var(--red);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
            color: #ff6b6b;
            margin-bottom: 20px;
        }

        /* Form */
        .form-group { margin-bottom: 18px; }

        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        input[type="password"] {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 44px 12px 14px;
            font-size: 14px;
            color: var(--text);
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus { border-color: var(--gold); }

        .input-wrap { position: relative; }

        .toggle-pw {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--muted); cursor: pointer;
            font-size: 16px;
            transition: color 0.2s;
        }

        .toggle-pw:hover { color: var(--gold); }

        /* Password strength */
        .strength-bar {
            height: 4px;
            background: var(--border);
            border-radius: 99px;
            margin-top: 8px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            border-radius: 99px;
            transition: width 0.3s, background 0.3s;
            width: 0%;
        }

        .strength-label {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }

        /* Rules checklist */
        .pw-rules {
            list-style: none;
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .pw-rules li {
            font-size: 12px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .pw-rules li.pass { color: var(--green); }
        .pw-rules li .dot { font-size: 10px; }

        /* Submit */
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
            transition: background 0.2s;
            margin-top: 8px;
        }

        .btn-primary:hover    { background: var(--gold-light); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-login {
            display: block;
            text-align: center;
            margin-top: 16px;
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .btn-login:hover { border-color: var(--gold); color: var(--gold); }
    </style>
</head>
<body>

<div class="wrap">
    <div class="logo">
        <div class="logo-mark">R</div>
        <div>
            <div class="logo-name">RISE Capital Group</div>
            <div class="logo-sub">Investor Portal</div>
        </div>
    </div>

    <div class="card">

        <?php if ($done): ?>
            <!-- ── Success State ── -->
            <div style="text-align:center;">
                <div class="success-icon">✓</div>
                <div class="card-title" style="text-align:center;">You're all set!</div>
                <p style="color:var(--muted); font-size:13px; margin:10px 0 28px;">
                    Your account is now active. Sign in to access your investment portal.
                </p>
                <a href="<?= APP_URL ?>/login.php" class="btn-login">
                    Sign In to Your Portal →
                </a>
            </div>

        <?php elseif ($error && !$user): ?>
            <!-- ── Invalid / Expired Token ── -->
            <div class="card-title">Invalid Invite Link</div>
            <p class="card-sub">This link cannot be used.</p>
            <div class="alert-error">⚠ <?= e($error) ?></div>
            <p style="font-size:13px; color:var(--muted); line-height:1.6;">
                Please contact your fund manager to request a new invitation link.
            </p>

        <?php else: ?>
            <!-- ── Password Setup Form ── -->
            <div class="card-title">Accept Your Invitation</div>
            <p class="card-sub">Set a password to activate your account.</p>

            <?php if ($user): ?>
            <div class="greeting">
                👋 Welcome, <strong><?= e($user['first_name']) ?> <?= e($user['last_name']) ?></strong>
                &nbsp;·&nbsp; <?= e($user['email']) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert-error">⚠ <?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="inviteForm">
                <?= csrfField() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>"/>

                <div class="form-group">
                    <label for="password">Create Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password"
                               placeholder="Minimum 8 characters"
                               oninput="checkStrength(this.value)"
                               autocomplete="new-password" required/>
                        <button type="button" class="toggle-pw" onclick="togglePw('password')">👁</button>
                    </div>
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel">Enter a password</div>
                    <ul class="pw-rules" id="pwRules">
                        <li id="rule-len"><span class="dot">○</span> At least 8 characters</li>
                        <li id="rule-upper"><span class="dot">○</span> One uppercase letter</li>
                        <li id="rule-num"><span class="dot">○</span> One number</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password_confirm" name="password_confirm"
                               placeholder="Repeat your password"
                               autocomplete="new-password" required/>
                        <button type="button" class="toggle-pw" onclick="togglePw('password_confirm')">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">
                    Activate My Account →
                </button>
            </form>

        <?php endif; ?>
    </div>
</div>

<script>
function togglePw(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}

function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    const ruleLen   = document.getElementById('rule-len');
    const ruleUpper = document.getElementById('rule-upper');
    const ruleNum   = document.getElementById('rule-num');

    const hasLen   = val.length >= 8;
    const hasUpper = /[A-Z]/.test(val);
    const hasNum   = /[0-9]/.test(val);

    // Update rule indicators
    ruleLen.className   = hasLen   ? 'pass' : '';
    ruleUpper.className = hasUpper ? 'pass' : '';
    ruleNum.className   = hasNum   ? 'pass' : '';

    ruleLen.querySelector('.dot').textContent   = hasLen   ? '●' : '○';
    ruleUpper.querySelector('.dot').textContent = hasUpper ? '●' : '○';
    ruleNum.querySelector('.dot').textContent   = hasNum   ? '●' : '○';

    // Score
    const score = [hasLen, hasUpper, hasNum].filter(Boolean).length;

    const states = [
        { width: '0%',   color: '#333',    text: 'Enter a password' },
        { width: '33%',  color: '#e53935', text: 'Weak' },
        { width: '66%',  color: '#C9922A', text: 'Getting stronger' },
        { width: '100%', color: '#4caf50', text: 'Strong ✓' },
    ];

    const s = val.length === 0 ? states[0] : states[score];
    fill.style.width      = s.width;
    fill.style.background = s.color;
    label.textContent     = s.text;
    label.style.color     = s.color;
}
</script>

</body>
</html>