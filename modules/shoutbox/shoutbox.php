<?php
/**
 * shoutbox.php — Shoutbox AJAX endpoint
 *
 * GET  → return latest messages as JSON
 * POST → add a new shout
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!rate_limit('shoutbox_' . (int)$user['id'], 5, 30)) {
        echo json_encode(['ok' => false, 'error' => 'Slow down!']);
        exit;
    }

    $message = sanitise_string($_POST['message'] ?? '', 500);
    if (empty($message)) {
        echo json_encode(['ok' => false, 'error' => 'Empty message']);
        exit;
    }

    db_insert(
        'INSERT INTO shoutbox (user_id, message) VALUES (?, ?)',
        [(int)$user['id'], $message]
    );
}

// Return latest 20 messages
$messages = db_query(
    'SELECT s.id, s.message, s.created_at, u.username, u.id AS user_id, u.avatar_path
     FROM shoutbox s
     JOIN users u ON u.id = s.user_id
     WHERE s.is_deleted = 0
     ORDER BY s.created_at DESC
     LIMIT 20'
);

// Sanitise output (reverse so oldest is first → newest appears at bottom of the shoutbox)
$output = array_reverse(array_map(fn($s) => [
    'id'          => (int)$s['id'],
    'message'     => $s['message'],
    'time_ago'    => time_ago($s['created_at']),
    'username'    => $s['username'],
    'user_id'     => (int)$s['user_id'],
    'avatar_url'  => avatar_url($s, 'small'),
    'profile_url' => SITE_URL . '/pages/profile.php?id=' . (int)$s['user_id'],
], $messages));

echo json_encode(['ok' => true, 'messages' => $output]);
