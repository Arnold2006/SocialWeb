<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * clear_notifications.php — Delete all notifications for the current user
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

$user = json_api_guard('POST');
$uid  = (int) $user['id'];

db_exec('DELETE FROM notifications WHERE user_id = ?', [$uid]);

echo json_encode(['ok' => true]);
