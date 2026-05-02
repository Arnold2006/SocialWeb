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
 * app.js — Core application JS
 *
 * Handles:
 *  - AJAX post creation
 *  - Like button toggles
 *  - Comment submission
 *  - Image preview before upload
 *  - Avatar crop tool
 */

'use strict';

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Get CSRF token from the first hidden input on the page */
function getCsrfToken() {
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

/** Escape HTML to prevent XSS when inserting user content */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}

/**
 * Convert http/https URLs in raw text into safe clickable links, while also
 * HTML-escaping all content. Takes raw (unescaped) user text; do NOT call
 * escapeHtml() on the input first. Only http/https schemes are linkified;
 * links get rel="noopener noreferrer nofollow" and target="_blank".
 */
function linkifyHtml(rawStr) {
    function escStr(s) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(s)));
        return d.innerHTML;
    }
    return String(rawStr).split(/(\bhttps?:\/\/\S+)/g).map(function (part, i) {
        if (i % 2 === 0) return escStr(part);
        const url        = part.replace(/[.,;:!?)'"]+$/, '');
        const escapedUrl = escStr(url);
        return '<a href="' + escapedUrl + '" rel="noopener noreferrer nofollow" target="_blank">'
            + escapedUrl + '</a>'
            + escStr(part.slice(url.length));
    }).join('');
}

/**
 * Replace common text emoticons with Unicode emoji.
 * Only matches smileys surrounded by whitespace or at start/end of the string,
 * so they are never accidentally matched inside a word or URL.
 * Call this on raw (unescaped) text before passing to linkifyHtml().
 *
 * @param {string} str - Raw user text (not yet HTML-escaped)
 * @returns {string}
 */
const smilifyText = (function () {
    const map = {
        'O:-)': '😇', 'O:)':  '😇',
        '>:-)': '😈', '>:)':  '😈',
        '>:-(': '😠', '>:(': '😠',
        'B-)':  '😎',
        ':-)':  '😊', ':-D': '😀', ':-(': '😞',
        ';-)':  '😉', ':-P': '😛', ':-p': '😛',
        ':-O':  '😮', ':-o': '😮', ':-*': '😘',
        ':-/':  '😕', ':-|': '😐',
        ":'-(": '😢', ":'(": '😢',
        ':)':   '😊', ':D':  '😀', ':(': '😞',
        ';)':   '😉', ':P':  '😛', ':p': '😛',
        ':O':   '😮', ':o':  '😮', ':*': '😘',
        ':/':   '😕', ':|':  '😐',
        'B)':   '😎', '<3':  '❤️',
    };
    const parts = Object.keys(map).map(s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const re    = new RegExp('(?<!\\S)(' + parts.join('|') + ')(?!\\S)', 'gu');
    return function (str) {
        re.lastIndex = 0;
        return String(str ?? '').replace(re, (_, s) => map[s] ?? s);
    };
}());

// ── Smiley Picker ─────────────────────────────────────────────────────────────

/** Insert text at the cursor position (or end) in a textarea / text input / contenteditable */
function insertAtCursor(el, text) {
    if (el.isContentEditable) {
        el.focus();
        // execCommand is deprecated but remains the most concise cross-browser
        // approach for text insertion in contenteditable elements without
        // external dependencies — consistent with the blog editor's own usage.
        document.execCommand('insertText', false, text);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        return;
    }
    const start = el.selectionStart != null ? el.selectionStart : el.value.length;
    const end   = el.selectionEnd   != null ? el.selectionEnd   : el.value.length;
    el.value    = el.value.slice(0, start) + text + el.value.slice(end);
    const pos   = start + text.length;
    el.setSelectionRange(pos, pos);
    el.dispatchEvent(new Event('input', { bubbles: true }));
}

/* Single document-level listener that closes all open smiley dropdowns */
document.addEventListener('click', (e) => {
    if (!e.target.closest('.smiley-picker-wrap')) {
        document.querySelectorAll('.smiley-dropdown:not(.hidden)').forEach((d) => {
            d.classList.add('hidden');
            const pickerBtn = d.closest('.smiley-picker-wrap')
                              && d.closest('.smiley-picker-wrap').querySelector('.smiley-picker-btn');
            if (pickerBtn) pickerBtn.setAttribute('aria-expanded', 'false');
        });
    }
});

/**
 * Build a smiley-picker button + dropdown and attach it to a text input/textarea.
 * Clicking a smiley inserts its text code at the cursor position.
 *
 * @param {HTMLInputElement|HTMLTextAreaElement} inputEl
 * @returns {HTMLSpanElement} Wrapper element containing the button and dropdown
 */
function createSmileyPicker(inputEl) {
    const smileys = [
        { code: ':-)',  emoji: '😊' },
        { code: ':-D',  emoji: '😀' },
        { code: ':-(',  emoji: '😞' },
        { code: ';-)',  emoji: '😉' },
        { code: ':-P',  emoji: '😛' },
        { code: ':-O',  emoji: '😮' },
        { code: ':-*',  emoji: '😘' },
        { code: ':-/',  emoji: '😕' },
        { code: ':-|',  emoji: '😐' },
        { code: ":'(",  emoji: '😢' },
        { code: 'B-)',  emoji: '😎' },
        { code: '>:-)', emoji: '😈' },
        { code: '>:-(', emoji: '😠' },
        { code: 'O:-)', emoji: '😇' },
        { code: '<3',   emoji: '❤️' },
    ];

    const wrap     = document.createElement('span');
    wrap.className = 'smiley-picker-wrap';

    const btn      = document.createElement('button');
    btn.type       = 'button';
    btn.className  = 'smiley-picker-btn';
    btn.title      = 'Insert smiley';
    btn.setAttribute('aria-label', 'Smiley picker');
    btn.setAttribute('aria-haspopup', 'listbox');
    btn.textContent = '😊';

    const dropdown = document.createElement('div');
    dropdown.className = 'smiley-dropdown hidden';
    dropdown.setAttribute('role', 'listbox');
    dropdown.setAttribute('aria-label', 'Smileys');

    smileys.forEach(({ code, emoji }) => {
        const opt     = document.createElement('button');
        opt.type      = 'button';
        opt.className = 'smiley-option';
        opt.setAttribute('role', 'option');
        opt.title     = code;

        const emojiSpan = document.createElement('span');
        emojiSpan.className = 'smiley-option-emoji';
        emojiSpan.setAttribute('aria-hidden', 'true');
        emojiSpan.textContent = emoji;

        const codeSpan = document.createElement('span');
        codeSpan.className = 'smiley-option-code';
        codeSpan.textContent = code;

        opt.appendChild(emojiSpan);
        opt.appendChild(codeSpan);

        opt.addEventListener('click', () => {
            insertAtCursor(inputEl, ' ' + (inputEl.isContentEditable ? emoji : code) + ' ');
            dropdown.classList.add('hidden');
            btn.setAttribute('aria-expanded', 'false');
            inputEl.focus();
        });
        dropdown.appendChild(opt);
    });

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const hidden = dropdown.classList.toggle('hidden');
        btn.setAttribute('aria-expanded', String(!hidden));
        if (!hidden) {
            const rect = btn.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.top    = 'auto';
            dropdown.style.left   = 'auto';
            dropdown.style.bottom = (window.innerHeight - rect.top + 6) + 'px';
            dropdown.style.right  = (window.innerWidth  - rect.right)  + 'px';
        } else {
            dropdown.style.cssText = '';
        }
    });

    wrap.appendChild(btn);
    wrap.appendChild(dropdown);
    return wrap;
}

/** POST JSON (or FormData) to a URL, return parsed JSON */
async function apiPost(url, data) {
    const body = data instanceof FormData ? data : new URLSearchParams(data);
    const resp = await fetch(url, {
        method: 'POST',
        headers: data instanceof FormData ? {} : { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
        credentials: 'same-origin',
    });
    return resp.json();
}

/** Get the current logged-in user's ID from the meta tag (0 if not found) */
function getCurrentUserId() {
    const meta = document.querySelector('meta[name="current-user-id"]');
    return meta ? parseInt(meta.content, 10) || 0 : 0;
}

/**
 * Build the inner HTML for a comment item body.
 * Includes an Edit button if userId matches the current user.
 *
 * @param {number|string} commentId
 * @param {string}        profileUrl
 * @param {string}        username
 * @param {string}        timeAgo
 * @param {string}        rawContent
 * @param {number}        userId
 * @param {boolean}       edited
 * @param {string}        contentHtml
 * @param {object|null}   imageData  - Optional: { thumb_url, large_url }
 * @param {number}        likeCount  - Number of likes on the comment
 * @param {boolean}       userLiked  - Whether the current user has liked the comment
 */
function buildCommentBodyHtml(commentId, profileUrl, username, timeAgo, rawContent, userId, edited, contentHtml, imageData, likeCount, userLiked) {
    const currentUserId = getCurrentUserId();
    const editedBadge   = edited ? '<span class="comment-edited">(edited)</span>' : '';
    const editBtn       = (userId && userId === currentUserId)
        ? `<button type="button" class="comment-edit-btn btn btn-xs btn-secondary" data-comment-id="${parseInt(commentId, 10)}">Edit</button>`
        : '';
    const displayHtml   = contentHtml || linkifyHtml(smilifyText(rawContent));
    let imageHtml = '';
    if (imageData && imageData.thumb_url) {
        const thumbUrl = escapeHtml(imageData.thumb_url);
        const largeUrl = escapeHtml(imageData.large_url || imageData.thumb_url);
        imageHtml = `<a href="${largeUrl}" class="lightbox-trigger comment-image-trigger" data-src="${largeUrl}">` +
            `<img src="${thumbUrl}" alt="comment image" class="comment-attached-image" loading="lazy">` +
            `</a>`;
    }
    const likeBtn = `<div class="comment-footer"><button class="btn-like-comment${userLiked ? ' liked' : ''}" data-comment-id="${parseInt(commentId, 10)}">♥ <span class="like-count">${parseInt(likeCount, 10) || 0}</span></button></div>`;
    return `<a href="${escapeHtml(profileUrl)}" class="comment-author">${escapeHtml(username)}</a>` +
        `<span class="comment-time">${escapeHtml(timeAgo)}</span>` +
        editedBadge + editBtn +
        `<p class="comment-text" data-raw="${escapeHtml(rawContent)}">${displayHtml}</p>` +
        imageHtml + likeBtn;
}

// ── AJAX post creation ────────────────────────────────────────────────────────

const postForm = document.getElementById('post-form');

if (postForm) {
    postForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(postForm);
        // Ensure CSRF token is present
        if (!formData.get('csrf_token')) {
            formData.append('csrf_token', getCsrfToken());
        }

        const submitBtn = postForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Posting…';
        }

        try {
            const result = await fetch(postForm.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await result.json();

            if (json.ok) {
                // Reload the post feed by refreshing the page
                window.location.reload();
            } else {
                alert('Error: ' + (json.error || 'Unknown error'));
            }
        } catch (err) {
            console.error('Post creation failed:', err);
            alert('Failed to submit post. Please try again.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post';
            }
        }
    });
}

// ── Like button ───────────────────────────────────────────────────────────────

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-like');
    if (!btn) return;
    if (btn.classList.contains('btn-like-blog')) return; // handled separately

    e.preventDefault();
    btn.disabled = true;

    const postId = btn.dataset.postId;
    const data   = new URLSearchParams({ csrf_token: getCsrfToken(), post_id: postId });

    try {
        // Determine base URL dynamically
        const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';
        const result  = await apiPost(baseUrl + '/modules/wall/like_post.php', data);

        if (result.ok) {
            const countEl = btn.querySelector('.like-count');
            if (countEl) countEl.textContent = result.count;
            btn.classList.toggle('liked', result.liked);
        }
    } catch (err) {
        console.error('Like failed:', err);
    } finally {
        btn.disabled = false;
    }
});

