<?php
/**
 * load_posts.php — AJAX endpoint to load more wall posts.
 *
 * GET params:
 *   offset  int  Number of posts already loaded (default 0)
 *
 * Returns JSON:
 *   { ok: true,  html: string, has_more: bool }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$user   = current_user();
$limit  = 10;
$offset = max(0, sanitise_int($_GET['offset'] ?? 0));

try {
    $limitSql  = (int) ($limit + 1);   // fetch one extra to detect if more posts exist
    $offsetSql = (int) $offset;

    $posts = db_query(
        "SELECT p.*, u.username, u.avatar_path,
                (SELECT COUNT(*) FROM likes   WHERE post_id = p.id) AS like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND is_deleted = 0) AS comment_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) AS user_liked
         FROM posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.is_deleted = 0
         ORDER BY p.created_at DESC
         LIMIT {$limitSql} OFFSET {$offsetSql}",
        [$user['id']]
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
    error_log('Load more posts error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['ok' => false, 'error' => 'Failed to load posts']);
}
