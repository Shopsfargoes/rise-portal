<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Document Library
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Document;

Auth::requireInvestor();

// Get all documents accessible to investors
$allDocs = Document::findForInvestor();

// Group by category
$grouped = [];
foreach ($allDocs as $doc) {
    $category = $doc['category_name'] ?? 'General';
    $grouped[$category][] = $doc;
}
ksort($grouped);

// Separate company-wide from project-specific
$companyDocs = array_filter($allDocs, fn($d) => empty($d['project_id']));
$projectDocs = array_filter($allDocs, fn($d) => !empty($d['project_id']));

// Group project docs by project
$byProject = [];
foreach ($projectDocs as $doc) {
    $byProject[$doc['project_title']][] = $doc;
}
ksort($byProject);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Documents — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .doc-section { margin-bottom: 32px; }

        .doc-section-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .doc-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
        }

        .doc-card {
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            text-decoration: none;
            color: var(--text);
            transition: border-color .2s, background .15s;
        }

        .doc-card:hover {
            border-color: var(--gold);
            background: var(--surface2);
            text-decoration: none;
            color: var(--text);
        }

        .doc-icon {
            font-size: 28px;
            flex-shrink: 0;
        }

        .doc-info { flex: 1; min-width: 0; }

        .doc-title {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px;
        }

        .doc-meta {
            font-size: 11px;
            color: var(--muted);
        }

        .doc-download {
            font-size: 16px;
            color: var(--muted);
            flex-shrink: 0;
            transition: color .2s;
        }

        .doc-card:hover .doc-download { color: var(--gold); }

        /* Project tabs */
        .project-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 99px;
            padding: 5px 14px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            transition: all .15s;
            text-decoration: none;
        }

        .project-pill.active,
        .project-pill:hover {
            background: var(--gold-dim);
            border-color: var(--gold-border);
            color: var(--gold);
            text-decoration: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        .empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; }
    </style>
</head>
<body class="investor-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-investor.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Documents</h1>
                <p class="page-sub">
                    <?= count($allDocs) ?> document<?= count($allDocs) !== 1 ? 's' : '' ?> available to you
                </p>
            </div>
        </div>

        <?php if (empty($allDocs)): ?>
        <div class="empty-state">
            <div class="empty-icon">📁</div>
            <p>No documents have been shared with you yet.</p>
            <p style="font-size:12px;margin-top:6px;">Check back later or contact your fund manager.</p>
        </div>

        <?php else: ?>

        <!-- Filter pills by project -->
        <?php if (!empty($byProject)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:28px;">
            <a href="#company" class="project-pill active" id="pill-company"
               onclick="filterSection('company')">🏢 Company-wide</a>
            <?php foreach (array_keys($byProject) as $projTitle): ?>
            <a href="#<?= e(slugify($projTitle)) ?>" class="project-pill"
               id="pill-<?= e(slugify($projTitle)) ?>"
               onclick="filterSection('<?= e(slugify($projTitle)) ?>')">
               📍 <?= e($projTitle) ?>
            </a>
            <?php endforeach; ?>
            <a href="#all" class="project-pill" onclick="filterSection('all')">All Documents</a>
        </div>
        <?php endif; ?>

        <!-- Company-wide documents -->
        <?php if (!empty($companyDocs)): ?>
        <div class="doc-section" id="section-company">
            <div class="doc-section-title">🏢 Company-wide Documents</div>
            <div class="doc-grid">
                <?php foreach ($companyDocs as $doc): ?>
                <a href="<?= APP_URL ?>/download.php?uuid=<?= e($doc['uuid']) ?>"
                   class="doc-card" target="_blank">
                    <span class="doc-icon"><?= Document::mimeIcon($doc['mime_type']) ?></span>
                    <div class="doc-info">
                        <div class="doc-title"><?= e($doc['title']) ?></div>
                        <div class="doc-meta">
                            <?= e($doc['category_name'] ?? 'General') ?>
                            · <?= humanFileSize((int)$doc['file_size']) ?>
                            · <?= formatDate($doc['created_at']) ?>
                        </div>
                    </div>
                    <span class="doc-download">↓</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Per-project documents -->
        <?php foreach ($byProject as $projTitle => $docs): ?>
        <div class="doc-section" id="section-<?= e(slugify($projTitle)) ?>">
            <div class="doc-section-title">📍 <?= e($projTitle) ?></div>
            <div class="doc-grid">
                <?php foreach ($docs as $doc): ?>
                <a href="<?= APP_URL ?>/download.php?uuid=<?= e($doc['uuid']) ?>"
                   class="doc-card" target="_blank">
                    <span class="doc-icon"><?= Document::mimeIcon($doc['mime_type']) ?></span>
                    <div class="doc-info">
                        <div class="doc-title"><?= e($doc['title']) ?></div>
                        <div class="doc-meta">
                            <?= e($doc['category_name'] ?? 'General') ?>
                            · <?= humanFileSize((int)$doc['file_size']) ?>
                            · <?= formatDate($doc['created_at']) ?>
                        </div>
                    </div>
                    <span class="doc-download">↓</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>

    </div>
</div>

<script>
function filterSection(key) {
    // Update pills
    document.querySelectorAll('.project-pill').forEach(p => p.classList.remove('active'));
    const activePill = document.getElementById('pill-' + key);
    if (activePill) activePill.classList.add('active');

    // Show/hide sections
    const sections = document.querySelectorAll('.doc-section');
    if (key === 'all') {
        sections.forEach(s => s.style.display = '');
    } else {
        sections.forEach(s => {
            s.style.display = s.id === 'section-' + key ? '' : 'none';
        });
    }

    return false;
}
</script>

</body>
</html>