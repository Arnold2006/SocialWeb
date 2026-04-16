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
 * friends.js — Friend button and friends page interactions
 *
 * Handles:
 *  - Add / Cancel / Accept / Decline friend buttons on profile pages
 *  - Tab switching on the friends list page
 *  - Accept / Decline / Cancel action buttons on the friends list page
 */

'use strict';

(function () {
    const siteUrl = document.querySelector('meta[name="site-url"]')?.content || '';
    const BASE    = siteUrl + '/modules/friends/';

    // ── Profile page: Add/Cancel/Accept/Decline friend buttons ───────────────
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.friend-btn');
        if (!btn) return;

        const action    = btn.dataset.action;
        const profileId = btn.dataset.profileId;
        const csrf      = btn.dataset.csrf;

        const endpoints = {
            request: 'ajax_request.php',
            accept:  'ajax_accept.php',
            decline: 'ajax_decline.php',
            cancel:  'ajax_cancel.php',
        };

        const bodies = {
            request: 'addressee_id=' + profileId,
            accept:  'requester_id=' + profileId,
            decline: 'requester_id=' + profileId,
            cancel:  'other_id='     + profileId,
        };

        if (!endpoints[action]) return;

        btn.disabled = true;

        fetch(BASE + endpoints[action], {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'csrf_token=' + encodeURIComponent(csrf) + '&' + bodies[action],
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Action failed.');
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
        });
    });

    // ── Friends list page: tab switching ─────────────────────────────────────
    const tabBtns   = document.querySelectorAll('.friends-tab-btn');
    const tabPanels = document.querySelectorAll('.friends-tab-panel');

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanels.forEach(p => p.style.display = 'none');
            btn.classList.add('active');
            const panel = document.getElementById('tab-' + btn.dataset.tab);
            if (panel) panel.style.display = '';
        });
    });

    // ── Friends list page: accept / decline / cancel buttons ─────────────────
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.friend-action-btn');
        if (!btn) return;

        const action  = btn.dataset.action;
        const otherId = btn.dataset.otherId;
        const csrf    = btn.dataset.csrf;

        const endpointMap = {
            accept:  'ajax_accept.php',
            decline: 'ajax_decline.php',
            cancel:  'ajax_cancel.php',
        };

        const bodyMap = {
            accept:  'requester_id=' + otherId,
            decline: 'requester_id=' + otherId,
            cancel:  'other_id='     + otherId,
        };

        if (!endpointMap[action]) return;

        btn.disabled = true;

        fetch(BASE + endpointMap[action], {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'csrf_token=' + encodeURIComponent(csrf) + '&' + bodyMap[action],
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const card = document.getElementById('req-card-' + otherId)
                          || document.getElementById('sent-card-' + otherId)
                          || btn.closest('.member-card');
                if (card) card.remove();
            } else {
                alert(data.message || 'Action failed.');
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
        });
    });
}());