// ── Hover tooltips: who liked / who commented ────────────────────────────────

(function () {
    const baseUrl = () => document.querySelector('meta[name="site-url"]')?.content || '';

    /** Build and return a tooltip element with the given text. */
    function makeTooltip(text) {
        const tip = document.createElement('div');
        tip.className = 'reaction-tooltip';
        tip.textContent = text;
        return tip;
    }

    /** Format a list of usernames into a readable string. */
    function formatNames(users, total) {
        if (!users || users.length === 0) return null;
        const shown = users.join(', ');
        if (total > users.length) {
            return shown + ' and ' + (total - users.length) + ' more';
        }
        return shown;
    }

    /** Per-button timers to avoid cross-button race conditions. */
    const hoverTimers = new WeakMap();

    document.addEventListener('mouseenter', async (e) => {
        const btn = e.target.closest('.btn-like:not(.btn-like-blog), .btn-comment[data-post-id]');
        if (!btn) return;

        const postId = btn.dataset.postId;
        if (!postId) return;

        // Remove any existing tooltip on this button first
        const existing = btn.querySelector('.reaction-tooltip');
        if (existing) existing.remove();

        clearTimeout(hoverTimers.get(btn));
        hoverTimers.set(btn, setTimeout(async () => {
            // Guard: button still hovered
            if (!btn.matches(':hover')) return;

            const isLike = btn.classList.contains('btn-like');
            const endpoint = isLike
                ? '/modules/wall/get_likers.php'
                : '/modules/wall/get_commenters.php';

            try {
                const resp   = await fetch(
                    baseUrl() + endpoint + '?post_id=' + encodeURIComponent(postId),
                    { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                const result = await resp.json();

                if (!result.ok || !result.users || result.users.length === 0) return;

                // Only show if button is still hovered
                if (!btn.matches(':hover')) return;

                const text = formatNames(result.users, result.total);
                if (!text) return;

                // Remove any stale tooltip that may have been added concurrently
                const stale = btn.querySelector('.reaction-tooltip');
                if (stale) stale.remove();

                btn.appendChild(makeTooltip(text));
            } catch (_) {
                // Silently ignore tooltip fetch errors
            }
        }, 300));
    }, true);

    document.addEventListener('mouseleave', (e) => {
        const btn = e.target.closest('.btn-like:not(.btn-like-blog), .btn-comment[data-post-id]');
        if (!btn) return;
        clearTimeout(hoverTimers.get(btn));
        hoverTimers.delete(btn);
        const tip = btn.querySelector('.reaction-tooltip');
        if (tip) tip.remove();
    }, true);
}());

// ── Blog post like button ─────────────────────────────────────────────────────

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-like-blog');
    if (!btn) return;

    e.preventDefault();
    btn.disabled = true;

    const blogPostId = btn.dataset.blogPostId;
    const data       = new URLSearchParams({ csrf_token: getCsrfToken(), blog_post_id: blogPostId });

    try {
        const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';
        const result  = await apiPost(baseUrl + '/modules/blog/like_post.php', data);

        if (result.ok) {
            const countEl = btn.querySelector('.like-count');
            if (countEl) countEl.textContent = result.count;
            btn.classList.toggle('liked', result.liked);
        }
    } catch (err) {
        console.error('Blog like failed:', err);
    } finally {
        btn.disabled = false;
    }
});

// ── Comment like button ───────────────────────────────────────────────────────

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-like-comment');
    if (!btn) return;

    e.preventDefault();
    btn.disabled = true;

    const commentId = btn.dataset.commentId;
    const data      = new URLSearchParams({ csrf_token: getCsrfToken(), comment_id: commentId });

    try {
        const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';
        const result  = await apiPost(baseUrl + '/modules/wall/like_comment.php', data);

        if (result.ok) {
            const countEl = btn.querySelector('.like-count');
            if (countEl) countEl.textContent = result.count;
            btn.classList.toggle('liked', result.liked);
        }
    } catch (err) {
        console.error('Comment like failed:', err);
    } finally {
        btn.disabled = false;
    }
});

// ── Comment form (AJAX) ───────────────────────────────────────────────────────

