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
    $limitSql  = (int) ($limit + 1);   // fetch one extra to detect if more posts exist
    $offsetSql = (int) $offset;

    $posts = db_query(
        "SELECT p.*, u.username, u.avatar_path,
                (SELECT COUNT(DISTINCT user_id) FROM likes WHERE post_id = p.id OR (p.media_id IS NOT NULL AND media_id = p.media_id)) AS like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND is_deleted = 0) +
                    CASE WHEN p.media_id IS NOT NULL THEN (SELECT COUNT(*) FROM comments WHERE media_id = p.media_id AND is_deleted = 0) ELSE 0 END +
                    CASE WHEN p.media_ids IS NOT NULL THEN (SELECT COUNT(*) FROM comments c2 WHERE c2.media_id IS NOT NULL AND c2.is_deleted = 0 AND JSON_CONTAINS(p.media_ids, CAST(c2.media_id AS CHAR))) ELSE 0 END AS comment_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) +
                    CASE WHEN p.media_id IS NOT NULL THEN (SELECT COUNT(*) FROM likes WHERE media_id = p.media_id AND user_id = ?) ELSE 0 END AS user_liked
         FROM posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.user_id = ? AND p.is_deleted = 0
         ORDER BY p.created_at DESC
         LIMIT {$limitSql} OFFSET {$offsetSql}",
        [(int)$user['id'], (int)$user['id'], $profileId]
    );

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
