<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Media Library
// ============================================================
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Uploader;
use Rise\Models\Media;
use Rise\Models\Project;

Auth::requireAdmin();

// Handle upload inline
if (isPost() && post('action') === 'upload') {
    verifyCsrf();

    $title     = trim(post('title'));
    $projectId = (int) post('project_id') ?: null;

    if (!empty($_FILES['media_file']['name'])) {
        $uploader = new Uploader();
        $result   = $uploader->image($_FILES['media_file'], 'media');

        if ($result['success']) {
            Media::create([
                'uploaded_by' => Auth::id(),
                'project_id'  => $projectId,
                'type'        => Media::mimeToType($result['mime_type']),
                'title'       => $title ?: $_FILES['media_file']['name'],
                'file_name'   => $result['filename'],
                'file_path'   => $result['path'],
                'file_size'   => $result['size'],
                'mime_type'   => $result['mime_type'],
            ]);
            flash('Media uploaded successfully.', 'success');
        } else {
            flash($result['error'], 'error');
        }
    } else {
        flash('Please select a file to upload.', 'error');
    }

    redirect('/admin/media/index.php');
}

// Handle delete
if (isPost() && post('action') === 'delete') {
    verifyCsrf();
    $mediaId = (int) post('media_id');
    if ($mediaId) {
        Media::delete($mediaId);
        Auth::audit(Auth::id(), 'delete_media', 'media', $mediaId);
        flash('Media deleted.', 'success');
    }
    redirect('/admin/media/index.php');
}

$type    = get('type', '');
$page    = max(1, (int) get('page', 1));
$perPage = 24;
$offset  = ($page - 1) * $perPage;

$filters    = array_filter(['type' => $type ?: null]);
$total      = Media::count($filters);
$mediaItems = Media::findAll($filters, $perPage, $offset);
$projects   = Project::findAll([], 100, 0);
$totalPages = (int) ceil($total / $perPage);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Media Library — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 14px;
            padding: 20px;
        }

        .media-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            transition: border-color .2s;
        }

        .media-card:hover { border-color: var(--gold); }

        .media-thumb {
            width: 100%; height: 130px;
            object-fit: cover;
            display: block;
            background: var(--surface);
        }

        .media-thumb-placeholder {
            width: 100%; height: 130px;
            background: var(--surface);
            display: flex; align-items: center; justify-content: center;
            font-size: 36px;
        }

        .media-info {
            padding: 10px 12px;
        }

        .media-title {
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px;
        }

        .media-meta {
            font-size: 10px;
            color: var(--muted);
        }

        .media-actions {
            display: flex;
            gap: 4px;
            padding: 0 12px 10px;
        }

        /* Upload zone */
        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 32px;
            text-align: center;
            transition: border-color .2s, background .2s;
            cursor: pointer;
        }

        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: var(--gold);
            background: var(--gold-dim);
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
                <h1 class="page-title">Media Library</h1>
                <p class="page-sub"><?= number_format($total) ?> file<?= $total !== 1 ? 's' : '' ?></p>
            </div>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <!-- Upload form -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <div class="card-title">Upload Media</div>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="upload"/>

                <div class="form-grid-2" style="margin-bottom:14px;">
                    <div class="form-group" style="margin:0;">
                        <label>Title (optional)</label>
                        <input type="text" name="title" placeholder="Leave blank to use filename"/>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Link to Project (optional)</label>
                        <select name="project_id">
                            <option value="">No project</option>
                            <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>"><?= e($proj['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="upload-zone" id="uploadZone"
                     onclick="document.getElementById('mediaFile').click()">
                    <input type="file" name="media_file" id="mediaFile"
                           accept="image/jpeg,image/png,image/webp,image/gif,video/mp4"
                           style="display:none;"
                           onchange="showFileName(this)"/>
                    <div style="font-size:32px;margin-bottom:8px;">📸</div>
                    <div style="font-size:14px;font-weight:600;" id="uploadLabel">
                        Drop or click to upload
                    </div>
                    <div style="font-size:12px;color:var(--muted);margin-top:4px;">
                        JPG, PNG, WebP, GIF, MP4 · Max <?= MAX_UPLOAD_MB ?>MB
                    </div>
                </div>

                <div style="margin-top:12px;text-align:right;">
                    <button type="submit" class="btn-primary">Upload →</button>
                </div>
            </form>
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-8" style="margin-bottom:16px;">
            <a href="<?= APP_URL ?>/admin/media/index.php"
               class="btn-secondary btn-sm <?= !$type ? '' : '' ?>"
               style="<?= !$type ? 'background:var(--gold);color:#000;border-color:var(--gold);' : '' ?>">
                All (<?= $total ?>)
            </a>
            <?php
            $typeCounts = db()->fetchAll(
                "SELECT type, COUNT(*) AS cnt FROM media GROUP BY type ORDER BY type"
            );
            foreach ($typeCounts as $tc):
            ?>
            <a href="?type=<?= $tc['type'] ?>"
               class="btn-secondary btn-sm"
               style="<?= $type === $tc['type'] ? 'background:var(--gold);color:#000;border-color:var(--gold);' : '' ?>">
                <?= Media::icon($tc['type']) ?> <?= ucfirst($tc['type']) ?> (<?= $tc['cnt'] ?>)
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Media grid -->
        <?php if (empty($mediaItems)): ?>
        <div style="text-align:center;padding:48px;color:var(--muted);">
            <div style="font-size:40px;margin-bottom:12px;">🖼</div>
            <p>No media uploaded yet.</p>
        </div>
        <?php else: ?>
        <div class="media-grid">
            <?php foreach ($mediaItems as $item): ?>
            <div class="media-card">
                <?php if ($item['type'] === 'image'): ?>
                <img src="<?= e($item['file_path']) ?>"
                     class="media-thumb" alt="<?= e($item['title'] ?? '') ?>"/>
                <?php else: ?>
                <div class="media-thumb-placeholder">
                    <?= Media::icon($item['type']) ?>
                </div>
                <?php endif; ?>

                <div class="media-info">
                    <div class="media-title"><?= e($item['title'] ?? $item['file_name']) ?></div>
                    <div class="media-meta">
                        <?= humanFileSize((int)$item['file_size']) ?>
                        · <?= formatDate($item['created_at']) ?>
                        <?php if ($item['project_title']): ?>
                        <br><?= e($item['project_title']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="media-actions">
                    <a href="<?= e($item['file_path']) ?>"
                       target="_blank"
                       class="btn-secondary btn-sm" style="flex:1;justify-content:center;">
                        View
                    </a>
                    <form method="POST" action=""
                          onsubmit="return confirm('Delete this media?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"   value="delete"/>
                        <input type="hidden" name="media_id" value="<?= $item['id'] ?>"/>
                        <button type="submit" class="btn-danger btn-sm">✕</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="justify-content:center;margin-top:20px;">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&type=<?= urlencode($type) ?>" class="page-btn">← Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?page=<?= $i ?>&type=<?= urlencode($type) ?>"
               class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?>&type=<?= urlencode($type) ?>" class="page-btn">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<script>
function showFileName(input) {
    if (input.files && input.files[0]) {
        document.getElementById('uploadLabel').textContent = input.files[0].name;
    }
}

const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) {
        document.getElementById('mediaFile').files = e.dataTransfer.files;
        showFileName(document.getElementById('mediaFile'));
    }
});
</script>

</body>
</html>
