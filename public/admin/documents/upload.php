<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Upload Document
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Document;
use Rise\Models\Project;

Auth::requireAdmin();

$categories = Document::getCategories();
$projects   = Project::findAll([], 100, 0);

// Pre-select project if coming from a project page
$preProjectId = (int) get('project_id', 0);

$data  = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Upload Document — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .form-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px; }
        .form-card-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border); }
        .form-card-icon  { font-size:20px;margin-top:2px; }
        .form-card-title { font-size:15px;font-weight:700; }
        .form-card-sub   { font-size:12px;color:var(--muted);margin-top:2px; }

        /* Drop zone */
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 48px 24px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
        }

        .drop-zone:hover,
        .drop-zone.dragover {
            border-color: var(--gold);
            background: var(--gold-dim);
        }

        .drop-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .drop-icon { font-size: 40px; margin-bottom: 12px; }
        .drop-text { font-size: 15px; font-weight: 600; margin-bottom: 6px; }
        .drop-sub  { font-size: 12px; color: var(--muted); }

        /* File selected state */
        .file-selected {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            display: none;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }

        .file-selected.show { display: flex; }
        .file-info { flex: 1; }
        .file-info .name { font-size: 13px; font-weight: 600; }
        .file-info .size { font-size: 11px; color: var(--muted); margin-top: 2px; }

        .file-remove {
            background: none; border: none;
            color: var(--red); cursor: pointer;
            font-size: 18px; padding: 0;
        }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <a href="<?= APP_URL ?>/admin/documents/index.php" class="back-link">← Back to Documents</a>
                <h1 class="page-title">Upload Document</h1>
                <p class="page-sub">Add a PDF, Word or Excel file to the document library</p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="<?= APP_URL ?>/app/Actions/admin/upload-document.php"
              enctype="multipart/form-data" style="max-width:680px;">
            <?= csrfField() ?>

            <!-- File drop zone -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">📎</span>
                    <div>
                        <div class="form-card-title">Select File</div>
                        <div class="form-card-sub">PDF, Word (.docx) or Excel (.xlsx) — Max <?= MAX_UPLOAD_MB ?>MB</div>
                    </div>
                </div>

                <div class="drop-zone" id="dropZone">
                    <input type="file" name="document" id="fileInput"
                           accept=".pdf,.doc,.docx,.xls,.xlsx"
                           onchange="handleFileSelect(this)" required/>
                    <div class="drop-icon">📄</div>
                    <div class="drop-text">Drop your file here or click to browse</div>
                    <div class="drop-sub">PDF, DOC, DOCX, XLS, XLSX supported</div>
                </div>

                <!-- Selected file preview -->
                <div class="file-selected" id="fileSelected">
                    <span style="font-size:28px;" id="fileIcon">📄</span>
                    <div class="file-info">
                        <div class="name" id="fileName">—</div>
                        <div class="size" id="fileSize">—</div>
                    </div>
                    <button type="button" class="file-remove" onclick="clearFile()" title="Remove">✕</button>
                </div>
            </div>

            <!-- Document details -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">📋</span>
                    <div>
                        <div class="form-card-title">Document Details</div>
                        <div class="form-card-sub">How this document appears in the library</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Document Title <span class="required">*</span></label>
                    <input type="text" name="title" required
                           placeholder="e.g. Doty Jackson — Private Placement Memorandum"
                           value="<?= e($data['title'] ?? '') ?>"/>
                    <span class="form-hint">A clear, descriptive title investors will see.</span>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">— Select Category —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= ($data['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Visibility <span class="required">*</span></label>
                        <select name="visibility" required>
                            <option value="investor" <?= ($data['visibility'] ?? 'investor') === 'investor' ? 'selected' : '' ?>>
                                All Investors
                            </option>
                            <option value="admin" <?= ($data['visibility'] ?? '') === 'admin' ? 'selected' : '' ?>>
                                Admin Only
                            </option>
                            <option value="public" <?= ($data['visibility'] ?? '') === 'public' ? 'selected' : '' ?>>
                                Public
                            </option>
                        </select>
                        <span class="form-hint">
                            "All Investors" = any logged-in investor can download.<br>
                            "Admin Only" = internal use, hidden from investors.
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Linked Project</label>
                    <select name="project_id">
                        <option value="">Company-wide (not project-specific)</option>
                        <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>"
                            <?= ($data['project_id'] ?? $preProjectId) == $proj['id'] ? 'selected' : '' ?>>
                            <?= e($proj['title']) ?>
                            <?php if ($proj['location']): ?>— <?= e($proj['location']) ?><?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">
                        Link to a project if this is a project-specific document (e.g. a PPM for Doty Jackson).
                        Leave blank for company-wide documents.
                    </span>
                </div>
            </div>

            <!-- Allowed types info -->
            <div class="card" style="background:var(--blue-bg);border-color:#1a3a6a;margin-bottom:20px;">
                <div style="display:flex;gap:12px;align-items:flex-start;">
                    <span style="font-size:18px;">ℹ</span>
                    <div style="font-size:13px;color:var(--blue);line-height:1.6;">
                        <strong>Security note:</strong> Documents are stored outside the public web folder
                        and served through a secure PHP download handler. Investors can only access
                        files their visibility level permits. Every download is logged.
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-8" style="justify-content:flex-end;">
                <a href="<?= APP_URL ?>/admin/documents/index.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" id="submitBtn">
                    Upload Document →
                </button>
            </div>

        </form>
    </div>
</div>

<script>
const dropZone = document.getElementById('dropZone');

// Drag and drop
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) {
        document.getElementById('fileInput').files = e.dataTransfer.files;
        showFile(file);
    }
});

function handleFileSelect(input) {
    if (input.files && input.files[0]) showFile(input.files[0]);
}

function showFile(file) {
    const icons = { 'pdf': '📄', 'doc': '📝', 'docx': '📝', 'xls': '📊', 'xlsx': '📊' };
    const ext   = file.name.split('.').pop().toLowerCase();

    document.getElementById('fileIcon').textContent = icons[ext] || '📎';
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatBytes(file.size);
    document.getElementById('fileSelected').classList.add('show');
    dropZone.style.opacity = '.5';

    // Auto-populate title if empty
    const titleInput = document.querySelector('input[name="title"]');
    if (!titleInput.value) {
        titleInput.value = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
    }
}

function clearFile() {
    document.getElementById('fileInput').value = '';
    document.getElementById('fileSelected').classList.remove('show');
    dropZone.style.opacity = '1';
    document.getElementById('fileName').textContent = '—';
    document.getElementById('fileSize').textContent = '—';
}

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}
</script>

</body>
</html>
