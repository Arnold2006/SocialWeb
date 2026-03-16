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
 * forum/thread.php — Show posts in a thread
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$threadId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($threadId <= 0) {
    redirect(SITE_URL . '/forum/index.php');
}

$thread = db_row(
    'SELECT t.id, t.title, t.is_locked, t.is_deleted, t.forum_id,
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

$pageTitle = e($thread['title']) . ' — Forum';
$user      = current_user();

// Mark this thread as read for the current user
if ($user) {
    mark_thread_read($threadId);
}

// Handle post deletion (owner or admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_post') {
    require_login();
    csrf_verify();

    $postId   = (int)($_POST['post_id'] ?? 0);
    $backPage = max(1, (int)($_POST['back_page'] ?? 1));
    if ($postId > 0) {
        $postRow = db_row(
            'SELECT id, user_id FROM forum_posts WHERE id = ? AND thread_id = ? AND is_deleted = 0',
            [$postId, $threadId]
        );
        if ($postRow && ((int)$postRow['user_id'] === (int)$user['id'] || is_admin())) {
            db_exec(
                'UPDATE forum_posts SET is_deleted = 1 WHERE id = ? AND thread_id = ?',
                [$postId, $threadId]
            );
            $count = (int)db_val('SELECT COUNT(*) FROM forum_posts WHERE thread_id = ? AND is_deleted = 0', [$threadId]);
            db_exec('UPDATE forum_threads SET reply_count = ? WHERE id = ?', [max(0, $count - 1), $threadId]);
            flash_set('success', 'Post deleted.');
        } else {
            flash_set('error', 'Permission denied.');
        }
    }
    redirect(SITE_URL . '/forum/thread.php?id=' . $threadId . '&page=' . $backPage);
}

