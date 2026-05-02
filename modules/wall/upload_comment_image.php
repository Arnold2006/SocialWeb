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
 * upload_comment_image.php — Upload an image to attach to a comment (AJAX)
 *
 * The image is saved in the commenting user's "Wall Images" album (the same
 * album used by wall post images).  Returns the new media record's ID and
 * URL variants so the client can display a preview and send the ID when the
 * comment is submitted.
 *
 * POST params (multipart/form-data):
 *   csrf_token  string  CSRF token
 *   image       file    The image file to upload
 *
 * Returns JSON:
 *   { ok: true,  media_id: int, thumb_url: string, medium_url: string, large_url: string }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user = json_api_guard('POST');

if (empty($_FILES['image']['name'])) {
    echo json_encode(['ok' => false, 'error' => 'No image provided']);
    exit;
}

$file     = $_FILES['image'];
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid image type. Supported: JPEG, PNG, GIF, WebP.']);
    exit;
}

// Get or create the "Wall Images" album — identical pattern to create_post.php
$wallAlbum = db_row(
    'SELECT id FROM albums WHERE user_id = ? AND title = ? AND is_deleted = 0 ORDER BY id ASC LIMIT 1',
    [(int)$user['id'], 'Wall Images']
);

if ($wallAlbum) {
    $wallAlbumId = (int)$wallAlbum['id'];
} else {
    $mainCat = db_row(
        'SELECT id FROM album_categories WHERE user_id = ? AND title = ? AND is_deleted = 0 ORDER BY id ASC LIMIT 1',
        [(int)$user['id'], 'Main']
    );
    if ($mainCat) {
        $mainCatId = (int)$mainCat['id'];
    } else {
        $mainCatId = (int)db_insert(
            'INSERT INTO album_categories (user_id, title) VALUES (?, ?)',
            [(int)$user['id'], 'Main']
        );
    }
    $wallAlbumId = (int)db_insert(
        'INSERT INTO albums (user_id, category_id, title) VALUES (?, ?, ?)',
        [(int)$user['id'], $mainCatId, 'Wall Images']
    );
}

$result = process_image_upload($file, (int)$user['id'], $wallAlbumId);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$mediaId  = (int)$result['media_id'];
$mediaRow = db_row('SELECT * FROM media WHERE id = ? AND is_deleted = 0', [$mediaId]);

if (!$mediaRow) {
    echo json_encode(['ok' => false, 'error' => 'Upload succeeded but media record not found']);
    exit;
}

echo json_encode([
    'ok'          => true,
    'media_id'    => $mediaId,
    'thumb_url'   => get_media_url($mediaRow, 'thumb'),
    'medium_url'  => get_media_url($mediaRow, 'medium'),
    'large_url'   => get_media_url($mediaRow, 'large'),
    'original_url' => get_media_url($mediaRow, 'original'),
]);
