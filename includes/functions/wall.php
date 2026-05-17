<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * functions/wall.php — Wall post query helpers
 *
 * Centralises the complex post SELECT (with like/comment counts and
 * viewer-specific user_liked flag) that was previously duplicated across:
 *   pages/index.php
 *   pages/profile.php
 *   modules/wall/load_posts.php
 *   modules/wall/load_profile_posts.php
 */

declare(strict_types=1);

/**
 * Fetch wall posts with like/comment counts and a viewer-specific liked flag.
 *
 * @param int      $viewerId       Current user ID (used for the user_liked subquery)
 * @param int      $limit          Max rows to return
 * @param int      $offset         Row offset for pagination
 * @param int|null $profileUserId  When set, restricts results to a single user's posts
 *                                 and orders by created_at DESC instead of bumped_at.
 * @param int[]    $excludeUserIds User IDs whose posts must be excluded (privacy filter)
 * @return array
 */
function fetch_wall_posts(
    int $viewerId,
    int $limit,
    int $offset,
    ?int $profileUserId = null,
    array $excludeUserIds = [],
    ?int $specificPostId = null
): array {
    // The two leading params feed the user_liked correlated subqueries.
    $params = [$viewerId, $viewerId];

    $where = 'p.is_deleted = 0';

    if ($specificPostId !== null) {
        $where   .= ' AND p.id = ?';
        $params[] = $specificPostId;
    }

    if ($profileUserId !== null) {
        $where   .= ' AND p.user_id = ?';
        $params[] = $profileUserId;
    }

    if (!empty($excludeUserIds)) {
        $phs     = implode(',', array_fill(0, count($excludeUserIds), '?'));
        $where  .= " AND p.user_id NOT IN ($phs)";
        $params  = array_merge($params, array_values($excludeUserIds));
    }

    // Profile feeds show the owner's own posts in strict chronological order;
    // the main wall feed uses bumped_at so that active discussions stay visible.
    $orderBy = $profileUserId !== null
        ? 'p.created_at DESC'
        : 'COALESCE(p.bumped_at, p.created_at) DESC';

    $limitSql  = (int) $limit;
    $offsetSql = (int) $offset;

    return db_query(
        "SELECT p.*, u.username, u.avatar_path,
                (SELECT COUNT(DISTINCT user_id) FROM likes
                 WHERE post_id = p.id OR (p.media_id IS NOT NULL AND media_id = p.media_id)) AS like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND is_deleted = 0) +
                    CASE WHEN p.media_id IS NOT NULL
                         THEN (SELECT COUNT(*) FROM comments WHERE media_id = p.media_id AND is_deleted = 0)
                         ELSE 0 END AS comment_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) +
                    CASE WHEN p.media_id IS NOT NULL
                         THEN (SELECT COUNT(*) FROM likes WHERE media_id = p.media_id AND user_id = ?)
                         ELSE 0 END AS user_liked
         FROM posts p
         JOIN users u ON u.id = p.user_id
         WHERE {$where}
         ORDER BY {$orderBy}
         LIMIT {$limitSql} OFFSET {$offsetSql}",
        $params
    );
}
