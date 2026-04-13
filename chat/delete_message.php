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
 * delete_message.php — Delete a chat image message posted by the current user (AJAX, POST).
 *
 * POST /chat/delete_message.php
 *
 * Body params:
 *   csrf_token  — CSRF token
 *   message_id  — ID of the message to delete
 *
 * Only the original sender may delete their own message.
 * Deletes the uploaded image file from disk and removes the DB row.
 *
 * Response:
 *   { ok: true }
 *   { ok: false, error: '...' }
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = json_api_guard('POST');
$uid  = (int) $user['id'];

$messageId = sanitise_int($_POST['message_id'] ?? 0);

if ($messageId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid message ID']);
    exit;
}

// Fetch the message
$msg = db_row(
    'SELECT id, sender_id, image_path FROM chat_messages WHERE id = ?',
    [$messageId]
);

if (!$msg) {
    echo json_encode(['ok' => false, 'error' => 'Message not found']);
    exit;
}

// Only the sender may delete their own message
if ((int) $msg['sender_id'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => 'Not authorised']);
    exit;
}

// Remove the uploaded image file from disk
if ($msg['image_path'] !== null && $msg['image_path'] !== '') {
    $absPath = SITE_ROOT . '/' . $msg['image_path'];
    if (is_file($absPath) && !unlink($absPath)) {
        error_log('chat/delete_message.php: failed to unlink ' . $absPath);
    }
}

// Delete the message row
db_exec('DELETE FROM chat_messages WHERE id = ?', [$messageId]);

echo json_encode(['ok' => true]);
