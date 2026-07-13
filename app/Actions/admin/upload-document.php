<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Upload Document
// POST only. Called by admin/documents/upload.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Uploader;
use Rise\Models\Document;

if (!isPost()) redirect('/admin/documents/index.php');
Auth::requireAdmin();
verifyCsrf();

// ── Validate fields ───────────────────────────────────────
$title      = trim(post('title'));
$categoryId = (int) post('category_id') ?: null;
$projectId  = (int) post('project_id')  ?: null;
$visibility = post('visibility', 'investor');

$errors = [];

if (empty($title))       $errors[] = 'Document title is required.';
if (empty($_FILES['document']['name'])) $errors[] = 'Please select a file to upload.';
if (!in_array($visibility, ['admin','investor','public'])) $errors[] = 'Invalid visibility setting.';

if (!empty($errors)) {
    $_SESSION['form_data'] = $_POST;
    flash(implode(' ', $errors), 'error');
    redirect('/admin/documents/upload.php');
}

// ── Upload file ───────────────────────────────────────────
$uploader = new Uploader();
$result   = $uploader->document($_FILES['document'], 'documents');

if (!$result['success']) {
    $_SESSION['form_data'] = $_POST;
    flash($result['error'], 'error');
    redirect('/admin/documents/upload.php');
}

// ── Save to database ──────────────────────────────────────
try {
    $docId = Document::create([
        'project_id'  => $projectId,
        'category_id' => $categoryId,
        'uploaded_by' => Auth::id(),
        'title'       => $title,
        'file_name'   => $result['filename'],
        'file_path'   => $result['path'],
        'file_size'   => $result['size'],
        'mime_type'   => $result['mime_type'],
        'visibility'  => $visibility,
    ]);

    Auth::audit(Auth::id(), 'upload_document', 'document', (int)$docId, [
        'title'      => $title,
        'visibility' => $visibility,
        'project_id' => $projectId,
    ]);

    flash("Document \"{$title}\" uploaded successfully.", 'success');
    redirect('/admin/documents/index.php');

} catch (\Throwable $e) {
    error_log('upload-document error: ' . $e->getMessage());

    // Clean up the uploaded file if DB insert failed
    $filePath = STORAGE_PATH . '/' . $result['path'];
    if (file_exists($filePath)) unlink($filePath);

    flash('Something went wrong saving the document. Please try again.', 'error');
    $_SESSION['form_data'] = $_POST;
    redirect('/admin/documents/upload.php');
}