document.addEventListener('submit', async (e) => {
    const form = e.target.closest('.comment-form');
    if (!form) return;

    e.preventDefault();

    const postId  = form.dataset.postId;
    const input   = form.querySelector('input[name="content"]');
    const content = input ? input.value.trim() : '';
    if (!content) return;

    const imageMediaId = commentImageState.get(form) || 0;

    const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';
    const data    = new URLSearchParams({
        csrf_token:     getCsrfToken(),
        post_id:        postId,
        content,
        image_media_id: imageMediaId,
    });

    try {
        const result = await apiPost(baseUrl + '/modules/wall/add_comment.php', data);

        if (result.ok) {
            // Clear the input immediately so it is always emptied when the server
            // confirms the comment was saved, regardless of whether the DOM update
            // below succeeds (e.g. when content_html contains a @mention link).
            if (input) {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            clearCommentImagePreview(form);
            // Insert new comment before the comment form so it appears above the input
            const section = document.getElementById('comments-' + postId);
            if (section) {
                const currentUserId = getCurrentUserId();
                const imageData = result.image_thumb_url
                    ? { thumb_url: result.image_thumb_url, large_url: result.image_large_url }
                    : null;
                const commentHtml = `
                <div class="comment-item" id="comment-${parseInt(result.comment_id, 10)}">
                    <a href="${escapeHtml(result.profile_url)}">
                        <img src="${escapeHtml(result.avatar)}" alt=""
                             class="avatar avatar-small" width="28" height="28" loading="lazy">
                    </a>
                    <div class="comment-body">
                        ${buildCommentBodyHtml(result.comment_id, result.profile_url, result.username, result.time_ago, result.content, currentUserId, false, result.content_html, imageData, 0, false)}
                    </div>
                </div>`;
                const commentForm = section.querySelector('.comment-form');
                if (commentForm) {
                    commentForm.insertAdjacentHTML('beforebegin', commentHtml);
                } else {
                    section.insertAdjacentHTML('beforeend', commentHtml);
                }
                reinitLightboxTriggers();
            }
            // Update comment count
            const commentBtn = document.querySelector(`.btn-comment[data-post-id="${postId}"]`);
            if (commentBtn) {
                const current = parseInt(commentBtn.textContent.replace(/\D/g, ''), 10) || 0;
                commentBtn.textContent = '💬 ' + (current + 1);
            }
        } else {
            alert('Error: ' + (result.error || 'Could not post comment'));
        }
    } catch (err) {
        console.error('Comment failed:', err);
    }
});

// ── Load all comments ("View all X comments" link) ───────────────────────────

document.addEventListener('click', async (e) => {
    const link = e.target.closest('.load-more-comments');
    if (!link) return;

    e.preventDefault();

    const postId  = link.dataset.postId;
    const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';

    link.textContent = 'Loading\u2026';

    try {
        const resp   = await fetch(
            baseUrl + '/modules/wall/get_comments.php?post_id=' + encodeURIComponent(postId),
            { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        const result = await resp.json();

        if (result.ok) {
            const section = document.getElementById('comments-' + postId);
            if (section) {
                // Remove all existing comment items before re-rendering the full list
                section.querySelectorAll('.comment-item').forEach(el => el.remove());

                // Build HTML for all comments and insert before the "load more" link
                const html = result.comments.map(c => {
                    const imageData = c.image_thumb_url
                        ? { thumb_url: c.image_thumb_url, large_url: c.image_large_url }
                        : null;
                    return `
                <div class="comment-item" id="comment-${parseInt(c.id, 10)}">
                    <a href="${escapeHtml(c.profile_url)}">
                        <img src="${escapeHtml(c.avatar)}" alt=""
                             class="avatar avatar-small" width="28" height="28" loading="lazy">
                    </a>
                    <div class="comment-body">
                        ${buildCommentBodyHtml(c.id, c.profile_url, c.username, c.time_ago, c.content, c.user_id, !!c.edited, c.content_html, imageData, c.like_count, !!c.user_liked)}
                    </div>
                </div>`;
                }).join('');

                link.insertAdjacentHTML('beforebegin', html);
                reinitLightboxTriggers();
            }
            // Remove the "View all" link — all comments are now visible
            link.remove();
        } else {
            link.textContent = 'Could not load comments. Try again.';
        }
    } catch (err) {
        console.error('Load comments failed:', err);
        link.textContent = 'Could not load comments. Try again.';
    }
});

// ── Blog post comment form (AJAX) ─────────────────────────────────────────────

document.addEventListener('submit', async (e) => {
    const form = e.target.closest('.blog-comment-form');
    if (!form) return;

    e.preventDefault();

    const blogPostId   = form.dataset.blogPostId;
    const input        = form.querySelector('input[name="content"]');
    const content      = input ? input.value.trim() : '';
    if (!content) return;

    const imageMediaId = commentImageState.get(form) || 0;

    const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';
    const data    = new URLSearchParams({
        csrf_token:     getCsrfToken(),
        blog_post_id:   blogPostId,
        content,
        image_media_id: imageMediaId,
    });

    try {
        const result = await apiPost(baseUrl + '/modules/blog/add_comment.php', data);

        if (result.ok) {
            // Clear the input immediately on server confirmation (same defensive
            // pattern as the wall comment handler above).
            if (input) {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            clearCommentImagePreview(form);
            const section = document.getElementById('blog-comments-' + blogPostId);
            if (section) {
                const currentUserId = getCurrentUserId();
                const imageData = result.image_thumb_url
                    ? { thumb_url: result.image_thumb_url, large_url: result.image_large_url }
                    : null;
                const commentHtml = `
                <div class="comment-item" id="comment-${parseInt(result.comment_id, 10)}">
                    <a href="${escapeHtml(result.profile_url)}">
                        <img src="${escapeHtml(result.avatar)}" alt=""
                             class="avatar avatar-small" width="28" height="28" loading="lazy">
                    </a>
                    <div class="comment-body">
                        ${buildCommentBodyHtml(result.comment_id, result.profile_url, result.username, result.time_ago, result.content, currentUserId, false, result.content_html, imageData, 0, false)}
                    </div>
                </div>`;
                const commentForm = section.querySelector('.blog-comment-form');
                if (commentForm) {
                    commentForm.insertAdjacentHTML('beforebegin', commentHtml);
                } else {
                    section.insertAdjacentHTML('beforeend', commentHtml);
                }
                reinitLightboxTriggers();
            }
            // Update comment count badge
            const countEl = document.querySelector(`.btn-comment[data-blog-post-id="${blogPostId}"] .blog-comment-count`);
            if (countEl) {
                countEl.textContent = (parseInt(countEl.textContent, 10) || 0) + 1;
            }
        } else {
            alert('Error: ' + (result.error || 'Could not post comment'));
        }
    } catch (err) {
        console.error('Blog comment failed:', err);
    }
});

// ── Load more blog comments ───────────────────────────────────────────────────

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.load-more-blog-comments');
    if (!btn) return;

    e.preventDefault();

    const blogPostId = btn.dataset.blogPostId;
    const baseUrl    = document.querySelector('meta[name="site-url"]')?.content || '';

    btn.disabled    = true;
    btn.textContent = 'Loading\u2026';

    try {
        const resp   = await fetch(
            baseUrl + '/modules/blog/get_comments.php?blog_post_id=' + encodeURIComponent(blogPostId),
            { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        const result = await resp.json();

        if (result.ok) {
            const section = document.getElementById('blog-comments-' + blogPostId);
            if (section) {
                // Remove all existing comment items before re-rendering the full list
                section.querySelectorAll('.comment-item').forEach(el => el.remove());

                // Build HTML for all comments and insert before the "load more" button
                const html = result.comments.map(c => {
                    const imageData = c.image_thumb_url
                        ? { thumb_url: c.image_thumb_url, large_url: c.image_large_url }
                        : null;
                    return `
                <div class="comment-item" id="comment-${parseInt(c.id, 10)}">
                    <a href="${escapeHtml(c.profile_url)}">
                        <img src="${escapeHtml(c.avatar)}" alt=""
                             class="avatar avatar-small" width="28" height="28" loading="lazy">
                    </a>
                    <div class="comment-body">
                        ${buildCommentBodyHtml(c.id, c.profile_url, c.username, c.time_ago, c.content, c.user_id, !!c.edited, c.content_html, imageData, c.like_count, !!c.user_liked)}
                    </div>
                </div>`;
                }).join('');

                btn.insertAdjacentHTML('beforebegin', html);
                reinitLightboxTriggers();
            }
            // Remove the "Load more" button — all comments are now visible
            btn.remove();
        } else {
            btn.disabled    = false;
            btn.textContent = 'Could not load comments. Try again.';
        }
    } catch (err) {
        console.error('Load blog comments failed:', err);
        btn.disabled    = false;
        btn.textContent = 'Could not load comments. Try again.';
    }
});

// ── Comment edit (inline, AJAX) ────────────────────────────────────────────────

document.addEventListener('click', async (e) => {
    // ── Edit button click: show inline form ──
    const editBtn = e.target.closest('.comment-edit-btn');
    if (editBtn) {
        const commentId  = editBtn.dataset.commentId;
        const body       = editBtn.closest('.comment-body');
        if (!body) return;
        const textEl = body.querySelector('.comment-text');
        if (!textEl) return;

        // Don't open a second form if one already exists
        if (body.querySelector('.comment-edit-form')) return;

        const rawContent = textEl.dataset.raw || '';

        textEl.style.display = 'none';
        editBtn.style.display = 'none';

        const form = document.createElement('div');
        form.className = 'comment-edit-form';

        const input = document.createElement('input');
        input.type      = 'text';
        input.value     = rawContent;
        input.maxLength = 1000;

        const actions = document.createElement('div');
        actions.className = 'comment-edit-actions';

        const saveBtn = document.createElement('button');
        saveBtn.type      = 'button';
        saveBtn.className = 'btn btn-xs btn-primary';
        saveBtn.textContent = 'Save';

        const cancelBtn = document.createElement('button');
        cancelBtn.type      = 'button';
        cancelBtn.className = 'btn btn-xs btn-secondary';
        cancelBtn.textContent = 'Cancel';

        actions.appendChild(saveBtn);
        actions.appendChild(cancelBtn);
        form.appendChild(input);
        form.appendChild(actions);
        body.appendChild(form);
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);

        // Cancel: restore original display
        cancelBtn.addEventListener('click', () => {
            form.remove();
            textEl.style.display = '';
            editBtn.style.display = '';
        });

        // Save: submit edit
        saveBtn.addEventListener('click', async () => {
            const newContent = input.value.trim();
            if (!newContent) return;

            saveBtn.disabled   = true;
            cancelBtn.disabled = true;

            const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';
            try {
                const result = await apiPost(baseUrl + '/modules/wall/edit_comment.php', new URLSearchParams({
                    csrf_token: getCsrfToken(),
                    comment_id: commentId,
                    content:    newContent,
                }));

                if (result.ok) {
                    // Update the raw content and rendered text
                    textEl.dataset.raw   = result.content;
                    textEl.innerHTML     = result.content_html || linkifyHtml(smilifyText(result.content));
                    textEl.style.display = '';
                    editBtn.style.display = '';

                    // Add or update the "(edited)" badge
                    let editedBadge = body.querySelector('.comment-edited');
                    if (!editedBadge) {
                        editedBadge = document.createElement('span');
                        editedBadge.className   = 'comment-edited';
                        editedBadge.textContent = '(edited)';
                        editBtn.insertAdjacentElement('beforebegin', editedBadge);
                    }

                    form.remove();
                } else {
                    saveBtn.disabled   = false;
                    cancelBtn.disabled = false;
                    alert('Error: ' + (result.error || 'Could not save comment'));
                }
            } catch (err) {
                console.error('Comment edit failed:', err);
                saveBtn.disabled   = false;
                cancelBtn.disabled = false;
            }
        });

        // Allow submitting with Enter key
        input.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') { ev.preventDefault(); saveBtn.click(); }
            if (ev.key === 'Escape') { cancelBtn.click(); }
        });

        return;
    }
});

// ── Image preview before upload ───────────────────────────────────────────────

const postImageInput = document.getElementById('post-image');
const imagePreview   = document.getElementById('image-preview');

/**
 * Show a preview for the given File in #image-preview.
 * @param {File} file
 */
function showPostImagePreview(file) {
    if (!imagePreview) return;
    if (!file.type.startsWith('image/') && !file.type.startsWith('video/')) {
        imagePreview.innerHTML = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = (ev) => {
        if (file.type.startsWith('image/')) {
            imagePreview.innerHTML =
                `<img src="${escapeHtml(ev.target.result)}" alt="Preview">`;
        } else {
            imagePreview.innerHTML =
                `<span>🎥 ${escapeHtml(file.name)}</span>`;
        }
    };
    reader.readAsDataURL(file);
}

if (postImageInput && imagePreview) {
    postImageInput.addEventListener('change', () => {
        const file = postImageInput.files[0];
        if (!file) {
            imagePreview.innerHTML = '';
            return;
        }
        showPostImagePreview(file);
    });
}

// ── Drag-and-drop image onto the wall post composer ───────────────────────────

const postComposer = document.querySelector('.post-composer');

if (postComposer && postImageInput) {
    // Use a counter to handle nested-element enter/leave events reliably
    let dragDepth = 0;

    postComposer.addEventListener('dragenter', (e) => {
        if (e.dataTransfer.types.includes('Files')) {
            e.preventDefault();
            dragDepth++;
            postComposer.classList.add('drag-over');
        }
    });

    postComposer.addEventListener('dragover', (e) => {
        if (e.dataTransfer.types.includes('Files')) {
            e.preventDefault();
        }
    });

    postComposer.addEventListener('dragleave', () => {
        dragDepth--;
        if (dragDepth <= 0) {
            dragDepth = 0;
            postComposer.classList.remove('drag-over');
        }
    });

    postComposer.addEventListener('drop', (e) => {
        e.preventDefault();
        dragDepth = 0;
        postComposer.classList.remove('drag-over');

        const files = e.dataTransfer.files;
        if (!files || files.length === 0) return;

        const file = files[0];
        if (!file.type.startsWith('image/') && !file.type.startsWith('video/')) {
            if (imagePreview) {
                imagePreview.innerHTML =
                    `<span class="drag-drop-error">⚠️ Only image and video files are supported.</span>`;
            }
            return;
        }

        // Assign the dropped file to the existing file input via DataTransfer
        const dt = new DataTransfer();
        dt.items.add(file);
        postImageInput.files = dt.files;

        showPostImagePreview(file);
    });
}

// ── Avatar crop tool ──────────────────────────────────────────────────────────

const avatarInput     = document.getElementById('avatar-input');
const cropContainer   = document.getElementById('avatar-crop-container');
const cropCanvas      = document.getElementById('avatar-crop-canvas');

if (avatarInput && cropContainer && cropCanvas) {
    let cropState = { startX: 0, startY: 0, endX: 0, endY: 0, isDragging: false };
    let sourceImage = null;
    let imgScale    = 1;

    avatarInput.addEventListener('change', () => {
        const file = avatarInput.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = (ev) => {
            const img = new Image();
            img.onload = () => {
                sourceImage = img;
                const maxW = 360;
                imgScale    = Math.min(maxW / img.width, maxW / img.height, 1);
                cropCanvas.width  = Math.round(img.width  * imgScale);
                cropCanvas.height = Math.round(img.height * imgScale);

                const ctx = cropCanvas.getContext('2d');
                ctx.drawImage(img, 0, 0, cropCanvas.width, cropCanvas.height);
                cropContainer.style.display = 'block';

                // Default crop: full image square
                const side = Math.min(cropCanvas.width, cropCanvas.height);
                const cx   = (cropCanvas.width  - side) / 2;
                const cy   = (cropCanvas.height - side) / 2;
                updateCropInputs(
                    Math.round(cx / imgScale),
                    Math.round(cy / imgScale),
                    Math.round(side / imgScale),
                    Math.round(side / imgScale)
                );
                drawCropOverlay(cx, cy, side, side);
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    /** Normalise a pointer event to canvas-relative coordinates */
    function getCanvasPos(e) {
        const rect = cropCanvas.getBoundingClientRect();
        const src  = e.touches ? e.touches[0] : e;
        return {
            x: Math.min(Math.max(src.clientX - rect.left, 0), cropCanvas.width),
            y: Math.min(Math.max(src.clientY - rect.top,  0), cropCanvas.height),
        };
    }

    function onCropStart(e) {
        e.preventDefault();
        const pos = getCanvasPos(e);
        cropState.startX     = pos.x;
        cropState.startY     = pos.y;
        cropState.isDragging = true;
    }
    function onCropMove(e) {
        if (!cropState.isDragging || !sourceImage) return;
        e.preventDefault();
        const pos = getCanvasPos(e);
        cropState.endX = pos.x;
        cropState.endY = pos.y;

        const w    = Math.abs(cropState.endX - cropState.startX);
        const h    = Math.abs(cropState.endY - cropState.startY);
        const side = Math.max(w, h);
        const x    = Math.min(cropState.startX, cropState.endX);
        const y    = Math.min(cropState.startY, cropState.endY);

        drawCropOverlay(x, y, side, side);
        updateCropInputs(
            Math.round(x / imgScale),
            Math.round(y / imgScale),
            Math.round(side / imgScale),
            Math.round(side / imgScale)
        );
    }
    function onCropEnd(e) { cropState.isDragging = false; }

    cropCanvas.addEventListener('mousedown',  onCropStart);
    cropCanvas.addEventListener('mousemove',  onCropMove);
    cropCanvas.addEventListener('mouseup',    onCropEnd);
    cropCanvas.addEventListener('touchstart', onCropStart, { passive: false });
    cropCanvas.addEventListener('touchmove',  onCropMove,  { passive: false });
    cropCanvas.addEventListener('touchend',   onCropEnd);

    function drawCropOverlay(x, y, w, h) {
        if (!sourceImage) return;
        const ctx = cropCanvas.getContext('2d');
        ctx.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
        ctx.drawImage(sourceImage, 0, 0, cropCanvas.width, cropCanvas.height);
        // Darken non-crop area
        ctx.fillStyle = 'rgba(0,0,0,0.5)';
        ctx.fillRect(0, 0, cropCanvas.width, cropCanvas.height);
        // Clear the crop region
        ctx.clearRect(x, y, w, h);
        ctx.drawImage(sourceImage, x / imgScale, y / imgScale, w / imgScale, h / imgScale, x, y, w, h);
        // Draw border
        ctx.strokeStyle = '#e94560';
        ctx.lineWidth   = 2;
        ctx.strokeRect(x, y, w, h);
    }

    function updateCropInputs(x, y, w, h) {
        document.getElementById('crop-x').value = x;
        document.getElementById('crop-y').value = y;
        document.getElementById('crop-w').value = w;
        document.getElementById('crop-h').value = h;
    }
}

// ── Gallery dropzone ──────────────────────────────────────────────────────────

(function initGalleryDropzone() {
    const dropzone    = document.getElementById('gallery-dropzone');
    const fileInput   = document.getElementById('gallery-file-input');
    const previewsEl  = document.getElementById('dropzone-previews');
    const uploadForm  = document.getElementById('gallery-upload-form');
    const uploadBtn   = document.getElementById('gallery-upload-btn');

    if (!dropzone || !fileInput || !previewsEl || !uploadForm) return;

    let selectedFiles = [];

    // Click anywhere in dropzone to open file picker
    dropzone.addEventListener('click', (e) => {
        if (e.target.closest('.preview-remove') || e.target.closest('#gallery-upload-btn')) return;
        fileInput.click();
    });

    // Drag-and-drop events
    dropzone.addEventListener('dragenter', (e) => { e.preventDefault(); dropzone.classList.add('drag-over'); });
    dropzone.addEventListener('dragover',  (e) => { e.preventDefault(); dropzone.classList.add('drag-over'); });
    dropzone.addEventListener('dragleave', (e) => {
        if (!dropzone.contains(e.relatedTarget)) dropzone.classList.remove('drag-over');
    });
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        addFiles(Array.from(e.dataTransfer.files));
    });

    // File input change
    fileInput.addEventListener('change', () => {
        addFiles(Array.from(fileInput.files));
        fileInput.value = ''; // reset so same file can be re-added
    });

    function addFiles(files) {
        files.forEach((file) => {
            if (!file.type.startsWith('image/') && !file.type.startsWith('video/')) return;
            selectedFiles.push(file);
            renderPreview(file);
        });
        updateUploadButton();
    }

    function renderPreview(file) {
        const item = document.createElement('div');
        item.className = 'dropzone-preview-item';

        const removeBtn = document.createElement('button');
        removeBtn.type        = 'button';
        removeBtn.className   = 'preview-remove';
        removeBtn.textContent = '✕';
        removeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const idx = selectedFiles.indexOf(file);
            if (idx !== -1) selectedFiles.splice(idx, 1);
            item.remove();
            updateUploadButton();
        });

        const nameEl       = document.createElement('div');
        nameEl.className   = 'preview-name';
        nameEl.textContent = file.name;

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.alt   = '';
            const reader = new FileReader();
            reader.onload = (ev) => { img.src = ev.target.result; };
            reader.readAsDataURL(file);
            item.appendChild(img);
        } else {
            const icon       = document.createElement('div');
            icon.className   = 'dropzone-video-icon';
            icon.textContent = '🎥';
            item.appendChild(icon);
        }

        item.appendChild(removeBtn);
        item.appendChild(nameEl);
        previewsEl.appendChild(item);
    }

    function updateUploadButton() {
        if (!uploadBtn) return;
        const count = selectedFiles.length;
        if (count > 0) {
            uploadBtn.style.display = 'inline-block';
            uploadBtn.textContent   = 'Upload ' + count + ' file' + (count !== 1 ? 's' : '');
        } else {
            uploadBtn.style.display = 'none';
        }
    }

    /**
     * The three server-side processing stages shown in the progress bar.
     * UPLOAD  = data transfer to server  (bar: 0 – 60 %)
     * SCRUB   = EXIF/metadata stripping  (bar: 62 – 80 %)
     * RESIZE  = generating image sizes   (bar: 82 – 99 %)
     */
    const STAGES = [
        { key: 'upload', icon: '↑', label: 'Upload' },
        { key: 'scrub',  icon: '✦', label: 'Scrub'  },
        { key: 'resize', icon: '⊞', label: 'Resize' },
    ];

    // Progress bar percentages for each server-side processing step
    const PCT_SCRUB_START  = 62;   // bar value when the scrub step begins
    const PCT_RESIZE_START = 82;   // bar value when the resize step begins
    const SCRUB_DELAY_MS   = 600;  // ms to show "Scrubbing" before advancing to "Resizing"

    /** Lazily create (or retrieve) the progress bar element inside the dropzone. */
    function getProgress() {
        let el = dropzone.querySelector('.upload-progress');
        if (!el) {
            // Build step indicators
            const stepsHtml = STAGES.map((s, i) => {
                const line = i < STAGES.length - 1
                    ? '<div class="upload-step-line"></div>'
                    : '';
                return '<div class="upload-step" data-step="' + s.key + '">'
                     +   '<div class="upload-step-icon">' + s.icon + '</div>'
                     +   '<div class="upload-step-label">' + s.label + '</div>'
                     + '</div>'
                     + line;
            }).join('');

            el = document.createElement('div');
            el.className  = 'upload-progress';
            el.innerHTML  = '<div class="upload-progress-steps">' + stepsHtml + '</div>'
                          + '<div class="upload-progress-track">'
                          +   '<div class="upload-progress-fill"></div>'
                          + '</div>'
                          + '<div class="upload-progress-info"></div>';
            dropzone.appendChild(el);
        }
        return el;
    }

    /**
     * Update the progress bar.
     * @param {number} pct   - Fill percentage (0–100).
     * @param {string} label - Status text.
     * @param {string} [step] - Key of the current stage ('upload'|'scrub'|'resize'|'done').
     */
    function setProgress(pct, label, step) {
        const el = getProgress();
        el.querySelector('.upload-progress-fill').style.width = pct + '%';
        el.querySelector('.upload-progress-info').textContent = label;
        el.style.display = 'block';

        if (step) {
            const currentIdx = STAGES.findIndex((s) => s.key === step);
            el.querySelectorAll('.upload-step').forEach((stepEl, i) => {
                stepEl.classList.remove('active', 'done');
                if (step === 'done' || i < currentIdx) {
                    stepEl.classList.add('done');
                } else if (i === currentIdx) {
                    stepEl.classList.add('active');
                }
            });
            // Update connector lines
            el.querySelectorAll('.upload-step-line').forEach((line, i) => {
                line.classList.toggle('done', step === 'done' || i < currentIdx);
            });
        }
    }

    function hideProgress() {
        const el = dropzone.querySelector('.upload-progress');
        if (el) el.style.display = 'none';
    }

    // On form submit, upload via XHR in batches to work around PHP's
    // max_file_uploads limit (default: 20). Each batch is sent sequentially
    // and the wall post is only created after the final batch completes.
    uploadForm.addEventListener('submit', (e) => {
        e.preventDefault();
        if (selectedFiles.length === 0) return;

        const BATCH_SIZE   = parseInt(uploadForm.dataset.batchSize || '20', 10);
        const batches      = [];
        for (let i = 0; i < selectedFiles.length; i += BATCH_SIZE) {
            batches.push(selectedFiles.slice(i, i + BATCH_SIZE));
        }

        const totalFiles   = selectedFiles.length;
        const totalBatches = batches.length;
        const batchPortion = 100 / totalBatches;  // % of the bar each batch covers
        const fileWord     = (n) => n + ' file' + (n !== 1 ? 's' : '');

        setProgress(0, 'Preparing ' + fileWord(totalFiles) + '…', 'upload');
        if (uploadBtn) uploadBtn.disabled = true;

        let batchIndex       = 0;
        let totalUploaded    = 0;
        let allErrors        = [];
        let finalRedirectUrl = window.location.href;

        function uploadBatch() {
            if (batchIndex >= totalBatches) {
                const msgs = [];
                if (totalUploaded > 0) msgs.push(fileWord(totalUploaded) + ' uploaded successfully.');
                if (allErrors.length > 0) msgs.push(...allErrors);
                setProgress(100, msgs.length > 0 ? msgs.join(' ') : 'Done.', 'done');
                setTimeout(() => { window.location.href = finalRedirectUrl; }, 800);
                return;
            }

            const batch       = batches[batchIndex];
            const isLastBatch = batchIndex === totalBatches - 1;
            const batchStart  = batchIndex * batchPortion;

            const dt = new DataTransfer();
            batch.forEach((f) => dt.items.add(f));
            fileInput.files = dt.files;

            const formData = new FormData(uploadForm);
            // Tell the server to create the wall post only on the last batch
            formData.set('create_post', isLastBatch ? '1' : '0');

            const batchLabel = totalBatches > 1
                ? ' (' + (batchIndex + 1) + '/' + totalBatches + ')'
                : '';

            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            // Track whether the server has already responded (to avoid overwriting the
            // final state with a late-firing processing animation timer).
            let serverDone    = false;
            let processingTimer = null;

            xhr.upload.addEventListener('progress', (ev) => {
                if (ev.lengthComputable) {
                    // Map real upload progress to 0–60 % of this batch's portion of the bar
                    const ratio = ev.loaded / ev.total;
                    const pct   = Math.round(batchStart + batchPortion * ratio * 0.60);
                    setProgress(pct, 'Uploading' + batchLabel + '… ' + Math.round(ratio * 100) + '%', 'upload');
                }
            });

            // All bytes sent — server is now scrubbing and resizing
            xhr.upload.addEventListener('load', () => {
                if (serverDone) return;
                const pct = Math.round(batchStart + batchPortion * PCT_SCRUB_START / 100);
                setProgress(pct, 'Scrubbing metadata…', isLastBatch ? 'scrub' : 'upload');
                processingTimer = setTimeout(() => {
                    if (!serverDone) {
                        const pct2 = Math.round(batchStart + batchPortion * PCT_RESIZE_START / 100);
                        setProgress(pct2, 'Resizing images…', isLastBatch ? 'resize' : 'upload');
                    }
                }, SCRUB_DELAY_MS);
            });

            xhr.addEventListener('load', () => {
                serverDone = true;
                if (processingTimer) clearTimeout(processingTimer);

                try {
                    const data = JSON.parse(xhr.responseText);
                    totalUploaded    += (data.uploaded || 0);
                    finalRedirectUrl  = data.redirect || finalRedirectUrl;
                    if (data.errors && data.errors.length > 0) allErrors.push(...data.errors);
                } catch (_) {
                    // Server returned non-JSON; continue with next batch
                }

                batchIndex++;
                uploadBatch();
            });

            xhr.addEventListener('error', () => {
                serverDone = true;
                if (processingTimer) clearTimeout(processingTimer);
                hideProgress();
                if (uploadBtn) uploadBtn.disabled = false;
                alert('Upload failed. Please try again.');
            });

            xhr.send(formData);
        }

        uploadBatch();
    });
})();

// ── Cover crop modal ──────────────────────────────────────────────────────────

(function initCoverCropModal() {
    const modal       = document.getElementById('cover-crop-modal');
    const canvas      = document.getElementById('cover-crop-canvas');
    const cancelBtn   = document.getElementById('cover-crop-cancel');
    const albumIdEl   = document.getElementById('cover-album-id');
    const mediaIdEl   = document.getElementById('cover-media-id');

    if (!modal || !canvas) return;

    let coverImage = null;
    let imgScale          = 1;
    let canvasToOrigScale = 1; // converts canvas px → original image px
    let cropState  = { startX: 0, startY: 0, endX: 0, endY: 0, isDragging: false,
                       mode: 'draw', moveOX: 0, moveOY: 0 };
    let currentCrop = { x: 0, y: 0, w: 0, h: 0 };

    // Open modal when "Set as Cover" is clicked
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.set-cover-btn');
        if (!btn) return;

        const mediaSrc  = btn.dataset.mediaSrc;
        const mediaId   = btn.dataset.mediaId;
        const albumId   = btn.dataset.albumId;
        const origWidth  = parseInt(btn.dataset.origWidth,  10) || 0;
        const origHeight = parseInt(btn.dataset.origHeight, 10) || 0;

        albumIdEl.value = albumId;
        mediaIdEl.value = mediaId;

        // Load the image into the canvas
        const img = new Image();
        img.onload = () => {
            coverImage = img;
            const maxW = 480;
            imgScale   = Math.min(maxW / img.width, maxW / img.height, 1);
            canvas.width  = Math.round(img.width  * imgScale);
            canvas.height = Math.round(img.height * imgScale);

            // canvasToOrigScale converts canvas coordinates to original-image coordinates.
            // The mediaSrc is a scaled-down version of the original; use the stored
            // original dimensions so crop coordinates sent to the server are correct.
            canvasToOrigScale = (origWidth > 0 ? origWidth : img.width) / canvas.width;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            // Default: full-square crop centred
            const side = Math.min(canvas.width, canvas.height);
            const cx   = Math.round((canvas.width  - side) / 2);
            const cy   = Math.round((canvas.height - side) / 2);
            currentCrop = { x: cx, y: cy, w: side, h: side };
            drawCoverOverlay(cx, cy, side, side);
            setCoverCropInputs(
                Math.round(cx   * canvasToOrigScale),
                Math.round(cy   * canvasToOrigScale),
                Math.round(side * canvasToOrigScale),
                Math.round(side * canvasToOrigScale)
            );

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        };
        img.src = mediaSrc;
    });

    // Close modal
    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        coverImage = null;
    }
    cancelBtn && cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    /** Normalise pointer coordinates relative to canvas */
    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const src  = e.touches ? e.touches[0] : e;
        return {
            x: Math.min(Math.max(src.clientX - rect.left, 0), canvas.width),
            y: Math.min(Math.max(src.clientY - rect.top,  0), canvas.height),
        };
    }

    function onStart(e) {
        e.preventDefault();
        const pos = getPos(e);
        // Click inside existing crop box → move it; outside → draw a new one
        if (currentCrop.w > 0 &&
            pos.x >= currentCrop.x && pos.x <= currentCrop.x + currentCrop.w &&
            pos.y >= currentCrop.y && pos.y <= currentCrop.y + currentCrop.h) {
            cropState.mode   = 'move';
            cropState.moveOX = pos.x - currentCrop.x;
            cropState.moveOY = pos.y - currentCrop.y;
        } else {
            cropState.mode   = 'draw';
            cropState.startX = pos.x;
            cropState.startY = pos.y;
        }
        cropState.isDragging = true;
    }
    function onMove(e) {
        if (!coverImage) return;
        const pos = getPos(e);

        if (!cropState.isDragging) {
            // Update cursor to indicate whether a drag will move or draw
            const inside = currentCrop.w > 0 &&
                pos.x >= currentCrop.x && pos.x <= currentCrop.x + currentCrop.w &&
                pos.y >= currentCrop.y && pos.y <= currentCrop.y + currentCrop.h;
            canvas.style.cursor = inside ? 'move' : 'crosshair';
            return;
        }

        e.preventDefault();

        if (cropState.mode === 'move') {
            // Drag the existing crop box
            let nx = pos.x - cropState.moveOX;
            let ny = pos.y - cropState.moveOY;
            nx = Math.min(Math.max(nx, 0), canvas.width  - currentCrop.w);
            ny = Math.min(Math.max(ny, 0), canvas.height - currentCrop.h);
            currentCrop.x = nx;
            currentCrop.y = ny;
            drawCoverOverlay(nx, ny, currentCrop.w, currentCrop.h);
            setCoverCropInputs(
                Math.round(nx             * canvasToOrigScale),
                Math.round(ny             * canvasToOrigScale),
                Math.round(currentCrop.w  * canvasToOrigScale),
                Math.round(currentCrop.h  * canvasToOrigScale)
            );
        } else {
            // Draw a new crop selection (square-constrained)
            cropState.endX = pos.x;
            cropState.endY = pos.y;

            const w    = Math.abs(cropState.endX - cropState.startX);
            const h    = Math.abs(cropState.endY - cropState.startY);
            const side = Math.max(w, h);
            const x    = Math.min(cropState.startX, cropState.endX);
            const y    = Math.min(cropState.startY, cropState.endY);

            currentCrop = { x, y, w: side, h: side };
            drawCoverOverlay(x, y, side, side);
            setCoverCropInputs(
                Math.round(x    * canvasToOrigScale),
                Math.round(y    * canvasToOrigScale),
                Math.round(side * canvasToOrigScale),
                Math.round(side * canvasToOrigScale)
            );
        }
    }
    function onEnd() { cropState.isDragging = false; }

    canvas.addEventListener('mousedown',  onStart);
    canvas.addEventListener('mousemove',  onMove);
    canvas.addEventListener('mouseup',    onEnd);
    canvas.addEventListener('touchstart', onStart, { passive: false });
    canvas.addEventListener('touchmove',  onMove,  { passive: false });
    canvas.addEventListener('touchend',   onEnd);

    function drawCoverOverlay(x, y, w, h) {
        if (!coverImage) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(coverImage, 0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'rgba(0,0,0,0.52)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        if (w > 0 && h > 0) {
            ctx.clearRect(x, y, w, h);
            ctx.drawImage(coverImage, x / imgScale, y / imgScale, w / imgScale, h / imgScale, x, y, w, h);
            ctx.strokeStyle = '#e94560';
            ctx.lineWidth   = 2;
            ctx.strokeRect(x, y, w, h);
        }
    }

    function setCoverCropInputs(x, y, w, h) {
        document.getElementById('cover-crop-x').value = x;
        document.getElementById('cover-crop-y').value = y;
        document.getElementById('cover-crop-w').value = w;
        document.getElementById('cover-crop-h').value = h;
    }
})();

