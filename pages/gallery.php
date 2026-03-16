<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Free to use, copy, modify, fork, and distribute.
 *
 * NOT allowed:
 * - Selling this software
 * - Redistributing it for profit
 *
 * Provided "AS IS" without warranty.
 */
/**
 * gallery.php — User gallery with albums and media
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$currentUser  = current_user();
$galleryOwner = sanitise_int($_GET['user_id'] ?? (int)$currentUser['id']);
$albumId      = sanitise_int($_GET['album'] ?? 0);
$isOwn        = ((int)$currentUser['id'] === $galleryOwner);

$owner = db_row('SELECT id, username, avatar_path FROM users WHERE id = ? AND is_banned = 0', [$galleryOwner]);
if (!$owner) {
    flash_set('error', 'User not found.');
    redirect(SITE_URL . '/pages/members.php');
}

$pageTitle = e($owner['username']) . "'s Gallery";

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwn) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_album':
            $title = sanitise_string($_POST['title'] ?? '', 255);
            if ($title) {
                db_insert('INSERT INTO albums (user_id, title) VALUES (?, ?)', [(int)$currentUser['id'], $title]);
                flash_set('success', 'Album created.');
            }
            redirect(SITE_URL . '/pages/gallery.php?user_id=' . $galleryOwner);
            break;

        case 'rename_album':
            $aId   = sanitise_int($_POST['album_id'] ?? 0);
            $title = sanitise_string($_POST['title'] ?? '', 255);
            db_exec(
                'UPDATE albums SET title = ? WHERE id = ? AND user_id = ?',
                [$title, $aId, (int)$currentUser['id']]
            );
            flash_set('success', 'Album renamed.');
            redirect(SITE_URL . '/pages/gallery.php?user_id=' . $galleryOwner . '&album=' . $aId);
            break;

        case 'delete_album':
            $aId = sanitise_int($_POST['album_id'] ?? 0);
            db_exec(
                'UPDATE albums SET is_deleted = 1 WHERE id = ? AND user_id = ?',
                [$aId, (int)$currentUser['id']]
            );
            flash_set('success', 'Album deleted.');
            redirect(SITE_URL . '/pages/gallery.php?user_id=' . $galleryOwner);
            break;

        case 'upload_media':
            $isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                          && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            $aId        = sanitise_int($_POST['album_id'] ?? 0);
            // When JS sends multiple batches, only create the wall post on the last one
            $createPost = ($_POST['create_post'] ?? '1') !== '0';
            $uploaded = 0;
            $errors   = [];
            $uploadedMediaIds = [];
            if (!empty($_FILES['media']['name'][0])) {
                $files = $_FILES['media'];
                $count = count($files['name']);

                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $file = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];
                    $finfo    = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($file['tmp_name']);
                    if (in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
                        $res = process_image_upload($file, (int)$currentUser['id'], $aId);
                    } elseif (in_array($mimeType, ALLOWED_VIDEO_TYPES, true)) {
                        $res = process_video_upload($file, (int)$currentUser['id'], $aId);
                    } else {
                        $res = ['ok' => false, 'error' => 'Unsupported file type.'];
                    }
                    if ($res['ok']) {
                        $uploaded++;
                        if (!empty($res['media_id'])) {
                            $uploadedMediaIds[] = $res['media_id'];
                        }
                    } else {
                        $errors[] = $res['error'];
                    }
                }
            }

            // Create a system wall post announcing the upload
            if ($uploaded > 0 && $aId > 0 && !empty($uploadedMediaIds) && $createPost) {
                $uploadAlbum = db_row(
                    'SELECT title FROM albums WHERE id = ? AND user_id = ? AND is_deleted = 0',
                    [$aId, (int)$currentUser['id']]
                );
                if ($uploadAlbum) {
                    create_album_upload_post(
                        (int)$currentUser['id'],
                        $aId,
                        $uploadAlbum['title'],
                        $uploaded,
                        $uploadedMediaIds
                    );
                }
            }

            $redirectUrl = SITE_URL . '/pages/gallery.php?user_id=' . $galleryOwner . '&album=' . $aId;
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok'       => $uploaded > 0,
                    'uploaded' => $uploaded,
                    'errors'   => $errors,
                    'redirect' => $redirectUrl,
                ]);
                exit;
            }
            if ($uploaded > 0) {
                flash_set('success', $uploaded . ' file(s) uploaded successfully.');
            }
            if (!empty($errors)) {
                flash_set('error', implode(' ', $errors));
            }
            redirect($redirectUrl);
            break;

        case 'set_cover':
            $aId     = sanitise_int($_POST['album_id'] ?? 0);
            $mediaId = sanitise_int($_POST['media_id'] ?? 0);
            $media   = db_row(
                'SELECT * FROM media WHERE id = ? AND user_id = ? AND is_deleted = 0 AND type IN ("image", "video")',
                [$mediaId, (int)$currentUser['id']]
            );
            $album   = db_row(
                'SELECT * FROM albums WHERE id = ? AND user_id = ? AND is_deleted = 0',
                [$aId, (int)$currentUser['id']]
            );

            if ($media && $album) {
                $coverPath = null;
                $crop      = [];
                if (isset($_POST['cover_crop_x'], $_POST['cover_crop_y'], $_POST['cover_crop_w'], $_POST['cover_crop_h'])
                    && is_numeric($_POST['cover_crop_x']) && is_numeric($_POST['cover_crop_y'])
                    && is_numeric($_POST['cover_crop_w']) && is_numeric($_POST['cover_crop_h'])) {
                    $crop = [
                        'x' => (int)$_POST['cover_crop_x'],
                        'y' => (int)$_POST['cover_crop_y'],
                        'w' => (int)$_POST['cover_crop_w'],
                        'h' => (int)$_POST['cover_crop_h'],
                    ];
                }
                if (!empty($crop) && $crop['w'] > 0 && $crop['h'] > 0) {
                    $result = process_cover_crop($media, $crop);
                    if ($result['ok']) {
                        $coverPath = $result['cover_path'];
                    }
                }

                // Delete the previous cropped cover file if one exists
                if (!empty($album['cover_path'])) {
                    cover_delete_file($album['cover_path']);
                }

                db_exec(
                    'UPDATE albums SET cover_id = ?, cover_path = ? WHERE id = ? AND user_id = ?',
                    [$mediaId, $coverPath, $aId, (int)$currentUser['id']]
                );
                flash_set('success', 'Album cover updated.');
            }
            redirect(SITE_URL . '/pages/gallery.php?user_id=' . $galleryOwner . '&album=' . $aId);
            break;

        case 'delete_media':
            $mediaId = sanitise_int($_POST['media_id'] ?? 0);
            $mediaToDelete = db_row(
                'SELECT * FROM media WHERE id = ? AND user_id = ? AND is_deleted = 0',
                [$mediaId, (int)$currentUser['id']]
            );
            if ($mediaToDelete) {
                db_exec(
                    'UPDATE media SET is_deleted = 1 WHERE id = ? AND user_id = ?',
                    [$mediaId, (int)$currentUser['id']]
                );
                media_delete_files($mediaToDelete);
            }
            flash_set('success', 'Media deleted.');
            redirect(SITE_URL . '/pages/gallery.php?user_id=' . $galleryOwner . '&album=' . $albumId);
            break;
    }
}

// Load albums
$albums = db_query(
    'SELECT a.*, (SELECT COUNT(*) FROM media WHERE album_id = a.id AND is_deleted = 0) AS media_count
     FROM albums a
     WHERE a.user_id = ? AND a.is_deleted = 0
     ORDER BY a.created_at DESC',
    [$galleryOwner]
);

// Load media for selected album (first page only)
$mediaItems  = [];
$mediaTotal  = 0;
$mediaLimit  = 25;
if ($albumId > 0) {
    $mediaTotal = (int) db_val(
        'SELECT COUNT(*) FROM media WHERE album_id = ? AND user_id = ? AND is_deleted = 0',
        [$albumId, $galleryOwner]
    );
    $mediaItems = db_query(
        'SELECT m.*,
            (SELECT COUNT(*) FROM likes    WHERE media_id = m.id) AS like_count,
            (SELECT COUNT(*) FROM comments WHERE media_id = m.id AND is_deleted = 0) AS comment_count
         FROM media m
         WHERE m.album_id = ? AND m.user_id = ? AND m.is_deleted = 0
         ORDER BY m.created_at DESC
         LIMIT ' . (int)$mediaLimit,
        [$albumId, $galleryOwner]
    );
}

include SITE_ROOT . '/includes/header.php';
?>

<div class="two-col-layout">

    <!-- ── Left Column ─────────────────────────────────────────── -->
    <aside class="col-left">
        <?php include SITE_ROOT . '/includes/sidebar_widgets.php'; ?>
    </aside>

    <!-- ── Right Column ────────────────────────────────────────── -->
    <main class="col-right gallery-layout">

    <div class="gallery-header">
        <img src="<?= e(avatar_url($owner, 'small')) ?>" alt="" width="40" height="40" class="avatar avatar-small">
        <h1><?= e($owner['username']) ?>'s Gallery</h1>
    </div>

    <!-- Create album form (owner only) -->
    <?php if ($isOwn): ?>
    <div class="gallery-actions">
        <form method="POST" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_album">
            <input type="text" name="title" placeholder="New album name…" required maxlength="255">
            <button type="submit" class="btn btn-primary btn-sm">Create Album</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Albums list -->
    <?php if (empty($albumId)): ?>
    <div class="albums-grid">
        <?php if (empty($albums)): ?>
        <p class="empty-state">No albums yet.</p>
        <?php else: ?>
            <?php foreach ($albums as $album): ?>
            <div class="album-card">
                <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . $galleryOwner . '&album=' . (int)$album['id']) ?>">
                    <div class="album-cover">
                        <?php
                        // Resolve cover: explicit cropped path → cover_id media thumb → first image
                        $coverUrl = null;
                        if (!empty($album['cover_path'])) {
                            $coverUrl = SITE_URL . $album['cover_path'];
                        } elseif (!empty($album['cover_id'])) {
                            $coverMedia = db_row(
                                'SELECT thumb_path FROM media WHERE id = ? AND is_deleted = 0',
                                [(int)$album['cover_id']]
                            );
                            if ($coverMedia && $coverMedia['thumb_path']) {
                                $coverUrl = get_media_url($coverMedia, 'thumb');
                            }
                        }
                        if (!$coverUrl) {
                            $firstImg = db_row(
                                'SELECT thumb_path FROM media WHERE album_id = ? AND is_deleted = 0 ORDER BY created_at ASC LIMIT 1',
                                [(int)$album['id']]
                            );
                            if ($firstImg && $firstImg['thumb_path']) {
                                $coverUrl = get_media_url($firstImg, 'thumb');
                            }
                        }
                        ?>
                        <?php if ($coverUrl): ?>
                        <img src="<?= e($coverUrl) ?>" alt="" loading="lazy">
                        <?php else: ?>
                        <div class="album-cover-placeholder">📁</div>
                        <?php endif; ?>
                    </div>
                    <h3 class="album-title"><?= e($album['title']) ?></h3>
                    <p class="album-count"><?= (int)$album['media_count'] ?> items</p>
                </a>
                <?php if ($isOwn): ?>
                <div class="album-actions">
                    <form method="POST" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_album">
                        <input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-xs"
                                onclick="return confirm('Delete album?')">Delete</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Media inside album -->
    <?php else: ?>
    <?php
    $currentAlbum = db_row('SELECT * FROM albums WHERE id = ? AND user_id = ? AND is_deleted = 0', [$albumId, $galleryOwner]);
    if (!$currentAlbum) {
        flash_set('error', 'Album not found.');
        redirect(SITE_URL . '/pages/gallery.php?user_id=' . $galleryOwner);
    }
    ?>
    <div class="album-view">
        <div class="album-view-header">
            <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . $galleryOwner) ?>"
               class="btn btn-secondary btn-sm">← Back to Albums</a>
            <h2><?= e($currentAlbum['title']) ?></h2>
        </div>

        <?php if ($isOwn): ?>
        <!-- Multi-file dropzone upload -->
        <div class="dropzone" id="gallery-dropzone">
            <div class="dropzone-inner">
                <div class="dropzone-icon">📷</div>
                <p>Drop images or videos here, or <span class="dropzone-link">click to browse</span></p>
                <p class="muted">JPG, PNG, GIF, WebP, MP4, WebM · Max <?= (int)(MAX_UPLOAD_BYTES / 1024 / 1024) ?> MB per file · Multiple files allowed</p>
            </div>
            <div class="dropzone-previews" id="dropzone-previews"></div>
            <form method="POST" enctype="multipart/form-data" id="gallery-upload-form"
                  data-batch-size="<?= MAX_UPLOAD_FILES ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_media">
                <input type="hidden" name="album_id" value="<?= $albumId ?>">
                <input type="file" id="gallery-file-input" name="media[]"
                       accept="image/*,video/mp4,video/webm" class="sr-only" multiple>
                <button type="submit" id="gallery-upload-btn" class="btn btn-primary" style="display:none">
                    Upload Selected Files
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php $hasMoreMedia = $mediaTotal > $mediaLimit; ?>
        <div class="media-grid" id="lightbox-gallery"
             data-album-id="<?= $albumId ?>"
             data-user-id="<?= $galleryOwner ?>"
             data-offset="<?= count($mediaItems) ?>"
             data-has-more="<?= $hasMoreMedia ? '1' : '0' ?>">
            <?php if (empty($mediaItems)): ?>
            <p class="empty-state">No media in this album yet.</p>
            <?php else: ?>
                <?php foreach ($mediaItems as $media): ?>
                <?php $isCover = ((int)$media['id'] === (int)($currentAlbum['cover_id'] ?? 0)); ?>
                <?php include SITE_ROOT . '/modules/gallery/media_item.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if ($hasMoreMedia): ?>
        <div id="media-load-sentinel" class="media-load-sentinel" aria-hidden="true"></div>
        <div id="media-load-spinner" class="media-load-spinner" style="display:none" role="status" aria-label="Loading more media" aria-live="polite">
            <span class="media-load-spinner-dot"></span>
            <span class="media-load-spinner-dot"></span>
            <span class="media-load-spinner-dot"></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Cover Crop Modal -->
    <?php if ($isOwn): ?>
    <div id="cover-crop-modal" class="crop-modal" style="display:none"
         role="dialog" aria-modal="true" aria-label="Set Album Cover">
        <div class="crop-modal-inner">
            <h3>Set Album Cover</h3>
            <p class="muted">Drag inside the selection to reposition it. Drag outside to draw a new square crop area.</p>
            <canvas id="cover-crop-canvas"></canvas>
            <form method="POST" id="cover-crop-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_cover">
                <input type="hidden" name="album_id" id="cover-album-id" value="">
                <input type="hidden" name="media_id" id="cover-media-id" value="">
                <input type="hidden" name="cover_crop_x" id="cover-crop-x">
                <input type="hidden" name="cover_crop_y" id="cover-crop-y">
                <input type="hidden" name="cover_crop_w" id="cover-crop-w">
                <input type="hidden" name="cover_crop_h" id="cover-crop-h">
                <div class="crop-modal-actions">
                    <button type="button" id="cover-crop-cancel" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Cover</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    </main>

</div><!-- /.two-col-layout -->
<?php if ($albumId > 0): ?>
<script src="<?= ASSETS_URL ?>/js/masonry_layout.js"></script>
<script src="<?= ASSETS_URL ?>/js/gallery_infinite_scroll.js"></script>
<?php endif; ?>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
