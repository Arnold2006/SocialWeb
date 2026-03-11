<?php
/**
 * notifications.php — Notifications page
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pageTitle   = 'Notifications';
$currentUser = current_user();

// Mark all as read
db_exec(
    'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0',
    [(int)$currentUser['id']]
);

$notifications = db_query(
    'SELECT n.*, u.username AS from_username, u.avatar_path AS from_avatar
     FROM notifications n
     LEFT JOIN users u ON u.id = n.from_user_id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC
     LIMIT 50',
    [(int)$currentUser['id']]
);

include SITE_ROOT . '/includes/header.php';
?>

<div class="two-col-layout">

    <!-- ── Left Column ─────────────────────────────────────────── -->
    <aside class="col-left">
        <?php include SITE_ROOT . '/includes/sidebar_widgets.php'; ?>
    </aside>

    <!-- ── Right Column ────────────────────────────────────────── -->
    <main class="col-right">

<input type="hidden" id="notif-csrf" value="<?= e(csrf_token()) ?>">

<div class="page-header">
    <h1>Notifications</h1>
    <?php if (!empty($notifications)): ?>
    <button id="clear-all-notifs" class="btn btn-secondary btn-sm">Clear all</button>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
<p class="empty-state" id="notif-empty-state">No notifications yet.</p>
<?php else: ?>
<div class="notifications-list" id="notifications-list">
    <?php foreach ($notifications as $n): ?>
    <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" id="notif-<?= (int)$n['id'] ?>">
        <?php if ($n['from_avatar']): ?>
        <img src="<?= e(avatar_url(['avatar_path' => $n['from_avatar']], 'small')) ?>"
             alt="" class="avatar avatar-small" width="36" height="36" loading="lazy">
        <?php endif; ?>
        <div class="notif-body">
            <?php switch ($n['type']):
                case 'like': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> liked your post.</p>
                <?php if ($n['ref_id']): ?>
                <a href="<?= e(SITE_URL . '/pages/index.php#post-' . (int)$n['ref_id']) ?>">View post</a>
                <?php endif; ?>
                <?php break;

                case 'comment': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> commented on your post.</p>
                <?php break;

                case 'friend_request': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> sent you a friend request.</p>
                <?php if ($n['from_user_id']): ?>
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$n['from_user_id']) ?>">View profile</a>
                <?php endif; ?>
                <?php break;

                case 'message': ?>
                <p><strong><?= e($n['from_username'] ?? 'Someone') ?></strong> sent you a message.</p>
                <?php if ($n['from_user_id']): ?>
                <a href="<?= e(SITE_URL . '/pages/messages.php?with=' . (int)$n['from_user_id']) ?>">View message</a>
                <?php endif; ?>
                <?php break;
            endswitch; ?>
        </div>
        <time class="notif-time"><?= e(time_ago($n['created_at'])) ?></time>
        <button class="notif-delete-btn btn btn-danger btn-xs"
                data-id="<?= (int)$n['id'] ?>"
                title="Delete notification">✕</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
(function () {
    'use strict';

    const csrf     = document.getElementById('notif-csrf')?.value || '';
    const baseUrl  = document.querySelector('meta[name="site-url"]')?.content || '';
    const list     = document.getElementById('notifications-list');
    const clearBtn = document.getElementById('clear-all-notifs');
    const emptyMsg = document.getElementById('notif-empty-state');

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
</script>

    </main>

</div><!-- /.two-col-layout -->

<?php include SITE_ROOT . '/includes/footer.php'; ?>
