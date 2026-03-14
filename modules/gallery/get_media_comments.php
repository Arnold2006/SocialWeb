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
 * get_media_comments.php — Fetch comments and like state for a media item (AJAX)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user    = json_api_guard('GET');
$mediaId = sanitise_int($_GET['media_id'] ?? 0);

if ($mediaId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid media']);
    exit;
}

$media = db_row('SELECT id FROM media WHERE id = ? AND is_deleted = 0', [$mediaId]);
if ($media === null) {
    echo json_encode(['ok' => false, 'error' => 'Media not found']);
    exit;
}

$comments = db_query(
    'SELECT c.id, c.content, c.created_at, u.id AS user_id, u.username, u.avatar_path
     FROM comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.media_id = ? AND c.is_deleted = 0
     ORDER BY c.created_at ASC',
    [$mediaId]
);

$likeCount = (int) db_val('SELECT COUNT(*) FROM likes WHERE media_id = ?', [$mediaId]);

$userLiked = (int) db_val(
    'SELECT COUNT(*) FROM likes WHERE user_id = ? AND media_id = ?',
    [(int)$user['id'], $mediaId]
) > 0;

$commentData = [];
foreach ($comments as $comment) {
    $commentData[] = [
        'id'          => (int)$comment['id'],
        'username'    => $comment['username'],
        'avatar'      => avatar_url($comment, 'small'),
        'content'     => $comment['content'],
        'time_ago'    => time_ago($comment['created_at']),
        'profile_url' => SITE_URL . '/pages/profile.php?id=' . (int)$comment['user_id'],
    ];
}

echo json_encode([
    'ok'         => true,
    'comments'   => $commentData,
    'like_count' => $likeCount,
    'user_liked' => $userLiked,
]);
