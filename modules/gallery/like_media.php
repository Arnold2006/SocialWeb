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
 * like_media.php — Toggle a like on a media item (AJAX)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user    = json_api_guard('POST');
$mediaId = sanitise_int($_POST['media_id'] ?? 0);

if ($mediaId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid media']);
    exit;
}

$media = db_row('SELECT id, user_id FROM media WHERE id = ? AND is_deleted = 0', [$mediaId]);
if ($media === null) {
    echo json_encode(['ok' => false, 'error' => 'Media not found']);
    exit;
}

// Toggle like
$existing = db_row(
    'SELECT id FROM likes WHERE user_id = ? AND media_id = ?',
    [(int)$user['id'], $mediaId]
);

if ($existing) {
    db_exec('DELETE FROM likes WHERE user_id = ? AND media_id = ?', [(int)$user['id'], $mediaId]);
    $liked = false;
} else {
    db_insert('INSERT INTO likes (user_id, media_id) VALUES (?, ?)', [(int)$user['id'], $mediaId]);
    $liked = true;

    // Notify media owner (if not self-like)
    notify_user((int)$media['user_id'], 'like', (int)$user['id'], $mediaId);
}

$likeCount = (int) db_val('SELECT COUNT(*) FROM likes WHERE media_id = ?', [$mediaId]);

echo json_encode(['ok' => true, 'liked' => $liked, 'count' => $likeCount]);
