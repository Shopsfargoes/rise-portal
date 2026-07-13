<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Record Investment
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Project;

Auth::requireAdmin();

// Fetch all active investors
$investors = db()->fetchAll(
    "SELECT u.id, u.email, p.first_name, p.last_name
     FROM users u
     LEFT JOIN user_profiles p ON p.user_id = u.id
     WHERE u.role = 'investor' AND u.status = 'active'
     ORDER BY p.last_name ASC, p.first_name ASC"
);

$projects = Project::findAll([], 100, 0);

// Pre-select investor if coming from investor view page
$preUserId    = (int) get('user_id', 0);
$preProjectId = (int) get('project_id', 0);

$data  = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Record Investment — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .form-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px; }
        .form-card-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border); }
        .form-card-icon  { font-size:20px;margin-top:2px; }
        .form-card-title { font-size:15px;font-weight:700; }
        .form-card-sub   { font-size:12px;color:var(--muted);margin-top:2px; }

        /* Preview card */
        .investment-preview {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
            margin-top: 16px;
            display: none;
        }

        .investment-preview.show { display: block; }

        .preview-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }

        .preview-row:last-child { border-bottom: none; }
        .preview-label { color: var(--muted); }
        .preview-value { font-weight: 600; color: var(--text); }
        .preview-value.gold { color: var(--gold); font-size: 18px; }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <a href="<?= APP_URL ?>/admin/investments/index.php" class="back-link">
                    ← Back to Investments
                </a>
                <h1 class="page-title">Record Investment</h1>
                <p class="page-sub">Manually record an investor's allocation to a project</p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:flex-start;max-width:900px;">

            <form method="POST" action="<?= APP_URL ?>/app/Actions/admin/record-investment.php"
                  id="investmentForm">
                <?= csrfField() ?>

                <!-- Investor -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span class="form-card-icon">👤</span>
                        <div>
                            <div class="form-card-title">Select Investor</div>
                            <div class="form-card-sub">Only active investors are listed</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label>Investor <span class="required">*</span></label>
                        <select name="user_id" id="investorSelect" required
                                onchange="updatePreview()">
                            <option value="">— Select Investor —</option>
                            <?php foreach ($investors as $inv): ?>
                            <option value="<?= $inv['id'] ?>"
                                data-name="<?= e($inv['first_name'] . ' ' . $inv['last_name']) ?>"
                                data-email="<?= e($inv['email']) ?>"
                                <?= ($data['user_id'] ?? $preUserId) == $inv['id'] ? 'selected' : '' ?>>
                                <?= e($inv['first_name'] . ' ' . $inv['last_name']) ?>
                                — <?= e($inv['email']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($investors)): ?>
                        <span class="form-hint" style="color:var(--orange);">
                            No active investors found.
                            <a href="<?= APP_URL ?>/admin/users/create.php">Invite one →</a>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Project -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span class="form-card-icon">📍</span>
                        <div>
                            <div class="form-card-title">Select Project</div>
                            <div class="form-card-sub">The project this investment is allocated to</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label>Project <span class="required">*</span></label>
                        <select name="project_id" id="projectSelect" required
                                onchange="updatePreview()">
                            <option value="">— Select Project —</option>
                            <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>"
                                data-title="<?= e($proj['title']) ?>"
                                data-location="<?= e($proj['location'] ?? '') ?>"
                                data-cost="<?= e($proj['project_cost']) ?>"
                                <?= ($data['project_id'] ?? $preProjectId) == $proj['id'] ? 'selected' : '' ?>>
                                <?= e($proj['title']) ?>
                                <?php if ($proj['location']): ?>
                                    — <?= e($proj['location']) ?>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Amount & details -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span class="form-card-icon">💰</span>
                        <div>
                            <div class="form-card-title">Investment Details</div>
                            <div class="form-card-sub">Amount and date of the investment</div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Amount (USD) <span class="required">*</span></label>
                            <input type="number" name="amount" id="amountInput"
                                   required min="1" step="0.01"
                                   placeholder="50000"
                                   value="<?= e($data['amount'] ?? '') ?>"
                                   oninput="updatePreview()"/>
                        </div>

                        <div class="form-group">
                            <label>Investment Date <span class="required">*</span></label>
                            <input type="date" name="invested_at" required
                                   value="<?= e($data['invested_at'] ?? date('Y-m-d')) ?>"/>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="active"  <?= ($data['status'] ?? 'active') === 'active'  ? 'selected' : '' ?>>Active</option>
                                <option value="pending" <?= ($data['status'] ?? '')        === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="exited"  <?= ($data['status'] ?? '')        === 'exited'  ? 'selected' : '' ?>>Exited</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <!-- spacer -->
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label>Internal Notes</label>
                        <textarea name="notes" rows="3"
                                  placeholder="Wire reference, call notes, any context about this investment..."><?= e($data['notes'] ?? '') ?></textarea>
                        <span class="form-hint">Admin-only. Not shown to the investor.</span>
                    </div>
                </div>

                <div class="flex gap-8" style="justify-content:flex-end;">
                    <a href="<?= APP_URL ?>/admin/investments/index.php" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">Record Investment →</button>
                </div>
            </form>

            <!-- Live preview -->
            <div>
                <div class="card" style="position:sticky;top:76px;">
                    <div class="card-header">
                        <div class="card-title">Investment Summary</div>
                    </div>
                    <p style="font-size:13px;color:var(--muted);margin-bottom:12px;">
                        Fill in the form to see a preview before saving.
                    </p>

                    <div class="investment-preview" id="previewCard">
                        <div class="preview-row">
                            <span class="preview-label">Investor</span>
                            <span class="preview-value" id="previewInvestor">—</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Project</span>
                            <span class="preview-value" id="previewProject">—</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Location</span>
                            <span class="preview-value" id="previewLocation" style="color:var(--muted);">—</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Amount</span>
                            <span class="preview-value gold" id="previewAmount">—</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">% of Project Cost</span>
                            <span class="preview-value" id="previewPct">—</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function updatePreview() {
    const investorSel = document.getElementById('investorSelect');
    const projectSel  = document.getElementById('projectSelect');
    const amountInput = document.getElementById('amountInput');
    const preview     = document.getElementById('previewCard');

    const investorOpt = investorSel.options[investorSel.selectedIndex];
    const projectOpt  = projectSel.options[projectSel.selectedIndex];
    const amount      = parseFloat(amountInput.value) || 0;

    if (!investorSel.value && !projectSel.value && !amount) return;

    preview.classList.add('show');

    // Investor
    document.getElementById('previewInvestor').textContent =
        investorOpt?.dataset.name || '—';

    // Project
    const projTitle    = projectOpt?.dataset.title    || '—';
    const projLocation = projectOpt?.dataset.location || '—';
    const projCost     = parseFloat(projectOpt?.dataset.cost || 0);

    document.getElementById('previewProject').textContent  = projTitle;
    document.getElementById('previewLocation').textContent = projLocation;

    // Amount
    document.getElementById('previewAmount').textContent =
        amount > 0 ? '$' + amount.toLocaleString('en-US', {minimumFractionDigits:2}) : '—';

    // Percentage of project cost
    if (amount > 0 && projCost > 0) {
        const pct = ((amount / projCost) * 100).toFixed(2);
        document.getElementById('previewPct').textContent = pct + '%';
    } else {
        document.getElementById('previewPct').textContent = '—';
    }
}

// Run on load if form is pre-populated
document.addEventListener('DOMContentLoaded', updatePreview);
</script>

</body>
</html>