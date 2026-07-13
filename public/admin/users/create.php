<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Invite New Investor
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;

Auth::requireAdmin();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Invite Investor — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">

    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <!-- Page header -->
        <div class="page-header">
            <div>
                <a href="<?= APP_URL ?>/admin/users/index.php" class="back-link">← Back to Investors</a>
                <h1 class="page-title">Invite New Investor</h1>
                <p class="page-sub">Send a secure invite link. The investor sets their own password.</p>
            </div>
        </div>

        <!-- Flash messages -->
        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>">
            <?= e($msg['message']) ?>
        </div>
        <?php endforeach; ?>

        <div class="form-layout">
            <form method="POST" action="<?= APP_URL ?>/app/Actions/admin/invite-user.php">
                <?= csrfField() ?>

                <!-- Personal Info -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span class="form-card-icon">👤</span>
                        <div>
                            <div class="form-card-title">Personal Information</div>
                            <div class="form-card-sub">Basic details for the investor's account</div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name"
                                   placeholder="Keith" required
                                   value="<?= e($_SESSION['form_data']['first_name'] ?? '') ?>"/>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name"
                                   placeholder="Johnson" required
                                   value="<?= e($_SESSION['form_data']['last_name'] ?? '') ?>"/>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email"
                               placeholder="investor@example.com" required
                               value="<?= e($_SESSION['form_data']['email'] ?? '') ?>"/>
                        <span class="form-hint">The invite link will be sent to this address.</span>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone"
                               placeholder="+1 (555) 000-0000"
                               value="<?= e($_SESSION['form_data']['phone'] ?? '') ?>"/>
                    </div>
                </div>

                <!-- Account Settings -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span class="form-card-icon">⚙️</span>
                        <div>
                            <div class="form-card-title">Account Settings</div>
                            <div class="form-card-sub">Role and accreditation status</div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="role">Account Role <span class="required">*</span></label>
                            <select id="role" name="role" required>
                                <option value="investor" <?= (($_SESSION['form_data']['role'] ?? '') === 'investor') ? 'selected' : '' ?>>Investor</option>
                                <option value="admin"    <?= (($_SESSION['form_data']['role'] ?? '') === 'admin')    ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="accredited">Accredited Investor?</label>
                            <select id="accredited" name="accredited">
                                <option value="0">Not yet verified</option>
                                <option value="1" <?= (($_SESSION['form_data']['accredited'] ?? '0') === '1') ? 'selected' : '' ?>>Yes — Verified Accredited</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Internal Notes</label>
                        <textarea id="notes" name="notes" rows="3"
                                  placeholder="How did they find RISE? Any relationship notes..."><?= e($_SESSION['form_data']['notes'] ?? '') ?></textarea>
                        <span class="form-hint">Visible to admins only. Not shown to the investor.</span>
                    </div>
                </div>

                <!-- Invite preview -->
                <div class="form-card form-card-preview">
                    <div class="form-card-header">
                        <span class="form-card-icon">📧</span>
                        <div>
                            <div class="form-card-title">What the investor receives</div>
                            <div class="form-card-sub">A branded email with a secure one-time link</div>
                        </div>
                    </div>

                    <div class="invite-preview">
                        <div class="invite-preview-inner">
                            <div class="invite-preview-logo">R &nbsp; RISE Capital Group</div>
                            <h3 style="color:#C9922A; margin-bottom:8px;">You've been invited</h3>
                            <p style="color:#aaa; font-size:13px; margin-bottom:16px;">
                                <strong style="color:#f0f0f0;"><?= e(Auth::name()) ?></strong> has invited you
                                to access the RISE Capital Group investor portal.
                            </p>
                            <div class="invite-preview-btn">Accept Invitation →</div>
                            <p style="color:#555; font-size:11px; margin-top:12px;">Link expires in 48 hours</p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="<?= APP_URL ?>/admin/users/index.php" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">
                        Send Invitation →
                    </button>
                </div>

            </form>
        </div>

    </div>
</div>

<style>
/* Page-specific styles */
.form-layout    { max-width: 720px; }

.form-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
}

.form-card-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 22px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.form-card-icon  { font-size: 20px; margin-top: 2px; }
.form-card-title { font-size: 15px; font-weight: 700; }
.form-card-sub   { font-size: 12px; color: var(--muted); margin-top: 2px; }

.form-card-preview { border-color: #2a1f00; }

.invite-preview {
    background: #0a0a0a;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 24px;
}

.invite-preview-logo {
    font-weight: 800;
    color: var(--gold);
    font-size: 15px;
    margin-bottom: 16px;
}

.invite-preview-btn {
    display: inline-block;
    background: var(--gold);
    color: #000;
    padding: 10px 22px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 8px;
}
</style>

</body>
</html>
<?php
// Clean up form data after display
unset($_SESSION['form_data']);
?>