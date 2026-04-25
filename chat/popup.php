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
 * popup.php — Standalone chat window tab.
 *
 * Opened by the pop-out button (⊡) on a floating chat window.
 * No site nav, header, or footer — just the chat interface.
 *
 * GET /chat/popup.php?to=<userId>
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$currentUser = current_user();
$toId        = sanitise_int($_GET['to'] ?? 0);

if ($toId < 1 || $toId === (int) $currentUser['id']) {
    http_response_code(400);
    exit('Invalid recipient.');
}

// Fetch the target user
$toRows = db_query(
    'SELECT id, username, avatar_path FROM users WHERE id = ? AND is_banned = 0',
    [$toId]
);
if (empty($toRows)) {
    http_response_code(404);
    exit('User not found.');
}
$toUser = $toRows[0];

$siteTheme = active_theme();
$toAvatar  = avatar_url($toUser, 'small');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($siteTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat — <?= e($toUser['username']) ?></title>
    <meta name="site-url" content="<?= SITE_URL ?>">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body class="chat-popup-page">

<div id="chat-widget" data-user-id="<?= (int) $currentUser['id'] ?>">

    <input type="hidden" id="chat-csrf" value="<?= e(csrf_token()) ?>">

    <!-- Target user data for auto-open -->
    <div id="chat-popup-to" hidden
         data-user-id="<?= (int) $toUser['id'] ?>"
         data-username="<?= e($toUser['username']) ?>"
         data-avatar-url="<?= e($toAvatar) ?>"></div>

    <div id="chat-windows-container"></div>

    <!-- Sidebar is not shown in popup mode, but JS requires the element -->
    <div id="chat-sidebar" style="display:none" aria-hidden="true">
        <div class="chat-sidebar-header">
            <span class="chat-sidebar-title">Chat</span>
            <button id="chat-sidebar-close" class="chat-sidebar-close-btn" aria-label="Close chat">&#x2715;</button>
        </div>
        <div class="chat-sidebar-search">
            <input type="text" id="chat-user-search" placeholder="Find Contact" autocomplete="off">
        </div>
        <div id="chat-users-list" class="chat-users-list" role="list"></div>
    </div>

    <!-- Toggle button is not shown in popup mode -->
    <button id="chat-toggle" class="chat-toggle-btn" style="display:none" aria-hidden="true" tabindex="-1">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
        </svg>
    </button>

</div>

<script src="<?= ASSETS_URL ?>/js/chat.js"></script>
</body>
</html>
