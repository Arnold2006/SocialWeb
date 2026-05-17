<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * plugin_loader.php — Auto-load and register plugins from /plugins/
 *
 * Each plugin lives in its own subdirectory:
 *   /plugins/my_plugin/plugin.php
 *
 * plugin.php must define a function named:
 *   plugin_register_my_plugin(array &$registry): void
 *
 * The registry accepts:
 *   $registry['sidebar_widgets'][]  = callable
 *   $registry['wall_widgets'][]     = callable
 *   $registry['menu_items'][]       = ['label' => ..., 'url' => ...]
 *   $registry['profile_extensions'][] = callable($userId)
 *   $registry['footer_scripts'][]   = callable
 */

declare(strict_types=1);

/**
 * Load all enabled plugins and return the registry.
 *
 * @return array{
 *   sidebar_widgets: callable[],
 *   wall_widgets: callable[],
 *   menu_items: array[],
 *   profile_extensions: callable[],
 *   footer_scripts: callable[]
 * }
 */
function plugins_load(): array
{
    static $cachedRegistry = null;
    if (is_array($cachedRegistry)) {
        return $cachedRegistry;
    }

    $registry = [
        'sidebar_widgets'    => [],
        'wall_widgets'       => [],
        'menu_items'         => [],
        'profile_extensions' => [],
        'footer_scripts'     => [],
    ];

    if (!is_dir(PLUGINS_DIR)) {
        $cachedRegistry = $registry;
        return $cachedRegistry;
    }

    // Fetch all registered plugins and their enabled status from DB
    $allPlugins   = db_query('SELECT slug, is_enabled FROM plugins');
    $pluginStatus = array_column($allPlugins, 'is_enabled', 'slug');

    foreach (glob(PLUGINS_DIR . '/*/plugin.php') as $pluginFile) {
        $slug = basename(dirname($pluginFile));

        // Skip if registered in DB but not enabled; load if not yet registered (first run)
        if (isset($pluginStatus[$slug]) && (int)$pluginStatus[$slug] !== 1) {
            continue;
        }

        // Guard against malicious paths
        $realFile     = realpath($pluginFile);
        $realPluginsDir = realpath(PLUGINS_DIR);
        if ($realFile === false || !str_starts_with($realFile, $realPluginsDir . '/')) {
            continue;
        }

        require_once $realFile;

        $registerFn = 'plugin_register_' . $slug;
        if (function_exists($registerFn)) {
            $registerFn($registry);
        }
    }

    $cachedRegistry = $registry;
    return $cachedRegistry;
}
