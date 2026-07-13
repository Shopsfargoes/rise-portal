<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Create Distribution
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Project;

Auth::requireAdmin();

$projects  = Project::findAll([], 100, 0);
$data      = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// If a project is pre-selected, load its investors for preview
$preProjectId = (int) get('project_id', 0);
$previewData  = [];

if ($preProjectId) {
    $previewData = db()->fetchAll(
        "SELECT i.id, i.amount, i.user_id,
                up.first_name, up.last_name, u.email
         FROM investments i
         JOIN users u ON u.id = i.user_id
         LEFT JOIN user_profiles up ON up.user_id = i.user_id
         WHERE i.project_id = ? AND i.status = 'active'
         ORDER BY i.amount DESC",
        [$preProjectId]
    );
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Record Distribution — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .form-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px; }
        .form-card-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border); }
        .form-card-icon  { font-size:20px;margin-top:2px; }
        .form-card-title { font-size:15px;font-weight:700; }
        .form-card-sub   { font-size:12px;color:var(--muted);margin-top:2px; }

        .investor-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .share-amount {
            font-size: 15px;
            font-weight: 700;
            color: var(--green);
            min-width: 100px;
            text-align: right;
        }

        .share-pct {
            font-size: 11px;
            color: var(--muted);
            text-align: right;
        }

        .preview-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 14px;
            background: var(--gold-dim);
            border: 1px solid var(--gold-border);
            border-radius: 8px;
            margin-top: 12px;
        }

        .preview-total-label { font-size: 13px; font-weight: 700; color: var(--gold); }
        .preview-total-amount { font-size: 20px; font-weight: 900; color: var(--gold); }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <a href="<?= APP_URL ?>/admin/distributions/index.php" class="back-link">
                    ← Back to Distributions
                </a>
                <h1 class="page-title">Record Distribution</h1>
                <p class="page-sub">
                    Shares are automatically calculated based on each investor's
                    percentage of total invested in the project.
                </p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;
                    align-items:flex-start;max-width:960px;">

            <!-- Form -->
            <form method="POST"
                  action="<?= APP_URL ?>/app/Actions/admin/save-distribution.php"
                  id="distForm">
                <?= csrfField() ?>

                <!-- Project -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span class="form-card-icon">📍</span>
                        <div>
                            <div class="form-card-title">Select Project</div>
                            <div class="form-card-sub">
                                Only projects with active investments are eligible
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label>Project <span class="required">*</span></label>
                        <select name="project_id" id="projectSelect" required
                                onchange="loadInvestors(this.value)">
                            <option value="">— Select Project —</option>
                            <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>"
                                data-title="<?= e($proj['title']) ?>"
                                <?= ($data['project_id'] ?? $preProjectId) == $proj['id'] ? 'selected' : '' ?>>
                                <?= e($proj['title']) ?>
                                <?php if ($proj['location']): ?>— <?= e($proj['location']) ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Distribution Details -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span class="form-card-icon">💰</span>
                        <div>
                            <div class="form-card-title">Distribution Details</div>
                            <div class="form-card-sub">
                                Enter the total amount — shares auto-calculate
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Total Amount (USD) <span class="required">*</span></label>
                            <input type="number" name="total_amount" id="totalAmount"
                                   required min="1" step="0.01"
                                   placeholder="0.00"
                                   value="<?= e($data['total_amount'] ?? '') ?>"
                                   oninput="updatePreview()"/>
                        </div>
                        <div class="form-group">
                            <label>Distribution Date <span class="required">*</span></label>
                            <input type="date" name="distribution_date" required
                                   value="<?= e($data['distribution_date'] ?? date('Y-m-d')) ?>"/>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label>Description</label>
                        <textarea name="description" rows="2"
                                  placeholder="e.g. Q1 2026 Production Revenue, Initial Production Payment..."><?= e($data['description'] ?? '') ?></textarea>
                        <span class="form-hint">
                            Shown to investors on their distributions page.
                        </span>
                    </div>
                </div>

                <div class="flex gap-8" style="justify-content:flex-end;">
                    <a href="<?= APP_URL ?>/admin/distributions/index.php"
                       class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary" id="submitBtn" disabled>
                        Record Distribution →
                    </button>
                </div>
            </form>

            <!-- Live preview panel -->
            <div style="position:sticky;top:76px;">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📊 Distribution Preview</div>
                    </div>

                    <div id="previewEmpty" style="color:var(--muted);font-size:13px;padding:8px 0;">
                        Select a project and enter an amount to see the breakdown.
                    </div>

                    <div id="previewContent" style="display:none;">
                        <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">
                            Shares based on % of total invested in project
                        </div>
                        <div id="investorList"></div>
                        <div class="preview-total">
                            <span class="preview-total-label">Total</span>
                            <span class="preview-total-amount" id="previewTotal">$0.00</span>
                        </div>
                        <div style="font-size:11px;color:var(--muted);margin-top:10px;">
                            ℹ Last investor receives remainder to avoid rounding gaps.
                        </div>
                    </div>

                    <!-- No investors warning -->
                    <div id="noInvestorsMsg"
                         style="display:none;background:var(--orange-bg);border:1px solid #3a2800;
                                border-radius:8px;padding:12px;font-size:13px;color:var(--orange);
                                margin-top:8px;">
                        ⚠ No active investments found for this project.
                        You must record investments before distributing.
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Store investors fetched from server
let investors = <?= json_encode($previewData) ?>;
let selectedProjectId = <?= $preProjectId ?: 'null' ?>;