// ── Move media modal ───────────────────────────────────────────────────────────

(function initMoveMediaModal() {
    const modal    = document.getElementById('move-media-modal');
    const mediaIdEl = document.getElementById('move-media-id');
    const cancelBtn = document.getElementById('move-media-cancel');

    if (!modal) return;

    // Restore scroll position after a move redirect.
    // If the user had used "Load More" before moving, the dynamically-loaded
    // posts won't be on the page yet — re-fetch those batches first, then
    // scroll to the exact post that contained the moved media item.
    const savedScroll     = sessionStorage.getItem('moveMediaScrollY');
    const savedPostId     = sessionStorage.getItem('moveMediaPostId');
    const savedFeedId     = sessionStorage.getItem('moveMediaFeedId');
    const savedFeedOffset = sessionStorage.getItem('moveMediaFeedOffset');
    sessionStorage.removeItem('moveMediaScrollY');
    sessionStorage.removeItem('moveMediaPostId');
    sessionStorage.removeItem('moveMediaFeedId');
    sessionStorage.removeItem('moveMediaFeedOffset');

    if (savedScroll !== null) {
        (async () => {
            const feed         = savedFeedId ? document.getElementById(savedFeedId) : null;
            const targetOffset = savedFeedOffset ? parseInt(savedFeedOffset, 10) : 0;

            const BATCH_SIZE = feed ? parseInt(feed.dataset.offset || '10', 10) : 10;
            if (feed && targetOffset > BATCH_SIZE) {
                const baseUrl    = document.querySelector('meta[name="site-url"]')?.content || '';
                let   offset     = BATCH_SIZE; // initial batch already rendered by PHP

                while (offset < targetOffset) {
                    const endpoint = feed.id === 'post-feed'
                        ? baseUrl + '/modules/wall/load_posts.php?offset=' + encodeURIComponent(offset)
                        : baseUrl + '/modules/wall/load_profile_posts.php'
                            + '?user_id=' + encodeURIComponent(feed.dataset.profileId || '')
                            + '&offset='  + encodeURIComponent(offset);

                    try {
                        const resp   = await fetch(endpoint, {
                            credentials: 'same-origin',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const result = await resp.json();
                        if (result.ok && result.html) {
                            const countBefore = feed.querySelectorAll('.post-item').length;
                            feed.insertAdjacentHTML('beforeend', result.html);
                            offset += BATCH_SIZE;
                            feed.dataset.loadedOffset = String(offset);

                            const allPosts  = feed.querySelectorAll('.post-item');
                            const newPosts  = Array.from(allPosts).slice(countBefore);
                            const lazyObs   = typeof window.lazyObserveImages === 'function' ? window.lazyObserveImages : null;
                            const lbBindNew = typeof window.lightboxBindNew    === 'function' ? window.lightboxBindNew    : null;
                            newPosts.forEach(post => {
                                if (lazyObs)   lazyObs(post);
                                if (lbBindNew) lbBindNew(post);
                            });
                        } else {
                            break;
                        }
                    } catch (err) {
                        break;
                    }
                }
            }

            // Scroll to the post that held the moved media; fall back to raw scrollY
            const targetEl = savedPostId ? document.getElementById(savedPostId) : null;
            if (targetEl) {
                targetEl.scrollIntoView({ block: 'start', behavior: 'instant' });
            } else {
                window.scrollTo(0, parseInt(savedScroll, 10));
            }
        })();
    }

    function openModal(mediaId) {
        mediaIdEl.value = mediaId;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.move-media-btn');
        if (!btn) return;
        openModal(btn.dataset.mediaId);
    });

    const form = document.getElementById('move-media-form');
    form && form.addEventListener('submit', () => {
        sessionStorage.setItem('moveMediaScrollY', String(window.scrollY));

        // Remember which post held the media so we can scroll straight to it
        const moveBtn  = document.querySelector('.move-media-btn[data-media-id="' + CSS.escape(mediaIdEl.value) + '"]');
        const postItem = moveBtn ? moveBtn.closest('.post-item') : null;
        if (postItem && postItem.id) {
            sessionStorage.setItem('moveMediaPostId', postItem.id);
        }

        // Save the feed's loaded-offset so extra batches can be re-fetched after redirect
        const feed = document.getElementById('post-feed') || document.getElementById('profile-post-feed');
        if (feed && feed.dataset.loadedOffset) {
            sessionStorage.setItem('moveMediaFeedId',     feed.id);
            sessionStorage.setItem('moveMediaFeedOffset', feed.dataset.loadedOffset);
        }
    });

    cancelBtn && cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
})();

// ── Banner crop tool (admin/settings.php) ─────────────────────────────────────

(function initBannerCrop() {
    const fileInput  = document.getElementById('banner-file-input');
    const container  = document.getElementById('banner-crop-container');
    const canvas     = document.getElementById('banner-crop-canvas');
    const resetBtn   = document.getElementById('banner-crop-reset');
    const infoEl     = document.getElementById('banner-crop-info');

    if (!fileInput || !container || !canvas) return;

    // Target aspect ratio: 1400 × 250 = 28 : 5
    const RATIO = 1400 / 250;

    let sourceImage = null;
    let imgScale    = 1;
    let cropState   = { startX: 0, startY: 0, endX: 0, endY: 0,
                        isDragging: false, mode: 'draw', moveOX: 0, moveOY: 0 };
    let currentCrop = { x: 0, y: 0, w: 0, h: 0 };

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = (ev) => {
            const img = new Image();
            img.onload = () => {
                sourceImage = img;
                const maxW = 700;
                imgScale   = Math.min(maxW / img.width, 1);
                canvas.width  = Math.round(img.width  * imgScale);
                canvas.height = Math.round(img.height * imgScale);

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                container.style.display = 'block';

                // Default crop: widest 7:2 region centred
                setDefaultCrop();
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    function setDefaultCrop() {
        if (!sourceImage) return;
        const cw = canvas.width;
        const ch = canvas.height;

        let w, h;
        if (cw / ch >= RATIO) {
            // Image is wider than 7:2 — constrain by height
            h = ch;
            w = Math.min(cw, Math.round(h * RATIO));
        } else {
            // Image is taller — constrain by width
            w = cw;
            h = Math.min(ch, Math.round(w / RATIO));
        }
        const x = Math.round((cw - w) / 2);
        const y = Math.round((ch - h) / 2);

        currentCrop = { x, y, w, h };
        drawBannerOverlay(x, y, w, h);
        setBannerCropInputs(
            Math.round(x / imgScale),
            Math.round(y / imgScale),
            Math.round(w / imgScale),
            Math.round(h / imgScale)
        );
    }

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const src  = e.touches ? e.touches[0] : e;
        return {
            x: Math.min(Math.max(src.clientX - rect.left, 0), canvas.width),
            y: Math.min(Math.max(src.clientY - rect.top,  0), canvas.height),
        };
    }

    function onStart(e) {
        e.preventDefault();
        const pos = getPos(e);
        if (currentCrop.w > 0 &&
            pos.x >= currentCrop.x && pos.x <= currentCrop.x + currentCrop.w &&
            pos.y >= currentCrop.y && pos.y <= currentCrop.y + currentCrop.h) {
            cropState.mode   = 'move';
            cropState.moveOX = pos.x - currentCrop.x;
            cropState.moveOY = pos.y - currentCrop.y;
        } else {
            cropState.mode   = 'draw';
            cropState.startX = pos.x;
            cropState.startY = pos.y;
        }
        cropState.isDragging = true;
    }

    function onMove(e) {
        if (!sourceImage) return;
        const pos = getPos(e);

        if (!cropState.isDragging) {
            const inside = currentCrop.w > 0 &&
                pos.x >= currentCrop.x && pos.x <= currentCrop.x + currentCrop.w &&
                pos.y >= currentCrop.y && pos.y <= currentCrop.y + currentCrop.h;
            canvas.style.cursor = inside ? 'move' : 'crosshair';
            return;
        }
        e.preventDefault();

        if (cropState.mode === 'move') {
            let nx = pos.x - cropState.moveOX;
            let ny = pos.y - cropState.moveOY;
            nx = Math.min(Math.max(nx, 0), canvas.width  - currentCrop.w);
            ny = Math.min(Math.max(ny, 0), canvas.height - currentCrop.h);
            currentCrop.x = nx;
            currentCrop.y = ny;
            drawBannerOverlay(nx, ny, currentCrop.w, currentCrop.h);
            setBannerCropInputs(
                Math.round(nx / imgScale),
                Math.round(ny / imgScale),
                Math.round(currentCrop.w / imgScale),
                Math.round(currentCrop.h / imgScale)
            );
        } else {
            // Draw new crop constrained to 7:2 ratio
            const rawW = Math.abs(pos.x - cropState.startX);
            const rawH = Math.abs(pos.y - cropState.startY);
            let w = rawW;
            let h = Math.round(w / RATIO);
            if (h > rawH && rawH > 0) {
                h = rawH;
                w = Math.round(h * RATIO);
            }
            w = Math.min(w, canvas.width);
            h = Math.min(h, canvas.height);

            const x = Math.min(Math.max(cropState.startX, 0), canvas.width  - w);
            const y = Math.min(Math.max(cropState.startY, 0), canvas.height - h);

            currentCrop = { x, y, w, h };
            drawBannerOverlay(x, y, w, h);
            setBannerCropInputs(
                Math.round(x / imgScale),
                Math.round(y / imgScale),
                Math.round(w / imgScale),
                Math.round(h / imgScale)
            );
        }
    }

    function onEnd() { cropState.isDragging = false; }

    canvas.addEventListener('mousedown',  onStart);
    canvas.addEventListener('mousemove',  onMove);
    canvas.addEventListener('mouseup',    onEnd);
    canvas.addEventListener('touchstart', onStart, { passive: false });
    canvas.addEventListener('touchmove',  onMove,  { passive: false });
    canvas.addEventListener('touchend',   onEnd);

    resetBtn && resetBtn.addEventListener('click', setDefaultCrop);

    function drawBannerOverlay(x, y, w, h) {
        if (!sourceImage) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(sourceImage, 0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'rgba(0,0,0,0.52)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        if (w > 0 && h > 0) {
            ctx.clearRect(x, y, w, h);
            ctx.drawImage(
                sourceImage,
                x / imgScale, y / imgScale, w / imgScale, h / imgScale,
                x, y, w, h
            );
            ctx.strokeStyle = '#e94560';
            ctx.lineWidth   = 2;
            ctx.strokeRect(x, y, w, h);
        }
    }

    function setBannerCropInputs(x, y, w, h) {
        document.getElementById('banner-crop-x').value = x;
        document.getElementById('banner-crop-y').value = y;
        document.getElementById('banner-crop-w').value = w;
        document.getElementById('banner-crop-h').value = h;
        if (infoEl) infoEl.textContent = w + ' × ' + h + ' px (original)';
    }
})();

// ── Banner overlay position/size editor (admin/settings.php) ─────────────────

(function initOverlayEditor() {
    const preview      = document.getElementById('overlay-preview');
    const handle       = document.getElementById('overlay-handle');
    const xInput       = document.getElementById('overlay-x-input');
    const yInput       = document.getElementById('overlay-y-input');
    const sizeInput    = document.getElementById('overlay-size-input');
    const sizeRange    = document.getElementById('overlay-size-range');
    const sizeLabel    = document.getElementById('overlay-size-label');
    const colorInput   = document.getElementById('overlay-color-input');
    const fontSelect   = document.getElementById('overlay-font-select');
    const shadowSelect = document.getElementById('overlay-shadow-select');

    if (!preview || !handle) return;

    // CSS font-family stacks (must match admin/settings.php and includes/header.php)
    const fontMap = {
        system:  'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif',
        serif:   'Georgia,"Times New Roman",Times,serif',
        mono:    '"Courier New",Courier,monospace',
        impact:  'Impact,Haettenschweiler,"Arial Narrow Bold",sans-serif',
    };

    // CSS text-shadow presets (must match admin/settings.php and includes/header.php)
    const shadowMap = {
        none:   'none',
        light:  '0 1px 4px rgba(0,0,0,.5)',
        medium: '0 2px 8px rgba(0,0,0,.7)',
        heavy:  '0 3px 12px rgba(0,0,0,.9)',
    };

    let isDragging = false;
    let startMX = 0, startMY = 0;
    let startPX = 0, startPY = 0;   // % at drag start

    handle.addEventListener('mousedown', startDrag);
    handle.addEventListener('touchstart', startDrag, { passive: false });

    function startDrag(e) {
        e.preventDefault();
        isDragging = true;
        const src = e.touches ? e.touches[0] : e;
        startMX = src.clientX;
        startMY = src.clientY;
        startPX = parseFloat(xInput ? xInput.value : handle.style.left) || 50;
        startPY = parseFloat(yInput ? yInput.value : handle.style.top)  || 50;
    }

    document.addEventListener('mousemove', onDrag);
    document.addEventListener('touchmove', onDrag, { passive: false });

    function onDrag(e) {
        if (!isDragging) return;
        e.preventDefault();
        const src = e.touches ? e.touches[0] : e;
        const rect = preview.getBoundingClientRect();
        const dx = src.clientX - startMX;
        const dy = src.clientY - startMY;
        const newX = Math.min(Math.max(startPX + (dx / rect.width)  * 100, 0), 100);
        const newY = Math.min(Math.max(startPY + (dy / rect.height) * 100, 0), 100);

        handle.style.left = newX + '%';
        handle.style.top  = newY + '%';
        if (xInput) xInput.value = newX.toFixed(2);
        if (yInput) yInput.value = newY.toFixed(2);
    }

    document.addEventListener('mouseup',  endDrag);
    document.addEventListener('touchend', endDrag);
    function endDrag() { isDragging = false; }

    // Font-size range slider
    if (sizeRange) {
        sizeRange.addEventListener('input', () => {
            const v = parseFloat(sizeRange.value);
            handle.style.fontSize = v + 'rem';
            if (sizeInput) sizeInput.value = v.toFixed(2);
            if (sizeLabel) sizeLabel.textContent = '— ' + v.toFixed(1) + 'rem';
        });
        // Initialise label
        if (sizeLabel) sizeLabel.textContent = '— ' + parseFloat(sizeRange.value).toFixed(1) + 'rem';
    }

    // Text colour picker
    if (colorInput) {
        colorInput.addEventListener('input', () => {
            handle.style.color = colorInput.value;
        });
    }

    // Font family select — read CSS family from option's data-css-family attribute
    if (fontSelect) {
        fontSelect.addEventListener('change', () => {
            const opt = fontSelect.options[fontSelect.selectedIndex];
            handle.style.fontFamily = (opt && opt.dataset.cssFamily) || fontMap[fontSelect.value] || fontMap.system;
        });
    }

    // Drop shadow select
    if (shadowSelect) {
        shadowSelect.addEventListener('change', () => {
            handle.style.textShadow = shadowMap[shadowSelect.value] || shadowMap.medium;
        });
    }
})();

// ── Info modals (Welcome & How it Works) ─────────────────────────────────────

(function initInfoModals() {
    function openInfoModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeInfoModal(modal) {
        modal.style.display = 'none';
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    document.addEventListener('click', (e) => {
        // Open via data-modal trigger
        const trigger = e.target.closest('[data-modal]');
        if (trigger) {
            openInfoModal(trigger.dataset.modal);
            return;
        }
        // Close button inside modal
        const closeBtn = e.target.closest('.info-modal-close');
        if (closeBtn) {
            const modal = closeBtn.closest('.info-modal');
            if (modal) closeInfoModal(modal);
            return;
        }
        // Backdrop click
        if (e.target.classList.contains('info-modal')) {
            closeInfoModal(e.target);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const openModal = document.querySelector('.info-modal.is-open');
        if (openModal) closeInfoModal(openModal);
    });
})();

// ── Theme swatch selection ────────────────────────────────────────────────────

(function () {
    const swatches = document.querySelectorAll('.theme-swatch');
    const input    = document.getElementById('site-theme-input');
    const form     = document.getElementById('theme-form');

    if (!swatches.length || !input || !form) return;

    const ACTIVE_BORDER   = '#e94560';
    const ACTIVE_SHADOW   = '0 0 0 3px rgba(233,69,96,.35)';
    const INACTIVE_BORDER = 'rgba(255,255,255,.15)';

    /* Hide the manual save button – selection now auto-saves */
    const saveBtn = form.querySelector('button[type="submit"]');
    if (saveBtn) { saveBtn.style.display = 'none'; }

    function selectSwatch(sw) {
        /* Skip if this swatch is already the active theme */
        if (sw.classList.contains('theme-swatch--active')) { return; }

        swatches.forEach((s) => {
            const isActive = s === sw;
            s.setAttribute('aria-checked', isActive ? 'true' : 'false');
            s.style.borderColor = isActive ? ACTIVE_BORDER : INACTIVE_BORDER;
            s.style.boxShadow   = isActive ? ACTIVE_SHADOW : '';
            s.classList.toggle('theme-swatch--active', isActive);
            const label = s.querySelector('div:last-child');
            if (label) { label.textContent = isActive ? '\u2713 Active' : '\u00A0'; }
        });
        input.value = sw.dataset.themeSlug;
        form.submit();
    }

    swatches.forEach((sw) => {
        sw.addEventListener('click', () => selectSwatch(sw));
        sw.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectSwatch(sw);
            }
        });
    });
}());

// ── Back-to-top button ────────────────────────────────────────────────────────
(function () {
    const btn = document.getElementById('back-to-top');
    if (!btn) return;

    let ticking = false;

    function onScroll() {
        if (window.scrollY > 300) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
        ticking = false;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(onScroll);
            ticking = true;
        }
    }, { passive: true });

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Handle initial state (e.g. page restored with scroll position)
    onScroll();
}());

