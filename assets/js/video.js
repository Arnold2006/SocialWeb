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
 * video.js — JS for the video hub (video.php) and video playback (video_play.php).
 */

'use strict';

(function () {

    /** Return the base site URL from the meta tag */
    function baseUrl() {
        return document.querySelector('meta[name="site-url"]')?.content || '';
    }

    /** Escape HTML for safe DOM insertion */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    // ── Video upload panel toggle (video.php) ─────────────────────────────────
    var uploadBtn   = document.getElementById('video-upload-toggle');
    var uploadPanel = document.getElementById('video-upload-panel');
    if (uploadBtn && uploadPanel) {
        uploadBtn.addEventListener('click', function () {
            var hidden = uploadPanel.classList.toggle('hidden');
            uploadBtn.textContent = hidden ? '\u25b2 Upload Video' : '\u25bc Upload Video';
        });

        var form    = document.getElementById('video-upload-form');
        var spinner = document.getElementById('video-upload-progress');
        var upBtn   = document.getElementById('video-upload-btn');
        if (form) {
            form.addEventListener('submit', function () {
                if (spinner) spinner.style.display = 'inline';
                if (upBtn)   upBtn.disabled = true;
            });
        }
    }

    // ── Edit description toggle (video_play.php) ──────────────────────────────
    var descBtn  = document.getElementById('video-edit-desc-toggle');
    var descForm = document.getElementById('video-edit-desc-form');
    if (descBtn && descForm) {
        descBtn.addEventListener('click', function () {
            var hidden = descForm.classList.toggle('hidden');
            descBtn.textContent = hidden ? 'Edit Description' : 'Cancel';
        });
    }

    // ── Like button (video_play.php) ──────────────────────────────────────────
    var likeBtn      = document.getElementById('video-like-btn');
    var likeCountEl  = document.getElementById('video-like-count');
    if (likeBtn && likeCountEl) {
        likeBtn.addEventListener('click', function () {
            var mediaId = likeBtn.dataset.mediaId;
            if (!mediaId) return;

            likeBtn.disabled = true;
            var csrfToken = (document.querySelector('input[name="csrf_token"]') || {}).value || '';

            fetch(baseUrl() + '/modules/gallery/like_media.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: csrfToken, media_id: mediaId }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                likeCountEl.textContent = data.count;
                likeBtn.classList.toggle('liked', data.liked);
                likeBtn.setAttribute('aria-pressed', data.liked ? 'true' : 'false');
            })
            .catch(function () {})
            .finally(function () { likeBtn.disabled = false; });
        });
    }

    // ── Comment form (video_play.php) ─────────────────────────────────────────
    var commentForm     = document.getElementById('video-comment-form');
    var commentsList    = document.getElementById('video-comments-list');
    var commentCountEl  = document.getElementById('video-comment-count');

    if (commentForm && commentsList) {
        commentForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var input   = commentForm.querySelector('input[name="content"]');
            var content = input ? input.value.trim() : '';
            if (!content) return;

            var submitBtn  = commentForm.querySelector('button[type="submit"]');
            var csrfToken  = (commentForm.querySelector('input[name="csrf_token"]') || {}).value || '';
            var mediaId    = (commentForm.querySelector('input[name="media_id"]') || {}).value || '';
            if (submitBtn) submitBtn.disabled = true;

            fetch(baseUrl() + '/modules/gallery/add_media_comment.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: csrfToken, media_id: mediaId, content: content }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    alert('Error: ' + (data.error || 'Could not post comment'));
                    return;
                }
                if (input) {
                    input.value = '';
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }

                // Remove empty-state placeholder if present
                var placeholder = commentsList.querySelector('.video-comments-empty');
                if (placeholder) placeholder.remove();

                // Append the new comment
                var item = document.createElement('div');
                item.className = 'video-comment-item';
                item.innerHTML =
                    '<a href="' + escapeHtml(data.profile_url) + '" class="video-comment-avatar-link">' +
                        '<img src="' + escapeHtml(data.avatar) + '" alt="" width="32" height="32" class="avatar avatar-small">' +
                    '</a>' +
                    '<div class="video-comment-body">' +
                        '<a href="' + escapeHtml(data.profile_url) + '" class="video-comment-author">' + escapeHtml(data.username) + '</a>' +
                        '<span class="muted video-comment-time">just now</span>' +
                        '<p class="video-comment-text">' + (data.content_html || escapeHtml(data.content)) + '</p>' +
                    '</div>';
                commentsList.appendChild(item);

                // Update comment count label
                if (commentCountEl) {
                    var newCount = commentsList.querySelectorAll('.video-comment-item').length;
                    commentCountEl.textContent = newCount;
                    var suffix = document.getElementById('video-comment-suffix');
                    if (suffix) {
                        suffix.textContent = ' comment' + (newCount !== 1 ? 's' : '');
                    }
                }
            })
            .catch(function () { alert('Failed to post comment. Please try again.'); })
            .finally(function () { if (submitBtn) submitBtn.disabled = false; });
        });
    }

}());
