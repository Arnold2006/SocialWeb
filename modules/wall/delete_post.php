<?php
/**
 * delete_post.php — Delete a post (owner or admin only)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_login();

$postId = sanitise_int($_GET['id'] ?? 0);
$user   = current_user();

if ($postId < 1) {
    redirect(SITE_URL . '/pages/index.php');
}

$post = db_row('SELECT * FROM posts WHERE id = ? AND is_deleted = 0', [$postId]);

if ($post === null) {
    flash_set('error', 'Post not found.');
    redirect(SITE_URL . '/pages/index.php');
}

if ((int)$post['user_id'] !== (int)$user['id'] && !is_admin()) {
    http_response_code(403);
    flash_set('error', 'Permission denied.');
    redirect(SITE_URL . '/pages/index.php');
}

db_exec('UPDATE posts SET is_deleted = 1 WHERE id = ?', [$postId]);
cache_invalidate_wall();

flash_set('success', 'Post deleted.');
redirect(SITE_URL . '/pages/index.php');
