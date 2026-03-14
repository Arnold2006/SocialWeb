<?php
/**
 * save_post.php — Create or update a blog post.
 *
 * POST parameters:
 *   csrf_token  string   CSRF token
 *   post_id     int      0 = new post, >0 = update existing
 *   title       string   Post title (max 255 chars)
 *   content     string   Post HTML content (sanitised server-side)
 *
 * Response: JSON { ok, post_id, error }
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

csrf_verify();

$user    = current_user();
$postId  = sanitise_int($_POST['post_id'] ?? 0);
$title   = sanitise_string($_POST['title'] ?? '', 255);
$content = sanitise_html($_POST['content'] ?? '', 200000);

if ($title === '') {
    echo json_encode(['ok' => false, 'error' => 'Title is required.']);
    exit;
}

if ($content === '') {
    echo json_encode(['ok' => false, 'error' => 'Content cannot be empty.']);
    exit;
}

if ($postId > 0) {
    // Update — verify ownership
    $existing = db_row(
        'SELECT id FROM blog_posts WHERE id = ? AND user_id = ? AND is_deleted = 0',
        [$postId, (int)$user['id']]
    );
    if (!$existing) {
        echo json_encode(['ok' => false, 'error' => 'Post not found.']);
        exit;
    }
    db_exec(
        'UPDATE blog_posts SET title = ?, content = ? WHERE id = ? AND user_id = ?',
        [$title, $content, $postId, (int)$user['id']]
    );
    echo json_encode(['ok' => true, 'post_id' => $postId]);
} else {
    // Create
    $newId = db_insert(
        'INSERT INTO blog_posts (user_id, title, content) VALUES (?, ?, ?)',
        [(int)$user['id'], $title, $content]
    );

    // Create a wall post so the new blog entry appears in the activity feed
    db_insert(
        'INSERT INTO posts (user_id, content, post_type, blog_post_id) VALUES (?, ?, \'blog_post\', ?)',
        [(int)$user['id'], 'Published a new blog post: ' . $title, (int)$newId]
    );
    cache_invalidate_wall();

    echo json_encode(['ok' => true, 'post_id' => (int)$newId]);
}
