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
 * video.php — Community video hub
 *
 * Allows members to upload videos (stored in their "Videos" album,
 * created automatically if absent) and browse a grid of all community videos.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$currentUser = current_user();
$pageTitle   = 'Videos';
$pageScript  = ASSETS_URL . '/js/video.js';

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Return (or lazily create) the "Videos" album for a user.
 */
function get_or_create_videos_album(int $userId): int
{
    $album = db_row(
        'SELECT id FROM albums WHERE user_id = ? AND title = "Videos" AND is_deleted = 0 LIMIT 1',
        [$userId]
    );
    if ($album) {
        return (int) $album['id'];
    }
    return (int) db_insert(
        'INSERT INTO albums (user_id, title) VALUES (?, "Videos")',
        [$userId]
    );
}

// ── Handle POST: upload ───────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // When the uploaded file exceeds PHP's post_max_size the entire request
    // body is silently discarded, leaving $_POST empty.  Detect this before
    // calling csrf_verify() so the user sees a clear "file too large" message
    // instead of a misleading CSRF error.
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
        flash_set('error', 'Upload too large. The video exceeds the server\'s maximum upload size.');
        redirect(SITE_URL . '/pages/video.php');
    }

    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_video') {
        $description = sanitise_string($_POST['description'] ?? '', 500);
        $albumId     = get_or_create_videos_album((int) $currentUser['id']);

        if (empty($_FILES['video']) || $_FILES['video']['error'] === UPLOAD_ERR_NO_FILE) {
            flash_set('error', 'No video file selected.');
        } else {
            $res = process_video_upload($_FILES['video'], (int) $currentUser['id'], $albumId);
            if ($res['ok']) {
                if ($description !== '' && $res['media_id'] > 0) {
                    db_exec(
                        'UPDATE media SET description = ? WHERE id = ?',
                        [$description, $res['media_id']]
                    );
                }
                // Create wall post
                $uploadAlbum = db_row(
                    'SELECT title FROM albums WHERE id = ? AND user_id = ? AND is_deleted = 0',
                    [$albumId, (int) $currentUser['id']]
                );
                if ($uploadAlbum) {
                    create_album_upload_post(
                        (int) $currentUser['id'],
                        $albumId,
                        $uploadAlbum['title'],
                        1,
                        [$res['media_id']]
                    );
                }
                flash_set('success', 'Video uploaded successfully.');
            } else {
                flash_set('error', $res['error']);
            }
        }
        redirect(SITE_URL . '/pages/video.php');
    }
}

// ── Pagination ────────────────────────────────────────────────────────────────

