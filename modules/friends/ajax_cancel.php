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
 * ajax_cancel.php — Cancel a sent friend request or unfriend
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once __DIR__ . '/FriendshipService.php';

$currentUser = json_api_guard('POST');

$otherId = sanitise_int($_POST['other_id'] ?? 0);

if ($otherId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user.']);
    exit;
}

$ok = FriendshipService::cancel((int) $currentUser['id'], $otherId);

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Done.' : 'Could not complete action.',
]);
