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
 * lightbox.js — Vanilla JS photo/video lightbox viewer
 *
 * Features:
 *  - Click .lightbox-trigger to open
 *  - Supports images (data-src) and videos (data-video-src)
 *  - Prev / Next navigation
 *  - Keyboard: ArrowLeft, ArrowRight, Escape
 *  - Close on overlay click
 *  - Comment / Like panel for gallery media (data-media-id present)
 */

'use strict';

(function () {
    let triggers   = [];
    let currentIdx = 0;
    let overlay    = null;
    let inner      = null;
    let imgEl      = null;
    let videoEl    = null;
    let panel      = null;
    let likeBtn    = null;
    let likeCountEl  = null;
    let commentsList = null;
    let commentForm  = null;
    let commentInput = null;

    /** Return the base site URL from the meta tag */
    function baseUrl() {
        return document.querySelector('meta[name="site-url"]')?.content || '';
    }

    /** Build the overlay element */
    function buildOverlay() {
        overlay = document.createElement('div');
        overlay.id = 'lightbox-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Media viewer');

        // Inner wrapper holds the media + optional side panel
        inner = document.createElement('div');
        inner.className = 'lightbox-inner';

        const imageWrap = document.createElement('div');
        imageWrap.className = 'lightbox-image-wrap';

        imgEl = document.createElement('img');
        imgEl.alt = '';
        imgEl.style.cursor = 'pointer';
        imgEl.title = 'Click to open in new tab';
        imgEl.addEventListener('click', (e) => {
            e.stopPropagation();
            if (imgEl.dataset.fullSrc) {
                window.open(imgEl.dataset.fullSrc, '_blank', 'noopener,noreferrer');
            }
        });

        videoEl = document.createElement('video');
        videoEl.className = 'lightbox-video';
        videoEl.controls = true;
        videoEl.preload = 'metadata';
        videoEl.style.display = 'none';
        videoEl.addEventListener('click', (e) => e.stopPropagation());

        imageWrap.appendChild(imgEl);
        imageWrap.appendChild(videoEl);

        // Comment / like panel (hidden until a media-id trigger is opened)
        panel = document.createElement('div');
        panel.className = 'lightbox-panel';
        panel.style.display = 'none';

        // Panel header: like button + comment count label
        const panelHeader = document.createElement('div');
        panelHeader.className = 'lightbox-panel-header';

        likeBtn = document.createElement('button');
        likeBtn.className = 'btn-like-media';
        likeBtn.setAttribute('aria-label', 'Like media');
        likeBtn.setAttribute('type', 'button');

        likeCountEl = document.createElement('span');
        likeCountEl.className = 'media-like-count';
        likeCountEl.textContent = '0';
        likeBtn.appendChild(document.createTextNode('\u2665 '));
        likeBtn.appendChild(likeCountEl);

        likeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMediaLike();
        });

        const commentCountText = document.createElement('span');
        commentCountText.className = 'lightbox-comment-count-text';

        panelHeader.appendChild(likeBtn);
        panelHeader.appendChild(commentCountText);

        // Comments list
        commentsList = document.createElement('div');
        commentsList.className = 'lightbox-comments-list';

        // Comment form
        commentForm = document.createElement('form');
        commentForm.className = 'lightbox-comment-form';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';

        commentInput = document.createElement('input');
        commentInput.type = 'text';
        commentInput.placeholder = 'Write a comment\u2026';
        commentInput.maxLength = 1000;
        commentInput.autocomplete = 'off';
        commentInput.setAttribute('aria-label', 'Comment text');

        const commentSubmit = document.createElement('button');
        commentSubmit.type = 'submit';
        commentSubmit.className = 'btn btn-sm';
        commentSubmit.textContent = 'Post';

        commentForm.appendChild(csrfInput);
        commentForm.appendChild(commentInput);
        if (typeof createSmileyPicker === 'function') {
            commentForm.appendChild(createSmileyPicker(commentInput));
        }
        commentForm.appendChild(commentSubmit);

        commentForm.addEventListener('submit', (e) => {
            e.preventDefault();
            e.stopPropagation();
            submitMediaComment();
        });

        panel.appendChild(panelHeader);
        panel.appendChild(commentsList);
        panel.appendChild(commentForm);

        inner.appendChild(imageWrap);
        inner.appendChild(panel);

        const controls = document.createElement('div');
        controls.className = 'lightbox-controls';

        const prevBtn = document.createElement('button');
        prevBtn.className = 'lightbox-btn';
        prevBtn.setAttribute('aria-label', 'Previous');
        prevBtn.textContent = '\u2039';
        prevBtn.addEventListener('click', (e) => { e.stopPropagation(); navigate(-1); });

        const closeBtn = document.createElement('button');
        closeBtn.className = 'lightbox-btn';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.textContent = '\u2715';
        closeBtn.addEventListener('click', closeLightbox);

        const openBtn = document.createElement('button');
        openBtn.className = 'lightbox-btn';
        openBtn.setAttribute('aria-label', 'Open in new tab');
        openBtn.textContent = '\u2922';
        openBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const trigger = triggers[currentIdx];
            if (!trigger) return;
            const url = trigger.dataset.videoSrc || (imgEl && imgEl.dataset.fullSrc) || trigger.href || '';
            if (url) window.open(url, '_blank', 'noopener,noreferrer');
        });

        const nextBtn = document.createElement('button');
        nextBtn.className = 'lightbox-btn';
        nextBtn.setAttribute('aria-label', 'Next');
        nextBtn.textContent = '\u203a';
        nextBtn.addEventListener('click', (e) => { e.stopPropagation(); navigate(1); });

        controls.appendChild(prevBtn);
        controls.appendChild(openBtn);
        controls.appendChild(closeBtn);
        controls.appendChild(nextBtn);

        overlay.appendChild(inner);
        overlay.appendChild(controls);

        // Close on overlay background click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target === inner) closeLightbox();
        });

        document.body.appendChild(overlay);
    }

    function openLightbox(index) {
        if (!overlay) buildOverlay();
        currentIdx = index;
        showMedia(currentIdx);
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        overlay.focus();
    }

    function closeLightbox() {
        if (!overlay) return;
        stopVideo();
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    function navigate(direction) {
        stopVideo();
        currentIdx = (currentIdx + direction + triggers.length) % triggers.length;
        showMedia(currentIdx);
    }

    /** Pause and reset the lightbox video element */
    function stopVideo() {
        if (videoEl && !videoEl.paused) {
            videoEl.pause();
        }
    }

    function showMedia(index) {
        const trigger = triggers[index];
        if (!trigger) return;

        const mediaId  = trigger.dataset.mediaId || '';
        const videoSrc = trigger.dataset.videoSrc || '';

        if (videoSrc) {
            // — Video mode —
            imgEl.style.display  = 'none';
            videoEl.style.display = '';

            // Compare against stored src to avoid unnecessary reloads
            if (videoEl.dataset.loadedSrc !== videoSrc) {
                videoEl.dataset.loadedSrc = videoSrc;
                videoEl.src = videoSrc;
                videoEl.load();
            }
        } else {
            // — Image mode —
            const src = trigger.dataset.src || trigger.href || '';
            videoEl.style.display = 'none';
            imgEl.style.display   = '';

            imgEl.style.opacity = '0';
            imgEl.src = src;
            // Use href (original scrubbed file) as the click-to-open full-size link,
            // falling back to the display src if href is not a real URL.
            imgEl.dataset.fullSrc = trigger.href || src;
            imgEl.onload = () => { imgEl.style.opacity = '1'; };
        }

        if (mediaId) {
            overlay.classList.add('has-panel');
            panel.style.display = 'flex';
            panel.dataset.mediaId = mediaId;
            const csrfEl = commentForm ? commentForm.querySelector('input[name="csrf_token"]') : null;
            if (csrfEl) {
                const pageCsrf = document.querySelector('input[name="csrf_token"]');
                csrfEl.value = pageCsrf ? pageCsrf.value : '';
            }
            loadMediaPanel(mediaId);
        } else {
            overlay.classList.remove('has-panel');
            panel.style.display = 'none';
            panel.dataset.mediaId = '';
        }
    }

    /** Fetch comments + like state for the given media item */
    function loadMediaPanel(mediaId) {
        commentsList.innerHTML = '<p class="lightbox-empty-comments">Loading\u2026</p>';
        likeCountEl.textContent = '0';
        likeBtn.classList.remove('liked');

        fetch(baseUrl() + '/modules/gallery/get_media_comments.php?media_id=' + encodeURIComponent(mediaId), {
            credentials: 'same-origin',
        })
        .then((r) => r.json())
        .then((data) => {
            if (!data.ok) return;
            likeCountEl.textContent = data.like_count;
            likeBtn.classList.toggle('liked', data.user_liked);
            updateCommentCountText(data.comments.length);
            renderComments(data.comments);
        })
        .catch(() => {
            commentsList.innerHTML = '<p class="lightbox-empty-comments">Could not load comments.</p>';
        });
    }

    function renderComments(comments) {
        if (!comments.length) {
            commentsList.innerHTML = '<p class="lightbox-empty-comments">No comments yet. Be the first!</p>';
            return;
        }
        commentsList.innerHTML = '';
        comments.forEach((c) => appendComment(c));
    }

    function appendComment(c) {
        const item = document.createElement('div');
        item.className = 'lightbox-comment-item';
        const smilified = (typeof smilifyText === 'function') ? smilifyText(c.content) : c.content;
        item.innerHTML =
            '<a href="' + escapeHtml(c.profile_url) + '" class="lightbox-comment-avatar">' +
                '<img src="' + escapeHtml(c.avatar) + '" alt="" class="avatar avatar-small" width="28" height="28" loading="lazy">' +
            '</a>' +
            '<div class="lightbox-comment-body">' +
                '<a href="' + escapeHtml(c.profile_url) + '" class="lightbox-comment-author">' + escapeHtml(c.username) + '</a>' +
                '<span class="lightbox-comment-time">' + escapeHtml(c.time_ago) + '</span>' +
                '<p class="lightbox-comment-text">' + escapeHtml(smilified) + '</p>' +
            '</div>';
        commentsList.appendChild(item);
        commentsList.scrollTop = commentsList.scrollHeight;
    }

    function updateCommentCountText(count) {
        const el = panel ? panel.querySelector('.lightbox-comment-count-text') : null;
        if (el) el.textContent = count + ' comment' + (count !== 1 ? 's' : '');
    }

    /** Toggle like on the currently displayed media item */
    function toggleMediaLike() {
        const mediaId = panel ? panel.dataset.mediaId : '';
        if (!mediaId) return;

        likeBtn.disabled = true;
        const csrfEl    = commentForm ? commentForm.querySelector('input[name="csrf_token"]') : null;
        const csrfToken = csrfEl ? csrfEl.value : (document.querySelector('input[name="csrf_token"]')?.value || '');

        fetch(baseUrl() + '/modules/gallery/like_media.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ csrf_token: csrfToken, media_id: mediaId }),
        })
        .then((r) => r.json())
        .then((data) => {
            if (!data.ok) return;
            likeCountEl.textContent = data.count;
            likeBtn.classList.toggle('liked', data.liked);
            updateMasonryLikeCount(mediaId, data.count);
        })
        .catch(() => {})
        .finally(() => { likeBtn.disabled = false; });
    }

    /** Submit a new comment on the currently displayed media item */
    function submitMediaComment() {
        const mediaId = panel ? panel.dataset.mediaId : '';
        if (!mediaId) return;

        const content = commentInput ? commentInput.value.trim() : '';
        if (!content) return;

        const csrfEl    = commentForm ? commentForm.querySelector('input[name="csrf_token"]') : null;
        const csrfToken = csrfEl ? csrfEl.value : (document.querySelector('input[name="csrf_token"]')?.value || '');
        const submitBtn = commentForm ? commentForm.querySelector('button[type="submit"]') : null;
        if (submitBtn) submitBtn.disabled = true;

        fetch(baseUrl() + '/modules/gallery/add_media_comment.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ csrf_token: csrfToken, media_id: mediaId, content }),
        })
        .then((r) => r.json())
        .then((data) => {
            if (!data.ok) {
                alert('Error: ' + (data.error || 'Could not post comment'));
                return;
            }
            if (commentInput) commentInput.value = '';
            // Remove "no comments" placeholder if present
            const placeholder = commentsList ? commentsList.querySelector('.lightbox-empty-comments') : null;
            if (placeholder) placeholder.remove();

            appendComment(data);

            const currentCount = commentsList ? commentsList.querySelectorAll('.lightbox-comment-item').length : 0;
            updateCommentCountText(currentCount);
            updateMasonryCommentCount(mediaId, currentCount);
        })
        .catch(() => { alert('Failed to post comment. Please try again.'); })
        .finally(() => { if (submitBtn) submitBtn.disabled = false; });
    }

    /** Update the like count badge on the masonry thumbnail */
    function updateMasonryLikeCount(mediaId, count) {
        const trigger = document.querySelector('.lightbox-trigger[data-media-id="' + mediaId + '"]');
        if (!trigger) return;
        const mediaItem = trigger.closest('.media-item');
        if (!mediaItem) return;
        ensureStatsBadge(mediaItem, mediaId);
        const likeStat = mediaItem.querySelector('.media-stat-likes');
        if (likeStat) {
            if (count > 0) {
                likeStat.textContent = '\u2665 ' + count;
                likeStat.style.display = '';
            } else {
                likeStat.style.display = 'none';
            }
        }
    }

    /** Update the comment count badge on the masonry thumbnail */
    function updateMasonryCommentCount(mediaId, count) {
        const trigger = document.querySelector('.lightbox-trigger[data-media-id="' + mediaId + '"]');
        if (!trigger) return;
        const mediaItem = trigger.closest('.media-item');
        if (!mediaItem) return;
        ensureStatsBadge(mediaItem, mediaId);
        const commentStat = mediaItem.querySelector('.media-stat-comments');
        if (commentStat) {
            if (count > 0) {
                commentStat.textContent = '\u{1F4AC} ' + count;
                commentStat.style.display = '';
            } else {
                commentStat.style.display = 'none';
            }
        }
    }

    /**
     * Ensure a .media-stats div with like/comment spans exists on the media item.
     * Creates it (hidden) if missing so we can update counts live.
     */
    function ensureStatsBadge(mediaItem, mediaId) {
        let statsEl = mediaItem.querySelector('.media-stats');
        if (!statsEl) {
            statsEl = document.createElement('div');
            statsEl.className = 'media-stats';
            const likeStat = document.createElement('span');
            likeStat.className = 'media-stat media-stat-likes';
            likeStat.style.display = 'none';
            const commentStat = document.createElement('span');
            commentStat.className = 'media-stat media-stat-comments';
            commentStat.style.display = 'none';
            statsEl.appendChild(likeStat);
            statsEl.appendChild(commentStat);
            mediaItem.appendChild(statsEl);
        } else {
            // Ensure individual spans exist
            if (!mediaItem.querySelector('.media-stat-likes')) {
                const s = document.createElement('span');
                s.className = 'media-stat media-stat-likes';
                s.style.display = 'none';
                statsEl.prepend(s);
            }
            if (!mediaItem.querySelector('.media-stat-comments')) {
                const s = document.createElement('span');
                s.className = 'media-stat media-stat-comments';
                s.style.display = 'none';
                statsEl.appendChild(s);
            }
        }
    }

    /** Escape HTML to prevent XSS when inserting user content */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    /** Collect all triggers on the page */
    function collectTriggers() {
        triggers = Array.from(document.querySelectorAll('.lightbox-trigger'));
    }

    /** Bind click events to all triggers */
    function bindTriggers() {
        collectTriggers();
        triggers.forEach((el, idx) => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                openLightbox(idx);
            });
        });
    }

    /**
     * Bind click events to any new .lightbox-trigger elements found inside
     * the given container.  New triggers are appended to the existing triggers
     * array so that prev/next navigation still works across all posts.
     * Call this after dynamically inserting new post HTML (e.g. "Load More").
     */
    function bindNewTriggers(container) {
        if (!container) return;
        const newTriggers = Array.from(container.querySelectorAll('.lightbox-trigger'));
        newTriggers.forEach(el => {
            const idx = triggers.length;
            triggers.push(el);
            el.addEventListener('click', (e) => {
                e.preventDefault();
                openLightbox(idx);
            });
        });
    }

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (!overlay || overlay.style.display === 'none') return;
        switch (e.key) {
            case 'ArrowLeft':  navigate(-1); break;
            case 'ArrowRight': navigate(1);  break;
            case 'Escape':     closeLightbox(); break;
        }
    });

    // Init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindTriggers);
    } else {
        bindTriggers();
    }

    // Public API: bind lightbox triggers in dynamically loaded content
    // (e.g. "Load More" posts).
    window.lightboxBindNew = bindNewTriggers;
})();
