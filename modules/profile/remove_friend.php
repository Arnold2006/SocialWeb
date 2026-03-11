<?php
/**
 * remove_friend.php — Remove a friend connection
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(SITE_URL . '/pages/index.php');
csrf_verify();

$user     = current_user();
$friendId = sanitise_int($_POST['friend_id'] ?? 0);

db_exec(
    'DELETE FROM friends
     WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)',
    [(int)$user['id'], $friendId, $friendId, (int)$user['id']]
);

flash_set('success', 'Friend removed.');
redirect(SITE_URL . '/pages/profile.php?id=' . $friendId);
