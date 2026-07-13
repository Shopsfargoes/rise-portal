<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Media Gallery
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Media;

Auth::requireInvestor();

$type    = get('type', '');
$page    = max(1, (int) get('page', 1));
$perPage = 24;
$offset  = ($page - 1) * $perPage;

$filters    = array_filter(['type' => $type ?: null]);
$total      = Media::count($filters);
$mediaItems = Media::findAll($filters, $perPage, $offset);
$totalPages = (int) ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Media — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
            margin-top: 20px;
        }

        .media-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            transition: border-color .2s, transform .15s;
            cursor: pointer;
        }

        .media-card:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
        }

        .media-thumb {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
        }

        .media-thumb-placeholder {
            width: 100%;
            aspect-ratio: 1;
            background: var(--surface2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        .media-info {
            padding: 10px 12px;
        }

        .media-title {
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }

        .media-meta { font-size: 10px; color: var(--muted); }

        /* Lightbox */
        .lightbox {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.9);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .lightbox.open { display: flex; }

        .lightbox img {
            max-width: 90vw;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 8px;
        }

        .lightbox-close {
            position: absolute;
            top: 20px; right: 24px;
            font-size: 28px;
            color: #fff;
            cursor: pointer;
            background: none;
            border: none;
            line-height: 1;
        }

        .filter-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }

        .filter-pill {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 99px;
            padding: 5px 16px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-decoration: none;
            transition: all .15s;
        }

        .filter-pill.active,
        .filter-pill:hover {
            background: var(--gold-dim);
            border-color: var(--gold-border);
            color: var(--gold);
            text-decoration: none;
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
                <h1 class="page-title">Media Gallery</h1>
                <p class="page-sub"><?= number_format($total) ?> item<?= $total !== 1 ? 's' : '' ?></p>
            </div>
        </div>

        <!-- Filter pills -->
        <div class="filter-pills">
            <a href="<?= APP_URL ?>/investor/media.php"
               class="filter-pill <?= !$type ? 'active' : '' ?>">All</a>
            <a href="?type=image"
               class="filter-pill <?= $type === 'image' ? 'active' : '' ?>">🖼 Images</a>
            <a href="?type=video"
               class="filter-pill <?= $type === 'video' ? 'active' : '' ?>">🎥 Videos</a>
        </div>

        <?php if (empty($mediaItems)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--muted);">
            <div style="font-size:48px;margin-bottom:12px;">🖼</div>
            <p>No media available yet.</p>
        </div>
        <?php else: ?>

        <div class="media-grid">
            <?php foreach ($mediaItems as $item): ?>
            <div class="media-card"
                 onclick="<?= $item['type'] === 'image'
                    ? "openLightbox('" . e($item['file_path']) . "', '" . e($item['title'] ?? $item['file_name']) . "')"
                    : "window.open('" . e($item['file_path']) . "','_blank')" ?>">

                <?php if ($item['type'] === 'image'): ?>
                <img src="<?= e($item['file_path']) ?>" class="media-thumb"
                     alt="<?= e($item['title'] ?? '') ?>" loading="lazy"/>
                <?php else: ?>
                <div class="media-thumb-placeholder">
                    <?= Media::icon($item['type']) ?>
                </div>
                <?php endif; ?>

                <div class="media-info">
                    <div class="media-title"><?= e($item['title'] ?? $item['file_name']) ?></div>
                    <div class="media-meta">
                        <?= $item['project_title'] ? e($item['project_title']) . ' · ' : '' ?>
                        <?= formatDate($item['created_at']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="justify-content:center;margin-top:28px;">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&type=<?= urlencode($type) ?>" class="page-btn">← Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?page=<?= $i ?>&type=<?= urlencode($type) ?>"
               class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?>&type=<?= urlencode($type) ?>" class="page-btn">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <img id="lightboxImg" src="" alt=""/>
</div>

<script>
function openLightbox(src, alt) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxImg').alt = alt;
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeLightbox(e) {
    if (e && e.target !== document.getElementById('lightbox') &&
        !document.querySelector('.lightbox-close').contains(e.target)) return;
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeLightbox({ target: document.getElementById('lightbox') });
});
</script>

</body>
</html>