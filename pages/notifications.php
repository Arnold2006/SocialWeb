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
 * notifications.php — Notifications page
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pageTitle   = 'Notifications';
$currentUser = current_user();

// Fetch notifications BEFORE marking as read so the 'unread' CSS class renders correctly.
$notifications = db_query(
    'SELECT n.*, u.username AS from_username, u.avatar_path AS from_avatar
     FROM notifications n
     LEFT JOIN users u ON u.id = n.from_user_id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC
     LIMIT 50',
    [(int)$currentUser['id']]
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

<input type="hidden" id="notif-csrf" value="<?= e(csrf_token()) ?>">

<div class="page-header">
    <h1>Notifications</h1>
    <?php if (!empty($notifications)): ?>
    <button id="clear-all-notifs" class="btn btn-secondary btn-sm">Clear all</button>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
<p class="empty-state" id="notif-empty-state">No notifications yet.</p>
<?php else: ?>
<div class="notifications-list" id="notifications-list">
    <?php foreach ($notifications as $n): ?>
    <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" id="notif-<?= (int)$n['id'] ?>">
        <?php if ($n['from_avatar']): ?>
        <img src="<?= e(avatar_url(['avatar_path' => $n['from_avatar']], 'small')) ?>"
             alt="" class="avatar avatar-small" width="36" height="36" loading="lazy">
        <?php endif; ?>
        <div class="notif-body">
            <?php
            // secondary_ref_id may not exist in DB before migration 039 runs
            $secondaryRefId = ($n['secondary_ref_id'] ?? null) !== null ? (int)$n['secondary_ref_id'] : null;

            switch ($n['type']):
                case 'like': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> liked your post.</p>
                <?php if ($n['ref_id']): ?>
                <a href="<?= e(SITE_URL . '/pages/index.php?goto_post=' . (int)$n['ref_id']) ?>">View post</a>
                <?php endif; ?>
                <?php break;

                case 'comment': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> commented on your post.</p>
                <?php if ($n['ref_id']):
                    if ($secondaryRefId !== null):
                        // New format: ref_id = post_id, secondary_ref_id = comment_id
                ?>
                <a href="<?= e(SITE_URL . '/pages/index.php?goto_post=' . (int)$n['ref_id'] . '&goto_comment=' . $secondaryRefId) ?>">View comment</a>
                <?php   else:
                        // Legacy format: ref_id = comment_id
                        $commentPostRow = db_row(
                            'SELECT c.post_id FROM comments c WHERE c.id = ? AND c.is_deleted = 0',
                            [(int)$n['ref_id']]
                        );
                        if ($commentPostRow && $commentPostRow['post_id']):
                ?>
                <a href="<?= e(SITE_URL . '/pages/index.php?goto_post=' . (int)$commentPostRow['post_id'] . '&goto_comment=' . (int)$n['ref_id']) ?>">View comment</a>
                <?php   endif;
                    endif;
                endif; ?>
                <?php break;

                case 'comment_like': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> liked your comment.</p>
                <?php if ($n['ref_id']):
                    $likedCommentRow = db_row(
                        'SELECT c.post_id, c.blog_post_id, c.media_id
                         FROM comments c WHERE c.id = ? AND c.is_deleted = 0',
                        [(int)$n['ref_id']]
                    );
                    if ($likedCommentRow):
                        if ($likedCommentRow['post_id']): ?>
                <a href="<?= e(SITE_URL . '/pages/index.php?goto_post=' . (int)$likedCommentRow['post_id'] . '&goto_comment=' . (int)$n['ref_id']) ?>">View comment</a>
                <?php       elseif ($likedCommentRow['blog_post_id']):
                            $likedCommentBlogPost = db_row(
                                'SELECT user_id FROM blog_posts WHERE id = ? AND is_deleted = 0',
                                [(int)$likedCommentRow['blog_post_id']]
                            );
                            if ($likedCommentBlogPost): ?>
                <a href="<?= e(SITE_URL . '/pages/blog.php?user_id=' . (int)$likedCommentBlogPost['user_id'] . '&post_id=' . (int)$likedCommentRow['blog_post_id']) ?>">View comment</a>
                <?php       endif;
                        elseif ($likedCommentRow['media_id']):
                            $likedCommentMedia = db_row(
                                'SELECT user_id, album_id FROM media WHERE id = ? AND is_deleted = 0',
                                [(int)$likedCommentRow['media_id']]
                            );
                            if ($likedCommentMedia && $likedCommentMedia['album_id'] !== null): ?>
                <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$likedCommentMedia['user_id'] . '&album=' . (int)$likedCommentMedia['album_id'] . '&photo=' . (int)$likedCommentRow['media_id']) ?>">View comment</a>
                <?php       endif;
                        endif;
                    endif;
                endif; ?>
                <?php break;

                case 'photo_like': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> liked your photo.</p>
                <?php if ($n['ref_id']):
                    $photoRow = db_row(
                        'SELECT user_id, album_id FROM media WHERE id = ? AND is_deleted = 0',
                        [(int)$n['ref_id']]
                    );
                    if ($photoRow && $photoRow['album_id'] !== null):
                ?>
                <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$photoRow['user_id'] . '&album=' . (int)$photoRow['album_id'] . '&photo=' . (int)$n['ref_id']) ?>">View photo</a>
                <?php   endif;
                endif; ?>
                <?php break;

                case 'blog_like': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> liked your blog post.</p>
                <?php if ($n['ref_id']):
                    $blogLikePost = db_row(
                        'SELECT user_id FROM blog_posts WHERE id = ? AND is_deleted = 0',
                        [(int)$n['ref_id']]
                    );
                    if ($blogLikePost):
                ?>
                <a href="<?= e(SITE_URL . '/pages/blog.php?user_id=' . (int)$blogLikePost['user_id'] . '&post_id=' . (int)$n['ref_id']) ?>">View blog post</a>
                <?php   endif;
                endif; ?>
                <?php break;

                case 'message': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> sent you a message.</p>
                <?php if ($n['from_user_id']): ?>
                <button type="button" class="notif-chat-btn"
                        data-chat-user-id="<?= (int)$n['from_user_id'] ?>"
                        data-chat-username="<?= e($n['from_username'] ?? '') ?>"
                        data-chat-avatar="<?= e(avatar_url(['avatar_path' => $n['from_avatar'] ?? null])) ?>">Open chat</button>
                <?php endif; ?>
                <?php break;

                case 'mail_message': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> sent you a private message.</p>
                <a href="<?= e(SITE_URL . '/pages/messages.php') ?>">View messages</a>
                <?php break;

                case 'photo_comment': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> commented on your photo.</p>
                <?php if ($n['ref_id']):
                    $photoCommentRow = db_row(
                        'SELECT user_id, album_id FROM media WHERE id = ? AND is_deleted = 0',
                        [(int)$n['ref_id']]
                    );
                    if ($photoCommentRow && $photoCommentRow['album_id'] !== null):
                ?>
                <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$photoCommentRow['user_id'] . '&album=' . (int)$photoCommentRow['album_id'] . '&photo=' . (int)$n['ref_id']) ?>">View photo</a>
                <?php   endif;
                endif; ?>
                <?php break;

                case 'blog_comment': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> commented on your blog post.</p>
                <?php if ($n['ref_id']):
                    if ($secondaryRefId !== null):
                        // New format: ref_id = blog_post_id, secondary_ref_id = comment_id
                        $blogCommentPost = db_row(
                            'SELECT user_id FROM blog_posts WHERE id = ? AND is_deleted = 0',
                            [(int)$n['ref_id']]
                        );
                        if ($blogCommentPost):
                ?>
                <a href="<?= e(SITE_URL . '/pages/blog.php?user_id=' . (int)$blogCommentPost['user_id'] . '&post_id=' . (int)$n['ref_id']) ?>">View blog post</a>
                <?php   endif;
                    else:
                        // Legacy format: ref_id = comment_id
                        $blogCommentRow = db_row(
                            'SELECT c.blog_post_id, bp.user_id AS blog_owner_id
                             FROM comments c
                             JOIN blog_posts bp ON bp.id = c.blog_post_id
                             WHERE c.id = ? AND c.is_deleted = 0 AND bp.is_deleted = 0',
                            [(int)$n['ref_id']]
                        );
                        if ($blogCommentRow):
                ?>
                <a href="<?= e(SITE_URL . '/pages/blog.php?user_id=' . (int)$blogCommentRow['blog_owner_id'] . '&post_id=' . (int)$blogCommentRow['blog_post_id']) ?>">View blog post</a>
                <?php   endif;
                    endif;
                endif; ?>
                <?php break;

                case 'mention_comment': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> mentioned you in a comment.</p>
                <?php if ($n['ref_id']):
                    if ($secondaryRefId !== null):
                        // New format: ref_id = post_id, secondary_ref_id = comment_id
                ?>
                <a href="<?= e(SITE_URL . '/pages/index.php?goto_post=' . (int)$n['ref_id'] . '&goto_comment=' . $secondaryRefId) ?>">View comment</a>
                <?php   else:
                        // Legacy format: ref_id = comment_id
                        $mentionCommentRow = db_row(
                            'SELECT c.post_id FROM comments c WHERE c.id = ? AND c.is_deleted = 0',
                            [(int)$n['ref_id']]
                        );
                        if ($mentionCommentRow && $mentionCommentRow['post_id']):
                ?>
                <a href="<?= e(SITE_URL . '/pages/index.php?goto_post=' . (int)$mentionCommentRow['post_id'] . '&goto_comment=' . (int)$n['ref_id']) ?>">View comment</a>
                <?php   endif;
                    endif;
                endif; ?>
                <?php break;

                case 'mention_comment_blog': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> mentioned you in a blog comment.</p>
                <?php if ($n['ref_id']):
                    $mentionBlogPost = db_row(
                        'SELECT user_id FROM blog_posts WHERE id = ? AND is_deleted = 0',
                        [(int)$n['ref_id']]
                    );
                    if ($mentionBlogPost):
                ?>
                <a href="<?= e(SITE_URL . '/pages/blog.php?user_id=' . (int)$mentionBlogPost['user_id'] . '&post_id=' . (int)$n['ref_id']) ?>">View blog post</a>
                <?php   endif;
                endif; ?>
                <?php break;

                case 'mention_comment_photo': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> mentioned you in a photo comment.</p>
                <?php if ($n['ref_id']):
                    $mentionPhotoMedia = db_row(
                        'SELECT user_id, album_id FROM media WHERE id = ? AND is_deleted = 0',
                        [(int)$n['ref_id']]
                    );
                    if ($mentionPhotoMedia && $mentionPhotoMedia['album_id'] !== null):
                ?>
                <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$mentionPhotoMedia['user_id'] . '&album=' . (int)$mentionPhotoMedia['album_id'] . '&photo=' . (int)$n['ref_id']) ?>">View photo</a>
                <?php   endif;
                endif; ?>
                <?php break;

                case 'mention':
                case 'mention_post': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong>
                    mentioned you in a <?= $n['type'] === 'mention_post' ? 'post' : 'comment' ?>.</p>
                <?php if ($n['ref_id']): ?>
                <a href="<?= e(SITE_URL . '/pages/index.php?goto_post=' . (int)$n['ref_id']) ?>">View post</a>
                <?php endif; ?>
                <?php break;

                case 'friend_request': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> sent you a friend request.</p>
                <a href="<?= e(SITE_URL . '/pages/friends.php') ?>">View requests</a>
                <?php break;

                case 'friend_accept': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> accepted your friend request.</p>
                <?php if ($n['from_user_id']): ?>
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$n['from_user_id']) ?>">View profile</a>
                <?php endif; ?>
                <?php break;
            endswitch; ?>
        </div>
        <time class="notif-time"><?= e(time_ago($n['created_at'])) ?></time>
        <button class="notif-delete-btn btn btn-danger btn-xs"
                data-id="<?= (int)$n['id'] ?>"
                title="Delete notification">✕</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

    </main>

</div><!-- /.two-col-layout -->

<?php
// Mark all non-mail notifications as read after rendering, so the 'unread' CSS
// class is visible to the user for the duration of this page view.
// mail_message rows are excluded: they should only be considered read when the
// user actually opens the message thread.
db_exec(
    "UPDATE notifications SET is_read = 1
     WHERE user_id = ? AND is_read = 0 AND type != 'mail_message'",
    [(int)$currentUser['id']]
);

include SITE_ROOT . '/includes/footer.php';
?>
