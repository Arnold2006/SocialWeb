<?php
/**
 * add_media_comment.php — Add a comment to a media item (AJAX)
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

csrf_verify();

$user    = current_user();
$mediaId = sanitise_int($_POST['media_id'] ?? 0);
$content = sanitise_string($_POST['content'] ?? '', 1000);

if ($mediaId < 1 || empty($content)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

$media = db_row('SELECT id, user_id FROM media WHERE id = ? AND is_deleted = 0', [$mediaId]);
if ($media === null) {
    echo json_encode(['ok' => false, 'error' => 'Media not found']);
    exit;
}

$commentId = db_insert(
    'INSERT INTO comments (media_id, user_id, content) VALUES (?, ?, ?)',
    [$mediaId, (int)$user['id'], $content]
);

// Notify media owner (if not self-comment)
if ((int)$media['user_id'] !== (int)$user['id']) {
    db_insert(
        'INSERT INTO notifications (user_id, type, from_user_id, ref_id) VALUES (?, "comment", ?, ?)',
        [(int)$media['user_id'], (int)$user['id'], (int)$commentId]
    );
}

echo json_encode([
    'ok'          => true,
    'comment_id'  => (int)$commentId,
    'username'    => $user['username'],
    'avatar'      => avatar_url($user, 'small'),
    'content'     => $content,
    'time_ago'    => 'just now',
    'profile_url' => SITE_URL . '/pages/profile.php?id=' . (int)$user['id'],
]);
