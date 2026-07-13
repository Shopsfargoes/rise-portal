<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Mark Notifications Read
// Works for both admin and investor.
// GET: mark_all=1 (redirect after)
// POST: notif_id=X (single) or mark_all=1 (AJAX — no redirect)
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Notification;

Auth::requireLogin();

$userId  = Auth::id();
$markAll = (bool)(get('mark_all') ?: post('mark_all'));
$notifId = (int)(get('notif_id') ?: post('notif_id'));
$isAjax  = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
           || (isset($_SERVER['CONTENT_TYPE'])
               && str_contains($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded')
               && !isPost());

// Determine redirect URL
$redirectTo = get('redirect')
    ?: (Auth::isAdmin()
        ? APP_URL . '/admin/notifications/index.php'
        : APP_URL . '/investor/dashboard.php');

// Mark all
if ($markAll) {
    Notification::markAllRead($userId);

    if (isPost() && !get('redirect')) {
        // AJAX call — just return 200
        http_response_code(200);
        exit;
    }

    flash('All notifications marked as read.', 'success');
    redirect(str_replace(APP_URL, '', $redirectTo));
}

// Mark single
if ($notifId) {
    Notification::markRead($notifId, $userId);

    if (isPost()) {
        http_response_code(200);
        exit;
    }
}

// Default redirect
redirect(str_replace(APP_URL, '', $redirectTo));