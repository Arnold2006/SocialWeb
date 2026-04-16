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
 * friend_button.php — Reusable friend action buttons for a profile.
 *
 * Expected variables in calling scope:
 *   $profileId   int    — ID of the profile being viewed
 *   $currentUser array  — current logged-in user row
 */

declare(strict_types=1);

if (!isset($profileId, $currentUser)) {
    return;
}

require_once SITE_ROOT . '/modules/friends/FriendshipService.php';

$_friendStatus  = FriendshipService::getStatus((int) $currentUser['id'], (int) $profileId);
$_csrfToken     = csrf_token();
?>
<div class="friend-actions" id="friend-actions-<?= (int) $profileId ?>">
<?php if ($_friendStatus === 'none'): ?>
    <button class="btn btn-primary btn-sm friend-btn"
            data-action="request"
            data-profile-id="<?= (int) $profileId ?>"
            data-csrf="<?= e($_csrfToken) ?>">
        ➕ Add Friend
    </button>

<?php elseif ($_friendStatus === 'pending_sent'): ?>
    <span class="btn btn-secondary btn-sm" style="opacity:.65;cursor:default">Request Sent</span>
    <button class="btn btn-sm btn-danger friend-btn"
            data-action="cancel"
            data-profile-id="<?= (int) $profileId ?>"
            data-csrf="<?= e($_csrfToken) ?>">
        Cancel Request
    </button>

<?php elseif ($_friendStatus === 'pending_received'): ?>
    <button class="btn btn-primary btn-sm friend-btn"
            data-action="accept"
            data-profile-id="<?= (int) $profileId ?>"
            data-csrf="<?= e($_csrfToken) ?>">
        ✓ Accept
    </button>
    <button class="btn btn-secondary btn-sm friend-btn"
            data-action="decline"
            data-profile-id="<?= (int) $profileId ?>"
            data-csrf="<?= e($_csrfToken) ?>">
        ✕ Decline
    </button>

<?php elseif ($_friendStatus === 'friends'): ?>
    <span class="btn btn-secondary btn-sm" style="opacity:.8;cursor:default">✓ Friends</span>
    <button class="btn btn-sm btn-danger friend-btn"
            data-action="cancel"
            data-profile-id="<?= (int) $profileId ?>"
            data-csrf="<?= e($_csrfToken) ?>">
        Unfriend
    </button>
<?php endif; ?>
</div>
