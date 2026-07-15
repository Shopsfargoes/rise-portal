<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Project Timeline
// ============================================================
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Project;

Auth::requireAdmin();

$id = (int) get('id');
if (!$id) redirect('/admin/projects/index.php');

$project  = Project::findById($id);
if (!$project) redirect('/admin/projects/index.php');

$timeline = Project::getTimeline($id);

// ── Handle add event ──────────────────────────────────────
if (isPost() && post('action') === 'add') {
    verifyCsrf();

    $title     = trim(post('event_title'));
    $date      = trim(post('event_date'));
    $desc      = trim(post('event_description'));
    $sortOrder = (int) post('sort_order', 0);

    if ($title && $date) {
        Project::addTimelineEvent($id, [
            'title'       => $title,
            'event_date'  => $date,
            'description' => $desc,
            'sort_order'  => $sortOrder,
        ]);
        Auth::audit(Auth::id(), 'add_timeline_event', 'project', $id);
        flash('Timeline event added.', 'success');
    } else {
        flash('Title and date are required.', 'error');
    }

    redirect("/admin/projects/timeline.php?id={$id}");
}

// ── Handle delete event ───────────────────────────────────
if (isPost() && post('action') === 'delete') {
    verifyCsrf();
    $eventId = (int) post('event_id');
    if ($eventId) {
        Project::deleteTimelineEvent($eventId, $id);
        Auth::audit(Auth::id(), 'delete_timeline_event', 'project', $id);
        flash('Event removed.', 'success');
    }
    redirect("/admin/projects/timeline.php?id={$id}");
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Timeline — <?= e($project['title']) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .timeline { position:relative; padding-left:28px; }
        .timeline::before { content:'';position:absolute;left:8px;top:0;bottom:0;width:2px;background:var(--border); }
        .timeline-item { position:relative; margin-bottom:24px; }
        .timeline-dot { position:absolute;left:-24px;top:4px;width:12px;height:12px;border-radius:50%;
                        background:var(--gold);border:2px solid var(--bg);z-index:1; }
        .timeline-card { background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px 18px; }
        .timeline-date { font-size:11px;color:var(--gold);font-weight:700;letter-spacing:.5px;margin-bottom:4px; }
        .timeline-title { font-size:14px;font-weight:600;margin-bottom:4px; }
        .timeline-desc { font-size:13px;color:var(--muted);line-height:1.5; }
        .timeline-actions { margin-top:10px;display:flex;gap:8px; }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <a href="<?= APP_URL ?>/admin/projects/edit.php?id=<?= $id ?>" class="back-link">
                    ← Back to <?= e($project['title']) ?>
                </a>
                <h1 class="page-title">Project Timeline</h1>
                <p class="page-sub">Key milestones and events shown to investors</p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div class="form-grid-2" style="gap:24px;align-items:flex-start;">

            <!-- Timeline display -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Timeline Events</div>
                            <div class="card-sub"><?= count($timeline) ?> event<?= count($timeline) !== 1 ? 's' : '' ?></div>
                        </div>
                    </div>

                    <?php if (empty($timeline)): ?>
                    <p style="color:var(--muted);font-size:13px;padding:12px 0;">
                        No timeline events yet. Add the first one →
                    </p>
                    <?php else: ?>
                    <div class="timeline" style="margin-top:8px;">
                        <?php foreach ($timeline as $event): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-card">
                                <div class="timeline-date"><?= formatDate($event['event_date'], 'M j, Y') ?></div>
                                <div class="timeline-title"><?= e($event['title']) ?></div>
                                <?php if ($event['description']): ?>
                                <div class="timeline-desc"><?= nl2br(e($event['description'])) ?></div>
                                <?php endif; ?>
                                <div class="timeline-actions">
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Remove this event?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete"/>
                                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>"/>
                                        <button type="submit" class="btn-danger btn-sm">Remove</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add event form -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Add Timeline Event</div>
                    </div>
                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add"/>

                        <div class="form-group">
                            <label>Event Title <span class="required">*</span></label>
                            <input type="text" name="event_title" required
                                   placeholder="e.g. Spud Date, TD Reached, Production Started"/>
                        </div>

                        <div class="form-group">
                            <label>Event Date <span class="required">*</span></label>
                            <input type="date" name="event_date" required/>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="event_description" rows="3"
                                      placeholder="Optional details about this milestone..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" min="0"
                                   value="<?= count($timeline) ?>"
                                   placeholder="0"/>
                            <span class="form-hint">Events are also ordered by date. Use this to break ties.</span>
                        </div>

                        <button type="submit" class="btn-primary" style="width:100%;">
                            Add Event
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
