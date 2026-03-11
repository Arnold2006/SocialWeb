<?php
/**
 * login.php — Login page
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(SITE_URL . '/pages/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // csrf_verify() calls die() on a bad token, so keep it outside the try block.
    csrf_verify();

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!rate_limit('login_' . $ip, 10, 300)) {
            $error = 'Too many login attempts. Please wait a few minutes.';
        } else {
            $usernameOrEmail = sanitise_string($_POST['username'] ?? '', 255);
            $password        = $_POST['password'] ?? '';

            if (empty($usernameOrEmail) || empty($password)) {
                $error = 'Please fill in all fields.';
            } else {
                $user = login($usernameOrEmail, $password);
                if ($user === null) {
                    $error = 'Invalid username or password.';
                } else {
                    $redirect = sanitise_string($_GET['redirect'] ?? '', 500);
                    // Only allow redirects to our own site
                    if (empty($redirect) || !str_starts_with($redirect, '/')) {
                        $redirect = SITE_URL . '/pages/index.php';
                    } else {
                        $redirect = SITE_URL . $redirect;
                    }
                    redirect($redirect);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Login error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (SITE_DEBUG) {
            $error = get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine();
        } else {
            $error = 'A system error occurred. Please try again later.';
        }
    }
}

$pageTitle = 'Login';
$siteTheme = active_theme();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($siteTheme) ?>">
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
        <h2>Sign In</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= e(SITE_URL . '/pages/login.php') ?>" class="auth-form">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username"
                       value="<?= e($_POST['username'] ?? '') ?>"
                       autocomplete="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <p class="auth-alt">
            Don't have an account?
            <a href="<?= e(SITE_URL . '/pages/register.php') ?>">Register with invite code</a>
        </p>
    </div>
</div>

<script src="<?= ASSETS_URL ?>/js/app.js"></script>
</body>
</html>
