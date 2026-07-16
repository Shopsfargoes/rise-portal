<?php
// ============================================================
// RISE CAPITAL GROUP - Action: Save Post (Create / Update / Delete)
// POST only. Called by admin/posts/create.php and edit.php
// ============================================================
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Uploader;
use Rise\Models\Post;

if (!isPost()) redirect('/admin/posts/index.php');
Auth::requireAdmin();
verifyCsrf();

$mode   = post('mode', 'create');
$postId = (int) post('post_id');

// Handle delete
if ($mode === 'delete') {
    if ($postId) {
        $p = Post::findById($postId);
        Post::delete($postId);
        Auth::audit(Auth::id(), 'delete_post', 'post', $postId, ['title' => $p['title'] ?? '']);
        flash('Post deleted.', 'success');
    }
    redirect('/admin/posts/index.php');
}

// Validate
$title     = trim(post('title'));
$body      = trim(post('body'));
$type      = post('type', 'news');
$status    = post('status', 'draft');
$projectId = (int) post('project_id') ?: null;

$errors = [];
if (empty($title))                              $errors[] = 'Title is required.';
if (empty($body))                               $errors[] = 'Body content is required.';
if (!in_array($type, ['news', 'update']))       $errors[] = 'Invalid post type.';
if (!in_array($status, ['draft','published']))  $errors[] = 'Invalid status.';
if ($mode === 'update' && !$postId)             $errors[] = 'Invalid post ID.';

if (!empty($errors)) {
    $_SESSION['form_data'] = $_POST;
    flash(implode(' ', $errors), 'error');
    redirect($mode === 'update'
        ? "/admin/posts/edit.php?id={$postId}"
        : '/admin/posts/create.php');
}

// Handle cover image upload
$coverImage = null;
if (!empty($_FILES['cover_image']['name'])) {
    $uploader = new Uploader();
    $result   = $uploader->image($_FILES['cover_image'], 'posts');
    if ($result['success']) {
        $coverImage = $result['path'];
    } else {
        flash($result['error'], 'error');
        $_SESSION['form_data'] = $_POST;
        redirect($mode === 'update'
            ? "/admin/posts/edit.php?id={$postId}"
            : '/admin/posts/create.php');
    }
}

// Save
try {
    $postData = [
        'project_id' => $projectId,
        'type'       => $type,
        'title'      => $title,
        'body'       => $body,
        'status'     => $status,
    ];

    if ($coverImage) {
        $postData['cover_image'] = $coverImage;
    }

    if ($mode === 'create') {
        $postData['author_id'] = Auth::id();
        $savedId = (int) Post::create($postData);
        Auth::audit(Auth::id(), 'create_post', 'post', $savedId, ['title' => $title]);
        flash("Post \"{$title}\" created successfully.", 'success');
    } else {
        Post::update($postId, $postData);
        $savedId = $postId;
        Auth::audit(Auth::id(), 'update_post', 'post', $savedId, ['title' => $title]);
        flash("Post \"{$title}\" updated successfully.", 'success');
    }

    redirect("/admin/posts/edit.php?id={$savedId}");

} catch (\Throwable $e) {
    error_log('save-post error: ' . $e->getMessage());
    flash('Something went wrong. Please try again.', 'error');
    $_SESSION['form_data'] = $_POST;
    redirect($mode === 'update'
        ? "/admin/posts/edit.php?id={$postId}"
        : '/admin/posts/create.php');
}