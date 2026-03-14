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
 * masonry_layout.js — Gap-free masonry grid for the media gallery.
 *
 * Replaces the CSS column-count approach with a JavaScript-driven layout
 * that tracks each column's height and always places the next item in the
 * shortest column — eliminating the gaps that CSS columns leave at the
 * bottom of a page or after infinite-scroll batches.
 *
 * Works seamlessly with the preloading infinite scroll: because images have
 * `width` / `height` HTML attributes the browser can calculate each item's
 * intrinsic height before the full image downloads, so layout is stable from
 * the very first paint.
 *
 * Public API (used by gallery_infinite_scroll.js):
 *   window.masonryLayout(gridElement)  — (re-)layout the given grid
 */

'use strict';

(function () {

    /** Return the number of columns for the current viewport width. */
    function getColumnCount() {
        var w = window.innerWidth;
        if (w <= 600) return 2;
        if (w <= 900) return 3;
        return 4;
    }

    /**
     * Perform a full masonry layout pass on the given grid element.
     *
     * Algorithm:
     *  1. Compute the gap from the root font-size so it matches `0.75rem`.
     *  2. Set the pixel-width of every item so the browser can resolve the
     *     aspect-ratio heights (images have explicit width/height attrs).
     *  3. Read each item's offsetHeight in a single batch (one reflow).
     *  4. Walk the items in DOM order, place each one in the shortest column
     *     by writing its `left` and `top` style.
     *  5. Set the grid's height to the tallest column so the page flow is
     *     correct below it.
     */
    function layout(grid) {
        if (!grid) return;

        var items = Array.from(grid.querySelectorAll('.media-item'));
        if (items.length === 0) {
            grid.style.height = '';
            return;
        }

        var cols     = getColumnCount();
        // Compute gap from root font-size so it always tracks 0.75 rem
        var rootFontSize = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
        var gapPx    = Math.round(0.75 * rootFontSize);
        var gridW    = grid.clientWidth;
        var colWidth = Math.floor((gridW - gapPx * (cols - 1)) / cols);

        // ── Pass 1: write widths so the browser can compute aspect-ratio heights ──
        items.forEach(function (item) {
            item.style.position = 'absolute';
            item.style.width    = colWidth + 'px';

            // Explicitly set aspect-ratio on images with known dimensions.
            // Some browsers only compute the intrinsic height of a
            // `loading="lazy"` image after it has been fetched; forcing
            // aspect-ratio via CSS ensures the layout always uses the correct
            // height even for images that are still off-screen.
            var img = item.querySelector('img[width][height]');
            if (img) {
                var w = parseInt(img.getAttribute('width'),  10);
                var h = parseInt(img.getAttribute('height'), 10);
                if (w > 0 && h > 0) {
                    img.style.aspectRatio = w + '/' + h;
                }
            }
        });

        // ── Pass 2: read heights (one synchronous reflow) ──
        var itemHeights = items.map(function (item) {
            return item.offsetHeight;
        });

        // ── Pass 3: assign left / top via shortest-column algorithm ──
        var colHeights = new Array(cols).fill(0);

        items.forEach(function (item, i) {
            var minH  = Math.min.apply(null, colHeights);
            var col   = colHeights.indexOf(minH);

            item.style.left = (col * (colWidth + gapPx)) + 'px';
            item.style.top  = colHeights[col] + 'px';

            colHeights[col] += itemHeights[i] + gapPx;
        });

        // ── Set grid container height so content below it is pushed down ──
        grid.style.height = Math.max.apply(null, colHeights) + 'px';
    }

    // ── Debounced resize handler ──────────────────────────────────────────────
    var resizeTimer = null;

    function onResize() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            var grid = document.getElementById('lightbox-gallery');
            if (grid) layout(grid);
        }, 150);
    }

    // ── Expose public API ────────────────────────────────────────────────────
    window.masonryLayout = layout;

    // ── Initial layout on DOMContentLoaded ──────────────────────────────────
    function init() {
        var grid = document.getElementById('lightbox-gallery');
        if (!grid) return;

        layout(grid);
        window.addEventListener('resize', onResize);

        // Re-run layout as lazy images finish loading.  Some browsers only
        // compute the intrinsic height of a `loading="lazy"` image once it
        // has actually been fetched, so the first layout pass may use a height
        // of 0 for images that are off-screen.  Listening for each image's
        // load event (debounced) keeps the grid correct without extra cost.
        var layoutTimer = null;
        function scheduleLayout() {
            clearTimeout(layoutTimer);
            layoutTimer = setTimeout(function () { layout(grid); }, 50);
        }
        Array.from(grid.querySelectorAll('img')).forEach(function (img) {
            if (!img.complete) {
                img.addEventListener('load', scheduleLayout, { once: true });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
