<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use Rise\Core\Auth;
Auth::requireAdmin();

$settings = [
    'company_name'     => db()->fetchColumn("SELECT value FROM settings WHERE key_name='company_name'") ?: APP_NAME,
    'contact_email'    => db()->fetchColumn("SELECT value FROM settings WHERE key_name='contact_email'") ?: '',
    'bank_details'     => db()->fetchColumn("SELECT value FROM settings WHERE key_name='bank_details'") ?: '',
    'wire_instructions'=> db()->fetchColumn("SELECT value FROM settings WHERE key_name='wire_instructions'") ?: '',
];

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Settings — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .form-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px; }
        .form-card-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border); }
        .form-card-icon { font-size:20px;margin-top:2px; }
        .form-card-title { font-size:15px;font-weight:700; }
        .form-card-sub { font-size:12px;color:var(--muted);margin-top:2px; }
    </style>
</head>
<body class="admin-layout">
<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>
<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>
    <div class="page-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Settings</h1>
                <p class="page-sub">Portal configuration and company details</p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="<?= APP_URL ?>/app/Actions/admin/save-settings.php" style="max-width:680px;">
            <?= csrfField() ?>

            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">🏢</span>
                    <div>
                        <div class="form-card-title">Company Information</div>
                        <div class="form-card-sub">Displayed across the portal</div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="company_name" value="<?= e($settings['company_name']) ?>"/>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Contact Email</label>
                    <input type="email" name="contact_email" value="<?= e($settings['contact_email']) ?>"
                           placeholder="admin@risecapitalgroup.com"/>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">🏦</span>
                    <div>
                        <div class="form-card-title">Banking Details</div>
                        <div class="form-card-sub">Sent to investors when they request a deposit</div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Bank Account Details</label>
                    <textarea name="bank_details" rows="4"
                              placeholder="Bank Name, Account Number, Routing Number..."><?= e($settings['bank_details']) ?></textarea>
                    <span class="form-hint">This text is sent to investors via the message thread when they make a deposit request.</span>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Wire Transfer Instructions</label>
                    <textarea name="wire_instructions" rows="4"
                              placeholder="Step-by-step wire transfer instructions..."><?= e($settings['wire_instructions']) ?></textarea>
                </div>
            </div>

            <div class="form-card" style="border-color:var(--blue-bg);">
                <div class="form-card-header">
                    <span class="form-card-icon">ℹ️</span>
                    <div>
                        <div class="form-card-title">Server Configuration</div>
                        <div class="form-card-sub">Read-only — edit in .env file</div>
                    </div>
                </div>
                <table style="width:100%;font-size:13px;">
                    <tr><td style="color:var(--muted);padding:6px 0;width:40%">App URL</td><td><?= e(APP_URL) ?></td></tr>
                    <tr><td style="color:var(--muted);padding:6px 0">Environment</td><td><span class="badge <?= APP_ENV === 'production' ? 'badge-green' : 'badge-orange' ?>"><?= APP_ENV ?></span></td></tr>
                    <tr><td style="color:var(--muted);padding:6px 0">Debug Mode</td><td><span class="badge <?= APP_DEBUG ? 'badge-red' : 'badge-grey' ?>"><?= APP_DEBUG ? 'ON' : 'OFF' ?></span></td></tr>
                    <tr><td style="color:var(--muted);padding:6px 0">PHP Version</td><td><?= PHP_VERSION ?></td></tr>
                    <tr><td style="color:var(--muted);padding:6px 0">Max Upload</td><td><?= MAX_UPLOAD_MB ?>MB</td></tr>
                </table>
            </div>

            <div class="flex gap-8" style="justify-content:flex-end;">
                <button type="submit" class="btn-primary">Save Settings →</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>