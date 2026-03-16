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
    } elseif ($firstPost && $content !== '') {
        $content = sanitise_html($content);
        if ($content === '') {
            $errors[] = 'Post content is required.';
        }
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

$pageTitle  = 'Edit Thread — Forum';
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

            <?php if ($firstPost):
                $editHtml = (strip_tags($firstPost['content']) !== $firstPost['content'])
                    ? sanitise_html($firstPost['content'])
                    : nl2br(e($firstPost['content']));
                $editHtml = isset($_POST['content']) ? sanitise_html($_POST['content']) : $editHtml;
            ?>
            <div class="form-group">
                <label>Your Post</label>
                <div class="blog-toolbar forum-editor-toolbar" role="toolbar" aria-label="Text formatting">
                    <button type="button" class="blog-tb-btn" data-cmd="bold" title="Bold"><b>B</b></button>
                    <button type="button" class="blog-tb-btn" data-cmd="italic" title="Italic"><i>I</i></button>
                    <button type="button" class="blog-tb-btn" data-cmd="underline" title="Underline"><u>U</u></button>
                    <button type="button" class="blog-tb-btn" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
                    <span class="blog-tb-sep"></span>
                    <button type="button" class="blog-tb-btn" data-cmd="formatBlock" data-val="h2" title="Heading 2">H2</button>
                    <button type="button" class="blog-tb-btn" data-cmd="formatBlock" data-val="h3" title="Heading 3">H3</button>
                    <span class="blog-tb-sep"></span>
                    <button type="button" class="blog-tb-btn" data-cmd="insertUnorderedList" title="Bullet list">&#8226;&#8212;</button>
                    <button type="button" class="blog-tb-btn" data-cmd="insertOrderedList"   title="Numbered list">1&#8212;</button>
                    <button type="button" class="blog-tb-btn" data-cmd="formatBlock" data-val="blockquote" title="Blockquote">&#10078;</button>
                    <span class="blog-tb-sep"></span>
                    <button type="button" class="blog-tb-btn forum-editor-link-btn" title="Insert link">&#128279;</button>
                    <button type="button" class="blog-tb-btn forum-editor-img-upload-btn" title="Upload image">&#128247;</button>
                    <input type="file" accept="image/*" class="sr-only forum-editor-img-input">
                </div>
                <div class="blog-editor forum-editor"
                     contenteditable="true"
                     role="textbox"
                     aria-multiline="true"
                     aria-label="Post content"
                     data-placeholder="Write your post here…"><?= $editHtml ?></div>
                <textarea name="content" class="sr-only" aria-hidden="true" tabindex="-1"></textarea>
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
