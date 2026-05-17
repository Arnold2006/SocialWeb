<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * get_unread_count.php — Return only the total unread chat message count for the current user.
 *
 * GET /chat/get_unread_count.php
 *
 * Response:
 *   { ok: true, unread_count: N }
 *
 * This is a lightweight alternative to get_users.php used solely for updating the
 * badge counter. It avoids fetching and serialising the full user list on every
 * background poll tick.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = json_api_guard('GET');
$uid  = (int) $user['id'];

$count = (int) db_val(
    'SELECT COUNT(*)
     FROM   chat_messages cm
     JOIN   conversations c ON c.id = cm.conversation_id
     WHERE  (c.user1_id = ? OR c.user2_id = ?)
       AND  cm.sender_id != ?
       AND  cm.is_read   = 0',
    [$uid, $uid, $uid]
);

echo json_encode(['ok' => true, 'unread_count' => $count]);
