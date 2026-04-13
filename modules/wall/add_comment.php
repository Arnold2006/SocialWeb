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
 * add_comment.php — Add a comment to a post (AJAX)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user    = json_api_guard('POST');
$postId  = sanitise_int($_POST['post_id'] ?? 0);
$content = sanitise_string($_POST['content'] ?? '', 1000);

if ($postId < 1 || empty($content)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

$post = db_row('SELECT id, user_id FROM posts WHERE id = ? AND is_deleted = 0', [$postId]);
if ($post === null) {
    echo json_encode(['ok' => false, 'error' => 'Post not found']);
    exit;
}

$commentId = db_insert(
    'INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)',
    [$postId, (int)$user['id'], $content]
);

// Bump the post to the top of the feed
db_exec('UPDATE posts SET bumped_at = NOW() WHERE id = ?', [$postId]);

// Notify post owner and any @mentioned users.
// Wrapped in try/catch so a notification failure does not prevent the JSON
// response from being returned (which would leave the comment input un-cleared).
try {
    notify_user((int)$post['user_id'], 'comment', (int)$user['id'], (int)$postId);
    notify_mentions($content, (int)$user['id'], (int)$postId);
} catch (\Throwable $e) {
    error_log('add_comment notify failed: ' . $e->getMessage());
}

cache_invalidate_wall();

echo json_encode([
    'ok'           => true,
    'comment_id'   => (int)$commentId,
    'username'     => $user['username'],
    'avatar'       => avatar_url($user, 'small'),
    'content'      => $content,
    'content_html' => nl2br(linkify(smilify($content))),
    'time_ago'     => 'just now',
    'profile_url'  => SITE_URL . '/pages/profile.php?id=' . (int)$user['id'],
]);
