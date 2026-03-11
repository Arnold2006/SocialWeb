<?php
/**
 * get_users.php — Return JSON list of all users for the chat contact list.
 *
 * GET /chat/get_users.php
 * GET /chat/get_users.php?search=query
 *
 * Response:
 *   { ok: true, users: [ { id, username, avatar_url, unread_count } ] }
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$user   = current_user();
$uid    = (int) $user['id'];
$search = sanitise_string($_GET['search'] ?? '', 100);

$params = [$uid];
$where  = 'u.is_banned = 0 AND u.id != ?';

if ($search !== '') {
    // Escape SQL wildcard characters so the user cannot craft unintended patterns
    $safeLike = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    $where   .= ' AND u.username LIKE ?';
    $params[] = '%' . $safeLike . '%';
}

$users = db_query(
    "SELECT u.id, u.username, u.avatar_path
     FROM   users u
     WHERE  {$where}
     ORDER  BY u.username ASC",
    $params
);

// Unread counts: messages sent to the current user that have not been read yet,
// grouped by sender so we can show a badge per contact.
$unreadMap  = [];
$unreadRows = db_query(
    'SELECT cm.sender_id, COUNT(*) AS cnt
     FROM   chat_messages cm
     JOIN   conversations c ON c.id = cm.conversation_id
     WHERE  (c.user1_id = ? OR c.user2_id = ?)
       AND  cm.sender_id != ?
       AND  cm.is_read = 0
     GROUP  BY cm.sender_id',
    [$uid, $uid, $uid]
);

foreach ($unreadRows as $row) {
    $unreadMap[(int) $row['sender_id']] = (int) $row['cnt'];
}

$result = [];
foreach ($users as $u) {
    $result[] = [
        'id'           => (int) $u['id'],
        'username'     => $u['username'],
        'avatar_url'   => avatar_url($u, 'small'),
        'unread_count' => $unreadMap[(int) $u['id']] ?? 0,
    ];
}

echo json_encode(['ok' => true, 'users' => $result]);
