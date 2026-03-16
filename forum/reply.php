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
 * forum/reply.php — Submit a reply to a thread
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/forum/index.php');
}

csrf_verify();

$user     = current_user();
$threadId = (int)($_POST['thread_id'] ?? 0);
$content  = trim($_POST['content'] ?? '');
$mediaId  = (int)($_POST['media_id'] ?? 0);

if ($threadId <= 0) {
    flash_set('error', 'Invalid thread.');
    redirect(SITE_URL . '/forum/index.php');
}

$thread = db_row(
    'SELECT id, is_locked, is_deleted FROM forum_threads WHERE id = ? AND is_deleted = 0',
    [$threadId]
);
if (!$thread) {
    flash_set('error', 'Thread not found.');
    redirect(SITE_URL . '/forum/index.php');
}
if ($thread['is_locked']) {
    flash_set('error', 'This thread is locked.');
    redirect(SITE_URL . '/forum/thread.php?id=' . $threadId);
}

$content = sanitise_html($content);
if ($content === '') {
    flash_set('error', 'Reply content cannot be empty.');
    redirect(SITE_URL . '/forum/thread.php?id=' . $threadId);
}

// Validate media_id belongs to this user (if provided)
if ($mediaId > 0) {
    $mediaCheck = db_row(
        'SELECT id FROM media WHERE id = ? AND user_id = ? AND type = ? AND is_deleted = 0',
        [$mediaId, (int)$user['id'], 'image']
    );
    if (!$mediaCheck) {
        $mediaId = 0;
    }
}

db_insert(
    'INSERT INTO forum_posts (thread_id, user_id, content, media_id, created_at) VALUES (?, ?, ?, ?, NOW())',
    [$threadId, (int)$user['id'], $content, $mediaId > 0 ? $mediaId : null]
);

db_exec(
    'UPDATE forum_threads SET reply_count = reply_count + 1, last_post_at = NOW() WHERE id = ?',
    [$threadId]
);

flash_set('success', 'Reply posted.');

// Redirect to last page of the thread
$postCount = (int)db_val(
    'SELECT COUNT(*) FROM forum_posts WHERE thread_id = ? AND is_deleted = 0',
    [$threadId]
);
$lastPage = max(1, (int)ceil($postCount / 20));

redirect(SITE_URL . '/forum/thread.php?id=' . $threadId . '&page=' . $lastPage . '#post-end');
