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
 * get_media_likers.php — Return the list of users who liked a media item (AJAX)
 *
 * GET params:
 *   media_id  int  Gallery media ID
 *
 * Returns JSON:
 *   { ok: true,  users: [ "alice", "bob", … ], total: N }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$user = json_api_guard('GET');

$mediaId = sanitise_int($_GET['media_id'] ?? 0);

if ($mediaId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid media_id']);
    exit;
}

$media = db_row('SELECT id FROM media WHERE id = ? AND is_deleted = 0', [$mediaId]);
if ($media === null) {
    echo json_encode(['ok' => false, 'error' => 'Media not found']);
    exit;
}

// Find any wall post linked to this media item so its engagement can be merged.
$linkedPost = db_row(
    'SELECT id FROM posts WHERE media_id = ? AND is_deleted = 0 ORDER BY id ASC LIMIT 1',
    [$mediaId]
);
$linkedPostId = $linkedPost ? (int)$linkedPost['id'] : null;

if ($linkedPostId !== null) {
    // Count distinct users who liked via either path (deduplicated).
    $total = (int) db_val(
        'SELECT COUNT(*) FROM (
             SELECT user_id FROM likes WHERE media_id = ?
             UNION
             SELECT user_id FROM likes WHERE post_id = ?
         ) AS merged_likes',
        [$mediaId, $linkedPostId]
    );
} else {
    $total = (int) db_val('SELECT COUNT(*) FROM likes WHERE media_id = ?', [$mediaId]);
}

if ($linkedPostId !== null) {
    // Merge direct media likes and wall-post likes.
    // Use UNION ALL + GROUP BY to deduplicate by user and sort by most recent like.
    $rows = db_query(
        'SELECT u.username
         FROM users u
         JOIN (
             SELECT user_id, MAX(id) AS last_like_id
             FROM (
                 SELECT user_id, id FROM likes WHERE media_id = ?
                 UNION ALL
                 SELECT user_id, id FROM likes WHERE post_id = ?
             ) AS all_likes
             GROUP BY user_id
         ) AS agg ON agg.user_id = u.id
         ORDER BY agg.last_like_id DESC
         LIMIT 10',
        [$mediaId, $linkedPostId]
    );
} else {
    $rows = db_query(
        'SELECT u.username
         FROM likes l
         JOIN users u ON u.id = l.user_id
         WHERE l.media_id = ?
         ORDER BY l.id DESC
         LIMIT 10',
        [$mediaId]
    );
}

$usernames = array_column($rows, 'username');

echo json_encode(['ok' => true, 'users' => $usernames, 'total' => $total]);
