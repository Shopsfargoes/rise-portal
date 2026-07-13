<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Documents List
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Document;
use Rise\Models\Project;

Auth::requireAdmin();

$search     = trim(get('search', ''));
$categoryId = (int) get('category_id', 0);
$projectId  = (int) get('project_id', 0);
$visibility = get('visibility', '');
$page       = max(1, (int) get('page', 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

$filters = array_filter([
    'search'      => $search,
    'category_id' => $categoryId ?: null,
    'project_id'  => $projectId  ?: null,
    'visibility'  => $visibility  ?: null,
]);

$total      = Document::count($filters);
$documents  = Document::findAll($filters, $perPage, $offset);
$categories = Document::getCategories();
$projects   = Project::findAll([], 100, 0);
$totalPages = (int) ceil($total / $perPage);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Documents — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Documents</h1>
                <p class="page-sub"><?= number_format($total) ?> document<?= $total !== 1 ? 's' : '' ?> in the library</p>
            </div>
            <a href="<?= APP_URL ?>/admin/documents/upload.php" class="btn-primary">
                + Upload Document
            </a>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">Document Library</div>
                    <div class="table-sub">PDFs, reports, legal documents and more</div>
                </div>

                <!-- Filters -->
                <form method="GET" class="flex gap-8" style="flex-wrap:wrap;">
                    <div class="search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search"
                               placeholder="Search documents..."
                               value="<?= e($search) ?>"/>
                    </div>

                    <select name="category_id" onchange="this.form.submit()"
                            style="width:auto;min-width:160px;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="project_id" onchange="this.form.submit()"
                            style="width:auto;min-width:160px;">
                        <option value="">All Projects</option>
                        <option value="-1" <?= $projectId === -1 ? 'selected' : '' ?>>Company-wide</option>
                        <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>"
                            <?= $projectId === (int)$proj['id'] ? 'selected' : '' ?>>
                            <?= e($proj['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="visibility" onchange="this.form.submit()"
                            style="width:auto;min-width:140px;">
                        <option value="">All Visibility</option>
                        <option value="admin"    <?= $visibility === 'admin'    ? 'selected' : '' ?>>Admin Only</option>
                        <option value="investor" <?= $visibility === 'investor' ? 'selected' : '' ?>>Investors</option>
                        <option value="public"   <?= $visibility === 'public'   ? 'selected' : '' ?>>Public</option>
                    </select>

                    <button type="submit" class="btn-secondary btn-sm">Filter</button>
                    <?php if ($search || $categoryId || $projectId || $visibility): ?>
                    <a href="<?= APP_URL ?>/admin/documents/index.php" class="btn-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Category</th>
                            <th>Project</th>
                            <th>Visibility</th>
                            <th class="text-right">Size</th>
                            <th class="text-right">Downloads</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="9" class="table-empty">
                                <?= $search ? 'No documents match "' . e($search) . '"' : 'No documents uploaded yet.' ?>
                                <?php if (!$search): ?>
                                <br><a href="<?= APP_URL ?>/admin/documents/upload.php"
                                       style="color:var(--gold);">Upload the first document →</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                        <tr>
                            <!-- Title + filename -->
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="font-size:22px;"><?= Document::mimeIcon($doc['mime_type']) ?></span>
                                    <div>
                                        <div style="font-weight:600;color:var(--text);">
                                            <?= e($doc['title']) ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--muted);font-family:monospace;">
                                            <?= e($doc['file_name']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td style="color:var(--muted);font-size:12px;">
                                <?= e($doc['category_name'] ?? '—') ?>
                            </td>

                            <td style="color:var(--muted);font-size:12px;">
                                <?= e($doc['project_title'] ?? 'Company-wide') ?>
                            </td>

                            <td>
                                <span class="badge <?= Document::visibilityBadge($doc['visibility']) ?>">
                                    <?= Document::visibilityLabel($doc['visibility']) ?>
                                </span>
                            </td>

                            <td class="text-right" style="color:var(--muted);font-size:12px;">
                                <?= humanFileSize((int)$doc['file_size']) ?>
                            </td>

                            <td class="text-right" style="color:var(--muted);font-size:12px;">
                                <?= number_format((int)$doc['download_count']) ?>
                            </td>

                            <td style="color:var(--muted);font-size:12px;">
                                <?= e(trim(($doc['uploaded_by_first'] ?? '') . ' ' . ($doc['uploaded_by_last'] ?? ''))) ?: e($doc['uploaded_by_email'] ?? '—') ?>
                            </td>

                            <td style="color:var(--muted);font-size:12px;">
                                <?= formatDate($doc['created_at']) ?>
                            </td>

                            <td>
                                <div class="flex gap-8">
                                    <a href="<?= APP_URL ?>/download.php?uuid=<?= e($doc['uuid']) ?>"
                                       class="btn-secondary btn-sm" target="_blank">
                                       Download
                                    </a>
                                    <form method="POST"
                                          action="<?= APP_URL ?>/app/Actions/admin/delete-document.php"
                                          onsubmit="return confirm('Delete this document? This cannot be undone.')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="document_id" value="<?= $doc['id'] ?>"/>
                                        <button type="submit" class="btn-danger btn-sm">Delete</button>
                                    </form>
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