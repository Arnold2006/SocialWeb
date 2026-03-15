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
 * admin/forum/index.php — Forum admin dashboard
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_admin();

$pageTitle = 'Forum Administration';

$stats = [
    'categories' => (int)db_val('SELECT COUNT(*) FROM forum_categories'),
    'forums'     => (int)db_val('SELECT COUNT(*) FROM forum_forums'),
    'threads'    => (int)db_val('SELECT COUNT(*) FROM forum_threads WHERE is_deleted = 0'),
    'posts'      => (int)db_val('SELECT COUNT(*) FROM forum_posts   WHERE is_deleted = 0'),
];

$recentThreads = db_query(
    'SELECT t.id, t.title, t.created_at, t.reply_count,
            u.username, f.title AS forum_title
     FROM   forum_threads t
     JOIN   users u ON u.id = t.user_id
     JOIN   forum_forums f ON f.id = t.forum_id
     WHERE  t.is_deleted = 0
     ORDER  BY t.created_at DESC
     LIMIT  10'
);

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Forum Administration</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-value"><?= $stats['categories'] ?></span>
                <span class="stat-label">Categories</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $stats['forums'] ?></span>
                <span class="stat-label">Forums</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $stats['threads'] ?></span>
                <span class="stat-label">Threads</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $stats['posts'] ?></span>
                <span class="stat-label">Posts</span>
            </div>
        </div>

        <section class="admin-section">
            <h2>Recent Threads</h2>
            <?php if (empty($recentThreads)): ?>
                <p class="muted">No threads yet.</p>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Thread</th>
                        <th>Forum</th>
                        <th>Author</th>
                        <th>Replies</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentThreads as $t): ?>
                    <tr>
                        <td><a href="<?= e(SITE_URL . '/forum/thread.php?id=' . (int)$t['id']) ?>"><?= e($t['title']) ?></a></td>
                        <td><?= e($t['forum_title']) ?></td>
                        <td><?= e($t['username']) ?></td>
                        <td><?= (int)$t['reply_count'] ?></td>
                        <td><?= e(date('Y-m-d', strtotime($t['created_at']))) ?></td>
                        <td>
                            <a href="<?= e(SITE_URL . '/admin/forum/moderation.php?thread_id=' . (int)$t['id']) ?>"
                               class="btn btn-xs btn-secondary">Moderate</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
