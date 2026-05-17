<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * delete_notification.php — Delete a single notification (owner only)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user    = json_api_guard('POST');
$uid     = (int) $user['id'];
$notifId = sanitise_int($_POST['id'] ?? 0);

if ($notifId < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid notification ID']);
    exit;
}

$notif = db_row('SELECT id FROM notifications WHERE id = ? AND user_id = ?', [$notifId, $uid]);

if (!$notif) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Notification not found']);
    exit;
}

db_exec('DELETE FROM notifications WHERE id = ? AND user_id = ?', [$notifId, $uid]);

echo json_encode(['ok' => true]);
