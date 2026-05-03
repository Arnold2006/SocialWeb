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
 * header.php — Global site header
 *
 * Variables available here (set before including):
 *   $pageTitle   string   — <title> value
 */

declare(strict_types=1);

$pageTitle   = $pageTitle ?? SITE_NAME;
$user        = current_user();
$notifCount        = $user ? unread_notifications_count() : 0;
$msgCount          = $user ? unread_messages_count() : 0;
$forumCount        = $user ? unread_forum_count() : 0;
$pendingMigrations = ($user && is_admin()) ? pending_migrations_count() : 0;
$friendRequestCount = 0;
if ($user) {
    try {
        $friendRequestCount = FriendshipService::countPendingRequests((int) $user['id']);
    } catch (\Throwable $e) {
        $friendRequestCount = 0;
    }
}
$bannerImage = site_setting('banner_image');

// Banner overlay settings
$overlayX      = site_setting('banner_overlay_x',      '50');
$overlayY      = site_setting('banner_overlay_y',      '50');
$overlaySize   = site_setting('banner_overlay_size',   '2.4');
$overlayColor  = site_setting('banner_overlay_color',  '#ffffff');
$overlayFont   = site_setting('banner_overlay_font',   'system');
$overlayShadow = site_setting('banner_overlay_shadow', 'medium');

require_once SITE_ROOT . '/includes/overlay_maps.php';

// Extend font map with any uploaded custom fonts (table may not exist on fresh installs)
try {
    $customFonts = db_query("SELECT id, name, filename, format FROM site_fonts ORDER BY name");
} catch (\Throwable $e) {
    $customFonts = [];
}
foreach ($customFonts as $cf) {
    $OVERLAY_FONT_MAP['custom_' . $cf['id']] = "'" . str_replace("'", "\\'", $cf['name']) . "',sans-serif";
}

$overlayFontCSS   = $OVERLAY_FONT_MAP[$overlayFont]     ?? $OVERLAY_FONT_MAP['system'];
$overlayShadowCSS = $OVERLAY_SHADOW_MAP[$overlayShadow] ?? $OVERLAY_SHADOW_MAP['medium'];

$siteTheme    = active_theme();
$themeMode    = user_theme_mode();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($siteTheme) ?>" data-mode="<?= e($themeMode) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?></title>
    <meta name="site-url" content="<?= SITE_URL ?>">
    <?php if ($user): ?>
    <meta name="current-user-id" content="<?= (int)$user['id'] ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <?php if ($user): ?>
    <script>
        /* Apply saved nav-pin preference before first paint to prevent layout flash */
        (function () {
            if (localStorage.getItem('nav_pinned') === '0') {
                document.documentElement.classList.add('nav-unpinned');
            }
        }());
    </script>
    <?php endif; ?>
    <?php if (!empty($customFonts)): ?>
    <style>
        <?php foreach ($customFonts as $cf):
            $fontUrl    = SITE_URL . '/uploads/fonts/' . rawurlencode($cf['filename']);
            $fontFamily = str_replace("'", "\\'", $cf['name']);
            $fmtMap     = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'];
            $cssFmt     = $fmtMap[$cf['format']] ?? $cf['format'];
        ?>
        @font-face {
            font-family: '<?= e($fontFamily) ?>';
            src: url('<?= e($fontUrl) ?>') format('<?= e($cssFmt) ?>');
        }
        <?php endforeach; ?>
    </style>
    <?php endif; ?>
</head>
<body>

