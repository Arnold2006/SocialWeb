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
 * get_notifications.php — Return unread count as JSON (AJAX polling)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'count' => 0]);
    exit;
}

$user   = current_user();
$uid    = (int) $user['id'];
$notifs = (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid]);
$msgs   = (int) db_val(
    'SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0 AND is_deleted_receiver = 0',
    [$uid]
);
$chat   = (int) db_val(
    'SELECT COUNT(*)
     FROM   chat_messages cm
     JOIN   conversations c ON c.id = cm.conversation_id
     WHERE  (c.user1_id = ? OR c.user2_id = ?)
       AND  cm.sender_id != ?
       AND  cm.is_read   = 0',
    [$uid, $uid, $uid]
);

echo json_encode(['ok' => true, 'notifications' => $notifs, 'messages' => $msgs, 'chat' => $chat]);
