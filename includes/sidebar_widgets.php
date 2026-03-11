<?php
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

<!-- Plugin sidebar widgets -->
<?php foreach ($plugins['sidebar_widgets'] as $widget): ?>
    <?php $widget(); ?>
<?php endforeach; ?>
