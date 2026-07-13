<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Update Investor Profile
// POST only. Called by admin/users/edit.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Uploader;

if (!isPost()) redirect('/admin/users/index.php');
Auth::requireAdmin();
verifyCsrf();

$userId = (int) post('user_id');
if (!$userId) redirect('/admin/users/index.php');

// Verify investor exists
$existing = db()->fetchOne("SELECT id, email FROM users WHERE id = ? LIMIT 1", [$userId]);
if (!$existing) redirect('/admin/users/index.php');

// ── Validate ──────────────────────────────────────────────
$firstName   = trim(post('first_name'));
$lastName    = trim(post('last_name'));
$email       = strtolower(trim(post('email')));
$phone       = trim(post('phone'));
$addressLine1= trim(post('address_line1'));
$addressLine2= trim(post('address_line2'));
$city        = trim(post('city'));
$state       = trim(post('state'));
$zip         = trim(post('zip'));
$country     = trim(post('country', 'US'));
$status      = post('status', 'active');
$accredited  = post('accredited', '0');
$notes       = trim(post('notes'));

$errors = [];

if (empty($firstName))     $errors[] = 'First name is required.';
if (empty($lastName))      $errors[] = 'Last name is required.';
if (empty($email))         $errors[] = 'Email is required.';
if (!isValidEmail($email)) $errors[] = 'Please enter a valid email address.';
if (!in_array($status, ['active', 'suspended', 'pending'])) $errors[] = 'Invalid status.';

// Check email uniqueness (allow same email for this user)
if (empty($errors) && $email !== $existing['email']) {
    $dupe = db()->fetchOne("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1", [$email, $userId]);
    if ($dupe) $errors[] = 'That email address is already in use by another account.';
}

if (!empty($errors)) {
    $_SESSION['form_data'] = $_POST;
    flash(implode(' ', $errors), 'error');
    redirect("/admin/users/edit.php?id={$userId}");
}

// ── Handle avatar upload ──────────────────────────────────
$avatarPath = null;

if (!empty($_FILES['avatar']['name'])) {
    $upload = new Uploader();
    $result = $upload->image($_FILES['avatar'], 'avatars');

    if ($result['success']) {
        $avatarPath = $result['path'];
    } else {
        flash($result['error'], 'error');
        $_SESSION['form_data'] = $_POST;
        redirect("/admin/users/edit.php?id={$userId}");
    }
}

// ── Save ──────────────────────────────────────────────────
try {
    db()->transaction(function() use (
        $userId, $email, $status, $firstName, $lastName,
        $phone, $addressLine1, $addressLine2, $city, $state,
        $zip, $country, $accredited, $notes, $avatarPath, $existing
    ) {
        $now = date('Y-m-d H:i:s');

        // Update users table
        db()->update('users', [
            'email'      => $email,
            'status'     => $status,
            'updated_at' => $now,
        ], ['id' => $userId]);

        // Build profile update array
        $profileData = [
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'phone'         => $phone,
            'address_line1' => $addressLine1,
            'address_line2' => $addressLine2,
            'city'          => $city,
            'state'         => $state,
            'zip'           => $zip,
            'country'       => $country,
            'accredited'    => (int) $accredited,
            'notes'         => $notes,
            'updated_at'    => $now,
        ];

        // Set accredited_at timestamp when first verified
        if ($accredited === '1') {
            $currentAccredited = db()->fetchColumn(
                "SELECT accredited FROM user_profiles WHERE user_id = ?", [$userId]
            );
            if (!$currentAccredited) {
                $profileData['accredited_at'] = $now;
            }
        } else {
            $profileData['accredited_at'] = null;
        }

        if ($avatarPath) {
            $profileData['avatar_path'] = $avatarPath;
        }

        db()->update('user_profiles', $profileData, ['user_id' => $userId]);

        // Audit
        Auth::audit(Auth::id(), 'update_investor', 'user', $userId, [
            'changed_email' => $email !== $existing['email'],
            'new_status'    => $status,
        ]);
    });

    flash('Investor profile updated successfully.', 'success');
    redirect("/admin/users/view.php?id={$userId}");

} catch (\Throwable $e) {
    error_log('update-investor error: ' . $e->getMessage());
    flash('Something went wrong. Please try again.', 'error');
    $_SESSION['form_data'] = $_POST;
    redirect("/admin/users/edit.php?id={$userId}");
}