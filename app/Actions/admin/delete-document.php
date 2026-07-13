<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Delete Document
// POST only. Called by admin/documents/index.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Document;

if (!isPost()) redirect('/admin/documents/index.php');
Auth::requireAdmin();
verifyCsrf();

$docId = (int) post('document_id');
if (!$docId) redirect('/admin/documents/index.php');

$doc = Document::findById($docId);

if (!$doc) {
    flash('Document not found.', 'error');
    redirect('/admin/documents/index.php');
}

$title = $doc['title'];

if (Document::delete($docId)) {
    Auth::audit(Auth::id(), 'delete_document', 'document', $docId, ['title' => $title]);
    flash("Document \"{$title}\" deleted successfully.", 'success');
} else {
    flash('Failed to delete document. Please try again.', 'error');
}

redirect('/admin/documents/index.php');