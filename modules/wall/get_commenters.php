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
 * get_commenters.php — Return the list of users who commented on a post (AJAX)
 *
 * GET params:
 *   post_id  int  Wall post ID
 *
 * Returns JSON:
 *   { ok: true,  users: [ "alice", "bob", … ], total: N }
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
    $total = (int) db_val(
        'SELECT COUNT(DISTINCT u.id)
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE (c.post_id = ? OR c.media_id = ?) AND c.is_deleted = 0',
        [$postId, $postMediaId]
    );

    $rows = db_query(
        'SELECT DISTINCT u.username, MIN(c.id) AS first_comment_id
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE (c.post_id = ? OR c.media_id = ?) AND c.is_deleted = 0
         GROUP BY u.id, u.username
         ORDER BY first_comment_id ASC
         LIMIT 10',
        [$postId, $postMediaId]
    );
} else {
    $total = (int) db_val(
        'SELECT COUNT(DISTINCT user_id) FROM comments WHERE post_id = ? AND is_deleted = 0',
        [$postId]
    );

    $rows = db_query(
        'SELECT DISTINCT u.username, MIN(c.id) AS first_comment_id
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.post_id = ? AND c.is_deleted = 0
         GROUP BY u.id, u.username
         ORDER BY first_comment_id ASC
         LIMIT 10',
        [$postId]
    );
}

$usernames = array_column($rows, 'username');

echo json_encode(['ok' => true, 'users' => $usernames, 'total' => $total]);
