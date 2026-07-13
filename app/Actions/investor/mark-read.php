<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Mark Thread Read (investor)
// GET or POST. Resets investor unread counter for a thread.
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Message;
use Rise\Models\MessageThread;

Auth::requireInvestor();

$threadId = (int) (get('thread_id') ?: post('thread_id'));

if ($threadId) {
    $thread = db()->fetchOne(
        "SELECT id FROM message_threads WHERE id = ? AND investor_id = ? LIMIT 1",
        [$threadId, Auth::id()]
    );

    if ($thread) {
        MessageThread::resetUnread($threadId, 'investor');
        Message::markThreadRead($threadId, Auth::id());
    }
}

redirect('/investor/messages.php');