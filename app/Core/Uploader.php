<?php
// ============================================================
// RISE CAPITAL GROUP — Uploader
// Handles all file uploads — images and documents
// ============================================================

namespace Rise\Core;

class Uploader
{
    private int $maxBytes;

    public function __construct()
    {
        $this->maxBytes = MAX_UPLOAD_MB * 1024 * 1024;
    }

    // ── Upload an image (avatar, cover photo) ─────────────

    /**
     * @param array  $file    $_FILES['field']
     * @param string $folder  Sub-folder inside UPLOAD_PATH/  e.g. 'avatars', 'projects'
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public function image(array $file, string $folder = 'images'): array
    {
        return $this->handle($file, $folder, ALLOWED_IMAGE_TYPES, 2 * 1024 * 1024);
    }

    // ── Upload a document (PDF, Word, Excel) ──────────────

    /**
     * Stores documents in STORAGE_PATH (outside public/) for access control.
     * @param array  $file    $_FILES['field']
     * @param string $folder  Sub-folder inside STORAGE_PATH/documents/
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public function document(array $file, string $folder = 'documents'): array
    {
        $dest = STORAGE_PATH . '/uploads/' . $folder;
        return $this->handle($file, $folder, ALLOWED_DOC_TYPES, $this->maxBytes, $dest);
    }

    // ── Core handler ──────────────────────────────────────

    private function handle(
        array  $file,
        string $folder,
        array  $allowedTypes,
        int    $maxBytes,
        string $destBase = null
    ): array {
        // No file provided
        if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'path' => null, 'error' => 'No file provided.'];
        }

        // PHP upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'path' => null, 'error' => $this->uploadErrorMessage($file['error'])];
        }

        // File size check
        if ($file['size'] > $maxBytes) {
            $maxMB = round($maxBytes / 1024 / 1024, 1);
            return ['success' => false, 'path' => null, 'error' => "File is too large. Maximum size is {$maxMB}MB."];
        }

        // MIME type — use finfo, not the browser-supplied type
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedTypes, true)) {
            $allowed = implode(', ', array_map(fn($t) => explode('/', $t)[1], $allowedTypes));
            return ['success' => false, 'path' => null, 'error' => "Invalid file type. Allowed: {$allowed}."];
        }

        // Build destination
        $destBase = $destBase ?? (UPLOAD_PATH . '/' . $folder);

        if (!is_dir($destBase)) {
            mkdir($destBase, 0755, true);
        }

        // Generate safe, unique filename
        $ext      = $this->mimeToExt($mimeType);
        $filename = uniqid('', true) . '_' . time() . '.' . $ext;
        $destPath = $destBase . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'path' => null, 'error' => 'Failed to save file. Check folder permissions.'];
        }

        // Return web-accessible path for images, storage path for docs
        if ($destBase === STORAGE_PATH . '/uploads/' . $folder) {
            // Document — return relative storage path
            $relativePath = 'uploads/' . $folder . '/' . $filename;
        } else {
            // Image — return web URL path
            $relativePath = APP_URL . '/assets/uploads/' . $folder . '/' . $filename;
        }

        return [
            'success'   => true,
            'path'      => $relativePath,
            'filename'  => $filename,
            'mime_type' => $mimeType,
            'size'      => $file['size'],
            'error'     => null,
        ];
    }

    // ── Helpers ───────────────────────────────────────────

    private function mimeToExt(string $mime): string
    {
        $map = [
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
            'image/webp'       => 'webp',
            'image/gif'        => 'gif',
            'application/pdf'  => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];

        return $map[$mime] ?? 'bin';
    }

    private function uploadErrorMessage(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum allowed size.',
            UPLOAD_ERR_PARTIAL  => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'File upload stopped by server extension.',
            default => 'Unknown upload error.',
        };
    }
}