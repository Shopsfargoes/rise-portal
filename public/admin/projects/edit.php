<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Edit Project
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Project;

Auth::requireAdmin();

$id = (int) get('id');
if (!$id) redirect('/admin/projects/index.php');

$project = Project::findById($id);
if (!$project) redirect('/admin/projects/index.php');

$categories = Project::getCategories();
$tags       = array_column(Project::getTags($id), 'tag');
$stats      = Project::getStats($id);

// Repopulate with session data if returning after error
$data = $_SESSION['form_data'] ?? $project;
unset($_SESSION['form_data']);

// Restore tags from session if present
if (!empty($_SESSION['form_tags'])) {
    $tags = explode(',', $_SESSION['form_tags']);
    unset($_SESSION['form_tags']);
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit <?= e($project['title']) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .form-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px; }
        .form-card-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border); }
        .form-card-icon  { font-size:20px;margin-top:2px; }
        .form-card-title { font-size:15px;font-weight:700; }
        .form-card-sub   { font-size:12px;color:var(--muted);margin-top:2px; }
        .tag-wrap { display:flex;flex-wrap:wrap;gap:6px;padding:8px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;min-height:44px;cursor:text; }
        .tag-chip { display:inline-flex;align-items:center;gap:5px;background:var(--gold-dim);border:1px solid var(--gold-border);color:var(--gold);border-radius:99px;padding:3px 10px;font-size:12px;font-weight:600; }
        .tag-chip button { background:none;border:none;color:var(--gold);cursor:pointer;font-size:13px;padding:0;opacity:.7; }
        .tag-chip button:hover { opacity:1; }
        .tag-input { border:none;background:transparent;color:var(--text);font-size:13px;outline:none;min-width:120px;flex:1;padding:2px 4px; }
        .img-preview { width:100%;height:180px;border-radius:8px;object-fit:cover;border:1px solid var(--border); }
        .img-placeholder { width:100%;height:180px;border-radius:8px;border:2px dashed var(--border);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:var(--muted);font-size:13px;cursor:pointer;transition:border-color .2s; }
        .img-placeholder:hover { border-color:var(--gold); }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <a href="<?= APP_URL ?>/admin/projects/index.php" class="back-link">← Back to Projects</a>
                <h1 class="page-title"><?= e($project['title']) ?></h1>
                <div class="flex-center gap-8 mt-4">
                    <span class="badge <?= Project::statusBadgeClass($project['status']) ?>">
                        <?= Project::statusLabel($project['status']) ?>
                    </span>
                    <span style="font-size:12px;color:var(--muted);"><?= e($project['location'] ?? '') ?></span>
                </div>
            </div>
            <div class="flex gap-8">
                <a href="<?= APP_URL ?>/admin/projects/timeline.php?id=<?= $id ?>" class="btn-secondary">
                    📅 Timeline
                </a>
                <a href="<?= APP_URL ?>/investor/project-detail.php?slug=<?= e($project['slug']) ?>"
                   class="btn-secondary" target="_blank">
                    Preview →
                </a>
            </div>
        </div>

        <!-- Stats row -->
        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
            <div class="stat-card">
                <div class="stat-label">Investors</div>
                <div class="stat-value"><?= (int)$stats['investor_count'] ?></div>
                <div class="stat-sub">Active in this project</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Raised</div>
                <div class="stat-value text-gold"><?= formatCurrency((float)$stats['total_raised']) ?></div>
                <div class="stat-sub">of <?= formatCurrency((float)$project['project_cost']) ?> cost</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Distributed</div>
                <div class="stat-value"><?= formatCurrency((float)$stats['total_distributed']) ?></div>
                <div class="stat-sub">Paid to investors</div>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="<?= APP_URL ?>/app/Actions/admin/save-project.php"
              enctype="multipart/form-data" style="max-width:800px;">
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="update"/>
            <input type="hidden" name="project_id" value="<?= $id ?>"/>

            <!-- Basic Info -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">📋</span>
                    <div>
                        <div class="form-card-title">Project Information</div>
                        <div class="form-card-sub">Slug: <code style="color:var(--gold);font-size:11px;"><?= e($project['slug']) ?></code></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Project Title <span class="required">*</span></label>
                    <input type="text" name="title" required value="<?= e($data['title'] ?? '') ?>"/>
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
                        <label>Status <span class="required">*</span></label>
                        <select name="status" required>
                            <?php foreach (['open','closed','drilled','producing','abandoned'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($data['status'] ?? '') === $s ? 'selected' : '' ?>>
                                <?= Project::statusLabel($s) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?= e($data['location'] ?? '') ?>"/>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="5"><?= e($data['description'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Specs -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">⚙️</span>
                    <div><div class="form-card-title">Project Specifications</div></div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Project Type</label>
                        <input type="text" name="project_type" value="<?= e($data['project_type'] ?? '') ?>"/>
                    </div>
                    <div class="form-group">
                        <label>Production Type</label>
                        <input type="text" name="production_type" value="<?= e($data['production_type'] ?? '') ?>"/>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Target Depth</label>
                        <input type="text" name="target_depth" value="<?= e($data['target_depth'] ?? '') ?>"/>
                    </div>
                    <div class="form-group">
                        <label>Project Cost ($) <span class="required">*</span></label>
                        <input type="number" name="project_cost" required min="0" step="0.01"
                               value="<?= e($data['project_cost'] ?? '') ?>"/>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" min="0" value="<?= e($data['sort_order'] ?? 0) ?>"/>
                    </div>
                    <div class="form-group">
                        <label>Featured?</label>
                        <select name="is_featured">
                            <option value="0" <?= empty($data['is_featured']) ? 'selected' : '' ?>>No</option>
                            <option value="1" <?= !empty($data['is_featured']) ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tags -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">🏷</span>
                    <div><div class="form-card-title">Tags</div></div>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Tags (press Enter or comma to add)</label>
                    <div class="tag-wrap" id="tagWrap" onclick="document.getElementById('tagInput').focus()">
                        <input type="text" id="tagInput" class="tag-input" placeholder="Add a tag..."/>
                    </div>
                    <input type="hidden" name="tags" id="tagsHidden" value="<?= e(implode(',', $tags)) ?>"/>
                </div>
            </div>

            <!-- Cover Image -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">🖼</span>
                    <div><div class="form-card-title">Cover Image & Map</div></div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Cover Image <?= !empty($project['cover_image']) ? '(upload to replace)' : '' ?></label>
                        <input type="file" name="cover_image" id="coverFile"
                               accept="image/jpeg,image/png,image/webp"
                               style="display:none;" onchange="previewCover(this)"/>
                        <?php if (!empty($project['cover_image'])): ?>
                        <img src="<?= e($project['cover_image']) ?>" class="img-preview" id="coverPreview"
                             style="display:block;" onclick="document.getElementById('coverFile').click()"
                             title="Click to change"/>
                        <?php else: ?>
                        <div class="img-placeholder" id="coverPlaceholder"
                             onclick="document.getElementById('coverFile').click()">
                            <span style="font-size:28px;">📷</span>
                            <span>Click to upload</span>
                        </div>
                        <img id="coverPreview" class="img-preview" alt="" style="display:none;"/>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="form-group">
                            <label>Map Latitude</label>
                            <input type="text" name="map_lat" value="<?= e($data['map_lat'] ?? '') ?>"/>
                        </div>
                        <div class="form-group">
                            <label>Map Longitude</label>
                            <input type="text" name="map_lng" value="<?= e($data['map_lng'] ?? '') ?>"/>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-8" style="justify-content:flex-end;padding-top:8px;">
                <a href="<?= APP_URL ?>/admin/projects/index.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>

        </form>
    </div>
</div>

<script>
// Tag input — pre-populate existing tags
const tags   = <?= json_encode($tags) ?>;
const wrap   = document.getElementById('tagWrap');
const input  = document.getElementById('tagInput');
const hidden = document.getElementById('tagsHidden');

tags.forEach((t, i) => renderChip(t, i));

input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = this.value.trim().replace(/,$/, '');
        if (val && !tags.includes(val)) { tags.push(val); renderChip(val, tags.length-1); syncHidden(); }
        this.value = '';
    }
    if (e.key === 'Backspace' && this.value === '' && tags.length) {
        removeTag(tags.length - 1);
    }
});

function renderChip(text, index) {
    const chip = document.createElement('span');
    chip.className = 'tag-chip';
    chip.id = 'chip-' + index;
    chip.innerHTML = `${text} <button type="button" onclick="removeTag(${index})">✕</button>`;
    wrap.insertBefore(chip, input);
}

function removeTag(index) {
    tags.splice(index, 1);
    document.querySelectorAll('.tag-chip').forEach(c => c.remove());
    tags.forEach((t, i) => renderChip(t, i));
    syncHidden();
}

function syncHidden() { hidden.value = tags.join(','); }

function previewCover(el) {
    if (!el.files || !el.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('coverPreview');
        const ph  = document.getElementById('coverPlaceholder');
        img.src = e.target.result;
        img.style.display = 'block';
        if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(el.files[0]);
}
</script>

</body>
</html>