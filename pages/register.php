<?php
/**
 * register.php — Invite-only registration page
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(SITE_URL . '/pages/index.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!rate_limit('register_' . $ip, 5, 3600)) {
        $error = 'Too many registration attempts. Please wait.';
    } else {
        $username   = sanitise_username($_POST['username'] ?? '');
        $email      = sanitise_email($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $password2  = $_POST['password2'] ?? '';
        $inviteCode = sanitise_string($_POST['invite_code'] ?? '', 64);

        if (empty($username) || empty($email) || empty($password) || empty($inviteCode)) {
            $error = 'All fields are required.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = 'Username must be 3–50 characters.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            $result = register_user($username, $email, $password, $inviteCode);
            if ($result['ok']) {
                flash_set('success', 'Registration successful! Please log in.');
                redirect(SITE_URL . '/pages/login.php');
            } else {
                $error = $result['error'];
            }
        }
    }
}

$pageTitle = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body class="auth-page">

<div class="auth-container">
    <div class="auth-box">
        <h1 class="auth-title"><?= e(SITE_NAME) ?></h1>
        <h2>Create Account</h2>
        <p class="auth-subtitle">An invite code is required to register.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= e(SITE_URL . '/pages/register.php') ?>" class="auth-form">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="invite_code">Invite Code</label>
                <input type="text" id="invite_code" name="invite_code"
                       value="<?= e($_POST['invite_code'] ?? $_GET['code'] ?? '') ?>"
                       placeholder="Enter your invite code" required>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= e($_POST['username'] ?? '') ?>"
                       minlength="3" maxlength="50"
                       pattern="[a-zA-Z0-9_\-]+"
                       title="Only letters, numbers, underscores, and hyphens"
                       autocomplete="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       minlength="8"
                       autocomplete="new-password" required>
            </div>

            <div class="form-group">
                <label for="password2">Confirm Password</label>
                <input type="password" id="password2" name="password2"
                       minlength="8"
                       autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>

        <p class="auth-alt">
            Already have an account?
            <a href="<?= e(SITE_URL . '/pages/login.php') ?>">Sign in</a>
        </p>
    </div>
</div>

<script src="<?= ASSETS_URL ?>/js/app.js"></script>
</body>
</html>
