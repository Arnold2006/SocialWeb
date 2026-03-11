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

// Load shoutbox messages (last 20, reversed so newest appears at bottom)
try {
    $shoutMessages = array_reverse(db_query(
        'SELECT s.*, u.username, u.avatar_path
         FROM shoutbox s
         JOIN users u ON u.id = s.user_id
         WHERE s.is_deleted = 0
         ORDER BY s.created_at DESC
         LIMIT 20'
    ));
} catch (Throwable $e) {
    error_log('Shoutbox load error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $shoutMessages = [];
}

// Fetch wall posts with caching
$page      = max(1, sanitise_int($_GET['page'] ?? 1));
$cacheKey  = 'wall_feed_page_' . $page;
$cachedFeed = cache_get($cacheKey);

include SITE_ROOT . '/includes/header.php';
?>

<div class="two-col-layout">

    <!-- ── Left Column ─────────────────────────────────────── -->
    <aside class="col-left">

        <!-- Shoutbox -->
        <div class="widget widget-shoutbox" id="shoutbox">
            <h3 class="widget-title">Shoutbox</h3>
            <div class="shoutbox-messages" id="shoutbox-messages">
                <?php foreach ($shoutMessages as $shout): ?>
                <div class="shout-item">
                    <img src="<?= e(avatar_url($shout, 'small')) ?>"
                         alt="" class="shout-avatar" width="24" height="24" loading="lazy">
                    <span class="shout-user">
                        <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$shout['user_id']) ?>">
                            <?= e($shout['username']) ?>
                        </a>
                    </span>
                    <span class="shout-time"><?= e(time_ago($shout['created_at'])) ?></span>
                    <p class="shout-text"><?= e($shout['message']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <form id="shoutbox-form" class="shoutbox-form">
                <?= csrf_field() ?>
                <input type="text" id="shout-input" name="message"
                       placeholder="Say something…" maxlength="500" required>
                <button type="submit" class="btn btn-sm">Shout</button>
            </form>
        </div>

        <!-- Site Info -->
        <div class="widget widget-info">
            <h3 class="widget-title">About <?= e(SITE_NAME) ?></h3>
            <p><?= e(site_setting('site_description')) ?></p>
            <?php
            try {
                $memberCount = (int) db_val('SELECT COUNT(*) FROM users WHERE is_banned = 0');
            } catch (Throwable $e) {
                error_log('Member count error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                $memberCount = 0;
            }
            ?>
            <ul class="site-stats">
                <li><strong><?= $memberCount ?></strong> members</li>
            </ul>
        </div>

        <!-- Plugin sidebar widgets -->
        <?php foreach ($plugins['sidebar_widgets'] as $widget): ?>
            <?php $widget(); ?>
        <?php endforeach; ?>

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
        <div id="post-feed">
        <?php if ($cachedFeed): ?>
            <?= $cachedFeed ?>
        <?php else:
            try {
                ob_start();

                $postsPerPage = 20;
                $offset       = ($page - 1) * $postsPerPage;

                // Cast to int before interpolation (already sanitised integers)
                $limitSql = (int) $postsPerPage;
                $offsetSql = (int) $offset;

                $posts = db_query(
                    "SELECT p.*, u.username, u.avatar_path,
                            (SELECT COUNT(*) FROM likes   WHERE post_id = p.id) AS like_count,
                            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND is_deleted = 0) AS comment_count,
                            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) AS user_liked
                     FROM posts p
                     JOIN users u ON u.id = p.user_id
                     WHERE p.is_deleted = 0
                     ORDER BY p.created_at DESC
                     LIMIT {$limitSql} OFFSET {$offsetSql}",
                    [$user['id']]
                );

                foreach ($posts as $post):
                    include SITE_ROOT . '/modules/wall/post_item.php';
                endforeach;

                // Pagination
                $totalPosts = (int) db_val('SELECT COUNT(*) FROM posts WHERE is_deleted = 0');
                $totalPages = (int) ceil($totalPosts / $postsPerPage);
                echo pagination_links($page, $totalPages, SITE_URL . '/pages/index.php');

                $feedHtml = ob_get_clean() ?: '';
                cache_set($cacheKey, $feedHtml);
                echo $feedHtml;
            } catch (Throwable $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                error_log('Feed load error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
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
            }
        endif; ?>
        </div>

        <!-- Plugin wall widgets -->
        <?php foreach ($plugins['wall_widgets'] as $widget): ?>
            <?php $widget(); ?>
        <?php endforeach; ?>

    </main>

</div><!-- /.two-col-layout -->

<?php include SITE_ROOT . '/includes/footer.php'; ?>
