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
 * move_media.php — Move a wall post's image to a different album (owner only).
 *
 * POST params:
 *   media_id        int   ID of the media item to move
 *   target_album_id int   ID of the destination album
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    redirect(SITE_URL . '/pages/index.php');
}

csrf_verify();

$user          = current_user();
$mediaId       = sanitise_int($_POST['media_id'] ?? 0);
$targetAlbumId = sanitise_int($_POST['target_album_id'] ?? 0);

if ($mediaId < 1 || $targetAlbumId < 1) {
    flash_set('error', 'Invalid parameters.');
    redirect(SITE_URL . '/pages/index.php');
}

// Verify the media belongs to the current user
$media = db_row(
    'SELECT id FROM media WHERE id = ? AND user_id = ? AND is_deleted = 0',
    [$mediaId, (int)$user['id']]
);

if (!$media) {
    flash_set('error', 'Media not found.');
    redirect(SITE_URL . '/pages/index.php');
}

// Verify the target album belongs to the current user
$targetAlbum = db_row(
    'SELECT id FROM albums WHERE id = ? AND user_id = ? AND is_deleted = 0',
    [$targetAlbumId, (int)$user['id']]
);

if (!$targetAlbum) {
    flash_set('error', 'Album not found.');
    redirect(SITE_URL . '/pages/index.php');
}

db_exec(
    'UPDATE media SET album_id = ? WHERE id = ? AND user_id = ?',
    [$targetAlbumId, $mediaId, (int)$user['id']]
);

flash_set('success', 'Image moved to album.');

// Redirect back to where the user came from, staying within this site
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if ($referer && strpos($referer, SITE_URL) === 0) {
    redirect($referer);
}
redirect(SITE_URL . '/pages/index.php');
