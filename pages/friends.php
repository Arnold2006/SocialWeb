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
 * friends.php — Friends list, incoming requests, and sent requests
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once SITE_ROOT . '/modules/friends/FriendshipService.php';

require_login();

$currentUser = current_user();
$uid         = (int) $currentUser['id'];

// Optionally view another user's friends list (read-only)
$viewUserId  = sanitise_int($_GET['user_id'] ?? $uid);
$isOwnList   = ($viewUserId === $uid);

$viewUser = db_row('SELECT id, username FROM users WHERE id = ? AND is_banned = 0', [$viewUserId]);
if (!$viewUser) {
    flash_set('error', 'User not found.');
    redirect(SITE_URL . '/pages/members.php');
}

// Fetch data
$friendIds       = FriendshipService::getFriendIds($viewUserId);
$friends         = [];
if (!empty($friendIds)) {
    $phs     = implode(',', array_fill(0, count($friendIds), '?'));
    $friends = db_query(
        "SELECT id, username, avatar_path, bio FROM users WHERE id IN ($phs) AND is_banned = 0 ORDER BY username ASC",
        $friendIds
    );
}

$pendingRequests = $isOwnList ? FriendshipService::getPendingRequests($uid) : [];
$sentRequests    = $isOwnList ? FriendshipService::getSentRequests($uid)    : [];

$pageTitle = $isOwnList ? 'My Friends' : e($viewUser['username']) . "'s Friends";
$csrfToken = csrf_token();

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
    <h1><?= $isOwnList ? 'My Friends' : e($viewUser['username']) . "'s Friends" ?></h1>
</div>

<?php if ($isOwnList): ?>
<!-- Tab navigation -->
<div class="friends-tabs">
    <button class="btn btn-secondary btn-sm friends-tab-btn active" data-tab="friends">
        My Friends (<?= count($friends) ?>)
    </button>
    <button class="btn btn-secondary btn-sm friends-tab-btn" data-tab="requests">
        Requests
        <?php if (!empty($pendingRequests)): ?>
        <span class="badge"><?= count($pendingRequests) ?></span>
        <?php endif; ?>
    </button>
    <button class="btn btn-secondary btn-sm friends-tab-btn" data-tab="sent">
        Sent (<?= count($sentRequests) ?>)
    </button>
</div>
<?php endif; ?>

<!-- ── My Friends ─────────────────────────────────── -->
<div class="friends-tab-panel" id="tab-friends">
    <?php if (empty($friends)): ?>
    <p class="empty-state">No friends yet.</p>
    <?php else: ?>
    <div class="members-grid">
        <?php foreach ($friends as $f): ?>
        <div class="member-card">
            <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int) $f['id']) ?>">
                <img src="<?= e(avatar_url($f, 'medium')) ?>"
                     alt="<?= e($f['username']) ?>"
                     class="member-avatar" width="100" height="100" loading="lazy">
            </a>
            <div class="member-info">
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int) $f['id']) ?>"
                   class="member-username"><?= e($f['username']) ?></a>
                <?php if (!empty($f['bio'])): ?>
                <p class="member-bio"><?= e(mb_substr($f['bio'], 0, 80)) ?><?= mb_strlen($f['bio'] ?? '') > 80 ? '…' : '' ?></p>
                <?php endif; ?>
                <?php if ($isOwnList): ?>
                <button class="btn btn-danger btn-sm friend-action-btn mt-1"
                        data-action="cancel"
                        data-other-id="<?= (int) $f['id'] ?>"
                        data-csrf="<?= e($csrfToken) ?>">
                    Unfriend
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($isOwnList): ?>

