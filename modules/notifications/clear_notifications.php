<?php
/**
 * clear_notifications.php — Delete all notifications for the current user
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

csrf_verify();

$user = current_user();
$uid  = (int) $user['id'];

db_exec('DELETE FROM notifications WHERE user_id = ?', [$uid]);

echo json_encode(['ok' => true]);
