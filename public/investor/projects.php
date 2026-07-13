<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Projects Listing
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Project;

Auth::requireInvestor();

$search     = trim(get('search', ''));
$status     = get('status', '');
$categoryId = (int) get('category_id', 0);
$page       = max(1, (int) get('page', 1));
$perPage    = 12;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Projects — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .project-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            transition: border-color .2s, transform .2s;
            text-decoration: none;
            color: var(--text);
            display: block;
        }

        .project-card:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
            text-decoration: none;
            color: var(--text);
        }

        .project-cover {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: var(--surface2);
            display: block;
        }

        .project-cover-placeholder {
            width: 100%;
            height: 180px;
            background: var(--surface2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }

        .project-body { padding: 18px; }

        .project-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .project-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: .3px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .project-location {
            font-size: 12px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 14px;
        }

        .project-specs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
            padding: 12px;
            background: var(--surface2);
            border-radius: 8px;
        }

        .spec-item { font-size: 11px; }
        .spec-label { color: var(--muted); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 2px; }
        .spec-value { font-weight: 700; color: var(--text); font-size: 13px; }

        .project-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 14px;
        }

        .project-tag {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 99px;
            padding: 3px 10px;
            font-size: 11px;
            color: var(--muted);
        }

        .project-footer {
            display: flex;
            gap: 8px;
        }

        .btn-request {
            flex: 1;
            background: var(--text);
            color: var(--bg);
            border: none;
            border-radius: 8px;
            padding: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-request:hover { background: var(--gold); color: #000; text-decoration: none; }

        .btn-timeline {
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
            transition: border-color .2s;
        }

        .btn-timeline:hover { border-color: var(--gold); color: var(--gold); text-decoration: none; }

        /* Filters bar */
        .filters-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        @media (max-width: 600px) {
            .projects-grid { grid-template-columns: 1fr; }
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
                <h1 class="page-title">Current Projects</h1>
                <p class="page-sub">
                    Explore our current oil and gas projects. All opportunities are available
                    exclusively to verified accredited investors through our private placement memorandum.
                </p>
            </div>
            <a href="<?= APP_URL ?>/investor/documents.php" class="btn-secondary">
                📁 Download Documents
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters-bar">
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
            <a href="<?= APP_URL ?>/investor/projects.php" class="btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Results count -->
        <p style="font-size:12px;color:var(--muted);margin-top:12px;">
            Showing <?= count($projects) ?> of <?= number_format($total) ?> project<?= $total !== 1 ? 's' : '' ?>
        </p>

        <!-- Project cards grid -->
        <?php if (empty($projects)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--muted);">
            <div style="font-size:40px;margin-bottom:12px;">📍</div>
            <p>No projects match your search.</p>
        </div>
        <?php else: ?>
        <div class="projects-grid">
            <?php foreach ($projects as $project):
                $tags = Project::getTags($project['id']);
            ?>
            <a href="<?= APP_URL ?>/investor/project-detail.php?slug=<?= e($project['slug']) ?>"
               class="project-card">

                <!-- Cover -->
                <?php if (!empty($project['cover_image'])): ?>
                <img src="<?= e($project['cover_image']) ?>" class="project-cover" alt="<?= e($project['title']) ?>"/>
                <?php else: ?>
                <div class="project-cover-placeholder">🛢</div>
                <?php endif; ?>

                <div class="project-body">
                    <!-- Status badge -->
                    <div class="project-status">
                        <span class="badge <?= Project::statusBadgeClass($project['status']) ?>">
                            <?= Project::statusLabel($project['status']) ?>
                        </span>
                        <?php if ($project['is_featured']): ?>
                        <span class="badge badge-gold">⭐ Featured</span>
                        <?php endif; ?>
                    </div>

                    <!-- Title & location -->
                    <div class="project-title"><?= e($project['title']) ?></div>
                    <?php if ($project['location']): ?>
                    <div class="project-location">
                        <span>📍</span> <?= e($project['location']) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Specs grid -->
                    <div class="project-specs">
                        <div class="spec-item">
                            <div class="spec-label">Project Cost</div>
                            <div class="spec-value"><?= formatCurrency((float)$project['project_cost']) ?></div>
                        </div>
                        <?php if ($project['production_type']): ?>
                        <div class="spec-item">
                            <div class="spec-label">Production</div>
                            <div class="spec-value"><?= e($project['production_type']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($project['project_type']): ?>
                        <div class="spec-item">
                            <div class="spec-label">Project Type</div>
                            <div class="spec-value"><?= e($project['project_type']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($project['target_depth']): ?>
                        <div class="spec-item">
                            <div class="spec-label">Target Depth</div>
                            <div class="spec-value"><?= e($project['target_depth']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tags -->
                    <?php if (!empty($tags)): ?>
                    <div class="project-tags">
                        <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                        <span class="project-tag"><?= e($tag['tag']) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($tags) > 3): ?>
                        <span class="project-tag">+<?= count($tags) - 3 ?> more</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Footer buttons -->
                    <div class="project-footer" onclick="event.preventDefault()">
                        <a href="<?= APP_URL ?>/investor/project-detail.php?slug=<?= e($project['slug']) ?>#request"
                           class="btn-request">Request Information →</a>
                        <a href="<?= APP_URL ?>/investor/project-detail.php?slug=<?= e($project['slug']) ?>#timeline"
                           class="btn-timeline">Timeline</a>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="justify-content:center;margin-top:28px;">
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

</body>
</html>