<!-- ── Incoming Requests ──────────────────────────── -->
<div class="friends-tab-panel" id="tab-requests" style="display:none">
    <?php if (empty($pendingRequests)): ?>
    <p class="empty-state">No pending friend requests.</p>
    <?php else: ?>
    <div class="members-grid">
        <?php foreach ($pendingRequests as $req): ?>
        <div class="member-card" id="req-card-<?= (int) $req['requester_id'] ?>">
            <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int) $req['requester_id']) ?>">
                <img src="<?= e(avatar_url($req, 'medium')) ?>"
                     alt="<?= e($req['username']) ?>"
                     class="member-avatar" width="100" height="100" loading="lazy">
            </a>
            <div class="member-info">
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int) $req['requester_id']) ?>"
                   class="member-username"><?= e($req['username']) ?></a>
                <div class="member-links" style="margin-top:.5rem">
                    <button class="btn btn-primary btn-sm friend-action-btn"
                            data-action="accept"
                            data-other-id="<?= (int) $req['requester_id'] ?>"
                            data-csrf="<?= e($csrfToken) ?>">
                        ✓ Accept
                    </button>
                    <button class="btn btn-secondary btn-sm friend-action-btn"
                            data-action="decline"
                            data-other-id="<?= (int) $req['requester_id'] ?>"
                            data-csrf="<?= e($csrfToken) ?>">
                        ✕ Decline
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── Sent Requests ──────────────────────────────── -->
<div class="friends-tab-panel" id="tab-sent" style="display:none">
    <?php if (empty($sentRequests)): ?>
    <p class="empty-state">No sent friend requests.</p>
    <?php else: ?>
    <div class="members-grid">
        <?php foreach ($sentRequests as $req): ?>
        <div class="member-card" id="sent-card-<?= (int) $req['addressee_id'] ?>">
            <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int) $req['addressee_id']) ?>">
                <img src="<?= e(avatar_url($req, 'medium')) ?>"
                     alt="<?= e($req['username']) ?>"
                     class="member-avatar" width="100" height="100" loading="lazy">
            </a>
            <div class="member-info">
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int) $req['addressee_id']) ?>"
                   class="member-username"><?= e($req['username']) ?></a>
                <div class="member-links" style="margin-top:.5rem">
                    <button class="btn btn-danger btn-sm friend-action-btn"
                            data-action="cancel"
                            data-other-id="<?= (int) $req['addressee_id'] ?>"
                            data-csrf="<?= e($csrfToken) ?>">
                        Cancel Request
                    </button>
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

<script>
(function () {
    // Tab switching
    const tabBtns   = document.querySelectorAll('.friends-tab-btn');
    const tabPanels = document.querySelectorAll('.friends-tab-panel');

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanels.forEach(p => p.style.display = 'none');
            btn.classList.add('active');
            const panel = document.getElementById('tab-' + btn.dataset.tab);
            if (panel) panel.style.display = '';
        });
    });

    // Friend action buttons
    const BASE = <?= json_encode(SITE_URL) ?> + '/modules/friends/';
    const CSRF = <?= json_encode($csrfToken) ?>;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.friend-action-btn');
        if (!btn) return;

        const action  = btn.dataset.action;
        const otherId = btn.dataset.otherId;
        const csrf    = btn.dataset.csrf || CSRF;

        const endpointMap = {
            accept:  'ajax_accept.php',
            decline: 'ajax_decline.php',
            cancel:  'ajax_cancel.php',
        };

        const bodyMap = {
            accept:  'requester_id=' + otherId,
            decline: 'requester_id=' + otherId,
            cancel:  'other_id='     + otherId,
        };

        if (!endpointMap[action]) return;

        btn.disabled = true;

        fetch(BASE + endpointMap[action], {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'csrf_token=' + encodeURIComponent(csrf) + '&' + bodyMap[action],
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Remove the card from view
                const card = document.getElementById('req-card-' + otherId)
                          || document.getElementById('sent-card-' + otherId)
                          || btn.closest('.member-card');
                if (card) card.remove();
            } else {
                alert(data.message || 'Action failed.');
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
        });
    });
}());
</script>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
