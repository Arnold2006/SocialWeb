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
 * media.php — Admin media management
 *
 * Three-level hierarchical navigation:
 *   /admin/media.php                       → List of users with media
 *   /admin/media.php?user_id=X             → List of albums for user X
 *   /admin/media.php?user_id=X&album_id=Y  → Media in album Y for user X
 *                                            (album_id=0 means unallocated / no album)
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$pageTitle = 'Admin – Media';

// ── POST: delete a media item ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action  = $_POST['action'] ?? '';
    $mediaId = sanitise_int($_POST['media_id'] ?? 0);

    if ($action === 'delete' && $mediaId > 0) {
        $media = db_row('SELECT * FROM media WHERE id = ?', [$mediaId]);
        if ($media) {
            // Remove files — verify each path is within UPLOADS_DIR before deletion
            $uploadsReal = realpath(UPLOADS_DIR);
            foreach (['storage_path', 'large_path', 'medium_path', 'thumb_path', 'thumbnail_path'] as $field) {
                if (empty($media[$field])) {
                    continue;
                }
                $real = realpath($media[$field]);
                if ($real !== false
                    && $uploadsReal !== false
                    && str_starts_with($real, $uploadsReal . DIRECTORY_SEPARATOR)
                    && file_exists($real)) {
                    @unlink($real);
                }
            }
            db_exec('UPDATE media SET is_deleted = 1 WHERE id = ?', [$mediaId]);
            flash_set('success', 'Media deleted.');
        }
    }

    // Redirect back to the same view level after deletion
    $returnUrl    = SITE_URL . '/admin/media.php';
    $returnUserId  = sanitise_int($_POST['return_user_id']  ?? 0);
    $returnAlbumId = sanitise_int($_POST['return_album_id'] ?? -1);
    if ($returnUserId > 0) {
        $returnUrl .= '?user_id=' . $returnUserId;
        if ($returnAlbumId >= 0) {
            $returnUrl .= '&album_id=' . $returnAlbumId;
        }
    }
    redirect($returnUrl);
}

// ── GET: determine view level from query parameters ───────────────────────────
$userId  = sanitise_int($_GET['user_id'] ?? 0);
// album_id=-1 means the parameter was not present in the URL
$albumId = isset($_GET['album_id']) ? sanitise_int($_GET['album_id']) : -1;

