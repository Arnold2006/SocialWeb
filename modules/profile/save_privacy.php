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
 * save_privacy.php — Handle privacy settings form submission
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/pages/profile.php?id=' . (int) current_user()['id']);
}

csrf_verify();

$user   = current_user();
$userId = (int) $user['id'];

// Collect only the known action keys from POST
$keys     = array_keys(PrivacyService::DEFAULTS);
$settings = [];

foreach ($keys as $key) {
    if (isset($_POST[$key])) {
        $settings[$key] = (string) $_POST[$key];
    }
}

PrivacyService::setAll($userId, $settings);

flash_set('success', 'Privacy settings saved.');
redirect(SITE_URL . '/pages/profile.php?id=' . $userId);
