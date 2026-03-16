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
 * admin/forum/moderation.php — Moderate threads and posts
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_admin();

$pageTitle = 'Forum Moderation — Forum Admin';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_thread') {
        $id = (int)($_POST['thread_id'] ?? 0);
        if ($id > 0) {
            db_exec('UPDATE forum_posts SET is_deleted = 1 WHERE thread_id = ?', [$id]);
            db_exec('UPDATE forum_threads SET is_deleted = 1 WHERE id = ?', [$id]);
            flash_set('success', 'Thread deleted.');
        }
    } elseif ($action === 'restore_thread') {
        $id = (int)($_POST['thread_id'] ?? 0);
        if ($id > 0) {
            db_exec('UPDATE forum_threads SET is_deleted = 0 WHERE id = ?', [$id]);
            flash_set('success', 'Thread restored.');
        }
    } elseif ($action === 'lock_thread') {
        $id = (int)($_POST['thread_id'] ?? 0);
        if ($id > 0) {
            db_exec('UPDATE forum_threads SET is_locked = 1 WHERE id = ?', [$id]);
            flash_set('success', 'Thread locked.');
        }
    } elseif ($action === 'unlock_thread') {
        $id = (int)($_POST['thread_id'] ?? 0);
        if ($id > 0) {
            db_exec('UPDATE forum_threads SET is_locked = 0 WHERE id = ?', [$id]);
            flash_set('success', 'Thread unlocked.');
        }
    } elseif ($action === 'delete_post') {
        $id = (int)($_POST['post_id'] ?? 0);
        if ($id > 0) {
            $threadId = (int)db_val('SELECT thread_id FROM forum_posts WHERE id = ?', [$id]);
            db_exec('UPDATE forum_posts SET is_deleted = 1 WHERE id = ?', [$id]);
            if ($threadId > 0) {
                $count = (int)db_val('SELECT COUNT(*) FROM forum_posts WHERE thread_id = ? AND is_deleted = 0', [$threadId]);
                db_exec('UPDATE forum_threads SET reply_count = ? WHERE id = ?', [max(0, $count - 1), $threadId]);
            }
            flash_set('success', 'Post deleted.');
        }
    } elseif ($action === 'restore_post') {
        $id = (int)($_POST['post_id'] ?? 0);
        if ($id > 0) {
            $threadId = (int)db_val('SELECT thread_id FROM forum_posts WHERE id = ?', [$id]);
            db_exec('UPDATE forum_posts SET is_deleted = 0 WHERE id = ?', [$id]);
            if ($threadId > 0) {
                $count = (int)db_val('SELECT COUNT(*) FROM forum_posts WHERE thread_id = ? AND is_deleted = 0', [$threadId]);
                db_exec('UPDATE forum_threads SET reply_count = ? WHERE id = ?', [max(0, $count - 1), $threadId]);
            }
            flash_set('success', 'Post restored.');
        }
    }

    redirect(SITE_URL . '/admin/forum/moderation.php' . (isset($_POST['thread_id']) ? '?thread_id=' . (int)$_POST['thread_id'] : ''));
}

