<?php
// ============================================================
// RISE CAPITAL GROUP — Secure Document Download
// Auth-gated. Streams the file, never exposes the real path.
// ============================================================
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Document;

// Must be logged in
Auth::requireLogin();

$uuid = trim(get('uuid'));
if (!$uuid) {
    http_response_code(400);
    die('Invalid request.');
}

// Fetch document record
$doc = Document::findByUuid($uuid);

if (!$doc) {
    http_response_code(404);
    die('Document not found.');
}

// ── Access control ────────────────────────────────────────
$canAccess = false;

if (Auth::isAdmin()) {
    // Admins can download everything
    $canAccess = true;
} elseif ($doc['visibility'] === 'public') {
    $canAccess = true;
} elseif ($doc['visibility'] === 'investor' && Auth::isInvestor()) {
    $canAccess = true;
}
// 'admin' visibility — only admins (already covered above)

if (!$canAccess) {
    http_response_code(403);
    die('You do not have permission to download this document.');
}

// ── Resolve file path ─────────────────────────────────────
// file_path stored as relative: 'uploads/documents/filename.pdf'
$filePath = STORAGE_PATH . '/' . $doc['file_path'];

if (!file_exists($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    die('File not found on server. Please contact support.');
}

// ── Log the download ──────────────────────────────────────
Document::incrementDownload($doc['id']);

Auth::audit(Auth::id(), 'download_document', 'document', $doc['id'], [
    'title'    => $doc['title'],
    'uuid'     => $uuid,
]);

// ── Stream the file ───────────────────────────────────────
$fileSize = filesize($filePath);
$mimeType = $doc['mime_type'] ?: 'application/octet-stream';

// Safe filename for Content-Disposition
$safeTitle    = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $doc['title']);
$ext          = pathinfo($doc['file_name'], PATHINFO_EXTENSION);
$downloadName = trim($safeTitle) . '.' . $ext;

// Clear any output buffers
while (ob_get_level()) ob_end_clean();

header('Content-Type: '        . $mimeType);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: '      . $fileSize);
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// Stream in chunks to handle large files
$handle = fopen($filePath, 'rb');
if ($handle) {
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
}
exit;