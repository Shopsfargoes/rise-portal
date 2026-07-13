<?php
// ============================================================
// RISE CAPITAL GROUP — Login Action Handler
// POST only. Called by public/login.php form submission.
// login.php handles this inline — this file is a standalone
// fallback for direct POST submissions / AJAX if needed.
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;

// Only accept POST requests
if (!isPost()) {
    redirect('/login.php');
}

verifyCsrf();

$email    = post('email');
$password = post('password');

// Basic presence check
if (empty($email) || empty($password)) {
    flash('Please enter your email and password.', 'error');
    redirect('/login.php');
}

// Attempt login
$result = Auth::attempt($email, $password);

if ($result === true) {
    $intended = $_SESSION['intended_url'] ?? '';
    unset($_SESSION['intended_url']);

    if ($intended) {
        // Redirect to the page they originally tried to visit
        header('Location: ' . $intended);
        exit;
    }

    // Default redirect by role
    if (Auth::isAdmin()) {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/investor/dashboard.php');
    }
}

// Failed — store error and redirect back
flash($result, 'error');
redirect('/login.php');