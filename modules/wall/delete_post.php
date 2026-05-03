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
 * delete_post.php — Delete a post (owner or admin only)
 *
 * Requires a POST request with a valid CSRF token to prevent CSRF attacks.
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_login();

// Only accept POST requests to prevent CSRF via GET (e.g. <img src="...?id=X">)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    flash_set('error', 'Invalid request method.');
    redirect(SITE_URL . '/pages/index.php');
}

csrf_verify();

$postId = sanitise_int($_POST['post_id'] ?? 0);
$user   = current_user();

if ($postId < 1) {
    redirect(SITE_URL . '/pages/index.php');
}

$post = db_row('SELECT * FROM posts WHERE id = ? AND is_deleted = 0', [$postId]);

if ($post === null) {
    flash_set('error', 'Post not found.');
    redirect(SITE_URL . '/pages/index.php');
}

if ((int)$post['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    flash_set('error', 'Permission denied.');
    redirect(SITE_URL . '/pages/index.php');
}

db_exec('UPDATE posts SET is_deleted = 1 WHERE id = ?', [$postId]);
cache_invalidate_wall();

flash_set('success', 'Post deleted.');
redirect(SITE_URL . '/pages/index.php');
