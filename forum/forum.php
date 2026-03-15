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
 * forum/forum.php — Show threads in a specific forum
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$forumId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($forumId <= 0) {
    redirect(SITE_URL . '/forum/index.php');
}

$forum = db_row(
    'SELECT f.id, f.title, f.description, c.title AS category_title, c.id AS category_id
     FROM   forum_forums f
     JOIN   forum_categories c ON c.id = f.category_id
     WHERE  f.id = ?',
    [$forumId]
);
if (!$forum) {
    http_response_code(404);
    $pageTitle = 'Forum Not Found';
    include SITE_ROOT . '/includes/header.php';
    echo '<div class="forum-layout"><p class="muted">Forum not found.</p></div>';
    include SITE_ROOT . '/includes/footer.php';
    exit;
}

$pageTitle = e($forum['title']) . ' — Forum';
$user      = current_user();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$result  = paginate(
    'SELECT t.id, t.title, t.created_at, t.last_post_at, t.reply_count, t.is_locked,
            u.id AS user_id, u.username AS author
     FROM   forum_threads t
     JOIN   users u ON u.id = t.user_id
     WHERE  t.forum_id = ? AND t.is_deleted = 0
     ORDER  BY t.last_post_at DESC',
    [$forumId],
    $page,
    $perPage
);

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

    <!-- Breadcrumb -->
    <nav class="forum-breadcrumb">
        <a href="<?= SITE_URL ?>/forum/index.php">Forum</a>
        <span class="sep">›</span>
        <a href="<?= SITE_URL ?>/forum/index.php#cat-<?= (int)$forum['category_id'] ?>"><?= e($forum['category_title']) ?></a>
        <span class="sep">›</span>
        <span><?= e($forum['title']) ?></span>
    </nav>

    <div class="forum-header">
        <div>
            <h1><?= e($forum['title']) ?></h1>
            <?php if ($forum['description']): ?>
            <p class="muted"><?= e($forum['description']) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($user): ?>
        <a href="<?= SITE_URL ?>/forum/new_thread.php?forum_id=<?= (int)$forum['id'] ?>" class="btn btn-primary">New Thread</a>
        <?php else: ?>
        <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-secondary">Log in to post</a>
        <?php endif; ?>
    </div>

    <?php if (empty($result['rows'])): ?>
        <p class="muted">No threads yet. <?php if ($user): ?><a href="<?= SITE_URL ?>/forum/new_thread.php?forum_id=<?= (int)$forum['id'] ?>">Start one!</a><?php endif; ?></p>
    <?php else: ?>

    <div class="thread-list">
        <div class="thread-list-header">
            <span>Thread</span>
            <span class="hidden-sm">Author</span>
            <span>Replies</span>
            <span class="hidden-sm">Last Post</span>
        </div>
        <?php foreach ($result['rows'] as $thread): ?>
        <div class="thread-item<?= $thread['is_locked'] ? ' thread-locked' : '' ?>">
            <div class="thread-title-col">
                <?php if ($thread['is_locked']): ?>
                <span class="thread-lock-icon" title="Locked">🔒</span>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>/forum/thread.php?id=<?= (int)$thread['id'] ?>" class="thread-title">
                    <?= e($thread['title']) ?>
                </a>
                <span class="muted thread-date hidden-sm"><?= e(date('M j, Y', strtotime($thread['created_at']))) ?></span>
            </div>
            <div class="thread-author hidden-sm">
                <a href="<?= SITE_URL ?>/pages/profile.php?id=<?= (int)$thread['user_id'] ?>" class="muted"><?= e($thread['author']) ?></a>
            </div>
            <div class="thread-replies">
                <?= (int)$thread['reply_count'] ?>
            </div>
            <div class="thread-last-post hidden-sm">
                <span class="muted"><?= e(time_ago($thread['last_post_at'])) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?= pagination_links($result['page'], $result['pages'], SITE_URL . '/forum/forum.php?id=' . $forumId) ?>

    <?php endif; ?>

    </div><!-- /.forum-layout -->
    </main>

</div><!-- /.two-col-layout -->

<?php include SITE_ROOT . '/includes/footer.php'; ?>
