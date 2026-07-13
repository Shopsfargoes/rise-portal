<?php
// ============================================================
// RISE CAPITAL GROUP — Topbar Partial
// Market ticker + notification bell + profile quick link
// ============================================================

use Rise\Core\Auth;

// Unread notifications count
$unreadNotifs = db()->fetchColumn(
    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
    [Auth::id()]
) ?? 0;
?>

<div class="topbar">

    <!-- Market Ticker -->
    <div class="topbar-ticker">
        <div class="ticker-scroll" id="marketTicker">
            <span class="ticker-item">
                <span class="name">WTI Crude</span>
                <span class="down">$89.42 ▼ –4.45%</span>
            </span>
            <span class="ticker-item">
                <span class="name">Nat Gas</span>
                <span class="up">$11.18 ▲ +2.47%</span>
            </span>
            <span class="ticker-item">
                <span class="name">Brent Crude</span>
                <span class="down">$92.94 ▼ –3.48%</span>
            </span>
            <span class="ticker-item">
                <span class="name">Gasoline</span>
                <span class="down">$106.40 ▼ –1.85%</span>
            </span>
            <span class="ticker-item">
                <span class="name">Refiners</span>
                <span class="down">$47.50 ▼ –0.81%</span>
            </span>
            <span class="ticker-item">
                <span class="name">Exxon</span>
                <span class="up">$147.90 ▲ +0.62%</span>
            </span>
            <!-- Duplicate for seamless loop -->
            <span class="ticker-item">
                <span class="name">WTI Crude</span>
                <span class="down">$89.42 ▼ –4.45%</span>
            </span>
            <span class="ticker-item">
                <span class="name">Nat Gas</span>
                <span class="up">$11.18 ▲ +2.47%</span>
            </span>
            <span class="ticker-item">
                <span class="name">Brent Crude</span>
                <span class="down">$92.94 ▼ –3.48%</span>
            </span>
            <span class="ticker-item">
                <span class="name">Gasoline</span>
                <span class="down">$106.40 ▼ –1.85%</span>
            </span>
            <span class="ticker-item">
                <span class="name">Refiners</span>
                <span class="down">$47.50 ▼ –0.81%</span>
            </span>
            <span class="ticker-item">
                <span class="name">Exxon</span>
                <span class="up">$147.90 ▲ +0.62%</span>
            </span>
        </div>
    </div>

    <!-- Actions -->
    <div class="topbar-actions">

        <!-- Notification Bell (dropdown) -->
        <?php require_once BASE_PATH . '/views/partials/notifications-bell.php'; ?>

        <!-- Messages -->
        <a href="<?= APP_URL ?>/<?= Auth::isAdmin() ? 'admin/messages/index.php' : 'investor/messages.php' ?>"
           class="topbar-btn" title="Messages">
            💬
        </a>

        <!-- Admin / Investor toggle (admin only) -->
        <?php if (Auth::isAdmin()): ?>
        <div style="display:flex; gap:4px;">
            <a href="<?= APP_URL ?>/admin/dashboard.php"
               class="topbar-btn btn-sm <?= str_contains(currentPath(), '/admin/') ? 'active-topbar' : '' ?>"
               style="font-size:11px; font-weight:700; padding:0 10px; width:auto; color:var(--gold); border-color:var(--gold);">
                ADMIN
            </a>
            <a href="<?= APP_URL ?>/investor/dashboard.php"
               class="topbar-btn btn-sm"
               style="font-size:11px; font-weight:700; padding:0 10px; width:auto;">
                INVESTOR
            </a>
        </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once BASE_PATH . '/views/partials/flash-messages.php'; ?>