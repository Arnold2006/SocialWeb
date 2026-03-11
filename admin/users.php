<?php
/**
 * users.php — Admin user management
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$pageTitle   = 'Admin – Users';
$currentUser = current_user();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $userId = sanitise_int($_POST['user_id'] ?? 0);

    if ($userId > 0 && $userId !== (int)$currentUser['id']) {
        switch ($action) {
            case 'ban':
                db_exec('UPDATE users SET is_banned = 1 WHERE id = ?', [$userId]);
                flash_set('success', 'User banned.');
                break;
            case 'unban':
                db_exec('UPDATE users SET is_banned = 0 WHERE id = ?', [$userId]);
                flash_set('success', 'User unbanned.');
                break;
            case 'delete':
                db_exec('UPDATE users SET is_banned = 1 WHERE id = ?', [$userId]);
                db_exec('UPDATE posts SET is_deleted = 1 WHERE user_id = ?', [$userId]);
                flash_set('success', 'User deleted and content removed.');
                break;
        }
    }
    redirect(SITE_URL . '/admin/users.php');
}

$search = sanitise_string($_GET['search'] ?? '', 100);
$page   = max(1, sanitise_int($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$params = [];
$where  = 'WHERE 1=1';

if ($search) {
    $where   .= ' AND (username LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$total     = (int) db_val("SELECT COUNT(*) FROM users $where", $params);
$pages     = (int) ceil($total / $perPage);
$limitSql  = (int) $perPage;
$offsetSql = (int) $offset;
$users     = db_query(
    "SELECT id, username, email, role, is_banned, created_at, last_login FROM users $where ORDER BY created_at DESC LIMIT {$limitSql} OFFSET {$offsetSql}",
    $params
);

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <nav class="admin-nav">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?= SITE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php" class="active">Users</a></li>
            <li><a href="<?= SITE_URL ?>/admin/invites.php">Invites</a></li>
            <li><a href="<?= SITE_URL ?>/admin/moderation.php">Moderation</a></li>
            <li><a href="<?= SITE_URL ?>/admin/media.php">Media</a></li>
            <li><a href="<?= SITE_URL ?>/admin/settings.php">Site Settings</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>Users</h1>

        <form method="GET" class="search-form">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search users…">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th><th>Username</th><th>Email</th><th>Role</th>
                    <th>Joined</th><th>Last Login</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$u['id']) ?>"><?= e($u['username']) ?></a></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['role']) ?></td>
                    <td><?= e(date('Y-m-d', strtotime($u['created_at']))) ?></td>
                    <td><?= $u['last_login'] ? e(date('Y-m-d', strtotime($u['last_login']))) : '—' ?></td>
                    <td><?= $u['is_banned'] ? '<span class="badge-danger">Banned</span>' : '<span class="badge-success">Active</span>' ?></td>
                    <td>
                        <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                        <form method="POST" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <?php if ($u['is_banned']): ?>
                            <button name="action" value="unban" class="btn btn-xs btn-success">Unban</button>
                            <?php else: ?>
                            <button name="action" value="ban" class="btn btn-xs btn-danger">Ban</button>
                            <?php endif; ?>
                            <button name="action" value="delete" class="btn btn-xs btn-danger"
                                    onclick="return confirm('Delete user and all content?')">Delete</button>
                        </form>
                        <?php else: ?>
                        <span class="muted">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= pagination_links($page, $pages, SITE_URL . '/admin/users.php' . ($search ? '?search=' . urlencode($search) : '')) ?>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
