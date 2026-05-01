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
 * get_likers.php — Return the list of users who liked a post (AJAX)
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

$post = db_row('SELECT id FROM posts WHERE id = ? AND is_deleted = 0', [$postId]);
if ($post === null) {
    echo json_encode(['ok' => false, 'error' => 'Post not found']);
    exit;
}

$total = (int) db_val('SELECT COUNT(*) FROM likes WHERE post_id = ?', [$postId]);

$rows  = db_query(
    'SELECT u.username
     FROM likes l
     JOIN users u ON u.id = l.user_id
     WHERE l.post_id = ?
     ORDER BY l.id DESC
     LIMIT 10',
    [$postId]
);

$usernames = array_column($rows, 'username');

echo json_encode(['ok' => true, 'users' => $usernames, 'total' => $total]);
