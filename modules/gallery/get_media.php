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
 * get_media.php — AJAX endpoint for paginated album media.
 *
 * GET params:
 *   album_id  int   Album to load media from
 *   user_id   int   Owner of the album
 *   offset    int   Number of items already loaded (default 0)
 *
 * Returns JSON:
 *   { ok: true,  html: string, has_more: bool }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$currentUser = current_user();
$albumId     = sanitise_int($_GET['album_id'] ?? 0);
$galleryOwner = sanitise_int($_GET['user_id'] ?? 0);
$offset      = max(0, sanitise_int($_GET['offset'] ?? 0));
$limit       = 25;

if ($albumId < 1 || $galleryOwner < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Verify album exists and belongs to the given owner
$album = db_row(
    'SELECT * FROM albums WHERE id = ? AND user_id = ? AND is_deleted = 0',
    [$albumId, $galleryOwner]
);

if (!$album) {
    echo json_encode(['ok' => false, 'error' => 'Album not found']);
    exit;
}

$isOwn = ((int)$currentUser['id'] === $galleryOwner);

// Load other albums for the move-media button (only needed for owner)
$allOwnerAlbums = [];
if ($isOwn) {
    $allOwnerAlbums = db_query(
        'SELECT a.id, a.title, c.title AS category_title
         FROM albums a
         LEFT JOIN album_categories c ON c.id = a.category_id AND c.is_deleted = 0
         WHERE a.user_id = ? AND a.is_deleted = 0 AND a.id != ?
         ORDER BY c.title ASC, a.title ASC',
        [$galleryOwner, $albumId]
    );
}

try {
    $fetchLimit = $limit + 1; // fetch one extra to detect whether more items exist

    $mediaRows = db_query(
        'SELECT m.*,
            (SELECT COUNT(*) FROM likes WHERE media_id = m.id)
              + (SELECT COUNT(*) FROM likes l2 JOIN posts p2 ON l2.post_id = p2.id
                 WHERE p2.media_id = m.id AND p2.is_deleted = 0) AS like_count,
            (SELECT COUNT(*) FROM comments WHERE media_id = m.id AND is_deleted = 0)
              + (SELECT COUNT(*) FROM comments c2 JOIN posts p3 ON c2.post_id = p3.id
                 WHERE p3.media_id = m.id AND p3.is_deleted = 0 AND c2.is_deleted = 0) AS comment_count
         FROM media m
         WHERE m.album_id = ? AND m.user_id = ? AND m.is_deleted = 0
         ORDER BY m.created_at DESC
         LIMIT ' . (int)$fetchLimit . ' OFFSET ' . (int)$offset,
        [$albumId, $galleryOwner]
    );

    $hasMore = count($mediaRows) > $limit;
    if ($hasMore) {
        array_pop($mediaRows);
    }

    ob_start();
    foreach ($mediaRows as $media) {
        $isCover = ((int)$media['id'] === (int)($album['cover_id'] ?? 0));
        include __DIR__ . '/media_item.php';
    }
    $html = ob_get_clean() ?: '';

    echo json_encode(['ok' => true, 'html' => $html, 'has_more' => $hasMore]);
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('get_media error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['ok' => false, 'error' => 'Failed to load media']);
}
