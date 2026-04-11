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
 * video_play.php — Video playback page
 *
 * Streams/plays a single video, shows its description, and allows
 * the owner to edit description or delete the video.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$currentUser = current_user();
$mediaId     = sanitise_int($_GET['id'] ?? 0);

if (!$mediaId) {
    flash_set('error', 'Video not found.');
    redirect(SITE_URL . '/pages/video.php');
}

$video = db_row(
    "SELECT m.*, u.id AS owner_id, u.username, u.avatar_path
     FROM media m
     JOIN users u ON u.id = m.user_id
     WHERE m.id = ? AND m.type = 'video' AND m.is_deleted = 0 AND u.is_banned = 0",
    [$mediaId]
);

if (!$video) {
    flash_set('error', 'Video not found.');
    redirect(SITE_URL . '/pages/video.php');
}

$isOwn = ((int) $currentUser['id'] === (int) $video['owner_id']) || is_admin();

// ── Handle POST actions ───────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_description' && $isOwn) {
        $desc = sanitise_string($_POST['description'] ?? '', 500);
        db_exec(
            'UPDATE media SET description = ? WHERE id = ?',
            [$desc, $mediaId]
        );
        flash_set('success', 'Description updated.');
        redirect(SITE_URL . '/pages/video_play.php?id=' . $mediaId);
    }

    if ($action === 'delete_video' && $isOwn) {
        db_exec('UPDATE media SET is_deleted = 1 WHERE id = ?', [$mediaId]);
        media_delete_files($video);
        flash_set('success', 'Video deleted.');
        redirect(SITE_URL . '/pages/video.php');
    }
}

// ── Build URLs ────────────────────────────────────────────────────────────────

$videoUrl  = get_media_url($video, 'original');
$thumbUrl  = !empty($video['thumbnail_path'])
    ? get_media_url($video, 'thumbnail')
    : SITE_URL . '/assets/images/placeholder.svg';

$pageTitle = 'Video';

include SITE_ROOT . '/includes/header.php';
?>

<div class="two-col-layout">

    <!-- ── Left Column ─────────────────────────────────────────── -->
    <aside class="col-left">
        <?php include SITE_ROOT . '/includes/sidebar_widgets.php'; ?>
    </aside>

    <!-- ── Right Column ────────────────────────────────────────── -->
    <main class="col-right">

<div class="video-play-wrap">

    <!-- ── Video player ──────────────────────────────────────────── -->
    <div class="video-player-box">
        <video controls preload="metadata"
               poster="<?= e($thumbUrl) ?>"
               class="video-player">
            <source src="<?= e($videoUrl) ?>" type="<?= e($video['mime_type'] ?? 'video/mp4') ?>">
            Your browser does not support the video element.
        </video>
    </div>

    <!-- ── Description & meta ────────────────────────────────────── -->
    <div class="video-play-meta">
        <div class="video-play-uploader">
            <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$video['owner_id']) ?>">
                <img src="<?= e(avatar_url($video, 'small')) ?>"
                     alt="<?= e($video['username']) ?>"
                     width="32" height="32" class="video-play-avatar">
            </a>
            <div>
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$video['owner_id']) ?>"
                   class="video-play-username"><?= e($video['username']) ?></a>
                <span class="muted video-play-date"><?= e(time_ago($video['created_at'])) ?></span>
            </div>
        </div>

        <?php if ($isOwn): ?>
        <div class="video-play-owner-actions">
            <button type="button" class="btn btn-secondary btn-sm"
                    id="video-edit-desc-toggle">Edit Description</button>
            <a href="<?= e($videoUrl) ?>" download class="btn btn-secondary btn-sm">&#8595; Download</a>
            <form method="POST" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_video">
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="Delete this video permanently?">Delete</button>
            </form>
        </div>

        <div id="video-edit-desc-form" style="display:none" class="video-edit-desc-form">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_description">
                <textarea name="description" maxlength="500" rows="3"
                          class="form-control"
                          placeholder="Add a description…"><?= e($video['description'] ?? '') ?></textarea>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.5rem">Save</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($video['description'])): ?>
        <p class="video-play-description"><?= e($video['description']) ?></p>
        <?php endif; ?>

        <?php if ($video['duration']): ?>
        <p class="muted video-play-duration">
            Duration: <?php
                $d = (int) $video['duration'];
                echo $d >= 3600
                    ? sprintf('%d:%02d:%02d', intdiv($d, 3600), intdiv($d % 3600, 60), $d % 60)
                    : sprintf('%d:%02d', intdiv($d, 60), $d % 60);
            ?>
        </p>
        <?php endif; ?>
    </div>

</div><!-- /.video-play-wrap -->

    </main>

</div><!-- /.two-col-layout -->

<script>
(function () {
    var btn  = document.getElementById('video-edit-desc-toggle');
    var form = document.getElementById('video-edit-desc-form');
    if (!btn || !form) return;
    btn.addEventListener('click', function () {
        var shown = form.style.display !== 'none';
        form.style.display = shown ? 'none' : 'block';
        btn.textContent = shown ? 'Edit Description' : 'Cancel';
    });
})();
</script>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