// ── Load More wall posts ──────────────────────────────────────────────────────

(function () {
    'use strict';

    const wrap    = document.getElementById('load-more-wrap');
    const btn     = document.getElementById('load-more-btn');
    const feed    = document.getElementById('post-feed');

    if (!wrap || !btn || !feed) return;

    // Hide the button immediately if there are no more posts to load
    if (feed.dataset.hasMore !== '1') {
        wrap.classList.add('hidden');
        return;
    }

    // Number of posts loaded per batch (must match PHP $limit in load_posts.php)
    const BATCH_SIZE = parseInt(feed.dataset.offset || '10', 10);
    // Track the running loaded-offset on the element so initMoveMediaModal can
    // read it on form-submit and restore the same state after a redirect.
    // (Restore logic may overwrite this asynchronously before the first click.)
    feed.dataset.loadedOffset = feed.dataset.loadedOffset || String(BATCH_SIZE);
    let loading  = false;
    let sentinel = null; // IntersectionObserver watching the last post

    /**
     * Show the "Load More" button only when the last post in the feed
     * enters the viewport.  This replaces any previous sentinel observer.
     */
    function watchLastPost() {
        if (sentinel) {
            sentinel.disconnect();
            sentinel = null;
        }

        const posts = feed.querySelectorAll('.post-item');
        if (!posts.length) return;
        const lastPost = posts[posts.length - 1];

        if (!('IntersectionObserver' in window)) {
            // Fallback for very old browsers: always show the button
            wrap.classList.remove('hidden');
            return;
        }

        sentinel = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        wrap.classList.remove('hidden');
                    } else {
                        wrap.classList.add('hidden');
                    }
                });
            },
            { rootMargin: '0px', threshold: 0 }
        );
        sentinel.observe(lastPost);
    }

    // Start hidden; the sentinel will reveal the button as needed
    wrap.classList.add('hidden');
    watchLastPost();

    btn.addEventListener('click', async function () {
        if (loading) return;
        loading = true;
        btn.disabled    = true;
        btn.textContent = 'Loading\u2026';

        const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';

        try {
            const offset = parseInt(feed.dataset.loadedOffset, 10);
            const resp   = await fetch(
                baseUrl + '/modules/wall/load_posts.php?offset=' + encodeURIComponent(offset),
                { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const result = await resp.json();

            if (result.ok) {
                // Remember how many post items exist before inserting new ones
                const countBefore = feed.querySelectorAll('.post-item').length;

                feed.insertAdjacentHTML('beforeend', result.html);
                feed.dataset.loadedOffset = String(offset + BATCH_SIZE);

                // Initialise lazy images and lightbox triggers in the new posts only
                const allPosts  = feed.querySelectorAll('.post-item');
                const newPosts  = Array.from(allPosts).slice(countBefore);
                const lazyObs   = typeof window.lazyObserveImages === 'function' ? window.lazyObserveImages   : null;
                const lbBindNew = typeof window.lightboxBindNew    === 'function' ? window.lightboxBindNew    : null;
                newPosts.forEach(post => {
                    if (lazyObs)   lazyObs(post);
                    if (lbBindNew) lbBindNew(post);
                });

                if (!result.has_more) {
                    wrap.classList.add('hidden');
                    if (sentinel) {
                        sentinel.disconnect();
                        sentinel = null;
                    }
                } else {
                    // Update the sentinel to watch the new last post
                    watchLastPost();
                }
            } else {
                alert('Could not load more posts. Please try again.');
            }
        } catch (err) {
            console.error('Load more failed:', err);
            alert('Failed to load more posts. Please try again.');
        } finally {
            loading         = false;
            btn.disabled    = false;
            btn.textContent = 'Load More';
        }
    });
}());