// Run on load if pre-populated
if (investors.length > 0) renderPreview();

function loadInvestors(projectId) {
    if (!projectId) {
        investors = [];
        renderPreview();
        return;
    }

    selectedProjectId = projectId;

    fetch(`<?= APP_URL ?>/app/Actions/admin/get-project-investors.php?project_id=${projectId}`)
        .then(r => r.json())
        .then(data => {
            investors = data;
            renderPreview();
        })
        .catch(() => {
            investors = [];
            renderPreview();
        });
}

function updatePreview() {
    renderPreview();
}

function renderPreview() {
    const amount      = parseFloat(document.getElementById('totalAmount').value) || 0;
    const empty       = document.getElementById('previewEmpty');
    const content     = document.getElementById('previewContent');
    const noInvestors = document.getElementById('noInvestorsMsg');
    const submitBtn   = document.getElementById('submitBtn');

    if (!selectedProjectId || investors.length === 0) {
        empty.style.display       = investors.length === 0 && selectedProjectId ? 'none' : '';
        content.style.display     = 'none';
        noInvestors.style.display = investors.length === 0 && selectedProjectId ? '' : 'none';
        submitBtn.disabled        = true;
        return;
    }

    empty.style.display       = 'none';
    noInvestors.style.display = 'none';
    content.style.display     = '';
    submitBtn.disabled        = amount <= 0;

    const totalInvested = investors.reduce((sum, i) => sum + parseFloat(i.amount), 0);
    const list          = document.getElementById('investorList');
    const previewTotal  = document.getElementById('previewTotal');

    let runningTotal = 0;
    let html         = '';

    investors.forEach((inv, index) => {
        const pct   = totalInvested > 0 ? (inv.amount / totalInvested) : 0;
        let share;

        if (index === investors.length - 1) {
            share = Math.round((amount - runningTotal) * 100) / 100;
        } else {
            share = Math.round(pct * amount * 100) / 100;
            runningTotal += share;
        }

        const name = `${inv.first_name} ${inv.last_name}`;
        const pctLabel = (pct * 100).toFixed(1) + '%';

        html += `
            <div class="investor-row">
                <div>
                    <div style="font-size:13px;font-weight:600;">${name}</div>
                    <div style="font-size:11px;color:var(--muted);">
                        Invested: $${parseFloat(inv.amount).toLocaleString()} · ${pctLabel}
                    </div>
                </div>
                <div>
                    <div class="share-amount">+$${share.toLocaleString('en-US', {minimumFractionDigits:2})}</div>
                </div>
            </div>
        `;
    });

    list.innerHTML = html;
    previewTotal.textContent = '$' + (amount > 0 ? amount.toLocaleString('en-US', {minimumFractionDigits:2}) : '0.00');
}

// Run on page load
document.addEventListener('DOMContentLoaded', () => {
    if (selectedProjectId) renderPreview();
});
</script>

</body>
</html>