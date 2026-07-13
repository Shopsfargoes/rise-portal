<?php
// ============================================================
// RISE CAPITAL GROUP — AJAX: Get Project Investors
// GET only. Used by distributions/create.php preview panel.
// Returns JSON list of investors with investment amounts.
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;

Auth::requireAdmin();

$projectId = (int) get('project_id');

if (!$projectId) {
    jsonResponse([], 200);
}

$investors = db()->fetchAll(
    "SELECT i.id, i.amount, i.user_id,
            up.first_name, up.last_name, u.email
     FROM investments i
     JOIN users u ON u.id = i.user_id
     LEFT JOIN user_profiles up ON up.user_id = i.user_id
     WHERE i.project_id = ? AND i.status = 'active'
     ORDER BY i.amount DESC",
    [$projectId]
);

jsonResponse($investors);