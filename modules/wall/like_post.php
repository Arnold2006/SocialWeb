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
 * like_post.php — Toggle a like on a post (AJAX)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

csrf_verify();

$user   = current_user();
$postId = sanitise_int($_POST['post_id'] ?? 0);

if ($postId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid post']);
    exit;
}

$post = db_row('SELECT id, user_id FROM posts WHERE id = ? AND is_deleted = 0', [$postId]);
if ($post === null) {
    echo json_encode(['ok' => false, 'error' => 'Post not found']);
    exit;
}

// Toggle like
$existing = db_row(
    'SELECT id FROM likes WHERE user_id = ? AND post_id = ?',
    [(int)$user['id'], $postId]
);

if ($existing) {
    db_exec('DELETE FROM likes WHERE user_id = ? AND post_id = ?', [(int)$user['id'], $postId]);
    $liked = false;
} else {
    db_insert('INSERT INTO likes (user_id, post_id) VALUES (?, ?)', [(int)$user['id'], $postId]);
    $liked = true;

    // Bump the post to the top of the feed
    db_exec('UPDATE posts SET bumped_at = NOW() WHERE id = ?', [$postId]);

    // Notify post owner (if not self-like)
    if ((int)$post['user_id'] !== (int)$user['id']) {
        db_insert(
            'INSERT INTO notifications (user_id, type, from_user_id, ref_id) VALUES (?, "like", ?, ?)',
            [(int)$post['user_id'], (int)$user['id'], $postId]
        );
    }
}

cache_invalidate_wall();

$likeCount = (int) db_val('SELECT COUNT(*) FROM likes WHERE post_id = ?', [$postId]);

echo json_encode(['ok' => true, 'liked' => $liked, 'count' => $likeCount]);
