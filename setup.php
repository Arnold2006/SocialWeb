<?php
/**
 * setup.php — One-time installation wizard
 *
 * Run this once to:
 *  1. Create the database tables (from schema.sql)
 *  2. Create the first admin user
 *
 * DELETE or rename this file after running it.
 */

declare(strict_types=1);

// Prevent re-running once setup is complete
$lockFile = __DIR__ . '/cache/setup.lock';
if (file_exists($lockFile)) {
    die('Setup has already been run. Delete cache/setup.lock to re-run.');
}

require_once __DIR__ . '/includes/bootstrap.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check — token is seeded in session on first GET load
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken   = $_SESSION['setup_csrf'] ?? '';
    if (empty($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
        $error = 'Invalid security token. Please reload and try again.';
    } else {
    $username = sanitise_username($_POST['username'] ?? '');
    $email    = sanitise_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Run SQL schema
            $schemaSql = file_get_contents(__DIR__ . '/database/schema.sql');
            // Execute multi-statement SQL
            db()->exec($schemaSql);

            // Create admin user (no invite required)
            $hash = password_hash($password, PASSWORD_BCRYPT);
            db_insert(
                'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)',
                [$username, $email, $hash, 'admin']
            );

            // Generate a first invite code
            $inviteCode = bin2hex(random_bytes(16));
            $adminId    = (int) db_val('SELECT id FROM users WHERE username = ?', [$username]);
            db_insert(
                'INSERT INTO invites (code, created_by, max_uses) VALUES (?, ?, 10)',
                [$inviteCode, $adminId]
            );

            // Write lock file
            file_put_contents($lockFile, date('Y-m-d H:i:s'));

            $success = 'Setup complete! Admin user created. Your first invite code is: <strong>' . e($inviteCode) . '</strong>'
                     . '<br><a href="' . e(SITE_URL . '/pages/login.php') . '">Click here to log in</a>';
        } catch (\Throwable $ex) {
            $error = 'Setup failed: ' . $ex->getMessage();
        }
    }
    } // end CSRF check else
}

// Generate setup CSRF token for the form
if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
}
$setupCsrfToken = $_SESSION['setup_csrf'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body class="auth-page">

<div class="auth-container">
    <div class="auth-box">
        <h1 class="auth-title"><?= e(SITE_NAME) ?></h1>
        <h2>Installation Setup</h2>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
        <?php else: ?>

        <p style="font-size:.85rem;color:var(--color-text-muted);margin-bottom:1rem">
            Create the first admin account. Delete this file after setup.
        </p>

        <form method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= e($setupCsrfToken) ?>">
            <div class="form-group">
                <label for="username">Admin Username</label>
                <input type="text" id="username" name="username"
                       value="<?= e($_POST['username'] ?? '') ?>"
                       minlength="3" maxlength="50" required>
            </div>
            <div class="form-group">
                <label for="email">Admin Email</label>
                <input type="email" id="email" name="email"
                       value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" minlength="8" required>
            </div>
            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Run Setup</button>
        </form>

        <?php endif; ?>
    </div>
</div>

</body>
</html>
