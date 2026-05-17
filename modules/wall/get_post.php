<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * get_post.php — AJAX endpoint to fetch a single wall post by ID.
 *
 * GET params:
 *   post_id  int  The post ID to fetch
 *
 * Returns JSON:
 *   { ok: true,  html: string }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$user   = current_user();
$postId = max(0, sanitise_int($_GET['post_id'] ?? 0));

if (!$postId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid post ID']);
    exit;
}

try {
    $posts = fetch_wall_posts((int) $user['id'], 1, 0, null, [], $postId);

    if (empty($posts)) {
        echo json_encode(['ok' => false, 'error' => 'Post not found']);
        exit;
    }

    ob_start();
    $post = $posts[0];
    include SITE_ROOT . '/modules/wall/post_item.php';
    $html = ob_get_clean() ?: '';

    echo json_encode(['ok' => true, 'html' => $html]);
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('get_post error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['ok' => false, 'error' => 'Failed to load post']);
}
