<?php
// ============================================================
// RISE CAPITAL GROUP — Flash Messages Partial
// Auto-dismisses after 5 seconds
// ============================================================

$flashes = getFlash();
if (empty($flashes)) return;

$icons = [
    'success' => '✓',
    'error'   => '⚠',
    'warning' => '⚠',
    'info'    => 'ℹ',
];
?>

<div class="flash-container" id="flashContainer">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> flash-item">
            <span><?= $icons[$flash['type']] ?? 'ℹ' ?></span>
            <span><?= e($flash['message']) ?></span>
            <button class="flash-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endforeach; ?>
</div>

<style>
.flash-container {
    padding: 0 32px;
    margin-top: 16px;
}

.flash-item {
    justify-content: space-between;
    animation: slideIn 0.3s ease;
}

.flash-close {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    opacity: 0.6;
    font-size: 13px;
    margin-left: auto;
    padding: 0 0 0 12px;
    flex-shrink: 0;
}

.flash-close:hover { opacity: 1; }

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// Auto-dismiss after 5 seconds
setTimeout(function() {
    const container = document.getElementById('flashContainer');
    if (container) {
        container.style.transition = 'opacity 0.4s';
        container.style.opacity = '0';
        setTimeout(() => container.remove(), 400);
    }
}, 5000);
</script>