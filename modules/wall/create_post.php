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
 * create_post.php — Handle wall post creation (POST handler)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

const WALL_IMAGES_ALBUM = 'Wall Images';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/pages/index.php');
}

csrf_verify();

$user    = current_user();
$content = sanitise_string($_POST['content'] ?? '', 5000);

if (empty($content)) {
    flash_set('error', 'Post content cannot be empty.');
    redirect(SITE_URL . '/pages/index.php');
}

$mediaId = null;

// Handle optional media upload
if (!empty($_FILES['media']['name'])) {
    $file = $_FILES['media'];

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $isImage = in_array($mimeType, ALLOWED_IMAGE_TYPES, true);
    $isVideo = in_array($mimeType, ALLOWED_VIDEO_TYPES, true);

    if ($isImage || $isVideo) {
        // Get or create the "Wall Images" album for this user.
        // Use SELECT then INSERT; in the unlikely event of a concurrent duplicate
        // insert the oldest matching album is always used on subsequent requests.
        $wallAlbum = db_row(
            'SELECT id FROM albums WHERE user_id = ? AND title = ? AND is_deleted = 0 ORDER BY id ASC LIMIT 1',
            [(int)$user['id'], WALL_IMAGES_ALBUM]
        );
        if ($wallAlbum) {
            $wallAlbumId = (int)$wallAlbum['id'];
        } else {
            // Ensure the album lands in the user's "Main" category so it is
            // visible in the gallery root view without any extra steps.
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
                [(int)$user['id'], $mainCatId, WALL_IMAGES_ALBUM]
            );
        }

        $result = $isImage
            ? process_image_upload($file, (int)$user['id'], $wallAlbumId)
            : process_video_upload($file, (int)$user['id'], $wallAlbumId);

        if ($result['ok']) {
            $mediaId = $result['media_id'];
        } else {
            flash_set('error', 'Media upload failed: ' . $result['error']);
        }
    } else {
        flash_set('error', 'Unsupported media type.');
    }
}

db_insert(
    'INSERT INTO posts (user_id, content, media_id) VALUES (?, ?, ?)',
    [(int)$user['id'], $content, $mediaId]
);

// Create notification for followers/friends who should see this
// (simplified: just invalidate cache)
cache_invalidate_wall();

// Add post notification for friends
// (in a full system you'd notify all friends; omitted for brevity)

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

redirect(SITE_URL . '/pages/index.php');