<!-- ── Site Header ───────────────────────────────────────────── -->
<header class="site-header<?= $user ? ' has-nav' : '' ?>">

    <!-- Navigation is the very first element (fixed at top) -->
    <?php if ($user): ?>
    <nav class="main-nav">
        <ul>
            <li><a href="<?= SITE_URL ?>/pages/index.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'index.php')) ? 'active' : '' ?>">Wall</a></li>
            <li><a href="<?= SITE_URL ?>/pages/members.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'members.php')) ? 'active' : '' ?>">Members</a></li>
            <li>
                <a href="<?= SITE_URL ?>/pages/friends.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'friends.php')) ? 'active' : '' ?>">
                    Friends<?= $friendRequestCount > 0 ? ' <span class="badge">' . $friendRequestCount . '</span>' : '' ?>
                </a>
            </li>
            <li><a href="<?= SITE_URL ?>/pages/profile.php?id=<?= (int)$user['id'] ?>"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'profile.php')) ? 'active' : '' ?>">My Profile</a></li>
            <li><a href="<?= SITE_URL ?>/pages/photos.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'photos.php')) ? 'active' : '' ?>">Photos</a></li>
            <li><a href="<?= SITE_URL ?>/pages/video.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'video.php') || str_ends_with($_SERVER['PHP_SELF'] ?? '', 'video_play.php')) ? 'active' : '' ?>">Videos</a></li>
            <li><a href="<?= SITE_URL ?>/forum/index.php"
                   class="<?= str_contains($_SERVER['PHP_SELF'] ?? '', '/forum/') ? 'active' : '' ?>">Forum<?= $forumCount > 0 ? ' <span class="badge">' . $forumCount . '</span>' : '' ?></a></li>
            <li>
                <a href="<?= SITE_URL ?>/pages/messages.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'messages.php')) ? 'active' : '' ?>">
                    Messages<?= $msgCount > 0 ? ' <span class="badge">' . $msgCount . '</span>' : '' ?>
                </a>
            </li>
            <li>
                <a href="<?= SITE_URL ?>/pages/notifications.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'notifications.php')) ? 'active' : '' ?>">
                    Notifications<?= $notifCount > 0 ? ' <span class="badge">' . $notifCount . '</span>' : '' ?>
                </a>
            </li>
            <?php if (is_admin()): ?>
            <li><a href="<?= SITE_URL ?>/admin/dashboard.php"
                   class="<?= str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/') ? 'active' : '' ?>">Admin<?= $pendingMigrations > 0 ? ' <span class="badge">' . $pendingMigrations . '</span>' : '' ?></a></li>
            <?php endif; ?>
            <li><a href="https://sf.tera-sat.com" target="_blank" rel="noopener noreferrer">SendFile</a></li>
            <li><a href="https://print.tera-sat.com" target="_blank" rel="noopener noreferrer">PrintService</a></li>
            <li><a href="<?= SITE_URL ?>/pages/logout.php">Logout</a></li>
        </ul>
        <button type="button" class="nav-pin-btn" id="nav-pin-btn"
                aria-pressed="true" title="Unpin navigation bar"
                aria-label="Toggle pinned navigation bar">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true">
                <path d="M16 9V4h1c.55 0 1-.45 1-1s-.45-1-1-1H7c-.55 0-1 .45-1 1s.45 1 1 1h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z"/>
            </svg>
        </button>
    </nav>
    <?php endif; ?>

    <!-- Banner image (1400×250) right below the nav -->
    <div class="header-banner<?= $bannerImage !== '' ? ' has-banner' : '' ?>"<?php if ($bannerImage !== ''): ?> style="background-image:url('<?= e(SITE_URL . $bannerImage) ?>')"<?php endif; ?>>
        <a href="<?= SITE_URL ?>/pages/index.php" class="site-logo"
           style="left:<?= e($overlayX) ?>%;top:<?= e($overlayY) ?>%;font-size:<?= e($overlaySize) ?>rem;color:<?= e($overlayColor) ?>;font-family:<?= e($overlayFontCSS) ?>;text-shadow:<?= e($overlayShadowCSS) ?>">
            <?= e(SITE_NAME) ?>
        </a>
    </div>

</header>

<!-- ── Flash messages ────────────────────────────────────────── -->
<div class="container">
<?= flash_render() ?>
