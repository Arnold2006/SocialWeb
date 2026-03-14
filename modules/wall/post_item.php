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
 * post_item.php — Renders a single wall post.
 *
 * Expected variables:
 *   $post  — post row with joined user fields
 *   $user  — current logged-in user
 */

declare(strict_types=1);

$postMedia = null;
if (!empty($post['media_id'])) {
    $postMedia = db_row('SELECT * FROM media WHERE id = ? AND is_deleted = 0', [(int)$post['media_id']]);
}

$postComments = db_query(
    'SELECT c.*, u.username, u.avatar_path
     FROM comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.post_id = ? AND c.is_deleted = 0
     ORDER BY c.created_at ASC
     LIMIT 3',
    [(int)$post['id']]
);
$moreComments = (int)$post['comment_count'] > 3;
?>
<article class="post-item" id="post-<?= (int)$post['id'] ?>">
    <div class="post-header">
        <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$post['user_id']) ?>">
            <img src="<?= e(avatar_url($post, 'small')) ?>"
                 alt="<?= e($post['username']) ?>"
                 class="avatar avatar-small" width="40" height="40" loading="lazy">
        </a>
        <div class="post-meta">
            <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$post['user_id']) ?>" class="post-author">
                <?= e($post['username']) ?>
            </a>
            <time class="post-time" datetime="<?= e($post['created_at']) ?>">
                <?= e(time_ago($post['created_at'])) ?>
            </time>
        </div>
        <?php if ((int)$user['id'] === (int)$post['user_id'] || is_admin()): ?>
        <div class="post-actions-menu">
            <form method="POST" action="<?= e(SITE_URL . '/modules/wall/delete_post.php') ?>"
                  class="inline-form"
                  onsubmit="return confirm('Delete this post?')">
                <?= csrf_field() ?>
                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                <button type="submit" class="btn btn-danger btn-xs">Delete</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="post-content">
        <?= nl2br(linkify($post['content'])) ?>
        <?php if (($post['post_type'] ?? 'user') === 'album_upload' && !empty($post['album_id'])): ?>
        <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$post['user_id'] . '&album=' . (int)$post['album_id']) ?>"
           class="post-album-link">View Album →</a>
        <?php endif; ?>
        <?php if (($post['post_type'] ?? 'user') === 'blog_post' && !empty($post['blog_post_id'])): ?>
        <a href="<?= e(SITE_URL . '/pages/blog.php?user_id=' . (int)$post['user_id'] . '#blog-post-' . (int)$post['blog_post_id']) ?>"
           class="post-blog-link">Read post →</a>
        <?php endif; ?>
    </div>

    <?php if ($postMedia): ?>
    <div class="post-media">
        <?php if ($postMedia['type'] === 'image'): ?>
        <a href="<?= e(get_media_url($postMedia, 'original')) ?>" class="lightbox-trigger"
           data-src="<?= e(get_media_url($postMedia, 'large')) ?>"
           data-caption="<?= e($post['username']) ?>">
            <img src="<?= e(get_media_url($postMedia, 'thumb')) ?>"
                 data-src="<?= e(get_media_url($postMedia, 'medium')) ?>"
                 alt="Post image"
                 class="post-img lazy-image"
                 loading="lazy">
        </a>
        <?php elseif ($postMedia['type'] === 'video'): ?>
        <video controls class="post-video" preload="metadata">
            <source src="<?= e(get_media_url($postMedia, 'original')) ?>" type="video/mp4">
        </video>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="post-footer">
        <button class="btn-like <?= $post['user_liked'] > 0 ? 'liked' : '' ?>"
                data-post-id="<?= (int)$post['id'] ?>">
            ♥ <span class="like-count"><?= (int)$post['like_count'] ?></span>
        </button>

        <button class="btn-comment" data-post-id="<?= (int)$post['id'] ?>">
            💬 <?= (int)$post['comment_count'] ?>
        </button>
    </div>

    <!-- Comments section -->
    <div class="comments-section" id="comments-<?= (int)$post['id'] ?>">
        <?php foreach ($postComments as $comment): ?>
        <div class="comment-item" id="comment-<?= (int)$comment['id'] ?>">
            <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$comment['user_id']) ?>">
                <img src="<?= e(avatar_url($comment, 'small')) ?>"
                     alt="<?= e($comment['username']) ?>"
                     class="avatar avatar-small" width="28" height="28" loading="lazy">
            </a>
            <div class="comment-body">
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$comment['user_id']) ?>"
                   class="comment-author"><?= e($comment['username']) ?></a>
                <span class="comment-time"><?= e(time_ago($comment['created_at'])) ?></span>
                <p class="comment-text"><?= nl2br(linkify($comment['content'])) ?></p>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($moreComments): ?>
        <a href="#" class="load-more-comments" data-post-id="<?= (int)$post['id'] ?>">
            View all <?= (int)$post['comment_count'] ?> comments
        </a>
        <?php endif; ?>

        <form class="comment-form" data-post-id="<?= (int)$post['id'] ?>">
            <?= csrf_field() ?>
            <input type="text" name="content" placeholder="Write a comment…" maxlength="1000" required>
            <button type="submit" class="btn btn-sm">Post</button>
        </form>
    </div>
</article>
