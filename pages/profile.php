<?php
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

    } elseif ($action === 'update_email') {
        $newEmail = sanitise_email($_POST['email'] ?? '');

        if (empty($newEmail)) {
            $error = 'Email address is required.';
        } elseif ($newEmail === $currentUser['email']) {
            $error = 'That is already your current email address.';
        } elseif (db_val('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?', [$newEmail, (int)$currentUser['id']]) > 0) {
            $error = 'Email address is already in use.';
        } else {
            db_exec('UPDATE users SET email = ? WHERE id = ?', [$newEmail, (int)$currentUser['id']]);
            $success = 'Email address updated.';
        }

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
        'SELECT id, username, full_name, email, bio, avatar_path, role FROM users WHERE id = ?',
        [(int)$currentUser['id']]
    );
}

$profileUser = db_row(
    'SELECT id, username, full_name, bio, avatar_path, created_at
     FROM users WHERE id = ? AND is_banned = 0',
    [$profileId]
);

if ($profileUser === null) {
    flash_set('error', 'User not found.');
    redirect(SITE_URL . '/pages/members.php');
}

// Privacy gate — view_profile
if (!$isOwnProfile && !PrivacyService::canView((int) $currentUser['id'], $profileId, 'view_profile')) {
    $pageTitle = e($profileUser['username']) . "'s Profile";
    include SITE_ROOT . '/includes/header.php';
    echo '<div class="two-col-layout"><main class="col-right">';
    echo '<div class="profile-layout">';
    echo '<aside class="profile-sidebar">';
    echo '<img src="' . e(avatar_url($profileUser, 'large')) . '" alt="' . e($profileUser['username']) . '" class="profile-avatar" width="200" height="200">';
    echo '<h1 class="profile-username">' . e($profileUser['username']) . '</h1>';
    echo '</aside>';
    echo '<main class="profile-posts"><div class="alert alert-error">This profile is private.</div></main>';
    echo '</div></div></main></div>';
    include SITE_ROOT . '/includes/footer.php';
    exit;
}

// Recent posts (fetch one extra to detect whether a "Load More" is needed)
$profilePostsLimit = 10;
$canViewWall       = $isOwnProfile || PrivacyService::canView((int) $currentUser['id'], $profileId, 'view_wall');
$posts             = [];

