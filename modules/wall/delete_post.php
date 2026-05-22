<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
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

// ---------------------------------------------------------------
// Collect all media IDs directly attached to this post.
// posts.media_id  — single media item on a 'user' post
// posts.media_ids — JSON array of IDs on an 'album_upload' post
// ---------------------------------------------------------------
$postMediaIds = [];

if (!empty($post['media_id'])) {
    $postMediaIds[] = (int)$post['media_id'];
}

if (!empty($post['media_ids'])) {
    $decoded = json_decode($post['media_ids'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $mid) {
            $mid = (int)$mid;
            if ($mid > 0) {
                $postMediaIds[] = $mid;
            }
        }
    }
}

$postMediaIds = array_values(array_unique($postMediaIds));

// ---------------------------------------------------------------
// Also collect image_media_id values from comments on this post
// so that comment image attachments are cleaned up too.
// ---------------------------------------------------------------
$commentRows = db_query(
    'SELECT id, image_media_id FROM comments WHERE post_id = ?',
    [$postId]
);

$commentIds        = [];
$commentMediaIds   = [];
foreach ($commentRows as $row) {
    $commentIds[] = (int)$row['id'];
    if (!empty($row['image_media_id'])) {
        $commentMediaIds[] = (int)$row['image_media_id'];
    }
}

// ---------------------------------------------------------------
// Soft-delete the post and all its comments.
// ---------------------------------------------------------------
db_exec('UPDATE posts SET is_deleted = 1 WHERE id = ?', [$postId]);
db_exec('UPDATE comments SET is_deleted = 1 WHERE post_id = ?', [$postId]);

// ---------------------------------------------------------------
// Soft-delete all media attached to the post (regardless of what
// album they were moved to after the initial wall upload).
// Also soft-delete media used as comment image attachments.
// ---------------------------------------------------------------
$allMediaIds = array_values(array_unique(array_merge($postMediaIds, $commentMediaIds)));
if (!empty($allMediaIds)) {
    $phs = implode(',', array_fill(0, count($allMediaIds), '?'));
    db_exec("UPDATE media SET is_deleted = 1 WHERE id IN ($phs)", $allMediaIds);
}

// ---------------------------------------------------------------
// Hard-delete likes on the post and on any attached media.
// ---------------------------------------------------------------
db_exec('DELETE FROM likes WHERE post_id = ?', [$postId]);

if (!empty($postMediaIds)) {
    $phs = implode(',', array_fill(0, count($postMediaIds), '?'));
    db_exec("DELETE FROM likes WHERE media_id IN ($phs)", $postMediaIds);
}

// Remove likes on the comments themselves.
if (!empty($commentIds)) {
    $phs = implode(',', array_fill(0, count($commentIds), '?'));
    db_exec("DELETE FROM likes WHERE comment_id IN ($phs)", $commentIds);
}

// ---------------------------------------------------------------
// Hard-delete notifications that reference the post or its media.
// ---------------------------------------------------------------
db_exec(
    "DELETE FROM notifications WHERE type IN ('like','comment','mention','mention_post') AND ref_id = ?",
    [$postId]
);

if (!empty($postMediaIds)) {
    $phs = implode(',', array_fill(0, count($postMediaIds), '?'));
    db_exec(
        "DELETE FROM notifications WHERE type IN ('photo_like','photo_comment','mention_comment_photo') AND ref_id IN ($phs)",
        $postMediaIds
    );
}

// Notifications whose secondary_ref_id points to one of the deleted comments.
if (!empty($commentIds)) {
    $phs = implode(',', array_fill(0, count($commentIds), '?'));
    db_exec("DELETE FROM notifications WHERE secondary_ref_id IN ($phs)", $commentIds);
}

cache_invalidate_wall();

flash_set('success', 'Post deleted.');
redirect(SITE_URL . '/pages/index.php');
