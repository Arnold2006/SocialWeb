/**
 * progressive_loader.js — Lazy medium-res upgrade
 *
 * Thumbnails are shown immediately (src attribute).
 * When a lazy image enters the viewport the medium-resolution version
 * (data-src) is fetched in the background and swapped in silently,
 * matching the Oxwall approach: load first, then display — no stutter.
 *
 * Uses IntersectionObserver for performance; falls back to immediate load.
 */

'use strict';

(function () {
    const LAZY_CLASS   = 'lazy-image';
    const LOADED_CLASS = 'loaded';

    /** Preload medium-res in background then swap src silently */
    function upgradeImage(img) {
        const fullSrc = img.dataset.src;
        if (!fullSrc || img.classList.contains(LOADED_CLASS)) return;

        // Mark early so repeated observer firings are no-ops
        img.classList.add(LOADED_CLASS);
        delete img.dataset.src;

        // Defer starting the fetch by one tick (Oxwall-style) so the browser
        // can finish painting the already-visible thumbnail before the network
        // request begins.  The actual src swap only happens inside onload once
        // the full image has downloaded — never sooner.
        setTimeout(function () {
            var loader = new Image();
            loader.onload = function () { img.src = fullSrc; };
            // On error we already show the thumbnail — nothing more to do
            loader.src = fullSrc;
        }, 1);
    }

    /** Shared observer instance */
    var sharedObserver = null;

    function getObserver() {
        if (sharedObserver) return sharedObserver;
        if (!('IntersectionObserver' in window)) return null;
        sharedObserver = new IntersectionObserver(
            function (entries, obs) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        obs.unobserve(entry.target);
                        upgradeImage(entry.target);
                    }
                });
            },
            {
                rootMargin: '200px 0px',
                threshold: 0.01,
            }
        );
        return sharedObserver;
    }

    /**
     * Observe all unloaded lazy images within the given container (or the whole
     * document when no container is provided). Safe to call multiple times.
     */
    function observeImages(container) {
        var ctx = container || document;
        var lazyImages = ctx.querySelectorAll('.' + LAZY_CLASS + '[data-src]');
        if (lazyImages.length === 0) return;

        var observer = getObserver();
        if (!observer) {
            // Fallback: upgrade all immediately
            lazyImages.forEach(upgradeImage);
            return;
        }

        lazyImages.forEach(function (img) { observer.observe(img); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { observeImages(document); });
    } else {
        observeImages(document);
    }

    // Public API for dynamically added content (infinite scroll, etc.)
    window.lazyObserveImages = observeImages;
})();
