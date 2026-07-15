<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Edit Post
// ============================================================
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Post;
use Rise\Models\Project;

Auth::requireAdmin();

$id = (int) get('id');
if (!$id) redirect('/admin/posts/index.php');

$post = Post::findById($id);
if (!$post) redirect('/admin/posts/index.php');

$projects = Project::findAll([], 100, 0);
$data     = $_SESSION['form_data'] ?? $post;
unset($_SESSION['form_data']);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit Post — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .form-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px; }
        .form-card-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border); }
        .form-card-icon  { font-size:20px;margin-top:2px; }
        .form-card-title { font-size:15px;font-weight:700; }
        .form-card-sub   { font-size:12px;color:var(--muted);margin-top:2px; }
        .rich-editor { background:var(--surface2);border:1px solid var(--border);border-radius:8px;overflow:hidden; }
        .editor-toolbar { display:flex;gap:2px;padding:8px 10px;border-bottom:1px solid var(--border);flex-wrap:wrap; }
        .toolbar-btn { background:none;border:none;color:var(--muted);cursor:pointer;padding:5px 9px;border-radius:5px;font-size:13px;transition:background .15s,color .15s; }
        .toolbar-btn:hover { background:var(--border);color:var(--text); }
        .editor-area { min-height:320px;padding:16px;font-size:14px;line-height:1.7;color:var(--text);outline:none; }
        .editor-area h2 { font-size:20px;font-weight:700;margin:16px 0 8px; }
        .editor-area h3 { font-size:16px;font-weight:700;margin:14px 0 6px; }
        .editor-area p  { margin-bottom:10px; }
        .editor-area ul, .editor-area ol { padding-left:24px;margin-bottom:10px; }
        .editor-area strong { font-weight:700; }
        .editor-area em { font-style:italic; }
        .editor-area a  { color:var(--gold); }
        .cover-preview { width:100%;height:200px;object-fit:cover;border-radius:8px;border:1px solid var(--border); }
        .cover-placeholder { width:100%;height:200px;border:2px dashed var(--border);border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:var(--muted);font-size:13px;cursor:pointer;transition:border-color .2s; }
        .cover-placeholder:hover { border-color:var(--gold); }
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
                <h1 class="page-title">Edit Post</h1>
                <div class="flex-center gap-8 mt-4">
                    <span class="badge <?= $post['status'] === 'published' ? 'badge-green' : 'badge-grey' ?>">
                        <?= ucfirst($post['status']) ?>
                    </span>
                    <span class="badge <?= $post['type'] === 'news' ? 'badge-blue' : 'badge-gold' ?>">
                        <?= ucfirst($post['type']) ?>
                    </span>
                    <?php if ($post['published_at']): ?>
                    <span style="font-size:12px;color:var(--muted);">
                        Published <?= formatDate($post['published_at']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($post['status'] === 'published'): ?>
            <a href="<?= APP_URL ?>/investor/news.php" class="btn-secondary" target="_blank">View Live →</a>
            <?php endif; ?>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="<?= APP_URL ?>/app/Actions/admin/save-post.php"
              enctype="multipart/form-data" style="max-width:800px;">
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="update"/>
            <input type="hidden" name="post_id" value="<?= $id ?>"/>

            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">⚙️</span>
                    <div>
                        <div class="form-card-title">Post Settings</div>
                        <div class="form-card-sub">Slug: <code style="color:var(--gold);font-size:11px;">/<?= e($post['slug']) ?></code></div>
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Post Type</label>
                        <select name="type">
                            <option value="news"   <?= ($data['type'] ?? '') === 'news'   ? 'selected' : '' ?>>📰 News</option>
                            <option value="update" <?= ($data['type'] ?? '') === 'update' ? 'selected' : '' ?>>📈 Market Update</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="draft"     <?= ($data['status'] ?? '') === 'draft'     ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= ($data['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Linked Project</label>
                    <select name="project_id">
                        <option value="">Company-wide</option>
                        <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>" <?= ($data['project_id'] ?? '') == $proj['id'] ? 'selected' : '' ?>>
                            <?= e($proj['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">✏️</span>
                    <div><div class="form-card-title">Content</div></div>
                </div>
                <div class="form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" required value="<?= e($data['title'] ?? '') ?>"/>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Body <span class="required">*</span></label>
                    <div class="rich-editor">
                        <div class="editor-toolbar">
                            <button type="button" class="toolbar-btn" onclick="fmt('bold')"><strong>B</strong></button>
                            <button type="button" class="toolbar-btn" onclick="fmt('italic')"><em>I</em></button>
                            <button type="button" class="toolbar-btn" onclick="fmt('underline')"><u>U</u></button>
                            <button type="button" class="toolbar-btn" onclick="fmtBlock('h2')">H2</button>
                            <button type="button" class="toolbar-btn" onclick="fmtBlock('h3')">H3</button>
                            <button type="button" class="toolbar-btn" onclick="fmt('insertUnorderedList')">• List</button>
                            <button type="button" class="toolbar-btn" onclick="fmt('insertOrderedList')">1. List</button>
                            <button type="button" class="toolbar-btn" onclick="addLink()">🔗</button>
                            <button type="button" class="toolbar-btn" onclick="fmt('removeFormat')">✕ Format</button>
                        </div>
                        <div class="editor-area" id="editorArea" contenteditable="true"><?= $data['body'] ?? '' ?></div>
                    </div>
                    <input type="hidden" name="body" id="bodyInput"/>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">🖼</span>
                    <div><div class="form-card-title">Cover Image</div></div>
                </div>
                <input type="file" name="cover_image" id="coverFile" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="previewCover(this)"/>
                <?php if (!empty($post['cover_image'])): ?>
                <img src="<?= e($post['cover_image']) ?>" class="cover-preview" id="coverPreview" onclick="document.getElementById('coverFile').click()" style="cursor:pointer;" title="Click to replace"/>
                <?php else: ?>
                <div class="cover-placeholder" id="coverPlaceholder" onclick="document.getElementById('coverFile').click()">
                    <span style="font-size:32px;">📷</span><span>Click to upload</span>
                </div>
                <img id="coverPreview" class="cover-preview" alt="" style="display:none;"/>
                <?php endif; ?>
            </div>

            <div class="flex gap-8" style="justify-content:flex-end;">
                <a href="<?= APP_URL ?>/admin/posts/index.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save Changes →</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelector('form').addEventListener('submit', function() {
    document.getElementById('bodyInput').value = document.getElementById('editorArea').innerHTML;
});
function fmt(cmd) { document.execCommand(cmd, false, null); document.getElementById('editorArea').focus(); }
function fmtBlock(tag) { document.execCommand('formatBlock', false, tag); document.getElementById('editorArea').focus(); }
function addLink() { const url = prompt('Enter URL:'); if (url) document.execCommand('createLink', false, url); }
function previewCover(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('coverPreview');
        const ph  = document.getElementById('coverPlaceholder');
        img.src = e.target.result; img.style.display = 'block';
        if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
</body>
</html>
