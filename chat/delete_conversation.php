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
 * delete_conversation.php — Delete an entire chat conversation (AJAX, POST).
 *
 * POST /chat/delete_conversation.php
 *
 * Body params:
 *   csrf_token       — CSRF token
 *   conversation_id  — ID of the conversation to delete
 *
 * Only a participant of the conversation may delete it.
 * Deletes all image files shared in the conversation from disk,
 * then removes all related rows from chat_activity, chat_messages,
 * and conversations.
 *
 * Response:
 *   { ok: true }
 *   { ok: false, error: '...' }
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user   = json_api_guard('POST');
$uid    = (int) $user['id'];
$convId = sanitise_int($_POST['conversation_id'] ?? 0);

if ($convId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid conversation ID']);
    exit;
}

// Verify the current user is a participant
$conv = db_row(
    'SELECT id FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)',
    [$convId, $uid, $uid]
);

if (!$conv) {
    echo json_encode(['ok' => false, 'error' => 'Conversation not found']);
    exit;
}

// Collect all image paths so we can remove the files from disk
$images = db_query(
    'SELECT image_path FROM chat_messages WHERE conversation_id = ? AND image_path IS NOT NULL',
    [$convId]
);

foreach ($images as $row) {
    $absPath = SITE_ROOT . '/' . $row['image_path'];
    if (is_file($absPath) && !unlink($absPath)) {
        error_log('chat/delete_conversation.php: failed to unlink ' . $row['image_path']);
    }
}

// Delete related rows, then the conversation itself — wrapped in a transaction
// so that a failure leaves no partial state.
$pdo = db();
$pdo->beginTransaction();
try {
    db_exec('DELETE FROM chat_activity  WHERE conversation_id = ?', [$convId]);
    db_exec('DELETE FROM chat_messages  WHERE conversation_id = ?', [$convId]);
    db_exec('DELETE FROM conversations  WHERE id = ?',              [$convId]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('chat/delete_conversation.php: transaction failed — ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to delete conversation']);
    exit;
}

echo json_encode(['ok' => true]);
