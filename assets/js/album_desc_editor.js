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
 * album_desc_editor.js — Rich-text editor for album descriptions
 *
 * Provides a contenteditable-based editor with:
 *  - Formatting toolbar (bold, italic, underline, strikethrough, headings, lists, blockquote)
 *  - Link insertion
 *  - HTML serialisation into a hidden textarea before form submission
 */

'use strict';

(function () {

    const editor   = document.getElementById('album-desc-editor');
    const toolbar  = document.getElementById('album-desc-toolbar');
    const linkBtn  = document.getElementById('album-desc-link-btn');
    const form     = document.getElementById('album-desc-form');
    const hidden   = document.getElementById('album-desc-hidden');

    if (!editor || !toolbar || !form || !hidden) return;

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
        try {
            document.execCommand(cmd, false, val);
        } catch (_) {
            // execCommand not available — ignore
        }
    });

    // ── Link insertion ────────────────────────────────────────────────────────

    function escAttr(s) {
        return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function escHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    if (linkBtn) {
        linkBtn.addEventListener('click', function () {
            editor.focus();
            const sel = window.getSelection();
            const selectedText = sel ? sel.toString() : '';
            const url = prompt('Enter link URL (https://\u2026):', 'https://');
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
    }

    // ── Serialize HTML into hidden field before form submit ───────────────────

    form.addEventListener('submit', function () {
        hidden.value = editor.innerHTML.trim();
    });

})();
