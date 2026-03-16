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
 * forum/new_thread.php — Create a new thread
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

$user    = current_user();
$forumId = isset($_GET['forum_id']) ? (int)$_GET['forum_id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $forumId = (int)($_POST['forum_id'] ?? 0);
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $mediaId = (int)($_POST['media_id'] ?? 0);
    $errors  = [];

    if ($forumId <= 0) {
        $errors[] = 'Please select a forum.';
    } else {
        $targetForum = db_row('SELECT id FROM forum_forums WHERE id = ?', [$forumId]);
        if (!$targetForum) {
            $errors[] = 'Invalid forum selected.';
        }
    }
    if ($title === '') {
        $errors[] = 'Thread title is required.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = 'Thread title may not exceed 200 characters.';
    }
    if ($content === '') {
        $errors[] = 'Post content is required.';
    } else {
        $content = sanitise_html($content);
        if ($content === '') {
            $errors[] = 'Post content is required.';
        }
    }

    // Validate media_id belongs to this user (if provided)
    if ($mediaId > 0) {
        $mediaCheck = db_row(
            'SELECT id FROM media WHERE id = ? AND user_id = ? AND type = ? AND is_deleted = 0',
            [$mediaId, (int)$user['id'], 'image']
        );
        if (!$mediaCheck) {
            $mediaId = 0;
        }
    }

    if (empty($errors)) {
        $threadId = (int)db_insert(
            'INSERT INTO forum_threads (forum_id, user_id, title, created_at, last_post_at, reply_count)
             VALUES (?, ?, ?, NOW(), NOW(), 0)',
            [$forumId, (int)$user['id'], $title]
        );
        db_insert(
            'INSERT INTO forum_posts (thread_id, user_id, content, media_id, created_at)
             VALUES (?, ?, ?, ?, NOW())',
            [$threadId, (int)$user['id'], $content, $mediaId > 0 ? $mediaId : null]
        );
        flash_set('success', 'Thread created successfully.');
        redirect(SITE_URL . '/forum/thread.php?id=' . $threadId);
    }

    foreach ($errors as $err) {
        flash_set('error', $err);
    }
}

// Load forums for the dropdown, grouped by category
$categories = db_query(
    'SELECT c.id AS cat_id, c.title AS cat_title,
            f.id AS forum_id, f.title AS forum_title
     FROM   forum_categories c
     JOIN   forum_forums f ON f.category_id = c.id
     ORDER  BY c.sort_order ASC, c.id ASC, f.sort_order ASC, f.id ASC'
);

// Group by category
$grouped = [];
foreach ($categories as $row) {
    $grouped[$row['cat_id']]['title']  = $row['cat_title'];
    $grouped[$row['cat_id']]['forums'][] = ['id' => $row['forum_id'], 'title' => $row['forum_title']];
}

$pageTitle = 'New Thread — Forum';

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
        <span>New Thread</span>
    </nav>

    <div class="forum-header">
        <h1>Create New Thread</h1>
    </div>

    <div class="forum-form-wrap">
        <form method="post" action="<?= SITE_URL ?>/forum/new_thread.php">
            <?= csrf_field() ?>
            <input type="hidden" name="media_id" id="forum-media-id" value="">

            <div class="form-group">
                <label for="forum_id">Forum</label>
                <select id="forum_id" name="forum_id" required>
                    <option value="">— Select a forum —</option>
                    <?php foreach ($grouped as $cat): ?>
                    <optgroup label="<?= e($cat['title']) ?>">
                        <?php foreach ($cat['forums'] as $f): ?>
                        <option value="<?= (int)$f['id'] ?>"<?= (int)$forumId === (int)$f['id'] ? ' selected' : '' ?>>
                            <?= e($f['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="title">Thread Title</label>
                <input type="text" id="title" name="title" maxlength="200" required
                       value="<?= isset($_POST['title']) ? e($_POST['title']) : '' ?>"
                       placeholder="Enter a descriptive title">
            </div>

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
                     data-placeholder="Write your post here…"><?= isset($_POST['content']) ? sanitise_html($_POST['content']) : '' ?></div>
                <textarea name="content" class="sr-only" aria-hidden="true" tabindex="-1"></textarea>
            </div>

            <div class="forum-image-picker-wrap">
                <button type="button" class="btn btn-sm btn-secondary forum-pick-image-btn">
                    🖼️ Add Image from Gallery
                </button>
                <div id="forum-image-preview" class="forum-image-preview"></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Post Thread</button>
                <a href="<?= $forumId > 0 ? e(SITE_URL . '/forum/forum.php?id=' . $forumId) : e(SITE_URL . '/forum/index.php') ?>"
                   class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    </div><!-- /.forum-layout -->
    </main>

</div><!-- /.two-col-layout -->

<?php include SITE_ROOT . '/includes/footer.php'; ?>
