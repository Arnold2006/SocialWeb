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
    $tab = $_POST['tab'] ?? 'posts';
    redirect(SITE_URL . '/admin/moderation.php?tab=' . urlencode($tab));
}

$activeTab = $_GET['tab'] ?? 'posts';
if (!in_array($activeTab, ['posts', 'comments', 'shoutbox'], true)) {
    $activeTab = 'posts';
}

$posts = $comments = $shouts = [];

if ($activeTab === 'posts') {
    $posts = db_query(
        'SELECT p.id, p.content, p.created_at, u.username
         FROM posts p JOIN users u ON u.id = p.user_id
         WHERE p.is_deleted = 0 ORDER BY p.created_at DESC LIMIT 30'
    );
} elseif ($activeTab === 'comments') {
    $comments = db_query(
        'SELECT c.id, c.content, c.created_at, u.username, c.post_id
         FROM comments c JOIN users u ON u.id = c.user_id
         WHERE c.is_deleted = 0 ORDER BY c.created_at DESC LIMIT 30'
    );
} elseif ($activeTab === 'shoutbox') {
    $shouts = db_query(
        'SELECT s.id, s.message, s.created_at, u.username
         FROM shoutbox s JOIN users u ON u.id = s.user_id
         WHERE s.is_deleted = 0 ORDER BY s.created_at DESC LIMIT 30'
    );
}

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
            <li><a href="<?= SITE_URL ?>/admin/orphans.php">Orphan Cleanup</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
            <li><a href="<?= SITE_URL ?>/admin/forum/index.php">Forum Administration</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>Content Moderation</h1>

        <?= flash_render() ?>

        <!-- Tab navigation -->
        <div class="photos-tabs" style="margin: 0 0 1.25rem">
            <a href="<?= SITE_URL ?>/admin/moderation.php?tab=posts"
               class="photos-tab-btn<?= $activeTab === 'posts' ? ' active' : '' ?>">Posts</a>
            <a href="<?= SITE_URL ?>/admin/moderation.php?tab=comments"
               class="photos-tab-btn<?= $activeTab === 'comments' ? ' active' : '' ?>">Comments</a>
            <a href="<?= SITE_URL ?>/admin/moderation.php?tab=shoutbox"
               class="photos-tab-btn<?= $activeTab === 'shoutbox' ? ' active' : '' ?>">Shoutbox</a>
        </div>

        <?php if ($activeTab === 'posts'): ?>
        <section class="admin-section">
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
                                <input type="hidden" name="tab" value="posts">
                                <button class="btn btn-xs btn-danger" data-confirm="Delete post?">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($posts)): ?>
                    <tr><td colspan="5" class="text-muted" style="text-align:center">No posts found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <?php elseif ($activeTab === 'comments'): ?>
        <section class="admin-section">
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
                                <input type="hidden" name="tab" value="comments">
                                <button class="btn btn-xs btn-danger" data-confirm="Delete comment?">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($comments)): ?>
                    <tr><td colspan="6" class="text-muted" style="text-align:center">No comments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <?php elseif ($activeTab === 'shoutbox'): ?>
        <section class="admin-section">
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
                                <input type="hidden" name="tab" value="shoutbox">
                                <button class="btn btn-xs btn-danger" data-confirm="Delete shout?">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($shouts)): ?>
                    <tr><td colspan="5" class="text-muted" style="text-align:center">No shouts found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
