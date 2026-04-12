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

$post = db_row('SELECT id, media_id FROM posts WHERE id = ? AND is_deleted = 0', [$postId]);
if ($post === null) {
    echo json_encode(['ok' => false, 'error' => 'Post not found']);
    exit;
}

$postMediaId = !empty($post['media_id']) ? (int)$post['media_id'] : null;

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
