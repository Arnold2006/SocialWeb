/**
 * notifications.js — Notification & message count updater
 *
 * Polls the server every 30 seconds and updates badge counts.
 */

'use strict';

(function () {
    const baseUrl  = document.querySelector('meta[name="site-url"]')?.content || '';
    const endpoint = baseUrl + '/modules/notifications/get_notifications.php';

    /** Update badge elements with new counts */
    function updateBadges(data) {
        // Update notification badge in nav
        const notifLinks = document.querySelectorAll('a[href*="notifications.php"]');
        notifLinks.forEach(link => {
            let badge = link.querySelector('.badge');
            if (data.notifications > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge';
                    link.appendChild(badge);
                }
                badge.textContent = data.notifications;
            } else if (badge) {
                badge.remove();
            }
        });

        // Update message badge in nav
        const msgLinks = document.querySelectorAll('a[href*="messages.php"]');
        msgLinks.forEach(link => {
            // Skip conversation links (they have ?with= param)
            if (link.href.includes('with=')) return;

            let badge = link.querySelector('.badge');
            if (data.messages > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge';
                    link.appendChild(badge);
                }
                badge.textContent = data.messages;
            } else if (badge) {
                badge.remove();
            }
        });

        // Update page title with notification count
        const totalUnread = data.notifications + data.messages;
        if (totalUnread > 0) {
            document.title = document.title.replace(/^\(\d+\) /, '');
            document.title = '(' + totalUnread + ') ' + document.title;
        } else {
            document.title = document.title.replace(/^\(\d+\) /, '');
        }
    }

    async function pollNotifications() {
        try {
            const resp = await fetch(endpoint, { credentials: 'same-origin' });
            const json = await resp.json();
            if (json.ok) {
                updateBadges(json);
            }
        } catch (err) {
            // Silent fail
        }
    }

    // Only poll if user is logged in (page has main-nav)
    if (document.querySelector('.main-nav')) {
        pollNotifications();
        setInterval(pollNotifications, 30000);  // Every 30 seconds
    }
})();

// ── Notification delete / clear-all (notifications page only) ────────────────
(function () {
    'use strict';

    const csrf     = document.getElementById('notif-csrf')?.value || '';
    const baseUrl  = document.querySelector('meta[name="site-url"]')?.content || '';
    const list     = document.getElementById('notifications-list');
    const clearBtn = document.getElementById('clear-all-notifs');

    if (!list && !clearBtn) return;   // not on the notifications page

    function showEmptyState() {
        if (list) list.remove();
        if (clearBtn) clearBtn.remove();
        if (!document.getElementById('notif-empty-state')) {
            const p = document.createElement('p');
            p.className = 'empty-state';
            p.id = 'notif-empty-state';
            p.textContent = 'No notifications yet.';
            document.querySelector('.col-right').appendChild(p);
        }
    }

    // Delete single notification
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.notif-delete-btn');
        if (!btn) return;

        const notifId = btn.dataset.id;
        btn.disabled = true;

        try {
            const resp = await fetch(baseUrl + '/modules/notifications/delete_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(csrf) + '&id=' + encodeURIComponent(notifId),
                credentials: 'same-origin',
            });
            const json = await resp.json();
            if (json.ok) {
                const item = document.getElementById('notif-' + notifId);
                if (item) item.remove();
                if (list && list.children.length === 0) {
                    showEmptyState();
                }
            } else {
                btn.disabled = false;
                alert('Could not delete notification.');
            }
        } catch (err) {
            btn.disabled = false;
            alert('Could not delete notification.');
        }
    });

    // Clear all notifications
    if (clearBtn) {
        clearBtn.addEventListener('click', async function () {
            if (!confirm('Delete all notifications?')) return;
            clearBtn.disabled = true;

            try {
                const resp = await fetch(baseUrl + '/modules/notifications/clear_notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'csrf_token=' + encodeURIComponent(csrf),
                    credentials: 'same-origin',
                });
                const json = await resp.json();
                if (json.ok) {
                    showEmptyState();
                } else {
                    clearBtn.disabled = false;
                    alert('Could not clear notifications.');
                }
            } catch (err) {
                clearBtn.disabled = false;
                alert('Could not clear notifications.');
            }
        });
    }
})();
