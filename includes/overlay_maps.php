<?php
/**
 * overlay_maps.php — Banner overlay CSS preset maps
 *
 * Shared by includes/header.php and admin/settings.php.
 * The same presets must also be reflected in assets/js/app.js (fontMap / shadowMap).
 */

declare(strict_types=1);

/** @var array<string,string> Maps font preset key → CSS font-family stack */
$OVERLAY_FONT_MAP = [
    'system'  => 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif',
    'serif'   => 'Georgia,"Times New Roman",Times,serif',
    'mono'    => '"Courier New",Courier,monospace',
    'impact'  => 'Impact,Haettenschweiler,"Arial Narrow Bold",sans-serif',
];

/** @var array<string,string> Maps shadow preset key → CSS text-shadow value */
$OVERLAY_SHADOW_MAP = [
    'none'   => 'none',
    'light'  => '0 1px 4px rgba(0,0,0,.5)',
    'medium' => '0 2px 8px rgba(0,0,0,.7)',
    'heavy'  => '0 3px 12px rgba(0,0,0,.9)',
];
