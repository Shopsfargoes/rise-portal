<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Single Investor Transaction History
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\WalletTransaction;
use Rise\Models\WalletBalance;

Auth::requireAdmin();

$userId = (int) get('user_id');
if (!$userId) redirect('/admin/transactions/index.php');

// Fetch investor
$investor = db()->fetchOne(
    "SELECT u.id, u.email, u.status,
            p.first_name, p.last_name, p.avatar_path
     FROM users u
     LEFT JOIN user_profiles p ON p.user_id = u.id
     WHERE u.id = ? AND u.role = 'investor' LIMIT 1",
    [$userId]
);

if (!$investor) redirect('/admin/transactions/index.php');

$balance      = WalletBalance::get($userId);
$transactions = WalletTransaction::findAll(['user_id' => $userId], 100, 0);

// Distributions
$distributions = db()->fetchAll(
    "SELECT di.*, d.distribution_date, p.title AS project_title
     FROM distribution_items di
     JOIN distributions d ON d.id = di.distribution_id
     JOIN projects p ON p.id = d.project_id
     WHERE di.user_id = ?
     ORDER BY d.distribution_date DESC",
    [$userId]
);

// Build unified timeline
$timeline = [];

foreach ($transactions as $tx) {
    $timeline[] = [
        'date'   => $tx['created_at'],
        'type'   => $tx['type'],        // 'deposit' | 'withdrawal'
        'group'  => 'transaction',
        'data'   => $tx,
    ];
}

foreach ($distributions as $dist) {
    $timeline[] = [
        'date'   => $dist['distribution_date'] . ' 00:00:00',
        'type'   => 'distribution',
        'group'  => 'distribution',
        'data'   => $dist,
    ];
}

// Sort newest first
usort($timeline, fn($a, $b) => strcmp($b['date'], $a['date']));

