<?php
/**
 * get_notifications.php — Return unread count as JSON (AJAX polling)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'count' => 0]);
    exit;
}

$user    = current_user();
$notifs  = (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [(int)$user['id']]);
$msgs    = (int) db_val('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0 AND is_deleted_receiver = 0', [(int)$user['id']]);

echo json_encode(['ok' => true, 'notifications' => $notifs, 'messages' => $msgs]);
