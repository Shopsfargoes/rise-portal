<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Edit Investor
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;

Auth::requireAdmin();

$id = (int) get('id');
if (!$id) redirect('/admin/users/index.php');

// ── Fetch investor ────────────────────────────────────────
$investor = db()->fetchOne(
    "SELECT u.id, u.email, u.status, u.role,
            p.first_name, p.last_name, p.phone,
            p.address_line1, p.address_line2,
            p.city, p.state, p.zip, p.country,
            p.accredited, p.accredited_at,
            p.avatar_path, p.notes
     FROM users u
     LEFT JOIN user_profiles p ON p.user_id = u.id
     WHERE u.id = ? LIMIT 1",
    [$id]
);

if (!$investor) redirect('/admin/users/index.php');

// Repopulate with session data if returning after error
$data = $_SESSION['form_data'] ?? $investor;
unset($_SESSION['form_data']);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit <?= e($investor['first_name'] . ' ' . $investor['last_name']) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <a href="<?= APP_URL ?>/admin/users/view.php?id=<?= $id ?>" class="back-link">
                    ← Back to <?= e($investor['first_name']) ?>
                </a>
                <h1 class="page-title">Edit Investor</h1>
                <p class="page-sub"><?= e($investor['first_name'] . ' ' . $investor['last_name']) ?> · <?= e($investor['email']) ?></p>
            </div>
        </div>

        <!-- Flash messages -->
        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="<?= APP_URL ?>/app/Actions/admin/update-investor.php"
              enctype="multipart/form-data" style="max-width:720px;">
            <?= csrfField() ?>
            <input type="hidden" name="user_id" value="<?= $id ?>"/>

            <!-- ── Personal Info ── -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">👤</span>
                    <div>
                        <div class="form-card-title">Personal Information</div>
                        <div class="form-card-sub">Basic profile details</div>
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
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" required
                               value="<?= e($data['email'] ?? '') ?>"/>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone"
                               value="<?= e($data['phone'] ?? '') ?>"/>
                    </div>
                </div>

                <!-- Avatar upload -->
                <div class="form-group">
                    <label>Profile Photo</label>
                    <div class="flex-center gap-12">
                        <div class="avatar avatar-lg" id="avatarPreview">
                            <?php if (!empty($investor['avatar_path'])): ?>
                                <img src="<?= e($investor['avatar_path']) ?>" alt="" id="avatarImg"/>
                            <?php else: ?>
                                <span id="avatarInitial">
                                    <?= strtoupper(substr($investor['first_name'] ?? '?', 0, 1)) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <input type="file" name="avatar" id="avatarFile"
                                   accept="image/jpeg,image/png,image/webp"
                                   style="display:none;" onchange="previewAvatar(this)"/>
                            <button type="button" class="btn-secondary btn-sm"
                                    onclick="document.getElementById('avatarFile').click()">
                                Upload Photo
                            </button>
                            <div class="form-hint">JPG, PNG or WebP. Max 2MB.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Address ── -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">📍</span>
                    <div>
                        <div class="form-card-title">Address</div>
                        <div class="form-card-sub">Investor's mailing address</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Address Line 1</label>
                    <input type="text" name="address_line1" placeholder="123 Main St"
                           value="<?= e($data['address_line1'] ?? '') ?>"/>
                </div>
                <div class="form-group">
                    <label>Address Line 2</label>
                    <input type="text" name="address_line2" placeholder="Suite, Apt, Unit..."
                           value="<?= e($data['address_line2'] ?? '') ?>"/>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city"
                               value="<?= e($data['city'] ?? '') ?>"/>
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <input type="text" name="state" placeholder="TX"
                               value="<?= e($data['state'] ?? '') ?>"/>
                    </div>
                    <div class="form-group">
                        <label>ZIP Code</label>
                        <input type="text" name="zip"
                               value="<?= e($data['zip'] ?? '') ?>"/>
                    </div>
                </div>

                <div class="form-group">
                    <label>Country</label>
                    <select name="country">
                        <?php
                        $countries = ['US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
                                      'AU' => 'Australia', 'NG' => 'Nigeria', 'OTHER' => 'Other'];
                        foreach ($countries as $code => $name):
                        ?>
                        <option value="<?= $code ?>" <?= ($data['country'] ?? 'US') === $code ? 'selected' : '' ?>>
                            <?= $name ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- ── Account Settings ── -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">⚙️</span>
                    <div>
                        <div class="form-card-title">Account Settings</div>
                        <div class="form-card-sub">Status, role, and accreditation</div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Account Status</label>
                        <select name="status">
                            <option value="active"    <?= ($data['status'] ?? '') === 'active'    ? 'selected' : '' ?>>Active</option>
                            <option value="suspended" <?= ($data['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            <option value="pending"   <?= ($data['status'] ?? '') === 'pending'   ? 'selected' : '' ?>>Pending</option>
                        </select>
                        <span class="form-hint">Suspended investors cannot log in.</span>
                    </div>

                    <div class="form-group">
                        <label>Accredited Investor</label>
                        <select name="accredited">
                            <option value="0" <?= empty($data['accredited']) ? 'selected' : '' ?>>Not Verified</option>
                            <option value="1" <?= !empty($data['accredited']) ? 'selected' : '' ?>>Verified Accredited</option>
                        </select>
                        <?php if (!empty($investor['accredited_at'])): ?>
                            <span class="form-hint">Verified on <?= formatDate($investor['accredited_at']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── Internal Notes ── -->
            <div class="form-card" id="notes">
                <div class="form-card-header">
                    <span class="form-card-icon">📝</span>
                    <div>
                        <div class="form-card-title">Internal Notes</div>
                        <div class="form-card-sub">Only visible to admins</div>
                    </div>
                </div>
                <div class="form-group" style="margin:0;">
                    <textarea name="notes" rows="5"
                              placeholder="Relationship notes, how they were referred, any important context..."><?= e($data['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- ── Danger Zone ── -->
            <div class="form-card" style="border-color:var(--red-border);">
                <div class="form-card-header">
                    <span class="form-card-icon">⚠️</span>
                    <div>
                        <div class="form-card-title" style="color:var(--red);">Danger Zone</div>
                        <div class="form-card-sub">Irreversible actions</div>
                    </div>
                </div>
                <div class="flex gap-8" style="flex-wrap:wrap;">
                    <?php if ($investor['status'] !== 'suspended'): ?>
                    <button type="button" class="btn-danger btn-sm"
                            onclick="confirmAction('suspend', <?= $id ?>)">
                        Suspend Account
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn-secondary btn-sm"
                            onclick="confirmAction('activate', <?= $id ?>)">
                        Reactivate Account
                    </button>
                    <?php endif; ?>

                    <button type="button" class="btn-danger btn-sm"
                            onclick="confirmAction('resend_invite', <?= $id ?>)">
                        Resend Invite Email
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-8" style="justify-content:flex-end; padding-top:8px;">
                <a href="<?= APP_URL ?>/admin/users/view.php?id=<?= $id ?>" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>

        </form>

    </div>
</div>

<!-- Confirm action form (hidden) -->
<form method="POST" action="<?= APP_URL ?>/app/Actions/admin/investor-action.php"
      id="actionForm" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="user_id" value="<?= $id ?>"/>
    <input type="hidden" name="action" id="actionInput"/>
</form>

<style>
.form-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
}
.form-card-header {
    display: flex; align-items: flex-start; gap: 12px;
    margin-bottom: 20px; padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}
.form-card-icon  { font-size: 20px; margin-top: 2px; }
.form-card-title { font-size: 15px; font-weight: 700; }
.form-card-sub   { font-size: 12px; color: var(--muted); margin-top: 2px; }
</style>

<script>
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('avatarPreview');
        preview.innerHTML = '<img src="' + e.target.result + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"/>';
    };
    reader.readAsDataURL(input.files[0]);
}

function confirmAction(action, userId) {
    const messages = {
        suspend:       'Suspend this investor? They will not be able to log in.',
        activate:      'Reactivate this investor account?',
        resend_invite: 'Resend the invite email to this investor?',
    };

    if (!confirm(messages[action] || 'Are you sure?')) return;

    document.getElementById('actionInput').value = action;
    document.getElementById('actionForm').submit();
}
</script>

</body>
</html>
