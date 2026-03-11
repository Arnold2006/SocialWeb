<?php
/**
 * friend_request.php — Send a friend request
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(SITE_URL . '/pages/index.php');
csrf_verify();

$user   = current_user();
$toUser = sanitise_int($_POST['to_user'] ?? 0);

if ($toUser < 1 || $toUser === (int)$user['id']) {
    redirect(SITE_URL . '/pages/index.php');
}

$target = db_row('SELECT id, username FROM users WHERE id = ? AND is_banned = 0', [$toUser]);
if (!$target) {
    flash_set('error', 'User not found.');
    redirect(SITE_URL . '/pages/members.php');
}

// Check existing
$existing = db_row(
    'SELECT id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)',
    [(int)$user['id'], $toUser, $toUser, (int)$user['id']]
);

if (!$existing) {
    db_insert(
        'INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, "pending")',
        [(int)$user['id'], $toUser]
    );

    db_insert(
        'INSERT INTO notifications (user_id, type, from_user_id, ref_id) VALUES (?, "friend_request", ?, ?)',
        [$toUser, (int)$user['id'], (int)$user['id']]
    );

    flash_set('success', 'Friend request sent to ' . $target['username'] . '.');
} else {
    flash_set('info', 'Friend request already exists.');
}

redirect(SITE_URL . '/pages/profile.php?id=' . $toUser);
