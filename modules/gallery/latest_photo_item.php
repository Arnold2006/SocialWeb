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
 * latest_photo_item.php — Minimal masonry item for the Photos hub feed.
 *
 * Expected variables in scope:
 *   $media       array   Media row (type = 'image'; includes width, height)
 */

declare(strict_types=1);
?>
<div class="media-item latest-photo-item">
    <a href="<?= e(get_media_url($media, 'original')) ?>"
       class="lightbox-trigger"
       data-src="<?= e(get_media_url($media, 'large')) ?>"
       data-media-id="<?= (int)$media['id'] ?>">
        <img src="<?= e(get_media_url($media, 'thumb')) ?>"
             data-src="<?= e(get_media_url($media, 'medium')) ?>"
             alt="" class="lazy-image" loading="lazy">
    </a>
</div>
