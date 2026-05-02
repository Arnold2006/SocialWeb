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
 * get_comments.php — Fetch all comments for a blog post (AJAX)
 *
 * GET params:
 *   blog_post_id  int  Blog post ID
 *
 * Returns JSON:
 *   { ok: true,  comments: [ { id, user_id, username, avatar, content, time_ago, profile_url }, … ] }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$user = json_api_guard('GET');

$blogPostId = sanitise_int($_GET['blog_post_id'] ?? 0);

if ($blogPostId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid blog_post_id']);
    exit;
}

$blogPost = db_row('SELECT id FROM blog_posts WHERE id = ? AND is_deleted = 0', [$blogPostId]);
if ($blogPost === null) {
    echo json_encode(['ok' => false, 'error' => 'Blog post not found']);
    exit;
}

$comments = db_query(
    'SELECT c.id, c.user_id, c.content, c.created_at, c.updated_at, c.image_media_id,
            u.username, u.avatar_path,
            m.thumb_path AS img_thumb, m.medium_path AS img_medium, m.large_path AS img_large,
            m.storage_path AS img_original
     FROM comments c
     JOIN users u ON u.id = c.user_id
     LEFT JOIN media m ON m.id = c.image_media_id AND m.is_deleted = 0
     WHERE c.blog_post_id = ? AND c.is_deleted = 0
     ORDER BY c.created_at ASC',
    [$blogPostId]
);

$result = [];
foreach ($comments as $comment) {
    $imgMedia = !empty($comment['image_media_id']) ? [
        'thumb_path'   => $comment['img_thumb'],
        'medium_path'  => $comment['img_medium'],
        'large_path'   => $comment['img_large'],
        'storage_path' => $comment['img_original'],
    ] : null;
    $result[] = [
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

echo json_encode(['ok' => true, 'comments' => $result]);
