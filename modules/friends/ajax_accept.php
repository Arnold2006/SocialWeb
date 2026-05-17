<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * ajax_accept.php — Accept a friend request
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once __DIR__ . '/FriendshipService.php';

$currentUser = json_api_guard('POST');

$requesterId = sanitise_int($_POST['requester_id'] ?? 0);

if ($requesterId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user.']);
    exit;
}

$ok = FriendshipService::accept((int) $currentUser['id'], $requesterId);

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Friend request accepted.' : 'Could not accept request.',
]);