// ── Load More profile posts ───────────────────────────────────────────────────

(function () {
    'use strict';

    const wrap = document.getElementById('profile-load-more-wrap');
    const btn  = document.getElementById('profile-load-more-btn');
    const feed = document.getElementById('profile-post-feed');

    if (!wrap || !btn || !feed) return;

    // Hide the button immediately if there are no more posts to load
    if (feed.dataset.hasMore !== '1') {
        wrap.classList.add('hidden');
        return;
    }

    const BATCH_SIZE = parseInt(feed.dataset.offset || '10', 10);
    const profileId  = feed.dataset.profileId || '';
    // Track the running loaded-offset on the element so initMoveMediaModal can
    // read it on form-submit and restore the same state after a redirect.
    feed.dataset.loadedOffset = feed.dataset.loadedOffset || String(BATCH_SIZE);
    let loading  = false;
    let sentinel = null;

    function watchLastPost() {
        if (sentinel) {
            sentinel.disconnect();
            sentinel = null;
        }

        const posts = feed.querySelectorAll('.post-item');
        if (!posts.length) return;
        const lastPost = posts[posts.length - 1];

        if (!('IntersectionObserver' in window)) {
            wrap.classList.remove('hidden');
            return;
        }

        sentinel = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        wrap.classList.remove('hidden');
                    } else {
                        wrap.classList.add('hidden');
                    }
                });
            },
            { rootMargin: '0px', threshold: 0 }
        );
        sentinel.observe(lastPost);
    }

    wrap.classList.add('hidden');
    watchLastPost();

    btn.addEventListener('click', async function () {
        if (loading) return;
        loading = true;
        btn.disabled    = true;
        btn.textContent = 'Loading\u2026';

        const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';

        try {
            const offset = parseInt(feed.dataset.loadedOffset, 10);
            const resp   = await fetch(
                baseUrl + '/modules/wall/load_profile_posts.php'
                    + '?user_id=' + encodeURIComponent(profileId)
                    + '&offset='  + encodeURIComponent(offset),
                { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const result = await resp.json();

            if (result.ok) {
                const countBefore = feed.querySelectorAll('.post-item').length;

                feed.insertAdjacentHTML('beforeend', result.html);
                feed.dataset.loadedOffset = String(offset + BATCH_SIZE);

                const allPosts  = feed.querySelectorAll('.post-item');
                const newPosts  = Array.from(allPosts).slice(countBefore);
                const lazyObs   = typeof window.lazyObserveImages === 'function' ? window.lazyObserveImages   : null;
                const lbBindNew = typeof window.lightboxBindNew    === 'function' ? window.lightboxBindNew    : null;
                newPosts.forEach(post => {
                    if (lazyObs)   lazyObs(post);
                    if (lbBindNew) lbBindNew(post);
                });

                if (!result.has_more) {
                    wrap.classList.add('hidden');
                    if (sentinel) {
                        sentinel.disconnect();
                        sentinel = null;
                    }
                } else {
                    watchLastPost();
                }
            } else {
                alert('Could not load more posts. Please try again.');
            }
        } catch (err) {
            console.error('Load more profile posts failed:', err);
            alert('Failed to load more posts. Please try again.');
        } finally {
            loading         = false;
            btn.disabled    = false;
            btn.textContent = 'Load More';
        }
    });
}());

