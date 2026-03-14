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
 * get_messages.php — Return JSON messages for a conversation.
 *
 * GET /chat/get_messages.php
 *
 * Query params:
 *   conversation_id  (required unless receiver_id given) — the conversation to fetch
 *   receiver_id      (alternative) — look up conversation by the other user's ID
 *   after_id         (optional) — only return messages with id > after_id  (for polling)
 *   before_id        (optional) — only return messages with id < before_id (load older)
 *
 * Loads the latest 50 messages by default. Older messages are fetched via before_id.
 *
 * Response:
 *   { ok: true, conversation_id: N|null, messages: [ { id, sender_id, is_mine,
 *     message_text, image_url, created_at, time_ago, sender_username,
 *     sender_avatar_url } ] }
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user       = json_api_guard('GET');
$uid        = (int) $user['id'];
$convId     = sanitise_int($_GET['conversation_id'] ?? 0);
$receiverId = sanitise_int($_GET['receiver_id']      ?? 0);

// Resolve conversation by receiver_id when conversation_id is not given
if ($convId < 1 && $receiverId > 0 && $receiverId !== $uid) {
    $u1 = min($uid, $receiverId);
    $u2 = max($uid, $receiverId);
    $convRow = db_row(
        'SELECT id FROM conversations WHERE user1_id = ? AND user2_id = ?',
        [$u1, $u2]
    );
    if ($convRow) {
        $convId = (int) $convRow['id'];
    } else {
        // No conversation yet — return empty response with null conversation_id
        echo json_encode(['ok' => true, 'conversation_id' => null, 'messages' => []]);
        exit;
    }
}

if ($convId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid conversation_id']);
    exit;
}

// Ensure the current user is a participant
$conv = db_row(
    'SELECT id FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)',
    [$convId, $uid, $uid]
);
if (!$conv) {
    echo json_encode(['ok' => false, 'error' => 'Conversation not found']);
    exit;
}

$afterId  = sanitise_int($_GET['after_id']  ?? 0);
$beforeId = sanitise_int($_GET['before_id'] ?? 0);

if ($afterId > 0) {
    // Polling — only messages newer than the last known ID
    $messages = db_query(
        'SELECT cm.id, cm.sender_id, cm.message_text, cm.image_path, cm.created_at,
                u.username AS sender_username, u.avatar_path AS sender_avatar
         FROM   chat_messages cm
         JOIN   users u ON u.id = cm.sender_id
         WHERE  cm.conversation_id = ? AND cm.id > ?
         ORDER  BY cm.created_at ASC',
        [$convId, $afterId]
    );
} elseif ($beforeId > 0) {
    // Scroll-up — load older messages (returns up to 50 before the given ID)
    $messages = db_query(
        'SELECT cm.id, cm.sender_id, cm.message_text, cm.image_path, cm.created_at,
                u.username AS sender_username, u.avatar_path AS sender_avatar
         FROM   chat_messages cm
         JOIN   users u ON u.id = cm.sender_id
         WHERE  cm.conversation_id = ? AND cm.id < ?
         ORDER  BY cm.id DESC
         LIMIT  50',
        [$convId, $beforeId]
    );
    // Return in ascending (chronological) order
    $messages = array_reverse($messages);
} else {
    // Initial open — latest 50 messages in chronological order
    $messages = db_query(
        'SELECT cm.id, cm.sender_id, cm.message_text, cm.image_path, cm.created_at,
                u.username AS sender_username, u.avatar_path AS sender_avatar
         FROM   chat_messages cm
         JOIN   users u ON u.id = cm.sender_id
         WHERE  cm.conversation_id = ?
         ORDER  BY cm.id DESC
         LIMIT  50',
        [$convId]
    );
    $messages = array_reverse($messages);
}

$result = [];
foreach ($messages as $msg) {
    $result[] = [
        'id'                => (int) $msg['id'],
        'sender_id'         => (int) $msg['sender_id'],
        'is_mine'           => (int) $msg['sender_id'] === $uid,
        'message_text'      => $msg['message_text'],
        'image_url'         => $msg['image_path'] !== null
                                   ? SITE_URL . '/' . $msg['image_path']
                                   : null,
        'created_at'        => $msg['created_at'],
        'time_ago'          => time_ago($msg['created_at']),
        'sender_username'   => $msg['sender_username'],
        'sender_avatar_url' => avatar_url(['avatar_path' => $msg['sender_avatar']], 'small'),
    ];
}

echo json_encode(['ok' => true, 'conversation_id' => $convId, 'messages' => $result]);
