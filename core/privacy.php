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
 * privacy.php — PrivacyService: per-user content visibility controls
 */

declare(strict_types=1);

require_once APP_ROOT . '/modules/friends/FriendshipService.php';

class PrivacyService
{
    /** All valid privacy levels, from most to least permissive. */
    const LEVELS = ['everybody', 'members', 'friends_only', 'only_me'];

    /** Default setting for each action key. */
    const DEFAULTS = [
        'view_profile' => 'members',
        'view_wall'    => 'members',
        'view_photos'  => 'members',
        'view_videos'  => 'members',
        'view_blog'    => 'members',
        'send_message' => 'members',
    ];

    /** Human-readable labels for the UI. */
    const LABELS = [
        'everybody'    => 'Everybody',
        'members'      => 'Members only',
        'friends_only' => 'Friends only',
        'only_me'      => 'Only me',
    ];

    /**
     * Check whether $viewerId may perform $action on $ownerId's content.
     *
     * - Admins always have access.
     * - The owner always has access to their own content.
     * - everybody / members → always true for any logged-in viewer.
     * - friends_only        → true only if areFriends().
     * - only_me             → always false for non-owners.
     */
    public static function canView(int $viewerId, int $ownerId, string $action): bool
    {
        // Owner can always see their own content
        if ($viewerId === $ownerId) {
            return true;
        }

        // Admins bypass all restrictions
        try {
            $viewer = db_row('SELECT role FROM users WHERE id = ? AND is_banned = 0 LIMIT 1', [$viewerId]);
            if ($viewer && $viewer['role'] === 'admin') {
                return true;
            }

            $level = self::get($ownerId, $action);
        } catch (\Throwable $e) {
            // If the DB call fails (e.g. pre-migration), default to allowing access
            return true;
        }

        switch ($level) {
            case 'everybody':
            case 'members':
                return true;

            case 'friends_only':
                return FriendshipService::areFriends($viewerId, $ownerId);

            case 'only_me':
            default:
                return false;
        }
    }

    /**
     * Get the stored privacy setting for $userId + $action,
     * falling back to the default if not set.
     */
    public static function get(int $userId, string $action): string
    {
        try {
            $value = db_val(
                'SELECT value FROM user_privacy_settings WHERE user_id = ? AND action_key = ?',
                [$userId, $action]
            );
        } catch (\Throwable $e) {
            return self::DEFAULTS[$action] ?? 'members';
        }

        if ($value !== null && in_array($value, self::LEVELS, true)) {
            return $value;
        }

        return self::DEFAULTS[$action] ?? 'members';
    }

    /**
     * Upsert a single privacy setting.
     * Silently ignores invalid values or unknown action keys.
     */
    public static function set(int $userId, string $action, string $value): void
    {
        if (!isset(self::DEFAULTS[$action])) {
            return;
        }
        if (!in_array($value, self::LEVELS, true)) {
            return;
        }

        db_exec(
            'INSERT INTO user_privacy_settings (user_id, action_key, value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$userId, $action, $value]
        );
    }

    /**
     * Bulk-upsert privacy settings from a form submission.
     * Ignores unknown keys and invalid values.
     */
    public static function setAll(int $userId, array $settings): void
    {
        foreach ($settings as $action => $value) {
            self::set($userId, (string) $action, (string) $value);
        }
    }

    /**
     * Build a SQL WHERE fragment that excludes users whose view_profile
     * setting blocks $viewerId.  Used to filter member / search listings.
     *
     * Returns ['sql' => 'AND u.id NOT IN (...)', 'params' => [...]]
     * or      ['sql' => '', 'params' => []] when nothing needs filtering.
     */
    public static function visibleUsersFilter(int $viewerId): array
    {
        try {
            // Fetch all user IDs that have view_profile = 'only_me'
            // They should be hidden from everyone except themselves (and admins).
            $hiddenRows = db_query(
                "SELECT user_id FROM user_privacy_settings
                 WHERE action_key = 'view_profile' AND value = 'only_me'",
                []
            );

            $hiddenIds = array_column($hiddenRows, 'user_id');

            // Also fetch 'friends_only' profiles and check friendship
            $friendsOnlyRows = db_query(
                "SELECT user_id FROM user_privacy_settings
                 WHERE action_key = 'view_profile' AND value = 'friends_only'",
                []
            );

            foreach ($friendsOnlyRows as $row) {
                $ownerId = (int) $row['user_id'];
                if ($ownerId === $viewerId) {
                    continue;
                }
                if (!FriendshipService::areFriends($viewerId, $ownerId)) {
                    $hiddenIds[] = $ownerId;
                }
            }

            if (empty($hiddenIds)) {
                return ['sql' => '', 'params' => []];
            }

            // Always allow the viewer to see themselves
            $hiddenIds = array_filter($hiddenIds, fn($id) => (int) $id !== $viewerId);

            if (empty($hiddenIds)) {
                return ['sql' => '', 'params' => []];
            }

            $phs = implode(',', array_fill(0, count($hiddenIds), '?'));
            return [
                'sql'    => "AND u.id NOT IN ($phs)",
                'params' => array_values($hiddenIds),
            ];
        } catch (\Throwable $e) {
            return ['sql' => '', 'params' => []];
        }
    }

    /**
     * Return user IDs that have view_wall='friends_only' AND are friends
     * with $viewerId — their wall posts should appear in the feed.
     */
    public static function friendsOnlyUserIds(int $viewerId): array
    {
        $rows = db_query(
            "SELECT user_id FROM user_privacy_settings
             WHERE action_key = 'view_wall' AND value = 'friends_only'",
            []
        );

        $result = [];
        foreach ($rows as $row) {
            $ownerId = (int) $row['user_id'];
            if ($ownerId !== $viewerId && FriendshipService::areFriends($viewerId, $ownerId)) {
                $result[] = $ownerId;
            }
        }

        return $result;
    }

    /**
     * Return an array of user IDs that should be hidden from $viewerId
     * for a given $actionKey.  Accounts for 'only_me' and 'friends_only' settings.
     *
     * Safe to call before the migration has been applied (returns [] on error).
     */
    public static function blockedUsersByAction(int $viewerId, string $actionKey): array
    {
        try {
            $hiddenIds = [];

            $onlyMeRows = db_query(
                "SELECT user_id FROM user_privacy_settings
                 WHERE action_key = ? AND value = 'only_me'",
                [$actionKey]
            );
            foreach ($onlyMeRows as $row) {
                if ((int) $row['user_id'] !== $viewerId) {
                    $hiddenIds[] = (int) $row['user_id'];
                }
            }

            $friendsOnlyRows = db_query(
                "SELECT user_id FROM user_privacy_settings
                 WHERE action_key = ? AND value = 'friends_only'",
                [$actionKey]
            );
            foreach ($friendsOnlyRows as $row) {
                $ownerId = (int) $row['user_id'];
                if ($ownerId !== $viewerId && !FriendshipService::areFriends($viewerId, $ownerId)) {
                    $hiddenIds[] = $ownerId;
                }
            }

            return array_values(array_unique($hiddenIds));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
