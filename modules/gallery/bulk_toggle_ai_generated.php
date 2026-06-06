<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * bulk_toggle_ai_generated.php — Set or unset AI-generated flag on ALL images in an album (AJAX)
 *
 * POST params:
 *   album_id   int   The album whose images should be updated
 *   value      int   1 = mark all as AI, 0 = remove AI flag from all
 *
 * Returns JSON:
 *   { ok: true,  is_ai_generated: bool, updated_count: int }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$user = json_api_guard('POST');

$albumId = sanitise_int($_POST['album_id'] ?? 0);
$value   = sanitise_int($_POST['value'] ?? 1);

if ($albumId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid album']);
    exit;
}

// Only the album owner can bulk-toggle the AI flag
$album = db_row(
    'SELECT id, user_id FROM albums WHERE id = ? AND is_deleted = 0',
    [$albumId]
);

if ($album === null) {
    echo json_encode(['ok' => false, 'error' => 'Album not found']);
    exit;
}

if ((int)$album['user_id'] !== (int)$user['id']) {
    echo json_encode(['ok' => false, 'error' => 'Permission denied']);
    exit;
}

$newValue = $value ? 1 : 0;

$updatedCount = db_exec(
    'UPDATE media SET is_ai_generated = ? WHERE album_id = ? AND user_id = ? AND is_deleted = 0 AND type = \'image\'',
    [$newValue, $albumId, (int)$user['id']]
);

echo json_encode([
    'ok'              => true,
    'is_ai_generated' => (bool)$newValue,
    'updated_count'   => $updatedCount,
]);