// Optional filter by thread
$filterThreadId = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
$filterThread   = null;
if ($filterThreadId > 0) {
    $filterThread = db_row('SELECT id, title FROM forum_threads WHERE id = ?', [$filterThreadId]);
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Threads listing
$threadResult = paginate(
    'SELECT t.id, t.title, t.is_deleted, t.is_locked, t.reply_count, t.created_at,
            u.username, f.title AS forum_title
     FROM   forum_threads t
     JOIN   users u ON u.id = t.user_id
     JOIN   forum_forums f ON f.id = t.forum_id
     ORDER  BY t.created_at DESC',
    [],
    $page,
    $perPage
);

// Posts listing (filtered or recent)
$postResult = paginate(
    'SELECT p.id, p.content, p.is_deleted, p.created_at,
            u.username, t.title AS thread_title, t.id AS thread_id
     FROM   forum_posts p
     JOIN   users u ON u.id = p.user_id
     JOIN   forum_threads t ON t.id = p.thread_id
     ' . ($filterThreadId > 0 ? 'WHERE p.thread_id = ' . $filterThreadId : '') . '
     ORDER  BY p.created_at DESC',
    [],
    $page,
    $perPage
);

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Forum Moderation</h1>

        <?php if ($filterThread): ?>
        <p class="muted">Filtering by thread: <strong><?= e($filterThread['title']) ?></strong>
            <a href="<?= SITE_URL ?>/admin/forum/moderation.php" class="btn btn-xs btn-secondary">Clear</a>
        </p>
        <?php endif; ?>

        <!-- Threads -->
        <section class="admin-section">
            <h2>Threads</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Forum</th>
                        <th>Author</th>
                        <th>Replies</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($threadResult['rows'] as $t): ?>
                    <tr class="<?= $t['is_deleted'] ? 'row-deleted' : '' ?>">
                        <td>
                            <?php if (!$t['is_deleted']): ?>
                            <a href="<?= e(SITE_URL . '/forum/thread.php?id=' . (int)$t['id']) ?>"><?= e($t['title']) ?></a>
                            <?php else: ?>
                            <span class="muted"><?= e($t['title']) ?> <em>(deleted)</em></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($t['forum_title']) ?></td>
                        <td><?= e($t['username']) ?></td>
                        <td><?= (int)$t['reply_count'] ?></td>
                        <td>
                            <?php if ($t['is_deleted']): ?>
                            <span class="badge-danger">Deleted</span>
                            <?php elseif ($t['is_locked']): ?>
                            <span class="badge-warning">Locked</span>
                            <?php else: ?>
                            <span class="badge-success">Open</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(date('Y-m-d', strtotime($t['created_at']))) ?></td>
                        <td>
                            <?php if (!$t['is_deleted']): ?>
                            <a href="<?= e(SITE_URL . '/admin/forum/moderation.php?thread_id=' . (int)$t['id']) ?>"
                               class="btn btn-xs btn-secondary">Posts</a>
                            <?php if ($t['is_locked']): ?>
                            <form method="post" action="<?= SITE_URL ?>/admin/forum/moderation.php" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unlock_thread">
                                <input type="hidden" name="thread_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-warning">Unlock</button>
                            </form>
                            <?php else: ?>
                            <form method="post" action="<?= SITE_URL ?>/admin/forum/moderation.php" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="lock_thread">
                                <input type="hidden" name="thread_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-warning">Lock</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="<?= SITE_URL ?>/admin/forum/moderation.php" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_thread">
                                <input type="hidden" name="thread_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger"
                                        onclick="return confirm('Delete this thread?')">Delete</button>
                            </form>
                            <?php else: ?>
                            <form method="post" action="<?= SITE_URL ?>/admin/forum/moderation.php" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="restore_thread">
                                <input type="hidden" name="thread_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-success">Restore</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= pagination_links($threadResult['page'], $threadResult['pages'], SITE_URL . '/admin/forum/moderation.php') ?>
        </section>

        <!-- Posts -->
        <section class="admin-section">
            <h2><?= $filterThread ? 'Posts in: ' . e($filterThread['title']) : 'Recent Posts' ?></h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Content</th>
                        <th>Thread</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($postResult['rows'] as $p): ?>
                    <tr class="<?= $p['is_deleted'] ? 'row-deleted' : '' ?>">
                        <td class="post-excerpt"><?= e(mb_strimwidth($p['content'], 0, 80, '…')) ?></td>
                        <td>
                            <a href="<?= e(SITE_URL . '/forum/thread.php?id=' . (int)$p['thread_id']) ?>">
                                <?= e(mb_strimwidth($p['thread_title'], 0, 40, '…')) ?>
                            </a>
                        </td>
                        <td><?= e($p['username']) ?></td>
                        <td>
                            <?= $p['is_deleted']
                                ? '<span class="badge-danger">Deleted</span>'
                                : '<span class="badge-success">Visible</span>' ?>
                        </td>
                        <td><?= e(date('Y-m-d', strtotime($p['created_at']))) ?></td>
                        <td>
                            <?php if (!$p['is_deleted']): ?>
                            <form method="post" action="<?= SITE_URL ?>/admin/forum/moderation.php<?= $filterThreadId > 0 ? '?thread_id=' . $filterThreadId : '' ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_post">
                                <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                                <?php if ($filterThreadId > 0): ?>
                                <input type="hidden" name="thread_id" value="<?= $filterThreadId ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-xs btn-danger"
                                        onclick="return confirm('Delete this post?')">Delete</button>
                            </form>
                            <?php else: ?>
                            <form method="post" action="<?= SITE_URL ?>/admin/forum/moderation.php<?= $filterThreadId > 0 ? '?thread_id=' . $filterThreadId : '' ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="restore_post">
                                <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                                <?php if ($filterThreadId > 0): ?>
                                <input type="hidden" name="thread_id" value="<?= $filterThreadId ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-xs btn-success">Restore</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= pagination_links(
                $postResult['page'],
                $postResult['pages'],
                SITE_URL . '/admin/forum/moderation.php' . ($filterThreadId > 0 ? '?thread_id=' . $filterThreadId . '&' : '?')
            ) ?>
        </section>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
