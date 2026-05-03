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

$isOwn = ((int) $currentUser['id'] === (int) $video['owner_id']);

// Privacy gate — view_videos
if (!$isOwn && !is_admin() && !PrivacyService::canView((int) $currentUser['id'], (int) $video['owner_id'], 'view_videos')) {
    flash_set('error', 'This user\'s videos are private.');
    redirect(SITE_URL . '/pages/profile.php?id=' . (int) $video['owner_id']);
}

// ── Like state ────────────────────────────────────────────────────────────────

$likeCount = (int) db_val('SELECT COUNT(*) FROM likes WHERE media_id = ?', [$mediaId]);
$userLiked = (int) db_val(
    'SELECT COUNT(*) FROM likes WHERE user_id = ? AND media_id = ?',
    [(int)$currentUser['id'], $mediaId]
) > 0;

$commentCount = (int) db_val(
    'SELECT COUNT(*) FROM comments WHERE media_id = ? AND is_deleted = 0',
    [$mediaId]
);

$comments = db_query(
    'SELECT c.id, c.content, c.created_at, u.id AS user_id, u.username, u.avatar_path
     FROM comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.media_id = ? AND c.is_deleted = 0
     ORDER BY c.created_at ASC',
    [$mediaId]
);

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
$pageScript = ASSETS_URL . '/js/video.js';

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

    <div class="video-play-back">
        <a href="<?= e(SITE_URL . '/pages/video.php') ?>" class="btn btn-secondary btn-sm">&#8592; Go Back</a>
    </div>

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

        <div id="video-edit-desc-form" class="hidden video-edit-desc-form">
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

        <!-- ── Like button ──────────────────────────────────────────── -->
        <div class="video-play-actions">
            <button type="button"
                    class="btn-like-media<?= $userLiked ? ' liked' : '' ?>"
                    id="video-like-btn"
                    data-media-id="<?= $mediaId ?>"
                    aria-label="Like video"
                    aria-pressed="<?= $userLiked ? 'true' : 'false' ?>">
                &#9829; <span id="video-like-count"><?= $likeCount ?></span>
            </button>
            <span class="muted video-comment-count-label">
                <span id="video-comment-count"><?= $commentCount ?></span><span id="video-comment-suffix"> comment<?= $commentCount !== 1 ? 's' : '' ?></span>
            </span>
        </div>
    </div>

    <!-- ── Comments section ──────────────────────────────────────────── -->
    <div class="video-play-comments">
        <h3 class="video-comments-heading">Comments</h3>

        <!-- Comment form -->
        <form class="video-comment-form" id="video-comment-form">
            <?= csrf_field() ?>
            <input type="hidden" name="media_id" value="<?= $mediaId ?>">
            <input type="text" name="content"
                   class="form-control video-comment-input mention-input"
                   placeholder="Write a comment…"
                   maxlength="1000"
                   autocomplete="off"
                   aria-label="Comment text">
            <button type="submit" class="btn btn-primary btn-sm">Post</button>
        </form>

        <!-- Comments list -->
        <div id="video-comments-list" class="video-comments-list">
            <?php if (empty($comments)): ?>
            <p class="video-comments-empty muted">No comments yet. Be the first!</p>
            <?php else: ?>
            <?php foreach ($comments as $c): ?>
            <div class="video-comment-item">
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$c['user_id']) ?>"
                   class="video-comment-avatar-link">
                    <img src="<?= e(avatar_url($c, 'small')) ?>"
                         alt="<?= e($c['username']) ?>"
                         width="32" height="32"
                         class="avatar avatar-small">
                </a>
                <div class="video-comment-body">
                    <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$c['user_id']) ?>"
                       class="video-comment-author"><?= e($c['username']) ?></a>
                    <span class="muted video-comment-time"><?= e(time_ago($c['created_at'])) ?></span>
                    <p class="video-comment-text"><?= nl2br(linkify(smilify($c['content']))) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.video-play-wrap -->

    </main>

</div><!-- /.two-col-layout -->

<?php include SITE_ROOT . '/includes/footer.php'; ?>
