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
 * photos_latest.js — Load More handler for the Photos hub latest-images masonry.
 *
 * Reads initial state from #photos-latest-grid data attributes, then handles
 * the "Load more" button click to fetch and append additional items.
 */

'use strict';

(function () {
    var grid    = document.getElementById('photos-latest-grid');
    var btn     = document.getElementById('photos-load-more-btn');
    var wrap    = document.getElementById('photos-load-more-wrap');

    if (!grid || !btn || !wrap) return;

    var offset  = parseInt(grid.dataset.offset || '0', 10);
    var hasMore = grid.dataset.hasMore === '1';
    var loading = false;

    var baseUrl = (document.querySelector('meta[name="site-url"]') || {}).content || '';

    if (!hasMore) wrap.style.display = 'none';

    btn.addEventListener('click', function () {
        if (loading || !hasMore) return;
        loading = true;
        btn.disabled = true;
        btn.textContent = 'Loading…';

        var safeOffset = Math.max(0, parseInt(offset, 10) || 0);
        fetch(baseUrl + '/modules/gallery/get_latest_photos.php?offset=' + encodeURIComponent(safeOffset), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (resp) { return resp.json(); })
        .then(function (result) {
            if (result.ok && result.html) {
                var temp = document.createElement('div');
                temp.innerHTML = result.html;
                var newItems = Array.from(temp.querySelectorAll('.media-item'));

                newItems.forEach(function (item) {
                    item.classList.add('media-item-new');
                    grid.appendChild(item);
                });

                offset += newItems.length;
                grid.dataset.offset = String(offset);

                // Bind lazy-loading and lightbox to new items
                newItems.forEach(function (item) {
                    if (typeof window.lazyObserveImages === 'function') {
                        window.lazyObserveImages(item);
                    }
                    if (typeof window.lightboxBindNew === 'function') {
                        window.lightboxBindNew(item);
                    }
                });
            }

            hasMore = !!(result.ok && result.has_more);
            if (!hasMore) {
                wrap.style.display = 'none';
            } else {
                btn.disabled = false;
                btn.textContent = 'Load more';
            }
        })
        .catch(function (err) {
            console.error('Latest photos load error:', err);
            btn.disabled = false;
            btn.textContent = 'Load more';
        })
        .finally(function () {
            loading = false;
        });
    });
}());
