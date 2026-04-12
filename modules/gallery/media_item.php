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
 * media_item.php — Shared template for a single album media item.
 *
 * Expected variables in scope:
 *   $media           array   Media row (with like_count, comment_count columns)
 *   $isCover         bool    Whether this item is the album cover
 *   $isOwn           bool    Whether the viewer owns the album
 *   $albumId         int     Current album ID
 *   $allOwnerAlbums  array   Other albums of the owner (for Move button; may be empty)
 */

declare(strict_types=1);
?>
<div class="media-item<?= $isCover ? ' is-cover' : '' ?>">
    <?php if ($media['type'] === 'image'): ?>
    <a href="<?= e(get_media_url($media, 'original')) ?>"
       class="lightbox-trigger"
       data-src="<?= e(get_media_url($media, 'large')) ?>"
       data-media-id="<?= (int)$media['id'] ?>">
        <img src="<?= e(get_media_url($media, 'thumb')) ?>"
             data-src="<?= e(get_media_url($media, 'medium')) ?>"
             alt="" class="lazy-image" loading="lazy"
             <?php if (!empty($media['width']) && !empty($media['height'])): ?>
             width="<?= (int)$media['width'] ?>" height="<?= (int)$media['height'] ?>"
             style="aspect-ratio: <?= (int)$media['width'] ?>/<?= (int)$media['height'] ?>"
             <?php endif; ?>>
    </a>
    <?php if ($isCover): ?>
    <span class="cover-badge">★ Cover</span>
    <?php endif; ?>
    <?php else: ?>
    <?php
        $videoThumb = !empty($media['thumbnail_path'])
            ? e(get_media_url($media, 'thumbnail'))
            : e(SITE_URL . '/assets/images/placeholder.svg');
        $videoSrc = e(get_media_url($media, 'original'));
    ?>
    <a href="<?= $videoSrc ?>"
       class="lightbox-trigger video-thumb-wrap"
       data-src="<?= $videoThumb ?>"
       data-video-src="<?= $videoSrc ?>"
       data-media-id="<?= (int)$media['id'] ?>"
       aria-label="Play video">
        <img src="<?= $videoThumb ?>" alt="" class="media-video-thumb" loading="lazy">
        <span class="video-play-icon" aria-hidden="true">&#9654;</span>
    </a>
    <?php if ($isCover): ?>
    <span class="cover-badge">★ Cover</span>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ((int)$media['like_count'] > 0 || (int)$media['comment_count'] > 0): ?>
    <div class="media-stats">
        <?php if ((int)$media['like_count'] > 0): ?>
        <span class="media-stat media-stat-likes">♥ <?= (int)$media['like_count'] ?></span>
        <?php endif; ?>
        <?php if ((int)$media['comment_count'] > 0): ?>
        <span class="media-stat media-stat-comments">💬 <?= (int)$media['comment_count'] ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($media['type'] === 'video'): ?>
    <div class="media-item-top-actions">
        <a href="<?= e(get_media_url($media, 'original')) ?>"
           download
           class="btn btn-xs btn-secondary">&#8595; Download</a>
        <?php if ($isOwn): ?>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_media">
            <input type="hidden" name="media_id" value="<?= (int)$media['id'] ?>">
            <button type="submit" class="btn btn-danger btn-xs"
                    data-confirm="Delete this media?">✕</button>
        </form>
        <?php if (empty($media['thumbnail_path'])): ?>
        <?php if (!empty($allOwnerAlbums ?? [])): ?>
        <button type="button"
                class="btn btn-xs btn-secondary move-media-btn"
                data-media-id="<?= (int)$media['id'] ?>">
            ↗ Move
        </button>
        <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php if ($isOwn && !empty($media['thumbnail_path'])): ?>
    <div class="media-item-actions">
        <button type="button"
                class="btn btn-xs btn-secondary set-cover-btn"
                data-media-id="<?= (int)$media['id'] ?>"
                data-media-src="<?= e(get_media_url($media, 'thumbnail')) ?>"
                data-album-id="<?= $albumId ?>"
                data-orig-width="0"
                data-orig-height="0">
            <?= $isCover ? '★' : '☆' ?> Cover
        </button>
        <?php if (!empty($allOwnerAlbums ?? [])): ?>
        <button type="button"
                class="btn btn-xs btn-secondary move-media-btn"
                data-media-id="<?= (int)$media['id'] ?>">
            ↗ Move
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php elseif ($isOwn): ?>
    <?php if (!empty($allOwnerAlbums ?? [])): ?>
    <div class="media-item-top-actions">
        <button type="button"
                class="btn btn-xs btn-secondary move-media-btn"
                data-media-id="<?= (int)$media['id'] ?>">
            ↗ Move
        </button>
    </div>
    <?php endif; ?>
    <div class="media-item-actions">
        <button type="button"
                class="btn btn-xs btn-secondary set-cover-btn"
                data-media-id="<?= (int)$media['id'] ?>"
                data-media-src="<?= e(get_media_url($media, 'medium')) ?>"
                data-album-id="<?= $albumId ?>"
                data-orig-width="<?= (int)$media['width'] ?>"
                data-orig-height="<?= (int)$media['height'] ?>">
            <?= $isCover ? '★' : '☆' ?> Cover
        </button>
        <form method="POST" class="media-delete-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_media">
            <input type="hidden" name="media_id" value="<?= (int)$media['id'] ?>">
            <button type="submit" class="btn btn-danger btn-xs"
                    data-confirm="Delete this media?">✕</button>
        </form>
    </div>
    <?php endif; ?>
</div>
