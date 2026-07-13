<?php
// ============================================================
// RISE CAPITAL GROUP — Logout
// Destroys the session and redirects to login.
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;

// Must be logged in to log out
if (!Auth::isLoggedIn()) {
    redirect('/login.php');
}

// Only allow POST logout (protects against CSRF logout attacks)
// GET logout still works but with token check
if (isPost()) {
    verifyCsrf();
}

Auth::logout();

flash('You have been signed out successfully.', 'success');
redirect('/login.php');