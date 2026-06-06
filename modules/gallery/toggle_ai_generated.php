<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * toggle_ai_generated.php — Toggle the AI-generated flag on a media item (AJAX)
 *
 * POST params:
 *   media_id   int   The media item to update
 *
 * Returns JSON:
 *   { ok: true,  is_ai_generated: bool }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$user = json_api_guard('POST');

$mediaId = sanitise_int($_POST['media_id'] ?? 0);

if ($mediaId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid media']);
    exit;
}

// Only the owner can toggle the AI flag
$media = db_row(
    'SELECT id, user_id, is_ai_generated FROM media WHERE id = ? AND is_deleted = 0',
    [$mediaId]
);

if ($media === null) {
    echo json_encode(['ok' => false, 'error' => 'Media not found']);
    exit;
}

if ((int)$media['user_id'] !== (int)$user['id']) {
    echo json_encode(['ok' => false, 'error' => 'Permission denied']);
    exit;
}

$newValue = empty($media['is_ai_generated']) ? 1 : 0;

db_exec('UPDATE media SET is_ai_generated = ? WHERE id = ?', [$newValue, $mediaId]);

echo json_encode([
    'ok'              => true,
    'is_ai_generated' => (bool)$newValue,
]);
