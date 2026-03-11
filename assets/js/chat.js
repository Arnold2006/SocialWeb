/**
 * chat.js — Real-time chat widget
 *
 * Features:
 *  - Floating toggle button (bottom-right corner)
 *  - Conversation list with unread counts
 *  - Real-time message polling every 3 seconds
 *  - Text messaging and drag-and-drop / click-to-upload image sharing
 *  - Auto-scroll to newest message
 *  - Load older messages on scroll-up (50 messages at a time)
 *  - Dark-theme bubbles: sent messages align right, received align left
 */

'use strict';

(function () {

    /* ── State ─────────────────────────────────────────────────────────────── */

    const state = {
        isOpen:                false,
        activeConversationId:  null,    // null = no open conversation
        activeReceiverId:      null,
        activeReceiverName:    '',
        activeReceiverAvatar:  '',
        newestMessageId:       0,
        oldestMessageId:       null,
        isLoadingOlder:        false,
        msgPollTimer:          null,
        convPollTimer:         null,
    };

    /* ── DOM references (resolved once on init) ─────────────────────────────── */

    let elWidget, elToggle, elBadge, elPanel;
    let elConvsPanel, elConvsList;
    let elChatWindow, elChatWindowAvatar, elChatWindowName;
    let elMessages, elInput, elSendBtn, elImageInput;
    let elBackBtn, elCloseBtn;
    let csrfToken, siteUrl;

    /* ── Helpers ────────────────────────────────────────────────────────────── */

    /** Escape a string for safe insertion into HTML */
    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str ?? '')));
        return d.innerHTML;
    }

    /** POST FormData to a URL, return parsed JSON */
    async function apiPost(url, fields) {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        for (const [k, v] of Object.entries(fields)) {
            fd.append(k, v);
        }
        const resp = await fetch(url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
        });
        return resp.json();
    }

    /** Build the DOM element for a single message bubble */
    function buildMessageEl(msg) {
        const div = document.createElement('div');
        div.className = 'chat-msg ' + (msg.is_mine ? 'chat-msg--mine' : 'chat-msg--theirs');
        div.dataset.id = msg.id;

        let bubbleContent = '';

        if (msg.message_text) {
            bubbleContent += '<p class="chat-bubble-text">'
                + esc(msg.message_text).replace(/\n/g, '<br>')
                + '</p>';
        }

        if (msg.image_url) {
            bubbleContent += '<a href="' + esc(msg.image_url) + '" target="_blank"'
                + ' rel="noopener noreferrer" class="chat-img-link"'
                + ' download>'
                + '<img src="' + esc(msg.image_url) + '" alt="Shared image"'
                + ' class="chat-img-preview" loading="lazy">'
                + '</a>';
        }

        const avatarHtml = '<img src="' + esc(msg.sender_avatar_url) + '" alt=""'
            + ' class="chat-msg-avatar" width="28" height="28" loading="lazy">';

        div.innerHTML = (msg.is_mine ? '' : avatarHtml)
            + '<div class="chat-bubble">'
            +   bubbleContent
            +   '<time class="chat-msg-time">' + esc(msg.time_ago) + '</time>'
            + '</div>'
            + (msg.is_mine ? avatarHtml : '');

        return div;
    }

    /* ── Conversation list rendering ───────────────────────────────────────── */

    function renderConversations(convs) {
        if (!convs.length) {
            elConvsList.innerHTML = '<p class="chat-empty">No conversations yet.<br>'
                + 'Visit a member\'s profile to start one.</p>';
            return;
        }

        elConvsList.innerHTML = '';
        convs.forEach(c => {
            const item = document.createElement('div');
            item.className = 'chat-conv-item'
                + (c.id === state.activeConversationId ? ' chat-conv-item--active' : '');
            item.dataset.convId   = c.id;
            item.dataset.userId   = c.other_user.id;
            item.dataset.username = c.other_user.username;
            item.dataset.avatar   = c.other_user.avatar_url;

            item.innerHTML = '<img src="' + esc(c.other_user.avatar_url) + '" alt=""'
                + ' class="chat-conv-avatar" width="40" height="40" loading="lazy">'
                + '<div class="chat-conv-body">'
                +   '<span class="chat-conv-name">' + esc(c.other_user.username) + '</span>'
                +   '<span class="chat-conv-preview">' + esc(c.last_message) + '</span>'
                + '</div>'
                + '<div class="chat-conv-meta">'
                +   '<span class="chat-conv-time">' + esc(c.last_time) + '</span>'
                +   (c.unread_count > 0 ? '<span class="badge">' + c.unread_count + '</span>' : '')
                + '</div>';

            item.addEventListener('click', () => openConversation(
                c.id,
                c.other_user.id,
                c.other_user.username,
                c.other_user.avatar_url
            ));

            elConvsList.appendChild(item);
        });
    }

    /* ── Conversation fetching ─────────────────────────────────────────────── */

    async function loadConversations() {
        try {
            const resp = await fetch(siteUrl + '/chat/get_conversations.php', {
                credentials: 'same-origin',
            });
            const data = await resp.json();
            if (!data.ok) return;

            renderConversations(data.conversations);
            updateToggleBadge(
                data.conversations.reduce((sum, c) => sum + c.unread_count, 0)
            );
        } catch (_) { /* silent */ }
    }

    /* ── Open a conversation ───────────────────────────────────────────────── */

    async function openConversation(convId, userId, username, avatarUrl) {
        state.activeConversationId = convId;
        state.activeReceiverId     = userId;
        state.activeReceiverName   = username;
        state.activeReceiverAvatar = avatarUrl;
        state.newestMessageId      = 0;
        state.oldestMessageId      = null;
        state.isLoadingOlder       = false;

        // Update window header
        elChatWindowAvatar.src = avatarUrl;
        elChatWindowAvatar.alt = username;
        elChatWindowName.textContent = username;

        // Switch panels
        elConvsPanel.style.display  = 'none';
        elChatWindow.style.display  = 'flex';

        // Clear old messages
        elMessages.innerHTML = '';

        // Load initial messages
        await loadMessages();

        // Mark as read
        if (convId) markRead(convId);

        // Start per-message polling
        startMsgPolling();
    }

    /* ── Message loading ───────────────────────────────────────────────────── */

    async function loadMessages() {
        if (!state.activeConversationId) return;

        try {
            const resp = await fetch(
                siteUrl + '/chat/get_messages.php?conversation_id=' + state.activeConversationId,
                { credentials: 'same-origin' }
            );
            const data = await resp.json();
            if (!data.ok || !data.messages.length) return;

            data.messages.forEach(msg => {
                elMessages.appendChild(buildMessageEl(msg));
            });

            state.newestMessageId = data.messages[data.messages.length - 1].id;
            state.oldestMessageId = data.messages[0].id;

            scrollToBottom();
        } catch (_) { /* silent */ }
    }

    async function loadOlderMessages() {
        if (!state.activeConversationId || !state.oldestMessageId || state.isLoadingOlder) return;

        state.isLoadingOlder = true;

        // Remember scroll position so we can restore it after prepending
        const prevHeight = elMessages.scrollHeight;

        try {
            const resp = await fetch(
                siteUrl + '/chat/get_messages.php'
                + '?conversation_id=' + state.activeConversationId
                + '&before_id=' + state.oldestMessageId,
                { credentials: 'same-origin' }
            );
            const data = await resp.json();
            if (!data.ok || !data.messages.length) {
                state.isLoadingOlder = false;
                return;
            }

            // Prepend in reverse order so first message ends up on top
            for (let i = data.messages.length - 1; i >= 0; i--) {
                elMessages.insertBefore(buildMessageEl(data.messages[i]), elMessages.firstChild);
            }

            state.oldestMessageId = data.messages[0].id;

            // Restore scroll so the user stays at the same visual position
            elMessages.scrollTop = elMessages.scrollHeight - prevHeight;
        } catch (_) { /* silent */ }

        state.isLoadingOlder = false;
    }

    /* ── Polling ───────────────────────────────────────────────────────────── */

    function startMsgPolling() {
        if (state.msgPollTimer) clearInterval(state.msgPollTimer);
        state.msgPollTimer = setInterval(pollNewMessages, 3000);
    }

    function stopMsgPolling() {
        if (state.msgPollTimer) {
            clearInterval(state.msgPollTimer);
            state.msgPollTimer = null;
        }
    }

    async function pollNewMessages() {
        if (!state.activeConversationId) return;

        try {
            const resp = await fetch(
                siteUrl + '/chat/get_messages.php'
                + '?conversation_id=' + state.activeConversationId
                + '&after_id=' + state.newestMessageId,
                { credentials: 'same-origin' }
            );
            const data = await resp.json();
            if (!data.ok || !data.messages.length) return;

            // Are we already at the bottom? (within 80 px)
            const atBottom =
                elMessages.scrollHeight - elMessages.scrollTop - elMessages.clientHeight < 80;

            data.messages.forEach(msg => {
                elMessages.appendChild(buildMessageEl(msg));
                state.newestMessageId = msg.id;
            });

            if (atBottom) scrollToBottom();

            // Keep the unread badge current
            if (state.isOpen) markRead(state.activeConversationId);
        } catch (_) { /* silent */ }
    }

    async function pollConversations() {
        try {
            const resp = await fetch(siteUrl + '/chat/get_conversations.php', {
                credentials: 'same-origin',
            });
            const data = await resp.json();
            if (!data.ok) return;

            const total = data.conversations.reduce((s, c) => s + c.unread_count, 0);
            updateToggleBadge(total);

            // Refresh the list only when the conversations panel is visible
            if (state.isOpen && !state.activeConversationId) {
                renderConversations(data.conversations);
            }
        } catch (_) { /* silent */ }
    }

    /* ── Send & upload ──────────────────────────────────────────────────────── */

    async function sendMessage() {
        const text = elInput.value.trim();
        if (!text || !state.activeReceiverId) return;

        elInput.value = '';
        elSendBtn.disabled = true;

        try {
            const data = await apiPost(siteUrl + '/chat/send_message.php', {
                receiver_id:  state.activeReceiverId,
                message_text: text,
            });

            if (data.ok) {
                // If this was a new conversation, store its ID and start polling
                if (!state.activeConversationId) {
                    state.activeConversationId = data.conversation_id;
                    startMsgPolling();
                }
                elMessages.appendChild(buildMessageEl(data.message));
                state.newestMessageId = data.message.id;
                if (!state.oldestMessageId) state.oldestMessageId = data.message.id;
                scrollToBottom();
            } else {
                elInput.value = text;
                alert('Could not send message: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            elInput.value = text;
            console.error('Chat sendMessage failed:', err);
        } finally {
            elSendBtn.disabled = false;
            elInput.focus();
        }
    }

    async function uploadImage(file) {
        if (!state.activeReceiverId) {
            alert('Open a conversation first.');
            return;
        }

        // Client-side type check (the server validates as well)
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowed.includes(file.type)) {
            alert('Only JPG, PNG, WEBP and GIF images are allowed.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            alert('File too large. Maximum size is 10 MB.');
            return;
        }

        elSendBtn.disabled = true;
        elSendBtn.textContent = '⏳';

        try {
            const fd = new FormData();
            fd.append('csrf_token',  csrfToken);
            fd.append('receiver_id', state.activeReceiverId);
            fd.append('image',       file);

            const resp = await fetch(siteUrl + '/chat/upload_image.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            });
            const data = await resp.json();

            if (data.ok) {
                if (!state.activeConversationId) {
                    state.activeConversationId = data.conversation_id;
                    startMsgPolling();
                }
                elMessages.appendChild(buildMessageEl(data.message));
                state.newestMessageId = data.message.id;
                if (!state.oldestMessageId) state.oldestMessageId = data.message.id;
                scrollToBottom();
            } else {
                alert('Upload failed: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            console.error('Chat uploadImage failed:', err);
            alert('Upload failed. Please try again.');
        } finally {
            elSendBtn.disabled  = false;
            elSendBtn.textContent = 'Send';
        }
    }

    async function markRead(convId) {
        try {
            await apiPost(siteUrl + '/chat/mark_read.php', { conversation_id: convId });
        } catch (_) { /* silent */ }
    }

    /* ── UI helpers ─────────────────────────────────────────────────────────── */

    function scrollToBottom() {
        elMessages.scrollTop = elMessages.scrollHeight;
    }

    function updateToggleBadge(count) {
        if (count > 0) {
            elBadge.textContent    = count;
            elBadge.style.display  = 'inline-block';
        } else {
            elBadge.style.display  = 'none';
        }
    }

    function openPanel() {
        state.isOpen               = true;
        elPanel.style.display      = 'flex';
        elConvsPanel.style.display = 'flex';
        elChatWindow.style.display = 'none';
        loadConversations();

        // Switch to faster 3-second background poll while the panel is open
        clearInterval(state.convPollTimer);
        state.convPollTimer = setInterval(pollConversations, 3000);
    }

    function closePanel() {
        state.isOpen          = false;
        elPanel.style.display = 'none';
        stopMsgPolling();

        // Return to slower 15-second background poll
        clearInterval(state.convPollTimer);
        state.convPollTimer = setInterval(pollConversations, 15000);
    }

    function showConversationList() {
        stopMsgPolling();
        state.activeConversationId = null;
        state.activeReceiverId     = null;

        elChatWindow.style.display  = 'none';
        elConvsPanel.style.display  = 'flex';
        loadConversations();
    }

    /* ── Drag-and-drop highlight ────────────────────────────────────────────── */

    function bindDragDrop() {
        // Highlight the whole messages area while dragging
        elMessages.addEventListener('dragover', e => {
            e.preventDefault();
            elMessages.classList.add('chat-drop-active');
        });

        elMessages.addEventListener('dragleave', e => {
            if (!elMessages.contains(e.relatedTarget)) {
                elMessages.classList.remove('chat-drop-active');
            }
        });

        elMessages.addEventListener('drop', e => {
            e.preventDefault();
            elMessages.classList.remove('chat-drop-active');
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                uploadImage(file);
            }
        });
    }

    /* ── Initialization ─────────────────────────────────────────────────────── */

    function init() {
        elWidget           = document.getElementById('chat-widget');
        elToggle           = document.getElementById('chat-toggle');
        elBadge            = document.getElementById('chat-badge');
        elPanel            = document.getElementById('chat-panel');
        elConvsPanel       = document.getElementById('chat-convs-panel');
        elConvsList        = document.getElementById('chat-convs-list');
        elChatWindow       = document.getElementById('chat-window');
        elChatWindowAvatar = document.getElementById('chat-window-avatar');
        elChatWindowName   = document.getElementById('chat-window-name');
        elMessages         = document.getElementById('chat-messages');
        elInput            = document.getElementById('chat-input');
        elSendBtn          = document.getElementById('chat-send-btn');
        elImageInput       = document.getElementById('chat-image-input');
        elBackBtn          = document.getElementById('chat-back-btn');
        elCloseBtn         = document.getElementById('chat-close-btn');

        if (!elWidget) return; // not logged in / element absent

        // Read CSRF token and site URL from the page
        const csrfEl = document.getElementById('chat-csrf');
        csrfToken    = csrfEl ? csrfEl.value : '';
        siteUrl      = document.querySelector('meta[name="site-url"]')?.content
                       || window.location.origin;

        /* ── Button bindings ── */
        elToggle.addEventListener('click', () => {
            state.isOpen ? closePanel() : openPanel();
        });

        elCloseBtn.addEventListener('click', closePanel);
        elBackBtn.addEventListener('click',  showConversationList);

        // The chat window has its own close button too
        const elWinCloseBtn = document.getElementById('chat-win-close-btn');
        if (elWinCloseBtn) elWinCloseBtn.addEventListener('click', closePanel);

        elSendBtn.addEventListener('click', sendMessage);

        elInput.addEventListener('keydown', e => {
            // Enter (without Shift) sends the message
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        elImageInput.addEventListener('change', e => {
            if (e.target.files[0]) {
                uploadImage(e.target.files[0]);
                e.target.value = ''; // reset so the same file can be re-picked
            }
        });

        /* ── Scroll-up: load older messages ── */
        elMessages.addEventListener('scroll', () => {
            if (elMessages.scrollTop === 0) {
                loadOlderMessages();
            }
        });

        /* ── Drag and drop ── */
        bindDragDrop();

        /* ── Background conversation poll (every 15 s when panel is closed) ── */
        state.convPollTimer = setInterval(pollConversations, 15000);

        // Initial badge refresh
        pollConversations();
    }

    /* ── Public API (for use in profile pages etc.) ─────────────────────────── */

    /**
     * Open the chat widget and start a new or existing conversation.
     *
     * Usage:
     *   ChatWidget.startChat(userId, username, avatarUrl)
     */
    window.ChatWidget = {
        startChat: async function (userId, username, avatarUrl) {
            if (!state.isOpen) openPanel();

            try {
                const resp = await fetch(siteUrl + '/chat/get_conversations.php', {
                    credentials: 'same-origin',
                });
                const data = await resp.json();

                if (data.ok) {
                    const existing = data.conversations.find(
                        c => c.other_user.id === userId
                    );
                    if (existing) {
                        openConversation(
                            existing.id,
                            userId,
                            username,
                            existing.other_user.avatar_url
                        );
                    } else {
                        // New conversation — no ID yet
                        state.activeConversationId = null;
                        state.activeReceiverId     = userId;
                        state.activeReceiverName   = username;
                        state.activeReceiverAvatar = avatarUrl || '';
                        state.newestMessageId      = 0;
                        state.oldestMessageId      = null;

                        elChatWindowAvatar.src       = avatarUrl || '';
                        elChatWindowAvatar.alt       = username;
                        elChatWindowName.textContent = username;
                        elMessages.innerHTML         = '';

                        elConvsPanel.style.display  = 'none';
                        elChatWindow.style.display  = 'flex';
                    }
                }
            } catch (err) {
                console.error('ChatWidget.startChat failed:', err);
            }
        },
    };

    document.addEventListener('DOMContentLoaded', init);

})();
