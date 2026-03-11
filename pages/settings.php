<?php
/**
 * settings.php — User settings page (bio, password)
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pageTitle   = 'Settings';
$currentUser = current_user();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $bio = sanitise_string($_POST['bio'] ?? '', 1000);
        db_exec('UPDATE users SET bio = ? WHERE id = ?', [$bio, (int)$currentUser['id']]);
        $success = 'Profile updated.';
        // Refresh user
        $_SESSION['user_id'] = (int)$currentUser['id'];

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
}

// Refresh user data
$currentUser = db_row(
    'SELECT id, username, email, bio, avatar_path, role FROM users WHERE id = ?',
    [(int)$currentUser['id']]
);

include SITE_ROOT . '/includes/header.php';
?>

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

</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
