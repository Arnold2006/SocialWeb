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
 * add_media_comment.php — Add a comment to a media item (AJAX)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user    = json_api_guard('POST');
$mediaId = sanitise_int($_POST['media_id'] ?? 0);
$content = sanitise_string($_POST['content'] ?? '', 1000);

if ($mediaId < 1 || empty($content)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

$media = db_row('SELECT id, user_id, album_id FROM media WHERE id = ? AND is_deleted = 0', [$mediaId]);
if ($media === null) {
    echo json_encode(['ok' => false, 'error' => 'Media not found']);
    exit;
}

$commentId = db_insert(
    'INSERT INTO comments (media_id, user_id, content) VALUES (?, ?, ?)',
    [$mediaId, (int)$user['id'], $content]
);

// Notify media owner (if not self-comment).
// Use the wall-feed album-upload post ID so the notification links to index.php#post-{id}.
// If multiple album-upload posts exist for the same album, use the most recent one.
$feedPost = $media['album_id'] ? db_row(
    'SELECT id FROM posts WHERE album_id = ? AND post_type = \'album_upload\' ORDER BY id DESC LIMIT 1',
    [$media['album_id']]
) : null;
if ($feedPost) {
    notify_user((int)$media['user_id'], 'comment', (int)$user['id'], (int)$feedPost['id']);
}

echo json_encode([
    'ok'          => true,
    'comment_id'  => (int)$commentId,
    'username'    => $user['username'],
    'avatar'      => avatar_url($user, 'small'),
    'content'     => $content,
    'time_ago'    => 'just now',
    'profile_url' => SITE_URL . '/pages/profile.php?id=' . (int)$user['id'],
]);
