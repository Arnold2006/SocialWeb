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

$total = (int) db_val('SELECT COUNT(*) FROM likes WHERE media_id = ?', [$mediaId]);

$rows = db_query(
    'SELECT u.username
     FROM likes l
     JOIN users u ON u.id = l.user_id
     WHERE l.media_id = ?
     ORDER BY l.id DESC
     LIMIT 10',
    [$mediaId]
);

$usernames = array_column($rows, 'username');

echo json_encode(['ok' => true, 'users' => $usernames, 'total' => $total]);
