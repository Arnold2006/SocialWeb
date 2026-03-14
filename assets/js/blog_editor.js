/**
 * blog_editor.js — Rich-text blog editor
 *
 * Provides a contenteditable-based editor with:
 *  - Formatting toolbar (bold, italic, underline, strikethrough, headings, lists, blockquote)
 *  - Link insertion
 *  - Image upload (AJAX to /modules/blog/upload_image.php, EXIF-stripped server-side)
 *  - AJAX save/update to /modules/blog/save_post.php
 *  - AJAX delete to /modules/blog/delete_post.php
 */

'use strict';

(function () {

    // ── DOM refs ─────────────────────────────────────────────────────────────

    const editor      = document.getElementById('blog-editor');
    const toolbar     = document.getElementById('blog-toolbar');
    const titleInput  = document.getElementById('blog-title-input');
    const saveBtn     = document.getElementById('blog-save-btn');
    const cancelBtn   = document.getElementById('blog-cancel-edit');
    const statusEl    = document.getElementById('blog-save-status');
    const postIdInput = document.getElementById('blog-edit-post-id');
    const csrfInput   = document.getElementById('blog-csrf');
    const imageBtn    = document.getElementById('blog-image-btn');
    const imageInput  = document.getElementById('blog-image-input');
    const linkBtn     = document.getElementById('blog-link-btn');
    const headingEl   = document.getElementById('blog-editor-heading');

    if (!editor) return;

    const SITE_URL = (document.querySelector('meta[name="site-url"]') || {}).content || '';

    function getCsrf() {
        return csrfInput ? csrfInput.value : '';
    }

    // ── Placeholder ──────────────────────────────────────────────────────────

    function updatePlaceholder() {
        if (editor.textContent.trim() === '' && editor.innerHTML.replace(/<br\s*\/?>/gi, '').trim() === '') {
            editor.classList.add('blog-editor--empty');
        } else {
            editor.classList.remove('blog-editor--empty');
        }
    }
    editor.addEventListener('input', updatePlaceholder);
    updatePlaceholder();

    // ── Toolbar formatting ────────────────────────────────────────────────────

    toolbar.addEventListener('mousedown', function (e) {
        const btn = e.target.closest('.blog-tb-btn[data-cmd]');
        if (!btn) return;
        e.preventDefault(); // keep editor focus
        const cmd = btn.dataset.cmd;
        const val = btn.dataset.val || null;
        editor.focus();
        // execCommand is deprecated in the HTML spec but remains widely supported
        // across all modern browsers and is the most concise cross-browser approach
        // for a vanilla-JS editor without external dependencies.
        try {
            document.execCommand(cmd, false, val);
        } catch (_) {
            // execCommand not available — ignore
        }
    });

    // ── Link insertion ────────────────────────────────────────────────────────

    linkBtn.addEventListener('click', function () {
        editor.focus();
        const sel = window.getSelection();
        const selectedText = sel ? sel.toString() : '';
        const url = prompt('Enter link URL (https://…):', 'https://');
        if (!url || !url.match(/^https?:\/\//i)) return;
        try {
            if (selectedText) {
                document.execCommand('createLink', false, url);
            } else {
                document.execCommand('insertHTML', false,
                    '<a href="' + escAttr(url) + '" rel="noopener noreferrer nofollow" target="_blank">'
                    + escHtml(url) + '</a>');
            }
        } catch (_) {}
    });

    // ── Image upload ──────────────────────────────────────────────────────────

    imageBtn.addEventListener('click', function () {
        imageInput.click();
    });

    imageInput.addEventListener('change', function () {
        const file = imageInput.files[0];
        if (!file) return;
        uploadImage(file);
        imageInput.value = ''; // reset so same file can be re-selected
    });

    // Allow drag-and-drop of images directly onto the editor
    editor.addEventListener('dragover', function (e) {
        e.preventDefault();
    });

    editor.addEventListener('drop', function (e) {
        const items = e.dataTransfer && e.dataTransfer.items;
        if (!items) return;
        for (let i = 0; i < items.length; i++) {
            if (items[i].kind === 'file' && items[i].type.startsWith('image/')) {
                e.preventDefault();
                uploadImage(items[i].getAsFile());
                return;
            }
        }
    });

    function uploadImage(file) {
        const formData = new FormData();
        formData.append('csrf_token', getCsrf());
        formData.append('image', file);

        setStatus('Uploading image…', 'info');
        imageBtn.disabled = true;

        fetch(SITE_URL + '/modules/blog/upload_image.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            imageBtn.disabled = false;
            if (data.ok) {
                editor.focus();
                // Insert image at cursor position
                document.execCommand('insertHTML', false,
                    '<img src="' + escAttr(data.url) + '" alt="" style="max-width:100%">');
                updatePlaceholder();
                setStatus('Image inserted.', 'success');
                clearStatusAfter(2000);
            } else {
                setStatus('Image upload failed: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(function () {
            imageBtn.disabled = false;
            setStatus('Image upload failed.', 'error');
        });
    }

    // ── Save / Update ─────────────────────────────────────────────────────────

    saveBtn.addEventListener('click', function () {
        const title   = titleInput.value.trim();
        const content = editor.innerHTML.trim();
        const postId  = parseInt(postIdInput.value, 10) || 0;

        if (!title) {
            setStatus('Please enter a title.', 'error');
            titleInput.focus();
            return;
        }
        if (!content || editor.textContent.trim() === '') {
            setStatus('Please write some content.', 'error');
            editor.focus();
            return;
        }

        saveBtn.disabled = true;
        setStatus('Saving…', 'info');

        const body = new URLSearchParams({
            csrf_token: getCsrf(),
            post_id: postId,
            title: title,
            content: content,
        });

        fetch(SITE_URL + '/modules/blog/save_post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            saveBtn.disabled = false;
            if (data.ok) {
                if (postId > 0) {
                    // Update the existing post in the DOM
                    updatePostInDom(postId, title, content);
                    setStatus('Post updated.', 'success');
                    resetEditor();
                } else {
                    // Prepend new post to the list
                    prependNewPost(data.post_id, title, content);
                    setStatus('Post published.', 'success');
                    resetEditor();
                }
                clearStatusAfter(2500);
            } else {
                setStatus(data.error || 'Save failed.', 'error');
            }
        })
        .catch(function () {
            saveBtn.disabled = false;
            setStatus('Save failed. Please try again.', 'error');
        });
    });

    // ── Delete ────────────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.blog-delete-btn');
        if (!btn) return;
        const postId = parseInt(btn.dataset.postId, 10);
        if (!postId) return;
        if (!confirm('Delete this blog post?')) return;

        const body = new URLSearchParams({
            csrf_token: getCsrf(),
            post_id: postId,
        });

        fetch(SITE_URL + '/modules/blog/delete_post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                const article = document.getElementById('blog-post-' + postId);
                if (article) {
                    article.remove();
                }
                // If editing this post, reset editor
                if (parseInt(postIdInput.value, 10) === postId) {
                    resetEditor();
                }
            } else {
                alert('Delete failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function () {
            alert('Delete failed. Please try again.');
        });
    });

    // ── Edit (load post into editor) ─────────────────────────────────────────

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.blog-edit-btn');
        if (!btn) return;
        const postId = parseInt(btn.dataset.postId, 10);
        if (!postId) return;

        const article = document.getElementById('blog-post-' + postId);
        if (!article) return;

        const contentEl = article.querySelector('.blog-post-content');
        const postTitle = btn.dataset.title || '';

        titleInput.value       = postTitle;
        editor.innerHTML       = contentEl ? contentEl.innerHTML : '';
        postIdInput.value      = postId;
        saveBtn.textContent    = 'Update';
        cancelBtn.style.display = '';
        if (headingEl) headingEl.textContent = 'Edit Post';
        updatePlaceholder();

        // Scroll editor into view
        const wrap = document.getElementById('blog-editor-wrap');
        if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
        editor.focus();
    });

    cancelBtn.addEventListener('click', resetEditor);

    // ── Helpers ───────────────────────────────────────────────────────────────

    function resetEditor() {
        titleInput.value        = '';
        editor.innerHTML        = '';
        postIdInput.value       = '0';
        saveBtn.textContent     = 'Publish';
        cancelBtn.style.display = 'none';
        if (headingEl) headingEl.textContent = 'New Post';
        updatePlaceholder();
        setStatus('', '');
    }

    function setStatus(msg, type) {
        if (!statusEl) return;
        statusEl.textContent  = msg;
        statusEl.className    = 'blog-save-status' + (type ? ' blog-save-status--' + type : '');
    }

    function clearStatusAfter(ms) {
        setTimeout(function () { setStatus('', ''); }, ms);
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    function escAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function prependNewPost(postId, title, content) {
        const list = document.getElementById('blog-posts-list');
        if (!list) return;

        const empty = list.querySelector('.empty-state');
        if (empty) empty.remove();

        const now   = new Date();
        const dateStr = now.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

        const article = document.createElement('article');
        article.className = 'blog-post card';
        article.id        = 'blog-post-' + postId;
        article.innerHTML =
            '<header class="blog-post-header">'
            + '<h2 class="blog-post-title">' + escHtml(title) + '</h2>'
            + '<time class="blog-post-date">' + escHtml(dateStr) + '</time>'
            + '</header>'
            + '<div class="blog-post-content">' + content + '</div>'
            + '<footer class="blog-post-footer">'
            + '<button type="button" class="btn btn-secondary btn-xs blog-edit-btn"'
            + ' data-post-id="' + postId + '" data-title="' + escAttr(title) + '">Edit</button>'
            + '<button type="button" class="btn btn-danger btn-xs blog-delete-btn"'
            + ' data-post-id="' + postId + '">Delete</button>'
            + '</footer>';

        list.insertBefore(article, list.firstChild);
    }

    function updatePostInDom(postId, title, content) {
        const article = document.getElementById('blog-post-' + postId);
        if (!article) return;

        const titleEl   = article.querySelector('.blog-post-title');
        const contentEl = article.querySelector('.blog-post-content');
        const editBtn   = article.querySelector('.blog-edit-btn');

        if (titleEl)   titleEl.textContent   = title;
        if (contentEl) contentEl.innerHTML   = content;
        if (editBtn)   editBtn.dataset.title = title;

        // Add (edited) marker
        let editedSpan = article.querySelector('.blog-post-edited');
        if (!editedSpan) {
            const timeEl = article.querySelector('.blog-post-date');
            if (timeEl) {
                editedSpan = document.createElement('span');
                editedSpan.className   = 'blog-post-edited';
                editedSpan.textContent = '(edited)';
                timeEl.appendChild(editedSpan);
            }
        }
    }

})();
