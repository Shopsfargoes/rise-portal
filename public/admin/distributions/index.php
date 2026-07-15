<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Distributions List
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Distribution;
use Rise\Models\Project;

Auth::requireAdmin();

$search    = trim(get('search', ''));
$projectId = (int) get('project_id', 0);
$page      = max(1, (int) get('page', 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$filters = array_filter([
    'search'     => $search,
    'project_id' => $projectId ?: null,
]);

$total         = Distribution::count($filters);
$distributions = Distribution::findAll($filters, $perPage, $offset);
$projects      = Project::findAll([], 100, 0);
$summary       = Distribution::companySummary();
$totalPages    = (int) ceil($total / $perPage);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Distributions — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Distributions</h1>
                <p class="page-sub">
                    <?= number_format($total) ?> distribution<?= $total !== 1 ? 's' : '' ?> recorded
                </p>
            </div>
            <a href="<?= APP_URL ?>/admin/distributions/create.php" class="btn-primary">
                + Record Distribution
            </a>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <!-- Summary stats -->
        <div class="stats-grid" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-label">Total Distributed</div>
                <div class="stat-value text-gold">
                    <?= formatCurrency((float)$summary['total_distributed']) ?>
                </div>
                <div class="stat-sub">All time</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Distributions</div>
                <div class="stat-value"><?= (int)$summary['total_distributions'] ?></div>
                <div class="stat-sub">Across all projects</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Projects Paid Out</div>
                <div class="stat-value"><?= (int)$summary['projects_with_distributions'] ?></div>
                <div class="stat-sub">Projects with distributions</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending Payments</div>
                <div class="stat-value" style="color:var(--orange);">
                    <?= formatCurrency((float)$summary['pending_amount']) ?>
                </div>
                <div class="stat-sub">Not yet marked paid</div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">All Distributions</div>
                    <div class="table-sub">Per-project distribution history</div>
                </div>

                <form method="GET" class="flex gap-8" style="flex-wrap:wrap;">
                    <div class="search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search"
                               placeholder="Search distributions..."
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
                    <button type="submit" class="btn-secondary btn-sm">Filter</button>
                    <?php if ($search || $projectId): ?>
                    <a href="<?= APP_URL ?>/admin/distributions/index.php"
                       class="btn-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Description</th>
                            <th class="text-right">Total Amount</th>
                            <th class="text-right">Recipients</th>
                            <th>Paid Out</th>
                            <th>Date</th>
                            <th>Created By</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($distributions)): ?>
                        <tr>
                            <td colspan="8" class="table-empty">
                                No distributions recorded yet.
                                <br>
                                <a href="<?= APP_URL ?>/admin/distributions/create.php"
                                   style="color:var(--gold);">Record the first distribution →</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($distributions as $dist): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;color:var(--text);font-size:13px;">
                                    <?= e($dist['project_title']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--muted);">
                                    <?= e($dist['project_location'] ?? '') ?>
                                </div>
                            </td>
                            <td style="color:var(--muted);font-size:12px;max-width:200px;">
                                <?= $dist['description'] ? truncate(e($dist['description']), 60) : '—' ?>
                            </td>
                            <td class="text-right">
                                <span style="font-weight:700;color:var(--gold);font-size:14px;">
                                    <?= formatMoney((float)$dist['total_amount']) ?>
                                </span>
                            </td>
                            <td class="text-right" style="color:var(--muted);">
                                <?= (int)$dist['recipient_count'] ?>
                            </td>
                            <td>
                                <?php
                                $paidCount  = (int)$dist['paid_count'];
                                $totalCount = (int)$dist['recipient_count'];
                                $allPaid    = $paidCount === $totalCount && $totalCount > 0;
                                ?>
                                <span class="badge <?= $allPaid ? 'badge-green' : 'badge-orange' ?>">
                                    <?= $paidCount ?>/<?= $totalCount ?> paid
                                </span>
                            </td>
                            <td style="color:var(--muted);font-size:12px;white-space:nowrap;">
                                <?= formatDate($dist['distribution_date']) ?>
                            </td>
                            <td style="color:var(--muted);font-size:12px;">
                                <?= e(trim(($dist['created_by_first'] ?? '') . ' ' . ($dist['created_by_last'] ?? ''))) ?>
                            </td>
                            <td>
                                <a href="<?= APP_URL ?>/admin/distributions/index.php?view=<?= $dist['id'] ?>"
                                   class="btn-secondary btn-sm">View</a>
                            </td>
                        </tr>

                        <!-- Inline detail row (if viewing this distribution) -->
                        <?php if (get('view') == $dist['id']): ?>
                        <?php $items = \Rise\Models\Distribution::getItems($dist['id']); ?>
                        <tr>
                            <td colspan="8" style="padding:0;background:var(--surface2);">
                                <div style="padding:16px 20px;">
                                    <div style="font-size:12px;font-weight:700;color:var(--muted);
                                                letter-spacing:1px;text-transform:uppercase;
                                                margin-bottom:12px;">
                                        Distribution Breakdown — <?= count($items) ?> investors
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:8px;">
                                        <?php foreach ($items as $item): ?>
                                        <div style="display:flex;align-items:center;justify-content:space-between;
                                                    padding:10px 14px;background:var(--surface);
                                                    border:1px solid var(--border);border-radius:8px;">
                                            <div class="flex-center gap-8">
                                                <div class="avatar avatar-sm">
                                                    <?= strtoupper(substr($item['investor_first'] ?? '?', 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div style="font-size:13px;font-weight:600;">
                                                        <?= e($item['investor_first'] . ' ' . $item['investor_last']) ?>
                                                    </div>
                                                    <div style="font-size:11px;color:var(--muted);">
                                                        Invested: <?= formatMoney((float)$item['investment_amount']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="display:flex;align-items:center;gap:12px;">
                                                <span style="font-weight:700;font-size:15px;color:var(--green);">
                                                    +<?= formatMoney((float)$item['amount']) ?>
                                                </span>
                                                <span class="badge <?= $item['status'] === 'paid' ? 'badge-green' : 'badge-orange' ?>">
                                                    <?= ucfirst($item['status']) ?>
                                                </span>
                                                <?php if ($item['status'] === 'pending'): ?>
                                                <form method="POST"
                                                      action="<?= APP_URL ?>/app/Actions/admin/mark-distribution-paid.php">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>"/>
                                                    <input type="hidden" name="distribution_id" value="<?= $dist['id'] ?>"/>
                                                    <button type="submit" class="btn-secondary btn-sm">
                                                        Mark Paid
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php
                                    $pendingItems = array_filter($items, fn($i) => $i['status'] === 'pending');
                                    if (!empty($pendingItems)):
                                    ?>
                                    <form method="POST"
                                          action="<?= APP_URL ?>/app/Actions/admin/mark-distribution-paid.php"
                                          style="margin-top:12px;"
                                          onsubmit="return confirm('Mark all items as paid?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="distribution_id" value="<?= $dist['id'] ?>"/>
                                        <input type="hidden" name="mark_all" value="1"/>
                                        <button type="submit" class="btn-primary btn-sm">
                                            ✓ Mark All as Paid
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>

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
