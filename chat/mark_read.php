<?php
/**
 * mark_read.php — Mark all unread messages in a conversation as read (AJAX, POST).
 *
 * POST /chat/mark_read.php
 *
 * Body params:
 *   csrf_token      — CSRF token
 *   conversation_id — the conversation to mark as read
 *
 * Response:
 *   { ok: true }
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
$convId = sanitise_int($_POST['conversation_id'] ?? 0);

if ($convId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid conversation_id']);
    exit;
}

// Verify user participates in this conversation
$conv = db_row(
    'SELECT id FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)',
    [$convId, $uid, $uid]
);
if (!$conv) {
    echo json_encode(['ok' => false, 'error' => 'Conversation not found']);
    exit;
}

// Mark all messages in this conversation sent by the OTHER user as read
db_exec(
    'UPDATE chat_messages
     SET    is_read = 1
     WHERE  conversation_id = ? AND sender_id != ? AND is_read = 0',
    [$convId, $uid]
);

echo json_encode(['ok' => true]);