// Handle post editing (owner or admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_post') {
    require_login();
    csrf_verify();

    $postId  = (int)($_POST['post_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $backPage = max(1, (int)($_POST['back_page'] ?? 1));

    if ($postId > 0 && $content !== '') {
        $postRow = db_row(
            'SELECT id, user_id FROM forum_posts WHERE id = ? AND thread_id = ? AND is_deleted = 0',
            [$postId, $threadId]
        );
        if ($postRow && ((int)$postRow['user_id'] === (int)$user['id'] || is_admin())) {
            db_exec(
                'UPDATE forum_posts SET content = ?, edited_at = NOW() WHERE id = ? AND thread_id = ?',
                [$content, $postId, $threadId]
            );
            flash_set('success', 'Post updated.');
            redirect(SITE_URL . '/forum/thread.php?id=' . $threadId . '&page=' . $backPage . '#post-' . $postId);
        } else {
            flash_set('error', 'Permission denied.');
        }
    } elseif ($content === '') {
        flash_set('error', 'Post content cannot be empty.');
    }
    redirect(SITE_URL . '/forum/thread.php?id=' . $threadId . '&page=' . $backPage);
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$result  = paginate(
    'SELECT p.id, p.content, p.media_id, p.created_at, p.edited_at,
            u.id AS user_id, u.username, u.avatar_path
     FROM   forum_posts p
     JOIN   users u ON u.id = p.user_id
     WHERE  p.thread_id = ? AND p.is_deleted = 0
     ORDER  BY p.created_at ASC',
    [$threadId],
    $page,
    $perPage
);

$pageScript = ASSETS_URL . '/js/forum.js';

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
        <a href="<?= SITE_URL ?>/forum/index.php"><?= e($thread['category_title']) ?></a>
        <span class="sep">›</span>
        <a href="<?= SITE_URL ?>/forum/forum.php?id=<?= (int)$thread['forum_id'] ?>"><?= e($thread['forum_title']) ?></a>
        <span class="sep">›</span>
        <span><?= e($thread['title']) ?></span>
    </nav>

    <div class="forum-header">
        <h1>
            <?php if ($thread['is_locked']): ?><span title="Locked">🔒 </span><?php endif; ?>
            <?= e($thread['title']) ?>
        </h1>
    </div>

    <?php if (empty($result['rows'])): ?>
        <p class="muted">No posts in this thread.</p>
    <?php else: ?>

    <div class="post-list">
        <?php foreach ($result['rows'] as $post): ?>
        <?php
        $postMedia = null;
        if (!empty($post['media_id'])) {
            $postMedia = db_row('SELECT * FROM media WHERE id = ? AND is_deleted = 0', [(int)$post['media_id']]);
        }
        ?>
        <div class="forum-post" id="post-<?= (int)$post['id'] ?>">
            <div class="forum-post-author">
                <a href="<?= SITE_URL ?>/pages/profile.php?id=<?= (int)$post['user_id'] ?>">
                    <img src="<?= e(avatar_url($post, 'small')) ?>"
                         alt="<?= e($post['username']) ?>"
                         class="forum-avatar"
                         width="40" height="40">
                </a>
                <a href="<?= SITE_URL ?>/pages/profile.php?id=<?= (int)$post['user_id'] ?>"
                   class="forum-post-username"><?= e($post['username']) ?></a>
            </div>
            <div class="forum-post-body">
                <div class="forum-post-meta">
                    <span class="muted"><?= e(time_ago($post['created_at'])) ?></span>
                    <?php if (!empty($post['edited_at'])): ?>
                    <span class="muted forum-post-edited" title="Edited <?= e(time_ago($post['edited_at'])) ?>">(edited)</span>
                    <?php endif; ?>
                    <?php if ($user && ((int)$post['user_id'] === (int)$user['id'] || is_admin())): ?>
                    <button type="button" class="btn btn-xs btn-secondary forum-edit-btn"
                            data-post-id="<?= (int)$post['id'] ?>">Edit</button>
                    <form method="post" action="<?= SITE_URL ?>/forum/thread.php?id=<?= (int)$threadId ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_post">
                        <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                        <input type="hidden" name="back_page" value="<?= $page ?>">
                        <button type="submit" class="btn btn-xs btn-danger"
                                onclick="return confirm('Delete this post?')">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="forum-post-content" id="post-content-<?= (int)$post['id'] ?>"><?= nl2br(e($post['content'])) ?></div>
                <?php if ($user && ((int)$post['user_id'] === (int)$user['id'] || is_admin())): ?>
                <div class="forum-post-edit-form" id="post-edit-<?= (int)$post['id'] ?>" style="display:none;">
                    <form method="post" action="<?= SITE_URL ?>/forum/thread.php?id=<?= (int)$threadId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="edit_post">
                        <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                        <input type="hidden" name="back_page" value="<?= $page ?>">
                        <div class="form-group">
                            <textarea name="content" rows="5" required
                                      class="forum-edit-textarea"><?= e($post['content']) ?></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                            <button type="button" class="btn btn-sm btn-secondary forum-edit-cancel-btn"
                                    data-post-id="<?= (int)$post['id'] ?>">Cancel</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                <?php if ($postMedia && $postMedia['type'] === 'image'): ?>
                <div class="forum-post-image">
                    <a href="<?= e(get_media_url($postMedia, 'original')) ?>" class="lightbox-trigger"
                       data-src="<?= e(get_media_url($postMedia, 'large')) ?>">
                        <img src="<?= e(get_media_url($postMedia, 'thumb')) ?>"
                             alt="Post image"
                             class="forum-post-thumb"
                             loading="lazy">
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?= pagination_links($result['page'], $result['pages'], SITE_URL . '/forum/thread.php?id=' . $threadId) ?>

    <?php endif; ?>
    <div id="post-end"></div>

    <!-- Reply form -->
    <?php if ($user && !$thread['is_locked']): ?>
    <div class="forum-reply-form">
        <h3>Post a Reply</h3>
        <form method="post" action="<?= SITE_URL ?>/forum/reply.php">
            <?= csrf_field() ?>
            <input type="hidden" name="thread_id" value="<?= (int)$threadId ?>">
            <input type="hidden" name="media_id" id="forum-media-id" value="">
            <div class="form-group">
                <label for="content">Your reply</label>
                <textarea id="content" name="content" rows="6" required
                          placeholder="Write your reply here…"></textarea>
            </div>
            <div class="forum-image-picker-wrap">
                <button type="button" class="btn btn-sm btn-secondary forum-pick-image-btn">
                    🖼️ Add Image from Gallery
                </button>
                <div id="forum-image-preview" class="forum-image-preview"></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Post Reply</button>
            </div>
        </form>
    </div>
    <?php elseif ($thread['is_locked']): ?>
    <p class="muted forum-locked-msg">🔒 This thread is locked and no longer accepts replies.</p>
    <?php elseif (!$user): ?>
    <p class="muted"><a href="<?= SITE_URL ?>/pages/login.php">Log in</a> to reply.</p>
    <?php endif; ?>

    </div><!-- /.forum-layout -->
    </main>

</div><!-- /.two-col-layout -->

<?php include SITE_ROOT . '/includes/footer.php'; ?>

