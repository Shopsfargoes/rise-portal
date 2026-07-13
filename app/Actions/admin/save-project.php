<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Save Project (Create + Update)
// POST only. Called by admin/projects/create.php and edit.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Uploader;
use Rise\Models\Project;

if (!isPost()) redirect('/admin/projects/index.php');
Auth::requireAdmin();
verifyCsrf();

$mode      = post('mode', 'create');    // 'create' | 'update'
$projectId = (int) post('project_id');  // only for update

// ── Validate ──────────────────────────────────────────────
$title       = trim(post('title'));
$categoryId  = (int) post('category_id') ?: null;
$status      = post('status', 'open');
$location    = trim(post('location'));
$description = trim(post('description'));
$projectType = trim(post('project_type'));
$prodType    = trim(post('production_type'));
$targetDepth = trim(post('target_depth'));
$projectCost = post('project_cost', 0);
$sortOrder   = (int) post('sort_order', 0);
$isFeatured  = (int) post('is_featured', 0);
$mapLat      = trim(post('map_lat'));
$mapLng      = trim(post('map_lng'));
$tagsRaw     = trim(post('tags'));

$errors = [];

if (empty($title))                            $errors[] = 'Project title is required.';
if (!isPositiveNumber($projectCost))          $errors[] = 'Project cost must be a positive number.';
if (!in_array($status, ['open','closed','drilled','producing','abandoned'])) {
    $errors[] = 'Invalid status.';
}
if ($mapLat && !is_numeric($mapLat))          $errors[] = 'Map latitude must be a number.';
if ($mapLng && !is_numeric($mapLng))          $errors[] = 'Map longitude must be a number.';
if ($mode === 'update' && !$projectId)        $errors[] = 'Invalid project ID.';

if (!empty($errors)) {
    $_SESSION['form_data'] = $_POST;
    flash(implode(' ', $errors), 'error');
    $redirect = $mode === 'update'
        ? "/admin/projects/edit.php?id={$projectId}"
        : '/admin/projects/create.php';
    redirect($redirect);
}

// ── Handle cover image upload ──────────────────────────────
$coverImage = null;

if (!empty($_FILES['cover_image']['name'])) {
    $uploader = new Uploader();
    $result   = $uploader->image($_FILES['cover_image'], 'projects');

    if ($result['success']) {
        $coverImage = $result['path'];
    } else {
        $_SESSION['form_data'] = $_POST;
        flash($result['error'], 'error');
        redirect($mode === 'update'
            ? "/admin/projects/edit.php?id={$projectId}"
            : '/admin/projects/create.php');
    }
}

// ── Parse tags ────────────────────────────────────────────
$tags = $tagsRaw
    ? array_filter(array_map('trim', explode(',', $tagsRaw)))
    : [];

// ── Save ──────────────────────────────────────────────────
try {
    $projectData = [
        'category_id'     => $categoryId,
        'title'           => $title,
        'location'        => $location,
        'status'          => $status,
        'project_type'    => $projectType,
        'production_type' => $prodType,
        'target_depth'    => $targetDepth,
        'project_cost'    => (float) $projectCost,
        'description'     => $description,
        'map_lat'         => $mapLat ?: null,
        'map_lng'         => $mapLng ?: null,
        'is_featured'     => $isFeatured,
        'sort_order'      => $sortOrder,
    ];

    if ($coverImage) {
        $projectData['cover_image'] = $coverImage;
    }

    db()->transaction(function() use ($mode, $projectId, $projectData, $tags, &$savedId) {
        if ($mode === 'create') {
            $projectData['created_by'] = \Rise\Core\Auth::id();
            $savedId = (int) Project::create($projectData);
        } else {
            Project::update($projectId, $projectData);
            $savedId = $projectId;
        }

        // Sync tags
        Project::syncTags($savedId, $tags);

        Auth::audit(
            Auth::id(),
            $mode === 'create' ? 'create_project' : 'update_project',
            'project',
            $savedId,
            ['title' => $projectData['title']]
        );
    });

    $label = $mode === 'create' ? 'created' : 'updated';
    flash("Project \"{$title}\" {$label} successfully.", 'success');

    // After create, go to edit page so admin can add timeline
    redirect("/admin/projects/edit.php?id={$savedId}");

} catch (\Throwable $e) {
    error_log('save-project error: ' . $e->getMessage());
    flash('Something went wrong saving the project. Please try again.', 'error');
    $_SESSION['form_data'] = $_POST;
    redirect($mode === 'update'
        ? "/admin/projects/edit.php?id={$projectId}"
        : '/admin/projects/create.php');
}