<?php
/**
 * delete_account.php — Permanently delete the current user's account and all data.
 *
 * Three-stage verification is enforced on the client side (modal wizard) and
 * the password is re-verified here as the final server-side guard.
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    flash_set('error', 'Invalid request method.');
    redirect(SITE_URL . '/pages/index.php');
}

csrf_verify();

$user = current_user();
$userId = (int) $user['id'];

// ── Verify confirmation text ──────────────────────────────────────────────────
$confirmText = $_POST['confirm_text'] ?? '';
if ($confirmText !== 'DELETE') {
    flash_set('error', 'Please type DELETE to confirm account deletion.');
    redirect(SITE_URL . '/pages/profile.php?id=' . $userId);
}

// ── Verify password ───────────────────────────────────────────────────────────
$password = $_POST['delete_password'] ?? '';
$userRow  = db_row('SELECT password FROM users WHERE id = ?', [$userId]);
if ($userRow === null || !password_verify($password, $userRow['password'])) {
    flash_set('error', 'Incorrect password. Account deletion cancelled.');
    redirect(SITE_URL . '/pages/profile.php?id=' . $userId);
}

// ── Delete physical media files ───────────────────────────────────────────────

// 1. Delete all media files (images/videos with all size variants).
//    media_delete_files() skips a file when another non-deleted media row
//    still references the same path (deduplication safety).  By marking this
//    user's rows as deleted first, the ref-count drops to zero for files that
//    are not shared, so those files will be physically removed.
$mediaRows = db_query(
    'SELECT * FROM media WHERE user_id = ? AND is_deleted = 0',
    [$userId]
);
foreach ($mediaRows as $media) {
    db_exec('UPDATE media SET is_deleted = 1 WHERE id = ?', [(int) $media['id']]);
    media_delete_files($media);
}
// Hard-delete all media rows (including any previously soft-deleted ones)
db_exec('DELETE FROM media WHERE user_id = ?', [$userId]);

// 2. Delete chat message images
$uploadsReal = realpath(UPLOADS_DIR);
$chatImages  = db_query(
    'SELECT image_path FROM chat_messages WHERE sender_id = ? AND image_path IS NOT NULL',
    [$userId]
);
foreach ($chatImages as $row) {
    // Validate path is within uploads directory before deleting (path-traversal prevention)
    $absPath = SITE_ROOT . $row['image_path'];
    $real    = realpath($absPath);
    if ($real !== false
        && $uploadsReal !== false
        && str_starts_with($real, $uploadsReal . DIRECTORY_SEPARATOR)) {
        @unlink($real);
    }
}

// 3. Delete avatar files
if (!empty($user['avatar_path'])) {
    avatar_delete_files($user['avatar_path']);
}

// 4. Delete album cover images
$albumCovers = db_query(
    'SELECT cover_path FROM albums WHERE user_id = ? AND cover_path IS NOT NULL',
    [$userId]
);
foreach ($albumCovers as $row) {
    cover_delete_file($row['cover_path']);
}

// ── Delete database records ───────────────────────────────────────────────────

// Likes by the user
db_exec('DELETE FROM likes WHERE user_id = ?', [$userId]);

// Comments by the user (hard delete)
db_exec('DELETE FROM comments WHERE user_id = ?', [$userId]);

// Wall posts by the user
db_exec('DELETE FROM posts WHERE user_id = ?', [$userId]);

// Albums owned by the user
db_exec('DELETE FROM albums WHERE user_id = ?', [$userId]);

// Notifications sent to or from the user
db_exec('DELETE FROM notifications WHERE user_id = ? OR from_user_id = ?', [$userId, $userId]);

// Shoutbox entries
db_exec('DELETE FROM shoutbox WHERE user_id = ?', [$userId]);

// Legacy messages (both sent and received)
db_exec('DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?', [$userId, $userId]);

// Chat messages and conversations where the user participated.
// Delete all messages in every conversation the user was part of, then
// remove those conversations.  This avoids leaving conversation rows that
// reference a now-deleted user ID.
$conversationIds = db_query(
    'SELECT id FROM conversations WHERE user1_id = ? OR user2_id = ?',
    [$userId, $userId]
);
foreach ($conversationIds as $conv) {
    $convId = (int) $conv['id'];
    db_exec('DELETE FROM chat_messages WHERE conversation_id = ?', [$convId]);
    db_exec('DELETE FROM conversations WHERE id = ?', [$convId]);
}

// Active sessions
db_exec('DELETE FROM user_sessions WHERE user_id = ?', [$userId]);

// The user record itself
db_exec('DELETE FROM users WHERE id = ?', [$userId]);

// Invalidate wall cache so deleted posts disappear immediately
cache_invalidate_wall();

// ── Log out ───────────────────────────────────────────────────────────────────
logout();

flash_set('success', 'Your account has been permanently deleted. We are sorry to see you go.');
redirect(SITE_URL . '/pages/login.php');
