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
 * delete_post.php — Soft-delete a blog post.
 *
 * POST parameters:
 *   csrf_token  string   CSRF token
 *   post_id     int      ID of the post to delete
 *
 * Response: JSON { ok, error }
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

csrf_verify();

$user   = current_user();
$postId = sanitise_int($_POST['post_id'] ?? 0);

if ($postId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid post ID.']);
    exit;
}

// Verify ownership (or admin)
$post = db_row(
    'SELECT id, user_id FROM blog_posts WHERE id = ? AND is_deleted = 0',
    [$postId]
);

if (!$post) {
    echo json_encode(['ok' => false, 'error' => 'Post not found.']);
    exit;
}

if ((int)$post['user_id'] !== (int)$user['id'] && !is_admin()) {
    echo json_encode(['ok' => false, 'error' => 'Permission denied.']);
    exit;
}

db_exec(
    'UPDATE blog_posts SET is_deleted = 1 WHERE id = ?',
    [$postId]
);

echo json_encode(['ok' => true]);
