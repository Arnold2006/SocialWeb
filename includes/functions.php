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
 * functions.php — Shared utility functions
 *
 * This file acts as a loader for the individual function modules.
 * Utility functions are split by responsibility:
 *   functions/pagination.php   – paginate(), pagination_links()
 *   functions/notifications.php – unread counts, mark_thread_read(), notify_user()
 *   functions/media.php        – avatar_url(), get_media_url(), time_ago()
 *   functions/theme.php        – site_setting(), valid_themes(), active_theme()
 *   functions/cache.php        – flash_set(), flash_get(), flash_render()
 */

declare(strict_types=1);

require_once __DIR__ . '/functions/pagination.php';
require_once __DIR__ . '/functions/notifications.php';
require_once __DIR__ . '/functions/media.php';
require_once __DIR__ . '/functions/theme.php';
require_once __DIR__ . '/functions/cache.php';

// ── Core utilities ────────────────────────────────────────────────────────────

/**
 * Redirect to a URL.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Guard for JSON API endpoints.
 *
 * Sets the Content-Type header, verifies the user is logged in, checks the
 * request method, and (for POST) verifies the CSRF token.  Exits with a JSON
 * error response if any check fails.
 *
 * @param string $method  Expected HTTP method ('POST' or 'GET')
 * @return array          The current user row
 */
function json_api_guard(string $method = 'POST'): array
{
    header('Content-Type: application/json');

    if (!is_logged_in()) {
        http_response_code(401);
        die(json_encode(['ok' => false, 'error' => 'Not logged in']));
    }

    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        http_response_code(405);
        die(json_encode(['ok' => false, 'error' => 'Method not allowed']));
    }

    if (strtoupper($method) === 'POST') {
        csrf_verify();
    }

    return current_user();
}
