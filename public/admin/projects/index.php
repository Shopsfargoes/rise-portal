<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Projects List
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Project;

Auth::requireAdmin();

$search     = trim(get('search', ''));
$status     = get('status', '');
$categoryId = (int) get('category_id', 0);
$page       = max(1, (int) get('page', 1));
$perPage    = 15;
$offset     = ($page - 1) * $perPage;

$filters = array_filter([
    'search'      => $search,
    'status'      => $status,
    'category_id' => $categoryId ?: null,
]);

$total      = Project::count($filters);
$projects   = Project::findAll($filters, $perPage, $offset);
$categories = Project::getCategories();
$totalPages = (int) ceil($total / $perPage);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Projects — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Projects</h1>
                <p class="page-sub"><?= number_format($total) ?> project<?= $total !== 1 ? 's' : '' ?> total</p>
            </div>
            <a href="<?= APP_URL ?>/admin/projects/create.php" class="btn-primary">+ New Project</a>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">All Projects</div>
                    <div class="table-sub">Oil & gas investment opportunities</div>
                </div>
                <form method="GET" class="flex gap-8" style="flex-wrap:wrap;">
                    <div class="search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search"
                               placeholder="Search projects..."
                               value="<?= e($search) ?>"/>
                    </div>
                    <select name="status" onchange="this.form.submit()" style="width:auto;min-width:130px;">
                        <option value="">All Status</option>
                        <?php foreach (['open','closed','drilled','producing','abandoned'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>>
                            <?= Project::statusLabel($s) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="category_id" onchange="this.form.submit()" style="width:auto;min-width:140px;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-secondary btn-sm">Filter</button>
                    <?php if ($search || $status || $categoryId): ?>
                    <a href="<?= APP_URL ?>/admin/projects/index.php" class="btn-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th class="text-right">Project Cost</th>
                            <th class="text-right">Total Raised</th>
                            <th class="text-right">Investors</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="8" class="table-empty">
                                <?= $search ? 'No projects match "' . e($search) . '"' : 'No projects yet.' ?>
                                <?php if (!$search): ?>
                                <br><a href="<?= APP_URL ?>/admin/projects/create.php" style="color:var(--gold);">Create the first project →</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <!-- Cover image thumbnail -->
                                    <div style="width:44px;height:44px;border-radius:8px;overflow:hidden;
                                                background:var(--surface2);flex-shrink:0;border:1px solid var(--border);">
                                        <?php if (!empty($project['cover_image'])): ?>
                                        <img src="<?= e($project['cover_image']) ?>" alt=""
                                             style="width:100%;height:100%;object-fit:cover;"/>
                                        <?php else: ?>
                                        <div style="width:100%;height:100%;display:flex;align-items:center;
                                                    justify-content:center;font-size:18px;">📍</div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;color:var(--text);"><?= e($project['title']) ?></div>
                                        <div style="font-size:11px;color:var(--muted);"><?= e($project['location'] ?? '—') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--muted);font-size:12px;"><?= e($project['category_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge <?= Project::statusBadgeClass($project['status']) ?>">
                                    <?= Project::statusLabel($project['status']) ?>
                                </span>
                            </td>
                            <td class="text-right" style="color:var(--text2);"><?= formatCurrency((float)$project['project_cost']) ?></td>
                            <td class="text-right text-gold font-bold"><?= formatCurrency((float)$project['total_raised']) ?></td>
                            <td class="text-right" style="color:var(--text2);"><?= (int)$project['investor_count'] ?></td>
                            <td style="color:var(--muted);font-size:12px;"><?= formatDate($project['created_at']) ?></td>
                            <td>
                                <div class="flex gap-8">
                                    <a href="<?= APP_URL ?>/admin/projects/edit.php?id=<?= $project['id'] ?>"
                                       class="btn-secondary btn-sm">Edit</a>
                                    <a href="<?= APP_URL ?>/investor/project-detail.php?slug=<?= e($project['slug']) ?>"
                                       class="btn-secondary btn-sm" target="_blank">View</a>
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
                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
                   class="page-btn">← Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
                   class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
                   class="page-btn">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</body>
</html>