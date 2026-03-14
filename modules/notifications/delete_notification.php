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
 * delete_notification.php — Delete a single notification (owner only)
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

$user    = current_user();
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
