<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Investor List
// ============================================================
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

use Rise\Core\Auth;

Auth::requireAdmin();

// ── Filters ───────────────────────────────────────────────
$search = trim(get('search', ''));
$status = get('status', '');
$page   = max(1, (int) get('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// ── Build query ───────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = "(p.first_name LIKE ? OR p.last_name LIKE ? OR u.email LIKE ?)";
    $like     = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (in_array($status, ['active', 'pending', 'suspended'])) {
    $where[]  = "u.status = ?";
    $params[] = $status;
}

// Only investors (not other admins) — unless admin wants to see all
$where[] = "u.role = 'investor'";

$whereSQL = implode(' AND ', $where);

// Total count
$total = (int) db()->fetchColumn(
    "SELECT COUNT(*) FROM users u
     LEFT JOIN user_profiles p ON p.user_id = u.id
     WHERE {$whereSQL}",
    $params
);

// Paginated results
$investors = db()->fetchAll(
    "SELECT u.id, u.uuid, u.email, u.status, u.role, u.last_login, u.created_at,
            p.first_name, p.last_name, p.phone, p.accredited, p.avatar_path,
            COALESCE(wb.balance, 0) AS wallet_balance,
            COUNT(DISTINCT i.id) AS investment_count
     FROM users u
     LEFT JOIN user_profiles p ON p.user_id = u.id
     LEFT JOIN wallet_balances wb ON wb.user_id = u.id
     LEFT JOIN investments i ON i.user_id = u.id AND i.status = 'active'
     WHERE {$whereSQL}
     GROUP BY u.id
     ORDER BY u.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$totalPages = (int) ceil($total / $perPage);

$statusBadge = [
    'active'    => 'badge-green',
    'pending'   => 'badge-orange',
    'suspended' => 'badge-red',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Investors — <?= APP_NAME ?></title>
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
                <h1 class="page-title">Investors</h1>
                <p class="page-sub"><?= number_format($total) ?> registered investor<?= $total !== 1 ? 's' : '' ?></p>
            </div>
            <a href="<?= APP_URL ?>/admin/users/create.php" class="btn-primary">
                + Invite Investor
            </a>
        </div>

        <!-- Filters -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">All Investors</div>
                    <div class="table-sub">Manage accounts, view portfolios, track activity</div>
                </div>
                <div class="flex gap-8" style="flex-wrap:wrap;">
                    <!-- Search -->
                    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap;">
                        <div class="search-wrap">
                            <span class="search-icon">🔍</span>
                            <input type="text" name="search"
                                   placeholder="Search name or email..."
                                   value="<?= e($search) ?>"/>
                        </div>
                        <!-- Status filter -->
                        <select name="status" onchange="this.form.submit()"
                                style="width:auto; min-width:140px;">
                            <option value="">All Status</option>
                            <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                            <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>Pending</option>
                            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                        <button type="submit" class="btn-secondary btn-sm">Filter</button>
                        <?php if ($search || $status): ?>
                            <a href="<?= APP_URL ?>/admin/users/index.php" class="btn-secondary btn-sm">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Investor</th>
                            <th>Status</th>
                            <th>Accredited</th>
                            <th class="text-right">Wallet Balance</th>
                            <th class="text-right">Investments</th>
                            <th>Last Login</th>
                            <th>Joined</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($investors)): ?>
                        <tr>
                            <td colspan="8" class="table-empty">
                                <?= $search ? "No investors match \"" . e($search) . "\"" : 'No investors yet.' ?>
                                <?php if (!$search): ?>
                                    <br><a href="<?= APP_URL ?>/admin/users/create.php" style="color:var(--gold);">Invite the first investor →</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($investors as $inv): ?>
                        <tr>
                            <!-- Investor name + email -->
                            <td>
                                <div class="flex-center gap-8">
                                    <div class="avatar avatar-sm">
                                        <?php if (!empty($inv['avatar_path'])): ?>
                                            <img src="<?= e($inv['avatar_path']) ?>" alt=""/>
                                        <?php else: ?>
                                            <?= strtoupper(substr($inv['first_name'] ?? '?', 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="td-primary" style="font-weight:600; color:var(--text);">
                                            <?= e($inv['first_name'] . ' ' . $inv['last_name']) ?>
                                        </div>
                                        <div style="font-size:11px; color:var(--muted);">
                                            <?= e($inv['email']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="badge <?= $statusBadge[$inv['status']] ?? 'badge-grey' ?>">
                                    <?= ucfirst(e($inv['status'])) ?>
                                </span>
                            </td>

                            <!-- Accredited -->
                            <td>
                                <?php if ($inv['accredited']): ?>
                                    <span class="badge badge-green">✓ Verified</span>
                                <?php else: ?>
                                    <span class="badge badge-grey">Unverified</span>
                                <?php endif; ?>
                            </td>

                            <!-- Wallet balance -->
                            <td class="text-right td-primary">
                                <?= formatMoney((float)$inv['wallet_balance']) ?>
                            </td>

                            <!-- Active investments -->
                            <td class="text-right">
                                <?= (int)$inv['investment_count'] ?>
                            </td>

                            <!-- Last login -->
                            <td style="color:var(--muted); font-size:12px;">
                                <?= $inv['last_login'] ? timeAgo($inv['last_login']) : 'Never' ?>
                            </td>

                            <!-- Joined -->
                            <td style="color:var(--muted); font-size:12px;">
                                <?= formatDate($inv['created_at']) ?>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div class="flex gap-8">
                                    <a href="<?= APP_URL ?>/admin/users/view.php?id=<?= $inv['id'] ?>"
                                       class="btn-secondary btn-sm">View</a>
                                    <a href="<?= APP_URL ?>/admin/users/edit.php?id=<?= $inv['id'] ?>"
                                       class="btn-secondary btn-sm">Edit</a>
                                </div>
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
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
                       class="page-btn">← Prev</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
                       class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
                       class="page-btn">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- /table-card -->

    </div>
</div>

</body>
</html>
