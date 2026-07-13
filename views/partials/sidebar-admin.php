<?php
// ============================================================
// RISE CAPITAL GROUP — Admin Sidebar Partial
// Included by views/layouts/admin.php
// ============================================================

use Rise\Core\Auth;

$currentUser = Auth::user();

// Unread counts for badges
$pendingWallet   = db()->fetchColumn("SELECT COUNT(*) FROM wallet_transactions WHERE status = 'pending'") ?? 0;
$unreadMessages  = db()->fetchColumn("SELECT SUM(unread_admin) FROM message_threads WHERE status = 'open'") ?? 0;
$currentPath     = currentPath();

// Helper — is this nav item active?
$active = fn(string $path): string => str_contains($currentPath, $path) ? 'active' : '';
?>

<aside class="sidebar">

    <!-- Logo -->
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="sidebar-logo">
        <div class="logo-mark">R</div>
        <div class="logo-text-wrap">
            <div class="logo-name">RISE Capital</div>
            <div class="logo-sub">Admin Portal</div>
        </div>
    </a>

    <!-- Navigation -->
    <div class="sidebar-section-label">Main</div>
    <ul class="sidebar-nav">

        <li>
            <a href="<?= APP_URL ?>/admin/dashboard.php" class="<?= $active('dashboard') ?>">
                <span class="nav-icon">⬛</span>
                Overview
                <?php if ($pendingWallet > 0): ?>
                    <span class="nav-badge"><?= (int)$pendingWallet ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/admin/users/index.php" class="<?= $active('/users/') ?>">
                <span class="nav-icon">👥</span>
                Investors
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/admin/projects/index.php" class="<?= $active('/projects/') ?>">
                <span class="nav-icon">📍</span>
                Projects
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/admin/investments/index.php" class="<?= $active('/investments/') ?>">
                <span class="nav-icon">💼</span>
                Investments
            </a>
        </li>

    </ul>

    <div class="sidebar-section-label">Finance</div>
    <ul class="sidebar-nav">

        <li>
            <a href="<?= APP_URL ?>/admin/wallet/pending.php" class="<?= $active('/wallet/') ?>">
                <span class="nav-icon">💳</span>
                Wallet Requests
                <?php if ($pendingWallet > 0): ?>
                    <span class="nav-badge"><?= (int)$pendingWallet ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/admin/transactions/index.php" class="<?= $active('/transactions/') ?>">
                <span class="nav-icon">📋</span>
                Transactions
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/admin/distributions/index.php" class="<?= $active('/distributions/') ?>">
                <span class="nav-icon">📤</span>
                Distributions
            </a>
        </li>

    </ul>

    <div class="sidebar-section-label">Content</div>
    <ul class="sidebar-nav">

        <li>
            <a href="<?= APP_URL ?>/admin/documents/index.php" class="<?= $active('/documents/') ?>">
                <span class="nav-icon">📁</span>
                Documents
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/admin/posts/index.php" class="<?= $active('/posts/') ?>">
                <span class="nav-icon">📰</span>
                News & Updates
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/admin/media/index.php" class="<?= $active('/media/') ?>">
                <span class="nav-icon">🖼</span>
                Media
            </a>
        </li>

    </ul>

    <div class="sidebar-section-label">Communication</div>
    <ul class="sidebar-nav">

        <li>
            <a href="<?= APP_URL ?>/admin/messages/index.php" class="<?= $active('/messages/') ?>">
                <span class="nav-icon">💬</span>
                Messages
                <?php if ($unreadMessages > 0): ?>
                    <span class="nav-badge"><?= (int)$unreadMessages ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/admin/settings.php" class="<?= $active('settings') ?>">
                <span class="nav-icon">⚙️</span>
                Settings
            </a>
        </li>

    </ul>

    <!-- User footer -->
    <div class="sidebar-footer">
        <a href="<?= APP_URL ?>/admin/settings.php" class="sidebar-user">
            <div class="user-avatar">
                <?php if (!empty($currentUser['avatar_path'])): ?>
                    <img src="<?= e($currentUser['avatar_path']) ?>" alt="Avatar"/>
                <?php else: ?>
                    <?= strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </a>

        <form method="POST" action="<?= APP_URL ?>/logout.php" style="margin-top:4px;">
            <?= csrfField() ?>
            <button type="submit" class="btn-secondary" style="width:100%; justify-content:center; font-size:12px; padding:8px;">
                Sign Out
            </button>
        </form>
    </div>

</aside>