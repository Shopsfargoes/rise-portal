<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Create Post
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Project;

Auth::requireAdmin();

$projects = Project::findAll([], 100, 0);
$data     = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>New Post — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .form-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px; }
        .form-card-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border); }
        .form-card-icon  { font-size:20px;margin-top:2px; }
        .form-card-title { font-size:15px;font-weight:700; }
        .form-card-sub   { font-size:12px;color:var(--muted);margin-top:2px; }

        /* Rich editor */
        .rich-editor {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }

        .editor-toolbar {
            display: flex;
            gap: 2px;
            padding: 8px 10px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .toolbar-btn {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            padding: 5px 9px;
            border-radius: 5px;
            font-size: 13px;
            transition: background .15s, color .15s;
        }

        .toolbar-btn:hover { background: var(--border); color: var(--text); }

        .editor-area {
            min-height: 320px;
            padding: 16px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--text);
            outline: none;
        }

        .editor-area:empty::before {
            content: attr(data-placeholder);
            color: var(--muted2);
            pointer-events: none;
        }

        .editor-area h2 { font-size: 20px; font-weight: 700; margin: 16px 0 8px; }
        .editor-area h3 { font-size: 16px; font-weight: 700; margin: 14px 0 6px; }
        .editor-area p  { margin-bottom: 10px; }
        .editor-area ul, .editor-area ol { padding-left: 24px; margin-bottom: 10px; }
        .editor-area li { margin-bottom: 4px; }
        .editor-area strong { font-weight: 700; }
        .editor-area em { font-style: italic; }
        .editor-area a  { color: var(--gold); }

        /* Cover preview */
        .cover-wrap { position:relative; }
        .cover-preview {
            width: 100%; height: 200px;
            object-fit: cover; border-radius: 8px;
            border: 1px solid var(--border);
            display: none;
        }

        .cover-placeholder {
            width: 100%; height: 200px;
            border: 2px dashed var(--border);
            border-radius: 8px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 8px; color: var(--muted); font-size: 13px;
            cursor: pointer; transition: border-color .2s;
        }

        .cover-placeholder:hover { border-color: var(--gold); }

        /* Char counter */
        .char-count { font-size: 11px; color: var(--muted); text-align: right; margin-top: 4px; }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <a href="<?= APP_URL ?>/admin/posts/index.php" class="back-link">← Back to Posts</a>
                <h1 class="page-title">New Post</h1>
                <p class="page-sub">Write a news article or market update for investors</p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="<?= APP_URL ?>/app/Actions/admin/save-post.php"
              enctype="multipart/form-data" style="max-width:800px;">
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="create"/>

            <!-- Settings bar -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">⚙️</span>
                    <div>
                        <div class="form-card-title">Post Settings</div>
                        <div class="form-card-sub">Type, visibility and project link</div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Post Type <span class="required">*</span></label>
                        <select name="type" required>
                            <option value="news"   <?= ($data['type'] ?? 'news') === 'news'   ? 'selected' : '' ?>>📰 News</option>
                            <option value="update" <?= ($data['type'] ?? '')     === 'update' ? 'selected' : '' ?>>📈 Market Update</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="draft"     <?= ($data['status'] ?? 'draft')  === 'draft'     ? 'selected' : '' ?>>Draft — not visible to investors</option>
                            <option value="published" <?= ($data['status'] ?? '')        === 'published' ? 'selected' : '' ?>>Published — live now</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label>Linked Project</label>
                    <select name="project_id">
                        <option value="">Company-wide (no specific project)</option>
                        <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>"
                            <?= ($data['project_id'] ?? '') == $proj['id'] ? 'selected' : '' ?>>
                            <?= e($proj['title']) ?>
                            <?php if ($proj['location']): ?>— <?= e($proj['location']) ?><?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Link to a specific project to show it on that project's page.</span>
                </div>
            </div>

            <!-- Content -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">✏️</span>
                    <div>
                        <div class="form-card-title">Content</div>
                        <div class="form-card-sub">Title and body of the post</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" id="titleInput" required
                           placeholder="e.g. WTI Crude Update — May 2026"
                           value="<?= e($data['title'] ?? '') ?>"
                           oninput="updateCharCount(this, 'titleCount', 120)"/>
                    <div class="char-count"><span id="titleCount">0</span>/120</div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label>Body <span class="required">*</span></label>

                    <!-- Toolbar -->
                    <div class="rich-editor">
                        <div class="editor-toolbar">
                            <button type="button" class="toolbar-btn" onclick="fmt('bold')" title="Bold"><strong>B</strong></button>
                            <button type="button" class="toolbar-btn" onclick="fmt('italic')" title="Italic"><em>I</em></button>
                            <button type="button" class="toolbar-btn" onclick="fmt('underline')" title="Underline"><u>U</u></button>
                            <button type="button" class="toolbar-btn" onclick="fmtBlock('h2')" title="Heading 2">H2</button>
                            <button type="button" class="toolbar-btn" onclick="fmtBlock('h3')" title="Heading 3">H3</button>
                            <button type="button" class="toolbar-btn" onclick="fmt('insertUnorderedList')" title="Bullet list">• List</button>
                            <button type="button" class="toolbar-btn" onclick="fmt('insertOrderedList')" title="Numbered list">1. List</button>
                            <button type="button" class="toolbar-btn" onclick="addLink()" title="Link">🔗</button>
                            <button type="button" class="toolbar-btn" onclick="fmt('removeFormat')" title="Clear formatting">✕ Format</button>
                        </div>
                        <div class="editor-area"
                             id="editorArea"
                             contenteditable="true"
                             data-placeholder="Write your post content here..."><?= $data['body'] ?? '' ?></div>
                    </div>
                    <input type="hidden" name="body" id="bodyInput"/>
                </div>
            </div>

            <!-- Cover image -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">🖼</span>
                    <div>
                        <div class="form-card-title">Cover Image</div>
                        <div class="form-card-sub">Displayed at the top of the post and in listings</div>
                    </div>
                </div>

                <input type="file" name="cover_image" id="coverFile"
                       accept="image/jpeg,image/png,image/webp"
                       style="display:none;" onchange="previewCover(this)"/>

                <div class="cover-placeholder" id="coverPlaceholder"
                     onclick="document.getElementById('coverFile').click()">
                    <span style="font-size:32px;">📷</span>
                    <span>Click to upload cover image</span>
                    <span style="font-size:11px;">JPG, PNG or WebP · Max 5MB</span>
                </div>
                <img id="coverPreview" class="cover-preview" alt=""/>
            </div>

            <div class="flex gap-8" style="justify-content:flex-end;">
                <a href="<?= APP_URL ?>/admin/posts/index.php" class="btn-secondary">Cancel</a>
                <button type="submit" name="publish" value="0" class="btn-secondary"
                        onclick="document.querySelector('[name=status]').value='draft'">
                    Save as Draft
                </button>
                <button type="submit" class="btn-primary"
                        onclick="document.querySelector('[name=status]').value='published'">
                    Publish Now →
                </button>
            </div>

        </form>
    </div>
</div>

<script>
// Sync contenteditable to hidden input before submit
document.querySelector('form').addEventListener('submit', function() {
    document.getElementById('bodyInput').value =
        document.getElementById('editorArea').innerHTML;
});

function fmt(cmd) {
    document.execCommand(cmd, false, null);
    document.getElementById('editorArea').focus();
}

function fmtBlock(tag) {
    document.execCommand('formatBlock', false, tag);
    document.getElementById('editorArea').focus();
}

function addLink() {
    const url = prompt('Enter URL:');
    if (url) document.execCommand('createLink', false, url);
}

function updateCharCount(el, countId, max) {
    const len = el.value.length;
    const el2 = document.getElementById(countId);
    if (el2) {
        el2.textContent = len;
        el2.style.color = len > max ? 'var(--red)' : 'var(--muted)';
    }
}

function previewCover(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('coverPreview');
        const ph  = document.getElementById('coverPlaceholder');
        img.src = e.target.result;
        img.style.display = 'block';
        ph.style.display  = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

// Init char count
const titleInput = document.getElementById('titleInput');
if (titleInput) updateCharCount(titleInput, 'titleCount', 120);
</script>

</body>
</html>
