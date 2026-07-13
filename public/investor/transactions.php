<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Transaction History
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\WalletBalance;
use Rise\Models\WalletTransaction;

Auth::requireInvestor();

$userId  = Auth::id();
$type    = get('type', '');
$status  = get('status', '');
$page    = max(1, (int) get('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$filters = array_filter([
    'user_id' => $userId,
    'type'    => $type   ?: null,
    'status'  => $status ?: null,
]);

$total        = WalletTransaction::count($filters);
$transactions = WalletTransaction::findAll($filters, $perPage, $offset);
$totalPages   = (int) ceil($total / $perPage);
$balance      = WalletBalance::get($userId);

// User totals
$totals = db()->fetchOne(
    "SELECT
        COALESCE(SUM(CASE WHEN type='deposit'    AND status='confirmed' THEN amount END), 0) AS total_deposited,
        COALESCE(SUM(CASE WHEN type='withdrawal' AND status='confirmed' THEN amount END), 0) AS total_withdrawn
     FROM wallet_transactions WHERE user_id = ?",
    [$userId]
) ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Transactions — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
</head>
<body class="investor-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-investor.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Transaction History</h1>
                <p class="page-sub">All your deposits, withdrawals and distributions</p>
            </div>
            <a href="<?= APP_URL ?>/investor/wallet.php" class="btn-primary">
                + New Request
            </a>
        </div>

        <!-- Summary -->
        <div class="stats-grid" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-label">Current Balance</div>
                <div class="stat-value text-gold"><?= formatMoney($balance) ?></div>
                <div class="stat-sub">Available in wallet</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Deposited</div>
                <div class="stat-value text-green"><?= formatCurrency((float)$totals['total_deposited']) ?></div>
                <div class="stat-sub">Confirmed deposits</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Withdrawn</div>
                <div class="stat-value"><?= formatCurrency((float)$totals['total_withdrawn']) ?></div>
                <div class="stat-sub">Confirmed withdrawals</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value"><?= number_format($total) ?></div>
                <div class="stat-sub">All time</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">All Transactions</div>
                    <div class="table-sub">Deposits and withdrawals</div>
                </div>
                <form method="GET" class="flex gap-8">
                    <select name="type" onchange="this.form.submit()"
                            style="width:auto;min-width:130px;">
                        <option value="">All Types</option>
                        <option value="deposit"    <?= $type === 'deposit'    ? 'selected' : '' ?>>Deposits</option>
                        <option value="withdrawal" <?= $type === 'withdrawal' ? 'selected' : '' ?>>Withdrawals</option>
                    </select>
                    <select name="status" onchange="this.form.submit()"
                            style="width:auto;min-width:130px;">
                        <option value="">All Status</option>
                        <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>Pending</option>
                        <option value="contacted" <?= $status === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                        <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="rejected"  <?= $status === 'rejected'  ? 'selected' : '' ?>>Rejected</option>
                    </select>
                    <?php if ($type || $status): ?>
                    <a href="<?= APP_URL ?>/investor/transactions.php"
                       class="btn-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                            <th>Your Note</th>
                            <th>Admin Note</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" class="table-empty">
                                No transactions yet.
                                <a href="<?= APP_URL ?>/investor/wallet.php"
                                   style="color:var(--gold);">Make a deposit →</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td>
                                <div>
                                    <span class="badge <?= $tx['type'] === 'deposit' ? 'badge-green' : 'badge-orange' ?>">
                                        <?= $tx['type'] === 'deposit' ? '↓ Deposit' : '↑ Withdrawal' ?>
                                    </span>
                                    <?php if ($tx['reference']): ?>
                                    <div style="font-size:10px;color:var(--muted);
                                                font-family:monospace;margin-top:3px;">
                                        Ref: <?= e($tx['reference']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-right">
                                <span style="font-weight:700;font-size:15px;
                                    color:<?= $tx['type'] === 'deposit' ? 'var(--green)' : 'var(--orange)' ?>;">
                                    <?= $tx['type'] === 'deposit' ? '+' : '–' ?><?= formatMoney((float)$tx['amount']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= WalletTransaction::statusBadge($tx['status']) ?>">
                                    <?= WalletTransaction::statusLabel($tx['status']) ?>
                                </span>
                                <?php if ($tx['confirmed_at']): ?>
                                <div style="font-size:10px;color:var(--muted);margin-top:3px;">
                                    <?= formatDate($tx['confirmed_at']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--muted);font-size:12px;max-width:160px;">
                                <?= $tx['note'] ? truncate(e($tx['note']), 60) : '—' ?>
                            </td>
                            <td style="color:var(--muted);font-size:12px;max-width:160px;">
                                <?= $tx['admin_note'] ? truncate(e($tx['admin_note']), 60) : '—' ?>
                            </td>
                            <td style="color:var(--muted);font-size:12px;white-space:nowrap;">
                                <?= formatDateTime($tx['created_at']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>"
                   class="page-btn">← Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                <a href="?page=<?= $i ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>"
                   class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>"
                   class="page-btn">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>