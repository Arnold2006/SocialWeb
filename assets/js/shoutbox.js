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
 * shoutbox.js — Shoutbox AJAX polling
 *
 * Polls the shoutbox endpoint every 10 seconds and updates the DOM.
 */

'use strict';

(function () {
    const container = document.getElementById('shoutbox-messages');
    const form      = document.getElementById('shoutbox-form');
    if (!container) return;

    const baseUrl   = document.querySelector('meta[name="site-url"]')?.content || '';
    const endpoint  = baseUrl + '/modules/shoutbox/shoutbox.php';
    let   lastId    = 0;

    function getCsrfToken() {
        const input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

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

    /** Render messages into the container */
    function renderMessages(messages) {
        if (!messages || messages.length === 0) return;

        // Check if we have new messages
        const newMessages = messages.filter(m => m.id > lastId);
        if (newMessages.length === 0) return;

        lastId = Math.max(...messages.map(m => m.id));

        container.innerHTML = messages.map(m => `
            <div class="shout-item">
                <img src="${escapeHtml(m.avatar_url)}"
                     alt="" class="shout-avatar" width="24" height="24" loading="lazy">
                <span class="shout-user">
                    <a href="${escapeHtml(m.profile_url)}">${escapeHtml(m.username)}</a>
                </span>
                <span class="shout-time">${escapeHtml(m.time_ago)}</span>
                <p class="shout-text">${linkifyHtml(m.message)}</p>
            </div>
        `).join('');

        // Scroll to the bottom so the newest shout is visible
        container.scrollTop = container.scrollHeight;
    }

    /** Fetch latest shoutbox messages */
    async function pollShoutbox() {
        try {
            const resp = await fetch(endpoint, { credentials: 'same-origin' });
            const json = await resp.json();
            if (json.ok) {
                renderMessages(json.messages);
            }
        } catch (err) {
            // Silent fail — shoutbox is non-critical
        }
    }

    /** Post a new shout */
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input   = form.querySelector('#shout-input');
            const message = input ? input.value.trim() : '';
            if (!message) return;

            const data = new URLSearchParams({
                csrf_token: getCsrfToken(),
                message,
            });

            try {
                const resp = await fetch(endpoint, {
                    method: 'POST',
                    body:   data,
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                });
                const json = await resp.json();
                if (json.ok) {
                    if (input) input.value = '';
                    renderMessages(json.messages);
                } else {
                    alert(json.error || 'Could not send shout');
                }
            } catch (err) {
                console.error('Shoutbox post failed:', err);
            }
        });
    }

    // Initial fetch + start polling
    pollShoutbox();
    setInterval(pollShoutbox, 10000);  // Poll every 10 seconds
})();
