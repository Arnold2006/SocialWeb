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
 * chat.js — Oxwall-style real-time chat widget
 *
 * Features:
 *  - Fixed contact sidebar (bottom-right) listing ALL site members
 *  - "Find Contact" search to filter the contact list
 *  - Multiple simultaneous floating chat windows stacked to the left
 *  - Per-window message polling (3 s) and global unread-badge poll (15 s)
 *  - Text messaging and drag-and-drop / click-to-upload image sharing
 *  - Load older messages on scroll-up (50 at a time)
 *  - Auto-scroll to newest message
 */

'use strict';

(function () {

    /* ── Constants ───────────────────────────────────────────────────────── */

    const POLL_MSG_MS   = 3000;   // per-window message poll interval
    const POLL_BADGE_MS = 15000;  // background badge-refresh interval

    /* ── Module state ────────────────────────────────────────────────────── */

    /** Map<userId:number, WindowState> */
    const openWindows = new Map();

    let csrfToken     = '';
    let siteUrl       = '';
    let sidebarOpen   = false;
    let badgePollTimer = null;
    let searchTimer    = null;

    /**
     * WindowState shape:
     * {
     *   userId        : number,
     *   username      : string,
     *   avatarUrl     : string,
     *   convId        : number|null,
     *   newestMsgId   : number,
     *   oldestMsgId   : number|null,
     *   isLoadingOlder: boolean,
     *   pollTimer     : number|null,
     *   el            : Element,
     *   elMessages    : Element,
     *   elInput       : Element,
     *   elImageInput  : Element,
     * }
     */

    /* ── DOM helpers ─────────────────────────────────────────────────────── */

    /** Escape a string for safe insertion into HTML */
    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str ?? '')));
        return d.innerHTML;
    }

    /** Convert http/https URLs in raw text to safe clickable links, HTML-escaping all content */
    function linkify(rawStr) {
        return String(rawStr ?? '').split(/(\bhttps?:\/\/\S+)/g).map(function (part, i) {
            if (i % 2 === 0) return esc(part);
            const url        = part.replace(/[.,;:!?)'"]+$/, '');
            const escapedUrl = esc(url);
            return '<a href="' + escapedUrl + '" rel="noopener noreferrer nofollow" target="_blank">'
                + escapedUrl + '</a>'
                + esc(part.slice(url.length));
        }).join('');
    }

    /** POST FormData to a URL, return parsed JSON */
    async function apiPost(url, fields) {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        for (const [k, v] of Object.entries(fields)) fd.append(k, v);
        const resp = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
        return resp.json();
    }

    function scrollToBottom(elMessages) {
        elMessages.scrollTop = elMessages.scrollHeight;
    }

    /* ── Message bubble builder ──────────────────────────────────────────── */

    function buildMessageEl(msg) {
        const div = document.createElement('div');
        div.className = 'chat-msg ' + (msg.is_mine ? 'chat-msg--mine' : 'chat-msg--theirs');
        div.dataset.id = msg.id;

        let bubble = '';

        if (msg.message_text) {
            bubble += '<p class="chat-bubble-text">'
                + linkify(msg.message_text).replace(/\n/g, '<br>')
                + '</p>';
        }

        if (msg.image_url) {
            bubble += '<a href="' + esc(msg.image_url) + '" target="_blank"'
                + ' rel="noopener noreferrer" class="chat-img-link" download>'
                + '<img src="' + esc(msg.image_url) + '" alt="Shared image"'
                + ' class="chat-img-preview" loading="lazy"></a>';
        }

        const avatarHtml = '<img src="' + esc(msg.sender_avatar_url) + '" alt=""'
            + ' class="chat-msg-avatar" width="28" height="28" loading="lazy">';

        div.innerHTML = (msg.is_mine ? '' : avatarHtml)
            + '<div class="chat-bubble">'
            +   bubble
            +   '<time class="chat-msg-time">' + esc(msg.time_ago) + '</time>'
            + '</div>'
            + (msg.is_mine ? avatarHtml : '');

        return div;
    }

    /* ── Window creation ─────────────────────────────────────────────────── */

    function createWindowEl(ws) {
        const div = document.createElement('div');
        div.className = 'chat-window';
        div.dataset.userId = ws.userId;

        div.innerHTML =
            '<div class="chat-window-header">'
            + '<img src="' + esc(ws.avatarUrl) + '" alt="" class="chat-window-avatar"'
            + ' width="26" height="26" loading="lazy">'
            + '<span class="chat-window-name">' + esc(ws.username) + '</span>'
            + '<button class="chat-win-close-btn" aria-label="Close">&#x2715;</button>'
            + '</div>'
            + '<div class="chat-messages" role="log" aria-live="polite"'
            + ' aria-label="Messages"></div>'
            + '<div class="chat-compose">'
            + '<input type="text" class="chat-input" placeholder="Text Message"'
            + ' maxlength="5000" autocomplete="off" aria-label="Message text">'
            + '<label class="chat-upload-label"'
            + ' title="Attach image (JPG, PNG, WEBP, GIF — max 10 MB)"'
            + ' aria-label="Attach image">'
            + '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19'
            + ' 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.41 17.41a2 2'
            + ' 0 0 1-2.83-2.83l8.49-8.48"/></svg>'
            + '<input type="file" class="chat-image-input"'
            + ' accept="image/jpeg,image/png,image/webp,image/gif"'
            + ' style="display:none" aria-hidden="true">'
            + '</label>'
            + '</div>';

        ws.el           = div;
        ws.elMessages   = div.querySelector('.chat-messages');
        ws.elInput      = div.querySelector('.chat-input');
        ws.elImageInput = div.querySelector('.chat-image-input');

        /* Close button */
        div.querySelector('.chat-win-close-btn').addEventListener('click', e => {
            e.stopPropagation();
            closeWindow(ws.userId);
        });

        /* Send on Enter */
        ws.elInput.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage(ws);
            }
        });

        /* Image file input */
        ws.elImageInput.addEventListener('change', e => {
            if (e.target.files[0]) {
                uploadImage(ws, e.target.files[0]);
                e.target.value = '';
            }
        });

        /* Drag and drop */
        ws.elMessages.addEventListener('dragover', e => {
            e.preventDefault();
            ws.elMessages.classList.add('chat-drop-active');
        });
        ws.elMessages.addEventListener('dragleave', e => {
            if (!ws.elMessages.contains(e.relatedTarget)) {
                ws.elMessages.classList.remove('chat-drop-active');
            }
        });
        ws.elMessages.addEventListener('drop', e => {
            e.preventDefault();
            ws.elMessages.classList.remove('chat-drop-active');
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) uploadImage(ws, file);
        });

        /* Scroll-up: load older messages */
        ws.elMessages.addEventListener('scroll', () => {
            if (ws.elMessages.scrollTop === 0) loadOlderMessages(ws);
        });

        return div;
    }

    /* ── Open / close window ─────────────────────────────────────────────── */

    async function openWindow(userId, username, avatarUrl) {
        userId = parseInt(userId, 10);

        if (openWindows.has(userId)) {
            // Window already open — just focus the input
            openWindows.get(userId).elInput.focus();
            return;
        }

        const ws = {
            userId,
            username,
            avatarUrl,
            convId:         null,
            newestMsgId:    0,
            oldestMsgId:    null,
            isLoadingOlder: false,
            pollTimer:      null,
            el:             null,
            elMessages:     null,
            elInput:        null,
            elImageInput:   null,
        };

        openWindows.set(userId, ws);

        const container = document.getElementById('chat-windows-container');
        container.appendChild(createWindowEl(ws));

        // Load initial messages (if a conversation already exists)
        await loadMessages(ws);

        // Start per-window polling
        ws.pollTimer = setInterval(() => pollMessages(ws), POLL_MSG_MS);

        ws.elInput.focus();
    }

    function closeWindow(userId) {
        const ws = openWindows.get(parseInt(userId, 10));
        if (!ws) return;
        if (ws.pollTimer) clearInterval(ws.pollTimer);
        ws.el.remove();
        openWindows.delete(parseInt(userId, 10));
    }

    /* ── Message loading ─────────────────────────────────────────────────── */

    async function loadMessages(ws) {
        try {
            // If we already know the conversation ID, use it; otherwise resolve by receiver_id
            const param = ws.convId
                ? 'conversation_id=' + ws.convId
                : 'receiver_id='     + ws.userId;

            const resp = await fetch(
                siteUrl + '/chat/get_messages.php?' + param,
                { credentials: 'same-origin' }
            );
            const data = await resp.json();

            if (!data.ok) return;

            // Store conversation ID if the server resolved it
            if (data.conversation_id) ws.convId = data.conversation_id;

            if (!data.messages.length) return;

            data.messages.forEach(msg => ws.elMessages.appendChild(buildMessageEl(msg)));
            ws.newestMsgId = data.messages[data.messages.length - 1].id;
            ws.oldestMsgId = data.messages[0].id;
            scrollToBottom(ws.elMessages);

            if (ws.convId) markRead(ws.convId);
        } catch (_) { /* silent */ }
    }

    async function loadOlderMessages(ws) {
        if (!ws.convId || !ws.oldestMsgId || ws.isLoadingOlder) return;

        ws.isLoadingOlder = true;
        const prevHeight  = ws.elMessages.scrollHeight;

        try {
            const resp = await fetch(
                siteUrl + '/chat/get_messages.php'
                + '?conversation_id=' + ws.convId
                + '&before_id='       + ws.oldestMsgId,
                { credentials: 'same-origin' }
            );
            const data = await resp.json();
            if (!data.ok || !data.messages.length) { ws.isLoadingOlder = false; return; }

            for (let i = data.messages.length - 1; i >= 0; i--) {
                ws.elMessages.insertBefore(buildMessageEl(data.messages[i]), ws.elMessages.firstChild);
            }
            ws.oldestMsgId = data.messages[0].id;
            ws.elMessages.scrollTop = ws.elMessages.scrollHeight - prevHeight;
        } catch (_) { /* silent */ }

        ws.isLoadingOlder = false;
    }

    async function pollMessages(ws) {
        try {
            // If no conversation yet, try to discover one that may have been created
            if (!ws.convId) {
                const resp = await fetch(
                    siteUrl + '/chat/get_messages.php?receiver_id=' + ws.userId,
                    { credentials: 'same-origin' }
                );
                const data = await resp.json();
                if (data.ok && data.conversation_id) {
                    ws.convId = data.conversation_id;
                    if (data.messages.length) {
                        data.messages.forEach(msg => ws.elMessages.appendChild(buildMessageEl(msg)));
                        ws.newestMsgId = data.messages[data.messages.length - 1].id;
                        ws.oldestMsgId = data.messages[0].id;
                        scrollToBottom(ws.elMessages);
                        markRead(ws.convId);
                    }
                }
                return;
            }

            const resp = await fetch(
                siteUrl + '/chat/get_messages.php'
                + '?conversation_id=' + ws.convId
                + '&after_id='        + ws.newestMsgId,
                { credentials: 'same-origin' }
            );
            const data = await resp.json();
            if (!data.ok || !data.messages.length) return;

            const atBottom =
                ws.elMessages.scrollHeight - ws.elMessages.scrollTop - ws.elMessages.clientHeight < 80;

            data.messages.forEach(msg => {
                ws.elMessages.appendChild(buildMessageEl(msg));
                ws.newestMsgId = msg.id;
            });

            if (atBottom) scrollToBottom(ws.elMessages);
            markRead(ws.convId);
        } catch (_) { /* silent */ }
    }

    /* ── Send message ────────────────────────────────────────────────────── */

    async function sendMessage(ws) {
        const text = ws.elInput.value.trim();
        if (!text) return;

        ws.elInput.value = '';

        try {
            const data = await apiPost(siteUrl + '/chat/send_message.php', {
                receiver_id:  ws.userId,
                message_text: text,
            });

            if (data.ok) {
                if (!ws.convId) ws.convId = data.conversation_id;
                ws.elMessages.appendChild(buildMessageEl(data.message));
                ws.newestMsgId = data.message.id;
                if (!ws.oldestMsgId) ws.oldestMsgId = data.message.id;
                scrollToBottom(ws.elMessages);
            } else {
                ws.elInput.value = text;
                console.error('Chat send error:', data.error);
            }
        } catch (err) {
            ws.elInput.value = text;
            console.error('sendMessage failed:', err);
        }

        ws.elInput.focus();
    }

    async function uploadImage(ws, file) {
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowed.includes(file.type)) {
            alert('Only JPG, PNG, WEBP and GIF images are allowed.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            alert('File too large. Maximum size is 10 MB.');
            return;
        }

        try {
            const fd = new FormData();
            fd.append('csrf_token',  csrfToken);
            fd.append('receiver_id', ws.userId);
            fd.append('image',       file);

            const resp = await fetch(siteUrl + '/chat/upload_image.php', {
                method: 'POST', body: fd, credentials: 'same-origin',
            });
            const data = await resp.json();

            if (data.ok) {
                if (!ws.convId) ws.convId = data.conversation_id;
                ws.elMessages.appendChild(buildMessageEl(data.message));
                ws.newestMsgId = data.message.id;
                if (!ws.oldestMsgId) ws.oldestMsgId = data.message.id;
                scrollToBottom(ws.elMessages);
            } else {
                alert('Upload failed: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            console.error('uploadImage failed:', err);
            alert('Upload failed. Please try again.');
        }
    }

    async function markRead(convId) {
        try {
            await apiPost(siteUrl + '/chat/mark_read.php', { conversation_id: convId });
        } catch (_) { /* silent */ }
    }

    /* ── Contact list ────────────────────────────────────────────────────── */

    async function loadUsers(search) {
        const elList = document.getElementById('chat-users-list');
        if (!elList) return;

        try {
            const url = siteUrl + '/chat/get_users.php'
                + (search ? '?search=' + encodeURIComponent(search) : '');
            const resp = await fetch(url, { credentials: 'same-origin' });
            const data = await resp.json();
            if (!data.ok) return;

            renderUsers(data.users);
            updateBadge(data.users.reduce((s, u) => s + u.unread_count, 0));
        } catch (_) { /* silent */ }
    }

    function renderUsers(users) {
        const elList = document.getElementById('chat-users-list');
        if (!elList) return;

        if (!users.length) {
            elList.innerHTML = '<p class="chat-empty">No contacts found.</p>';
            return;
        }

        elList.innerHTML = '';
        users.forEach(u => {
            const item = document.createElement('div');
            item.className = 'chat-user-item';
            item.dataset.userId = u.id;
            item.setAttribute('role', 'listitem');
            item.innerHTML =
                '<img src="' + esc(u.avatar_url) + '" alt=""'
                + ' class="chat-user-avatar" width="34" height="34" loading="lazy">'
                + '<span class="chat-user-name">' + esc(u.username) + '</span>'
                + (u.unread_count > 0
                    ? '<span class="badge">' + u.unread_count + '</span>'
                    : '');
            item.addEventListener('click', () => openWindow(u.id, u.username, u.avatar_url));
            elList.appendChild(item);
        });
    }

    /* ── Badge ───────────────────────────────────────────────────────────── */

    function updateBadge(total) {
        [
            document.getElementById('chat-badge'),
            document.getElementById('chat-badge-toggle'),
        ].forEach(el => {
            if (!el) return;
            el.textContent    = total;
            el.style.display  = total > 0 ? 'inline-block' : 'none';
        });
    }

    /* ── Sidebar open / close ────────────────────────────────────────────── */

    function openSidebar() {
        sidebarOpen = true;
        const elSidebar = document.getElementById('chat-sidebar');
        const elToggle  = document.getElementById('chat-toggle');
        if (elSidebar) elSidebar.style.display = 'flex';
        if (elToggle)  elToggle.style.display  = 'none';
        loadUsers('');
    }

    function closeSidebar() {
        sidebarOpen = false;
        const elSidebar = document.getElementById('chat-sidebar');
        const elToggle  = document.getElementById('chat-toggle');
        if (elSidebar) elSidebar.style.display = 'none';
        if (elToggle)  elToggle.style.display  = 'flex';
    }

    /* ── Background badge poll ───────────────────────────────────────────── */

    async function pollBadge() {
        try {
            // Always fetch the full (unfiltered) list to get accurate unread totals
            const resp = await fetch(siteUrl + '/chat/get_users.php', { credentials: 'same-origin' });
            const data = await resp.json();
            if (!data.ok) return;
            const total = data.users.reduce((s, u) => s + u.unread_count, 0);
            updateBadge(total);
            // If the sidebar is open, let loadUsers() refresh the list (it respects the
            // current search query and fetches the filtered result from the server)
            if (sidebarOpen) {
                const search = document.getElementById('chat-user-search')?.value.trim() ?? '';
                loadUsers(search);
            }
        } catch (_) { /* silent */ }
    }

    /* ── Initialisation ──────────────────────────────────────────────────── */

    function init() {
        if (!document.getElementById('chat-widget')) return;

        const csrfEl = document.getElementById('chat-csrf');
        csrfToken    = csrfEl ? csrfEl.value : '';
        siteUrl      = document.querySelector('meta[name="site-url"]')?.content
                       || window.location.origin;

        // Toggle button
        document.getElementById('chat-toggle')?.addEventListener('click', openSidebar);

        // Sidebar close button
        document.getElementById('chat-sidebar-close')?.addEventListener('click', closeSidebar);

        // Search input (debounced)
        const elSearch = document.getElementById('chat-user-search');
        if (elSearch) {
            elSearch.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => loadUsers(elSearch.value.trim()), 300);
            });
        }

        // Background badge poll + immediate first run
        badgePollTimer = setInterval(pollBadge, POLL_BADGE_MS);
        pollBadge();
    }

    /* ── Public API ──────────────────────────────────────────────────────── */

    /**
     * Open the chat widget and start a conversation with a specific user.
     *
     * Usage (e.g. from a profile page):
     *   ChatWidget.startChat(userId, username, avatarUrl)
     */
    window.ChatWidget = {
        startChat: async function (userId, username, avatarUrl) {
            if (!sidebarOpen) openSidebar();
            openWindow(userId, username, avatarUrl);
        },
    };

    document.addEventListener('DOMContentLoaded', init);

})();
