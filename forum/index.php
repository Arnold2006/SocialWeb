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
 * forum/index.php — Forum index: list all categories and their forums
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Forum';
$user          = current_user();
$userId        = $user ? (int)$user['id'] : 0;
$userCreatedAt = $user ? $user['created_at'] : '1970-01-01 00:00:00';

$categories = db_query(
    'SELECT c.id, c.title, c.description
     FROM   forum_categories c
     ORDER  BY c.sort_order ASC, c.id ASC'
);

foreach ($categories as &$cat) {
    $cat['forums'] = db_query(
        'SELECT f.id, f.title, f.description,
                COUNT(DISTINCT t.id) AS thread_count,
                COUNT(DISTINCT p.id) AS post_count,
                MAX(t.last_post_at)  AS last_post_at,
                u.username           AS last_poster,
                SUM(CASE WHEN ? > 0 AND t.id IS NOT NULL
                              AND t.last_post_at > IFNULL(fr.read_at, ?)
                         THEN 1 ELSE 0 END) AS unread_count
         FROM   forum_forums f
         LEFT   JOIN forum_threads t ON t.forum_id = f.id AND t.is_deleted = 0
         LEFT   JOIN forum_posts   p ON p.thread_id = t.id AND p.is_deleted = 0
         LEFT   JOIN (
             SELECT thread_id, user_id
             FROM   forum_posts
             WHERE  is_deleted = 0
             ORDER  BY created_at DESC
         ) lp ON lp.thread_id = t.id
         LEFT   JOIN users u ON u.id = lp.user_id
         LEFT   JOIN forum_reads fr ON fr.thread_id = t.id AND fr.user_id = ?
         WHERE  f.category_id = ?
         GROUP  BY f.id, f.title, f.description
         ORDER  BY f.sort_order ASC, f.id ASC',
        [$userId, $userCreatedAt, $userId, $cat['id']]
    );
}
unset($cat);

include SITE_ROOT . '/includes/header.php';
?>

<div class="two-col-layout">

    <!-- ── Left Column ─────────────────────────────────────── -->
    <aside class="col-left">
        <?php include SITE_ROOT . '/includes/sidebar_widgets.php'; ?>
    </aside>

    <!-- ── Right Column ────────────────────────────────────── -->
    <main class="col-right">
    <div class="forum-layout">

    <div class="forum-header">
        <h1>Forum</h1>
        <?php if ($user): ?>
        <a href="<?= SITE_URL ?>/forum/new_thread.php" class="btn btn-primary">New Thread</a>
        <?php else: ?>
        <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-secondary">Log in to post</a>
        <?php endif; ?>
    </div>

    <?php if (empty($categories)): ?>
        <p class="muted">No forums have been created yet.</p>
    <?php endif; ?>

    <?php foreach ($categories as $cat): ?>
    <section class="forum-category">
        <h2 class="forum-category-title"><?= e($cat['title']) ?></h2>
        <?php if ($cat['description']): ?>
        <p class="forum-category-desc muted"><?= e($cat['description']) ?></p>
        <?php endif; ?>

        <?php if (empty($cat['forums'])): ?>
            <p class="muted forum-empty">No forums in this category yet.</p>
        <?php else: ?>
        <div class="forum-list">
            <?php foreach ($cat['forums'] as $forum): ?>
            <div class="forum-item<?= (int)$forum['unread_count'] > 0 ? ' has-unread' : '' ?>">
                <div class="forum-icon"><?= (int)$forum['unread_count'] > 0 ? '🔵' : '💬' ?></div>
                <div class="forum-info">
                    <a href="<?= SITE_URL ?>/forum/forum.php?id=<?= (int)$forum['id'] ?>" class="forum-title">
                        <?= e($forum['title']) ?>
                        <?php if ((int)$forum['unread_count'] > 0): ?>
                        <span class="badge"><?= (int)$forum['unread_count'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($forum['description']): ?>
                    <p class="forum-desc muted"><?= e($forum['description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="forum-stats">
                    <span class="stat-item"><?= (int)$forum['thread_count'] ?> threads</span>
                    <span class="stat-item"><?= (int)$forum['post_count'] ?> posts</span>
                </div>
                <div class="forum-last-post">
                    <?php if ($forum['last_post_at']): ?>
                    <span class="muted"><?= e(time_ago($forum['last_post_at'])) ?></span>
                    <?php if ($forum['last_poster']): ?>
                    <span class="muted"> by <?= e($forum['last_poster']) ?></span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="muted">No posts yet</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>

    </div><!-- /.forum-layout -->
    </main>

</div><!-- /.two-col-layout -->

<?php include SITE_ROOT . '/includes/footer.php'; ?>
