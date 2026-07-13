<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Dashboard
// ============================================================
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Investment;
use Rise\Models\WalletTransaction;
use Rise\Models\Distribution;
use Rise\Models\Notification;

Auth::requireAdmin();

// ── Company-wide stats ────────────────────────────────────
$investmentStats = Investment::companyStats();
$distStats       = Distribution::companySummary();

// Equity under management = sum of all active wallet balances
$equityUnderMgmt = (float) db()->fetchColumn(
    "SELECT COALESCE(SUM(balance), 0) FROM wallet_balances"
) ?? 0;

// Total invested all time
$investedAllTime = (float) db()->fetchColumn(
    "SELECT COALESCE(SUM(amount), 0) FROM investments WHERE status = 'active'"
) ?? 0;

// Active investor count
$activeInvestors = (int) db()->fetchColumn(
    "SELECT COUNT(*) FROM users WHERE role = 'investor' AND status = 'active'"
) ?? 0;

// Pending wallet requests
$pendingRequests = WalletTransaction::getPending();
$pendingCount    = count($pendingRequests);

// Unread messages
$unreadMessages = (int) db()->fetchColumn(
    "SELECT COALESCE(SUM(unread_admin), 0) FROM message_threads WHERE status = 'open'"
) ?? 0;

// Admin notifications
$notifications = Notification::findByUser(Auth::id(), 10);
$unreadNotifs  = Notification::unreadCount(Auth::id());

// Recent investments (last 5)
$recentInvestments = Investment::findAll([], 5, 0);

// Recent transactions (last 5)
$recentTx = WalletTransaction::findAll([], 5, 0);

// Investor growth (last 6 months)
$investorGrowth = db()->fetchAll(
    "SELECT DATE_FORMAT(created_at, '%b %Y') AS month,
            COUNT(*) AS count
     FROM users
     WHERE role = 'investor' AND status = 'active'
       AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY created_at ASC"
);

