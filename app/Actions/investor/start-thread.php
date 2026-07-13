<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Start New General Thread
// Creates a blank general thread and redirects to it.
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\MessageThread;

Auth::requireInvestor();

$userId = Auth::id();
$user   = Auth::user();

try {
    $threadId = MessageThread::create([
        'investor_id'     => $userId,
        'context_type'    => 'general',
        'subject'         => 'General Enquiry',
        'unread_admin'    => 0,
        'unread_investor' => 0,
    ]);

    Auth::audit($userId, 'start_thread', 'message_thread', (int)$threadId);

    redirect('/investor/message-thread.php?id=' . $threadId);

} catch (\Throwable $e) {
    error_log('start-thread error: ' . $e->getMessage());
    flash('Could not start a new conversation. Please try again.', 'error');
    redirect('/investor/messages.php');
}