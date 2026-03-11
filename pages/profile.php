<?php
/**
 * profile.php — User profile page
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$profileId   = sanitise_int($_GET['id'] ?? 0);
$currentUser = current_user();

if ($profileId < 1) {
    redirect(SITE_URL . '/pages/index.php');
}

$profileUser = db_row(
    'SELECT id, username, bio, avatar_path, created_at
     FROM users WHERE id = ? AND is_banned = 0',
    [$profileId]
);

if ($profileUser === null) {
    flash_set('error', 'User not found.');
    redirect(SITE_URL . '/pages/members.php');
}

// Friend status
$friendStatus = null;
if ((int)$currentUser['id'] !== $profileId) {
    $friendStatus = db_row(
        'SELECT * FROM friends
         WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
         LIMIT 1',
        [$currentUser['id'], $profileId, $profileId, $currentUser['id']]
    );
}

// Friend list (accepted)
$friends = db_query(
    'SELECT u.id, u.username, u.avatar_path
     FROM friends f
     JOIN users u ON (
         CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END = u.id
     )
     WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = "accepted" AND u.is_banned = 0
     LIMIT 12',
    [$profileId, $profileId, $profileId]
);

// Recent posts
$posts = db_query(
    'SELECT p.*, u.username, u.avatar_path,
            (SELECT COUNT(*) FROM likes   WHERE post_id = p.id) AS like_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND is_deleted = 0) AS comment_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) AS user_liked
     FROM posts p JOIN users u ON u.id = p.user_id
     WHERE p.user_id = ? AND p.is_deleted = 0
     ORDER BY p.created_at DESC
     LIMIT 10',
    [(int)$currentUser['id'], $profileId]
);

// Plugin profile extensions
$plugins = plugins_load();

$pageTitle = e($profileUser['username']) . "'s Profile";
include SITE_ROOT . '/includes/header.php';
?>

<div class="profile-layout">

    <!-- Profile Sidebar -->
    <aside class="profile-sidebar">
        <div class="profile-avatar-wrap">
            <img src="<?= e(avatar_url($profileUser, 'large')) ?>"
                 alt="<?= e($profileUser['username']) ?>"
                 class="profile-avatar" width="200" height="200">
        </div>

        <h1 class="profile-username"><?= e($profileUser['username']) ?></h1>
        <p class="profile-joined">Joined <?= e(date('M Y', strtotime($profileUser['created_at']))) ?></p>

        <?php if (!empty($profileUser['bio'])): ?>
        <p class="profile-bio"><?= nl2br(e($profileUser['bio'])) ?></p>
        <?php endif; ?>

        <!-- Friend action buttons -->
        <?php if ((int)$currentUser['id'] !== $profileId): ?>
        <div class="friend-actions">
            <?php if ($friendStatus === null): ?>
            <form method="POST" action="<?= SITE_URL ?>/modules/profile/friend_request.php">
                <?= csrf_field() ?>
                <input type="hidden" name="to_user" value="<?= $profileId ?>">
                <button type="submit" class="btn btn-primary">Add Friend</button>
            </form>
            <?php elseif ($friendStatus['status'] === 'pending'): ?>
                <?php if ((int)$friendStatus['user_id'] === (int)$currentUser['id']): ?>
                <span class="friend-status">Friend request sent</span>
                <?php else: ?>
                <form method="POST" action="<?= SITE_URL ?>/modules/profile/accept_friend.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="request_id" value="<?= (int)$friendStatus['id'] ?>">
                    <button type="submit" class="btn btn-success">Accept Request</button>
                </form>
                <?php endif; ?>
            <?php elseif ($friendStatus['status'] === 'accepted'): ?>
            <form method="POST" action="<?= SITE_URL ?>/modules/profile/remove_friend.php">
                <?= csrf_field() ?>
                <input type="hidden" name="friend_id" value="<?= $profileId ?>">
                <button type="submit" class="btn btn-secondary">Remove Friend</button>
            </form>
            <?php endif; ?>

            <button type="button" class="btn btn-secondary"
                    onclick="ChatWidget.startChat(
                        <?= (int)$profileId ?>,
                        <?= json_encode($profileUser['username']) ?>,
                        <?= json_encode(avatar_url($profileUser, 'small')) ?>
                    )">Message</button>
        </div>
        <?php endif; ?>

        <!-- Avatar upload (own profile) -->
        <?php if ((int)$currentUser['id'] === $profileId): ?>
        <div class="avatar-upload-section">
            <form method="POST" action="<?= SITE_URL ?>/modules/profile/upload_avatar.php"
                  enctype="multipart/form-data" id="avatar-form">
                <?= csrf_field() ?>
                <label for="avatar-input" class="btn btn-sm btn-secondary">Change Avatar</label>
                <input type="file" id="avatar-input" name="avatar"
                       accept="image/*" class="sr-only">
                <div id="avatar-crop-container" class="avatar-crop-container" style="display:none">
                    <canvas id="avatar-crop-canvas"></canvas>
                    <input type="hidden" name="crop_x" id="crop-x">
                    <input type="hidden" name="crop_y" id="crop-y">
                    <input type="hidden" name="crop_w" id="crop-w">
                    <input type="hidden" name="crop_h" id="crop-h">
                    <button type="submit" class="btn btn-primary">Save Avatar</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Gallery link -->
        <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . $profileId) ?>"
           class="btn btn-sm btn-secondary profile-gallery-btn">View Gallery</a>

        <!-- Friend list -->
        <?php if (!empty($friends)): ?>
        <div class="profile-friends">
            <h3>Friends</h3>
            <div class="friends-grid">
                <?php foreach ($friends as $f): ?>
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$f['id']) ?>"
                   title="<?= e($f['username']) ?>">
                    <img src="<?= e(avatar_url($f, 'small')) ?>"
                         alt="<?= e($f['username']) ?>"
                         class="avatar avatar-small" width="48" height="48" loading="lazy">
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Plugin profile extensions -->
        <?php foreach ($plugins['profile_extensions'] as $ext): ?>
            <?php $ext($profileId); ?>
        <?php endforeach; ?>
    </aside>

    <!-- Recent Posts -->
    <main class="profile-posts">
        <h2>Recent Posts</h2>
        <?php if (empty($posts)): ?>
        <p class="empty-state">No posts yet.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <?php include SITE_ROOT . '/modules/wall/post_item.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
