<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Admin Close Thread
// POST only. Called by admin/messages/thread.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\MessageThread;

if (!isPost()) redirect('/admin/messages/index.php');
Auth::requireAdmin();
verifyCsrf();

$threadId = (int) post('thread_id');

if (!$threadId) {
    flash('Invalid thread.', 'error');
    redirect('/admin/messages/index.php');
}

$thread = MessageThread::findById($threadId);

if (!$thread) {
    flash('Thread not found.', 'error');
    redirect('/admin/messages/index.php');
}

MessageThread::close($threadId);

Auth::audit(Auth::id(), 'close_thread', 'message_thread', $threadId);

flash('Conversation closed.', 'success');
redirect('/admin/messages/thread.php?id=' . $threadId);