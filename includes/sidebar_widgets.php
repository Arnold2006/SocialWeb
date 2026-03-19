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
 * sidebar_widgets.php — Shared sidebar widgets
 *
 * Renders the standard left-sidebar widgets: Shoutbox, About/Site Info, and
 * any plugin-registered sidebar widgets.
 *
 * Include this file inside a .col-left element.
 * Requires bootstrap.php to have been loaded before inclusion.
 *
 * Uses $plugins if already set by the calling page; otherwise loads plugins
 * itself so pages that don't call plugins_load() still get plugin widgets.
 */

declare(strict_types=1);

// Load shoutbox messages (last 20, reversed so newest appears at bottom)
try {
    $sidebarShoutMessages = array_reverse(db_query(
        'SELECT s.*, u.username, u.avatar_path
         FROM shoutbox s
         JOIN users u ON u.id = s.user_id
         WHERE s.is_deleted = 0
         ORDER BY s.created_at DESC
         LIMIT 20'
    ));
} catch (Throwable $e) {
    error_log('Shoutbox load error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $sidebarShoutMessages = [];
}

// Use $plugins from the calling page, or load plugins if not yet available
if (!isset($plugins)) {
    $plugins = plugins_load();
}

// Load latest photos: 3 most recently active users, 3 photos each
try {
    $activeUsers = db_query(
        'SELECT m.user_id, u.username
         FROM media m
         JOIN users u ON u.id = m.user_id
         WHERE m.type = \'image\'
           AND m.is_deleted = 0
           AND u.is_banned = 0
         GROUP BY m.user_id, u.username
         ORDER BY MAX(m.created_at) DESC, MAX(m.id) DESC
         LIMIT 3'
    );
    $sidebarLatestPhotos = [];
    if (!empty($activeUsers)) {
        $userIds = [];
        foreach ($activeUsers as $row) {
            $uid = (int)$row['user_id'];
            $userIds[] = $uid;
            $sidebarLatestPhotos[$uid] = ['username' => $row['username'], 'photos' => []];
        }
        // UNION ALL ensures exactly up to 3 photos per user regardless of relative recency
        $perUserSql = '(SELECT id, user_id, thumb_path, medium_path, large_path, storage_path
                        FROM media
                        WHERE type = \'image\' AND is_deleted = 0 AND user_id = ?
                        ORDER BY created_at DESC, id DESC
                        LIMIT 3)';
        $sql = implode(' UNION ALL ', array_fill(0, count($userIds), $perUserSql));
        foreach (db_query($sql, $userIds) as $photo) {
            $uid = (int)$photo['user_id'];
            if (count($sidebarLatestPhotos[$uid]['photos']) < 3) {
                $sidebarLatestPhotos[$uid]['photos'][] = $photo;
            }
        }
        $sidebarLatestPhotos = array_filter($sidebarLatestPhotos, fn($u) => !empty($u['photos']));
    }
} catch (Throwable $e) {
    error_log('Latest photos load error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $sidebarLatestPhotos = [];
}
?>

<!-- Shoutbox -->
<div class="widget widget-shoutbox" id="shoutbox">
    <h3 class="widget-title">Shoutbox</h3>
    <div class="shoutbox-messages" id="shoutbox-messages">
        <?php foreach ($sidebarShoutMessages as $shout): ?>
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

<!-- Quick Links -->
<div class="widget widget-links">
    <h3 class="widget-title">Quick Links</h3>
    <ul class="site-links-list">
        <li>
            <button type="button" class="btn-link" data-modal="welcome-modal">
                👋 Welcome
            </button>
        </li>
        <li>
            <button type="button" class="btn-link" data-modal="how-it-works-modal">
                ⚙️ How it Works
            </button>
        </li>
        <li>
            <button type="button" class="btn-link" data-modal="terms-modal">
                📜 Terms of Use
            </button>
        </li>
    </ul>
</div>

<!-- Site Info -->
<div class="widget widget-info">
    <h3 class="widget-title">About <?= e(SITE_NAME) ?></h3>
    <p><?= e(site_setting('site_description')) ?></p>
    <?php
    try {
        $sidebarMemberCount = (int) db_val('SELECT COUNT(*) FROM users WHERE is_banned = 0');
    } catch (Throwable $e) {
        error_log('Member count error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $sidebarMemberCount = 0;
    }
    ?>
    <ul class="site-stats">
        <li><strong><?= $sidebarMemberCount ?></strong> members</li>
    </ul>
</div>

<!-- Latest Photos -->
<div class="widget widget-latest-photos">
    <h3 class="widget-title">Latest Photos</h3>
    <?php if (empty($sidebarLatestPhotos)): ?>
        <p class="latest-photos-empty">No photos uploaded yet.</p>
    <?php else: ?>
        <div class="latest-photos-grid">
            <?php foreach ($sidebarLatestPhotos as $uid => $userRow): ?>
            <div class="latest-photos-row">
                <?php foreach ($userRow['photos'] as $photo): ?>
                <a href="<?= e(get_media_url($photo, 'original')) ?>"
                   class="lightbox-trigger latest-photos-thumb"
                   data-src="<?= e(get_media_url($photo, 'large')) ?>"
                   data-media-id="<?= (int)$photo['id'] ?>"
                   title="<?= e($userRow['username']) ?>">
                    <img src="<?= e(get_media_url($photo, 'thumb')) ?>"
                         alt="<?= e($userRow['username']) ?>"
                         width="70" height="70"
                         loading="lazy">
                    <span class="latest-photos-caption"><?= e($userRow['username']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Plugin sidebar widgets -->
<?php foreach ($plugins['sidebar_widgets'] as $widget): ?>
    <?php $widget(); ?>
<?php endforeach; ?>
