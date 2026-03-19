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
 * functions/notifications.php — Notification & unread count helpers
 */

declare(strict_types=1);

/**
 * Count unread notifications for the current user.
 */
function unread_notifications_count(): int
{
    $user = current_user();
    if (!$user) return 0;
    return (int) db_val(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
        [$user['id']]
    );
}

/**
 * Count unread messages for the current user.
 */
function unread_messages_count(): int
{
    $user = current_user();
    if (!$user) return 0;
    return (int) db_val(
        'SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0 AND is_deleted_receiver = 0',
        [$user['id']]
    );
}

/**
 * Count unread chat messages (from the real-time chat system) for the current user.
 */
function unread_chat_count(): int
{
    $user = current_user();
    if (!$user) return 0;
    // Count messages in conversations where the current user is NOT the sender
    return (int) db_val(
        'SELECT COUNT(*)
         FROM   chat_messages cm
         JOIN   conversations c ON c.id = cm.conversation_id
         WHERE  (c.user1_id = ? OR c.user2_id = ?)
           AND  cm.sender_id != ?
           AND  cm.is_read   = 0',
        [$user['id'], $user['id'], $user['id']]
    );
}

/**
 * Count forums that have unread threads for the current user.
 */
function unread_forum_count(): int
{
    $user = current_user();
    if (!$user) return 0;
    return (int) db_val(
        'SELECT COUNT(DISTINCT t.forum_id)
         FROM   forum_threads t
         LEFT   JOIN forum_reads fr ON fr.thread_id = t.id AND fr.user_id = ?
         WHERE  t.is_deleted = 0
           AND  (fr.read_at IS NULL OR t.last_post_at > fr.read_at)',
        [$user['id']]
    );
}

/**
 * Mark a forum thread as read for the current user (upsert).
 */
function mark_thread_read(int $threadId): void
{
    $user = current_user();
    if (!$user) return;
    db_exec(
        'INSERT INTO forum_reads (user_id, thread_id, read_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE read_at = NOW()',
        [$user['id'], $threadId]
    );
}

/**
 * Create a notification for a user, skipping self-notifications.
 *
 * @param int    $recipientId  User to notify
 * @param string $type         Notification type (e.g. 'like', 'comment', 'message')
 * @param int    $fromUserId   ID of the user performing the action
 * @param int    $refId        Reference ID (post, comment, conversation, etc.)
 */
function notify_user(int $recipientId, string $type, int $fromUserId, int $refId): void
{
    if ($recipientId === $fromUserId) {
        return;
    }
    db_insert(
        'INSERT INTO notifications (user_id, type, from_user_id, ref_id) VALUES (?, ?, ?, ?)',
        [$recipientId, $type, $fromUserId, $refId]
    );
}

/**
 * Count pending (unapplied) database migrations.
 *
 * Scans database/migrations/ for *.sql files not yet recorded in db_migrations.
 * Returns 0 if the db_migrations table does not exist yet (fresh install).
 */
function pending_migrations_count(): int
{
    $dir   = APP_ROOT . '/database/migrations';
    $files = is_dir($dir) ? (glob($dir . '/*.sql') ?: []) : [];
    if (empty($files)) {
        return 0;
    }
    try {
        $applied = db_query('SELECT migration FROM db_migrations');
        $applied = array_column($applied, 'migration');
    } catch (\Throwable $e) {
        // db_migrations table does not exist yet (fresh install before setup.php is run)
        return 0;
    }
    $count = 0;
    foreach ($files as $file) {
        if (!in_array(basename($file), $applied, true)) {
            $count++;
        }
    }
    return $count;
}
