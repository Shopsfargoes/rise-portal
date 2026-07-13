<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Distributions
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Distribution;

Auth::requireInvestor();

$userId        = Auth::id();
$distributions = Distribution::findByUser($userId);

// Totals
$totalReceived = array_sum(array_column(
    array_filter($distributions, fn($d) => $d['status'] === 'paid'),
    'amount'
));
$totalPending  = array_sum(array_column(
    array_filter($distributions, fn($d) => $d['status'] === 'pending'),
    'amount'
));

// Group by project
$byProject = [];
foreach ($distributions as $dist) {
    $byProject[$dist['project_title']][] = $dist;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Distributions — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .dist-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 3px solid var(--green);
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dist-card.pending {
            border-left-color: var(--orange);
        }

        .dist-project {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .dist-meta {
            font-size: 12px;
            color: var(--muted);
        }

        .dist-amount {
            font-size: 20px;
            font-weight: 900;
            color: var(--green);
            text-align: right;
        }

        .dist-amount.pending { color: var(--orange); }

        .project-group {
            margin-bottom: 28px;
        }

        .project-group-header {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .project-group-header::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
    </style>
</head>
<body class="investor-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-investor.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Distributions</h1>
                <p class="page-sub">Your share of project revenues and returns</p>
            </div>
        </div>

        <!-- Summary stats -->
        <div class="stats-grid" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-label">Total Received</div>
                <div class="stat-value text-green"><?= formatMoney($totalReceived) ?></div>
                <div class="stat-sub">Confirmed distributions</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value" style="color:var(--orange);">
                    <?= formatMoney($totalPending) ?>
                </div>
                <div class="stat-sub">Awaiting payment</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Distributions</div>
                <div class="stat-value"><?= count($distributions) ?></div>
                <div class="stat-sub">Across all projects</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Projects</div>
                <div class="stat-value"><?= count($byProject) ?></div>
                <div class="stat-sub">Projects with distributions</div>
            </div>
        </div>

        <?php if (empty($distributions)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--muted);">
            <div style="font-size:48px;margin-bottom:12px;">📤</div>
            <p style="font-size:14px;">No distributions yet.</p>
            <p style="font-size:12px;margin-top:6px;">
                Distributions are paid when your invested projects generate revenue.
            </p>
        </div>

        <?php else: ?>

        <!-- Grouped by project -->
        <?php foreach ($byProject as $projectTitle => $dists): ?>
        <div class="project-group">
            <div class="project-group-header">
                📍 <?= e($projectTitle) ?>
                <span style="color:var(--text);font-weight:700;font-size:13px;
                             text-transform:none;letter-spacing:0;">
                    <?= formatMoney(array_sum(array_column($dists, 'amount'))) ?> total
                </span>
            </div>

            <?php foreach ($dists as $dist): ?>
            <div class="dist-card <?= $dist['status'] === 'pending' ? 'pending' : '' ?>">
                <div>
                    <div class="dist-project">
                        <?= $dist['dist_description']
                            ? e($dist['dist_description'])
                            : 'Distribution Payment' ?>
                    </div>
                    <div class="dist-meta">
                        <?= formatDate($dist['distribution_date']) ?>
                        &nbsp;·&nbsp;
                        <span class="badge <?= $dist['status'] === 'paid' ? 'badge-green' : 'badge-orange' ?>">
                            <?= ucfirst($dist['status']) ?>
                        </span>
                        <?php if ($dist['paid_at']): ?>
                        &nbsp;·&nbsp; Paid <?= formatDate($dist['paid_at']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dist-amount <?= $dist['status'] === 'pending' ? 'pending' : '' ?>">
                    +<?= formatMoney((float)$dist['amount']) ?>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
        <?php endforeach; ?>

        <?php endif; ?>

    </div>
</div>

</body>
</html>