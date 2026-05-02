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
 * get_user_album_images.php — Return the current user's albums or images within
 * a given album, for the comment image picker (AJAX).
 *
 * GET params:
 *   album_id  int  (optional) If given, returns image thumbnails for that album.
 *                             Otherwise returns the user's album list.
 *
 * Returns (albums list):
 *   { ok: true, albums: [ { id, title, cover_url, image_count }, … ] }
 *
 * Returns (album images):
 *   { ok: true, images: [ { media_id, thumb_url, medium_url, large_url, original_url }, … ] }
 *
 * Returns (error):
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user    = json_api_guard('GET');
$albumId = sanitise_int($_GET['album_id'] ?? 0);

if ($albumId > 0) {
    // Return images for the given album (must belong to current user)
    $album = db_row(
        'SELECT id FROM albums WHERE id = ? AND user_id = ? AND is_deleted = 0',
        [$albumId, (int)$user['id']]
    );
    if (!$album) {
        echo json_encode(['ok' => false, 'error' => 'Album not found']);
        exit;
    }

    $mediaRows = db_query(
        'SELECT id, thumb_path, medium_path, large_path, storage_path
         FROM media
         WHERE album_id = ? AND user_id = ? AND type = "image" AND is_deleted = 0
         ORDER BY created_at DESC
         LIMIT 100',
        [$albumId, (int)$user['id']]
    );

    $images = [];
    foreach ($mediaRows as $row) {
        $images[] = [
            'media_id'     => (int)$row['id'],
            'thumb_url'    => get_media_url($row, 'thumb'),
            'medium_url'   => get_media_url($row, 'medium'),
            'large_url'    => get_media_url($row, 'large'),
            'original_url' => get_media_url($row, 'original'),
        ];
    }

    echo json_encode(['ok' => true, 'images' => $images]);
    exit;
}

// Return the user's album list
$albums = db_query(
    'SELECT a.id, a.title, a.cover_id, a.cover_path,
            (SELECT COUNT(*) FROM media WHERE album_id = a.id AND type = "image" AND is_deleted = 0) AS image_count
     FROM albums a
     WHERE a.user_id = ? AND a.is_deleted = 0
     ORDER BY a.created_at DESC',
    [(int)$user['id']]
);

$result = [];
foreach ($albums as $album) {
    // Resolve cover URL
    $coverUrl = null;
    if (!empty($album['cover_path'])) {
        $coverUrl = SITE_URL . $album['cover_path'];
    } elseif (!empty($album['cover_id'])) {
        $coverMedia = db_row(
            'SELECT thumb_path, thumbnail_path FROM media WHERE id = ? AND is_deleted = 0',
            [(int)$album['cover_id']]
        );
        if ($coverMedia) {
            $coverUrl = get_media_url($coverMedia, 'thumb');
        }
    }
    if (!$coverUrl) {
        // Use first image as fallback cover
        $first = db_row(
            'SELECT thumb_path FROM media WHERE album_id = ? AND type = "image" AND is_deleted = 0 ORDER BY created_at ASC LIMIT 1',
            [(int)$album['id']]
        );
        if ($first) {
            $coverUrl = get_media_url($first, 'thumb');
        }
    }

    if ((int)$album['image_count'] === 0) {
        // Skip albums with no images
        continue;
    }

    $result[] = [
        'id'          => (int)$album['id'],
        'title'       => $album['title'],
        'cover_url'   => $coverUrl,
        'image_count' => (int)$album['image_count'],
    ];
}

echo json_encode(['ok' => true, 'albums' => $result]);
