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

// For album_upload posts: load up to 4 preview media items from stored IDs
$albumPreviewMedia = [];
if (($post['post_type'] ?? 'user') === 'album_upload' && !empty($post['media_ids'])) {
    $previewIds = json_decode($post['media_ids'], true);
    if (is_array($previewIds) && !empty($previewIds)) {
        $previewIds = array_slice(array_map('intval', $previewIds), 0, 4);
        $placeholders = implode(',', array_fill(0, count($previewIds), '?'));
        $albumPreviewMedia = db_query(
            "SELECT id, type, storage_path, large_path, medium_path, thumb_path, thumbnail_path
             FROM media WHERE id IN ($placeholders) AND is_deleted = 0 ORDER BY id ASC",
            $previewIds
        );
    }
}

$postId      = (int)$post['id'];
$postMediaId = !empty($post['media_id']) ? (int)$post['media_id'] : null;
$viewerId    = (int)$user['id'];

if ($postMediaId !== null) {
    $postComments = db_query(
        'SELECT c.id, c.user_id, c.content, c.created_at, c.updated_at, c.image_media_id,
                u.username, u.avatar_path,
                m.thumb_path AS img_thumb, m.medium_path AS img_medium,
                m.large_path AS img_large, m.storage_path AS img_original,
                COUNT(lk.id) AS like_count,
                MAX(CASE WHEN lk.user_id = ? THEN 1 ELSE 0 END) AS user_liked
         FROM comments c
         JOIN users u ON u.id = c.user_id
         LEFT JOIN media m ON m.id = c.image_media_id AND m.is_deleted = 0
         LEFT JOIN likes lk ON lk.comment_id = c.id
         WHERE c.post_id = ? AND c.is_deleted = 0
         GROUP BY c.id
         UNION
         SELECT c.id, c.user_id, c.content, c.created_at, c.updated_at, c.image_media_id,
                u.username, u.avatar_path,
                m.thumb_path AS img_thumb, m.medium_path AS img_medium,
                m.large_path AS img_large, m.storage_path AS img_original,
                COUNT(lk.id) AS like_count,
                MAX(CASE WHEN lk.user_id = ? THEN 1 ELSE 0 END) AS user_liked
         FROM comments c
         JOIN users u ON u.id = c.user_id
         LEFT JOIN media m ON m.id = c.image_media_id AND m.is_deleted = 0
         LEFT JOIN likes lk ON lk.comment_id = c.id
         WHERE c.media_id = ? AND c.is_deleted = 0
         GROUP BY c.id
         ORDER BY created_at ASC
         LIMIT 3',
        [$viewerId, $postId, $viewerId, $postMediaId]
    );
} else {
    $postComments = db_query(
        'SELECT c.id, c.user_id, c.content, c.created_at, c.updated_at, c.image_media_id,
                u.username, u.avatar_path,
                m.thumb_path AS img_thumb, m.medium_path AS img_medium,
                m.large_path AS img_large, m.storage_path AS img_original,
                COUNT(lk.id) AS like_count,
                MAX(CASE WHEN lk.user_id = ? THEN 1 ELSE 0 END) AS user_liked
         FROM comments c
         JOIN users u ON u.id = c.user_id
         LEFT JOIN media m ON m.id = c.image_media_id AND m.is_deleted = 0
         LEFT JOIN likes lk ON lk.comment_id = c.id
         WHERE c.post_id = ? AND c.is_deleted = 0
         GROUP BY c.id
         ORDER BY c.created_at ASC
         LIMIT 3',
        [$viewerId, $postId]
    );
}
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
        <?php if ((int)$user['id'] === (int)$post['user_id']): ?>
        <div class="post-actions-menu">
            <form method="POST" action="<?= e(SITE_URL . '/modules/wall/delete_post.php') ?>"
                  class="inline-form"
                  onsubmit="return confirm('Delete this post?')">
                <?= csrf_field() ?>
                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                <button type="submit" class="btn btn-danger btn-xs">Delete</button>
            </form>
            <?php if ((int)$user['id'] === (int)$post['user_id'] && $postMedia && $postMedia['type'] === 'image'): ?>
            <button type="button" class="btn btn-xs btn-secondary move-media-btn"
                    data-media-id="<?= (int)$postMedia['id'] ?>">&#8599; Move</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="post-content">
        <?= nl2br(linkify(smilify($post['content']))) ?>
        <?php if (($post['post_type'] ?? 'user') === 'album_upload' && !empty($post['album_id'])): ?>
        <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$post['user_id'] . '&album=' . (int)$post['album_id']) ?>"
           class="post-album-link">View Album →</a>
        <?php endif; ?>
        <?php if (!empty($albumPreviewMedia)): ?>
        <div class="post-album-thumbs">
        <?php foreach ($albumPreviewMedia as $previewItem): ?>
            <?php if ($previewItem['type'] === 'video'): ?>
            <a href="<?= e(SITE_URL . '/pages/video_play.php?id=' . (int)$previewItem['id']) ?>"
               class="video-thumb-wrap">
                <?php
                $vThumbUrl = !empty($previewItem['thumbnail_path'])
                    ? e(get_media_url($previewItem, 'thumbnail'))
                    : e(SITE_URL . '/assets/images/placeholder.svg');
                ?>
                <img src="<?= $vThumbUrl ?>"
                     alt="Video"
                     class="album-thumb"
                     width="70" height="70"
                     loading="lazy">
                <span class="video-play-icon" aria-hidden="true">&#9654;</span>
            </a>
            <?php else: ?>
            <a href="<?= e(get_media_url($previewItem, 'original')) ?>"
               class="lightbox-trigger"
               data-src="<?= e(get_media_url($previewItem, 'large')) ?>"
               data-media-id="<?= (int)$previewItem['id'] ?>"
               data-caption="<?= e($post['username']) ?>">
                <img src="<?= e(get_media_url($previewItem, 'thumb')) ?>"
                     alt="<?= e($post['username']) ?>"
                     class="album-thumb"
                     width="70" height="70"
                     loading="lazy">
            </a>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
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
                <?php if ($comment['updated_at']): ?><span class="comment-edited">(edited)</span><?php endif; ?>
                <?php if ((int)$comment['user_id'] === (int)$user['id']): ?>
                <button type="button" class="comment-edit-btn btn btn-xs btn-secondary"
                        data-comment-id="<?= (int)$comment['id'] ?>">Edit</button>
                <?php endif; ?>
                <p class="comment-text" data-raw="<?= e($comment['content']) ?>"><?= nl2br(linkify(smilify($comment['content']))) ?></p>
                <?php if (!empty($comment['image_media_id'])): ?>
                <?php
                $commentImgMedia = [
                    'thumb_path'   => $comment['img_thumb'],
                    'medium_path'  => $comment['img_medium'],
                    'large_path'   => $comment['img_large'],
                    'storage_path' => $comment['img_original'],
                ];
                ?>
                <a href="<?= e(get_media_url($commentImgMedia, 'original')) ?>"
                   class="lightbox-trigger comment-image-trigger"
                   data-src="<?= e(get_media_url($commentImgMedia, 'large')) ?>">
                    <img src="<?= e(get_media_url($commentImgMedia, 'thumb')) ?>"
                         alt="comment image"
                         class="comment-attached-image"
                         loading="lazy">
                </a>
                <?php endif; ?>
                <div class="comment-footer">
                    <button class="btn-like-comment <?= (int)$comment['user_liked'] > 0 ? 'liked' : '' ?>"
                            data-comment-id="<?= (int)$comment['id'] ?>">
                        ♥ <span class="like-count"><?= (int)$comment['like_count'] ?></span>
                    </button>
                </div>
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
            <input type="text" name="content" placeholder="Write a comment…" maxlength="1000" autocomplete="off" required class="mention-input">
            <button type="button" class="btn btn-sm btn-secondary comment-attach-image-btn" title="Attach image">📷</button>
            <button type="submit" class="btn btn-sm">Post</button>
            <div class="comment-image-preview" style="display:none"></div>
        </form>
    </div>
</article>