if ($canViewWall) {
    $posts = db_query(
        'SELECT p.*, u.username, u.avatar_path,
                (SELECT COUNT(DISTINCT user_id) FROM likes WHERE post_id = p.id OR (p.media_id IS NOT NULL AND media_id = p.media_id)) AS like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND is_deleted = 0) +
                    CASE WHEN p.media_id IS NOT NULL THEN (SELECT COUNT(*) FROM comments WHERE media_id = p.media_id AND is_deleted = 0) ELSE 0 END AS comment_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) +
                    CASE WHEN p.media_id IS NOT NULL THEN (SELECT COUNT(*) FROM likes WHERE media_id = p.media_id AND user_id = ?) ELSE 0 END AS user_liked
         FROM posts p JOIN users u ON u.id = p.user_id
         WHERE p.user_id = ? AND p.is_deleted = 0
         ORDER BY p.created_at DESC
         LIMIT ' . ($profilePostsLimit + 1),
        [(int)$currentUser['id'], (int)$currentUser['id'], $profileId]
    );
}
$profilePostsHasMore = count($posts) > $profilePostsLimit;
if ($profilePostsHasMore) {
    array_pop($posts);
}

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
        <?php if (!empty($profileUser['full_name'])): ?>
        <p class="profile-fullname"><?= e($profileUser['full_name']) ?></p>
        <?php endif; ?>
        <p class="profile-joined">Joined <?= e(date('M Y', strtotime($profileUser['created_at']))) ?></p>

        <?php if (!empty($profileUser['bio'])): ?>
        <p class="profile-bio"><?= nl2br(linkify(smilify($profileUser['bio']))) ?></p>
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

        <!-- Blog link -->
        <a href="<?= e(SITE_URL . '/pages/blog.php?user_id=' . $profileId) ?>"
           class="btn btn-sm btn-secondary profile-gallery-btn">View Blog</a>

        <!-- Friend button (only when viewing someone else's profile) -->
        <?php if (!$isOwnProfile): ?>
        <?php include SITE_ROOT . '/modules/friends/friend_button.php'; ?>
        <?php endif; ?>

        <!-- Friends widget -->
        <?php include SITE_ROOT . '/modules/friends/widget_friends.php'; ?>

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
                        <label>Full Name</label>
                        <input type="text" value="<?= e($currentUser['full_name'] ?? '') ?>" disabled
                               class="input-disabled">
                        <small>Full name cannot be changed.</small>
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio / About</label>
                        <textarea id="bio" name="bio" rows="5" maxlength="1000"><?= e($currentUser['bio'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>
            </section>

            <!-- Change email -->
            <section class="settings-section">
                <h2>Email Address</h2>
                <form method="POST" class="settings-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_email">

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                               value="<?= e($currentUser['email']) ?>"
                               autocomplete="email" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Email</button>
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

            <!-- Privacy settings -->
            <section class="settings-section">
                <h2>🔒 Privacy Settings</h2>
                <form method="POST" action="<?= e(SITE_URL . '/modules/profile/save_privacy.php') ?>"
                      class="settings-form">
                    <?= csrf_field() ?>

                    <?php
                    $privacyLabels = [
                        'view_profile' => 'Who can see my profile?',
                        'view_wall'    => 'Who can see my wall posts?',
                        'view_photos'  => 'Who can see my photos?',
                        'view_videos'  => 'Who can see my videos?',
                        'view_blog'    => 'Who can see my blog?',
                        'send_message' => 'Who can send me messages?',
                    ];
                    foreach ($privacyLabels as $key => $label):
                        $currentVal = PrivacyService::get((int) $currentUser['id'], $key);
                    ?>
                    <div class="form-group">
                        <label for="privacy_<?= e($key) ?>"><?= e($label) ?></label>
                        <select id="privacy_<?= e($key) ?>" name="<?= e($key) ?>">
                            <?php foreach (PrivacyService::LABELS as $val => $display): ?>
                            <option value="<?= e($val) ?>"<?= $currentVal === $val ? ' selected' : '' ?>><?= e($display) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-primary">Save Privacy Settings</button>
                </form>
            </section>

            <!-- Danger zone -->
            <section class="settings-section danger-zone">
                <h2>Danger Zone</h2>
                <p class="danger-zone-desc">
                    Download a copy of all your original media before you go, then
                    permanently delete your account and all associated data.
                    Deletion <strong>cannot be undone</strong>.
                </p>
                <div class="danger-zone-actions">
                    <a href="<?= e(SITE_URL) ?>/modules/profile/download_media.php"
                       class="btn btn-secondary">
                        ⬇ Download My Media
                    </a>
                    <button type="button" class="btn btn-danger" id="open-delete-modal">
                        Delete My Account
                    </button>
                </div>
            </section>

        </div><!-- /.settings-layout -->

        <!-- ── Delete Account Modal ───────────────────────────────── -->
        <div id="delete-account-modal" class="delete-modal" style="display:none"
             role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            <div class="delete-modal-inner">
                <button type="button" class="delete-modal-close" aria-label="Close">&times;</button>

                <!-- Step 1 — Warning -->
                <div class="delete-step" id="delete-step-1">
                    <h2 id="delete-modal-title" class="delete-modal-title">⚠️ Delete Account</h2>
                    <p class="delete-modal-lead">
                        You are about to <strong>permanently delete</strong> your account.
                        The following data will be <strong>irreversibly erased</strong>:
                    </p>
                    <ul class="delete-consequences">
                        <li>All wall posts and comments</li>
                        <li>All uploaded images and videos (albums &amp; gallery)</li>
                        <li>All chat messages and conversations</li>
                        <li>All private messages (inbox &amp; sent)</li>
                        <li>All shoutbox entries</li>
                        <li>All likes and notifications</li>
                        <li>Your profile, avatar, and account information</li>
                    </ul>
                    <p class="delete-modal-tip">
                        💾 Want to keep your photos and videos?
                        <a href="<?= e(SITE_URL) ?>/modules/profile/download_media.php"
                           class="delete-download-link">Download your media archive</a>
                        before continuing.
                    </p>
                    <p class="delete-modal-warning">
                        There is <strong>no recovery</strong> after this step.
                        Your data will be wiped from the server immediately.
                    </p>
                    <div class="delete-modal-actions">
                        <button type="button" class="btn btn-secondary" id="delete-cancel-1">Cancel</button>
                        <button type="button" class="btn btn-danger" id="delete-next-1">I understand — Continue</button>
                    </div>
                </div>

                <!-- Step 2 — Confirmation text -->
                <div class="delete-step" id="delete-step-2" style="display:none">
                    <h2 class="delete-modal-title">⚠️ Confirm Deletion</h2>
                    <p class="delete-modal-lead">
                        To confirm, type <strong>DELETE</strong> in the box below:
                    </p>
                    <div class="form-group">
                        <input type="text" id="delete-confirm-text" class="delete-confirm-input"
                               placeholder="Type DELETE here" autocomplete="off" spellcheck="false">
                    </div>
                    <p id="delete-confirm-error" class="delete-confirm-error" style="display:none">
                        Please type DELETE (all caps) to proceed.
                    </p>
                    <div class="delete-modal-actions">
                        <button type="button" class="btn btn-secondary" id="delete-back-2">Back</button>
                        <button type="button" class="btn btn-danger" id="delete-next-2">Continue</button>
                    </div>
                </div>

                <!-- Step 3 — Password verification & final submit -->
                <div class="delete-step" id="delete-step-3" style="display:none">
                    <h2 class="delete-modal-title">🔑 Verify Your Identity</h2>
                    <p class="delete-modal-lead">
                        Enter your current password to authorize account deletion.
                        This is your <strong>last chance</strong> to cancel.
                    </p>
                    <form method="POST"
                          action="<?= e(SITE_URL) ?>/modules/profile/delete_account.php"
                          id="delete-account-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="confirm_text" id="delete-confirm-hidden" value="">
                        <div class="form-group">
                            <label for="delete-password">Current Password</label>
                            <input type="password" id="delete-password" name="delete_password"
                                   autocomplete="current-password" required>
                        </div>
                        <div class="delete-modal-actions">
                            <button type="button" class="btn btn-secondary" id="delete-back-3">Back</button>
                            <button type="submit" class="btn btn-danger">
                                Permanently Delete My Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="<?= ASSETS_URL ?>/js/delete-account.js"></script>
        <?php endif; ?>
    </aside>

    <!-- Recent Posts -->
    <main class="profile-posts"
          id="profile-post-feed"
          data-offset="<?= $profilePostsLimit ?>"
          data-has-more="<?= $profilePostsHasMore ? '1' : '0' ?>"
          data-profile-id="<?= (int)$profileId ?>">
        <h2>Recent Posts</h2>
        <?php if (!$canViewWall): ?>
        <p class="empty-state">This user's wall posts are private.</p>
        <?php elseif (empty($posts)): ?>
        <p class="empty-state">No posts yet.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <?php include SITE_ROOT . '/modules/wall/post_item.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php if ($profilePostsHasMore): ?>
    <div class="load-more-wrap" id="profile-load-more-wrap">
        <button class="btn btn-primary btn-load-more" id="profile-load-more-btn" type="button">
            Load More
        </button>
    </div>
    <?php endif; ?>

</div>

<?php
// Move Media Modal — only render for the profile owner when they have albums
if ($isOwnProfile):
    $wallOwnerAlbums = db_query(
        'SELECT a.id, a.title, c.title AS category_title
         FROM albums a
         LEFT JOIN album_categories c ON c.id = a.category_id AND c.is_deleted = 0
         WHERE a.user_id = ? AND a.is_deleted = 0
         ORDER BY c.title ASC, a.title ASC',
        [(int)$currentUser['id']]
    );
    if (!empty($wallOwnerAlbums)):
?>
<div id="move-media-modal" class="crop-modal" style="display:none"
     role="dialog" aria-modal="true" aria-label="Move Image to Album">
    <div class="crop-modal-inner">
        <h3>Move Image to Album</h3>
        <form method="POST" action="<?= e(SITE_URL . '/modules/wall/move_media.php') ?>" id="move-media-form">
            <?= csrf_field() ?>
            <input type="hidden" name="media_id" id="move-media-id" value="">
            <div style="margin-bottom:1rem">
                <label for="move-target-album" style="display:block;margin-bottom:0.35rem;font-weight:600">Destination album</label>
                <select name="target_album_id" id="move-target-album" style="width:100%">
                    <?php
                    $lastCat = false;
                    foreach ($wallOwnerAlbums as $a):
                        $catLabel = $a['category_title'] ?? null;
                        if ($catLabel !== $lastCat):
                            if ($lastCat !== false) echo '</optgroup>';
                            echo '<optgroup label="' . e($catLabel ?? '(Uncategorised)') . '">';
                            $lastCat = $catLabel;
                        endif;
                    ?>
                    <option value="<?= (int)$a['id'] ?>"><?= e($a['title']) ?></option>
                    <?php endforeach; ?>
                    <?php if ($lastCat !== false) echo '</optgroup>'; ?>
                </select>
            </div>
            <div class="crop-modal-actions">
                <button type="button" id="move-media-cancel" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Move</button>
            </div>
        </form>
    </div>
</div>
<?php endif; endif; ?>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
