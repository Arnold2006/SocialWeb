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
 * blog.php — User blog page
 *
 * ?user_id=N  – view blog of user N (defaults to own blog)
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$currentUser = current_user();
$blogOwnerId = sanitise_int($_GET['user_id'] ?? (int)$currentUser['id']);

if ($blogOwnerId < 1) {
    redirect(SITE_URL . '/pages/index.php');
}

$isOwn = ((int)$currentUser['id'] === $blogOwnerId);

$blogOwner = db_row(
    'SELECT id, username, avatar_path FROM users WHERE id = ? AND is_banned = 0',
    [$blogOwnerId]
);

if (!$blogOwner) {
    flash_set('error', 'User not found.');
    redirect(SITE_URL . '/pages/members.php');
}

// Load blog posts (newest first, paginated)
$page    = max(1, sanitise_int($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Handle direct post links: ?post_id=N redirects to the correct page + anchor
if (isset($_GET['post_id']) && !isset($_GET['page'])) {
    $directPostId = sanitise_int($_GET['post_id']);
    if ($directPostId > 0) {
        $position = (int)db_val(
            'SELECT COUNT(*) FROM blog_posts
             WHERE user_id = ? AND is_deleted = 0
             AND created_at >= (
                 SELECT created_at FROM blog_posts
                 WHERE id = ? AND user_id = ? AND is_deleted = 0
             )',
            [$blogOwnerId, $directPostId, $blogOwnerId]
        );
        if ($position > 0) {
            $targetPage = (int)ceil($position / $perPage);
            redirect(SITE_URL . '/pages/blog.php?user_id=' . urlencode((string)$blogOwnerId) . '&page=' . urlencode((string)$targetPage) . '#blog-post-' . $directPostId);
        }
    }
}

$total = (int)db_val(
    'SELECT COUNT(*) FROM blog_posts WHERE user_id = ? AND is_deleted = 0',
    [$blogOwnerId]
);

$posts = db_query(
    'SELECT * FROM blog_posts WHERE user_id = ? AND is_deleted = 0
     ORDER BY created_at DESC
     LIMIT ' . $perPage . ' OFFSET ' . $offset,
    [$blogOwnerId]
);

$totalPages = (int)ceil($total / $perPage);

$pageTitle = e($blogOwner['username']) . "'s Blog";
include SITE_ROOT . '/includes/header.php';
?>

<div class="two-col-layout">

    <!-- ── Left Column ─────────────────────────────────────────── -->
    <aside class="col-left">
        <?php include SITE_ROOT . '/includes/sidebar_widgets.php'; ?>
    </aside>

    <!-- ── Right Column ────────────────────────────────────────── -->
    <main class="col-right">

        <div class="blog-header">
            <img src="<?= e(avatar_url($blogOwner, 'small')) ?>"
                 alt="" width="40" height="40" class="avatar avatar-small">
            <h1><?= e($blogOwner['username']) ?>'s Blog</h1>
            <?php if (!$isOwn): ?>
            <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . $blogOwnerId) ?>"
               class="btn btn-secondary btn-sm">← Profile</a>
            <?php endif; ?>
        </div>

        <?php if ($isOwn): ?>
        <!-- ── Editor ──────────────────────────────────────────── -->
        <div class="blog-editor-wrap card" id="blog-editor-wrap">
            <h2 class="blog-editor-heading" id="blog-editor-heading">New Post</h2>

            <div class="form-group">
                <input type="text" id="blog-title-input" class="blog-title-input"
                       placeholder="Post title…" maxlength="255">
            </div>

            <!-- Toolbar -->
            <div class="blog-toolbar" id="blog-toolbar" role="toolbar" aria-label="Text formatting">
                <button type="button" class="blog-tb-btn" data-cmd="bold"               title="Bold"><b>B</b></button>
                <button type="button" class="blog-tb-btn" data-cmd="italic"             title="Italic"><i>I</i></button>
                <button type="button" class="blog-tb-btn" data-cmd="underline"          title="Underline"><u>U</u></button>
                <button type="button" class="blog-tb-btn" data-cmd="strikeThrough"      title="Strikethrough"><s>S</s></button>
                <span class="blog-tb-sep"></span>
                <button type="button" class="blog-tb-btn" data-cmd="formatBlock" data-val="h2" title="Heading 2">H2</button>
                <button type="button" class="blog-tb-btn" data-cmd="formatBlock" data-val="h3" title="Heading 3">H3</button>
                <span class="blog-tb-sep"></span>
                <button type="button" class="blog-tb-btn" data-cmd="insertUnorderedList" title="Bullet list">&#8226;&#8212;</button>
                <button type="button" class="blog-tb-btn" data-cmd="insertOrderedList"   title="Numbered list">1&#8212;</button>
                <button type="button" class="blog-tb-btn" data-cmd="formatBlock" data-val="blockquote" title="Blockquote">&#10078;</button>
                <span class="blog-tb-sep"></span>
                <button type="button" class="blog-tb-btn" id="blog-link-btn"  title="Insert link">&#128279;</button>
                <button type="button" class="blog-tb-btn" id="blog-image-btn" title="Insert image">&#128247;</button>
                <input type="file" id="blog-image-input" accept="image/*" class="sr-only">
            </div>

            <!-- Editable content area -->
            <div id="blog-editor"
                 class="blog-editor"
                 contenteditable="true"
                 role="textbox"
                 aria-multiline="true"
                 aria-label="Blog post content"
                 data-placeholder="Write your post here…"></div>

            <div class="blog-editor-actions">
                <input type="hidden" id="blog-edit-post-id" value="0">
                <button type="button" class="btn btn-secondary btn-sm" id="blog-cancel-edit"
                        style="display:none">Cancel</button>
                <button type="button" class="btn btn-primary" id="blog-save-btn">Publish</button>
                <span class="blog-save-status" id="blog-save-status" aria-live="polite"></span>
            </div>
        </div>

        <!-- Hidden CSRF token for JS -->
        <input type="hidden" id="blog-csrf" value="<?= e(csrf_token()) ?>">
        <?php endif; ?>

        <!-- ── Posts list ───────────────────────────────────────── -->
        <div id="blog-posts-list" data-blog-owner-id="<?= (int)$blogOwnerId ?>">
        <?php if (empty($posts)): ?>
            <p class="empty-state">No blog posts yet.</p>
        <?php else: ?>
            <?php foreach ($posts as $post):
                $blogComments = db_query(
                    'SELECT c.*, u.username, u.avatar_path
                     FROM comments c
                     JOIN users u ON u.id = c.user_id
                     WHERE c.blog_post_id = ? AND c.is_deleted = 0
                     ORDER BY c.created_at ASC
                     LIMIT 3',
                    [(int)$post['id']]
                );
                $blogCommentCount = (int)db_val(
                    'SELECT COUNT(*) FROM comments WHERE blog_post_id = ? AND is_deleted = 0',
                    [(int)$post['id']]
                );
                $moreComments = $blogCommentCount > 3;
                $blogLikeCount = (int)db_val(
                    'SELECT COUNT(*) FROM likes WHERE blog_post_id = ?',
                    [(int)$post['id']]
                );
                $userLiked = (int)db_val(
                    'SELECT COUNT(*) FROM likes WHERE blog_post_id = ? AND user_id = ?',
                    [(int)$post['id'], (int)$currentUser['id']]
                );
            ?>
            <article class="blog-post card" id="blog-post-<?= (int)$post['id'] ?>">
                <header class="blog-post-header">
                    <h2 class="blog-post-title"><?= e($post['title']) ?></h2>
                    <time class="blog-post-date" datetime="<?= e($post['created_at']) ?>">
                        <?= e(date('F j, Y', strtotime($post['created_at']))) ?>
                        <?php if ($post['updated_at']): ?>
                        <span class="blog-post-edited">(edited)</span>
                        <?php endif; ?>
                    </time>
                </header>
                <div class="blog-post-content">
                    <?= sanitise_html($post['content']) ?>
                </div>
                <footer class="blog-post-footer">
                    <?php if ($isOwn): ?>
                    <button type="button" class="btn btn-secondary btn-xs blog-edit-btn"
                            data-post-id="<?= (int)$post['id'] ?>"
                            data-title="<?= e($post['title']) ?>">Edit</button>
                    <button type="button" class="btn btn-danger btn-xs blog-delete-btn"
                            data-post-id="<?= (int)$post['id'] ?>">Delete</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary btn-xs blog-copy-link-btn"
                            data-post-id="<?= (int)$post['id'] ?>"
                            data-user-id="<?= (int)$blogOwnerId ?>">Copy Link</button>
                    <button type="button" class="btn-like btn-like-blog<?= $userLiked ? ' liked' : '' ?>"
                            data-blog-post-id="<?= (int)$post['id'] ?>">
                        ♥ <span class="like-count"><?= $blogLikeCount ?></span>
                    </button>
                    <button class="btn-comment" data-blog-post-id="<?= (int)$post['id'] ?>">
                        💬 <span class="blog-comment-count"><?= $blogCommentCount ?></span>
                    </button>
                </footer>

                <!-- Comments section -->
                <div class="comments-section" id="blog-comments-<?= (int)$post['id'] ?>">
                    <?php foreach ($blogComments as $comment): ?>
                    <div class="comment-item" id="comment-<?= (int)$comment['id'] ?>">
                        <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$comment['user_id']) ?>">
                            <img src="<?= e(avatar_url($comment, 'small')) ?>"
                                 alt="<?= e($comment['username']) ?>"
                                 class="avatar avatar-small" width="28" height="28" loading="lazy">
                        </a>
                        <div class="comment-body">
                            <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$comment['user_id']) ?>"
                               class="comment-author"><?= e($comment['username']) ?></a>
                            <span class="comment-time"><?= e(time_ago($comment['created_at'])) ?></span>
                            <p class="comment-text"><?= nl2br(linkify(smilify($comment['content']))) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($moreComments): ?>
                    <button type="button" class="load-more-blog-comments"
                            data-blog-post-id="<?= (int)$post['id'] ?>"
                            data-loaded="3">
                        Load <?= $blogCommentCount - 3 ?> more comment<?= ($blogCommentCount - 3 !== 1) ? 's' : '' ?>
                    </button>
                    <?php endif; ?>

                    <form class="blog-comment-form" data-blog-post-id="<?= (int)$post['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="text" name="content" placeholder="Write a comment…"
                               maxlength="1000" autocomplete="off" required>
                        <button type="submit" class="btn btn-sm">Post</button>
                    </form>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="<?= e(SITE_URL . '/pages/blog.php?user_id=' . $blogOwnerId . '&page=' . $p) ?>"
               class="page-link<?= ($p === $page) ? ' active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>

    </main>

</div><!-- /.two-col-layout -->

<?php $pageScript = ASSETS_URL . '/js/blog_editor.js'; ?>
<?php include SITE_ROOT . '/includes/footer.php'; ?>
