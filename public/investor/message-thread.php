<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Message Thread View
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Message;
use Rise\Models\MessageThread;

Auth::requireInvestor();

$userId   = Auth::id();
$threadId = (int) get('id');

if (!$threadId) redirect('/investor/messages.php');

// Fetch thread — must belong to this investor
$thread = db()->fetchOne(
    "SELECT * FROM message_threads WHERE id = ? AND investor_id = ? LIMIT 1",
    [$threadId, $userId]
);

if (!$thread) redirect('/investor/messages.php');

// Mark thread as read by investor
MessageThread::resetUnread($threadId, 'investor');
Message::markThreadRead($threadId, $userId);

// Fetch all messages
$messages = Message::findByThread($threadId);

// Context info (transaction or project)
$contextInfo = null;
if ($thread['context_type'] === 'transaction' && $thread['context_id']) {
    $contextInfo = db()->fetchOne(
        "SELECT type, amount, status FROM wallet_transactions WHERE id = ? LIMIT 1",
        [$thread['context_id']]
    );
} elseif ($thread['context_type'] === 'project' && $thread['context_id']) {
    $contextInfo = db()->fetchOne(
        "SELECT title, location FROM projects WHERE id = ? LIMIT 1",
        [$thread['context_id']]
    );
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= e($thread['subject'] ?? 'Messages') ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .chat-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 24px;
            align-items: flex-start;
            height: calc(100vh - 140px);
        }

        /* Chat panel */
        .chat-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }

        .chat-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-shrink: 0;
        }

        .chat-title  { font-size: 15px; font-weight: 700; }
        .chat-sub    { font-size: 12px; color: var(--muted); margin-top: 2px; }

        /* Messages area */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .msg-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .msg-row.mine { flex-direction: row-reverse; }

        .msg-avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--gold-dim);
            border: 2px solid var(--gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: var(--gold);
            flex-shrink: 0;
        }

        .msg-avatar.admin-avatar {
            background: var(--surface2);
            border-color: var(--border);
            color: var(--text);
        }

        .msg-bubble {
            max-width: 70%;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.6;
            word-break: break-word;
        }

        .msg-bubble.mine {
            background: var(--gold);
            color: #000;
            border-bottom-right-radius: 4px;
        }

        .msg-bubble.theirs {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
            border-bottom-left-radius: 4px;
        }

        .msg-time {
            font-size: 10px;
            color: var(--muted);
            margin-top: 4px;
            text-align: right;
        }

        .msg-row.mine .msg-time { text-align: right; }
        .msg-row:not(.mine) .msg-time { text-align: left; }

        /* Compose area */
        .chat-compose {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }

        .compose-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .compose-row textarea {
            flex: 1;
            min-height: 44px;
            max-height: 120px;
            resize: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            line-height: 1.5;
        }

        .send-btn {
            width: 44px; height: 44px;
            background: var(--gold);
            border: none;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 18px;
            flex-shrink: 0;
            transition: background .2s;
        }

        .send-btn:hover { background: var(--gold-light); }
        .send-btn:disabled { opacity: .5; cursor: not-allowed; }

        /* Context sidebar */
        .context-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            position: sticky;
            top: 76px;
        }

        .context-title {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 14px;
        }

        .context-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
        }

        .context-row:last-child { border-bottom: none; }
        .context-label { color: var(--muted); }
        .context-value { font-weight: 600; text-align: right; max-width: 55%; }

        .closed-banner {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 12px;
            color: var(--muted);
            text-align: center;
        }

        @media (max-width: 800px) {
            .chat-layout { grid-template-columns: 1fr; height: auto; }
            .chat-panel  { height: 70vh; }
            .context-card { position: static; }
        }
    </style>
