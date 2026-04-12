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
 * edit_comment.php — Edit an existing comment (owner only, AJAX)
 *
 * POST params:
 *   comment_id  int     ID of the comment to edit
 *   content     string  New comment text (max 1000 chars)
 *
 * Returns JSON:
 *   { ok: true,  content: string }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$user = json_api_guard('POST');

$commentId = sanitise_int($_POST['comment_id'] ?? 0);
$content   = sanitise_string($_POST['content'] ?? '', 1000);

if ($commentId < 1 || empty($content)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

$comment = db_row(
    'SELECT id, user_id FROM comments WHERE id = ? AND is_deleted = 0',
    [$commentId]
);

if ($comment === null) {
    echo json_encode(['ok' => false, 'error' => 'Comment not found']);
    exit;
}

if ((int)$comment['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Permission denied']);
    exit;
}

db_exec(
    'UPDATE comments SET content = ?, updated_at = NOW() WHERE id = ?',
    [$content, $commentId]
);

cache_invalidate_wall();

echo json_encode(['ok' => true, 'content' => $content]);
