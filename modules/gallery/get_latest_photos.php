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
 * get_latest_photos.php — AJAX endpoint for the Photos hub latest-images feed.
 *
 * GET params:
 *   offset  int   Number of items already loaded (default 0)
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
$offset      = max(0, sanitise_int($_GET['offset'] ?? 0));
$limit       = 20;

// Fetch IDs of users whose photos the viewer cannot see (privacy settings)
$blockedIds = PrivacyService::blockedUsersByAction((int)$currentUser['id'], 'view_photos');

try {
    $fetchLimit = $limit + 1;

    // Build exclusion clause
    $excludeSql = '';
    $excludeParams = [];
    if (!empty($blockedIds)) {
        $ph = implode(',', array_fill(0, count($blockedIds), '?'));
        $excludeSql = " AND m.user_id NOT IN ($ph)";
        $excludeParams = $blockedIds;
    }

    $params = $excludeParams;

    $mediaRows = db_query(
        "SELECT m.id, m.user_id, m.type, m.width, m.height,
                m.thumb_path, m.medium_path, m.large_path, m.storage_path,
                m.created_at,
                u.username, u.avatar_path
         FROM media m
         JOIN albums a   ON a.id  = m.album_id  AND a.is_deleted = 0
                        AND a.privacy IN ('everybody','members')
         JOIN users u    ON u.id  = m.user_id   AND u.is_banned  = 0
         WHERE m.is_deleted = 0
           AND m.type = 'image'
           $excludeSql
         ORDER BY m.created_at DESC
         LIMIT $fetchLimit OFFSET " . (int)$offset,
        $params
    );

    $hasMore = count($mediaRows) > $limit;
    if ($hasMore) {
        array_pop($mediaRows);
    }

    ob_start();
    foreach ($mediaRows as $media) {
        $ownerUsername  = $media['username'];
        $ownerAvatarUrl = avatar_url($media, 'small');
        $galleryUrl     = SITE_URL . '/pages/gallery.php?user_id=' . (int)$media['user_id'];
        include __DIR__ . '/latest_photo_item.php';
    }
    $html = ob_get_clean() ?: '';

    echo json_encode(['ok' => true, 'html' => $html, 'has_more' => $hasMore]);
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('get_latest_photos error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to load photos']);
}
