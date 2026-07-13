<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Dashboard
// ============================================================
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Investment;

Auth::requireInvestor();

$user = Auth::user();
$userId = Auth::id();

// ── Portfolio data ────────────────────────────────────────
$summary     = Investment::summaryForUser($userId);
$investments = Investment::findByUser($userId);

// Wallet balance
$balance = (float) db()->fetchColumn(
    "SELECT balance FROM wallet_balances WHERE user_id = ?",
    [$userId]
) ?? 0;

// Recent transactions (last 5)
$recentTx = db()->fetchAll(
    "SELECT * FROM wallet_transactions
     WHERE user_id = ?
     ORDER BY created_at DESC LIMIT 5",
    [$userId]
);

// Recent distributions (last 5)
$recentDist = db()->fetchAll(
    "SELECT di.*, d.distribution_date, p.title AS project_title
     FROM distribution_items di
     JOIN distributions d ON d.id = di.distribution_id
     JOIN projects p ON p.id = d.project_id
     WHERE di.user_id = ?
     ORDER BY d.distribution_date DESC LIMIT 5",
    [$userId]
);

// Unread notifications
$notifications = db()->fetchAll(
    "SELECT * FROM notifications
     WHERE user_id = ? AND is_read = 0
     ORDER BY created_at DESC LIMIT 5",
    [$userId]
);

