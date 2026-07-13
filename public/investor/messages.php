<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Messages Inbox
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\MessageThread;

Auth::requireInvestor();

$userId  = Auth::id();
$threads = MessageThread::findByInvestor($userId);

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
        .thread-list { display:flex; flex-direction:column; gap:8px; }

        .thread-card {
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
            position: relative;
        }

        .thread-card:hover {
            border-color: var(--gold);
            background: var(--surface2);
            text-decoration: none;
            color: var(--text);
        }

        .thread-card.unread { border-left: 3px solid var(--gold); }

        .thread-avatar {
            width: 42px; height: 42px;
            border-radius: 50%;
            background: var(--gold-dim);
            border: 2px solid var(--gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: 700; color: var(--gold);
            flex-shrink: 0;
        }

        .thread-info { flex: 1; min-width: 0; }

        .thread-subject {
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px;
        }

        .thread-preview {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .thread-meta {
            text-align: right;
            flex-shrink: 0;
        }

        .thread-time {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .unread-dot {
            width: 8px; height: 8px;
            background: var(--gold);
            border-radius: 50%;
            display: inline-block;
        }

        .new-thread-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: border-color .2s, color .2s;
        }

        .new-thread-btn:hover {
            border-color: var(--gold);
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
                <h1 class="page-title">Messages</h1>
                <p class="page-sub">
                    <?= count($threads) ?> conversation<?= count($threads) !== 1 ? 's' : '' ?>
                    with RISE Capital Group
                </p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div class="thread-list">

            <!-- Start new general thread -->
            <a href="<?= APP_URL ?>/app/Actions/investor/start-thread.php"
               class="new-thread-btn">
                <span style="font-size:18px;">✏️</span>
                Start a new conversation
            </a>

            <?php if (empty($threads)): ?>
            <div style="text-align:center;padding:48px 20px;color:var(--muted);">
                <div style="font-size:40px;margin-bottom:12px;">💬</div>
                <p>No messages yet.</p>
                <p style="font-size:12px;margin-top:6px;">
                    Messages from your deposit and withdrawal requests will appear here.
                </p>
            </div>
            <?php else: ?>

            <?php foreach ($threads as $thread): ?>
            <?php $isUnread = (int)$thread['unread_investor'] > 0; ?>
            <a href="<?= APP_URL ?>/investor/message-thread.php?id=<?= $thread['id'] ?>"
               class="thread-card <?= $isUnread ? 'unread' : '' ?>">

                <!-- Avatar — RISE logo for admin threads -->
                <div class="thread-avatar">R</div>

                <div class="thread-info">
                    <div class="thread-subject">
                        <?= e($thread['subject'] ?? 'General Conversation') ?>
                    </div>
                    <div class="thread-preview">
                        <?php if ($thread['last_message']): ?>
                            <?= truncate(e($thread['last_message']), 80) ?>
                        <?php else: ?>
                            <em>No messages yet</em>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="thread-meta">
                    <div class="thread-time">
                        <?= $thread['last_message_at'] ? timeAgo($thread['last_message_at']) : '' ?>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                        <span class="badge <?= MessageThread::contextBadge($thread['context_type']) ?>"
                              style="font-size:10px;">
                            <?= MessageThread::contextLabel($thread['context_type'], $thread['context_id']) ?>
                        </span>
                        <?php if ($isUnread): ?>
                        <span class="unread-dot"></span>
                        <?php endif; ?>
                        <?php if ($thread['status'] === 'closed'): ?>
                        <span class="badge badge-grey" style="font-size:10px;">Closed</span>
                        <?php endif; ?>
                    </div>
                </div>

            </a>
            <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>