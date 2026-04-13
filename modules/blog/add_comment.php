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
 * add_comment.php — Add a comment to a blog post (AJAX)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user       = json_api_guard('POST');
$blogPostId = sanitise_int($_POST['blog_post_id'] ?? 0);
$content    = sanitise_string($_POST['content'] ?? '', 1000);

if ($blogPostId < 1 || empty($content)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

$blogPost = db_row('SELECT id, user_id FROM blog_posts WHERE id = ? AND is_deleted = 0', [$blogPostId]);
if ($blogPost === null) {
    echo json_encode(['ok' => false, 'error' => 'Blog post not found']);
    exit;
}

$commentId = db_insert(
    'INSERT INTO comments (blog_post_id, user_id, content) VALUES (?, ?, ?)',
    [$blogPostId, (int)$user['id'], $content]
);

// Notify blog post owner and any @mentioned users.
// Wrapped in try/catch so a notification failure does not prevent the JSON
// response from being returned (which would leave the comment input un-cleared).
try {
    notify_user((int)$blogPost['user_id'], 'blog_comment', (int)$user['id'], (int)$commentId);
    notify_mentions($content, (int)$user['id'], (int)$blogPostId);
} catch (\Throwable $e) {
    error_log('blog add_comment notify failed: ' . $e->getMessage());
}

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
