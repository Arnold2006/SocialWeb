<?php
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
$cacheKey   = 'wall_feed_page_1';
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
                            (SELECT COUNT(*) FROM likes   WHERE post_id = p.id) AS like_count,
                            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND is_deleted = 0) AS comment_count,
                            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) AS user_liked
                     FROM posts p
                     JOIN users u ON u.id = p.user_id
                     WHERE p.is_deleted = 0
                     ORDER BY COALESCE(p.bumped_at, p.created_at) DESC
                     LIMIT {$limitSql} OFFSET {$offsetSql}",
                    [$user['id']]
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

<?php include SITE_ROOT . '/includes/footer.php'; ?>
