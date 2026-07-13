<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Profile
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;

Auth::requireInvestor();

$user    = Auth::user();
$profile = db()->fetchOne(
    "SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1",
    [Auth::id()]
);

$data  = $_SESSION['form_data'] ?? array_merge($user ?? [], $profile ?? []);
unset($_SESSION['form_data']);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Profile — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .form-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px; }
        .form-card-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border); }
        .form-card-icon  { font-size:20px;margin-top:2px; }
        .form-card-title { font-size:15px;font-weight:700; }
        .form-card-sub   { font-size:12px;color:var(--muted);margin-top:2px; }

        .avatar-section {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .avatar-large {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: var(--gold-dim);
            border: 3px solid var(--gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; font-weight: 800; color: var(--gold);
            overflow: hidden; flex-shrink: 0;
        }

        .avatar-large img { width:100%;height:100%;object-fit:cover; }

        .strength-bar  { height:4px;background:var(--border);border-radius:99px;margin-top:8px;overflow:hidden; }
        .strength-fill { height:100%;border-radius:99px;transition:width .3s,background .3s; }
    </style>
</head>
<body class="investor-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-investor.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">My Profile</h1>
                <p class="page-sub">Manage your account information and password</p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="<?= APP_URL ?>/app/Actions/investor/update-profile.php"
              enctype="multipart/form-data" style="max-width:680px;">
            <?= csrfField() ?>

            <!-- Avatar + name -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">👤</span>
                    <div>
                        <div class="form-card-title">Personal Information</div>
                        <div class="form-card-sub">Your name and contact details</div>
                    </div>
                </div>

                <!-- Avatar -->
                <div class="avatar-section">
                    <div class="avatar-large" id="avatarPreview">
                        <?php if (!empty($profile['avatar_path'])): ?>
                        <img src="<?= e($profile['avatar_path']) ?>" alt="" id="avatarImg"/>
                        <?php else: ?>
                        <span id="avatarInitial">
                            <?= strtoupper(substr($user['first_name'] ?? 'I', 0, 1)) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <input type="file" name="avatar" id="avatarFile"
                               accept="image/jpeg,image/png,image/webp"
                               style="display:none;" onchange="previewAvatar(this)"/>
                        <button type="button" class="btn-secondary btn-sm"
                                onclick="document.getElementById('avatarFile').click()">
                            Change Photo
                        </button>
                        <div class="form-hint" style="margin-top:6px;">JPG, PNG or WebP · Max 2MB</div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" required
                               value="<?= e($data['first_name'] ?? '') ?>"/>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" required
                               value="<?= e($data['last_name'] ?? '') ?>"/>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" value="<?= e($user['email'] ?? '') ?>"
                               disabled style="opacity:.5;cursor:not-allowed;"/>
                        <span class="form-hint">Contact your fund manager to change your email.</span>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone"
                               value="<?= e($data['phone'] ?? '') ?>"
                               placeholder="+1 (555) 000-0000"/>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">📍</span>
                    <div>
                        <div class="form-card-title">Address</div>
                        <div class="form-card-sub">Your mailing address</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Address Line 1</label>
                    <input type="text" name="address_line1"
                           value="<?= e($data['address_line1'] ?? '') ?>"
                           placeholder="123 Main St"/>
                </div>
                <div class="form-group">
                    <label>Address Line 2</label>
                    <input type="text" name="address_line2"
                           value="<?= e($data['address_line2'] ?? '') ?>"
                           placeholder="Suite, Apt, Unit..."/>
                </div>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" value="<?= e($data['city'] ?? '') ?>"/>
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <input type="text" name="state"
                               value="<?= e($data['state'] ?? '') ?>" placeholder="TX"/>
                    </div>
                    <div class="form-group">
                        <label>ZIP Code</label>
                        <input type="text" name="zip" value="<?= e($data['zip'] ?? '') ?>"/>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Country</label>
                    <select name="country">
                        <?php foreach (['US'=>'United States','CA'=>'Canada','GB'=>'United Kingdom','AU'=>'Australia','NG'=>'Nigeria','OTHER'=>'Other'] as $code => $name): ?>
                        <option value="<?= $code ?>" <?= ($data['country'] ?? 'US') === $code ? 'selected' : '' ?>>
                            <?= $name ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Change password -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">🔐</span>
                    <div>
                        <div class="form-card-title">Change Password</div>
                        <div class="form-card-sub">Leave blank to keep your current password</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password"
                           placeholder="Enter current password to change"/>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" id="newPassword"
                               placeholder="Min 8 chars, 1 uppercase, 1 number"
                               oninput="checkStrength(this.value)"/>
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill" style="width:0%;"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="new_password_confirm"
                               placeholder="Repeat new password"/>
                    </div>
                </div>
            </div>

            <div class="flex gap-8" style="justify-content:flex-end;">
                <a href="<?= APP_URL ?>/investor/dashboard.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save Changes →</button>
            </div>

        </form>
    </div>
</div>

<script>
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('avatarPreview');
        preview.innerHTML = '<img src="' + e.target.result +
            '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"/>';
    };
    reader.readAsDataURL(input.files[0]);
}

function checkStrength(val) {
    const fill   = document.getElementById('strengthFill');
    const hasLen   = val.length >= 8;
    const hasUpper = /[A-Z]/.test(val);
    const hasNum   = /[0-9]/.test(val);
    const score    = [hasLen, hasUpper, hasNum].filter(Boolean).length;
    const states   = [
        { w: '0%',   c: '#333' },
        { w: '33%',  c: '#e53935' },
        { w: '66%',  c: '#C9922A' },
        { w: '100%', c: '#4caf50' },
    ];
    const s = val.length === 0 ? states[0] : states[score];
    fill.style.width      = s.w;
    fill.style.background = s.c;
}
</script>

</body>
</html>