// Totals
$totalDeposited    = array_sum(array_column(array_filter($transactions, fn($t) => $t['type'] === 'deposit'    && $t['status'] === 'confirmed'), 'amount'));
$totalWithdrawn    = array_sum(array_column(array_filter($transactions, fn($t) => $t['type'] === 'withdrawal' && $t['status'] === 'confirmed'), 'amount'));
$totalDistributed  = array_sum(array_column($distributions, 'amount'));

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= e($investor['first_name'] . ' ' . $investor['last_name']) ?> — Transactions</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        /* Timeline */
        .tx-timeline { position:relative; padding-left:36px; }
        .tx-timeline::before {
            content:''; position:absolute;
            left:14px; top:0; bottom:0;
            width:2px; background:var(--border);
        }

        .tx-item { position:relative; margin-bottom:16px; }

        .tx-dot {
            position:absolute;
            left:-26px; top:14px;
            width:14px; height:14px;
            border-radius:50%;
            border:2px solid var(--bg);
            z-index:1;
        }

        .tx-dot.deposit      { background:var(--green); }
        .tx-dot.withdrawal   { background:var(--orange); }
        .tx-dot.distribution { background:var(--blue); }

        .tx-card {
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:10px;
            padding:14px 16px;
        }

        .tx-card.deposit-card      { border-left:3px solid var(--green); }
        .tx-card.withdrawal-card   { border-left:3px solid var(--orange); }
        .tx-card.distribution-card { border-left:3px solid var(--blue); }

        .tx-row {
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:12px;
        }

        .tx-label {
            font-size:13px; font-weight:700;
            margin-bottom:3px;
        }

        .tx-meta {
            font-size:11px;
            color:var(--muted);
            line-height:1.5;
        }

        .tx-amount {
            font-size:18px;
            font-weight:900;
            white-space:nowrap;
        }

        .tx-amount.deposit      { color:var(--green); }
        .tx-amount.withdrawal   { color:var(--orange); }
        .tx-amount.distribution { color:var(--blue); }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div style="display:flex;align-items:center;gap:16px;">
                <div class="avatar avatar-lg">
                    <?php if (!empty($investor['avatar_path'])): ?>
                    <img src="<?= e($investor['avatar_path']) ?>" alt=""/>
                    <?php else: ?>
                    <?= strtoupper(substr($investor['first_name'] ?? '?', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="<?= APP_URL ?>/admin/users/view.php?id=<?= $userId ?>"
                       class="back-link">← Back to Investor Profile</a>
                    <h1 class="page-title">
                        <?= e($investor['first_name'] . ' ' . $investor['last_name']) ?>
                    </h1>
                    <div style="font-size:13px;color:var(--muted);margin-top:3px;">
                        <?= e($investor['email']) ?>
                    </div>
                </div>
            </div>
            <a href="<?= APP_URL ?>/admin/transactions/index.php" class="btn-secondary">
                All Investors →
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-label">Current Balance</div>
                <div class="stat-value text-gold"><?= formatMoney($balance) ?></div>
                <div class="stat-sub">Wallet balance</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Deposited</div>
                <div class="stat-value text-green"><?= formatCurrency($totalDeposited) ?></div>
                <div class="stat-sub">Confirmed deposits</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Withdrawn</div>
                <div class="stat-value"><?= formatCurrency($totalWithdrawn) ?></div>
                <div class="stat-sub">Confirmed withdrawals</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Distributed</div>
                <div class="stat-value"><?= formatCurrency($totalDistributed) ?></div>
                <div class="stat-sub">From investments</div>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <!-- Unified timeline -->
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Full Financial Timeline</div>
                    <div class="card-sub">
                        <?= count($timeline) ?> events —
                        deposits, withdrawals and distributions combined
                    </div>
                </div>
                <div class="flex gap-8">
                    <!-- Legend -->
                    <span style="font-size:11px;color:var(--green);">● Deposit</span>
                    <span style="font-size:11px;color:var(--orange);">● Withdrawal</span>
                    <span style="font-size:11px;color:var(--blue);">● Distribution</span>
                </div>
            </div>

            <?php if (empty($timeline)): ?>
            <p style="color:var(--muted);font-size:13px;padding:16px 0;">
                No financial activity recorded yet.
            </p>
            <?php else: ?>

            <div class="tx-timeline" style="margin-top:16px;">
                <?php foreach ($timeline as $item): ?>
                <?php
                    $group = $item['group'];
                    $d     = $item['data'];
                    $type  = $item['type'];
                ?>
                <div class="tx-item">
                    <div class="tx-dot <?= $type ?>"></div>
                    <div class="tx-card <?= $type ?>-card">
                        <div class="tx-row">
                            <div style="flex:1;">
                                <?php if ($group === 'transaction'): ?>
                                    <div class="tx-label">
                                        <?= $type === 'deposit' ? '↓ Deposit' : '↑ Withdrawal' ?>
                                        <span class="badge <?= WalletTransaction::statusBadge($d['status']) ?>"
                                              style="font-size:10px;margin-left:6px;">
                                            <?= WalletTransaction::statusLabel($d['status']) ?>
                                        </span>
                                    </div>
                                    <div class="tx-meta">
                                        <?= formatDateTime($d['created_at']) ?>
                                        <?php if ($d['note']): ?>
                                            · Note: <?= e($d['note']) ?>
                                        <?php endif; ?>
                                        <?php if ($d['admin_note']): ?>
                                            <br>Admin: <?= e($d['admin_note']) ?>
                                        <?php endif; ?>
                                        <?php if ($d['reference']): ?>
                                            <br>Ref: <code><?= e($d['reference']) ?></code>
                                        <?php endif; ?>
                                    </div>

                                <?php elseif ($group === 'distribution'): ?>
                                    <div class="tx-label">📤 Distribution — <?= e($d['project_title']) ?></div>
                                    <div class="tx-meta">
                                        <?= formatDate($d['distribution_date']) ?>
                                        · <span class="badge <?= $d['status'] === 'paid' ? 'badge-green' : 'badge-orange' ?>">
                                            <?= ucfirst($d['status']) ?>
                                          </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tx-amount <?= $type ?>">
                                <?php if ($group === 'transaction'): ?>
                                    <?= $type === 'deposit' ? '+' : '–' ?><?= formatMoney((float)$d['amount']) ?>
                                <?php else: ?>
                                    +<?= formatMoney((float)$d['amount']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>