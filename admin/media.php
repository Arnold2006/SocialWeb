<?php
/**
 * media.php — Admin media management
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$pageTitle = 'Admin – Media';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action  = $_POST['action'] ?? '';
    $mediaId = sanitise_int($_POST['media_id'] ?? 0);

    if ($action === 'delete' && $mediaId > 0) {
        $media = db_row('SELECT * FROM media WHERE id = ?', [$mediaId]);
        if ($media) {
            // Remove files
            foreach (['storage_path', 'large_path', 'medium_path', 'thumb_path', 'thumbnail_path'] as $field) {
                if (!empty($media[$field]) && file_exists($media[$field])) {
                    @unlink($media[$field]);
                }
            }
            db_exec('UPDATE media SET is_deleted = 1 WHERE id = ?', [$mediaId]);
            flash_set('success', 'Media deleted.');
        }
    }
    redirect(SITE_URL . '/admin/media.php');
}

$page    = max(1, sanitise_int($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$total = (int) db_val('SELECT COUNT(*) FROM media WHERE is_deleted = 0');
$pages = (int) ceil($total / $perPage);

$mediaList = db_query(
    'SELECT m.*, u.username
     FROM media m JOIN users u ON u.id = m.user_id
     WHERE m.is_deleted = 0
     ORDER BY m.created_at DESC
     LIMIT ' . $perPage . ' OFFSET ' . $offset
);

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
            <li><a href="<?= SITE_URL ?>/admin/media.php" class="active">Media</a></li>
            <li><a href="<?= SITE_URL ?>/admin/settings.php">Site Settings</a></li>
            <li><a href="<?= SITE_URL ?>/admin/orphans.php">Orphan Cleanup</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>Media Management</h1>

        <div class="media-admin-grid">
            <?php foreach ($mediaList as $media): ?>
            <div class="media-admin-item">
                <?php if ($media['type'] === 'image'): ?>
                <img src="<?= e(get_media_url($media, 'thumb')) ?>"
                     alt="" loading="lazy" class="media-admin-thumb">
                <?php else: ?>
                <div class="media-admin-video-icon">🎥</div>
                <?php endif; ?>
                <div class="media-admin-info">
                    <span class="media-admin-user"><?= e($media['username']) ?></span>
                    <span class="media-admin-type"><?= e($media['type']) ?></span>
                    <span class="media-admin-size"><?= number_format($media['size'] / 1024, 1) ?> KB</span>
                </div>
                <form method="POST" class="media-admin-delete">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="media_id" value="<?= (int)$media['id'] ?>">
                    <button class="btn btn-xs btn-danger" onclick="return confirm('Delete media?')">Delete</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>

        <?= pagination_links($page, $pages, SITE_URL . '/admin/media.php') ?>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
