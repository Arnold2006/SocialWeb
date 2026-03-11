<?php
/**
 * moderation.php — Content moderation (posts, comments, shoutbox)
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$pageTitle = 'Admin – Moderation';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $id     = sanitise_int($_POST['id'] ?? 0);

    switch ($action) {
        case 'delete_post':
            db_exec('UPDATE posts SET is_deleted = 1 WHERE id = ?', [$id]);
            cache_invalidate_wall();
            flash_set('success', 'Post deleted.');
            break;
        case 'delete_comment':
            db_exec('UPDATE comments SET is_deleted = 1 WHERE id = ?', [$id]);
            cache_invalidate_wall();
            flash_set('success', 'Comment deleted.');
            break;
        case 'delete_shout':
            db_exec('UPDATE shoutbox SET is_deleted = 1 WHERE id = ?', [$id]);
            flash_set('success', 'Shout deleted.');
            break;
    }
    redirect(SITE_URL . '/admin/moderation.php');
}

$posts = db_query(
    'SELECT p.id, p.content, p.created_at, u.username
     FROM posts p JOIN users u ON u.id = p.user_id
     WHERE p.is_deleted = 0 ORDER BY p.created_at DESC LIMIT 30'
);

$comments = db_query(
    'SELECT c.id, c.content, c.created_at, u.username, c.post_id
     FROM comments c JOIN users u ON u.id = c.user_id
     WHERE c.is_deleted = 0 ORDER BY c.created_at DESC LIMIT 30'
);

$shouts = db_query(
    'SELECT s.id, s.message, s.created_at, u.username
     FROM shoutbox s JOIN users u ON u.id = s.user_id
     WHERE s.is_deleted = 0 ORDER BY s.created_at DESC LIMIT 30'
);

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <nav class="admin-nav">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?= SITE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php">Users</a></li>
            <li><a href="<?= SITE_URL ?>/admin/invites.php">Invites</a></li>
            <li><a href="<?= SITE_URL ?>/admin/moderation.php" class="active">Moderation</a></li>
            <li><a href="<?= SITE_URL ?>/admin/media.php">Media</a></li>
            <li><a href="<?= SITE_URL ?>/admin/settings.php">Site Settings</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>Content Moderation</h1>

        <section class="admin-section">
            <h2>Recent Posts</h2>
            <table class="admin-table">
                <thead><tr><th>#</th><th>User</th><th>Content</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($posts as $p): ?>
                    <tr>
                        <td><?= (int)$p['id'] ?></td>
                        <td><?= e($p['username']) ?></td>
                        <td><?= e(mb_substr($p['content'], 0, 80)) ?>…</td>
                        <td><?= e(date('Y-m-d', strtotime($p['created_at']))) ?></td>
                        <td>
                            <form method="POST" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_post">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-xs btn-danger" onclick="return confirm('Delete post?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="admin-section">
            <h2>Recent Comments</h2>
            <table class="admin-table">
                <thead><tr><th>#</th><th>User</th><th>Content</th><th>Post</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($comments as $c): ?>
                    <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td><?= e($c['username']) ?></td>
                        <td><?= e(mb_substr($c['content'], 0, 80)) ?>…</td>
                        <td><a href="<?= e(SITE_URL . '/pages/index.php#post-' . (int)$c['post_id']) ?>">#<?= (int)$c['post_id'] ?></a></td>
                        <td><?= e(date('Y-m-d', strtotime($c['created_at']))) ?></td>
                        <td>
                            <form method="POST" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_comment">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button class="btn btn-xs btn-danger" onclick="return confirm('Delete comment?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="admin-section">
            <h2>Shoutbox</h2>
            <table class="admin-table">
                <thead><tr><th>#</th><th>User</th><th>Message</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($shouts as $s): ?>
                    <tr>
                        <td><?= (int)$s['id'] ?></td>
                        <td><?= e($s['username']) ?></td>
                        <td><?= e($s['message']) ?></td>
                        <td><?= e(date('Y-m-d', strtotime($s['created_at']))) ?></td>
                        <td>
                            <form method="POST" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_shout">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-xs btn-danger" onclick="return confirm('Delete shout?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
