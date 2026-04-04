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
 * upload_image.php — Upload an image to a chat conversation (AJAX, POST).
 *
 * POST /chat/upload_image.php
 *
 * Body params (multipart/form-data):
 *   csrf_token  — CSRF token
 *   receiver_id — ID of the message recipient
 *   image       — The uploaded image file
 *
 * Allowed MIME types : image/jpeg, image/png, image/webp, image/gif
 * Maximum size       : 10 MB
 * Storage            : /uploads/chat/<random-hex>.<ext>
 *
 * Response:
 *   { ok: true, conversation_id: N, message: { ... } }
 *   { ok: false, error: '...' }
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = json_api_guard('POST');
$uid  = (int) $user['id'];

$receiverId = sanitise_int($_POST['receiver_id'] ?? 0);

if ($receiverId < 1 || $receiverId === $uid) {
    echo json_encode(['ok' => false, 'error' => 'Invalid receiver']);
    exit;
}

// Validate upload
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['image']['error'] ?? -1;
    $msg  = match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum upload size.',
        UPLOAD_ERR_NO_FILE                        => 'No file was uploaded.',
        default                                   => 'Upload error (code ' . $code . ').',
    };
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$file = $_FILES['image'];

// Validate MIME type using finfo (not the browser-supplied type, which can be spoofed)
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowed, true)) {
    echo json_encode(['ok' => false, 'error' => 'Only JPG, PNG, WEBP and GIF images are allowed.']);
    exit;
}

// Validate file size (max 10 MB)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'File too large. Maximum size is 10 MB.']);
    exit;
}

// Check receiver
$receiver = db_row(
    'SELECT id FROM users WHERE id = ? AND is_banned = 0',
    [$receiverId]
);
if (!$receiver) {
    echo json_encode(['ok' => false, 'error' => 'Recipient not found']);
    exit;
}

// Generate a unique filename to prevent overwrites
$ext = match ($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg',
};
$filename     = bin2hex(random_bytes(16)) . '.' . $ext;
$uploadDir    = UPLOADS_DIR . '/chat/';
$destPath     = $uploadDir . $filename;
$relativePath = 'uploads/chat/' . $filename;

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save the uploaded file.']);
    exit;
}

// Get or create conversation
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

// Insert image message (message_text is NULL for image-only messages)
$msgId = (int) db_insert(
    'INSERT INTO chat_messages (conversation_id, sender_id, image_path) VALUES (?, ?, ?)',
    [$convId, $uid, $relativePath]
);

// Keep conversation timestamp current
db_exec(
    'UPDATE conversations SET last_message_time = NOW() WHERE id = ?',
    [$convId]
);

// Notify the receiver only if they do not have this conversation open right now
if (!is_user_active_in_chat($receiverId, $convId)) {
    notify_user($receiverId, 'message', $uid, $convId);
}

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
        'message_text'      => null,
        'image_url'         => SITE_URL . '/' . $msg['image_path'],
        'created_at'        => $msg['created_at'],
        'time_ago'          => time_ago($msg['created_at']),
        'sender_username'   => $msg['sender_username'],
        'sender_avatar_url' => avatar_url(['avatar_path' => $msg['sender_avatar']], 'small'),
    ],
]);
