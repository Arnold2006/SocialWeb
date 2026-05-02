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
 * like_comment.php — Toggle a like on a comment (AJAX)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user      = json_api_guard('POST');
$commentId = sanitise_int($_POST['comment_id'] ?? 0);

if ($commentId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid comment']);
    exit;
}

$comment = db_row('SELECT id, user_id FROM comments WHERE id = ? AND is_deleted = 0', [$commentId]);
if ($comment === null) {
    echo json_encode(['ok' => false, 'error' => 'Comment not found']);
    exit;
}

// Toggle like
$existing = db_row(
    'SELECT id FROM likes WHERE user_id = ? AND comment_id = ?',
    [(int)$user['id'], $commentId]
);

if ($existing) {
    db_exec('DELETE FROM likes WHERE user_id = ? AND comment_id = ?', [(int)$user['id'], $commentId]);
    $liked = false;
} else {
    db_insert('INSERT INTO likes (user_id, comment_id) VALUES (?, ?)', [(int)$user['id'], $commentId]);
    $liked = true;

    // Notify comment owner (if not self-like)
    notify_user((int)$comment['user_id'], 'comment_like', (int)$user['id'], $commentId);
}

$likeCount = (int) db_val('SELECT COUNT(*) FROM likes WHERE comment_id = ?', [$commentId]);

echo json_encode(['ok' => true, 'liked' => $liked, 'count' => $likeCount]);
