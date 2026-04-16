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
 * members.php — Members directory with pagination and search
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once SITE_ROOT . '/modules/friends/FriendshipService.php';

require_login();

$pageTitle   = 'Members';
$currentUser = current_user();
$search      = sanitise_string($_GET['search'] ?? '', 100);
$page        = max(1, sanitise_int($_GET['page'] ?? 1));
$perPage     = 24;

$params = [];
$where  = 'WHERE u.is_banned = 0';

if (!empty($search)) {
    $where    .= ' AND (u.username LIKE ? OR u.bio LIKE ?)';
    $params[]  = '%' . $search . '%';
    $params[]  = '%' . $search . '%';
}

// Apply privacy filter — exclude users with view_profile = 'only_me' (or friends_only, if not a friend)
try {
    $_privacyFilter = PrivacyService::visibleUsersFilter((int) $currentUser['id']);
    if (!empty($_privacyFilter['sql'])) {
        $where   .= ' ' . $_privacyFilter['sql'];
        $params   = array_merge($params, $_privacyFilter['params']);
    }
} catch (\Throwable $e) {
    // Privacy tables may not exist yet (pre-migration); ignore silently
}

$total  = (int) db_val("SELECT COUNT(*) FROM users u $where", $params);
$pages  = (int) ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

// Cast to ints for safe interpolation in LIMIT/OFFSET
$limitSql  = (int) $perPage;
$offsetSql = (int) $offset;

$members = db_query(
    "SELECT u.id, u.username, u.bio, u.avatar_path, u.created_at, u.last_seen
     FROM users u $where
     ORDER BY u.created_at DESC
     LIMIT {$limitSql} OFFSET {$offsetSql}",
    $params
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
    <h1>Members</h1>
    <form method="GET" class="search-form">
        <input type="text" name="search" value="<?= e($search) ?>"
               placeholder="Search members…" class="search-input">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
        <a href="<?= e(SITE_URL . '/pages/members.php') ?>" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($members)): ?>
<p class="empty-state">No members found.</p>
<?php else: ?>
<div class="members-grid">
    <?php foreach ($members as $member): ?>
    <div class="member-card">
        <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$member['id']) ?>">
            <img src="<?= e(avatar_url($member, 'medium')) ?>"
                 alt="<?= e($member['username']) ?>"
                 class="member-avatar" width="100" height="100" loading="lazy">
        </a>
        <div class="member-info">
            <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$member['id']) ?>"
               class="member-username"><?= e($member['username']) ?></a>
            <p class="member-last-online">Last online: <?php
                if (!$member['last_seen']) {
                    echo 'Never';
                } elseif (time() - strtotime($member['last_seen']) > 86400) {
                    echo e(date('M j, Y', strtotime($member['last_seen'])));
                } else {
                    echo e(time_ago($member['last_seen']));
                }
            ?></p>
            <?php if (!empty($member['bio'])): ?>
            <p class="member-bio"><?= e(mb_substr($member['bio'], 0, 100)) ?><?= mb_strlen($member['bio'] ?? '') > 100 ? '…' : '' ?></p>
            <?php endif; ?>
            <div class="member-links">
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$member['id']) ?>">Profile</a>
                <a href="<?= e(SITE_URL . '/pages/gallery.php?user_id=' . (int)$member['id']) ?>">Gallery</a>
            </div>
            <?php if ((int)$member['id'] !== (int)$currentUser['id']): ?>
            <?php $profileId = (int)$member['id']; include SITE_ROOT . '/modules/friends/friend_button.php'; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?= pagination_links($page, $pages, SITE_URL . '/pages/members.php' . ($search ? '?search=' . urlencode($search) : '')) ?>
<?php endif; ?>

    </main>

</div><!-- /.two-col-layout -->

<?php include SITE_ROOT . '/includes/footer.php'; ?>
