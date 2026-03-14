<?php
/**
 * upload_image.php — Upload an image for a blog post.
 *
 * POST parameters:
 *   csrf_token  string   CSRF token
 *   image       file     Image file to upload
 *
 * Response: JSON { ok, url, error }
 *
 * The image is processed through the standard pipeline (EXIF stripped, resized)
 * and stored in the user's "Blog" album in their gallery.
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

const BLOG_IMAGES_ALBUM = 'Blog';

header('Content-Type: application/json');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

csrf_verify();

$user = current_user();

if (empty($_FILES['image']['name'])) {
    echo json_encode(['ok' => false, 'error' => 'No file uploaded.']);
    exit;
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'Upload error.']);
    exit;
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
    echo json_encode(['ok' => false, 'error' => 'Only image files are allowed.']);
    exit;
}

// Get or create the "Blog" album for this user
$blogAlbum = db_row(
    'SELECT id FROM albums WHERE user_id = ? AND title = ? AND is_deleted = 0 ORDER BY id ASC LIMIT 1',
    [(int)$user['id'], BLOG_IMAGES_ALBUM]
);
if ($blogAlbum) {
    $blogAlbumId = (int)$blogAlbum['id'];
} else {
    $blogAlbumId = (int)db_insert(
        'INSERT INTO albums (user_id, title) VALUES (?, ?)',
        [(int)$user['id'], BLOG_IMAGES_ALBUM]
    );
}

$result = process_image_upload($file, (int)$user['id'], $blogAlbumId);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

// Fetch the saved media row to build the URL
$media = db_row('SELECT * FROM media WHERE id = ?', [$result['media_id']]);
if (!$media) {
    echo json_encode(['ok' => false, 'error' => 'Media record not found.']);
    exit;
}

$url = get_media_url($media, 'large');

echo json_encode(['ok' => true, 'url' => $url, 'media_id' => $result['media_id']]);