</head>
<body class="investor-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-investor.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <a href="<?= APP_URL ?>/investor/messages.php" class="back-link"
           style="display:inline-block;margin-bottom:16px;">← Back to Messages</a>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div class="chat-layout">

            <!-- Chat panel -->
            <div class="chat-panel">
                <div class="chat-header">
                    <div>
                        <div class="chat-title">
                            <?= e($thread['subject'] ?? 'General Conversation') ?>
                        </div>
                        <div class="chat-sub">
                            <span class="badge <?= MessageThread::contextBadge($thread['context_type']) ?>">
                                <?= MessageThread::contextLabel($thread['context_type'], $thread['context_id']) ?>
                            </span>
                            &nbsp;·&nbsp;
                            <?= $thread['status'] === 'open' ? '🟢 Open' : '⚫ Closed' ?>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                    <div style="text-align:center;color:var(--muted);font-size:13px;
                                padding:32px 0;">No messages yet. Send one below.</div>
                    <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <?php $isMine = (int)$msg['sender_id'] === $userId; ?>
                    <div class="msg-row <?= $isMine ? 'mine' : '' ?>">
                        <div class="msg-avatar <?= !$isMine ? 'admin-avatar' : '' ?>">
                            <?php if (!empty($msg['avatar_path']) && !$isMine): ?>
                            <img src="<?= e($msg['avatar_path']) ?>" alt=""
                                 style="width:100%;height:100%;object-fit:cover;border-radius:50%;"/>
                            <?php else: ?>
                            <?= $isMine
                                ? strtoupper(substr(Auth::user()['first_name'] ?? 'I', 0, 1))
                                : 'R' ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="msg-bubble <?= $isMine ? 'mine' : 'theirs' ?>">
                                <?= nl2br(e($msg['body'])) ?>
                            </div>
                            <div class="msg-time">
                                <?= $isMine ? 'You' : e($msg['sender_first']) ?>
                                · <?= timeAgo($msg['created_at']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Compose -->
                <?php if ($thread['status'] === 'open'): ?>
                <div class="chat-compose">
                    <form method="POST"
                          action="<?= APP_URL ?>/app/Actions/investor/send-message.php"
                          id="composeForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="thread_id" value="<?= $threadId ?>"/>
                        <input type="hidden" name="redirect_to"
                               value="<?= APP_URL ?>/investor/message-thread.php?id=<?= $threadId ?>"/>
                        <div class="compose-row">
                            <textarea name="body" id="messageBody"
                                      placeholder="Type your message..."
                                      required
                                      onkeydown="handleEnter(event)"></textarea>
                            <button type="submit" class="send-btn" id="sendBtn">➤</button>
                        </div>
                        <div style="font-size:11px;color:var(--muted);margin-top:6px;">
                            Press Enter to send · Shift+Enter for new line
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div style="padding:16px 20px;">
                    <div class="closed-banner">
                        🔒 This conversation has been closed.
                        <a href="<?= APP_URL ?>/app/Actions/investor/start-thread.php"
                           style="color:var(--gold);">Start a new one →</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Context sidebar -->
            <div>
                <div class="context-card">
                    <div class="context-title">Thread Info</div>

                    <div class="context-row">
                        <span class="context-label">Type</span>
                        <span class="context-value">
                            <?= MessageThread::contextLabel($thread['context_type'], $thread['context_id']) ?>
                        </span>
                    </div>
                    <div class="context-row">
                        <span class="context-label">Status</span>
                        <span class="context-value">
                            <span class="badge <?= $thread['status'] === 'open' ? 'badge-green' : 'badge-grey' ?>">
                                <?= ucfirst($thread['status']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="context-row">
                        <span class="context-label">Started</span>
                        <span class="context-value">
                            <?= formatDate($thread['created_at']) ?>
                        </span>
                    </div>
                    <div class="context-row">
                        <span class="context-label">Messages</span>
                        <span class="context-value"><?= count($messages) ?></span>
                    </div>

                    <!-- Transaction context -->
                    <?php if ($thread['context_type'] === 'transaction' && $contextInfo): ?>
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">
                        <div class="context-title">Related Transaction</div>
                        <div class="context-row">
                            <span class="context-label">Type</span>
                            <span class="context-value">
                                <span class="badge <?= $contextInfo['type'] === 'deposit' ? 'badge-green' : 'badge-orange' ?>">
                                    <?= ucfirst($contextInfo['type']) ?>
                                </span>
                            </span>
                        </div>
                        <div class="context-row">
                            <span class="context-label">Amount</span>
                            <span class="context-value text-gold">
                                <?= formatMoney((float)$contextInfo['amount']) ?>
                            </span>
                        </div>
                        <div class="context-row">
                            <span class="context-label">Status</span>
                            <span class="context-value">
                                <?= ucfirst($contextInfo['status']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Project context -->
                    <?php if ($thread['context_type'] === 'project' && $contextInfo): ?>
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">
                        <div class="context-title">Related Project</div>
                        <div class="context-row">
                            <span class="context-label">Project</span>
                            <span class="context-value"><?= e($contextInfo['title']) ?></span>
                        </div>
                        <?php if ($contextInfo['location']): ?>
                        <div class="context-row">
                            <span class="context-label">Location</span>
                            <span class="context-value"><?= e($contextInfo['location']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Scroll to bottom of chat on load
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;

// Enter to send, Shift+Enter for newline
function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        const body = document.getElementById('messageBody');
        if (body.value.trim()) {
            document.getElementById('composeForm').submit();
        }
    }
}
</script>

</body>
</html>