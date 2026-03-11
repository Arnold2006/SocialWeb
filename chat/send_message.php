<?php
/**
 * send_message.php — Send a text message in a conversation (AJAX, POST).
 *
 * POST /chat/send_message.php
 *
 * Body params:
 *   csrf_token   — CSRF token
 *   receiver_id  — ID of the message recipient
 *   message_text — Message body (max 5 000 chars)
 *
 * Response:
 *   { ok: true, conversation_id: N, message: { ... } }
 *   { ok: false, error: '...' }
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

csrf_verify();

$user   = current_user();
$uid    = (int) $user['id'];

$receiverId  = sanitise_int($_POST['receiver_id']  ?? 0);
$messageText = sanitise_string($_POST['message_text'] ?? '', 5000);

if ($receiverId < 1 || $receiverId === $uid) {
    echo json_encode(['ok' => false, 'error' => 'Invalid receiver']);
    exit;
}

if ($messageText === '') {
    echo json_encode(['ok' => false, 'error' => 'Message cannot be empty']);
    exit;
}

// Check receiver exists and is not banned
$receiver = db_row(
    'SELECT id FROM users WHERE id = ? AND is_banned = 0',
    [$receiverId]
);
if (!$receiver) {
    echo json_encode(['ok' => false, 'error' => 'Recipient not found']);
    exit;
}

// Get or create conversation (user1_id is always the smaller ID for uniqueness)
$u1 = min($uid, $receiverId);
$u2 = max($uid, $receiverId);

$conv = db_row(
    'SELECT id FROM conversations WHERE user1_id = ? AND user2_id = ?',
    [$u1, $u2]
);
if ($conv) {
    $convId = (int) $conv['id'];
} else {
    $convId = (int) db_insert(
        'INSERT INTO conversations (user1_id, user2_id, last_message_time) VALUES (?, ?, NOW())',
        [$u1, $u2]
    );
}

// Insert message
$msgId = (int) db_insert(
    'INSERT INTO chat_messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)',
    [$convId, $uid, $messageText]
);

// Keep conversation timestamp current
db_exec(
    'UPDATE conversations SET last_message_time = NOW() WHERE id = ?',
    [$convId]
);

// Notify the receiver (deduplicated: only one 'message' notification per sender)
db_insert(
    'INSERT INTO notifications (user_id, type, from_user_id, ref_id) VALUES (?, "message", ?, ?)',
    [$receiverId, $uid, $convId]
);

// Return the newly created message
$msg = db_row(
    'SELECT cm.id, cm.sender_id, cm.message_text, cm.image_path, cm.created_at,
            u.username AS sender_username, u.avatar_path AS sender_avatar
     FROM   chat_messages cm
     JOIN   users u ON u.id = cm.sender_id
     WHERE  cm.id = ?',
    [$msgId]
);

echo json_encode([
    'ok'              => true,
    'conversation_id' => $convId,
    'message'         => [
        'id'                => (int) $msg['id'],
        'sender_id'         => (int) $msg['sender_id'],
        'is_mine'           => true,
        'message_text'      => $msg['message_text'],
        'image_url'         => null,
        'created_at'        => $msg['created_at'],
        'time_ago'          => time_ago($msg['created_at']),
        'sender_username'   => $msg['sender_username'],
        'sender_avatar_url' => avatar_url(['avatar_path' => $msg['sender_avatar']], 'small'),
    ],
]);
