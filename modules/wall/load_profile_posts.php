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
 * load_profile_posts.php — AJAX endpoint to load more posts on a user's profile.
 *
 * GET params:
 *   user_id  int  Profile owner's user ID
 *   offset   int  Number of posts already loaded (default 0)
 *
 * Returns JSON:
 *   { ok: true,  html: string, has_more: bool }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$user      = current_user();
$profileId = max(0, sanitise_int($_GET['user_id'] ?? 0));
$limit     = 10;
$offset    = max(0, sanitise_int($_GET['offset'] ?? 0));

if ($profileId < 1) {
    echo json_encode(['ok' => false, 'error' => 'Invalid user']);
    exit;
}

try {
    $posts = fetch_wall_posts((int) $user['id'], $limit + 1, $offset, $profileId);

    $hasMore = count($posts) > $limit;
    if ($hasMore) {
        array_pop($posts);   // remove the extra sentinel row before rendering
    }

    ob_start();
    foreach ($posts as $post) {
        include SITE_ROOT . '/modules/wall/post_item.php';
    }
    $html = ob_get_clean() ?: '';

    echo json_encode(['ok' => true, 'html' => $html, 'has_more' => $hasMore]);
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('Load more profile posts error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['ok' => false, 'error' => 'Failed to load posts']);
}
