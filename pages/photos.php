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
 * photos.php — Photos hub with two tabs:
 *   1. Members  — grid of all users; click a user to browse their albums
 *   2. My Albums — grid of the current user's own albums
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$currentUser = current_user();
$activeTab   = ($_GET['tab'] ?? 'members') === 'my_albums' ? 'my_albums' : 'members';

// Handle POST actions on My Albums tab
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'set_album_privacy') {
        $aId     = sanitise_int($_POST['album_id'] ?? 0);
        $privacy = $_POST['privacy'] ?? '';
        $allowed = ['everybody', 'members', 'friends_only', 'only_me'];
        if ($aId && in_array($privacy, $allowed, true)) {
            db_exec(
                'UPDATE albums SET privacy = ? WHERE id = ? AND user_id = ?',
                [$privacy, $aId, (int)$currentUser['id']]
            );
            flash_set('success', 'Album privacy updated.');
        }
        redirect(SITE_URL . '/pages/photos.php?tab=my_albums');
    }
}

$pageTitle = 'Photos';

/* ── Tab 1: Members ──────────────────────────────────────────── */
$search  = sanitise_string($_GET['search'] ?? '', 100);
$page    = max(1, sanitise_int($_GET['page'] ?? 1));
$perPage = 24;

$params = [];
$where  = 'WHERE u.is_banned = 0';

if (!empty($search)) {
    $where    .= ' AND (u.username LIKE ? OR u.bio LIKE ?)';
    $params[]  = '%' . $search . '%';
    $params[]  = '%' . $search . '%';
}

$total  = (int) db_val("SELECT COUNT(*) FROM users u $where", $params);
$pages  = (int) ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$limitSql  = (int) $perPage;
$offsetSql = (int) $offset;

$members = db_query(
    "SELECT u.id, u.username, u.bio, u.avatar_path
     FROM users u $where
     ORDER BY (u.id = ?) DESC, u.username ASC
     LIMIT {$limitSql} OFFSET {$offsetSql}",
    array_merge($params, [(int)$currentUser['id']])
);

/* ── Tab 1: Latest Photos (initial batch) ────────────────────── */
$latestPhotoLimit  = 20;
$latestPhotosBlockedIds = PrivacyService::blockedUsersByAction((int)$currentUser['id'], 'view_photos');
$latestExcludeSql  = '';
$latestExcludeParams = [];
if (!empty($latestPhotosBlockedIds)) {
    $ph = implode(',', array_fill(0, count($latestPhotosBlockedIds), '?'));
    $latestExcludeSql   = " AND m.user_id NOT IN ($ph)";
    $latestExcludeParams = $latestPhotosBlockedIds;
}
$latestFetchLimit = $latestPhotoLimit + 1;
$latestPhotos = db_query(
    "SELECT m.id, m.user_id, m.type, m.width, m.height,
            m.thumb_path, m.medium_path, m.large_path, m.storage_path,
            u.username, u.avatar_path
     FROM media m
     JOIN albums a ON a.id = m.album_id AND a.is_deleted = 0
                  AND a.privacy IN ('everybody','members')
     JOIN users u  ON u.id = m.user_id  AND u.is_banned  = 0
     WHERE m.is_deleted = 0
       AND m.type = 'image'
       $latestExcludeSql
     ORDER BY m.created_at DESC
     LIMIT $latestFetchLimit",
    $latestExcludeParams
);
$latestHasMore = count($latestPhotos) > $latestPhotoLimit;
if ($latestHasMore) {
    array_pop($latestPhotos);
}

/* ── Tab 2: My Albums ────────────────────────────────────────── */
$myAlbums = db_query(
    'SELECT a.*, (SELECT COUNT(*) FROM media WHERE album_id = a.id AND is_deleted = 0) AS media_count
     FROM albums a
     WHERE a.user_id = ? AND a.is_deleted = 0
     ORDER BY a.created_at DESC',
    [(int)$currentUser['id']]
);

include SITE_ROOT . '/includes/header.php';
?>

<div class="two-col-layout">

    <!-- ── Left Column ─────────────────────────────────────────── -->
    <aside class="col-left">
        <?php include SITE_ROOT . '/includes/sidebar_widgets.php'; ?>
    </aside>

    <!-- ── Right Column ────────────────────────────────────────── -->
    <main class="col-right">

<div class="page-header">
    <h1>Photos</h1>
</div>

<!-- Tab Navigation -->
<div class="photos-tabs">
    <a href="<?= e(SITE_URL . '/pages/photos.php') ?>"
       class="photos-tab-btn<?= $activeTab === 'members' ? ' active' : '' ?>">Members</a>
    <a href="<?= e(SITE_URL . '/pages/photos.php?tab=my_albums') ?>"
       class="photos-tab-btn<?= $activeTab === 'my_albums' ? ' active' : '' ?>">My Albums</a>
</div>

