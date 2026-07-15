<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Investments List
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Investment;
use Rise\Models\Project;

Auth::requireAdmin();

$search    = trim(get('search', ''));
$projectId = (int) get('project_id', 0);
$status    = get('status', '');
$page      = max(1, (int) get('page', 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$filters = array_filter([
    'search'     => $search,
    'project_id' => $projectId ?: null,
    'status'     => $status    ?: null,
]);

$total       = Investment::count($filters);
$investments = Investment::findAll($filters, $perPage, $offset);
$projects    = Project::findAll([], 100, 0);
$stats       = Investment::companyStats();
$totalPages  = (int) ceil($total / $perPage);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Investments — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Investments</h1>
                <p class="page-sub"><?= number_format($total) ?> investment<?= $total !== 1 ? 's' : '' ?> recorded</p>
            </div>
            <a href="<?= APP_URL ?>/admin/investments/create.php" class="btn-primary">
                + Record Investment
            </a>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <!-- Company stats -->
        <div class="stats-grid" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-label">Active Investments</div>
                <div class="stat-value"><?= number_format((int)$stats['active_investments']) ?></div>
                <div class="stat-sub">Across all projects</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Investors</div>
                <div class="stat-value"><?= number_format((int)$stats['active_investors']) ?></div>
                <div class="stat-sub">Unique investors</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Invested</div>
                <div class="stat-value text-gold"><?= formatCurrency((float)$stats['total_invested']) ?></div>
                <div class="stat-sub">All active investments</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Investment</div>
                <div class="stat-value"><?= formatCurrency((float)$stats['avg_investment']) ?></div>
                <div class="stat-sub">Per investment</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Distributed</div>
                <div class="stat-value"><?= formatCurrency((float)$stats['total_distributed']) ?></div>
                <div class="stat-sub">Paid to investors</div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">All Investments</div>
                    <div class="table-sub">Admin-recorded investment entries</div>
                </div>

                <form method="GET" class="flex gap-8" style="flex-wrap:wrap;">
                    <div class="search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search"
                               placeholder="Search investor or project..."
                               value="<?= e($search) ?>"/>
                    </div>
                    <select name="project_id" onchange="this.form.submit()"
                            style="width:auto;min-width:160px;">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>"
                            <?= $projectId === (int)$proj['id'] ? 'selected' : '' ?>>
                            <?= e($proj['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" onchange="this.form.submit()"
                            style="width:auto;min-width:130px;">
                        <option value="">All Status</option>
                        <option value="active"  <?= $status === 'active'  ? 'selected' : '' ?>>Active</option>
                        <option value="exited"  <?= $status === 'exited'  ? 'selected' : '' ?>>Exited</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                    <button type="submit" class="btn-secondary btn-sm">Filter</button>
                    <?php if ($search || $projectId || $status): ?>
                    <a href="<?= APP_URL ?>/admin/investments/index.php"
                       class="btn-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Investor</th>
                            <th>Project</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Notes</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($investments)): ?>
                        <tr>
                            <td colspan="7" class="table-empty">
                                No investments recorded yet.
                                <br>
                                <a href="<?= APP_URL ?>/admin/investments/create.php"
                                   style="color:var(--gold);">Record the first investment →</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($investments as $inv): ?>
                        <tr>
                            <!-- Investor -->
                            <td>
                                <div class="flex-center gap-8">
                                    <div class="avatar avatar-sm">
                                        <?php if (!empty($inv['avatar_path'])): ?>
                                        <img src="<?= e($inv['avatar_path']) ?>" alt=""/>
                                        <?php else: ?>
                                        <?= strtoupper(substr($inv['investor_first'] ?? '?', 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;color:var(--text);font-size:13px;">
                                            <?= e($inv['investor_first'] . ' ' . $inv['investor_last']) ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--muted);">
                                            <?= e($inv['investor_email']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Project -->
                            <td>
                                <div style="font-weight:500;color:var(--text);font-size:13px;">
                                    <?= e($inv['project_title']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--muted);">
                                    <?= e($inv['project_location'] ?? '') ?>
                                </div>
                            </td>

                            <!-- Amount -->
                            <td class="text-right">
                                <span style="font-weight:700;color:var(--gold);font-size:14px;">
                                    <?= formatMoney((float)$inv['amount']) ?>
                                </span>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="badge <?= match($inv['status']) {
                                    'active'  => 'badge-green',
                                    'exited'  => 'badge-grey',
                                    'pending' => 'badge-orange',
                                    default   => 'badge-grey'
                                } ?>">
                                    <?= ucfirst($inv['status']) ?>
                                </span>
                            </td>

                            <!-- Date -->
                            <td style="color:var(--muted);font-size:12px;">
                                <?= formatDate($inv['invested_at']) ?>
                            </td>

                            <!-- Notes -->
                            <td style="color:var(--muted);font-size:12px;max-width:180px;">
                                <?= $inv['notes'] ? truncate(e($inv['notes']), 60) : '—' ?>
                            </td>

                            <!-- Actions -->
                            <td>
                                <a href="<?= APP_URL ?>/admin/users/view.php?id=<?= $inv['user_id'] ?>&tab=investments"
                                   class="btn-secondary btn-sm">View Investor</a>
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
                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>"
                   class="page-btn">← Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
                   class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>"
                   class="page-btn">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>
