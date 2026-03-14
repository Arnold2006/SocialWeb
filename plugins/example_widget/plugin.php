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
 * Example Widget Plugin
 *
 * Demonstrates how to register sidebar widgets, menu items,
 * and profile extensions using the plugin system.
 *
 * To enable this plugin:
 *   INSERT INTO plugins (name, slug, version, is_enabled)
 *   VALUES ('Example Widget', 'example_widget', '1.0.0', 1);
 */

declare(strict_types=1);

/**
 * Required: function named plugin_register_{slug}
 */
function plugin_register_example_widget(array &$registry): void
{
    // Register a sidebar widget
    $registry['sidebar_widgets'][] = function () {
        ?>
        <div class="widget widget-example">
            <h3 class="widget-title">Online Now</h3>
            <?php
            // Show users who were active within the last 15 minutes
            $onlineUsers = db_query(
                'SELECT id, username, avatar_path
                 FROM users
                 WHERE last_seen >= NOW() - INTERVAL 15 MINUTE
                   AND is_banned = 0
                 LIMIT 10'
            );
            if (empty($onlineUsers)): ?>
            <p style="font-size:.82rem;color:var(--color-text-muted)">No one online recently.</p>
            <?php else: ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:.5rem">
                <?php foreach ($onlineUsers as $u): ?>
                <a href="<?= e(SITE_URL . '/pages/profile.php?id=' . (int)$u['id']) ?>"
                   title="<?= e($u['username']) ?>">
                    <img src="<?= e(avatar_url($u, 'small')) ?>"
                         alt="<?= e($u['username']) ?>"
                         width="32" height="32"
                         style="border-radius:50%;border:2px solid var(--color-success)"
                         loading="lazy">
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    };

    // Register a menu item
    $registry['menu_items'][] = [
        'label' => '🌐 Network',
        'url'   => SITE_URL . '/pages/members.php',
    ];
}
