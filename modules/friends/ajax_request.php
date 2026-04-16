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
 * ajax_request.php — Send a friend request
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once __DIR__ . '/FriendshipService.php';

$currentUser = json_api_guard('POST');

$addresseeId = sanitise_int($_POST['addressee_id'] ?? 0);

if ($addresseeId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user.']);
    exit;
}

$ok = FriendshipService::request((int) $currentUser['id'], $addresseeId);

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Friend request sent.' : 'Could not send request.',
]);
