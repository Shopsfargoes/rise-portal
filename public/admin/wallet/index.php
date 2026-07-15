<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: All Wallet Transactions
// ============================================================
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\WalletTransaction;

Auth::requireAdmin();

$search  = trim(get('search', ''));
$type    = get('type', '');
$status  = get('status', '');
$page    = max(1, (int) get('page', 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$filters = array_filter([
    'search' => $search,
    'type'   => $type   ?: null,
    'status' => $status ?: null,
]);

$total        = WalletTransaction::count($filters);
$transactions = WalletTransaction::findAll($filters, $perPage, $offset);
$totalPages   = (int) ceil($total / $perPage);

// Summary totals
$totals = db()->fetchOne(
    "SELECT
        COALESCE(SUM(CASE WHEN type='deposit'    AND status='confirmed' THEN amount END), 0) AS total_deposited,
        COALESCE(SUM(CASE WHEN type='withdrawal' AND status='confirmed' THEN amount END), 0) AS total_withdrawn,
        COUNT(CASE WHEN status='pending'   THEN 1 END) AS pending_count,
        COUNT(CASE WHEN status='confirmed' THEN 1 END) AS confirmed_count
     FROM wallet_transactions"
) ?? [];

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>All Transactions — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Wallet Transactions</h1>
                <p class="page-sub"><?= number_format($total) ?> transaction<?= $total !== 1 ? 's' : '' ?> total</p>
            </div>
            <a href="<?= APP_URL ?>/admin/wallet/pending.php" class="btn-primary">
                View Pending Requests
            </a>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <!-- Summary stats -->
        <div class="stats-grid" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-label">Total Deposited</div>
                <div class="stat-value text-gold"><?= formatCurrency((float)$totals['total_deposited']) ?></div>
                <div class="stat-sub">Confirmed deposits</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Withdrawn</div>
                <div class="stat-value"><?= formatCurrency((float)$totals['total_withdrawn']) ?></div>
                <div class="stat-sub">Confirmed withdrawals</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Net Flow</div>
                <div class="stat-value" style="color:<?= ((float)$totals['total_deposited'] - (float)$totals['total_withdrawn']) >= 0 ? 'var(--green)' : 'var(--red)' ?>">
                    <?= formatCurrency((float)$totals['total_deposited'] - (float)$totals['total_withdrawn']) ?>
                </div>
                <div class="stat-sub">Deposits minus withdrawals</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= (int)$totals['pending_count'] ?></div>
                <div class="stat-sub">
                    <a href="<?= APP_URL ?>/admin/wallet/pending.php"
                       style="color:var(--gold);font-size:11px;">Review now →</a>
                </div>
            </div>
        </div>

        <!-- Transactions table -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">All Transactions</div>
                    <div class="table-sub">Deposits and withdrawals across all investors</div>
                </div>

                <form method="GET" class="flex gap-8" style="flex-wrap:wrap;">
                    <div class="search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search"
                               placeholder="Search investor or reference..."
                               value="<?= e($search) ?>"/>
                    </div>
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
                    <button type="submit" class="btn-secondary btn-sm">Filter</button>
                    <?php if ($search || $type || $status): ?>
                    <a href="<?= APP_URL ?>/admin/wallet/index.php" class="btn-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Investor</th>
                            <th>Type</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Actioned By</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="8" class="table-empty">No transactions found.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td>
                                <div class="flex-center gap-8">
                                    <div class="avatar avatar-sm">
                                        <?php if (!empty($tx['avatar_path'])): ?>
                                        <img src="<?= e($tx['avatar_path']) ?>" alt=""/>
                                        <?php else: ?>
                                        <?= strtoupper(substr($tx['investor_first'] ?? '?', 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;font-size:13px;color:var(--text);">
                                            <?= e($tx['investor_first'] . ' ' . $tx['investor_last']) ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--muted);">
                                            <?= e($tx['investor_email']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $tx['type'] === 'deposit' ? 'badge-green' : 'badge-orange' ?>">
                                    <?= $tx['type'] === 'deposit' ? '↓ Deposit' : '↑ Withdrawal' ?>
                                </span>
                            </td>
                            <td class="text-right" style="font-weight:700;font-size:14px;
                                color:<?= $tx['type'] === 'deposit' ? 'var(--green)' : 'var(--orange)' ?>;">
                                <?= $tx['type'] === 'deposit' ? '+' : '–' ?><?= formatMoney((float)$tx['amount']) ?>
                            </td>
                            <td>
                                <span class="badge <?= WalletTransaction::statusBadge($tx['status']) ?>">
                                    <?= WalletTransaction::statusLabel($tx['status']) ?>
                                </span>
                            </td>
                            <td style="font-family:monospace;font-size:11px;color:var(--muted);">
                                <?= e($tx['reference'] ?? '—') ?>
                            </td>
                            <td style="font-size:12px;color:var(--muted);">
                                <?= $tx['admin_first']
                                    ? e($tx['admin_first'] . ' ' . $tx['admin_last'])
                                    : '—' ?>
                            </td>
                            <td style="font-size:12px;color:var(--muted);">
                                <?= formatDateTime($tx['created_at']) ?>
                            </td>
                            <td>
                                <?php if (in_array($tx['status'], ['pending','contacted'])): ?>
                                <a href="<?= APP_URL ?>/admin/wallet/pending.php"
                                   class="btn-secondary btn-sm">Action</a>
                                <?php else: ?>
                                <a href="<?= APP_URL ?>/admin/transactions/investor.php?user_id=<?= $tx['user_id'] ?>"
                                   class="btn-secondary btn-sm">History</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>"
                   class="page-btn">← Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>"
                   class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>"
                   class="page-btn">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
