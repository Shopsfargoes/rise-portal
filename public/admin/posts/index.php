<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Posts List
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Post;

Auth::requireAdmin();

$type   = get('type', '');
$status = get('status', '');
$page   = max(1, (int) get('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$filters = array_filter([
    'type'   => $type   ?: null,
    'status' => $status ?: null,
]);

$total      = Post::count($filters);
$posts      = Post::findAll($filters, $perPage, $offset);
$totalPages = (int) ceil($total / $perPage);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>News & Updates — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">News & Updates</h1>
                <p class="page-sub"><?= number_format($total) ?> post<?= $total !== 1 ? 's' : '' ?></p>
            </div>
            <a href="<?= APP_URL ?>/admin/posts/create.php" class="btn-primary">
                + New Post
            </a>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">All Posts</div>
                    <div class="table-sub">News articles and market updates</div>
                </div>
                <form method="GET" class="flex gap-8">
                    <select name="type" onchange="this.form.submit()"
                            style="width:auto;min-width:130px;">
                        <option value="">All Types</option>
                        <option value="news"   <?= $type === 'news'   ? 'selected' : '' ?>>News</option>
                        <option value="update" <?= $type === 'update' ? 'selected' : '' ?>>Updates</option>
                    </select>
                    <select name="status" onchange="this.form.submit()"
                            style="width:auto;min-width:130px;">
                        <option value="">All Status</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft"     <?= $status === 'draft'     ? 'selected' : '' ?>>Draft</option>
                    </select>
                    <?php if ($type || $status): ?>
                    <a href="<?= APP_URL ?>/admin/posts/index.php" class="btn-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Project</th>
                            <th>Author</th>
                            <th>Published</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts)): ?>
                        <tr>
                            <td colspan="7" class="table-empty">
                                No posts yet.
                                <a href="<?= APP_URL ?>/admin/posts/create.php"
                                   style="color:var(--gold);">Write the first post →</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <?php if (!empty($post['cover_image'])): ?>
                                    <div style="width:40px;height:40px;border-radius:6px;overflow:hidden;
                                                flex-shrink:0;border:1px solid var(--border);">
                                        <img src="<?= e($post['cover_image']) ?>" alt=""
                                             style="width:100%;height:100%;object-fit:cover;"/>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight:600;color:var(--text);font-size:13px;">
                                            <?= e($post['title']) ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--muted);font-family:monospace;">
                                            /<?= e($post['slug']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $post['type'] === 'news' ? 'badge-blue' : 'badge-gold' ?>">
                                    <?= ucfirst($post['type']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $post['status'] === 'published' ? 'badge-green' : 'badge-grey' ?>">
                                    <?= ucfirst($post['status']) ?>
                                </span>
                            </td>
                            <td style="color:var(--muted);font-size:12px;">
                                <?= e($post['project_title'] ?? 'Company-wide') ?>
                            </td>
                            <td style="color:var(--muted);font-size:12px;">
                                <?= e(trim(($post['author_first'] ?? '') . ' ' . ($post['author_last'] ?? ''))) ?>
                            </td>
                            <td style="color:var(--muted);font-size:12px;">
                                <?= $post['published_at'] ? formatDate($post['published_at']) : '—' ?>
                            </td>
                            <td>
                                <div class="flex gap-8">
                                    <a href="<?= APP_URL ?>/admin/posts/edit.php?id=<?= $post['id'] ?>"
                                       class="btn-secondary btn-sm">Edit</a>
                                    <form method="POST"
                                          action="<?= APP_URL ?>/app/Actions/admin/save-post.php"
                                          onsubmit="return confirm('Delete this post?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="mode" value="delete"/>
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>"/>
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
