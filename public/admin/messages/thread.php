<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Single Message Thread
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Message;
use Rise\Models\MessageThread;

Auth::requireAdmin();

$adminId  = Auth::id();
$threadId = (int) get('id');

// Support opening by investor_id (creates/finds a general thread)
$investorId = (int) get('investor_id');

if (!$threadId && $investorId) {
    // Find existing general thread or create one
    $existing = db()->fetchOne(
        "SELECT id FROM message_threads
         WHERE investor_id = ? AND context_type = 'general'
         ORDER BY created_at DESC LIMIT 1",
        [$investorId]
    );

    if ($existing) {
        $threadId = (int) $existing['id'];
    } else {
        $investor = db()->fetchOne(
            "SELECT p.first_name, p.last_name FROM user_profiles p WHERE p.user_id = ? LIMIT 1",
            [$investorId]
        );
        $name = $investor ? $investor['first_name'] . ' ' . $investor['last_name'] : 'Investor';

        $threadId = (int) MessageThread::create([
            'investor_id'     => $investorId,
            'context_type'    => 'general',
            'subject'         => "Conversation with {$name}",
            'unread_admin'    => 0,
            'unread_investor' => 0,
        ]);
    }

    redirect(APP_URL . '/admin/messages/thread.php?id=' . $threadId);
}

if (!$threadId) redirect('/admin/messages/index.php');

$thread = MessageThread::findById($threadId);
if (!$thread) redirect('/admin/messages/index.php');

// Mark as read by admin
MessageThread::resetUnread($threadId, 'admin');
Message::markThreadRead($threadId, $adminId);

$messages = Message::findByThread($threadId);

// Context info
$contextInfo = null;
if ($thread['context_type'] === 'transaction' && $thread['context_id']) {
    $contextInfo = db()->fetchOne(
        "SELECT type, amount, status, created_at FROM wallet_transactions WHERE id = ? LIMIT 1",
        [$thread['context_id']]
    );
} elseif ($thread['context_type'] === 'project' && $thread['context_id']) {
    $contextInfo = db()->fetchOne(
        "SELECT title, location, status FROM projects WHERE id = ? LIMIT 1",
        [$thread['context_id']]
    );
}

