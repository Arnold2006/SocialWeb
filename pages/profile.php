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

$isOwnProfile = ((int)$currentUser['id'] === $profileId);

$error   = '';
$success = '';

if ($isOwnProfile && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $bio = sanitise_string($_POST['bio'] ?? '', 1000);
        db_exec('UPDATE users SET bio = ? WHERE id = ?', [$bio, (int)$currentUser['id']]);
        $success = 'Profile updated.';

    } elseif ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $userRow = db_row('SELECT password FROM users WHERE id = ?', [(int)$currentUser['id']]);

        if (!password_verify($current, $userRow['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            db_exec('UPDATE users SET password = ? WHERE id = ?', [$hash, (int)$currentUser['id']]);
            $success = 'Password changed successfully.';
        }
    }

    // Refresh current user data after any update
    $currentUser = db_row(
        'SELECT id, username, email, bio, avatar_path, role FROM users WHERE id = ?',
        [(int)$currentUser['id']]
    );
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
        <p class="profile-bio"><?= nl2br(linkify($profileUser['bio'])) ?></p>
        <?php endif; ?>



        <!-- Avatar upload (own profile) -->
        <?php if ($isOwnProfile): ?>
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

        <?php if ($isOwnProfile): ?>
        <!-- ── Settings widget (own profile only) ──────────── -->
        <div class="settings-layout">
            <h1>Settings</h1>

            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

            <!-- Profile settings -->
            <section class="settings-section">
                <h2>Profile</h2>
                <form method="POST" class="settings-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?= e($currentUser['username']) ?>" disabled
                               class="input-disabled">
                        <small>Username cannot be changed.</small>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?= e($currentUser['email']) ?>" disabled
                               class="input-disabled">
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio / About</label>
                        <textarea id="bio" name="bio" rows="5" maxlength="1000"><?= e($currentUser['bio'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>
            </section>

            <!-- Change password -->
            <section class="settings-section">
                <h2>Change Password</h2>
                <form method="POST" class="settings-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password"
                               autocomplete="current-password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password"
                               minlength="8" autocomplete="new-password" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               minlength="8" autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </section>

        </div><!-- /.settings-layout -->
        <?php endif; ?>
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
