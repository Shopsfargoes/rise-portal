<?php
// ============================================================
// RISE CAPITAL GROUP — Entry Point
// Redirects logged-in users to their dashboard,
// guests to the login page.
// ============================================================
require_once dirname(__DIR__, 1) . '/app/bootstrap.php';

use Rise\Core\Auth;

if (!Auth::isLoggedIn()) {
    redirect('/login.php');
}

if (Auth::isAdmin()) {
    redirect('/admin/dashboard.php');
}

redirect('/investor/dashboard.php');