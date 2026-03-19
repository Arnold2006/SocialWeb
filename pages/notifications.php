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

// Mark all as read
db_exec(
    'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0',
    [(int)$currentUser['id']]
);

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
            <?php switch ($n['type']):
                case 'like': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> liked your post.</p>
                <?php if ($n['ref_id']): ?>
                <a href="<?= e(SITE_URL . '/pages/index.php#post-' . (int)$n['ref_id']) ?>">View post</a>
                <?php endif; ?>
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

                case 'comment': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> commented on your post.</p>
                <?php if ($n['ref_id']): ?>
                <a href="<?= e(SITE_URL . '/pages/index.php#post-' . (int)$n['ref_id']) ?>">View post</a>
                <?php endif; ?>
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
                    $blogCommentRow = db_row(
                        'SELECT c.blog_post_id, bp.user_id AS blog_owner_id
                         FROM comments c
                         JOIN blog_posts bp ON bp.id = c.blog_post_id
                         WHERE c.id = ? AND c.is_deleted = 0 AND bp.is_deleted = 0',
                        [(int)$n['ref_id']]
                    );
                    if ($blogCommentRow):
                ?>
                <a href="<?= e(SITE_URL . '/pages/blog.php?user_id=' . (int)$blogCommentRow['blog_owner_id'] . '#comment-' . (int)$n['ref_id']) ?>">View comment</a>
                <?php   endif;
                endif; ?>
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

<?php include SITE_ROOT . '/includes/footer.php'; ?>
