<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Investor Update Profile
// POST only. Called by investor/profile.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Uploader;

if (!isPost()) redirect('/investor/profile.php');
Auth::requireInvestor();
verifyCsrf();

$userId = Auth::id();

// Collect fields
$firstName   = trim(post('first_name'));
$lastName    = trim(post('last_name'));
$phone       = trim(post('phone'));
$addressLine1= trim(post('address_line1'));
$addressLine2= trim(post('address_line2'));
$city        = trim(post('city'));
$state       = trim(post('state'));
$zip         = trim(post('zip'));
$country     = post('country', 'US');

// Password change fields
$currentPassword    = post('current_password');
$newPassword        = post('new_password');
$newPasswordConfirm = post('new_password_confirm');

$errors = [];

if (empty($firstName)) $errors[] = 'First name is required.';
if (empty($lastName))  $errors[] = 'Last name is required.';

// Password change validation
$changePassword = !empty($currentPassword) || !empty($newPassword);

if ($changePassword) {
    if (empty($currentPassword)) {
        $errors[] = 'Current password is required to set a new password.';
    } else {
        // Verify current password
        $hash = db()->fetchColumn(
            "SELECT password_hash FROM users WHERE id = ? LIMIT 1", [$userId]
        );
        if (!password_verify($currentPassword, $hash)) {
            $errors[] = 'Current password is incorrect.';
        }
    }

    if (empty($newPassword)) {
        $errors[] = 'New password cannot be empty.';
    } else {
        $pwErrors = Auth::validatePassword($newPassword);
        $errors   = array_merge($errors, $pwErrors);
    }

    if ($newPassword !== $newPasswordConfirm) {
        $errors[] = 'New passwords do not match.';
    }
}

if (!empty($errors)) {
    $_SESSION['form_data'] = $_POST;
    flash(implode(' ', $errors), 'error');
    redirect('/investor/profile.php');
}

// Handle avatar upload
$avatarPath = null;
if (!empty($_FILES['avatar']['name'])) {
    $uploader = new Uploader();
    $result   = $uploader->image($_FILES['avatar'], 'avatars');
    if ($result['success']) {
        $avatarPath = $result['path'];
    } else {
        flash($result['error'], 'error');
        redirect('/investor/profile.php');
    }
}

try {
    $now = date('Y-m-d H:i:s');

    // Update profile
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
        'updated_at'    => $now,
    ];

    if ($avatarPath) $profileData['avatar_path'] = $avatarPath;

    db()->update('user_profiles', $profileData, ['user_id' => $userId]);

    // Update password if changing
    if ($changePassword && !empty($newPassword)) {
        db()->update('users', [
            'password_hash' => Auth::hashPassword($newPassword),
            'updated_at'    => $now,
        ], ['id' => $userId]);
    }

    // Update session name
    $_SESSION['auth_user_name'] = $firstName . ' ' . $lastName;

    Auth::audit($userId, 'update_profile', 'user', $userId);

    flash('Profile updated successfully.', 'success');
    redirect('/investor/profile.php');

} catch (\Throwable $e) {
    error_log('update-profile error: ' . $e->getMessage());
    flash('Something went wrong. Please try again.', 'error');
    redirect('/investor/profile.php');
}