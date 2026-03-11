<?php
/**
 * get_messages.php — Return JSON messages for a conversation.
 *
 * GET /chat/get_messages.php
 *
 * Query params:
 *   conversation_id  (required) — the conversation to fetch
 *   after_id         (optional) — only return messages with id > after_id  (for polling)
 *   before_id        (optional) — only return messages with id < before_id (load older)
 *
 * Loads the latest 50 messages by default. Older messages are fetched via before_id.
 *
 * Response:
 *   { ok: true, messages: [ { id, sender_id, is_mine, message_text, image_url,
 *                              created_at, time_ago, sender_username, sender_avatar_url } ] }
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$user   = current_user();
$uid    = (int) $user['id'];
$convId = sanitise_int($_GET['conversation_id'] ?? 0);

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

echo json_encode(['ok' => true, 'messages' => $result]);
