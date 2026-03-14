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
$notifCount  = $user ? unread_notifications_count() : 0;
$msgCount    = $user ? unread_messages_count() : 0;
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
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($siteTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?></title>
    <meta name="site-url" content="<?= SITE_URL ?>">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
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
<header class="site-header">

    <!-- Navigation is the very first element (sticky top) -->
    <?php if ($user): ?>
    <nav class="main-nav">
        <ul>
            <li><a href="<?= SITE_URL ?>/pages/index.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'index.php')) ? 'active' : '' ?>">Wall</a></li>
            <li><a href="<?= SITE_URL ?>/pages/members.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'members.php')) ? 'active' : '' ?>">Members</a></li>
            <li><a href="<?= SITE_URL ?>/pages/profile.php?id=<?= (int)$user['id'] ?>"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'profile.php')) ? 'active' : '' ?>">My Profile</a></li>
            <li><a href="<?= SITE_URL ?>/pages/photos.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'photos.php')) ? 'active' : '' ?>">Photos</a></li>
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
                   class="<?= str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/') ? 'active' : '' ?>">Admin</a></li>
            <?php endif; ?>
            <li><a href="<?= SITE_URL ?>/pages/logout.php">Logout</a></li>
        </ul>
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
