<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * mention_search.php — Return users whose username starts with a given prefix (AJAX)
 *
 * GET params:
 *   q  string  Username prefix (1–50 chars)
 *
 * Returns JSON:
 *   { ok: true,  users: [ { id, username, avatar }, … ] }
 *   { ok: false, error: string }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$currentUser = json_api_guard('GET');

$q = sanitise_string($_GET['q'] ?? '', 50);

if ($q === '') {
    echo json_encode(['ok' => true, 'users' => []]);
    exit;
}

$rows = db_query(
    'SELECT id, username, avatar_path
     FROM users
     WHERE username LIKE ? AND is_banned = 0
     ORDER BY username ASC
     LIMIT 8',
    [$q . '%']
);

$users = [];
foreach ($rows as $row) {
    $users[] = [
        'id'       => (int)$row['id'],
        'username' => $row['username'],
        'avatar'   => avatar_url($row, 'small'),
    ];
}

echo json_encode(['ok' => true, 'users' => $users]);
