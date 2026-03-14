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
 * upload_avatar.php — Handle avatar upload
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(SITE_URL . '/pages/index.php');
csrf_verify();

$user = current_user();

if (empty($_FILES['avatar']['name'])) {
    flash_set('error', 'No file selected.');
    redirect(SITE_URL . '/pages/profile.php?id=' . (int)$user['id']);
}

$crop = [];
if (isset($_POST['crop_x'], $_POST['crop_y'], $_POST['crop_w'], $_POST['crop_h'])
    && is_numeric($_POST['crop_x']) && is_numeric($_POST['crop_y'])
    && is_numeric($_POST['crop_w']) && is_numeric($_POST['crop_h'])) {
    $crop = [
        'x' => (int)$_POST['crop_x'],
        'y' => (int)$_POST['crop_y'],
        'w' => (int)$_POST['crop_w'],
        'h' => (int)$_POST['crop_h'],
    ];
}

$result = process_avatar_upload($_FILES['avatar'], (int)$user['id'], $crop);

if ($result['ok']) {
    flash_set('success', 'Avatar updated.');
} else {
    flash_set('error', 'Upload failed: ' . $result['error']);
}

redirect(SITE_URL . '/pages/profile.php?id=' . (int)$user['id']);
