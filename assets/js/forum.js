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
 * forum.js — Forum gallery image picker
 *
 * Provides a modal picker that lets users insert an image from their own
 * gallery into a forum post or reply.
 *
 * Looks for:
 *   .forum-pick-image-btn  — button(s) that open the picker
 *   #forum-media-id        — hidden input that stores the selected media ID
 *   #forum-image-preview   — container where the selected thumbnail is shown
 *
 * The gallery picker overlay is created once and reused.
 */

'use strict';

(function () {

    var overlay     = null;
    var grid        = null;
    var loadMoreBtn = null;
    var currentOffset = 0;
    var hasMore       = false;

    /** Return the base site URL from the meta tag */
    function baseUrl() {
        return document.querySelector('meta[name="site-url"]')?.content || '';
    }

    /** Escape HTML entities to avoid XSS when building markup */
    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    /** Build the picker overlay once */
    function buildOverlay() {
        overlay = document.createElement('div');
        overlay.id        = 'gallery-picker-overlay';
        overlay.className = 'gallery-picker-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Pick image from gallery');
        overlay.style.display = 'none';

        var inner = document.createElement('div');
        inner.className = 'gallery-picker-inner';

        /* Header */
        var header = document.createElement('div');
        header.className = 'gallery-picker-header';

        var title = document.createElement('h3');
        title.textContent = 'Pick Image from Gallery';

        var closeBtn = document.createElement('button');
        closeBtn.type      = 'button';
        closeBtn.className = 'gallery-picker-close';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.textContent = '\u00d7';
        closeBtn.addEventListener('click', closeOverlay);

        header.appendChild(title);
        header.appendChild(closeBtn);

        /* Image grid */
        grid = document.createElement('div');
        grid.id        = 'gallery-picker-grid';
        grid.className = 'gallery-picker-grid';

        /* Load-more */
        var loadMoreWrap = document.createElement('div');
        loadMoreWrap.className    = 'gallery-picker-load-more';
        loadMoreWrap.id           = 'gallery-picker-load-more';
        loadMoreWrap.style.display = 'none';

        loadMoreBtn = document.createElement('button');
        loadMoreBtn.type      = 'button';
        loadMoreBtn.className = 'btn btn-sm btn-secondary';
        loadMoreBtn.textContent = 'Load More';
        loadMoreBtn.addEventListener('click', function () {
            fetchImages(currentOffset);
        });

        loadMoreWrap.appendChild(loadMoreBtn);

        inner.appendChild(header);
        inner.appendChild(grid);
        inner.appendChild(loadMoreWrap);
        overlay.appendChild(inner);

        /* Close on backdrop click */
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeOverlay();
        });

        document.body.appendChild(overlay);
    }

    /** Open the picker overlay, loading images if the grid is empty */
    function openOverlay() {
        if (!overlay) buildOverlay();
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        if (!grid.hasChildNodes()) {
            currentOffset = 0;
            fetchImages(0);
        }
    }

    function closeOverlay() {
        if (!overlay) return;
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    /** Fetch a page of images from the server and append them to the grid */
    function fetchImages(offset) {
        if (loadMoreBtn) loadMoreBtn.disabled = true;
        if (offset === 0) {
            grid.innerHTML = '<p class="gallery-picker-loading">Loading\u2026</p>';
        }

        fetch(baseUrl() + '/modules/forum/get_user_images.php?offset=' + encodeURIComponent(offset), {
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) {
                grid.innerHTML = '<p class="gallery-picker-empty">Could not load images.</p>';
                return;
            }
            if (offset === 0) {
                grid.innerHTML = '';
                if (data.items.length === 0) {
                    grid.innerHTML = '<p class="gallery-picker-empty">Your gallery is empty. Upload images first.</p>';
                    return;
                }
            }

            data.items.forEach(function (item) {
                appendItem(item);
            });

            hasMore       = data.has_more;
            currentOffset = offset + data.items.length;

            var loadMoreWrap = document.getElementById('gallery-picker-load-more');
            if (loadMoreWrap) {
                loadMoreWrap.style.display = hasMore ? '' : 'none';
            }
        })
        .catch(function () {
            if (offset === 0) {
                grid.innerHTML = '<p class="gallery-picker-empty">Failed to load images.</p>';
            }
        })
        .finally(function () {
            if (loadMoreBtn) loadMoreBtn.disabled = false;
        });
    }

    /** Append a single image item to the grid */
    function appendItem(item) {
        var el = document.createElement('button');
        el.type        = 'button';
        el.className   = 'gallery-picker-item';
        el.dataset.id  = item.id;
        el.dataset.src = item.src;
        el.dataset.full = item.full;

        var img = document.createElement('img');
        img.src     = item.thumb;
        img.alt     = '';
        img.loading = 'lazy';
        img.width   = 80;
        img.height  = 80;

        el.appendChild(img);
        el.addEventListener('click', function () {
            selectImage(item);
            closeOverlay();
        });
        grid.appendChild(el);
    }

    /**
     * Store the selected image: update the hidden input, show preview,
     * and refresh lightbox triggers so the thumbnail opens in the lightbox.
     */
    function selectImage(item) {
        /* Find the active form's hidden input and preview container */
        var mediaInput   = document.querySelector('.forum-active-form #forum-media-id');
        var previewWrap  = document.querySelector('.forum-active-form #forum-image-preview');

        if (!mediaInput || !previewWrap) {
            /* Fall back to any instance on the page */
            mediaInput  = document.getElementById('forum-media-id');
            previewWrap = document.getElementById('forum-image-preview');
        }

        if (mediaInput) mediaInput.value = item.id;

        if (previewWrap) {
            previewWrap.innerHTML =
                '<div class="forum-image-preview-inner">' +
                    '<a href="' + escHtml(item.full) + '" class="lightbox-trigger" data-src="' + escHtml(item.src) + '">' +
                        '<img src="' + escHtml(item.thumb) + '" alt="Selected image" class="forum-post-thumb">' +
                    '</a>' +
                    '<button type="button" class="forum-image-remove-btn" aria-label="Remove image">&times;</button>' +
                '</div>';

            /* Wire up remove button */
            var removeBtn = previewWrap.querySelector('.forum-image-remove-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    if (mediaInput) mediaInput.value = '';
                    previewWrap.innerHTML = '';
                });
            }

            /* Register the new lightbox trigger with the existing lightbox */
            if (typeof window.lightboxBindNew === 'function') {
                window.lightboxBindNew(previewWrap);
            }
        }
    }

    /** Bind click handlers to all .forum-pick-image-btn buttons on the page */
    function bindPickButtons() {
        document.querySelectorAll('.forum-pick-image-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                /* Mark which form is currently active so selectImage knows where to write */
                document.querySelectorAll('.forum-active-form').forEach(function (f) {
                    f.classList.remove('forum-active-form');
                });
                var form = btn.closest('form');
                if (form) form.classList.add('forum-active-form');
                openOverlay();
            });
        });
    }

    /* Keyboard: close on Escape */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay && overlay.style.display !== 'none') {
            closeOverlay();
        }
    });

    /* Init when DOM is ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindPickButtons);
    } else {
        bindPickButtons();
    }

})();
