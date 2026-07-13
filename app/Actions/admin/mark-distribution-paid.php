<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Mark Distribution Item(s) Paid
// POST only. Called by admin/distributions/index.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Distribution;

if (!isPost()) redirect('/admin/distributions/index.php');
Auth::requireAdmin();
verifyCsrf();

$itemId         = (int) post('item_id');
$distributionId = (int) post('distribution_id');
$markAll        = (bool) post('mark_all', false);

if (!$distributionId) {
    flash('Invalid distribution.', 'error');
    redirect('/admin/distributions/index.php');
}

try {
    if ($markAll) {
        Distribution::markAllPaid($distributionId);
        Auth::audit(Auth::id(), 'mark_distribution_all_paid', 'distribution', $distributionId);
        flash('All distribution items marked as paid.', 'success');
    } elseif ($itemId) {
        Distribution::markItemPaid($itemId);
        Auth::audit(Auth::id(), 'mark_distribution_item_paid', 'distribution', $distributionId, [
            'item_id' => $itemId,
        ]);
        flash('Payment marked as paid.', 'success');
    }
} catch (\Throwable $e) {
    error_log('mark-distribution-paid error: ' . $e->getMessage());
    flash('Something went wrong. Please try again.', 'error');
}

redirect("/admin/distributions/index.php?view={$distributionId}");