$page    = max(1, sanitise_int($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

// Build privacy filter for video listings
$_videoExcludeSql    = '';
$_videoExcludeParams = [];

$_videoHiddenIds = PrivacyService::blockedUsersByAction((int) $currentUser['id'], 'view_videos');
if (!empty($_videoHiddenIds)) {
    $_videoPhs           = implode(',', array_fill(0, count($_videoHiddenIds), '?'));
    $_videoExcludeSql    = "AND u.id NOT IN ($_videoPhs)";
    $_videoExcludeParams = $_videoHiddenIds;
}

$total  = (int) db_val(
    "SELECT COUNT(*) FROM media m
     JOIN users u ON u.id = m.user_id
     WHERE m.type = 'video' AND m.is_deleted = 0 AND u.is_banned = 0 {$_videoExcludeSql}",
    $_videoExcludeParams
);
$pages  = max(1, (int) ceil($total / $perPage));

$limitSql  = (int) $perPage;
$offsetSql = (int) $offset;

$videos = db_query(
    "SELECT m.*, u.username, u.avatar_path,
            (SELECT COUNT(*) FROM likes    WHERE media_id = m.id) AS like_count,
            (SELECT COUNT(*) FROM comments WHERE media_id = m.id AND is_deleted = 0) AS comment_count
     FROM media m
     JOIN users u ON u.id = m.user_id
     WHERE m.type = 'video' AND m.is_deleted = 0 AND u.is_banned = 0 {$_videoExcludeSql}
     ORDER BY m.created_at DESC
     LIMIT {$limitSql} OFFSET {$offsetSql}",
    $_videoExcludeParams
);

include SITE_ROOT . '/includes/header.php';
?>

<div class="two-col-layout">

    <!-- ── Left Column ─────────────────────────────────────────── -->
    <aside class="col-left">
        <?php include SITE_ROOT . '/includes/sidebar_widgets.php'; ?>
    </aside>

    <!-- ── Right Column ────────────────────────────────────────── -->
    <main class="col-right">

<div class="page-header video-page-header">
    <h1>Videos</h1>
    <button type="button" class="btn btn-primary" id="video-upload-toggle">
        &#9650; Upload Video
    </button>
</div>

<!-- ── Upload panel ───────────────────────────────────────────── -->
<div id="video-upload-panel" class="video-upload-panel hidden">
    <h2>Upload a Video</h2>
    <form method="POST" enctype="multipart/form-data" id="video-upload-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_video">
        <div class="form-group">
            <label for="video-file">Video file
                <span class="muted">(MP4 / WebM / OGG · max <?= (int)(MAX_VIDEO_BYTES / 1024 / 1024) ?> MB)</span>
            </label>
            <input type="file" id="video-file" name="video"
                   accept="video/mp4,video/webm,video/ogg" required>
        </div>
        <div class="form-group">
            <label for="video-desc">Description
                <span class="muted">(optional)</span>
            </label>
            <input type="text" id="video-desc" name="description"
                   maxlength="500" placeholder="Say something about your video…"
                   class="form-control">
        </div>
        <button type="submit" class="btn btn-primary" id="video-upload-btn">Upload</button>
        <span id="video-upload-progress" class="muted" style="display:none">Uploading…</span>
    </form>
</div>

<!-- ── Video grid ─────────────────────────────────────────────── -->
<?php if (empty($videos)): ?>
<p class="empty-state">No videos have been uploaded yet. Be the first!</p>
<?php else: ?>
<div class="video-grid">
    <?php foreach ($videos as $v): ?>
    <?php
        $thumbUrl = !empty($v['thumbnail_path'])
            ? e(get_media_url($v, 'thumbnail'))
            : e(SITE_URL . '/assets/images/placeholder.svg');
        $playUrl  = e(SITE_URL . '/pages/video_play.php?id=' . (int)$v['id']);
        $duration = $v['duration'] ? (int)$v['duration'] : null;
        $durationStr = '';
        if ($duration !== null) {
            $durationStr = ($duration >= 3600)
                ? sprintf('%d:%02d:%02d', intdiv($duration, 3600), intdiv($duration % 3600, 60), $duration % 60)
                : sprintf('%d:%02d', intdiv($duration, 60), $duration % 60);
        }
    ?>
    <div class="video-card">
        <a href="<?= $playUrl ?>" class="video-card-thumb">
            <img src="<?= $thumbUrl ?>" alt="" loading="lazy">
            <span class="video-play-icon" aria-hidden="true">&#9654;</span>
            <?php if ($durationStr): ?>
            <span class="video-duration"><?= e($durationStr) ?></span>
            <?php endif; ?>
            <?php if ((int)$v['like_count'] > 0 || (int)$v['comment_count'] > 0): ?>
            <div class="video-card-stats">
                <?php if ((int)$v['like_count'] > 0): ?>
                <span class="video-card-stat">&#9829; <?= (int)$v['like_count'] ?></span>
                <?php endif; ?>
                <?php if ((int)$v['comment_count'] > 0): ?>
                <span class="video-card-stat">&#128172; <?= (int)$v['comment_count'] ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </a>
        <div class="video-card-info">
            <?php $vDesc = $v['description'] ?? ''; ?>
            <p class="video-card-desc"><?= e(mb_substr($vDesc, 0, 120)) ?><?= mb_strlen($vDesc) > 120 ? '…' : '' ?></p>
            <div class="video-card-meta">
                <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$v['user_id']) ?>"
                   class="video-card-user">
                    <img src="<?= e(avatar_url($v, 'small')) ?>"
                         alt="<?= e($v['username']) ?>"
                         width="20" height="20" class="video-card-avatar">
                    <?= e($v['username']) ?>
                </a>
                <span class="muted"><?= e(time_ago($v['created_at'])) ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
$baseUrl = SITE_URL . '/pages/video.php';
echo pagination_links($page, $pages, $baseUrl);
?>
<?php endif; ?>

    </main>

</div><!-- /.two-col-layout -->

<?php include SITE_ROOT . '/includes/footer.php'; ?>
