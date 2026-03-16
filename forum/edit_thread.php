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
 * forum/edit_thread.php — Edit a forum thread's title and opening post
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

$user     = current_user();
$threadId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($threadId <= 0) {
    redirect(SITE_URL . '/forum/index.php');
}

$thread = db_row(
    'SELECT t.id, t.title, t.forum_id, t.user_id,
            f.title AS forum_title, f.category_id,
            c.title AS category_title
     FROM   forum_threads t
     JOIN   forum_forums f ON f.id = t.forum_id
     JOIN   forum_categories c ON c.id = f.category_id
     WHERE  t.id = ? AND t.is_deleted = 0',
    [$threadId]
);
if (!$thread) {
    http_response_code(404);
    $pageTitle = 'Thread Not Found';
    include SITE_ROOT . '/includes/header.php';
    echo '<div class="forum-layout"><p class="muted">Thread not found.</p></div>';
    include SITE_ROOT . '/includes/footer.php';
    exit;
}

// Only the thread owner or an admin may edit
if ((int)$thread['user_id'] !== (int)$user['id'] && !is_admin()) {
    flash_set('error', 'Permission denied.');
    redirect(SITE_URL . '/forum/thread.php?id=' . $threadId);
}

// Fetch the opening post - only fetch a post owned by the current user (unless admin)
if (is_admin()) {
    $firstPost = db_row(
        'SELECT id, content, user_id FROM forum_posts
         WHERE thread_id = ? AND is_deleted = 0
         ORDER BY created_at ASC LIMIT 1',
        [$threadId]
    );
} else {
    $firstPost = db_row(
        'SELECT id, content, user_id FROM forum_posts
         WHERE thread_id = ? AND user_id = ? AND is_deleted = 0
         ORDER BY created_at ASC LIMIT 1',
        [$threadId, (int)$user['id']]
    );
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');
    $errors  = [];

    if ($title === '') {
        $errors[] = 'Thread title is required.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = 'Thread title may not exceed 200 characters.';
    }
    if ($firstPost && $content === '') {
        $errors[] = 'Post content is required.';
    }

    if (empty($errors)) {
        db_exec(
            'UPDATE forum_threads SET title = ? WHERE id = ?',
            [$title, $threadId]
        );
        if ($firstPost) {
            db_exec(
                'UPDATE forum_posts SET content = ?, edited_at = NOW() WHERE id = ?',
                [$content, (int)$firstPost['id']]
            );
        }
        flash_set('success', 'Thread updated successfully.');
        redirect(SITE_URL . '/forum/thread.php?id=' . $threadId);
    }

    foreach ($errors as $err) {
        flash_set('error', $err);
    }
}

$pageTitle = 'Edit Thread — Forum';

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

    <nav class="forum-breadcrumb">
        <a href="<?= SITE_URL ?>/forum/index.php">Forum</a>
        <span class="sep">›</span>
        <a href="<?= SITE_URL ?>/forum/index.php#cat-<?= (int)$thread['category_id'] ?>"><?= e($thread['category_title']) ?></a>
        <span class="sep">›</span>
        <a href="<?= SITE_URL ?>/forum/forum.php?id=<?= (int)$thread['forum_id'] ?>"><?= e($thread['forum_title']) ?></a>
        <span class="sep">›</span>
        <a href="<?= SITE_URL ?>/forum/thread.php?id=<?= (int)$threadId ?>"><?= e($thread['title']) ?></a>
        <span class="sep">›</span>
        <span>Edit Thread</span>
    </nav>

    <div class="forum-header">
        <h1>Edit Thread</h1>
    </div>

    <div class="forum-form-wrap">
        <form method="post" action="<?= SITE_URL ?>/forum/edit_thread.php?id=<?= (int)$threadId ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="title">Thread Title</label>
                <input type="text" id="title" name="title" maxlength="200" required
                       value="<?= isset($_POST['title']) ? e($_POST['title']) : e($thread['title']) ?>"
                       placeholder="Enter a descriptive title">
            </div>

            <?php if ($firstPost): ?>
            <div class="form-group">
                <label for="content">Your Post</label>
                <textarea id="content" name="content" rows="10" required
                          placeholder="Write your post here…"><?= isset($_POST['content']) ? e($_POST['content']) : e($firstPost['content']) ?></textarea>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="<?= SITE_URL ?>/forum/thread.php?id=<?= (int)$threadId ?>"
                   class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    </div><!-- /.forum-layout -->
    </main>

</div><!-- /.two-col-layout -->

<?php include SITE_ROOT . '/includes/footer.php'; ?>
