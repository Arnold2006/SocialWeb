/**
 * progressive_loader.js — Progressive image loading
 *
 * Images with class "lazy-image" and a data-src attribute will:
 *  1. Load the small src (from src attribute) immediately
 *  2. Lazy-load the larger image (data-src) when in viewport
 *  3. Fade in once the full image is loaded
 *
 * Uses IntersectionObserver for performance.
 */

'use strict';

(function () {
    const LAZY_CLASS   = 'lazy-image';
    const LOADED_CLASS = 'loaded';

    /** Swap src to the high-res version and mark as loaded */
    function loadFullImage(img) {
        const fullSrc = img.dataset.src;
        if (!fullSrc || img.classList.contains(LOADED_CLASS)) return;

        const loader  = new Image();
        loader.onload = () => {
            img.src = fullSrc;
            img.classList.add(LOADED_CLASS);
            delete img.dataset.src;
        };
        loader.onerror = () => {
            // Fallback: still show small image as loaded
            img.classList.add(LOADED_CLASS);
        };
        loader.src = fullSrc;
    }

    /** Load all images that don't have data-src (i.e. already at full res) */
    function markNoLazyAsLoaded() {
        document.querySelectorAll('.' + LAZY_CLASS).forEach(img => {
            if (!img.dataset.src) {
                img.classList.add(LOADED_CLASS);
            }
        });
    }

    /** Set up IntersectionObserver for lazy images */
    function initLazyLoader() {
        const lazyImages = document.querySelectorAll('.' + LAZY_CLASS + '[data-src]');
        if (lazyImages.length === 0) return;

        if (!('IntersectionObserver' in window)) {
            // Fallback: load all immediately
            lazyImages.forEach(loadFullImage);
            return;
        }

        const observer = new IntersectionObserver(
            (entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        loadFullImage(entry.target);
                        obs.unobserve(entry.target);
                    }
                });
            },
            {
                rootMargin: '200px 0px',  // Start loading 200px before entering viewport
                threshold: 0.01,
            }
        );

        lazyImages.forEach(img => observer.observe(img));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            markNoLazyAsLoaded();
            initLazyLoader();
        });
    } else {
        markNoLazyAsLoaded();
        initLazyLoader();
    }
})();
