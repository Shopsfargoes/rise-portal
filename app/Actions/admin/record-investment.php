<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Record Investment
// POST only. Called by admin/investments/create.php
// ============================================================
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Investment;

if (!isPost()) redirect('/admin/investments/index.php');
Auth::requireAdmin();
verifyCsrf();

// ── Collect & validate ────────────────────────────────────
$userId     = (int) post('user_id');
$projectId  = (int) post('project_id');
$amount     = post('amount');
$investedAt = trim(post('invested_at'));
$status     = post('status', 'active');
$notes      = trim(post('notes'));

$errors = [];

if (!$userId)                          $errors[] = 'Please select an investor.';
if (!$projectId)                       $errors[] = 'Please select a project.';
if (!isPositiveNumber($amount))        $errors[] = 'Amount must be a positive number.';
if (empty($investedAt))                $errors[] = 'Investment date is required.';
if (!in_array($status, ['active','pending','exited'])) $errors[] = 'Invalid status.';

// Verify investor exists and is active
if ($userId && empty($errors)) {
    $investor = db()->fetchOne(
        "SELECT u.id, u.status, p.first_name, p.last_name
         FROM users u
         LEFT JOIN user_profiles p ON p.user_id = u.id
         WHERE u.id = ? AND u.role = 'investor' LIMIT 1",
        [$userId]
    );

    if (!$investor) {
        $errors[] = 'Selected investor not found.';
    } elseif ($investor['status'] !== 'active') {
        $errors[] = 'Selected investor account is not active.';
    }
}

// Verify project exists
if ($projectId && empty($errors)) {
    $project = db()->fetchOne(
        "SELECT id, title, project_cost FROM projects WHERE id = ? LIMIT 1",
        [$projectId]
    );

    if (!$project) $errors[] = 'Selected project not found.';
}

if (!empty($errors)) {
    $_SESSION['form_data'] = $_POST;
    flash(implode(' ', $errors), 'error');
    redirect('/admin/investments/create.php');
}

// ── Save ──────────────────────────────────────────────────
try {
    $investmentId = db()->transaction(function() use (
        $userId, $projectId, $amount, $investedAt, $status, $notes, $investor, $project
    ) {
        $id = Investment::create([
            'user_id'     => $userId,
            'project_id'  => $projectId,
            'amount'      => (float) $amount,
            'invested_at' => $investedAt . ' 00:00:00',
            'status'      => $status,
            'notes'       => $notes,
            'created_by'  => Auth::id(),
        ]);

        // Create notification for the investor
        db()->insert('notifications', [
            'user_id'      => $userId,
            'type'         => 'investment_recorded',
            'title'        => 'New Investment Recorded',
            'message'      => "Your investment of " . formatMoney((float)$amount) .
                              " in {$project['title']} has been recorded.",
            'related_type' => 'investment',
            'related_id'   => (int) $id,
            'is_read'      => 0,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        Auth::audit(Auth::id(), 'record_investment', 'investment', (int)$id, [
            'investor_id' => $userId,
            'project_id'  => $projectId,
            'amount'      => $amount,
        ]);

        return $id;
    });

    $investorName = $investor['first_name'] . ' ' . $investor['last_name'];
    flash(
        "Investment of " . formatMoney((float)$amount) .
        " recorded for {$investorName} in {$project['title']}.",
        'success'
    );

    // Go to the investor's profile so admin can see full picture
    redirect("/admin/users/view.php?id={$userId}&tab=investments");

} catch (\Throwable $e) {
    error_log('record-investment error: ' . $e->getMessage());
    flash('Something went wrong recording the investment. Please try again.', 'error');
    $_SESSION['form_data'] = $_POST;
    redirect('/admin/investments/create.php');
}