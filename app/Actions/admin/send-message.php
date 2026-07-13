<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Admin Send Message
// POST only. Called by admin/messages/thread.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Mailer;
use Rise\Models\Message;
use Rise\Models\MessageThread;

if (!isPost()) redirect('/admin/messages/index.php');
Auth::requireAdmin();
verifyCsrf();

$adminId    = Auth::id();
$threadId   = (int) post('thread_id');
$body       = trim(post('body'));
$redirectTo = post('redirect_to', APP_URL . '/admin/messages/index.php');

if (!$threadId || empty($body)) {
    flash('Message cannot be empty.', 'error');
    redirect($redirectTo);
}

// Fetch thread
$thread = MessageThread::findById($threadId);

if (!$thread) {
    flash('Thread not found.', 'error');
    redirect('/admin/messages/index.php');
}

if ($thread['status'] === 'closed') {
    flash('This thread is closed and cannot receive new messages.', 'error');
    redirect($redirectTo);
}

try {
    // ── Insert message ────────────────────────────────────
    Message::create([
        'thread_id' => $threadId,
        'sender_id' => $adminId,
        'body'      => $body,
    ]);

    // ── Increment investor unread ─────────────────────────
    MessageThread::incrementUnread($threadId, 'investor');

    // ── Notify investor ───────────────────────────────────
    $admin = Auth::user();

    db()->insert('notifications', [
        'user_id'      => $thread['investor_id'],
        'type'         => 'new_message',
        'title'        => 'New Message from RISE Capital',
        'message'      => 'You have a new message regarding: ' .
                          ($thread['subject'] ?? 'your account'),
        'related_type' => 'message_thread',
        'related_id'   => $threadId,
        'is_read'      => 0,
        'created_at'   => date('Y-m-d H:i:s'),
    ]);

    // Email the investor
    $investor = db()->fetchOne(
        "SELECT u.email, p.first_name, p.last_name
         FROM users u
         LEFT JOIN user_profiles p ON p.user_id = u.id
         WHERE u.id = ? LIMIT 1",
        [$thread['investor_id']]
    );

    if ($investor) {
        Mailer::sendNewMessageNotification(
            $investor['email'],
            $investor['first_name'] . ' ' . $investor['last_name'],
            $admin['first_name'] . ' ' . $admin['last_name'],
            $thread['subject'] ?? 'Your Account'
        );
    }

    Auth::audit($adminId, 'admin_send_message', 'message_thread', $threadId);

    redirect($redirectTo);

} catch (\Throwable $e) {
    error_log('admin send-message error: ' . $e->getMessage());
    flash('Something went wrong. Please try again.', 'error');
    redirect($redirectTo);
}