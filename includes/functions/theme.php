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
 * functions/theme.php — Site theme and settings helpers
 */

declare(strict_types=1);

/**
 * Get site setting from DB.
 */
function site_setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $val = db_val('SELECT value FROM site_settings WHERE `key` = ? LIMIT 1', [$key]);
        $cache[$key] = $val !== null ? (string) $val : $default;
    }
    return $cache[$key];
}

/**
 * Return the list of valid colour theme slugs.
 */
function valid_themes(): array
{
    return ['blue-red', 'gray-orange', 'purple-red', 'green-teal', 'dark-gold', 'navy-cyan'];
}

/**
 * Return the active site theme slug, falling back to 'blue-red'.
 */
function active_theme(): string
{
    $theme = site_setting('site_theme', 'blue-red');
    return in_array($theme, valid_themes(), true) ? $theme : 'blue-red';
}
