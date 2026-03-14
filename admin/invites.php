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
 * invites.php — Admin invite management
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$pageTitle   = 'Admin – Invites';
$currentUser = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $maxUses   = max(1, sanitise_int($_POST['max_uses'] ?? 1));
        $expiresAt = null;

        $rawExpiry = trim($_POST['expires_at'] ?? '');
        if (!empty($rawExpiry)) {
            $ts = strtotime($rawExpiry);
            if ($ts !== false && $ts > time()) {
                $expiresAt = date('Y-m-d H:i:s', $ts);
            }
        }

        $code = bin2hex(random_bytes(16)); // 32-char hex code

        db_insert(
            'INSERT INTO invites (code, created_by, max_uses, expires_at) VALUES (?, ?, ?, ?)',
            [$code, (int)$currentUser['id'], $maxUses, $expiresAt]
        );

        flash_set('success', 'Invite generated: ' . $code);

    } elseif ($action === 'disable') {
        $inviteId = sanitise_int($_POST['invite_id'] ?? 0);
        db_exec('UPDATE invites SET is_disabled = 1 WHERE id = ?', [$inviteId]);
        flash_set('success', 'Invite disabled.');

    } elseif ($action === 'enable') {
        $inviteId = sanitise_int($_POST['invite_id'] ?? 0);
        db_exec('UPDATE invites SET is_disabled = 0 WHERE id = ?', [$inviteId]);
        flash_set('success', 'Invite enabled.');
    }

    redirect(SITE_URL . '/admin/invites.php');
}

$invites = db_query(
    'SELECT i.*, u.username AS creator
     FROM invites i
     JOIN users u ON u.id = i.created_by
     ORDER BY i.created_at DESC'
);

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <nav class="admin-nav">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?= SITE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php">Users</a></li>
            <li><a href="<?= SITE_URL ?>/admin/invites.php" class="active">Invites</a></li>
            <li><a href="<?= SITE_URL ?>/admin/moderation.php">Moderation</a></li>
            <li><a href="<?= SITE_URL ?>/admin/media.php">Media</a></li>
            <li><a href="<?= SITE_URL ?>/admin/settings.php">Site Settings</a></li>
            <li><a href="<?= SITE_URL ?>/admin/orphans.php">Orphan Cleanup</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>Invite Codes</h1>

        <!-- Generate invite form -->
        <section class="admin-section">
            <h2>Generate Invite</h2>
            <form method="POST" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="generate">
                <div class="form-row">
                    <div class="form-group">
                        <label for="max_uses">Max Uses</label>
                        <input type="number" id="max_uses" name="max_uses"
                               value="1" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label for="expires_at">Expires At (optional)</label>
                        <input type="datetime-local" id="expires_at" name="expires_at">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Generate Code</button>
            </form>
        </section>

        <!-- Invite list -->
        <section class="admin-section">
            <h2>All Invites</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Code</th><th>Created By</th><th>Uses</th>
                        <th>Max</th><th>Expires</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invites as $inv): ?>
                    <tr>
                        <td><code><?= e($inv['code']) ?></code></td>
                        <td><?= e($inv['creator']) ?></td>
                        <td><?= (int)$inv['uses'] ?></td>
                        <td><?= (int)$inv['max_uses'] ?></td>
                        <td><?= $inv['expires_at'] ? e(date('Y-m-d H:i', strtotime($inv['expires_at']))) : 'Never' ?></td>
                        <td>
                            <?php if ($inv['is_disabled']): ?>
                            <span class="badge-danger">Disabled</span>
                            <?php elseif ($inv['expires_at'] && strtotime($inv['expires_at']) < time()): ?>
                            <span class="badge-warning">Expired</span>
                            <?php elseif ($inv['uses'] >= $inv['max_uses']): ?>
                            <span class="badge-warning">Exhausted</span>
                            <?php else: ?>
                            <span class="badge-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                                <?php if ($inv['is_disabled']): ?>
                                <button name="action" value="enable" class="btn btn-xs btn-success">Enable</button>
                                <?php else: ?>
                                <button name="action" value="disable" class="btn btn-xs btn-danger">Disable</button>
                                <?php endif; ?>
                            </form>

                            <!-- Copy invite link -->
                            <button type="button" class="btn btn-xs btn-secondary"
                                    onclick="navigator.clipboard.writeText('<?= e(SITE_URL . '/pages/register.php?code=' . $inv['code']) ?>')">
                                Copy Link
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