// ── Toggle visibility via data-toggle ────────────────────────────────────────
// <button data-toggle="elementId"> toggles the 'hidden' class on the target element.
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-toggle]');
    if (!btn) return;
    const target = document.getElementById(btn.dataset.toggle);
    if (target) target.classList.toggle('hidden');
});

// ── Confirmation dialogs via data-confirm ─────────────────────────────────────
// <button data-confirm="message"> shows a confirm dialog before form submission.
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    if (!confirm(btn.dataset.confirm)) {
        e.preventDefault();
    }
});

// ── Clipboard copy via data-copy ──────────────────────────────────────────────
// <button data-copy="text"> copies text to the clipboard.
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    navigator.clipboard.writeText(btn.dataset.copy).catch(() => {});
});

// ── Chat widget trigger via data-chat-user-id ─────────────────────────────────
// <button data-chat-user-id="…" data-chat-username="…" data-chat-avatar="…">
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-chat-user-id]');
    if (!btn) return;
    if (typeof ChatWidget !== 'undefined') {
        ChatWidget.startChat(
            parseInt(btn.dataset.chatUserId, 10),
            btn.dataset.chatUsername || '',
            btn.dataset.chatAvatar   || ''
        );
    }
});

// ── Smiley Picker — auto-initialise ──────────────────────────────────────────

(function () {
    // Wall post composer
    const postContent = document.getElementById('post-content');
    if (postContent) {
        const composerActions = document.querySelector('.composer-actions');
        if (composerActions) {
            composerActions.insertBefore(
                createSmileyPicker(postContent),
                composerActions.firstChild
            );
        }
    }

    // Shoutbox input
    const shoutInput = document.getElementById('shout-input');
    if (shoutInput) {
        const shoutboxForm = document.getElementById('shoutbox-form');
        if (shoutboxForm) {
            shoutInput.insertAdjacentElement('afterend', createSmileyPicker(shoutInput));
        }
    }

    // Private messages compose textarea
    const composeBody = document.getElementById('compose-body');
    if (composeBody) {
        const formGroup = composeBody.closest('.form-group');
        if (formGroup) {
            formGroup.appendChild(createSmileyPicker(composeBody));
        }
    }

    // Wall comment forms
    document.querySelectorAll('.comment-form input[name="content"]').forEach((input) => {
        input.insertAdjacentElement('afterend', createSmileyPicker(input));
    });

    // Blog comment forms
    document.querySelectorAll('.blog-comment-form input[name="content"]').forEach((input) => {
        input.insertAdjacentElement('afterend', createSmileyPicker(input));
    });
}());

// ── Comment image attachment system ──────────────────────────────────────────

/**
 * WeakMap storing the selected image_media_id for each comment form element.
 * Key: form element, Value: media_id (int) or 0.
 */
const commentImageState = new WeakMap();

/**
 * Re-initialise lightbox triggers after dynamically inserting HTML into the DOM.
 * Calls the exported reinit function from lightbox.js if available.
 */
function reinitLightboxTriggers() {
    if (typeof window.reinitLightbox === 'function') {
        window.reinitLightbox();
    }
}

/**
 * Show a small preview of the selected image inside the comment form.
 *
 * @param {HTMLFormElement} form      The comment form element
 * @param {string}          thumbUrl  URL of the thumbnail to display
 * @param {number}          mediaId   media_id of the selected image
 */
function setCommentImagePreview(form, thumbUrl, mediaId) {
    commentImageState.set(form, mediaId);
    const previewEl = form.querySelector('.comment-image-preview');
    if (!previewEl) return;
    previewEl.innerHTML =
        `<img src="${escapeHtml(thumbUrl)}" alt="Attachment preview" class="comment-attachment-preview-img">` +
        `<button type="button" class="comment-attachment-remove btn btn-xs btn-danger" title="Remove image">✕</button>`;
    previewEl.style.display = 'flex';
}

