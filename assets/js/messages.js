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
 * messages.js — Client-side logic for the webmail compose form
 *
 * Features:
 *  - Immediate AJAX upload of image attachments via POST /pages/message_upload.php
 *  - Inline thumbnail preview with per-attachment remove button
 *  - Drag-and-drop images onto the compose body textarea
 *  - Accumulates attachment IDs in hidden <input name="attachment_ids[]"> fields
 *  - Quick-reply textarea auto-resize
 */

'use strict';

(function () {

    /* ── Helpers ─────────────────────────────────────────────────── */

    const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = String(str ?? '');
        return d.innerHTML;
    }

    /* ── Compose attachment upload ───────────────────────────────── */

    const composeForm   = document.getElementById('compose-form');
    const csrfInput     = document.getElementById('compose-csrf');
    const attachInput   = document.getElementById('compose-attach-input');
    const attachList    = document.getElementById('attach-preview-list');
    const attachIdsDiv  = document.getElementById('attach-ids-container');
    const attachBtn     = document.getElementById('compose-attach-btn');
    const bodyTextarea  = document.getElementById('compose-body');

    if (composeForm && attachInput) {

        // Clicking the visible button triggers the hidden file input
        if (attachBtn) {
            attachBtn.addEventListener('click', e => {
                e.preventDefault();
                attachInput.click();
            });
        }

        attachInput.addEventListener('change', e => {
            const file = e.target.files[0];
            if (file) uploadAttachment(file);
            e.target.value = '';
        });

        // Drag-and-drop onto the message body textarea
        if (bodyTextarea) {
            bodyTextarea.addEventListener('dragover', e => {
                e.preventDefault();
                bodyTextarea.classList.add('drag-over');
            });
            bodyTextarea.addEventListener('dragleave', () => {
                bodyTextarea.classList.remove('drag-over');
            });
            bodyTextarea.addEventListener('drop', e => {
                e.preventDefault();
                bodyTextarea.classList.remove('drag-over');
                const file = e.dataTransfer.files[0];
                if (file && file.type.startsWith('image/')) uploadAttachment(file);
            });
        }
    }

    async function uploadAttachment(file) {
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowed.includes(file.type)) {
            alert('Only JPG, PNG, WEBP and GIF images are allowed.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            alert('File too large. Maximum size is 10 MB.');
            return;
        }

        const csrf = csrfInput ? csrfInput.value : '';

        // Show a pending placeholder
        const item = document.createElement('div');
        item.className = 'attach-preview-item attach-pending';
        item.innerHTML = '<span class="attach-name">' + esc(file.name) + '</span>'
            + '<span class="attach-status">Uploading…</span>';
        if (attachList) attachList.appendChild(item);

        try {
            const fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('attachment', file);

            const resp = await fetch(baseUrl + '/pages/message_upload.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            });
            const data = await resp.json();

            if (data.ok) {
                // Replace pending item with thumbnail preview
                item.className = 'attach-preview-item';
                item.dataset.attachId = data.id;
                item.innerHTML =
                    '<a href="' + esc(data.url) + '" target="_blank" rel="noopener noreferrer">'
                    + '<img src="' + esc(data.url) + '" alt="" class="attach-thumb" loading="lazy">'
                    + '</a>'
                    + '<span class="attach-name">' + esc(data.name) + '</span>'
                    + '<button type="button" class="attach-remove-btn" aria-label="Remove attachment">'
                    + '&times;</button>';

                // Hidden input carries the ID to PHP
                const hidden = document.createElement('input');
                hidden.type            = 'hidden';
                hidden.name            = 'attachment_ids[]';
                hidden.value           = data.id;
                hidden.dataset.attachId = data.id;
                if (attachIdsDiv) attachIdsDiv.appendChild(hidden);

                // Wire up the remove button
                item.querySelector('.attach-remove-btn').addEventListener('click', () => {
                    item.remove();
                    attachIdsDiv?.querySelector('[data-attach-id="' + data.id + '"]')?.remove();
                });
            } else {
                item.className = 'attach-preview-item attach-error';
                item.innerHTML =
                    '<span class="attach-name">' + esc(file.name) + '</span>'
                    + '<span class="attach-status attach-error-msg">'
                    + esc(data.error || 'Upload failed') + '</span>'
                    + '<button type="button" class="attach-remove-btn" aria-label="Dismiss">&times;</button>';
                item.querySelector('.attach-remove-btn').addEventListener('click', () => item.remove());
            }
        } catch (err) {
            item.className = 'attach-preview-item attach-error';
            item.innerHTML =
                '<span class="attach-name">' + esc(file.name) + '</span>'
                + '<span class="attach-status attach-error-msg">Upload failed</span>'
                + '<button type="button" class="attach-remove-btn" aria-label="Dismiss">&times;</button>';
            item.querySelector('.attach-remove-btn').addEventListener('click', () => item.remove());
        }
    }

    /* ── Enforce required fields only for Send, not for Save Draft ─ */
    if (composeForm) {
        composeForm.querySelectorAll('button[name="action"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const isSend = btn.value === 'send';
                const toSel  = composeForm.querySelector('#compose-to');
                const bodyta = composeForm.querySelector('#compose-body');
                if (toSel)  toSel.required  = isSend;
                if (bodyta) bodyta.required  = isSend;
            });
        });
    }

    /* ── Quick-reply textarea auto-resize ───────────────────────── */
    document.querySelectorAll('.mail-quick-reply-input').forEach(ta => {
        ta.addEventListener('input', () => {
            ta.style.height = 'auto';
            ta.style.height = ta.scrollHeight + 'px';
        });
    });

    /* ── Scroll to latest message when a thread is open ─────────── */
    const mailViewer = document.querySelector('.mail-viewer');
    const mailThread = document.querySelector('.mail-thread-wrap');
    if (mailViewer && mailThread) {
        mailViewer.scrollTop = mailViewer.scrollHeight;
        mailViewer.querySelectorAll('img').forEach(img => {
            if (!img.complete) {
                img.addEventListener('load', () => {
                    mailViewer.scrollTop = mailViewer.scrollHeight;
                }, { once: true });
            }
        });
    }

    /* ── Lightbox integration for attachment thumbnails ─────────── */
    document.querySelectorAll('.mail-attachment-link[data-img-url]').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const url = link.dataset.imgUrl;
            if (url && typeof window.lightboxOpenUrl === 'function') {
                window.lightboxOpenUrl(url);
            } else if (url) {
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        });
    });

})();
