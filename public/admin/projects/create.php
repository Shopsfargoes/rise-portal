<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Create Project
// ============================================================
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Project;

Auth::requireAdmin();

$categories = Project::getCategories();
$data       = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>New Project — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .form-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px; }
        .form-card-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border); }
        .form-card-icon  { font-size:20px;margin-top:2px; }
        .form-card-title { font-size:15px;font-weight:700; }
        .form-card-sub   { font-size:12px;color:var(--muted);margin-top:2px; }

        /* Tag input */
        .tag-wrap { display:flex;flex-wrap:wrap;gap:6px;padding:8px;background:var(--surface2);
                    border:1px solid var(--border);border-radius:8px;min-height:44px;cursor:text; }
        .tag-chip { display:inline-flex;align-items:center;gap:5px;background:var(--gold-dim);
                    border:1px solid var(--gold-border);color:var(--gold);border-radius:99px;
                    padding:3px 10px;font-size:12px;font-weight:600; }
        .tag-chip button { background:none;border:none;color:var(--gold);cursor:pointer;font-size:13px;
                           padding:0;line-height:1;opacity:.7; }
        .tag-chip button:hover { opacity:1; }
        .tag-input { border:none;background:transparent;color:var(--text);font-size:13px;
                     outline:none;min-width:120px;flex:1;padding:2px 4px; }

        /* Image preview */
        .img-preview { width:100%;height:180px;border-radius:8px;object-fit:cover;
                       border:1px solid var(--border);display:none; }
        .img-placeholder { width:100%;height:180px;border-radius:8px;border:2px dashed var(--border);
                           display:flex;flex-direction:column;align-items:center;justify-content:center;
                           gap:8px;color:var(--muted);font-size:13px;cursor:pointer;
                           transition:border-color .2s; }
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
                <h1 class="page-title">New Project</h1>
                <p class="page-sub">Create a new oil & gas investment opportunity</p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="<?= APP_URL ?>/app/Actions/admin/save-project.php"
              enctype="multipart/form-data" style="max-width:800px;">
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="create"/>

            <!-- ── Basic Info ── -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">📋</span>
                    <div>
                        <div class="form-card-title">Project Information</div>
                        <div class="form-card-sub">Core details displayed to investors</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Project Title <span class="required">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Doty Jackson"
                           value="<?= e($data['title'] ?? '') ?>"/>
                    <span class="form-hint">A URL slug is auto-generated from the title.</span>
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
                            <option value="<?= $s ?>" <?= ($data['status'] ?? 'open') === $s ? 'selected' : '' ?>>
                                <?= Project::statusLabel($s) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" placeholder="e.g. Hardin County, Texas"
                           value="<?= e($data['location'] ?? '') ?>"/>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="5"
                              placeholder="Describe the project, geological formations, offset data, investment thesis..."><?= e($data['description'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- ── Project Specs ── -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">⚙️</span>
                    <div>
                        <div class="form-card-title">Project Specifications</div>
                        <div class="form-card-sub">Technical details shown on the project card</div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Project Type</label>
                        <input type="text" name="project_type"
                               placeholder="e.g. Exploration and Development"
                               value="<?= e($data['project_type'] ?? '') ?>"/>
                    </div>
                    <div class="form-group">
                        <label>Production Type</label>
                        <input type="text" name="production_type"
                               placeholder="e.g. Oil & Gas"
                               value="<?= e($data['production_type'] ?? '') ?>"/>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Target Depth</label>
                        <input type="text" name="target_depth"
                               placeholder="e.g. 7,500 feet"
                               value="<?= e($data['target_depth'] ?? '') ?>"/>
                    </div>
                    <div class="form-group">
                        <label>Project Cost ($) <span class="required">*</span></label>
                        <input type="number" name="project_cost" required min="0" step="0.01"
                               placeholder="2750000"
                               value="<?= e($data['project_cost'] ?? '') ?>"/>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" min="0" value="<?= e($data['sort_order'] ?? 0) ?>"/>
                        <span class="form-hint">Lower number = shown first. 0 = default.</span>
                    </div>
                    <div class="form-group">
                        <label>Featured Project?</label>
                        <select name="is_featured">
                            <option value="0" <?= empty($data['is_featured']) ? 'selected' : '' ?>>No</option>
                            <option value="1" <?= !empty($data['is_featured']) ? 'selected' : '' ?>>Yes — Show at top</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ── Tags ── -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">🏷</span>
                    <div>
                        <div class="form-card-title">Tags</div>
                        <div class="form-card-sub">Key selling points shown as pills on the project card</div>
                    </div>
                </div>

                <div class="form-group" style="margin:0;">
                    <label>Tags <span style="font-weight:400;text-transform:none;letter-spacing:0;">(press Enter or comma to add)</span></label>
                    <div class="tag-wrap" id="tagWrap" onclick="document.getElementById('tagInput').focus()">
                        <!-- chips injected here -->
                        <input type="text" id="tagInput" class="tag-input"
                               placeholder="e.g. Strong offset production history"/>
                    </div>
                    <input type="hidden" name="tags" id="tagsHidden"
                           value="<?= e($data['tags'] ?? '') ?>"/>
                    <span class="form-hint">Examples: "Target depth 7,500 feet", "Proven geological formations", "Experienced operator"</span>
                </div>
            </div>

            <!-- ── Cover Image & Map ── -->
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-icon">🖼</span>
                    <div>
                        <div class="form-card-title">Cover Image & Map</div>
                        <div class="form-card-sub">Satellite or aerial photo shown on the project card</div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Cover Image</label>
                        <input type="file" name="cover_image" id="coverFile"
                               accept="image/jpeg,image/png,image/webp"
                               style="display:none;" onchange="previewCover(this)"/>
                        <div class="img-placeholder" id="coverPlaceholder"
                             onclick="document.getElementById('coverFile').click()">
                            <span style="font-size:28px;">📷</span>
                            <span>Click to upload cover image</span>
                            <span style="font-size:11px;">JPG, PNG or WebP · Max 5MB</span>
                        </div>
                        <img id="coverPreview" class="img-preview" alt="Cover preview"/>
                    </div>

                    <div>
                        <div class="form-group">
                            <label>Map Latitude</label>
                            <input type="text" name="map_lat" placeholder="30.1234"
                                   value="<?= e($data['map_lat'] ?? '') ?>"/>
                        </div>
                        <div class="form-group">
                            <label>Map Longitude</label>
                            <input type="text" name="map_lng" placeholder="-94.5678"
                                   value="<?= e($data['map_lng'] ?? '') ?>"/>
                        </div>
                        <span class="form-hint">Right-click a location in Google Maps → "What's here?" to get coordinates.</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-8" style="justify-content:flex-end;padding-top:8px;">
                <a href="<?= APP_URL ?>/admin/projects/index.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Create Project →</button>
            </div>

        </form>
    </div>
</div>

<script>
// ── Tag input ─────────────────────────────────────────────
const tags    = [];
const wrap    = document.getElementById('tagWrap');
const input   = document.getElementById('tagInput');
const hidden  = document.getElementById('tagsHidden');

// Restore tags from session data if returning after error
const existing = hidden.value;
if (existing) {
    existing.split(',').forEach(t => { if (t.trim()) addTag(t.trim()); });
}

input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = this.value.trim().replace(/,$/, '');
        if (val) addTag(val);
        this.value = '';
    }
    if (e.key === 'Backspace' && this.value === '' && tags.length) {
        removeTag(tags.length - 1);
    }
});

function addTag(text) {
    if (tags.includes(text)) return;
    tags.push(text);
    const chip = document.createElement('span');
    chip.className = 'tag-chip';
    chip.dataset.index = tags.length - 1;
    chip.innerHTML = `${text} <button type="button" onclick="removeTag(${tags.length - 1})">✕</button>`;
    wrap.insertBefore(chip, input);
    syncHidden();
}

function removeTag(index) {
    tags.splice(index, 1);
    document.querySelectorAll('.tag-chip').forEach(c => c.remove());
    tags.forEach((t, i) => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.innerHTML = `${t} <button type="button" onclick="removeTag(${i})">✕</button>`;
        wrap.insertBefore(chip, input);
    });
    syncHidden();
}

function syncHidden() {
    hidden.value = tags.join(',');
}

// ── Cover image preview ───────────────────────────────────
function previewCover(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('coverPreview').src = e.target.result;
        document.getElementById('coverPreview').style.display = 'block';
        document.getElementById('coverPlaceholder').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>

</body>
</html>
