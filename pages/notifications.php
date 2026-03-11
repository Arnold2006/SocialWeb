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

<div class="page-header">
    <h1>Notifications</h1>
</div>

<?php if (empty($notifications)): ?>
<p class="empty-state">No notifications yet.</p>
<?php else: ?>
<div class="notifications-list">
    <?php foreach ($notifications as $n): ?>
    <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
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
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