// Investor stats for sidebar
$investorStats = db()->fetchOne(
    "SELECT
        COALESCE(wb.balance, 0)           AS balance,
        COUNT(DISTINCT i.id)              AS investment_count,
        COALESCE(SUM(i.amount), 0)        AS total_invested
     FROM users u
     LEFT JOIN wallet_balances wb ON wb.user_id = u.id
     LEFT JOIN investments i ON i.user_id = u.id AND i.status = 'active'
     WHERE u.id = ?",
    [$thread['investor_id']]
) ?? [];

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

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .msg-row { display:flex; gap:10px; align-items:flex-end; }
        .msg-row.mine { flex-direction:row-reverse; }

        .msg-avatar {
            width:32px; height:32px; border-radius:50%;
            background:var(--surface2); border:2px solid var(--border);
            display:flex; align-items:center; justify-content:center;
            font-size:12px; font-weight:700; color:var(--text);
            flex-shrink:0; overflow:hidden;
        }

        .msg-avatar.admin-self {
            background: var(--gold-dim);
            border-color: var(--gold);
            color: var(--gold);
        }

        .msg-bubble {
            max-width:70%; padding:10px 14px;
            border-radius:12px; font-size:13px;
            line-height:1.6; word-break:break-word;
        }

        .msg-bubble.mine {
            background:var(--gold); color:#000;
            border-bottom-right-radius:4px;
        }

        .msg-bubble.theirs {
            background:var(--surface2); color:var(--text);
            border:1px solid var(--border);
            border-bottom-left-radius:4px;
        }

        .msg-time {
            font-size:10px; color:var(--muted); margin-top:4px;
        }

        .chat-compose {
            padding:16px 20px;
            border-top:1px solid var(--border);
            flex-shrink:0;
        }

        .compose-row { display:flex; gap:10px; align-items:flex-end; }

        .compose-row textarea {
            flex:1; min-height:44px; max-height:120px;
            resize:none; border-radius:10px;
            padding:10px 14px; font-size:13px; line-height:1.5;
        }

        .send-btn {
            width:44px; height:44px; background:var(--gold);
            border:none; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; font-size:18px; flex-shrink:0;
            transition:background .2s;
        }

        .send-btn:hover { background:var(--gold-light); }

        .sidebar-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:12px; padding:18px;
            margin-bottom:16px; position:sticky; top:76px;
        }

        .sidebar-title {
            font-size:11px; font-weight:700; letter-spacing:1px;
            text-transform:uppercase; color:var(--muted); margin-bottom:12px;
        }

        .sidebar-row {
            display:flex; justify-content:space-between;
            align-items:center; padding:7px 0;
            border-bottom:1px solid var(--border);
            font-size:12px;
        }

        .sidebar-row:last-child { border-bottom:none; }
        .sidebar-label { color:var(--muted); }
        .sidebar-value { font-weight:600; }

        .closed-banner {
            background:var(--surface2); border:1px solid var(--border);
            border-radius:8px; padding:10px 14px;
            font-size:12px; color:var(--muted); text-align:center;
        }

        @media (max-width:800px) {
            .chat-layout { grid-template-columns:1fr; height:auto; }
            .chat-panel  { height:65vh; }
            .sidebar-card { position:static; }
        }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <a href="<?= APP_URL ?>/admin/messages/index.php" class="back-link"
           style="display:inline-block;margin-bottom:16px;">← Back to Messages</a>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <div class="chat-layout">

            <!-- Chat panel -->
            <div class="chat-panel">
                <div class="chat-header">
                    <div>
                        <div style="font-size:15px;font-weight:700;">
                            <?= e($thread['subject'] ?? 'General Conversation') ?>
                        </div>
                        <div style="font-size:12px;color:var(--muted);margin-top:2px;
                                    display:flex;align-items:center;gap:8px;">
                            <span>
                                with <?= e($thread['investor_first'] . ' ' . $thread['investor_last']) ?>
                            </span>
                            <span class="badge <?= MessageThread::contextBadge($thread['context_type']) ?>"
                                  style="font-size:10px;">
                                <?= MessageThread::contextLabel($thread['context_type'], $thread['context_id']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-8">
                        <a href="<?= APP_URL ?>/admin/users/view.php?id=<?= $thread['investor_id'] ?>"
                           class="btn-secondary btn-sm">View Investor</a>
                        <?php if ($thread['status'] === 'open'): ?>
                        <form method="POST"
                              action="<?= APP_URL ?>/app/Actions/admin/close-thread.php"
                              onsubmit="return confirm('Close this thread?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="thread_id" value="<?= $threadId ?>"/>
                            <button type="submit" class="btn-secondary btn-sm">Close Thread</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messages -->
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                    <div style="text-align:center;color:var(--muted);font-size:13px;padding:32px 0;">
                        No messages yet. Send the first one below.
                    </div>
                    <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <?php $isMine = $msg['sender_role'] === 'admin'; ?>
                    <div class="msg-row <?= $isMine ? 'mine' : '' ?>">
                        <div class="msg-avatar <?= $isMine ? 'admin-self' : '' ?>">
                            <?php if (!empty($msg['avatar_path']) && !$isMine): ?>
                            <img src="<?= e($msg['avatar_path']) ?>" alt=""
                                 style="width:100%;height:100%;object-fit:cover;border-radius:50%;"/>
                            <?php else: ?>
                            <?= $isMine ? 'R' : strtoupper(substr($msg['sender_first'] ?? '?', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="msg-bubble <?= $isMine ? 'mine' : 'theirs' ?>">
                                <?= nl2br(e($msg['body'])) ?>
                            </div>
                            <div class="msg-time">
                                <?= $isMine ? 'You (Admin)' : e($msg['sender_first']) ?>
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
                          action="<?= APP_URL ?>/app/Actions/admin/send-message.php"
                          id="composeForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="thread_id" value="<?= $threadId ?>"/>
                        <input type="hidden" name="redirect_to"
                               value="<?= APP_URL ?>/admin/messages/thread.php?id=<?= $threadId ?>"/>
                        <div class="compose-row">
                            <textarea name="body" id="messageBody"
                                      placeholder="Type your message to <?= e($thread['investor_first']) ?>..."
                                      required
                                      onkeydown="handleEnter(event)"></textarea>
                            <button type="submit" class="send-btn">➤</button>
                        </div>
                        <div style="font-size:11px;color:var(--muted);margin-top:6px;">
                            Enter to send · Shift+Enter for new line
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div style="padding:16px 20px;">
                    <div class="closed-banner">🔒 This thread is closed.</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Investor summary -->
                <div class="sidebar-card">
                    <div class="sidebar-title">Investor</div>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                        <div class="avatar">
                            <?php if (!empty($thread['avatar_path'])): ?>
                            <img src="<?= e($thread['avatar_path']) ?>" alt=""/>
                            <?php else: ?>
                            <?= strtoupper(substr($thread['investor_first'] ?? '?', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:13px;">
                                <?= e($thread['investor_first'] . ' ' . $thread['investor_last']) ?>
                            </div>
                            <div style="font-size:11px;color:var(--muted);">
                                <?= e($thread['investor_email']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Balance</span>
                        <span class="sidebar-value text-gold">
                            <?= formatMoney((float)($investorStats['balance'] ?? 0)) ?>
                        </span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Investments</span>
                        <span class="sidebar-value">
                            <?= (int)($investorStats['investment_count'] ?? 0) ?>
                        </span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Total Invested</span>
                        <span class="sidebar-value">
                            <?= formatCurrency((float)($investorStats['total_invested'] ?? 0)) ?>
                        </span>
                    </div>
                    <div style="margin-top:12px;">
                        <a href="<?= APP_URL ?>/admin/users/view.php?id=<?= $thread['investor_id'] ?>"
                           class="btn-secondary btn-sm" style="width:100%;justify-content:center;">
                            Full Profile →
                        </a>
                    </div>
                </div>

                <!-- Context card -->
                <?php if ($contextInfo): ?>
                <div class="sidebar-card">
                    <div class="sidebar-title">
                        <?= $thread['context_type'] === 'transaction' ? 'Transaction' : 'Project' ?>
                    </div>

                    <?php if ($thread['context_type'] === 'transaction'): ?>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Type</span>
                        <span class="sidebar-value">
                            <span class="badge <?= $contextInfo['type'] === 'deposit' ? 'badge-green' : 'badge-orange' ?>">
                                <?= ucfirst($contextInfo['type']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Amount</span>
                        <span class="sidebar-value text-gold">
                            <?= formatMoney((float)$contextInfo['amount']) ?>
                        </span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Status</span>
                        <span class="sidebar-value"><?= ucfirst($contextInfo['status']) ?></span>
                    </div>
                    <div style="margin-top:12px;">
                        <a href="<?= APP_URL ?>/admin/wallet/pending.php"
                           class="btn-primary btn-sm" style="width:100%;justify-content:center;">
                            Manage Request →
                        </a>
                    </div>

                    <?php elseif ($thread['context_type'] === 'project'): ?>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Project</span>
                        <span class="sidebar-value"><?= e($contextInfo['title']) ?></span>
                    </div>
                    <?php if ($contextInfo['location']): ?>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Location</span>
                        <span class="sidebar-value"><?= e($contextInfo['location']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Status</span>
                        <span class="sidebar-value"><?= ucfirst($contextInfo['status']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Thread info -->
                <div class="sidebar-card">
                    <div class="sidebar-title">Thread Info</div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Messages</span>
                        <span class="sidebar-value"><?= count($messages) ?></span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Status</span>
                        <span class="sidebar-value">
                            <span class="badge <?= $thread['status'] === 'open' ? 'badge-green' : 'badge-grey' ?>">
                                <?= ucfirst($thread['status']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Started</span>
                        <span class="sidebar-value"><?= formatDate($thread['created_at']) ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;

function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        const body = document.getElementById('messageBody');
        if (body.value.trim()) document.getElementById('composeForm').submit();
    }
}
</script>

</body>
</html>
