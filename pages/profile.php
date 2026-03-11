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

        <!-- Message button -->
        <?php if ((int)$currentUser['id'] !== $profileId): ?>
        <div class="profile-actions">
            <a href="<?= e(SITE_URL . '/pages/messages.php?with=' . (int)$profileId) ?>"
               class="btn btn-primary">Message</a>
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
