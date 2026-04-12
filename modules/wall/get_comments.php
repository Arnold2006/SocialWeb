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
 * get_comments.php — Fetch all comments for a wall post (AJAX)
 *
 * GET params:
 *   post_id  int  Wall post ID
 *
 * Returns JSON:
 *   { ok: true,  comments: [ { id, user_id, username, avatar, content, time_ago, profile_url }, … ] }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$user = json_api_guard('GET');

$postId = sanitise_int($_GET['post_id'] ?? 0);

if ($postId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid post_id']);
    exit;
}

$post = db_row('SELECT id, media_id, post_type, media_ids FROM posts WHERE id = ? AND is_deleted = 0', [$postId]);
if ($post === null) {
    echo json_encode(['ok' => false, 'error' => 'Post not found']);
    exit;
}

$postMediaId = !empty($post['media_id']) ? (int)$post['media_id'] : null;

// For album_upload posts, collect all associated media IDs for comment merging.
$albumMediaIds = [];
if (($post['post_type'] ?? 'user') === 'album_upload' && !empty($post['media_ids'])) {
    $decoded = json_decode($post['media_ids'], true);
    if (is_array($decoded)) {
        $albumMediaIds = array_slice(array_values(array_filter(array_map('intval', $decoded))), 0, 100);
    }
}

if ($postMediaId !== null) {
    $comments = db_query(
        'SELECT c.id, c.user_id, c.content, c.created_at, u.username, u.avatar_path
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.post_id = ? AND c.is_deleted = 0
         UNION
         SELECT c.id, c.user_id, c.content, c.created_at, u.username, u.avatar_path
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.media_id = ? AND c.is_deleted = 0
         ORDER BY created_at ASC',
        [$postId, $postMediaId]
    );
} elseif (!empty($albumMediaIds)) {
    $placeholders = implode(',', array_fill(0, count($albumMediaIds), '?'));
    $comments = db_query(
        "SELECT c.id, c.user_id, c.content, c.created_at, u.username, u.avatar_path
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE (c.post_id = ? OR c.media_id IN ($placeholders)) AND c.is_deleted = 0
         ORDER BY c.created_at ASC",
        array_merge([$postId], $albumMediaIds)
    );
} else {
    $comments = db_query(
        'SELECT c.id, c.user_id, c.content, c.created_at, u.username, u.avatar_path
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.post_id = ? AND c.is_deleted = 0
         ORDER BY c.created_at ASC',
        [$postId]
    );
}

$result = [];
foreach ($comments as $comment) {
    $result[] = [
        'id'          => (int)$comment['id'],
        'user_id'     => (int)$comment['user_id'],
        'username'    => $comment['username'],
        'avatar'      => avatar_url($comment, 'small'),
        'content'     => $comment['content'],
        'time_ago'    => time_ago($comment['created_at']),
        'profile_url' => SITE_URL . '/pages/profile.php?id=' . (int)$comment['user_id'],
    ];
}

echo json_encode(['ok' => true, 'comments' => $result]);
