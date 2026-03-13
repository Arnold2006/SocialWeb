/**
 * gallery_infinite_scroll.js — Infinite scroll for album media grid.
 *
 * Watches a sentinel element at the bottom of the media grid.
 * When the sentinel enters the viewport the next batch of 25 media items
 * is fetched automatically and appended to the grid.
 */

'use strict';

(function () {
    const grid     = document.getElementById('lightbox-gallery');
    const sentinel = document.getElementById('media-load-sentinel');
    const spinner  = document.getElementById('media-load-spinner');

    if (!grid || !sentinel) return;

    // Album metadata stored as data attributes on the grid element
    const albumId = grid.dataset.albumId;
    const userId  = grid.dataset.userId;
    let offset    = parseInt(grid.dataset.offset || '0', 10);
    let hasMore   = grid.dataset.hasMore === '1';
    let loading   = false;

    const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';

    function showSpinner() {
        if (spinner) spinner.style.display = 'flex';
    }

    function hideSpinner() {
        if (spinner) spinner.style.display = 'none';
    }

    function removeSentinel() {
        sentinel.remove();
        if (spinner) spinner.remove();
        if (obs) obs.disconnect();
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
                const countBefore = grid.querySelectorAll('.media-item').length;
                grid.insertAdjacentHTML('beforeend', result.html);
                offset += grid.querySelectorAll('.media-item').length - countBefore;

                // Initialise lazy image loading and lightbox on new items
                const allItems = grid.querySelectorAll('.media-item');
                const newItems = Array.from(allItems).slice(countBefore);
                newItems.forEach(function (item) {
                    if (typeof window.lazyObserveImages === 'function') {
                        window.lazyObserveImages(item);
                    }
                    if (typeof window.lightboxBindNew === 'function') {
                        window.lightboxBindNew(item);
                    }
                });

                // Update the grid's offset attribute for consistency
                grid.dataset.offset = String(offset);
            }

            hasMore = !!(result.ok && result.has_more);
            if (!hasMore) {
                removeSentinel();
            }
        } catch (err) {
            console.error('Gallery infinite scroll error:', err);
        } finally {
            loading = false;
            hideSpinner();
        }
    }

    var obs = null;

    if (!('IntersectionObserver' in window)) {
        // Fallback: load all remaining items immediately
        loadMore();
        return;
    }

    obs = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    loadMore();
                }
            });
        },
        { rootMargin: '300px 0px', threshold: 0 }
    );

    obs.observe(sentinel);
}());
