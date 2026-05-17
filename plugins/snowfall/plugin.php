<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * Snowfall Plugin
 *
 * Adds an animated snowfall effect to every page of the site.
 * Activate or deactivate it from the Admin → Plugins menu.
 *
 * Based on https://github.com/Arnold2006/Oxwall-Snowfall-Plugin
 * Re-implemented as pure vanilla JS (no jQuery dependency).
 *
 * To install this plugin for the first time, run the migration:
 *   database/migrations/041_add_snowfall_plugin.sql
 */

declare(strict_types=1);

function plugin_register_snowfall(array &$registry): void
{
    $registry['footer_scripts'][] = function (): void {
        $scriptUrl = SITE_URL . '/plugins/snowfall/js/snowfall.js';
        echo '<script src="' . htmlspecialchars($scriptUrl, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
    };
}