$txStatusBadge = [
    'pending'   => 'badge-orange',
    'contacted' => 'badge-blue',
    'confirmed' => 'badge-green',
    'rejected'  => 'badge-red',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, #1a1200 0%, #0a0a0a 60%);
            border: 1px solid var(--gold-border);
            border-radius: 14px;
            padding: 28px 32px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(201,146,42,0.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .welcome-name {
            font-size: 28px;
            font-weight: 900;
            color: var(--gold);
            margin-bottom: 4px;
        }

        .welcome-sub {
            font-size: 13px;
            color: var(--muted);
        }

        .welcome-badges {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        /* Portfolio card */
        .portfolio-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: var(--text);
            transition: border-color .2s, transform .15s;
        }

        .portfolio-card:hover {
            border-color: var(--gold);
            transform: translateY(-1px);
            text-decoration: none;
            color: var(--text);
        }

        .portfolio-cover {
            width: 48px; height: 48px;
            border-radius: 8px;
            object-fit: cover;
            background: var(--surface2);
            flex-shrink: 0;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            overflow: hidden;
        }

        .portfolio-cover img {
            width: 100%; height: 100%; object-fit: cover;
        }

        .portfolio-info { flex: 1; min-width: 0; }

        .portfolio-title {
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .portfolio-meta {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }

        .portfolio-amount {
            font-size: 15px;
            font-weight: 800;
            color: var(--gold);
            flex-shrink: 0;
        }

        /* Distribution badge */
        .dist-return {
            font-size: 11px;
            color: var(--green);
            background: var(--green-bg);
            border: 1px solid var(--green-border);
            border-radius: 99px;
            padding: 2px 8px;
            margin-top: 3px;
            display: inline-block;
        }

        /* Dashboard grid */
        .dash-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: flex-start;
        }

        @media (max-width: 900px) {
            .dash-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="investor-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-investor.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <!-- Welcome banner -->
        <div class="welcome-banner">
            <div class="welcome-name">Welcome back, <?= e($user['first_name']) ?></div>
            <div class="welcome-sub">
                Company-level source-of-truth metrics from the RISE portal.
            </div>
            <div class="welcome-badges">
                <span class="badge badge-grey">
                    <?= $user['accredited'] ? '✓ Accredited Investor' : 'Investor' ?>
                </span>
                <?php if (!empty($notifications)): ?>
                <span class="badge badge-orange">
                    🔔 <?= count($notifications) ?> unread notification<?= count($notifications) !== 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats cards -->
        <div class="stats-grid" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-label">
                    Wallet Balance
                    <span style="font-size:16px;">💳</span>
                </div>
                <div class="stat-value text-gold"><?= formatMoney($balance) ?></div>
                <div class="stat-sub">
                    <a href="<?= APP_URL ?>/investor/wallet.php"
                       style="color:var(--gold);font-size:11px;">Deposit / Withdraw →</a>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">
                    Active Investments
                    <span style="font-size:16px;">💼</span>
                </div>
                <div class="stat-value"><?= number_format((int)$summary['active_count']) ?></div>
                <div class="stat-sub">Across all projects</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">
                    Total Invested
                    <span style="font-size:16px;">📈</span>
                </div>
                <div class="stat-value"><?= formatCurrency((float)$summary['total_invested']) ?></div>
                <div class="stat-sub">All time</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">
                    Average Investment
                    <span style="font-size:16px;">🔗</span>
                </div>
                <div class="stat-value"><?= formatCurrency((float)$summary['avg_investment']) ?></div>
                <div class="stat-sub">Per project</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">
                    Total Distributions
                    <span style="font-size:16px;">📤</span>
                </div>
                <div class="stat-value"><?= formatCurrency((float)$summary['total_distributed']) ?></div>
                <div class="stat-sub">Received to date</div>
            </div>
        </div>

        <div class="dash-grid">

            <!-- Left — portfolio + recent transactions -->
            <div>

                <!-- Active investments -->
                <div class="card mb-24" style="margin-bottom:24px;">
                    <div class="card-header">
                        <div>
                            <div class="card-title">My Portfolio</div>
                            <div class="card-sub"><?= count($investments) ?> investment<?= count($investments) !== 1 ? 's' : '' ?></div>
                        </div>
                        <a href="<?= APP_URL ?>/investor/projects.php" class="btn-secondary btn-sm">
                            Browse Projects
                        </a>
                    </div>

                    <?php if (empty($investments)): ?>
                    <div style="text-align:center;padding:32px;color:var(--muted);">
                        <div style="font-size:36px;margin-bottom:10px;">💼</div>
                        <p style="font-size:13px;">No investments recorded yet.</p>
                        <a href="<?= APP_URL ?>/investor/projects.php"
                           style="font-size:12px;color:var(--gold);">Explore projects →</a>
                    </div>
                    <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach ($investments as $inv): ?>
                        <a href="<?= APP_URL ?>/investor/project-detail.php?slug=<?= e($inv['project_slug']) ?>"
                           class="portfolio-card">
                            <div class="portfolio-cover">
                                <?php if (!empty($inv['project_cover'])): ?>
                                <img src="<?= e($inv['project_cover']) ?>" alt=""/>
                                <?php else: ?>
                                🛢
                                <?php endif; ?>
                            </div>
                            <div class="portfolio-info">
                                <div class="portfolio-title"><?= e($inv['project_title']) ?></div>
                                <div class="portfolio-meta">
                                    <?= e($inv['project_location'] ?? '') ?>
                                    · <?= formatDate($inv['invested_at']) ?>
                                </div>
                                <?php if ($inv['total_distributed'] > 0): ?>
                                <span class="dist-return">
                                    ↑ <?= formatMoney((float)$inv['total_distributed']) ?> distributed
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="portfolio-amount">
                                <?= formatMoney((float)$inv['amount']) ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent transactions -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Recent Transactions</div>
                        <a href="<?= APP_URL ?>/investor/transactions.php"
                           class="btn-secondary btn-sm">View All</a>
                    </div>

                    <?php if (empty($recentTx)): ?>
                    <p style="font-size:13px;color:var(--muted);padding:12px 0;">
                        No transactions yet.
                        <a href="<?= APP_URL ?>/investor/wallet.php" style="color:var(--gold);">
                            Make a deposit →
                        </a>
                    </p>
                    <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:1px;">
                        <?php foreach ($recentTx as $tx): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;
                                    padding:10px 0;border-bottom:1px solid var(--border);">
                            <div class="flex-center gap-8">
                                <span style="font-size:18px;">
                                    <?= $tx['type'] === 'deposit' ? '↓' : '↑' ?>
                                </span>
                                <div>
                                    <div style="font-size:13px;font-weight:600;">
                                        <?= ucfirst($tx['type']) ?>
                                    </div>
                                    <div style="font-size:11px;color:var(--muted);">
                                        <?= timeAgo($tx['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-weight:700;font-size:13px;
                                            color:<?= $tx['type'] === 'deposit' ? 'var(--green)' : 'var(--red)' ?>;">
                                    <?= $tx['type'] === 'deposit' ? '+' : '–' ?><?= formatMoney((float)$tx['amount']) ?>
                                </div>
                                <span class="badge <?= $txStatusBadge[$tx['status']] ?? 'badge-grey' ?>"
                                      style="font-size:10px;">
                                    <?= ucfirst($tx['status']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Right — notifications + distributions -->
            <div>

                <!-- Notifications -->
                <?php if (!empty($notifications)): ?>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <div class="card-title">🔔 Notifications</div>
                        <span class="badge badge-orange"><?= count($notifications) ?> new</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach ($notifications as $notif): ?>
                        <div style="padding:10px 12px;background:var(--surface2);
                                    border-radius:8px;border:1px solid var(--border);">
                            <div style="font-size:13px;font-weight:600;margin-bottom:3px;">
                                <?= e($notif['title']) ?>
                            </div>
                            <div style="font-size:12px;color:var(--muted);line-height:1.5;">
                                <?= e($notif['message']) ?>
                            </div>
                            <div style="font-size:11px;color:var(--muted2);margin-top:5px;">
                                <?= timeAgo($notif['created_at']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent distributions -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Recent Distributions</div>
                        <a href="<?= APP_URL ?>/investor/distributions.php"
                           class="btn-secondary btn-sm">View All</a>
                    </div>

                    <?php if (empty($recentDist)): ?>
                    <p style="font-size:13px;color:var(--muted);padding:12px 0;">
                        No distributions received yet.
                    </p>
                    <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:1px;">
                        <?php foreach ($recentDist as $dist): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;
                                    padding:10px 0;border-bottom:1px solid var(--border);">
                            <div>
                                <div style="font-size:13px;font-weight:600;">
                                    <?= e($dist['project_title']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--muted);">
                                    <?= formatDate($dist['distribution_date']) ?>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-weight:700;font-size:13px;color:var(--green);">
                                    +<?= formatMoney((float)$dist['amount']) ?>
                                </div>
                                <span class="badge <?= $dist['status'] === 'paid' ? 'badge-green' : 'badge-orange' ?>"
                                      style="font-size:10px;">
                                    <?= ucfirst($dist['status']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

</body>
</html>