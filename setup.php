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

            // Run any pending migrations automatically
            db()->exec("
                CREATE TABLE IF NOT EXISTS `db_migrations` (
                    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `migration`  VARCHAR(255) NOT NULL UNIQUE,
                    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $migrationsDir = __DIR__ . '/database/migrations';
            $migrationFiles = is_dir($migrationsDir) ? (glob($migrationsDir . '/*.sql') ?: []) : [];
            sort($migrationFiles);

            $migrationResults = [];
            foreach ($migrationFiles as $migFile) {
                $migName = basename($migFile);
                $already = db_val('SELECT COUNT(*) FROM db_migrations WHERE migration = ?', [$migName]);
                if ((int)$already > 0) {
                    $deleted = @unlink($migFile);
                    $migrationResults[] = $migName . ': already applied — ' . ($deleted ? 'file removed' : 'file could not be removed');
                    continue;
                }
                try {
                    $migSql = file_get_contents($migFile);
                    foreach (array_filter(array_map('trim', explode(';', $migSql))) as $stmt) {
                        try {
                            db()->exec($stmt);
                        } catch (\Throwable $stmtEx) {
                            $errInfo = ($stmtEx instanceof \PDOException) ? $stmtEx->errorInfo : [];
                            $errCode = is_array($errInfo) && isset($errInfo[1]) ? (int)$errInfo[1] : 0;
                            if (!in_array($errCode, [1050, 1060], true)) {
                                throw $stmtEx;
                            }
                        }
                    }
                    db_insert('INSERT INTO db_migrations (migration) VALUES (?)', [$migName]);
                    $deleted = @unlink($migFile);
                    $migrationResults[] = $migName . ': applied successfully — ' . ($deleted ? 'file deleted' : 'file could not be deleted');
                } catch (\Throwable $migEx) {
                    $migrationResults[] = $migName . ': error — ' . $migEx->getMessage();
                    break;
                }
            }

            // Write lock file
            file_put_contents($lockFile, date('Y-m-d H:i:s'));

            $migInfo = '';
            if ($migrationResults) {
                $migInfo = '<br><strong>Migrations applied:</strong><ul style="margin:.25rem 0 0 1rem">'
                    . implode('', array_map(fn($r) => '<li>' . e($r) . '</li>', $migrationResults))
                    . '</ul>';
            }

            $success = 'Setup complete! Admin user created. Your first invite code is: <strong>' . e($inviteCode) . '</strong>'
                     . $migInfo
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
        <div class="alert alert-error"><?= e($error) ?></div>
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