$flash = getFlash();
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
        .admin-banner {
            background: linear-gradient(135deg, #1a1200 0%, #0a0a0a 60%);
            border: 1px solid var(--gold-border);
            border-radius: 14px;
            padding: 28px 32px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }

        .admin-banner::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 240px; height: 240px;
            background: radial-gradient(circle, rgba(201,146,42,.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .banner-title { font-size: 26px; font-weight: 900; color: var(--gold); }
        .banner-sub   { font-size: 13px; color: var(--muted); margin-top: 4px; }

        .banner-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }

        /* Pending alert card */
        .pending-alert {
            background: #1a1000;
            border: 1px solid var(--gold-border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .pending-alert-text {
            font-size: 14px;
            font-weight: 600;
            color: var(--gold);
        }

        .pending-alert-sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* Dashboard grid */
        .dash-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: flex-start;
        }

        /* Quick action buttons */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .quick-action {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            text-decoration: none;
            color: var(--text);
            transition: border-color .2s, background .15s;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .quick-action:hover {
            border-color: var(--gold);
            background: var(--surface2);
            text-decoration: none;
            color: var(--text);
        }

        .quick-action-icon { font-size: 22px; }
        .quick-action-label { font-size: 12px; font-weight: 600; }
        .quick-action-sub   { font-size: 11px; color: var(--muted); }

        /* Activity row */
        .activity-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            gap: 12px;
        }

        .activity-row:last-child { border-bottom: none; }

        @media (max-width: 900px) {
            .dash-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <!-- Welcome banner -->
        <div class="admin-banner">
            <div>
                <div class="banner-title">Welcome back, <?= e(Auth::user()['first_name']) ?></div>
                <div class="banner-sub">
                    Company-level source-of-truth metrics from the RISE portal.
                </div>
                <div class="banner-badges">
                    <span class="badge badge-gold">Admin</span>
                    <span class="badge badge-grey">Portal admin access</span>
                </div>
            </div>
            <div class="flex gap-8">
                <a href="<?= APP_URL ?>/investor/dashboard.php"
                   class="btn-secondary btn-sm">Investor Side →</a>
            </div>
        </div>

        <!-- Pending requests alert -->
        <?php if ($pendingCount > 0): ?>
        <div class="pending-alert">
            <div>
                <div class="pending-alert-text">
                    ⚡ <?= $pendingCount ?> pending wallet request<?= $pendingCount !== 1 ? 's' : '' ?>
                    require<?= $pendingCount === 1 ? 's' : '' ?> your attention
                </div>
                <div class="pending-alert-sub">
                    <?= count(array_filter($pendingRequests, fn($t) => $t['type'] === 'deposit')) ?> deposit<?= '' ?>,
                    <?= count(array_filter($pendingRequests, fn($t) => $t['type'] === 'withdrawal')) ?> withdrawal
                </div>
            </div>
            <a href="<?= APP_URL ?>/admin/wallet/pending.php" class="btn-primary">
                Review Now →
            </a>
        </div>
        <?php endif; ?>

        <!-- Unread messages alert -->
        <?php if ($unreadMessages > 0): ?>
        <div class="pending-alert" style="border-color:#1a3a6a;background:#0a1525;">
            <div>
                <div class="pending-alert-text" style="color:var(--blue);">
                    💬 <?= $unreadMessages ?> unread message<?= $unreadMessages !== 1 ? 's' : '' ?>
                </div>
                <div class="pending-alert-sub">From investors awaiting a reply</div>
            </div>
            <a href="<?= APP_URL ?>/admin/messages/index.php" class="btn-secondary">
                View Messages →
            </a>
        </div>
        <?php endif; ?>

        <!-- Company stats -->
        <div class="stats-grid" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-label">
                    Equity Under Management
                    <span style="font-size:16px;">🏦</span>
                </div>
                <div class="stat-value text-gold">
                    <?= formatCurrency($equityUnderMgmt) ?>
                </div>
                <div class="stat-sub">Total wallet balances</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">
                    Active Investments
                    <span style="font-size:16px;">💼</span>
                </div>
                <div class="stat-value">
                    <?= number_format((int)$investmentStats['active_investments']) ?>
                </div>
                <div class="stat-sub">
                    <?= number_format((int)$investmentStats['active_investors']) ?> investors
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">
                    Average Investment
                    <span style="font-size:16px;">🔗</span>
                </div>
                <div class="stat-value">
                    <?= formatCurrency((float)$investmentStats['avg_investment']) ?>
                </div>
                <div class="stat-sub">Across active investments</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">
                    Invested All-Time
                    <span style="font-size:16px;">💎</span>
                </div>
                <div class="stat-value">
                    <?= formatCurrency($investedAllTime) ?>
                </div>
                <div class="stat-sub">Company aggregate</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">
                    Distributions
                    <span style="font-size:16px;">📤</span>
                </div>
                <div class="stat-value">
                    <?= formatCurrency((float)$distStats['total_distributed']) ?>
                </div>
                <div class="stat-sub">Company aggregate</div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="quick-actions">
            <a href="<?= APP_URL ?>/admin/users/create.php" class="quick-action">
                <span class="quick-action-icon">👤</span>
                <span class="quick-action-label">Invite Investor</span>
                <span class="quick-action-sub">Send invite link</span>
            </a>
            <a href="<?= APP_URL ?>/admin/projects/create.php" class="quick-action">
                <span class="quick-action-icon">📍</span>
                <span class="quick-action-label">New Project</span>
                <span class="quick-action-sub">Add investment opportunity</span>
            </a>
            <a href="<?= APP_URL ?>/admin/investments/create.php" class="quick-action">
                <span class="quick-action-icon">💰</span>
                <span class="quick-action-label">Record Investment</span>
                <span class="quick-action-sub">Log investor allocation</span>
            </a>
            <a href="<?= APP_URL ?>/admin/distributions/create.php" class="quick-action">
                <span class="quick-action-icon">📤</span>
                <span class="quick-action-label">Record Distribution</span>
                <span class="quick-action-sub">Pay out investors</span>
            </a>
            <a href="<?= APP_URL ?>/admin/documents/upload.php" class="quick-action">
                <span class="quick-action-icon">📄</span>
                <span class="quick-action-label">Upload Document</span>
                <span class="quick-action-sub">Add to library</span>
            </a>
            <a href="<?= APP_URL ?>/admin/posts/create.php" class="quick-action">
                <span class="quick-action-icon">📰</span>
                <span class="quick-action-label">Write News Post</span>
                <span class="quick-action-sub">Publish update</span>
            </a>
            <a href="<?= APP_URL ?>/admin/wallet/pending.php" class="quick-action">
                <span class="quick-action-icon">💳</span>
                <span class="quick-action-label">Wallet Requests</span>
                <span class="quick-action-sub">
                    <?= $pendingCount > 0 ? "{$pendingCount} pending" : 'No pending' ?>
                </span>
            </a>
            <a href="<?= APP_URL ?>/admin/messages/index.php" class="quick-action">
                <span class="quick-action-icon">💬</span>
                <span class="quick-action-label">Messages</span>
                <span class="quick-action-sub">
                    <?= $unreadMessages > 0 ? "{$unreadMessages} unread" : 'All read' ?>
                </span>
            </a>
        </div>

        <div class="dash-grid">

            <!-- Left column -->
            <div>

                <!-- Recent investments -->
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Recent Investments</div>
                            <div class="card-sub">Latest recorded allocations</div>
                        </div>
                        <a href="<?= APP_URL ?>/admin/investments/index.php"
                           class="btn-secondary btn-sm">View All</a>
                    </div>
                    <?php if (empty($recentInvestments)): ?>
                    <p style="font-size:13px;color:var(--muted);padding:8px 0;">
                        No investments recorded yet.
                    </p>
                    <?php else: ?>
                    <?php foreach ($recentInvestments as $inv): ?>
                    <div class="activity-row">
                        <div class="flex-center gap-8">
                            <div class="avatar avatar-sm">
                                <?= strtoupper(substr($inv['investor_first'] ?? '?', 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13px;">
                                    <?= e($inv['investor_first'] . ' ' . $inv['investor_last']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--muted);">
                                    <?= e($inv['project_title']) ?>
                                </div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:700;color:var(--gold);">
                                <?= formatMoney((float)$inv['amount']) ?>
                            </div>
                            <div style="font-size:11px;color:var(--muted);">
                                <?= timeAgo($inv['invested_at']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent transactions -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Recent Transactions</div>
                            <div class="card-sub">Deposits and withdrawals</div>
                        </div>
                        <a href="<?= APP_URL ?>/admin/wallet/index.php"
                           class="btn-secondary btn-sm">View All</a>
                    </div>
                    <?php if (empty($recentTx)): ?>
                    <p style="font-size:13px;color:var(--muted);padding:8px 0;">
                        No transactions yet.
                    </p>
                    <?php else: ?>
                    <?php foreach ($recentTx as $tx): ?>
                    <div class="activity-row">
                        <div class="flex-center gap-8">
                            <span style="font-size:18px;">
                                <?= $tx['type'] === 'deposit' ? '↓' : '↑' ?>
                            </span>
                            <div>
                                <div style="font-weight:600;font-size:13px;">
                                    <?= e($tx['investor_first'] . ' ' . $tx['investor_last']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--muted);">
                                    <?= ucfirst($tx['type']) ?> · <?= timeAgo($tx['created_at']) ?>
                                </div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:700;
                                color:<?= $tx['type'] === 'deposit' ? 'var(--green)' : 'var(--orange)' ?>;">
                                <?= $tx['type'] === 'deposit' ? '+' : '-' ?><?= formatMoney((float)$tx['amount']) ?>
                            </div>
                            <span class="badge <?= WalletTransaction::statusBadge($tx['status']) ?>"
                                  style="font-size:10px;">
                                <?= WalletTransaction::statusLabel($tx['status']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Right column — notifications -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">🔔 Notifications</div>
                            <?php if ($unreadNotifs > 0): ?>
                            <div class="card-sub" style="color:var(--gold);">
                                <?= $unreadNotifs ?> unread
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-8">
                            <?php if ($unreadNotifs > 0): ?>
                            <a href="<?= APP_URL ?>/app/Actions/investor/mark-notifications-read.php?mark_all=1"
                               class="btn-secondary btn-sm">Mark all read</a>
                            <?php endif; ?>
                            <a href="<?= APP_URL ?>/admin/notifications/index.php"
                               class="btn-secondary btn-sm">All</a>
                        </div>
                    </div>

                    <?php if (empty($notifications)): ?>
                    <div style="text-align:center;padding:24px;color:var(--muted);font-size:13px;">
                        No notifications yet.
                    </div>
                    <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:1px;">
                        <?php foreach ($notifications as $notif): ?>
                        <div style="display:flex;align-items:flex-start;gap:10px;
                                    padding:10px 0;border-bottom:1px solid var(--border);
                                    background:<?= !$notif['is_read'] ? '#1a1500' : 'transparent' ?>;">
                            <span style="font-size:18px;flex-shrink:0;margin-top:1px;">
                                <?= Notification::icon($notif['type']) ?>
                            </span>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:<?= !$notif['is_read'] ? '700' : '500' ?>;">
                                    <?= e($notif['title']) ?>
                                </div>
                                <div style="font-size:12px;color:var(--muted);
                                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= truncate(e($notif['message']), 60) ?>
                                </div>
                                <div style="font-size:10px;color:var(--muted2);margin-top:3px;">
                                    <?= timeAgo($notif['created_at']) ?>
                                </div>
                            </div>
                            <?php if (!$notif['is_read']): ?>
                            <div style="width:7px;height:7px;background:var(--gold);
                                        border-radius:50%;flex-shrink:0;margin-top:5px;"></div>
                            <?php endif; ?>
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