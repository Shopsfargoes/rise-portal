<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Investor Send Message
// POST only. Handles both new thread creation and replies.
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Mailer;
use Rise\Models\Message;
use Rise\Models\MessageThread;

if (!isPost()) redirect('/investor/messages.php');
Auth::requireInvestor();
verifyCsrf();

$userId     = Auth::id();
$body       = trim(post('body'));
$threadId   = (int) post('thread_id');
$redirectTo = post('redirect_to', APP_URL . '/investor/messages.php');

// For new threads (from project-detail.php request info form)
$contextType = post('context_type', 'general');
$contextId   = (int) post('context_id') ?: null;
$subject     = trim(post('subject', ''));

if (empty($body)) {
    flash('Message cannot be empty.', 'error');
    redirect($redirectTo);
}

try {
    // ── Existing thread — just add a message ──────────────
    if ($threadId) {
        $thread = db()->fetchOne(
            "SELECT * FROM message_threads WHERE id = ? AND investor_id = ? LIMIT 1",
            [$threadId, $userId]
        );

        if (!$thread) {
            flash('Thread not found.', 'error');
            redirect('/investor/messages.php');
        }

        if ($thread['status'] === 'closed') {
            flash('This conversation is closed.', 'error');
            redirect($redirectTo);
        }

        Message::create([
            'thread_id' => $threadId,
            'sender_id' => $userId,
            'body'      => $body,
        ]);

        MessageThread::incrementUnread($threadId, 'admin');

    } else {
        // ── New thread ────────────────────────────────────
        // Check for existing thread with same context
        $existing = null;
        if ($contextType !== 'general' && $contextId) {
            $existing = MessageThread::findByContext($contextType, $contextId);
        }

        if ($existing) {
            $threadId = (int) $existing['id'];
        } else {
            $threadId = (int) MessageThread::create([
                'investor_id'     => $userId,
                'context_type'    => $contextType,
                'context_id'      => $contextId,
                'subject'         => $subject ?: 'New Conversation',
                'unread_admin'    => 1,
                'unread_investor' => 0,
            ]);
        }

        Message::create([
            'thread_id' => $threadId,
            'sender_id' => $userId,
            'body'      => $body,
        ]);

        MessageThread::incrementUnread($threadId, 'admin');
    }

    // ── Notify all admins ─────────────────────────────────
    $admins = db()->fetchAll(
        "SELECT u.id, u.email, p.first_name FROM users u
         LEFT JOIN user_profiles p ON p.user_id = u.id
         WHERE u.role = 'admin' AND u.status = 'active'"
    );

    $user = Auth::user();

    foreach ($admins as $admin) {
        db()->insert('notifications', [
            'user_id'      => $admin['id'],
            'type'         => 'new_message',
            'title'        => 'New Message',
            'message'      => ($user['first_name'] . ' ' . $user['last_name']) .
                              ' sent a message.',
            'related_type' => 'message_thread',
            'related_id'   => $threadId,
            'is_read'      => 0,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        // Email notification to admin
        Mailer::sendNewMessageNotification(
            $admin['email'],
            $admin['first_name'] ?? 'Admin',
            $user['first_name'] . ' ' . $user['last_name'],
            post('subject', 'New Message')
        );
    }

    Auth::audit($userId, 'send_message', 'message_thread', $threadId);

    // Redirect to the thread view
    redirect('/investor/message-thread.php?id=' . $threadId);

} catch (\Throwable $e) {
    error_log('investor send-message error: ' . $e->getMessage());
    flash('Something went wrong sending your message. Please try again.', 'error');
    redirect($redirectTo);
}