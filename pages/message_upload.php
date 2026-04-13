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
 * message_upload.php — Upload an image attachment for a private message (AJAX, POST).
 *
 * POST /pages/message_upload.php
 *
 * Body params (multipart/form-data):
 *   csrf_token  — CSRF token
 *   attachment  — The uploaded image file
 *
 * Allowed MIME types : image/jpeg, image/png, image/webp, image/gif
 * Maximum size       : 10 MB
 * Storage            : /uploads/msg_attachments/<year>/<month>/<random16hex>.<ext>
 *
 * Response:
 *   { ok: true,  id: N, name: "...", size: N, url: "..." }
 *   { ok: false, error: "..." }
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = json_api_guard('POST');
$uid  = (int) $user['id'];

// ── Validate upload ───────────────────────────────────────────────────────────
if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['attachment']['error'] ?? -1;
    $msg  = match ((int) $code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum upload size.',
        UPLOAD_ERR_NO_FILE                        => 'No file was uploaded.',
        default                                   => 'Upload error (code ' . $code . ').',
    };
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$file = $_FILES['attachment'];

if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'File too large. Maximum size is 10 MB.']);
    exit;
}

// Validate MIME via finfo (never trust the browser-supplied Content-Type)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mimeType, $allowed, true)) {
    echo json_encode(['ok' => false, 'error' => 'Only JPG, PNG, WEBP and GIF images are allowed.']);
    exit;
}

// ── Build storage path ────────────────────────────────────────────────────────
$ext = match ($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg',
};

$year     = date('Y');
$month    = date('m');
$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$dir      = UPLOADS_DIR . '/msg_attachments/' . $year . '/' . $month . '/';
$relPath  = 'uploads/msg_attachments/' . $year . '/' . $month . '/' . $filename;

if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    echo json_encode(['ok' => false, 'error' => 'Server storage error.']);
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save the uploaded file.']);
    exit;
}

// ── Persist record (message_id = NULL until message is sent) ──────────────────
$originalName = sanitise_string($file['name'] ?? '', 255);
if ($originalName === '') {
    $originalName = 'attachment.' . $ext;
}

$attachId = (int) db_insert(
    'INSERT INTO message_attachments (message_id, sender_id, file_path, original_name, mime_type, file_size)
     VALUES (NULL, ?, ?, ?, ?, ?)',
    [$uid, $relPath, $originalName, $mimeType, (int) $file['size']]
);

echo json_encode([
    'ok'   => true,
    'id'   => $attachId,
    'name' => $originalName,
    'size' => (int) $file['size'],
    'url'  => SITE_URL . '/' . $relPath,
]);
