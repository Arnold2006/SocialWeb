<?php
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
            <li><a href="<?= SITE_URL ?>/pages/gallery.php?user_id=<?= (int)$user['id'] ?>"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'gallery.php')) ? 'active' : '' ?>">My Gallery</a></li>
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
            <li><a href="<?= SITE_URL ?>/pages/settings.php"
                   class="<?= (str_ends_with($_SERVER['PHP_SELF'] ?? '', 'settings.php')) ? 'active' : '' ?>">Settings</a></li>
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
