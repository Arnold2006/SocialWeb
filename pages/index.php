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
 * index.php — Main wall / home feed page
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pageTitle = 'Wall';
$user      = current_user();
$plugins   = plugins_load();

// Fetch wall posts with caching (always load the first page of 10)
// Cache key is per-user so that ownership-based controls (Move, Delete, Edit) are never shared.
$cacheKey   = 'wall_feed_page_1_u' . (int)$user['id'];
$cachedFeed = cache_get($cacheKey);

include SITE_ROOT . '/includes/header.php';
?>

<div class="two-col-layout">

    <!-- ── Left Column ─────────────────────────────────────── -->
    <aside class="col-left">
        <?php include SITE_ROOT . '/includes/sidebar_widgets.php'; ?>
    </aside>

    <!-- ── Right Column ────────────────────────────────────── -->
    <main class="col-right">

        <!-- Post composer -->
        <div class="post-composer">
            <form id="post-form" method="POST"
                  action="<?= SITE_URL ?>/modules/wall/create_post.php"
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                <textarea name="content" id="post-content"
                          placeholder="What's on your mind?"
                          maxlength="5000" rows="3" required></textarea>
                <div class="composer-actions">
                    <label class="btn btn-sm btn-secondary" for="post-image">
                        📷 Add Photo
                    </label>
                    <input type="file" id="post-image" name="media"
                           accept="image/*,video/mp4,video/webm" class="sr-only">
                    <div id="image-preview" class="image-preview"></div>
                    <button type="submit" class="btn btn-primary">Post</button>
                </div>
            </form>
        </div>

        <!-- Post feed -->
        <?php
        $postsPerPage = 10;
        $totalPosts   = 0;
        $hasMore      = false;

        if ($cachedFeed) {
            // Also retrieve the cached has_more flag
            $cachedHasMore = cache_get($cacheKey . '_has_more') ?? '0';
            echo '<div id="post-feed" data-offset="' . $postsPerPage . '" data-has-more="' . htmlspecialchars($cachedHasMore, ENT_QUOTES, 'UTF-8') . '">';
            echo $cachedFeed;
            echo '</div>';
        } else {
            try {
                ob_start();

                $limitSql  = (int) $postsPerPage;
                $offsetSql = 0;

                $posts = db_query(
                    "SELECT p.*, u.username, u.avatar_path,
                            (SELECT COUNT(DISTINCT user_id) FROM likes WHERE post_id = p.id OR (p.media_id IS NOT NULL AND media_id = p.media_id)) AS like_count,
                            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND is_deleted = 0) +
                                CASE WHEN p.media_id IS NOT NULL THEN (SELECT COUNT(*) FROM comments WHERE media_id = p.media_id AND is_deleted = 0) ELSE 0 END AS comment_count,
                            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) +
                                CASE WHEN p.media_id IS NOT NULL THEN (SELECT COUNT(*) FROM likes WHERE media_id = p.media_id AND user_id = ?) ELSE 0 END AS user_liked
                     FROM posts p
                     JOIN users u ON u.id = p.user_id
                     WHERE p.is_deleted = 0
                     ORDER BY COALESCE(p.bumped_at, p.created_at) DESC
                     LIMIT {$limitSql} OFFSET {$offsetSql}",
                    [$user['id'], $user['id']]
                );

                foreach ($posts as $post) {
                    include SITE_ROOT . '/modules/wall/post_item.php';
                }

                $totalPosts = (int) db_val('SELECT COUNT(*) FROM posts WHERE is_deleted = 0');
                $hasMore    = $totalPosts > $postsPerPage;

                $feedHtml = ob_get_clean() ?: '';
                cache_set($cacheKey, $feedHtml);
                cache_set($cacheKey . '_has_more', $hasMore ? '1' : '0');

                $hasMoreAttr = $hasMore ? '1' : '0';
                echo '<div id="post-feed" data-offset="' . $postsPerPage . '" data-has-more="' . $hasMoreAttr . '">';
                echo $feedHtml;
                echo '</div>';
            } catch (Throwable $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                error_log('Feed load error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                echo '<div id="post-feed">';
                if (SITE_DEBUG) {
                    echo '<div class="alert alert-error"><pre>'
                        . htmlspecialchars(
                            get_class($e) . ': ' . $e->getMessage()
                                . "\nin " . $e->getFile() . ':' . $e->getLine()
                                . "\n\n" . $e->getTraceAsString(),
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '</pre></div>';
                } else {
                    echo '<div class="alert alert-error">Unable to load posts. Please try again later.</div>';
                }
                echo '</div>';
            }
        }
        ?>

        <!-- Floating "Load More" button (hidden via JS when no more posts) -->
        <div class="load-more-wrap" id="load-more-wrap">
            <button class="btn btn-primary btn-load-more" id="load-more-btn" type="button">
                Load More
            </button>
        </div>

        <!-- Plugin wall widgets -->
        <?php foreach ($plugins['wall_widgets'] as $widget): ?>
            <?php $widget(); ?>
        <?php endforeach; ?>

    </main>

</div><!-- /.two-col-layout -->

<?php
// Move Media Modal — only render if the current user has at least one album
$wallOwnerAlbums = db_query(
    'SELECT a.id, a.title, c.title AS category_title
     FROM albums a
     LEFT JOIN album_categories c ON c.id = a.category_id AND c.is_deleted = 0
     WHERE a.user_id = ? AND a.is_deleted = 0
     ORDER BY c.title ASC, a.title ASC',
    [(int)$user['id']]
);
if (!empty($wallOwnerAlbums)):
?>
<div id="move-media-modal" class="crop-modal" style="display:none"
     role="dialog" aria-modal="true" aria-label="Move Image to Album">
    <div class="crop-modal-inner">
        <h3>Move Image to Album</h3>
        <form method="POST" action="<?= e(SITE_URL . '/modules/wall/move_media.php') ?>" id="move-media-form">
            <?= csrf_field() ?>
            <input type="hidden" name="media_id" id="move-media-id" value="">
            <div style="margin-bottom:1rem">
                <label for="move-target-album" style="display:block;margin-bottom:0.35rem;font-weight:600">Destination album</label>
                <select name="target_album_id" id="move-target-album" style="width:100%">
                    <?php
                    $lastCat = false;
                    foreach ($wallOwnerAlbums as $a):
                        $catLabel = $a['category_title'] ?? null;
                        if ($catLabel !== $lastCat):
                            if ($lastCat !== false) echo '</optgroup>';
                            echo '<optgroup label="' . e($catLabel ?? '(Uncategorised)') . '">';
                            $lastCat = $catLabel;
                        endif;
                    ?>
                    <option value="<?= (int)$a['id'] ?>"><?= e($a['title']) ?></option>
                    <?php endforeach; ?>
                    <?php if ($lastCat !== false) echo '</optgroup>'; ?>
                </select>
            </div>
            <div class="crop-modal-actions">
                <button type="button" id="move-media-cancel" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Move</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
