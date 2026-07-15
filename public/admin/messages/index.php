<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Messages Inbox
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\MessageThread;

Auth::requireAdmin();

$search      = trim(get('search', ''));
$status      = get('status', '');
$contextType = get('context_type', '');
$page        = max(1, (int) get('page', 1));
$perPage     = 25;
$offset      = ($page - 1) * $perPage;

$filters = array_filter([
    'search'       => $search,
    'status'       => $status       ?: null,
    'context_type' => $contextType  ?: null,
]);

$total      = MessageThread::count($filters);
$threads    = MessageThread::findAll($filters, $perPage, $offset);
$totalPages = (int) ceil($total / $perPage);

// Total unread across all threads
$totalUnread = (int) db()->fetchColumn(
    "SELECT COALESCE(SUM(unread_admin), 0) FROM message_threads WHERE status = 'open'"
);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Messages — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .thread-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            color: var(--text);
            transition: background .15s;
            position: relative;
        }

        .thread-item:last-child { border-bottom: none; }

        .thread-item:hover {
            background: var(--surface2);
            text-decoration: none;
            color: var(--text);
        }

        .thread-item.unread {
            background: #1a1500;
        }

        .thread-item.unread:hover { background: #221c00; }

        .unread-bar {
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            background: var(--gold);
            border-radius: 0 2px 2px 0;
        }

        .thread-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--surface2);
            border: 2px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700; color: var(--text);
            flex-shrink: 0;
            overflow: hidden;
        }

        .thread-info { flex: 1; min-width: 0; }

        .thread-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 3px;
        }

        .thread-name {
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .thread-time {
            font-size: 11px;
            color: var(--muted);
            flex-shrink: 0;
        }

        .thread-subject {
            font-size: 13px;
            color: var(--text2);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }

        .thread-preview {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .thread-badges {
            display: flex;
            gap: 5px;
            align-items: center;
            flex-shrink: 0;
        }

        .unread-count {
            background: var(--gold);
            color: #000;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 99px;
            min-width: 18px;
            text-align: center;
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
                <h1 class="page-title">Messages</h1>
                <p class="page-sub">
                    <?= number_format($total) ?> thread<?= $total !== 1 ? 's' : '' ?>
                    <?php if ($totalUnread > 0): ?>
                    · <span style="color:var(--gold);"><?= $totalUnread ?> unread</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div class="table-card">
            <!-- Filters -->
            <div class="table-header">
                <div>
                    <div class="table-title">All Conversations</div>
                    <div class="table-sub">Threads across all investors</div>
                </div>
                <form method="GET" class="flex gap-8" style="flex-wrap:wrap;">
                    <div class="search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search"
                               placeholder="Search investor or subject..."
                               value="<?= e($search) ?>"/>
                    </div>
                    <select name="context_type" onchange="this.form.submit()"
                            style="width:auto;min-width:160px;">
                        <option value="">All Types</option>
                        <option value="transaction" <?= $contextType === 'transaction' ? 'selected' : '' ?>>Transaction</option>
                        <option value="project"     <?= $contextType === 'project'     ? 'selected' : '' ?>>Project</option>
                        <option value="general"     <?= $contextType === 'general'     ? 'selected' : '' ?>>General</option>
                    </select>
                    <select name="status" onchange="this.form.submit()"
                            style="width:auto;min-width:130px;">
                        <option value="">All Status</option>
                        <option value="open"   <?= $status === 'open'   ? 'selected' : '' ?>>Open</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                    <button type="submit" class="btn-secondary btn-sm">Filter</button>
                    <?php if ($search || $status || $contextType): ?>
                    <a href="<?= APP_URL ?>/admin/messages/index.php"
                       class="btn-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Thread list -->
            <?php if (empty($threads)): ?>
            <div class="table-empty">No conversations found.</div>
            <?php else: ?>
            <?php foreach ($threads as $thread): ?>
            <?php $isUnread = (int)$thread['unread_admin'] > 0; ?>
            <a href="<?= APP_URL ?>/admin/messages/thread.php?id=<?= $thread['id'] ?>"
               class="thread-item <?= $isUnread ? 'unread' : '' ?>">

                <?php if ($isUnread): ?>
                <div class="unread-bar"></div>
                <?php endif; ?>

                <!-- Investor avatar -->
                <div class="thread-avatar">
                    <?php if (!empty($thread['avatar_path'])): ?>
                    <img src="<?= e($thread['avatar_path']) ?>" alt=""
                         style="width:100%;height:100%;object-fit:cover;"/>
                    <?php else: ?>
                    <?= strtoupper(substr($thread['investor_first'] ?? '?', 0, 1)) ?>
                    <?php endif; ?>
                </div>

                <div class="thread-info">
                    <div class="thread-top">
                        <span class="thread-name">
                            <?= e($thread['investor_first'] . ' ' . $thread['investor_last']) ?>
                        </span>
                        <span class="thread-time">
                            <?= $thread['last_message_at'] ? timeAgo($thread['last_message_at']) : '' ?>
                        </span>
                    </div>
                    <div class="thread-subject">
                        <?= e($thread['subject'] ?? 'General Conversation') ?>
                    </div>
                    <div class="thread-preview">
                        <?= $thread['last_message']
                            ? truncate(e($thread['last_message']), 90)
                            : '<em>No messages yet</em>' ?>
                    </div>
                </div>

                <div class="thread-badges">
                    <span class="badge <?= MessageThread::contextBadge($thread['context_type']) ?>"
                          style="font-size:10px;">
                        <?= MessageThread::contextLabel($thread['context_type'], $thread['context_id']) ?>
                    </span>
                    <?php if ($thread['status'] === 'closed'): ?>
                    <span class="badge badge-grey" style="font-size:10px;">Closed</span>
                    <?php endif; ?>
                    <?php if ($isUnread): ?>
                    <span class="unread-count"><?= (int)$thread['unread_admin'] ?></span>
                    <?php endif; ?>
                </div>

            </a>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
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
</div>

</body>
</html>
