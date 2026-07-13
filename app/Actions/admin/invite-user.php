<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Invite New User
// POST only. Called by admin/users/create.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Mailer;

// Guards
if (!isPost()) redirect('/admin/users/index.php');
Auth::requireAdmin();
verifyCsrf();

// ── Collect & validate input ──────────────────────────────
$firstName  = trim(post('first_name'));
$lastName   = trim(post('last_name'));
$email      = strtolower(trim(post('email')));
$phone      = trim(post('phone'));
$role       = post('role', 'investor');
$accredited = post('accredited', '0');
$notes      = trim(post('notes'));

$errors = [];

if (empty($firstName))       $errors[] = 'First name is required.';
if (empty($lastName))        $errors[] = 'Last name is required.';
if (empty($email))           $errors[] = 'Email address is required.';
if (!isValidEmail($email))   $errors[] = 'Please enter a valid email address.';
if (!in_array($role, ['admin', 'investor'])) $errors[] = 'Invalid role selected.';

// Check email not already in use
if (empty($errors)) {
    $existing = db()->fetchOne(
        "SELECT id FROM users WHERE email = ? LIMIT 1",
        [$email]
    );
    if ($existing) {
        $errors[] = 'An account with this email address already exists.';
    }
}

// ── Return errors to form ─────────────────────────────────
if (!empty($errors)) {
    // Store form data so fields repopulate
    $_SESSION['form_data'] = $_POST;
    flash(implode(' ', $errors), 'error');
    redirect('/admin/users/create.php');
}

// ── Create account & send invite ──────────────────────────
try {
    db()->transaction(function() use (
        $firstName, $lastName, $email, $phone,
        $role, $accredited, $notes
    ) {
        $now          = date('Y-m-d H:i:s');
        $inviteToken  = generateToken(64);
        $inviteExpiry = date('Y-m-d H:i:s', strtotime('+48 hours'));

        // 1. Insert user row
        $userId = db()->insert('users', [
            'uuid'           => uuid4(),
            'email'          => $email,
            'password_hash'  => '',               // set when they accept invite
            'role'           => $role,
            'status'         => 'pending',         // activated after invite accepted
            'invited_by'     => Auth::id(),
            'invite_token'   => $inviteToken,
            'invite_expires' => $inviteExpiry,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        // 2. Insert profile row
        db()->insert('user_profiles', [
            'user_id'       => $userId,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'phone'         => $phone,
            'accredited'    => (int) $accredited,
            'accredited_at' => ($accredited === '1') ? $now : null,
            'notes'         => $notes,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // 3. Create empty wallet balance row
        db()->insert('wallet_balances', [
            'user_id'    => $userId,
            'balance'    => 0.00,
            'updated_at' => $now,
        ]);

        // 4. Send invite email
        $sent = Mailer::sendInvite(
            $email,
            $firstName . ' ' . $lastName,
            $inviteToken,
            Auth::name()
        );

        if (!$sent) {
            // Email failed but account was created — log it
            error_log("Invite email failed for user ID {$userId} ({$email})");
        }

        // 5. Audit log
        Auth::audit(Auth::id(), 'invite_user', 'user', (int)$userId, [
            'invited_email' => $email,
            'role'          => $role,
            'email_sent'    => $sent,
        ]);
    });

    flash("Invitation sent to {$firstName} {$lastName} ({$email}) successfully.", 'success');
    redirect('/admin/users/index.php');

} catch (\Throwable $e) {
    error_log('invite-user error: ' . $e->getMessage());
    flash('Something went wrong creating the account. Please try again.', 'error');
    $_SESSION['form_data'] = $_POST;
    redirect('/admin/users/create.php');
}