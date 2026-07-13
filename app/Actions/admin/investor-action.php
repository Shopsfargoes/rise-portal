<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Investor Account Actions
// POST only. Handles suspend / activate / resend_invite
// Called by the danger zone buttons on admin/users/edit.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Mailer;

if (!isPost()) redirect('/admin/users/index.php');
Auth::requireAdmin();
verifyCsrf();

$userId = (int) post('user_id');
$action = post('action');

if (!$userId || !$action) redirect('/admin/users/index.php');

// Fetch investor
$investor = db()->fetchOne(
    "SELECT u.id, u.email, u.status, u.invite_token,
            p.first_name, p.last_name
     FROM users u
     LEFT JOIN user_profiles p ON p.user_id = u.id
     WHERE u.id = ? LIMIT 1",
    [$userId]
);

if (!$investor) redirect('/admin/users/index.php');

$now = date('Y-m-d H:i:s');

switch ($action) {

    case 'suspend':
        db()->update('users', ['status' => 'suspended', 'updated_at' => $now], ['id' => $userId]);
        Auth::audit(Auth::id(), 'suspend_investor', 'user', $userId);
        flash("{$investor['first_name']} {$investor['last_name']}'s account has been suspended.", 'warning');
        break;

    case 'activate':
        db()->update('users', ['status' => 'active', 'updated_at' => $now], ['id' => $userId]);
        Auth::audit(Auth::id(), 'activate_investor', 'user', $userId);
        flash("{$investor['first_name']} {$investor['last_name']}'s account has been reactivated.", 'success');
        break;

    case 'resend_invite':
        // Generate fresh token
        $token   = generateToken(64);
        $expiry  = date('Y-m-d H:i:s', strtotime('+48 hours'));

        db()->update('users', [
            'invite_token'   => $token,
            'invite_expires' => $expiry,
            'status'         => 'pending',
            'updated_at'     => $now,
        ], ['id' => $userId]);

        $sent = Mailer::sendInvite(
            $investor['email'],
            $investor['first_name'] . ' ' . $investor['last_name'],
            $token,
            Auth::name()
        );

        Auth::audit(Auth::id(), 'resend_invite', 'user', $userId, ['email_sent' => $sent]);

        if ($sent) {
            flash("Invite email resent to {$investor['email']}.", 'success');
        } else {
            flash("Account reset but email failed to send. Check mail config.", 'warning');
        }
        break;

    default:
        flash('Unknown action.', 'error');
}

redirect("/admin/users/edit.php?id={$userId}");