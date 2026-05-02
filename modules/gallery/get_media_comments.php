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
 * get_media_comments.php — Fetch comments and like state for a media item (AJAX)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user    = json_api_guard('GET');
$mediaId = sanitise_int($_GET['media_id'] ?? 0);

if ($mediaId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid media']);
    exit;
}

$media = db_row('SELECT id FROM media WHERE id = ? AND is_deleted = 0', [$mediaId]);
if ($media === null) {
    echo json_encode(['ok' => false, 'error' => 'Media not found']);
    exit;
}

// Find any wall post linked to this media item so its engagement can be merged.
$linkedPost = db_row(
    'SELECT id FROM posts WHERE media_id = ? AND is_deleted = 0 ORDER BY id ASC LIMIT 1',
    [$mediaId]
);
$linkedPostId = $linkedPost ? (int)$linkedPost['id'] : null;

// Merge wall-post comments if a linked post exists.
if ($linkedPostId !== null) {
    $comments = db_query(
        'SELECT c.id, c.content, c.created_at, c.updated_at, c.image_media_id,
                u.id AS user_id, u.username, u.avatar_path,
                m.thumb_path AS img_thumb, m.medium_path AS img_medium,
                m.large_path AS img_large, m.storage_path AS img_original
         FROM comments c
         JOIN users u ON u.id = c.user_id
         LEFT JOIN media m ON m.id = c.image_media_id AND m.is_deleted = 0
         WHERE c.media_id = ? AND c.is_deleted = 0
         UNION
         SELECT c.id, c.content, c.created_at, c.updated_at, c.image_media_id,
                u.id AS user_id, u.username, u.avatar_path,
                m.thumb_path AS img_thumb, m.medium_path AS img_medium,
                m.large_path AS img_large, m.storage_path AS img_original
         FROM comments c
         JOIN users u ON u.id = c.user_id
         LEFT JOIN media m ON m.id = c.image_media_id AND m.is_deleted = 0
         WHERE c.post_id = ? AND c.is_deleted = 0
         ORDER BY created_at ASC',
        [$mediaId, $linkedPostId]
    );
} else {
    $comments = db_query(
        'SELECT c.id, c.content, c.created_at, c.updated_at, c.image_media_id,
                u.id AS user_id, u.username, u.avatar_path,
                m.thumb_path AS img_thumb, m.medium_path AS img_medium,
                m.large_path AS img_large, m.storage_path AS img_original
         FROM comments c
         JOIN users u ON u.id = c.user_id
         LEFT JOIN media m ON m.id = c.image_media_id AND m.is_deleted = 0
         WHERE c.media_id = ? AND c.is_deleted = 0
         ORDER BY c.created_at ASC',
        [$mediaId]
    );
}

$likeCount = (int) db_val('SELECT COUNT(*) FROM likes WHERE media_id = ?', [$mediaId]);

// Add wall-post likes to the count.
if ($linkedPostId !== null) {
    $likeCount += (int) db_val('SELECT COUNT(*) FROM likes WHERE post_id = ?', [$linkedPostId]);
}

$userLiked = (int) db_val(
    'SELECT COUNT(*) FROM likes WHERE user_id = ? AND media_id = ?',
    [(int)$user['id'], $mediaId]
) > 0;

// Also check if the user liked the linked wall post.
if (!$userLiked && $linkedPostId !== null) {
    $userLiked = (int) db_val(
        'SELECT COUNT(*) FROM likes WHERE user_id = ? AND post_id = ?',
        [(int)$user['id'], $linkedPostId]
    ) > 0;
}

$commentData = [];
foreach ($comments as $comment) {
    $imgMedia = !empty($comment['image_media_id']) ? [
        'thumb_path'   => $comment['img_thumb'],
        'medium_path'  => $comment['img_medium'],
        'large_path'   => $comment['img_large'],
        'storage_path' => $comment['img_original'],
    ] : null;
    $commentData[] = [
        'id'               => (int)$comment['id'],
        'user_id'          => (int)$comment['user_id'],
        'username'         => $comment['username'],
        'avatar'           => avatar_url($comment, 'small'),
        'content'          => $comment['content'],
        'content_html'     => nl2br(linkify(smilify($comment['content']))),
        'edited'           => !empty($comment['updated_at']),
        'time_ago'         => time_ago($comment['created_at']),
        'profile_url'      => SITE_URL . '/pages/profile.php?id=' . (int)$comment['user_id'],
        'image_media_id'   => $imgMedia ? (int)$comment['image_media_id'] : null,
        'image_thumb_url'  => $imgMedia ? get_media_url($imgMedia, 'thumb')  : null,
        'image_medium_url' => $imgMedia ? get_media_url($imgMedia, 'medium') : null,
        'image_large_url'  => $imgMedia ? get_media_url($imgMedia, 'large')  : null,
    ];
}

echo json_encode([
    'ok'         => true,
    'comments'   => $commentData,
    'like_count' => $likeCount,
    'user_liked' => $userLiked,
]);
