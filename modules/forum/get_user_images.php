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
 * modules/forum/get_user_images.php — AJAX endpoint for forum gallery image picker.
 *
 * Returns a paginated list of the current user's gallery images.
 *
 * GET params:
 *   offset  int   Number of items already loaded (default 0)
 *
 * Returns JSON:
 *   { ok: true,  items: [{id, thumb, src, full}], has_more: bool }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$user   = current_user();
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit  = 24;

try {
    $rows = db_query(
        'SELECT id, thumb_path, medium_path, large_path, storage_path
         FROM media
         WHERE user_id = ? AND type = ? AND is_deleted = 0
         ORDER BY created_at DESC
         LIMIT ' . ($limit + 1) . ' OFFSET ' . (int)$offset,
        [(int)$user['id'], 'image']
    );

    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        array_pop($rows);
    }

    $items = array_map(static function (array $m): array {
        return [
            'id'    => (int)$m['id'],
            'thumb' => get_media_url($m, 'thumb'),
            'src'   => get_media_url($m, 'large'),
            'full'  => get_media_url($m, 'original'),
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'items' => $items, 'has_more' => $hasMore]);
} catch (Throwable $e) {
    error_log('get_user_images error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['ok' => false, 'error' => 'Failed to load images']);
}
