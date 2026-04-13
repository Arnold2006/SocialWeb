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
 * dashboard.php — Admin dashboard
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$pageTitle = 'Admin Dashboard';

$stats = [
    'users'    => (int) db_val('SELECT COUNT(*) FROM users'),
    'posts'    => (int) db_val('SELECT COUNT(*) FROM posts WHERE is_deleted = 0'),
    'media'    => (int) db_val('SELECT COUNT(*) FROM media WHERE is_deleted = 0'),
    'invites'  => (int) db_val('SELECT COUNT(*) FROM invites WHERE is_disabled = 0'),
    'messages' => (int) db_val('SELECT COUNT(*) FROM messages WHERE is_draft = 0'),
    'banned'   => (int) db_val('SELECT COUNT(*) FROM users WHERE is_banned = 1'),
];

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <nav class="admin-nav">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?= SITE_URL ?>/admin/dashboard.php" class="active">Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php">Users</a></li>
            <li><a href="<?= SITE_URL ?>/admin/invites.php">Invites</a></li>
            <li><a href="<?= SITE_URL ?>/admin/moderation.php">Moderation</a></li>
            <li><a href="<?= SITE_URL ?>/admin/media.php">Media</a></li>
            <li><a href="<?= SITE_URL ?>/admin/settings.php">Site Settings</a></li>
            <li><a href="<?= SITE_URL ?>/admin/orphans.php">Orphan Cleanup</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
            <li><a href="<?= SITE_URL ?>/admin/forum/index.php">Forum Administration</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>Dashboard</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-value"><?= $stats['users'] ?></span>
                <span class="stat-label">Total Users</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $stats['banned'] ?></span>
                <span class="stat-label">Banned Users</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $stats['posts'] ?></span>
                <span class="stat-label">Posts</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $stats['media'] ?></span>
                <span class="stat-label">Media Files</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $stats['invites'] ?></span>
                <span class="stat-label">Active Invites</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $stats['messages'] ?></span>
                <span class="stat-label">Messages</span>
            </div>
        </div>

        <!-- Recent registrations -->
        <section class="admin-section">
            <h2>Recent Registrations</h2>
            <?php $recentUsers = db_query(
                'SELECT id, username, email, created_at, is_banned FROM users ORDER BY created_at DESC LIMIT 10'
            ); ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th><th>Username</th><th>Email</th><th>Joined</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentUsers as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$u['id']) ?>"><?= e($u['username']) ?></a></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e(date('Y-m-d', strtotime($u['created_at']))) ?></td>
                        <td><?= $u['is_banned'] ? '<span class="badge-danger">Banned</span>' : '<span class="badge-success">Active</span>' ?></td>
                        <td>
                            <a href="<?= e(SITE_URL . '/admin/users.php?search=' . urlencode($u['username'])) ?>"
                               class="btn btn-xs btn-secondary">Manage</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