<?php if ($activeTab === 'members'): ?>
<!-- ── Tab 1: Members ──────────────────────────────────────────── -->
<div class="photos-tab-panel">
    <form method="GET" class="search-form">
        <input type="hidden" name="tab" value="members">
        <input type="text" name="search" value="<?= e($search) ?>"
               placeholder="Search members…" class="search-input">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
        <a href="<?= e(SITE_URL . '/pages/photos.php') ?>" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($members)): ?>
    <p class="empty-state">No members found.</p>
    <?php else: ?>
    <div class="members-grid">
        <?php foreach ($members as $member): ?>
        <div class="member-card">
            <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$member['id']) ?>">
                <img src="<?= e(avatar_url($member, 'medium')) ?>"
                     alt="<?= e($member['username']) ?>"
                     class="member-avatar" width="100" height="100" loading="lazy">
            </a>
            <div class="member-info">
                <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$member['id']) ?>"
                   class="member-username"><?= e($member['username']) ?></a>
                <?php if (!empty($member['bio'])): ?>
                <p class="member-bio"><?= e(mb_substr($member['bio'], 0, 100)) ?><?= mb_strlen($member['bio'] ?? '') > 100 ? '…' : '' ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php
    $baseUrl = SITE_URL . '/pages/photos.php?tab=members' . ($search ? '&search=' . urlencode($search) : '');
    echo pagination_links($page, $pages, $baseUrl);
    ?>
    <?php endif; ?>

    <!-- ── Latest Photos ──────────────────────────────────────────── -->
    <div class="photos-latest-section">
        <h2>Latest Photos</h2>
        <?php if (empty($latestPhotos)): ?>
        <p class="empty-state">No photos have been shared yet.</p>
        <?php else: ?>
        <div class="photos-latest-grid" id="photos-latest-grid"
             data-offset="<?= count($latestPhotos) ?>"
             data-has-more="<?= $latestHasMore ? '1' : '0' ?>">
            <?php foreach ($latestPhotos as $media):
                include SITE_ROOT . '/modules/gallery/latest_photo_item.php';
            endforeach; ?>
        </div>
        <div id="photos-load-more-wrap" class="photos-load-more-wrap">
            <button id="photos-load-more-btn" type="button" class="btn btn-secondary btn-load-more">Load more</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ── Tab 2: My Albums ─────────────────────────────────────────── -->
<div class="photos-tab-panel">
    <div class="photos-my-albums-header">
        <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$currentUser['id']) ?>"
           class="btn btn-primary btn-sm">Manage Albums</a>
    </div>

    <?php if (empty($myAlbums)): ?>
    <p class="empty-state">You have no albums yet.
        <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$currentUser['id']) ?>">Create one</a>.
    </p>
    <?php else: ?>
    <div class="albums-grid">
        <?php foreach ($myAlbums as $album): ?>
        <div class="album-card">
            <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$currentUser['id'] . '&album=' . (int)$album['id']) ?>">
                <div class="album-cover">
                    <?php
                    $coverUrl = null;
                    if (!empty($album['cover_path'])) {
                        $coverUrl = SITE_URL . $album['cover_path'];
                    } elseif (!empty($album['cover_id'])) {
                        $coverMedia = db_row(
                            'SELECT thumb_path, thumbnail_path FROM media WHERE id = ? AND is_deleted = 0',
                            [(int)$album['cover_id']]
                        );
                        if ($coverMedia) {
                            if (!empty($coverMedia['thumb_path'])) {
                                $coverUrl = get_media_url($coverMedia, 'thumb');
                            } elseif (!empty($coverMedia['thumbnail_path'])) {
                                $coverUrl = get_media_url($coverMedia, 'thumbnail');
                            }
                        }
                    }
                    if (!$coverUrl) {
                        $firstImg = db_row(
                            'SELECT thumb_path, thumbnail_path FROM media WHERE album_id = ? AND is_deleted = 0 ORDER BY created_at ASC LIMIT 1',
                            [(int)$album['id']]
                        );
                        if ($firstImg) {
                            if (!empty($firstImg['thumb_path'])) {
                                $coverUrl = get_media_url($firstImg, 'thumb');
                            } elseif (!empty($firstImg['thumbnail_path'])) {
                                $coverUrl = get_media_url($firstImg, 'thumbnail');
                            }
                        }
                    }
                    ?>
                    <?php if ($coverUrl): ?>
                    <img src="<?= e($coverUrl) ?>" alt="" loading="lazy">
                    <?php else: ?>
                    <div class="album-cover-placeholder">📁</div>
                    <?php endif; ?>
                </div>
                <h3 class="album-title"><?= e($album['title']) ?></h3>
                <p class="album-count"><?= (int)$album['media_count'] ?> items</p>
            </a>
            <div class="album-actions">
                <!-- Security / privacy -->
                <button type="button" class="btn btn-secondary btn-xs"
                        data-toggle="security-myalbum-form-<?= (int)$album['id'] ?>">Security</button>
                <div id="security-myalbum-form-<?= (int)$album['id'] ?>" class="hidden inline-form-row">
                    <form method="POST" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="set_album_privacy">
                        <input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
                        <select name="privacy">
                            <?php foreach (PrivacyService::LABELS as $pVal => $pLabel): ?>
                            <option value="<?= $pVal ?>"<?= ($album['privacy'] ?? 'members') === $pVal ? ' selected' : '' ?>><?= $pLabel ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-xs">Save</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

    </main>

</div><!-- /.two-col-layout -->

<?php if ($activeTab === 'members'): ?>
<script src="<?= ASSETS_URL ?>/js/photos_latest.js"></script>
<?php endif; ?>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