// ── Level 3: media grid for a specific album (or unallocated media) ───────────
if ($userId > 0 && $albumId >= 0) {
    $user = db_row('SELECT id, username FROM users WHERE id = ?', [$userId]);
    if (!$user) {
        flash_set('error', 'User not found.');
        redirect(SITE_URL . '/admin/media.php');
    }

    if ($albumId === 0) {
        // Unallocated media — not assigned to any album
        $albumTitle = 'No Album';
        $totalMedia = (int) db_val(
            'SELECT COUNT(*) FROM media WHERE user_id = ? AND album_id IS NULL AND is_deleted = 0',
            [$userId]
        );
        $page    = max(1, sanitise_int($_GET['page'] ?? 1));
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;
        $pages   = (int) ceil($totalMedia / $perPage);
        $mediaList = db_query(
            'SELECT * FROM media
             WHERE user_id = ? AND album_id IS NULL AND is_deleted = 0
             ORDER BY created_at DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            [$userId]
        );
    } else {
        // Specific album
        $album = db_row(
            'SELECT id, title FROM albums WHERE id = ? AND user_id = ? AND is_deleted = 0',
            [$albumId, $userId]
        );
        if (!$album) {
            flash_set('error', 'Album not found.');
            redirect(SITE_URL . '/admin/media.php?user_id=' . $userId);
        }
        $albumTitle = $album['title'];
        $totalMedia = (int) db_val(
            'SELECT COUNT(*) FROM media WHERE album_id = ? AND is_deleted = 0',
            [$albumId]
        );
        $page    = max(1, sanitise_int($_GET['page'] ?? 1));
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;
        $pages   = (int) ceil($totalMedia / $perPage);
        $mediaList = db_query(
            'SELECT * FROM media
             WHERE album_id = ? AND is_deleted = 0
             ORDER BY created_at DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            [$albumId]
        );
    }
    $view = 'media';

// ── Level 2: albums for a specific user ──────────────────────────────────────
} elseif ($userId > 0) {
    $user = db_row('SELECT id, username FROM users WHERE id = ?', [$userId]);
    if (!$user) {
        flash_set('error', 'User not found.');
        redirect(SITE_URL . '/admin/media.php');
    }

    $albumList = db_query(
        'SELECT a.id, a.title, COUNT(m.id) AS media_count
         FROM albums a
         LEFT JOIN media m ON m.album_id = a.id AND m.is_deleted = 0
         WHERE a.user_id = ? AND a.is_deleted = 0
         GROUP BY a.id
         ORDER BY a.title ASC',
        [$userId]
    );

    $unallocatedCount = (int) db_val(
        'SELECT COUNT(*) FROM media WHERE user_id = ? AND album_id IS NULL AND is_deleted = 0',
        [$userId]
    );
    $view = 'albums';

// ── Level 1: all users who have at least one media item ───────────────────────
} else {
    $userList = db_query(
        'SELECT u.id, u.username, COUNT(m.id) AS media_count
         FROM users u
         JOIN media m ON m.user_id = u.id AND m.is_deleted = 0
         GROUP BY u.id
         ORDER BY u.username ASC'
    );
    $view = 'users';
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
            <li><a href="<?= SITE_URL ?>/admin/media.php" class="active">Media</a></li>
            <li><a href="<?= SITE_URL ?>/admin/settings.php">Site Settings</a></li>
            <li><a href="<?= SITE_URL ?>/admin/orphans.php">Orphan Cleanup</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>Media Management</h1>

        <?= flash_render() ?>

        <nav class="media-admin-breadcrumb">
            <a href="<?= SITE_URL ?>/admin/media.php">All Users</a>
            <?php if ($view === 'albums' || $view === 'media'): ?>
                &rsaquo; <a href="<?= SITE_URL ?>/admin/media.php?user_id=<?= (int)$user['id'] ?>"><?= e($user['username']) ?></a>
            <?php endif; ?>
            <?php if ($view === 'media'): ?>
                &rsaquo; <span><?= e($albumTitle) ?></span>
            <?php endif; ?>
        </nav>

        <?php if ($view === 'users'): ?>

        <div class="admin-section">
            <?php if (empty($userList)): ?>
                <p class="text-muted">No media found.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Media Count</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userList as $row): ?>
                        <tr>
                            <td><?= e($row['username']) ?></td>
                            <td><?= (int)$row['media_count'] ?></td>
                            <td>
                                <a href="<?= SITE_URL ?>/admin/media.php?user_id=<?= (int)$row['id'] ?>"
                                   class="btn btn-xs btn-secondary">View Albums</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php elseif ($view === 'albums'): ?>

        <div class="admin-section">
            <?php if (empty($albumList) && $unallocatedCount === 0): ?>
                <p class="text-muted">No media for this user.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Album</th>
                            <th>Media Count</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($albumList as $row): ?>
                        <tr>
                            <td><?= e($row['title']) ?></td>
                            <td><?= (int)$row['media_count'] ?></td>
                            <td>
                                <a href="<?= SITE_URL ?>/admin/media.php?user_id=<?= (int)$user['id'] ?>&amp;album_id=<?= (int)$row['id'] ?>"
                                   class="btn btn-xs btn-secondary">View Media</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($unallocatedCount > 0): ?>
                        <tr>
                            <td><em>No Album</em></td>
                            <td><?= $unallocatedCount ?></td>
                            <td>
                                <a href="<?= SITE_URL ?>/admin/media.php?user_id=<?= (int)$user['id'] ?>&amp;album_id=0"
                                   class="btn btn-xs btn-secondary">View Media</a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php else: ?>

        <div class="media-admin-grid">
            <?php foreach ($mediaList as $media): ?>
            <div class="media-admin-item">
                <?php if ($media['type'] === 'image'): ?>
                <a href="<?= e(get_media_url($media, 'original')) ?>"
                   class="lightbox-trigger media-admin-thumb-link"
                   data-src="<?= e(get_media_url($media, 'large')) ?>">
                    <img src="<?= e(get_media_url($media, 'thumb')) ?>"
                         alt="" loading="lazy" class="media-admin-thumb">
                </a>
                <?php else: ?>
                <a href="<?= e(get_media_url($media, 'original')) ?>"
                   class="lightbox-trigger media-admin-thumb-link"
                   data-video-src="<?= e(get_media_url($media, 'original')) ?>">
                    <?php if (!empty($media['thumbnail_path'])): ?>
                    <img src="<?= e(get_media_url($media, 'thumbnail')) ?>"
                         alt="" loading="lazy" class="media-admin-thumb">
                    <?php else: ?>
                    <div class="media-admin-video-icon">🎥</div>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <div class="media-admin-info">
                    <span class="media-admin-type"><?= e($media['type']) ?></span>
                    <span class="media-admin-size"><?= number_format($media['size'] / 1024, 1) ?> KB</span>
                </div>
                <form method="POST" class="media-admin-delete">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"           value="delete">
                    <input type="hidden" name="media_id"         value="<?= (int)$media['id'] ?>">
                    <input type="hidden" name="return_user_id"   value="<?= (int)$userId ?>">
                    <input type="hidden" name="return_album_id"  value="<?= (int)$albumId ?>">
                    <button class="btn btn-xs btn-danger" onclick="return confirm('Delete media?')">Delete</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>

        <?php
        $baseUrl = SITE_URL . '/admin/media.php?user_id=' . $userId . '&album_id=' . $albumId;
        echo pagination_links($page, $pages, $baseUrl);
        ?>

        <?php endif; ?>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
