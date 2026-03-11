<?php
/**
 * accept_friend.php — Accept a friend request
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(SITE_URL . '/pages/index.php');
csrf_verify();

$user      = current_user();
$requestId = sanitise_int($_POST['request_id'] ?? 0);

$req = db_row(
    'SELECT * FROM friends WHERE id = ? AND friend_id = ? AND status = "pending"',
    [$requestId, (int)$user['id']]
);

if ($req === null) {
    flash_set('error', 'Request not found.');
    redirect(SITE_URL . '/pages/notifications.php');
}

db_exec(
    'UPDATE friends SET status = "accepted", accepted_at = NOW() WHERE id = ?',
    [$requestId]
);

flash_set('success', 'Friend request accepted.');
redirect(SITE_URL . '/pages/profile.php?id=' . (int)$req['user_id']);
