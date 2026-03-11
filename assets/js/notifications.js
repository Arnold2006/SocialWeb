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
