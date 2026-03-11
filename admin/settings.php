<?php
/**
 * settings.php — Admin site settings (banner image)
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$pageTitle = 'Admin – Site Settings';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'upload_banner') {
        // ── Banner image upload ─────────────────────────────────────────────
        if (!isset($_FILES['banner']) || $_FILES['banner']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'No file selected.';
        } elseif ($_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload error (code ' . (int)$_FILES['banner']['error'] . '). Please try again.';
        } else {
            $file = $_FILES['banner'];

            // Validate size (max 10 MB)
            $maxBytes = 10 * 1024 * 1024;
            if ($file['size'] > $maxBytes) {
                $error = 'File too large. Maximum banner size is 10 MB.';
            } else {
                // Validate MIME type via finfo (do not trust browser-supplied type)
                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file['tmp_name']);

                if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                    $error = 'Invalid file type. Allowed types: JPEG, PNG, GIF, WebP.';
                } else {
                    // Load via GD to strip EXIF metadata and re-encode
                    $src = match ($mimeType) {
                        'image/png'  => @imagecreatefrompng($file['tmp_name']),
                        'image/gif'  => @imagecreatefromgif($file['tmp_name']),
                        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
                        default      => @imagecreatefromjpeg($file['tmp_name']),
                    };

                    if ($src === false) {
                        $error = 'Could not process image. Please try a different file.';
                    } else {
                        $origW = imagesx($src);
                        $origH = imagesy($src);

                        // Apply client-supplied crop coordinates (in original-image pixels)
                        $cropX = max(0, (int)($_POST['banner_crop_x'] ?? 0));
                        $cropY = max(0, (int)($_POST['banner_crop_y'] ?? 0));
                        $cropW = (int)($_POST['banner_crop_w'] ?? 0);
                        $cropH = (int)($_POST['banner_crop_h'] ?? 0);

                        // Only use crop if all dimensions are valid
                        if ($cropW > 0 && $cropH > 0 &&
                            $cropX + $cropW <= $origW &&
                            $cropY + $cropH <= $origH) {
                            $cropped = imagecreatetruecolor($cropW, $cropH);
                            imagecopyresampled($cropped, $src, 0, 0, $cropX, $cropY, $cropW, $cropH, $cropW, $cropH);
                            imagedestroy($src);
                            $src   = $cropped;
                            $origW = $cropW;
                            $origH = $cropH;
                        }

                        // Resize to exactly 1400×250 px (fill-crop to preserve ratio)
                        $targetW = 1400;
                        $targetH = 250;

                        $scaleW = $targetW / $origW;
                        $scaleH = $targetH / $origH;
                        $scale  = max($scaleW, $scaleH);

                        $scaledW = (int)round($origW * $scale);
                        $scaledH = (int)round($origH * $scale);

                        // Centre-crop to target dimensions
                        $offsetX = (int)(($scaledW - $targetW) / 2);
                        $offsetY = (int)(($scaledH - $targetH) / 2);

                        $dst = imagecreatetruecolor($targetW, $targetH);
                        imagecopyresampled(
                            $dst, $src,
                            0, 0,
                            (int)round($offsetX / $scale), (int)round($offsetY / $scale),
                            $targetW, $targetH,
                            (int)round($targetW / $scale), (int)round($targetH / $scale)
                        );
                        imagedestroy($src);

                        $bannerDir  = UPLOADS_DIR . '/banner';
                        $filename   = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
                        $savePath   = $bannerDir . '/' . $filename;
                        $relPath    = '/uploads/banner/' . $filename;

                        if (!is_dir($bannerDir)) {
                            mkdir($bannerDir, 0755, true);
                        }

                        if (imagejpeg($dst, $savePath, 90)) {
                            imagedestroy($dst);

                            // Delete old banner file if it exists
                            $oldPath = site_setting('banner_image');
                            if ($oldPath !== '') {
                                $oldFile = SITE_ROOT . $oldPath;
                                if (file_exists($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }

                            // Upsert into site_settings
                            db_exec(
                                "INSERT INTO site_settings (`key`, value) VALUES ('banner_image', ?)
                                 ON DUPLICATE KEY UPDATE value = ?",
                                [$relPath, $relPath]
                            );

                            flash_set('success', 'Banner image updated successfully.');
                            redirect(SITE_URL . '/admin/settings.php');
                        } else {
                            imagedestroy($dst);
                            $error = 'Failed to save image. Please check directory permissions.';
                        }
                    }
                }
            }
        }
    } elseif ($action === 'remove_banner') {
        // ── Remove banner ───────────────────────────────────────────────────
        $oldPath = site_setting('banner_image');
        if ($oldPath !== '') {
            $oldFile = SITE_ROOT . $oldPath;
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }
        db_exec("UPDATE site_settings SET value = NULL WHERE `key` = 'banner_image'");

        flash_set('success', 'Banner image removed.');
        redirect(SITE_URL . '/admin/settings.php');
    } elseif ($action === 'save_theme') {
        // ── Save site colour theme ───────────────────────────────────────────
        $theme = in_array($_POST['site_theme'] ?? '', valid_themes(), true)
            ? $_POST['site_theme'] : 'blue-red';

        db_exec(
            "INSERT INTO site_settings (`key`, value) VALUES ('site_theme', ?)
             ON DUPLICATE KEY UPDATE value = ?",
            [$theme, $theme]
        );

        flash_set('success', 'Colour theme updated.');
        redirect(SITE_URL . '/admin/settings.php');
    } elseif ($action === 'save_overlay') {
        // ── Save site-name overlay position, size, colour, font & shadow ───
        $ox   = max(0, min(100, (float)($_POST['overlay_x']    ?? 50)));
        $oy   = max(0, min(100, (float)($_POST['overlay_y']    ?? 50)));
        $osiz = max(0.8, min(10, (float)($_POST['overlay_size'] ?? 2.4)));

        $ocolor = $_POST['overlay_color'] ?? '#ffffff';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $ocolor)) {
            $ocolor = '#ffffff';
        }

        $validFonts = ['system', 'serif', 'mono', 'impact'];
        $ofont = in_array($_POST['overlay_font'] ?? 'system', $validFonts, true)
            ? $_POST['overlay_font'] : 'system';

        $validShadows = ['none', 'light', 'medium', 'heavy'];
        $oshadow = in_array($_POST['overlay_shadow'] ?? 'medium', $validShadows, true)
            ? $_POST['overlay_shadow'] : 'medium';

        foreach ([
            'banner_overlay_x'      => number_format($ox,   2, '.', ''),
            'banner_overlay_y'      => number_format($oy,   2, '.', ''),
            'banner_overlay_size'   => number_format($osiz, 2, '.', ''),
            'banner_overlay_color'  => $ocolor,
            'banner_overlay_font'   => $ofont,
            'banner_overlay_shadow' => $oshadow,
        ] as $k => $v) {
            db_exec(
                "INSERT INTO site_settings (`key`, value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE value = ?",
                [$k, $v, $v]
            );
        }

        flash_set('success', 'Banner overlay settings saved.');
        redirect(SITE_URL . '/admin/settings.php');
    }
}

$currentBanner  = site_setting('banner_image');
$overlayX       = site_setting('banner_overlay_x',      '50');
$overlayY       = site_setting('banner_overlay_y',      '50');
$overlaySize    = site_setting('banner_overlay_size',   '2.4');
$overlayColor   = site_setting('banner_overlay_color',  '#ffffff');
$overlayFont    = site_setting('banner_overlay_font',   'system');
$overlayShadow  = site_setting('banner_overlay_shadow', 'medium');

$currentTheme = active_theme();

// CSS maps (shared via includes/overlay_maps.php; also mirrored in assets/js/app.js)
require_once SITE_ROOT . '/includes/overlay_maps.php';
$overlayFontCSS   = $OVERLAY_FONT_MAP[$overlayFont]     ?? $OVERLAY_FONT_MAP['system'];
$overlayShadowCSS = $OVERLAY_SHADOW_MAP[$overlayShadow] ?? $OVERLAY_SHADOW_MAP['medium'];

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <nav class="admin-nav">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?= SITE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php">Users</a></li>
            <li><a href="<?= SITE_URL ?>/admin/invites.php">Invites</a></li>
            <li><a href="<?= SITE_URL ?>/admin/moderation.php">Moderation</a></li>
            <li><a href="<?= SITE_URL ?>/admin/media.php">Media</a></li>
            <li><a href="<?= SITE_URL ?>/admin/settings.php" class="active">Site Settings</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>Site Settings</h1>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <!-- ── Banner Image ──────────────────────────────────────────── -->
        <section class="admin-section">
            <h2>Banner Image</h2>
            <p class="muted" style="margin-bottom:1rem">
                The banner image is displayed right below the navigation across all pages.
                <br>
                <strong>Target size:</strong> 1400 × 250 px &nbsp;|&nbsp;
                <strong>Max file size:</strong> 10 MB &nbsp;|&nbsp;
                <strong>Formats:</strong> JPEG, PNG, GIF, WebP
                <br>
                Select a crop area after choosing a file — the image will be saved at exactly 1400 × 250 px.
            </p>

            <?php if ($currentBanner !== ''): ?>
            <div class="banner-preview-wrap" style="margin-bottom:1.5rem">
                <p style="margin-bottom:.5rem;font-weight:600">Current banner:</p>
                <img src="<?= e(SITE_URL . $currentBanner) ?>"
                     alt="Current banner image"
                     class="banner-preview-img">
                <form method="POST" style="margin-top:.75rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_banner">
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Remove the banner image?')">
                        Remove Banner
                    </button>
                </form>
            </div>
            <?php else: ?>
            <p class="muted" style="margin-bottom:1rem">No banner image is currently set.</p>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="settings-form" id="banner-upload-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_banner">
                <!-- Crop coordinates (original-image pixels, populated by JS) -->
                <input type="hidden" name="banner_crop_x" id="banner-crop-x" value="0">
                <input type="hidden" name="banner_crop_y" id="banner-crop-y" value="0">
                <input type="hidden" name="banner_crop_w" id="banner-crop-w" value="0">
                <input type="hidden" name="banner_crop_h" id="banner-crop-h" value="0">

                <div class="form-group">
                    <label for="banner" class="form-label">
                        <?= $currentBanner !== '' ? 'Replace banner image' : 'Upload banner image' ?>
                    </label>
                    <input type="file" id="banner-file-input" name="banner"
                           accept="image/jpeg,image/png,image/gif,image/webp"
                           class="form-control" required>
                </div>

                <!-- Crop canvas (shown after file is chosen) -->
                <div id="banner-crop-container">
                    <p class="muted" style="margin-bottom:.5rem">
                        Drag to select a crop area (7:2 ratio highlighted). You can move the selection.
                    </p>
                    <canvas id="banner-crop-canvas"></canvas>
                    <div class="banner-crop-actions">
                        <button type="button" id="banner-crop-reset" class="btn btn-secondary btn-sm">Reset crop</button>
                        <span class="muted" style="font-size:.85rem" id="banner-crop-info"></span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top:1rem">Upload Banner</button>
            </form>
        </section>

        <!-- ── Site Name Overlay ─────────────────────────────────────── -->
        <section class="admin-section" style="margin-top:2rem">
            <h2>Site Name Overlay</h2>
            <p class="muted" style="margin-bottom:1rem">
                Drag the site name text in the preview below to reposition it.
                Use the controls below to adjust font size, text colour, font family, and drop shadow.
                Changes are saved when you click <strong>Save Overlay</strong>.
            </p>

            <!-- Live preview -->
            <div class="banner-overlay-preview" id="overlay-preview"
                 <?php if ($currentBanner !== ''): ?>
                 style="background-image:url('<?= e(SITE_URL . $currentBanner) ?>')"
                 <?php endif; ?>>
                <span class="banner-overlay-handle" id="overlay-handle"
                      style="left:<?= e($overlayX) ?>%;top:<?= e($overlayY) ?>%;font-size:<?= e($overlaySize) ?>rem;color:<?= e($overlayColor) ?>;font-family:<?= e($overlayFontCSS) ?>;text-shadow:<?= e($overlayShadowCSS) ?>"
                      title="Drag to reposition">
                    <?= e(SITE_NAME) ?>
                </span>
            </div>

            <form method="POST" class="settings-form" id="overlay-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_overlay">
                <input type="hidden" name="overlay_x"    id="overlay-x-input"    value="<?= e($overlayX) ?>">
                <input type="hidden" name="overlay_y"    id="overlay-y-input"    value="<?= e($overlayY) ?>">
                <input type="hidden" name="overlay_size" id="overlay-size-input" value="<?= e($overlaySize) ?>">

                <div class="form-group" style="max-width:320px">
                    <label class="form-label">Font Size (<?= e($overlaySize) ?>rem)
                        <span id="overlay-size-label"></span>
                    </label>
                    <input type="range" id="overlay-size-range"
                           min="0.8" max="6" step="0.1"
                           value="<?= e($overlaySize) ?>"
                           style="width:100%;accent-color:var(--color-accent)">
                </div>

                <div class="form-group" style="max-width:320px">
                    <label class="form-label" for="overlay-color-input">Text Color</label>
                    <input type="color" id="overlay-color-input" name="overlay_color"
                           value="<?= e($overlayColor) ?>"
                           style="width:100%;height:2.5rem;padding:0.2rem;cursor:pointer;border:1px solid var(--color-border);border-radius:var(--radius-sm);background:var(--color-surface2)">
                </div>

                <div class="form-group" style="max-width:320px">
                    <label class="form-label" for="overlay-font-select">Font</label>
                    <select id="overlay-font-select" name="overlay_font" class="form-control">
                        <option value="system"<?= $overlayFont === 'system' ? ' selected' : '' ?>>System (default)</option>
                        <option value="serif"<?= $overlayFont === 'serif'  ? ' selected' : '' ?>>Serif (Georgia)</option>
                        <option value="mono"<?= $overlayFont === 'mono'   ? ' selected' : '' ?>>Monospace (Courier)</option>
                        <option value="impact"<?= $overlayFont === 'impact' ? ' selected' : '' ?>>Impact</option>
                    </select>
                </div>

                <div class="form-group" style="max-width:320px">
                    <label class="form-label" for="overlay-shadow-select">Drop Shadow</label>
                    <select id="overlay-shadow-select" name="overlay_shadow" class="form-control">
                        <option value="none"<?= $overlayShadow === 'none'   ? ' selected' : '' ?>>None</option>
                        <option value="light"<?= $overlayShadow === 'light'  ? ' selected' : '' ?>>Light</option>
                        <option value="medium"<?= $overlayShadow === 'medium' ? ' selected' : '' ?>>Medium (default)</option>
                        <option value="heavy"<?= $overlayShadow === 'heavy'  ? ' selected' : '' ?>>Heavy</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save Overlay</button>
            </form>
        </section>

        <!-- ── Colour Theme ──────────────────────────────────────── -->
        <section class="admin-section" style="margin-top:2rem">
            <h2>Colour Theme</h2>
            <p class="muted" style="margin-bottom:1rem">
                Choose a global colour scheme for the entire site by clicking a swatch below.
                The change takes effect immediately for all visitors.
            </p>

            <form method="POST" class="settings-form" id="theme-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_theme">
                <input type="hidden" name="site_theme" id="site-theme-input" value="<?= e($currentTheme) ?>">

                <!-- Clickable swatch previews -->
                <div role="radiogroup" aria-label="Theme selection"
                     style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem">
                    <?php
                    $themePreviews = [
                        'blue-red'    => ['bg' => '#1a1a2e', 'surface' => '#16213e', 'accent' => '#e94560', 'label' => 'Blue &amp; Red'],
                        'gray-orange' => ['bg' => '#1c1c1c', 'surface' => '#252525', 'accent' => '#e87c2a', 'label' => 'Gray &amp; Orange'],
                        'purple-red'  => ['bg' => '#1a0a2e', 'surface' => '#230e3c', 'accent' => '#e94560', 'label' => 'Purple &amp; Red'],
                        'green-teal'  => ['bg' => '#0a1a0f', 'surface' => '#0f2418', 'accent' => '#00bfa5', 'label' => 'Green &amp; Teal'],
                        'dark-gold'   => ['bg' => '#111111', 'surface' => '#1c1c1c', 'accent' => '#f0a500', 'label' => 'Dark &amp; Gold'],
                        'navy-cyan'   => ['bg' => '#050d1f', 'surface' => '#0a1530', 'accent' => '#00bcd4', 'label' => 'Navy &amp; Cyan'],
                    ];
                    foreach ($themePreviews as $slug => $tp):
                        $active = $currentTheme === $slug;
                    ?>
                    <div class="theme-swatch<?= $active ? ' theme-swatch--active' : '' ?>"
                         data-theme-slug="<?= e($slug) ?>"
                         title="<?= $tp['label'] ?>"
                         role="radio"
                         aria-checked="<?= $active ? 'true' : 'false' ?>"
                         tabindex="0"
                         style="border:2px solid <?= $active ? '#e94560' : 'rgba(255,255,255,.15)' ?>;border-radius:var(--radius-md);overflow:hidden;width:140px;cursor:pointer;transition:border-color .2s,box-shadow .2s;<?= $active ? 'box-shadow:0 0 0 3px rgba(233,69,96,.35)' : '' ?>">
                        <div style="background:<?= $tp['bg'] ?>;padding:.6rem .75rem">
                            <div style="background:<?= $tp['surface'] ?>;border-radius:var(--radius-sm);padding:.4rem .5rem;margin-bottom:.4rem;font-size:.75rem;color:#e0e0e0">Surface</div>
                            <div style="background:<?= $tp['accent'] ?>;border-radius:var(--radius-sm);padding:.3rem .5rem;font-size:.75rem;color:#fff;font-weight:600"><?= $tp['label'] ?></div>
                        </div>
                        <div style="background:<?= $tp['bg'] ?>;padding:.35rem .75rem;font-size:.7rem;color:rgba(255,255,255,.5);text-align:center">
                            <?= $active ? '&#10003; Active' : '&nbsp;' ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" id="theme-submit-btn" hidden aria-hidden="true">Save Theme</button>
            </form>

            <style>
            .theme-swatch:focus-visible {
                outline: 2px solid var(--color-accent);
                outline-offset: 2px;
            }
            </style>

            <script>
            (function () {
                var ACTIVE_BORDER   = '#e94560';
                var ACTIVE_SHADOW   = '0 0 0 3px rgba(233,69,96,.35)';
                var HOVER_BORDER    = '#4caf50';
                var HOVER_SHADOW    = '0 0 0 3px rgba(76,175,80,.35)';
                var INACTIVE_BORDER = 'rgba(255,255,255,.15)';

                var swatches   = document.querySelectorAll('.theme-swatch');
                var input      = document.getElementById('site-theme-input');
                var submitBtn  = document.getElementById('theme-submit-btn');

                function selectSwatch(sw) {
                    swatches.forEach(function (s) {
                        var isActive = s === sw;
                        s.setAttribute('aria-checked', isActive ? 'true' : 'false');
                        s.style.borderColor = isActive ? ACTIVE_BORDER : INACTIVE_BORDER;
                        s.style.boxShadow   = isActive ? ACTIVE_SHADOW : '';
                        s.classList.toggle('theme-swatch--active', isActive);
                        var label = s.querySelector('div:last-child');
                        if (label) { label.innerHTML = isActive ? '&#10003; Active' : '&nbsp;'; }
                    });
                    input.value = sw.dataset.themeSlug;
                    submitBtn.click();
                }

                swatches.forEach(function (sw) {
                    sw.addEventListener('click', function () { selectSwatch(sw); });
                    sw.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            selectSwatch(sw);
                        }
                    });
                    sw.addEventListener('mouseenter', function () {
                        if (!sw.classList.contains('theme-swatch--active')) {
                            sw.style.borderColor = HOVER_BORDER;
                            sw.style.boxShadow   = HOVER_SHADOW;
                        }
                    });
                    sw.addEventListener('mouseleave', function () {
                        if (!sw.classList.contains('theme-swatch--active')) {
                            sw.style.borderColor = INACTIVE_BORDER;
                            sw.style.boxShadow   = '';
                        }
                    });
                });
            }());
            </script>
        </section>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
