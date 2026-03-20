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
 * like_post.php — Toggle a like on a blog post (AJAX)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user       = json_api_guard('POST');
$blogPostId = sanitise_int($_POST['blog_post_id'] ?? 0);

if ($blogPostId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid blog post']);
    exit;
}

$blogPost = db_row('SELECT id, user_id FROM blog_posts WHERE id = ? AND is_deleted = 0', [$blogPostId]);
if ($blogPost === null) {
    echo json_encode(['ok' => false, 'error' => 'Blog post not found']);
    exit;
}

// Toggle like
$existing = db_row(
    'SELECT id FROM likes WHERE user_id = ? AND blog_post_id = ?',
    [(int)$user['id'], $blogPostId]
);

if ($existing) {
    db_exec('DELETE FROM likes WHERE user_id = ? AND blog_post_id = ?', [(int)$user['id'], $blogPostId]);
    $liked = false;
} else {
    db_insert('INSERT INTO likes (user_id, blog_post_id) VALUES (?, ?)', [(int)$user['id'], $blogPostId]);
    $liked = true;

    // Notify blog post owner (if not self-like)
    notify_user((int)$blogPost['user_id'], 'blog_like', (int)$user['id'], $blogPostId);
}

$likeCount = (int) db_val('SELECT COUNT(*) FROM likes WHERE blog_post_id = ?', [$blogPostId]);

echo json_encode(['ok' => true, 'liked' => $liked, 'count' => $likeCount]);
