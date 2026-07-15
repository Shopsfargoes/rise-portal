<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: All Notifications
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Notification;

Auth::requireAdmin();

$userId       = Auth::id();
$unreadOnly   = get('filter') === 'unread';
$notifications = Notification::findByUser($userId, 100, $unreadOnly);
$unreadCount   = Notification::unreadCount($userId);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Notifications — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .notif-row {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }

        .notif-row:last-child { border-bottom: none; }
        .notif-row.unread { background: #1a1500; }
        .notif-row:hover  { background: var(--surface2); }

        .notif-icon-wrap {
            width: 40px; height: 40px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .notif-content { flex: 1; min-width: 0; }
        .notif-title   { font-size: 14px; font-weight: 600; margin-bottom: 3px; }
        .notif-title.unread { color: var(--text); }
        .notif-title.read   { color: var(--text2); font-weight: 500; }
        .notif-message { font-size: 13px; color: var(--muted); line-height: 1.5; margin-bottom: 4px; }
        .notif-time    { font-size: 11px; color: var(--muted2); }

        .unread-indicator {
            width: 8px; height: 8px;
            background: var(--gold);
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 6px;
        }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Notifications</h1>
                <p class="page-sub">
                    <?php if ($unreadCount > 0): ?>
                    <span style="color:var(--gold);"><?= $unreadCount ?> unread</span>
                    &nbsp;·&nbsp;
                    <?php endif; ?>
                    <?= count($notifications) ?> total shown
                </p>
            </div>
            <div class="flex gap-8">
                <a href="?filter=<?= $unreadOnly ? '' : 'unread' ?>"
                   class="btn-secondary btn-sm">
                    <?= $unreadOnly ? 'Show All' : 'Unread Only' ?>
                </a>
                <?php if ($unreadCount > 0): ?>
                <a href="<?= APP_URL ?>/app/Actions/investor/mark-notifications-read.php?mark_all=1&redirect=<?= urlencode(APP_URL . '/admin/notifications/index.php') ?>"
                   class="btn-secondary btn-sm">
                    ✓ Mark All Read
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div class="table-card">
            <?php if (empty($notifications)): ?>
            <div class="table-empty" style="padding:48px;">
                <div style="font-size:40px;margin-bottom:12px;">🔔</div>
                <?= $unreadOnly ? 'No unread notifications.' : 'No notifications yet.' ?>
            </div>
            <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
            <div class="notif-row <?= !$notif['is_read'] ? 'unread' : '' ?>">
                <div class="notif-icon-wrap">
                    <?= Notification::icon($notif['type']) ?>
                </div>
                <div class="notif-content">
                    <div class="notif-title <?= !$notif['is_read'] ? 'unread' : 'read' ?>">
                        <?= e($notif['title']) ?>
                    </div>
                    <div class="notif-message"><?= e($notif['message']) ?></div>
                    <div class="notif-time">
                        <?= formatDateTime($notif['created_at']) ?>
                        &nbsp;·&nbsp;
                        <span class="badge <?= Notification::badgeClass($notif['type']) ?>"
                              style="font-size:10px;">
                            <?= e(str_replace('_', ' ', $notif['type'])) ?>
                        </span>
                        <?php if ($notif['related_type']): ?>
                        &nbsp;·&nbsp;
                        <a href="<?= e(Notification::link($notif, 'admin')) ?>"
                           style="color:var(--gold);font-size:11px;">
                            View →
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$notif['is_read']): ?>
                <div class="unread-indicator"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>