/**
 * Clear the comment image attachment for the given form.
 *
 * @param {HTMLFormElement} form
 */
function clearCommentImagePreview(form) {
    commentImageState.set(form, 0);
    const previewEl = form.querySelector('.comment-image-preview');
    if (!previewEl) return;
    previewEl.innerHTML = '';
    previewEl.style.display = 'none';
}

// ── Album picker modal logic ──────────────────────────────────────────────────

(function initCommentImagePicker() {
    const modal          = document.getElementById('comment-image-picker-modal');
    const cancelBtn      = document.getElementById('comment-picker-cancel');
    const confirmBtn     = document.getElementById('comment-picker-confirm');
    const tabBtns        = modal ? modal.querySelectorAll('.comment-picker-tab') : [];
    const uploadPanel    = document.getElementById('comment-picker-upload-panel');
    const albumPanel     = document.getElementById('comment-picker-album-panel');
    const dropZone       = document.getElementById('comment-img-drop-zone');
    const fileInput      = document.getElementById('comment-img-file-input');
    const uploadPreview  = document.getElementById('comment-img-upload-preview');
    const uploadThumb    = document.getElementById('comment-img-upload-thumb');
    const uploadRemove   = document.getElementById('comment-img-upload-remove');
    const albumList      = document.getElementById('comment-picker-album-list');
    const imagesWrap     = document.getElementById('comment-picker-images-wrap');
    const backBtn        = document.getElementById('comment-picker-back-btn');
    const imageGrid      = document.getElementById('comment-picker-image-grid');

    if (!modal) return;

    const baseUrl = () => document.querySelector('meta[name="site-url"]')?.content || '';

    // Track which form opened the picker and the current selection
    let activeForm    = null;
    let selectedId    = 0;
    let selectedThumb = '';
    let selectedLarge = '';
    let uploadedFile  = null;  // File object pending upload
    let albumsLoaded  = false;

    function openPicker(form) {
        activeForm = form;
        selectedId = 0;
        selectedThumb = '';
        selectedLarge = '';
        uploadedFile  = null;
        confirmBtn.disabled = true;
        resetUploadPanel();
        modal.style.display = 'flex';
    }

    function closePicker() {
        modal.style.display = 'none';
        activeForm = null;
    }

    function resetUploadPanel() {
        if (uploadPreview) uploadPreview.style.display = 'none';
        if (uploadThumb)   uploadThumb.src = '';
        if (dropZone)      dropZone.style.display = '';
        uploadedFile = null;
    }

    // Close on cancel
    if (cancelBtn) cancelBtn.addEventListener('click', closePicker);

    // Close on overlay click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closePicker();
    });

    // Tab switching
    tabBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const tab = btn.dataset.tab;
            if (uploadPanel) uploadPanel.style.display = tab === 'upload' ? '' : 'none';
            if (albumPanel)  albumPanel.style.display  = tab === 'album'  ? '' : 'none';

            if (tab === 'album' && !albumsLoaded) {
                loadAlbums();
            }
        });
    });

    // ── Upload tab: file input & drag-drop ────────────────────────────────────

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (file) handleFileSelected(file);
            fileInput.value = '';
        });
    }

    if (dropZone) {
        let dDepth = 0;
        dropZone.addEventListener('dragenter', (e) => {
            if (!e.dataTransfer.types.includes('Files')) return;
            e.preventDefault();
            dDepth++;
            dropZone.classList.add('drag-over');
        });
        dropZone.addEventListener('dragover', (e) => {
            if (!e.dataTransfer.types.includes('Files')) return;
            e.preventDefault();
        });
        dropZone.addEventListener('dragleave', () => {
            dDepth--;
            if (dDepth <= 0) { dDepth = 0; dropZone.classList.remove('drag-over'); }
        });
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dDepth = 0;
            dropZone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) handleFileSelected(file);
        });
    }

    function handleFileSelected(file) {
        if (!file.type.startsWith('image/')) {
            alert('Please choose an image file (JPEG, PNG, GIF, or WebP).');
            return;
        }
        uploadedFile = file;
        // Show preview from local data
        const reader = new FileReader();
        reader.onload = (ev) => {
            if (uploadThumb) uploadThumb.src = ev.target.result;
            if (uploadPreview) uploadPreview.style.display = 'flex';
            if (dropZone) dropZone.style.display = 'none';
        };
        reader.readAsDataURL(file);
        // Select as pending (will upload on confirm)
        selectedId    = -1;   // -1 = pending local file
        selectedThumb = '';
        selectedLarge = '';
        confirmBtn.disabled = false;
    }

    if (uploadRemove) {
        uploadRemove.addEventListener('click', () => {
            resetUploadPanel();
            selectedId = 0;
            confirmBtn.disabled = true;
        });
    }

    // ── Album tab ─────────────────────────────────────────────────────────────

    async function loadAlbums() {
        albumsLoaded = true;
        if (!albumList) return;
        albumList.innerHTML = '<p class="comment-picker-loading">Loading albums…</p>';
        try {
            const resp = await fetch(baseUrl() + '/modules/gallery/get_user_album_images.php', {
                credentials: 'same-origin',
            });
            const data = await resp.json();
            if (!data.ok || !data.albums || data.albums.length === 0) {
                albumList.innerHTML = '<p class="comment-picker-empty">No albums with images found.</p>';
                return;
            }
            albumList.innerHTML = '';
            data.albums.forEach((album) => {
                const item = document.createElement('div');
                item.className = 'comment-picker-album-item';
                item.innerHTML =
                    `<div class="comment-picker-album-cover">` +
                        (album.cover_url
                            ? `<img src="${escapeHtml(album.cover_url)}" alt="" loading="lazy">`
                            : `<span>📁</span>`) +
                    `</div>` +
                    `<div class="comment-picker-album-title">${escapeHtml(album.title)}</div>` +
                    `<div class="comment-picker-album-count">${album.image_count} image${album.image_count !== 1 ? 's' : ''}</div>`;
                item.addEventListener('click', () => loadAlbumImages(album.id));
                albumList.appendChild(item);
            });
        } catch (err) {
            albumList.innerHTML = '<p class="comment-picker-empty">Could not load albums.</p>';
        }
    }

    async function loadAlbumImages(albumId) {
        if (!albumList || !imagesWrap || !imageGrid) return;
        albumList.style.display = 'none';
        imagesWrap.style.display = '';
        imageGrid.innerHTML = '<p class="comment-picker-loading">Loading images…</p>';

        try {
            const resp = await fetch(
                baseUrl() + '/modules/gallery/get_user_album_images.php?album_id=' + encodeURIComponent(albumId),
                { credentials: 'same-origin' }
            );
            const data = await resp.json();
            if (!data.ok || !data.images || data.images.length === 0) {
                imageGrid.innerHTML = '<p class="comment-picker-empty">No images in this album.</p>';
                return;
            }
            imageGrid.innerHTML = '';
            data.images.forEach((img) => {
                const item = document.createElement('div');
                item.className = 'comment-picker-img-item';
                const imgEl = document.createElement('img');
                imgEl.src     = img.thumb_url;
                imgEl.alt     = '';
                imgEl.loading = 'lazy';
                item.appendChild(imgEl);
                item.addEventListener('click', () => {
                    imageGrid.querySelectorAll('.comment-picker-img-item').forEach(el => el.classList.remove('selected'));
                    item.classList.add('selected');
                    selectedId    = img.media_id;
                    selectedThumb = img.thumb_url;
                    selectedLarge = img.large_url;
                    confirmBtn.disabled = false;
                });
                imageGrid.appendChild(item);
            });
        } catch (err) {
            imageGrid.innerHTML = '<p class="comment-picker-empty">Could not load images.</p>';
        }
    }

    if (backBtn) {
        backBtn.addEventListener('click', () => {
            if (imagesWrap) imagesWrap.style.display = 'none';
            if (albumList)  albumList.style.display  = '';
            selectedId = 0;
            confirmBtn.disabled = true;
        });
    }

    // ── Confirm selection ─────────────────────────────────────────────────────

    if (confirmBtn) {
        confirmBtn.addEventListener('click', async () => {
            if (!activeForm) return;

            if (selectedId === -1 && uploadedFile) {
                // Upload new file to server
                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Uploading…';

                const fd = new FormData();
                fd.append('csrf_token', getCsrfToken());
                fd.append('image', uploadedFile);

                try {
                    const resp = await fetch(baseUrl() + '/modules/wall/upload_comment_image.php', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin',
                    });
                    const data = await resp.json();
                    if (data.ok) {
                        setCommentImagePreview(activeForm, data.thumb_url, data.media_id);
                    } else {
                        alert('Upload failed: ' + (data.error || 'Unknown error'));
                    }
                } catch (err) {
                    alert('Upload failed. Please try again.');
                } finally {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Attach Image';
                }
            } else if (selectedId > 0) {
                // Use existing media from album
                setCommentImagePreview(activeForm, selectedThumb, selectedId);
            }

            closePicker();
        });
    }

    // ── 📷 Button click on any comment form ──────────────────────────────────

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.comment-attach-image-btn');
        if (!btn) return;
        e.preventDefault();
        const form = btn.closest('.comment-form, .blog-comment-form');
        if (form) openPicker(form);
    });

    // ── Remove attachment button ──────────────────────────────────────────────

    document.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.comment-attachment-remove');
        if (!removeBtn) return;
        const form = removeBtn.closest('.comment-form, .blog-comment-form');
        if (form) clearCommentImagePreview(form);
    });

    // ── Drag-and-drop directly onto comment form ──────────────────────────────

    document.addEventListener('dragenter', (e) => {
        if (!e.dataTransfer.types.includes('Files')) return;
        const form = e.target.closest('.comment-form, .blog-comment-form');
        if (!form) return;
        e.preventDefault();
        form.classList.add('comment-form-drag-over');
    }, true);

    document.addEventListener('dragover', (e) => {
        const form = e.target.closest('.comment-form, .blog-comment-form');
        if (!form) return;
        if (!e.dataTransfer.types.includes('Files')) return;
        e.preventDefault();
    }, true);

    document.addEventListener('dragleave', (e) => {
        const form = e.target.closest('.comment-form, .blog-comment-form');
        if (!form) return;
        if (!form.contains(e.relatedTarget)) {
            form.classList.remove('comment-form-drag-over');
        }
    }, true);

    document.addEventListener('drop', async (e) => {
        const form = e.target.closest('.comment-form, .blog-comment-form');
        if (!form) return;
        e.preventDefault();
        form.classList.remove('comment-form-drag-over');

        const file = e.dataTransfer.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        // Upload immediately and show preview
        const uploadIndicator = form.querySelector('.comment-image-preview');
        if (uploadIndicator) {
            uploadIndicator.innerHTML = '<span class="comment-attachment-uploading">Uploading…</span>';
            uploadIndicator.style.display = 'flex';
        }

        const fd = new FormData();
        fd.append('csrf_token', getCsrfToken());
        fd.append('image', file);

        try {
            const resp = await fetch(baseUrl() + '/modules/wall/upload_comment_image.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            });
            const data = await resp.json();
            if (data.ok) {
                setCommentImagePreview(form, data.thumb_url, data.media_id);
            } else {
                if (uploadIndicator) { uploadIndicator.innerHTML = ''; uploadIndicator.style.display = 'none'; }
                alert('Upload failed: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            if (uploadIndicator) { uploadIndicator.innerHTML = ''; uploadIndicator.style.display = 'none'; }
            alert('Upload failed. Please try again.');
        }
    }, true);
}());
