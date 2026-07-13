<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Save Distribution
// POST only. Called by admin/distributions/create.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Distribution;

if (!isPost()) redirect('/admin/distributions/index.php');
Auth::requireAdmin();
verifyCsrf();

$projectId        = (int) post('project_id');
$totalAmount      = post('total_amount');
$distributionDate = trim(post('distribution_date'));
$description      = trim(post('description'));

// ── Validate ──────────────────────────────────────────────
$errors = [];

if (!$projectId)                      $errors[] = 'Please select a project.';
if (!isPositiveNumber($totalAmount))  $errors[] = 'Total amount must be a positive number.';
if (empty($distributionDate))         $errors[] = 'Distribution date is required.';

// Verify project exists
if ($projectId && empty($errors)) {
    $project = db()->fetchOne(
        "SELECT id, title FROM projects WHERE id = ? LIMIT 1",
        [$projectId]
    );
    if (!$project) $errors[] = 'Selected project not found.';
}

if (!empty($errors)) {
    $_SESSION['form_data'] = $_POST;
    flash(implode(' ', $errors), 'error');
    redirect('/admin/distributions/create.php');
}

// ── Create distribution ───────────────────────────────────
try {
    $distId = Distribution::createWithAutoSplit([
        'project_id'        => $projectId,
        'distribution_date' => $distributionDate,
        'total_amount'      => (float) $totalAmount,
        'description'       => $description,
        'created_by'        => Auth::id(),
    ]);

    Auth::audit(Auth::id(), 'create_distribution', 'distribution', $distId, [
        'project_id'   => $projectId,
        'total_amount' => $totalAmount,
    ]);

    flash(
        "Distribution of " . formatMoney((float)$totalAmount) .
        " for {$project['title']} recorded and investors notified.",
        'success'
    );

    redirect("/admin/distributions/index.php?view={$distId}");

} catch (\RuntimeException $e) {
    flash($e->getMessage(), 'error');
    $_SESSION['form_data'] = $_POST;
    redirect('/admin/distributions/create.php');

} catch (\Throwable $e) {
    error_log('save-distribution error: ' . $e->getMessage());
    flash('Something went wrong. Please try again.', 'error');
    $_SESSION['form_data'] = $_POST;
    redirect('/admin/distributions/create.php');
}