<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * widget_friends.php — Sidebar widget: friend count for a profile.
 *
 * Expected variables in calling scope:
 *   $profileId  int  — ID of the profile being viewed
 */

declare(strict_types=1);

if (!isset($profileId)) {
    return;
}

require_once SITE_ROOT . '/modules/friends/FriendshipService.php';

$_friendIds   = FriendshipService::getFriendIds((int) $profileId);
$_friendCount = count($_friendIds);
?>
<div class="sidebar-widget friends-widget">
    <h3>Friends</h3>
    <p>
        <a href="<?= e(SITE_URL . '/pages/friends.php?user_id=' . (int) $profileId) ?>">
            <?= (int) $_friendCount ?> friend<?= $_friendCount !== 1 ? 's' : '' ?>
        </a>
    </p>
</div>
