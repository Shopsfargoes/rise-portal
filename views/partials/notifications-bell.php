<?php
// ============================================================
// RISE CAPITAL GROUP — Notifications Bell Partial
// Dropdown with recent notifications, unread count badge
// Included by views/partials/topbar.php
// ============================================================

use Rise\Core\Auth;
use Rise\Models\Notification;

$userId       = Auth::id();
$role         = Auth::role();
$unreadCount  = Notification::unreadCount($userId);
$recent       = Notification::findByUser($userId, 8);
?>

<div class="notif-wrap" id="notifWrap">

    <!-- Bell button -->
    <button class="topbar-btn" id="notifBtn"
            onclick="toggleNotif(event)"
            title="Notifications"
            aria-label="Notifications">
        🔔
        <?php if ($unreadCount > 0): ?>
        <span class="notif-dot"></span>
        <?php endif; ?>
    </button>

    <!-- Dropdown -->
    <div class="notif-dropdown" id="notifDropdown">

        <div class="notif-header">
            <span class="notif-header-title">Notifications</span>
            <?php if ($unreadCount > 0): ?>
            <span class="notif-header-count"><?= $unreadCount ?> new</span>
            <?php endif; ?>
            <?php if (!empty($recent)): ?>
            <a href="<?= APP_URL ?>/app/Actions/investor/mark-notifications-read.php"
               class="notif-mark-all"
               onclick="markAllRead(event)">
                Mark all read
            </a>
            <?php endif; ?>
        </div>

        <?php if (empty($recent)): ?>
        <div class="notif-empty">
            <span style="font-size:28px;">🔔</span>
            <p>No notifications yet</p>
        </div>
        <?php else: ?>

        <div class="notif-list">
            <?php foreach ($recent as $notif): ?>
            <a href="<?= e(Notification::link($notif, $role)) ?>"
               class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>"
               onclick="markRead(<?= $notif['id'] ?>, event)">

                <div class="notif-icon">
                    <?= Notification::icon($notif['type']) ?>
                </div>

                <div class="notif-body">
                    <div class="notif-title"><?= e($notif['title']) ?></div>
                    <div class="notif-msg"><?= truncate(e($notif['message']), 70) ?></div>
                    <div class="notif-time"><?= timeAgo($notif['created_at']) ?></div>
                </div>

                <?php if (!$notif['is_read']): ?>
                <div class="notif-unread-dot"></div>
                <?php endif; ?>

            </a>
            <?php endforeach; ?>
        </div>

        <div class="notif-footer">
            <a href="<?= APP_URL ?>/<?= $role === 'admin' ? 'admin' : 'investor' ?>/notifications.php">
                View all notifications →
            </a>
        </div>

        <?php endif; ?>
    </div>
</div>

<style>
.notif-wrap {
    position: relative;
}

.notif-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 340px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: var(--shadow);
    z-index: 200;
    display: none;
    overflow: hidden;
}

.notif-dropdown.open { display: block; }

.notif-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
}

.notif-header-title {
    font-size: 13px;
    font-weight: 700;
    flex: 1;
}

.notif-header-count {
    background: var(--gold);
    color: #000;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 99px;
}

.notif-mark-all {
    font-size: 11px;
    color: var(--muted);
    text-decoration: none;
    transition: color .2s;
}

.notif-mark-all:hover { color: var(--gold); text-decoration: none; }

.notif-list {
    max-height: 360px;
    overflow-y: auto;
}

.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 16px;
    text-decoration: none;
    color: var(--text);
    border-bottom: 1px solid var(--border);
    transition: background .15s;
    position: relative;
}

.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: var(--surface2); text-decoration: none; color: var(--text); }
.notif-item.unread { background: #1a1500; }
.notif-item.unread:hover { background: #221c00; }

.notif-icon {
    font-size: 20px;
    width: 36px; height: 36px;
    background: var(--surface2);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.notif-body { flex: 1; min-width: 0; }
.notif-title { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
.notif-msg   { font-size: 12px; color: var(--muted); line-height: 1.4; margin-bottom: 4px; }
.notif-time  { font-size: 10px; color: var(--muted2); }

.notif-unread-dot {
    width: 7px; height: 7px;
    background: var(--gold);
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 5px;
}

.notif-empty {
    text-align: center;
    padding: 32px 20px;
    color: var(--muted);
    font-size: 13px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.notif-footer {
    padding: 10px 16px;
    border-top: 1px solid var(--border);
    text-align: center;
}

.notif-footer a {
    font-size: 12px;
    color: var(--gold);
    text-decoration: none;
}

.notif-footer a:hover { text-decoration: underline; }
</style>

<script>
function toggleNotif(e) {
    e.stopPropagation();
    document.getElementById('notifDropdown').classList.toggle('open');
}

// Close on outside click
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('notifWrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('notifDropdown').classList.remove('open');
    }
});

function markRead(id, e) {
    fetch('<?= APP_URL ?>/app/Actions/investor/mark-notifications-read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notif_id=' + id + '&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>'
    });
    // Let the link navigate naturally
}

function markAllRead(e) {
    e.preventDefault();
    fetch('<?= APP_URL ?>/app/Actions/investor/mark-notifications-read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_all=1&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>'
    }).then(() => {
        document.querySelectorAll('.notif-item.unread').forEach(el => {
            el.classList.remove('unread');
        });
        document.querySelectorAll('.notif-unread-dot').forEach(el => el.remove());
        document.querySelectorAll('.notif-dot').forEach(el => el.remove());
        document.querySelector('.notif-header-count')?.remove();
        document.querySelector('.notif-mark-all')?.remove();
    });
}
</script>