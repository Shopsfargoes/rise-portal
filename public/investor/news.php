<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: News
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Post;

Auth::requireInvestor();

$page    = max(1, (int) get('page', 1));
$perPage = 9;
$offset  = ($page - 1) * $perPage;

$filters    = ['type' => 'news', 'status' => 'published'];
$total      = Post::count($filters);
$posts      = Post::findAll($filters, $perPage, $offset);
$totalPages = (int) ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>News — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .news-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            transition: border-color .2s, transform .15s;
            cursor: pointer;
        }

        .news-card:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
        }

        .news-cover {
            width: 100%; height: 180px;
            object-fit: cover; display: block;
        }

        .news-cover-placeholder {
            width: 100%; height: 180px;
            background: var(--surface2);
            display: flex; align-items: center;
            justify-content: center; font-size: 40px;
        }

        .news-body { padding: 18px; }

        .news-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .news-title {
            font-size: 16px;
            font-weight: 700;
            line-height: 1.4;
            margin-bottom: 8px;
            color: var(--text);
        }

        .news-excerpt {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-footer {
            padding: 12px 18px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
        }

        .news-author {
            color: var(--muted);
            display: flex; align-items: center; gap: 6px;
        }

        /* Article view */
        .article-body {
            font-size: 15px;
            line-height: 1.8;
            color: var(--text2);
            max-width: 680px;
        }

        .article-body h2 { font-size: 22px; font-weight: 700; color: var(--text); margin: 24px 0 12px; }
        .article-body h3 { font-size: 18px; font-weight: 700; color: var(--text); margin: 20px 0 10px; }
        .article-body p  { margin-bottom: 16px; }
        .article-body ul,
        .article-body ol { padding-left: 24px; margin-bottom: 16px; }
        .article-body li { margin-bottom: 6px; }
        .article-body a  { color: var(--gold); }
        .article-body strong { color: var(--text); font-weight: 700; }
    </style>
</head>
<body class="investor-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-investor.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <?php
        // Single article view
        $slug = get('slug');
        if ($slug):
            $article = Post::findBySlug($slug);
            if (!$article) redirect('/investor/news.php');
        ?>

        <a href="<?= APP_URL ?>/investor/news.php" class="back-link"
           style="display:inline-block;margin-bottom:20px;">← Back to News</a>

        <?php if (!empty($article['cover_image'])): ?>
        <img src="<?= e($article['cover_image']) ?>"
             style="width:100%;height:320px;object-fit:cover;border-radius:14px;
                    display:block;margin-bottom:28px;border:1px solid var(--border);"
             alt="<?= e($article['title']) ?>"/>
        <?php endif; ?>

        <div style="max-width:680px;">
            <div style="display:flex;gap:8px;margin-bottom:12px;">
                <span class="badge badge-blue">📰 News</span>
                <?php if ($article['project_title']): ?>
                <span class="badge badge-grey">📍 <?= e($article['project_title']) ?></span>
                <?php endif; ?>
            </div>

            <h1 style="font-size:30px;font-weight:900;line-height:1.3;margin-bottom:12px;">
                <?= e($article['title']) ?>
            </h1>

            <div style="font-size:13px;color:var(--muted);margin-bottom:28px;
                        display:flex;align-items:center;gap:10px;">
                <span>By <?= e(trim(($article['author_first'] ?? '') . ' ' . ($article['author_last'] ?? ''))) ?></span>
                <span>·</span>
                <span><?= formatDate($article['published_at']) ?></span>
            </div>

            <div class="article-body">
                <?= $article['body'] ?>
            </div>
        </div>

        <?php else: ?>

        <!-- News listing -->
        <div class="page-header">
            <div>
                <h1 class="page-title">News</h1>
                <p class="page-sub"><?= number_format($total) ?> article<?= $total !== 1 ? 's' : '' ?></p>
            </div>
        </div>

        <?php if (empty($posts)): ?>
        <div style="text-align:center;padding:60px;color:var(--muted);">
            <div style="font-size:40px;margin-bottom:12px;">📰</div>
            <p>No news articles published yet.</p>
        </div>
        <?php else: ?>

        <div class="news-grid">
            <?php foreach ($posts as $post): ?>
            <div class="news-card"
                 onclick="window.location='<?= APP_URL ?>/investor/news.php?slug=<?= e($post['slug']) ?>'">

                <?php if (!empty($post['cover_image'])): ?>
                <img src="<?= e($post['cover_image']) ?>" class="news-cover"
                     alt="<?= e($post['title']) ?>"/>
                <?php else: ?>
                <div class="news-cover-placeholder">📰</div>
                <?php endif; ?>

                <div class="news-body">
                    <div class="news-meta">
                        <span class="badge badge-blue" style="font-size:10px;">News</span>
                        <?php if ($post['project_title']): ?>
                        <span>· <?= e($post['project_title']) ?></span>
                        <?php endif; ?>
                        <span>· <?= $post['published_at'] ? timeAgo($post['published_at']) : '' ?></span>
                    </div>
                    <div class="news-title"><?= e($post['title']) ?></div>
                    <div class="news-excerpt">
                        <?= truncate(strip_tags($post['body']), 150) ?>
                    </div>
                </div>

                <div class="news-footer">
                    <div class="news-author">
                        <div class="avatar avatar-sm">
                            <?= strtoupper(substr($post['author_first'] ?? 'R', 0, 1)) ?>
                        </div>
                        <?= e(trim(($post['author_first'] ?? '') . ' ' . ($post['author_last'] ?? ''))) ?>
                    </div>
                    <a href="<?= APP_URL ?>/investor/news.php?slug=<?= e($post['slug']) ?>"
                       style="color:var(--gold);font-size:12px;font-weight:600;">
                        Read More →
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="justify-content:center;margin-top:28px;">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>" class="page-btn">← Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?page=<?= $i ?>"
               class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?>" class="page-btn">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

</body>
</html>