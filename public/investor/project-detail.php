<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Project Detail
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Project;

Auth::requireInvestor();

$slug = trim(get('slug'));
if (!$slug) redirect('/investor/projects.php');

$project  = Project::findBySlug($slug);
if (!$project) redirect('/investor/projects.php');

$tags     = Project::getTags($project['id']);
$timeline = Project::getTimeline($project['id']);
$stats    = Project::getStats($project['id']);

// Documents linked to this project
$documents = db()->fetchAll(
    "SELECT d.*, dc.name AS category_name
     FROM documents d
     LEFT JOIN document_categories dc ON dc.id = d.category_id
     WHERE d.project_id = ? AND d.visibility IN ('investor','public')
     ORDER BY d.created_at DESC",
    [$project['id']]
);

// Check if investor has invested in this project
$myInvestment = db()->fetchOne(
    "SELECT * FROM investments WHERE user_id = ? AND project_id = ? AND status = 'active' LIMIT 1",
    [Auth::id(), $project['id']]
);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= e($project['title']) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        /* Hero */
        .project-hero {
            width: 100%;
            height: 320px;
            object-fit: cover;
            border-radius: 14px;
            display: block;
            margin-bottom: 28px;
            border: 1px solid var(--border);
        }

        .project-hero-placeholder {
            width: 100%;
            height: 220px;
            background: var(--surface2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            margin-bottom: 28px;
            border: 1px solid var(--border);
        }

        /* Specs grid */
        .specs-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 28px;
        }

        .spec-box {
            background: var(--surface);
            padding: 16px 18px;
        }

        .spec-box-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .spec-box-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .spec-box-value.gold { color: var(--gold); }

        /* Description */
        .project-description {
            font-size: 14px;
            color: var(--text2);
            line-height: 1.8;
            margin-bottom: 24px;
        }

        /* Tags */
        .tags-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 28px;
        }

        .project-tag-pill {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 99px;
            padding: 5px 14px;
            font-size: 12px;
            color: var(--text2);
        }

        /* Timeline */
        .timeline { position: relative; padding-left: 28px; }
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px; top: 6px; bottom: 6px;
            width: 2px;
            background: var(--border);
        }

        .tl-item { position: relative; margin-bottom: 20px; }

        .tl-dot {
            position: absolute;
            left: -24px; top: 5px;
            width: 12px; height: 12px;
            border-radius: 50%;
            background: var(--gold);
            border: 2px solid var(--bg);
        }

        .tl-date  { font-size: 11px; color: var(--gold); font-weight: 700; margin-bottom: 3px; }
        .tl-title { font-size: 14px; font-weight: 600; margin-bottom: 3px; }
        .tl-desc  { font-size: 13px; color: var(--muted); line-height: 1.5; }

        /* Request form */
        .request-card {
            background: var(--surface);
            border: 1px solid var(--gold-border);
            border-radius: 14px;
            padding: 28px;
            position: sticky;
            top: 76px;
        }

        .request-title {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .request-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        /* My investment badge */
        .my-investment-badge {
            background: var(--green-bg);
            border: 1px solid var(--green-border);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .my-investment-badge .amount {
            font-size: 20px;
            font-weight: 800;
            color: var(--green);
            display: block;
            margin-top: 4px;
        }

        /* Layout */
        .detail-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 28px;
            align-items: flex-start;
        }

        @media (max-width: 900px) {
            .detail-layout { grid-template-columns: 1fr; }
            .specs-grid { grid-template-columns: repeat(2, 1fr); }
            .request-card { position: static; }
        }

        @media (max-width: 540px) {
            .specs-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body class="investor-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-investor.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <!-- Back link -->
        <a href="<?= APP_URL ?>/investor/projects.php" class="back-link" style="display:inline-block;margin-bottom:16px;">
            ← Back to Projects
        </a>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div class="detail-layout">

            <!-- ── Left column — project info ── -->
            <div>
                <!-- Cover image -->
                <?php if (!empty($project['cover_image'])): ?>
                <img src="<?= e($project['cover_image']) ?>" class="project-hero" alt="<?= e($project['title']) ?>"/>
                <?php else: ?>
                <div class="project-hero-placeholder">🛢</div>
                <?php endif; ?>

                <!-- Title & status -->
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px;flex-wrap:wrap;">
                    <div>
                        <h1 style="font-size:28px;font-weight:900;letter-spacing:.5px;text-transform:uppercase;margin-bottom:6px;">
                            <?= e($project['title']) ?>
                        </h1>
                        <?php if ($project['location']): ?>
                        <div style="font-size:13px;color:var(--muted);display:flex;align-items:center;gap:5px;">
                            📍 <?= e($project['location']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="badge <?= Project::statusBadgeClass($project['status']) ?>" style="font-size:13px;padding:5px 14px;">
                        <?= Project::statusLabel($project['status']) ?>
                    </span>
                </div>

                <!-- Specs grid -->
                <div class="specs-grid">
                    <div class="spec-box">
                        <div class="spec-box-label">Project Cost</div>
                        <div class="spec-box-value gold"><?= formatCurrency((float)$project['project_cost']) ?></div>
                    </div>
                    <?php if ($project['production_type']): ?>
                    <div class="spec-box">
                        <div class="spec-box-label">Production</div>
                        <div class="spec-box-value"><?= e($project['production_type']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($project['project_type']): ?>
                    <div class="spec-box">
                        <div class="spec-box-label">Project Type</div>
                        <div class="spec-box-value" style="font-size:13px;"><?= e($project['project_type']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($project['target_depth']): ?>
                    <div class="spec-box">
                        <div class="spec-box-label">Target Depth</div>
                        <div class="spec-box-value"><?= e($project['target_depth']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <?php if ($project['description']): ?>
                <div class="project-description">
                    <?= nl2br(e($project['description'])) ?>
                </div>
                <?php endif; ?>

                <!-- Tags -->
                <?php if (!empty($tags)): ?>
                <div class="tags-row">
                    <?php foreach ($tags as $tag): ?>
                    <span class="project-tag-pill"><?= e($tag['tag']) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Documents -->
                <?php if (!empty($documents)): ?>
                <div class="card mb-24" id="documents" style="margin-bottom:24px;">
                    <div class="card-header">
                        <div class="card-title">📁 Project Documents</div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php foreach ($documents as $doc): ?>
                        <a href="<?= APP_URL ?>/download.php?uuid=<?= e($doc['uuid']) ?>"
                           class="flex-center gap-12"
                           style="padding:10px 12px;background:var(--surface2);border:1px solid var(--border);
                                  border-radius:8px;text-decoration:none;color:var(--text);transition:border-color .2s;"
                           target="_blank">
                            <span style="font-size:20px;">📄</span>
                            <div style="flex:1;">
                                <div style="font-size:13px;font-weight:600;"><?= e($doc['title']) ?></div>
                                <div style="font-size:11px;color:var(--muted);">
                                    <?= e($doc['category_name'] ?? '') ?>
                                    · <?= humanFileSize($doc['file_size']) ?>
                                </div>
                            </div>
                            <span style="color:var(--muted);font-size:12px;">Download ↓</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Timeline -->
                <?php if (!empty($timeline)): ?>
                <div class="card" id="timeline">
                    <div class="card-header">
                        <div class="card-title">📅 Project Timeline</div>
                    </div>
                    <div class="timeline" style="margin-top:12px;">
                        <?php foreach ($timeline as $event): ?>
                        <div class="tl-item">
                            <div class="tl-dot"></div>
                            <div class="tl-date"><?= formatDate($event['event_date'], 'M j, Y') ?></div>
                            <div class="tl-title"><?= e($event['title']) ?></div>
                            <?php if ($event['description']): ?>
                            <div class="tl-desc"><?= nl2br(e($event['description'])) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- ── Right column — request / investment ── -->
            <div>
                <div class="request-card" id="request">

                    <!-- If already invested -->
                    <?php if ($myInvestment): ?>
                    <div class="my-investment-badge">
                        <span style="color:var(--green);font-weight:700;">✓ You are invested in this project</span>
                        <span class="amount"><?= formatMoney((float)$myInvestment['amount']) ?></span>
                        <span style="font-size:11px;color:var(--muted);">Since <?= formatDate($myInvestment['invested_at']) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="request-title">Request Information</div>
                    <div class="request-sub">
                        Interested in this project? Send us a message and our team will
                        reach out with full details and investment documents.
                    </div>

                    <!-- Request info form → creates a message thread -->
                    <form method="POST" action="<?= APP_URL ?>/app/Actions/investor/send-message.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="context_type" value="project"/>
                        <input type="hidden" name="context_id" value="<?= $project['id'] ?>"/>
                        <input type="hidden" name="subject" value="Information Request: <?= e($project['title']) ?>"/>
                        <input type="hidden" name="redirect_to" value="<?= APP_URL ?>/investor/project-detail.php?slug=<?= e($project['slug']) ?>"/>

                        <div class="form-group">
                            <label>Your Message</label>
                            <textarea name="body" rows="4"
                                      placeholder="I'm interested in learning more about the <?= e($project['title']) ?> project. Please send me the investment details..."
                                      required></textarea>
                        </div>

                        <button type="submit" class="btn-primary" style="width:100%;">
                            Send Request →
                        </button>
                    </form>

                    <!-- Divider -->
                    <div style="border-top:1px solid var(--border);margin:20px 0;"></div>

                    <!-- Project summary stats -->
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <div class="flex-center" style="justify-content:space-between;">
                            <span style="font-size:12px;color:var(--muted);">Category</span>
                            <span style="font-size:13px;font-weight:600;"><?= e($project['category_name'] ?? '—') ?></span>
                        </div>
                        <div class="flex-center" style="justify-content:space-between;">
                            <span style="font-size:12px;color:var(--muted);">Investors</span>
                            <span style="font-size:13px;font-weight:600;"><?= (int)$stats['investor_count'] ?></span>
                        </div>
                        <div class="flex-center" style="justify-content:space-between;">
                            <span style="font-size:12px;color:var(--muted);">Total Raised</span>
                            <span style="font-size:13px;font-weight:600;color:var(--gold);"><?= formatCurrency((float)$stats['total_raised']) ?></span>
                        </div>
                        <div class="flex-center" style="justify-content:space-between;">
                            <span style="font-size:12px;color:var(--muted);">Total Distributed</span>
                            <span style="font-size:13px;font-weight:600;"><?= formatCurrency((float)$stats['total_distributed']) ?></span>
                        </div>
                        <?php if ($project['map_lat'] && $project['map_lng']): ?>
                        <div class="flex-center" style="justify-content:space-between;">
                            <span style="font-size:12px;color:var(--muted);">Coordinates</span>
                            <a href="https://maps.google.com/?q=<?= e($project['map_lat']) ?>,<?= e($project['map_lng']) ?>"
                               target="_blank"
                               style="font-size:12px;color:var(--gold);">
                                View on Map ↗
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>