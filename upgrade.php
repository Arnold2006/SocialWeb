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
 * upgrade.php — Database migration runner
 *
 * Applies any pending SQL migration scripts found in database/migrations/.
 * Migrations are run in filename order and each one is recorded in the
 * `db_migrations` table so it is never applied twice.
 *
 * Run this script from the web browser (admin access required) or via CLI
 * after deploying a new version.  Delete or restrict access to this file
 * once all migrations have been applied.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$isCli = (php_sapi_name() === 'cli');

// Only admins may run upgrades via the web; CLI is unrestricted (trusted process).
if (!$isCli) {
    $currentUser = current_user();
    if ($currentUser === null || $currentUser['role'] !== 'admin') {
        http_response_code(403);
        die('Access denied. Admin login required.');
    }
}

$error   = '';
$results = [];

// Ensure the migrations-tracking table exists
db()->exec("
    CREATE TABLE IF NOT EXISTS `db_migrations` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration`  VARCHAR(255) NOT NULL UNIQUE,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$migrationsDir = __DIR__ . '/database/migrations';
$files = is_dir($migrationsDir) ? (glob($migrationsDir . '/*.sql') ?: []) : [];
sort($files);

// CLI: run all pending migrations automatically and exit.
if ($isCli) {
    $anyPending = false;
    foreach ($files as $file) {
        $name    = basename($file);
        $already = db_val('SELECT COUNT(*) FROM db_migrations WHERE migration = ?', [$name]);
        if ((int)$already > 0) {
            $deleted = @unlink($file);
            echo "  [skip]  {$name}" . ($deleted ? ' (file removed)' : '') . PHP_EOL;
            continue;
        }

        $anyPending = true;
        echo "  [apply] {$name} ... ";
        try {
            $sql = file_get_contents($file);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                try {
                    db()->exec($stmt);
                } catch (\Throwable $stmtEx) {
                    $errCode = ($stmtEx instanceof \PDOException)
                        ? (int)($stmtEx->errorInfo[1] ?? 0)
                        : 0;
                    if (!in_array($errCode, [1050, 1060, 1091], true)) {
                        throw $stmtEx;
                    }
                }
            }
            db_insert('INSERT INTO db_migrations (migration) VALUES (?)', [$name]);
        } catch (\Throwable $ex) {
            echo "ERROR: " . $ex->getMessage() . PHP_EOL;
            exit(1);
        }

        $deleted = @unlink($file);
        echo "OK" . ($deleted ? ' (file removed)' : '') . PHP_EOL;
    }

    if (!$anyPending) {
        echo "  All migrations are already up to date." . PHP_EOL;
    }
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach ($files as $file) {
        $name = basename($file);

        // If already tracked, the SQL is already in the database — just remove the file.
        $already = db_val('SELECT COUNT(*) FROM db_migrations WHERE migration = ?', [$name]);
        if ((int)$already > 0) {
            $deleted = @unlink($file);
            $results[] = [
                'name'   => $name,
                'status' => 'skipped',
                'msg'    => $deleted ? 'Already applied — file removed' : 'Already applied (file could not be removed)',
            ];
            continue;
        }

        try {
            $sql = file_get_contents($file);
            // Execute each non-empty statement individually so that
            // multi-statement migration files work reliably across drivers.
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                try {
                    db()->exec($stmt);
                } catch (\Throwable $stmtEx) {
                    // MySQL/MariaDB-specific error codes (this application targets MySQL/MariaDB only):
                    //   1060: Duplicate column name — ALTER TABLE ADD COLUMN on an existing column.
                    //   1050: Table already exists — CREATE TABLE on a table already present in schema.sql.
                    //   1091: Can't DROP; check constraint doesn't exist — DROP CONSTRAINT on a
                    //         constraint that was never enforced (e.g. MySQL 5.7) or already removed.
                    // All three mean the structural change is already in the database.  We record the
                    // migration as applied and remove the file.  All other exceptions (including
                    // non-PDOException types) are re-thrown as real errors.
                    $errCode = ($stmtEx instanceof \PDOException)
                        ? (int)($stmtEx->errorInfo[1] ?? 0)
                        : 0;
                    if (!in_array($errCode, [1050, 1060, 1091], true)) {
                        throw $stmtEx;
                    }
                }
            }
            db_insert('INSERT INTO db_migrations (migration) VALUES (?)', [$name]);
        } catch (\Throwable $ex) {
            $results[] = ['name' => $name, 'status' => 'error', 'msg' => $ex->getMessage()];
            break; // stop on first error to avoid leaving the schema in an inconsistent state
        }

        $deleted = @unlink($file);
        $results[] = [
            'name'   => $name,
            'status' => 'ok',
            'msg'    => $deleted ? 'Applied successfully and file deleted' : 'Applied successfully (file could not be deleted)',
        ];
    }
}

// Determine pending migrations for display
$pendingCount = 0;
foreach ($files as $file) {
    $name    = basename($file);
    $already = db_val('SELECT COUNT(*) FROM db_migrations WHERE migration = ?', [$name]);
    if ((int)$already === 0) {
        $pendingCount++;
    }
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Upgrade — <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body class="auth-page">

<div class="auth-container">
    <div class="auth-box">
        <h1 class="auth-title"><?= e(SITE_NAME) ?></h1>
        <h2>Database Upgrade</h2>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($results): ?>
        <div class="alert alert-success">
            <strong>Migration run complete:</strong>
            <ul style="margin:.5rem 0 0 1rem">
            <?php foreach ($results as $r): ?>
                <li>
                    <strong><?= e($r['name']) ?></strong>:
                    <?php if ($r['status'] === 'error'): ?>
                        <span style="color:var(--color-danger)"><?= e($r['msg']) ?></span>
                    <?php else: ?>
                        <?= e($r['msg']) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($pendingCount > 0 && !$results): ?>
        <p style="font-size:.85rem;color:var(--color-text-muted);margin-bottom:1rem">
            <?= (int)$pendingCount ?> pending migration(s) found. Click the button below to apply them.
        </p>
        <?php elseif ($pendingCount === 0 && !$results): ?>
        <p style="font-size:.85rem;color:var(--color-text-muted);margin-bottom:1rem">
            All migrations are up to date. No action needed.
        </p>
        <?php endif; ?>

        <?php if ($pendingCount > 0): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <button type="submit" class="btn btn-primary btn-block">Apply Migrations</button>
        </form>
        <?php endif; ?>

        <p style="margin-top:1rem;font-size:.8rem;color:var(--color-text-muted)">
            <a href="<?= e(SITE_URL) ?>">← Back to site</a>
        </p>
    </div>
</div>

</body>
</html>
