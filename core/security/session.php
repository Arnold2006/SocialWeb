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
 * security/session.php — Secure session initialisation
 */

declare(strict_types=1);

/**
 * Start session with hardened settings.
 */
function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode',    '1');
    ini_set('session.use_only_cookies',   '1');
    ini_set('session.cookie_httponly',    '1');
    ini_set('session.cookie_samesite',    'Lax');
    ini_set('session.gc_maxlifetime',     (string) SESSION_LIFETIME);

    // Use HTTPS cookie flag in production
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    session_start([
        'name'            => SESSION_NAME,
        'gc_maxlifetime'  => SESSION_LIFETIME,
        'cookie_lifetime' => SESSION_LIFETIME,
    ]);

    // Session fixation protection: regenerate id after login (handled in auth.php)
}
