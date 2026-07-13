<?php
// ============================================================
// RISE CAPITAL GROUP — Investor Sidebar Partial
// ============================================================

use Rise\Core\Auth;

$currentUser    = Auth::user();
$currentPath    = currentPath();
$active         = fn(string $path): string => str_contains($currentPath, $path) ? 'active' : '';

$unreadMessages = db()->fetchColumn(
    "SELECT SUM(unread_investor) FROM message_threads WHERE investor_id = ? AND status = 'open'",
    [Auth::id()]
) ?? 0;

$unreadNotifs = db()->fetchColumn(
    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
    [Auth::id()]
) ?? 0;

// Wallet balance
$balance = db()->fetchColumn(
    "SELECT balance FROM wallet_balances WHERE user_id = ?",
    [Auth::id()]
) ?? 0;
?>

<aside class="sidebar">

    <!-- Logo -->
    <a href="<?= APP_URL ?>/investor/dashboard.php" class="sidebar-logo">
        <div class="logo-mark">R</div>
        <div class="logo-text-wrap">
            <div class="logo-name">RISE Capital</div>
            <div class="logo-sub">Investor Portal</div>
        </div>
    </a>

    <!-- Balance pill -->
    <div style="padding:12px 14px;border-bottom:1px solid var(--border);">
        <div style="background:var(--gold-dim);border:1px solid var(--gold-border);border-radius:8px;
                    padding:10px 14px;">
            <div style="font-size:10px;color:var(--gold);font-weight:700;letter-spacing:1px;
                        text-transform:uppercase;margin-bottom:4px;">Wallet Balance</div>
            <div style="font-size:20px;font-weight:800;color:var(--gold);">
                <?= formatMoney((float)$balance) ?>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="sidebar-section-label">Portfolio</div>
    <ul class="sidebar-nav">

        <li>
            <a href="<?= APP_URL ?>/investor/dashboard.php" class="<?= $active('dashboard') ?>">
                <span class="nav-icon">⬛</span>
                Overview
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/investor/projects.php" class="<?= $active('/projects') ?>">
                <span class="nav-icon">📍</span>
                Projects
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/investor/wallet.php" class="<?= $active('wallet') ?>">
                <span class="nav-icon">💳</span>
                Wallet
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/investor/transactions.php" class="<?= $active('transactions') ?>">
                <span class="nav-icon">📋</span>
                Transactions
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/investor/distributions.php" class="<?= $active('distributions') ?>">
                <span class="nav-icon">📤</span>
                Distributions
            </a>
        </li>

    </ul>

    <div class="sidebar-section-label">Resources</div>
    <ul class="sidebar-nav">

        <li>
            <a href="<?= APP_URL ?>/investor/documents.php" class="<?= $active('documents') ?>">
                <span class="nav-icon">📁</span>
                Documents
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/investor/news.php" class="<?= $active('news') ?>">
                <span class="nav-icon">📰</span>
                News
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/investor/updates.php" class="<?= $active('updates') ?>">
                <span class="nav-icon">📈</span>
                Market Updates
            </a>
        </li>

        <li>
            <a href="<?= APP_URL ?>/investor/media.php" class="<?= $active('media') ?>">
                <span class="nav-icon">🖼</span>
                Media
            </a>
        </li>

    </ul>

    <div class="sidebar-section-label">Communication</div>
    <ul class="sidebar-nav">

        <li>
            <a href="<?= APP_URL ?>/investor/messages.php" class="<?= $active('messages') ?>">
                <span class="nav-icon">💬</span>
                Messages
                <?php if ($unreadMessages > 0): ?>
                <span class="nav-badge"><?= (int)$unreadMessages ?></span>
                <?php endif; ?>
            </a>
        </li>

    </ul>

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="<?= APP_URL ?>/investor/profile.php" class="sidebar-user">
            <div class="user-avatar">
                <?php if (!empty($currentUser['avatar_path'])): ?>
                    <img src="<?= e($currentUser['avatar_path']) ?>" alt=""/>
                <?php else: ?>
                    <?= strtoupper(substr($currentUser['first_name'] ?? 'I', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></div>
                <div class="user-role">
                    <?= $currentUser['accredited'] ? '✓ Accredited Investor' : 'Investor' ?>
                </div>
            </div>
        </a>

        <form method="POST" action="<?= APP_URL ?>/logout.php" style="margin-top:4px;">
            <?= csrfField() ?>
            <button type="submit" class="btn-secondary"
                    style="width:100%;justify-content:center;font-size:12px;padding:8px;">
                Sign Out
            </button>
        </form>
    </div>

</aside>