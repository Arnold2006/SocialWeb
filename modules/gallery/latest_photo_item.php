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
 *   $galleryUrl  string  URL to the user's gallery (for the "view more" link)
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
    <div class="latest-photo-owner">
        <a href="<?= e($galleryUrl) ?>" class="latest-photo-owner-link">
            <img src="<?= e($ownerAvatarUrl) ?>" alt="<?= e($ownerUsername) ?>"
                 class="latest-photo-owner-avatar" width="24" height="24" loading="lazy">
            <span><?= e($ownerUsername) ?></span>
        </a>
    </div>
</div>
