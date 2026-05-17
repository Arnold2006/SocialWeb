<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * plugins.php — Admin plugin management
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$pageTitle = 'Admin – Plugins';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $slug   = sanitise_string((string)($_POST['slug'] ?? ''), 100);

    if ($action === 'toggle_plugin' && $slug !== '') {
        $plugin = db_row('SELECT is_enabled FROM plugins WHERE slug = ? LIMIT 1', [$slug]);
        if ($plugin) {
            $nextEnabled = ((int)$plugin['is_enabled'] === 1) ? 0 : 1;
            db_exec('UPDATE plugins SET is_enabled = ? WHERE slug = ?', [$nextEnabled, $slug]);
            flash_set('success', $nextEnabled === 1 ? 'Plugin enabled.' : 'Plugin disabled.');
        } else {
            flash_set('error', 'Plugin not found.');
        }
    } else {
        flash_set('error', 'Invalid plugin action.');
    }

    redirect(SITE_URL . '/admin/plugins.php');
}

$hasDescriptionColumn = true;
try {
    $plugins = db_query(
        'SELECT name, slug, version, description, is_enabled
         FROM plugins
         ORDER BY name ASC'
    );
} catch (\Throwable $e) {
    $hasDescriptionColumn = false;
    $plugins = db_query(
        'SELECT name, slug, version, is_enabled
         FROM plugins
         ORDER BY name ASC'
    );
}

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <nav class="admin-nav">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?= SITE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php">Users</a></li>
            <li><a href="<?= SITE_URL ?>/admin/invites.php">Invites</a></li>
            <li><a href="<?= SITE_URL ?>/admin/moderation.php">Moderation</a></li>
            <li><a href="<?= SITE_URL ?>/admin/media.php">Media</a></li>
            <li><a href="<?= SITE_URL ?>/admin/plugins.php" class="active">Plugins</a></li>
            <li><a href="<?= SITE_URL ?>/admin/settings.php">Site Settings</a></li>
            <li><a href="<?= SITE_URL ?>/admin/orphans.php">Orphan Cleanup</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
            <li><a href="<?= SITE_URL ?>/admin/forum/index.php">Forum Administration</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>Plugins</h1>

        <?= flash_render() ?>

        <section class="admin-section">
            <?php if (empty($plugins)): ?>
            <p class="text-muted">No plugins found in the database.</p>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Plugin</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plugins as $plugin): ?>
                    <tr>
                        <td>
                            <strong><?= e($plugin['name']) ?></strong>
                            <span class="text-muted">v<?= e($plugin['version']) ?></span>
                            <?php if ($hasDescriptionColumn && !empty($plugin['description'])): ?>
                            <div class="text-muted" style="margin-top:.25rem"><?= e((string)$plugin['description']) ?></div>
                            <?php endif; ?>
                            <div class="text-muted" style="margin-top:.25rem"><code><?= e($plugin['slug']) ?></code></div>
                        </td>
                        <td>
                            <?php if ((int)$plugin['is_enabled'] === 1): ?>
                            <span class="badge-success">Enabled</span>
                            <?php else: ?>
                            <span class="badge-warning">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_plugin">
                                <input type="hidden" name="slug" value="<?= e($plugin['slug']) ?>">
                                <?php if ((int)$plugin['is_enabled'] === 1): ?>
                                <button type="submit" class="btn btn-xs btn-danger">Disable</button>
                                <?php else: ?>
                                <button type="submit" class="btn btn-xs btn-success">Enable</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
