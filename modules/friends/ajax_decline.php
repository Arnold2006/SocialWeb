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
 * ajax_decline.php — Decline a friend request
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

$ok = FriendshipService::decline((int) $currentUser['id'], $requesterId);

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Friend request declined.' : 'Could not decline request.',
]);
