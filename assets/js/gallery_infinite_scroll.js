/**
 * gallery_infinite_scroll.js — Smooth infinite scroll for album media grid.
 *
 * Inspired by the Oxwall photo plugin approach:
 *   - Thumbnails are preloaded in the background before being inserted into
 *     the DOM, so items appear fully rendered instead of blank → smooth.
 *   - All items in a fetched batch reveal together with one gentle animation
 *     rather than a per-item stagger, removing the "jerk" feeling.
 *
 * Watches a sentinel element at the bottom of the media grid.
 * When the sentinel enters the viewport the next batch of media items
 * is fetched automatically and appended to the grid.
 */

'use strict';

(function () {
    const grid     = document.getElementById('lightbox-gallery');
    const sentinel = document.getElementById('media-load-sentinel');
    const spinner  = document.getElementById('media-load-spinner');

    if (!grid || !sentinel) return;

    const albumId = grid.dataset.albumId;
    const userId  = grid.dataset.userId;
    let offset    = parseInt(grid.dataset.offset || '0', 10);
    let hasMore   = grid.dataset.hasMore === '1';
    let loading   = false;

    const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';

    function showSpinner() { if (spinner) spinner.style.display = 'flex'; }
    function hideSpinner() { if (spinner) spinner.style.display = 'none'; }

    function removeSentinel() {
        sentinel.remove();
        if (spinner) spinner.remove();
        if (obs) obs.disconnect();
    }

    /**
     * Preload all thumbnail images in a batch of new DOM items before they
     * are appended to the page.  Resolves once every thumbnail has either
     * loaded or errored.  A 2-second safety timeout prevents stalling when a
     * thumbnail is unreachable.
     */
    function preloadThumbnails(items) {
        var srcs = [];
        items.forEach(function (item) {
            var img = item.querySelector('img[src]');
            if (img && img.src) srcs.push(img.src);
        });

        if (srcs.length === 0) return Promise.resolve();

        return new Promise(function (resolve) {
            var remaining = srcs.length;
            var timer = null;

            function done() {
                if (--remaining <= 0) {
                    clearTimeout(timer);
                    resolve();
                }
            }

            srcs.forEach(function (src) {
                var loader = new Image();
                loader.onload  = done;
                loader.onerror = done;
                loader.src     = src;
            });

            // Safety net: never block the append for more than 2 s
            timer = setTimeout(resolve, 2000);
        });
    }

    async function loadMore() {
        if (loading || !hasMore) return;
        loading = true;
        showSpinner();

        try {
            const url = baseUrl + '/modules/gallery/get_media.php'
                + '?album_id=' + encodeURIComponent(albumId)
                + '&user_id='  + encodeURIComponent(userId)
                + '&offset='   + encodeURIComponent(offset);

            const resp   = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await resp.json();

            if (result.ok && result.html) {
                // Parse the HTML into real DOM nodes
                const temp = document.createElement('div');
                temp.innerHTML = result.html;
                const newItems = Array.from(temp.querySelectorAll('.media-item'));

                if (newItems.length > 0) {
                    // Preload thumbnails before showing — no blank-box flicker
                    await preloadThumbnails(newItems);

                    // Append and mark for the batch-reveal animation
                    newItems.forEach(function (item) {
                        item.classList.add('media-item-new');
                        grid.appendChild(item);
                    });

                    offset += newItems.length;
                    grid.dataset.offset = String(offset);

                    // Re-run masonry layout to place new items without gaps
                    if (typeof window.masonryLayout === 'function') {
                        window.masonryLayout(grid);
                    }

                    // Wire up lazy medium-res upgrade and lightbox on new items
                    newItems.forEach(function (item) {
                        if (typeof window.lazyObserveImages === 'function') {
                            window.lazyObserveImages(item);
                        }
                        if (typeof window.lightboxBindNew === 'function') {
                            window.lightboxBindNew(item);
                        }
                    });
                }
            }

            hasMore = !!(result.ok && result.has_more);
            if (!hasMore) removeSentinel();
        } catch (err) {
            console.error('Gallery infinite scroll error:', err);
        } finally {
            loading = false;
            hideSpinner();
        }
    }

    var obs = null;

    if (!('IntersectionObserver' in window)) {
        loadMore();
        return;
    }

    obs = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) loadMore();
            });
        },
        { rootMargin: '300px 0px', threshold: 0 }
    );

    obs.observe(sentinel);
}());
