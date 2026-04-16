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
 * FriendshipService.php — Friends / Connections logic
 */

declare(strict_types=1);

class FriendshipService
{
    /**
     * Send a friend request from $requesterId to $addresseeId.
     * Returns false if they are the same user or a row already exists.
     */
    public static function request(int $requesterId, int $addresseeId): bool
    {
        if ($requesterId === $addresseeId) {
            return false;
        }

        // Check both directions
        $exists = db_val(
            'SELECT COUNT(*) FROM friendships
             WHERE (requester_id = ? AND addressee_id = ?)
                OR (requester_id = ? AND addressee_id = ?)',
            [$requesterId, $addresseeId, $addresseeId, $requesterId]
        );

        if ((int) $exists > 0) {
            return false;
        }

        db_insert(
            'INSERT INTO friendships (requester_id, addressee_id, status) VALUES (?, ?, ?)',
            [$requesterId, $addresseeId, 'pending']
        );

        notify_user($addresseeId, 'friend_request', $requesterId, $requesterId);

        return true;
    }

    /**
     * Accept a pending friend request from $requesterId to $addresseeId.
     */
    public static function accept(int $addresseeId, int $requesterId): bool
    {
        $affected = db_exec(
            "UPDATE friendships SET status = 'accepted'
             WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'",
            [$requesterId, $addresseeId]
        );

        if ($affected > 0) {
            notify_user($requesterId, 'friend_accept', $addresseeId, $addresseeId);
            return true;
        }

        return false;
    }

    /**
     * Decline a pending friend request.
     */
    public static function decline(int $addresseeId, int $requesterId): bool
    {
        $affected = db_exec(
            "UPDATE friendships SET status = 'declined'
             WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'",
            [$requesterId, $addresseeId]
        );

        return $affected > 0;
    }

    /**
     * Cancel a sent (pending) friend request or unfriend in either direction.
     */
    public static function cancel(int $userId, int $otherId): bool
    {
        $affected = db_exec(
            'DELETE FROM friendships
             WHERE (requester_id = ? AND addressee_id = ?)
                OR (requester_id = ? AND addressee_id = ?)',
            [$userId, $otherId, $otherId, $userId]
        );

        return $affected > 0;
    }

    /**
     * Remove an accepted friendship in either direction.
     */
    public static function unfriend(int $userId, int $otherId): bool
    {
        $affected = db_exec(
            "DELETE FROM friendships
             WHERE status = 'accepted'
               AND ((requester_id = ? AND addressee_id = ?)
                    OR (requester_id = ? AND addressee_id = ?))",
            [$userId, $otherId, $otherId, $userId]
        );

        return $affected > 0;
    }

    /**
     * Check if an accepted friendship exists between two users (either direction).
     */
    public static function areFriends(int $a, int $b): bool
    {
        try {
            return (bool) db_val(
                "SELECT COUNT(*) FROM friendships
                 WHERE status = 'accepted'
                   AND ((requester_id = ? AND addressee_id = ?)
                        OR (requester_id = ? AND addressee_id = ?))",
                [$a, $b, $b, $a]
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Return the relationship status from $viewerId's perspective.
     *
     * @return string 'none' | 'pending_sent' | 'pending_received' | 'friends'
     */
    public static function getStatus(int $viewerId, int $profileId): string
    {
        if ($viewerId === $profileId) {
            return 'none';
        }

        try {
            $row = db_row(
                'SELECT requester_id, status FROM friendships
                 WHERE (requester_id = ? AND addressee_id = ?)
                    OR (requester_id = ? AND addressee_id = ?)
                 LIMIT 1',
                [$viewerId, $profileId, $profileId, $viewerId]
            );
        } catch (\Throwable $e) {
            return 'none';
        }

        if (!$row) {
            return 'none';
        }

        if ($row['status'] === 'accepted') {
            return 'friends';
        }

        if ($row['status'] === 'pending') {
            return ((int) $row['requester_id'] === $viewerId)
                ? 'pending_sent'
                : 'pending_received';
        }

        return 'none';
    }

    /**
     * Return array of user IDs who are accepted friends with $userId (either direction).
     */
    public static function getFriendIds(int $userId): array
    {
        try {
            $rows = db_query(
                "SELECT CASE WHEN requester_id = ? THEN addressee_id ELSE requester_id END AS friend_id
                 FROM friendships
                 WHERE status = 'accepted'
                   AND (requester_id = ? OR addressee_id = ?)",
                [$userId, $userId, $userId]
            );
            return array_column($rows, 'friend_id');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Return rows of pending incoming requests for $userId.
     */
    public static function getPendingRequests(int $userId): array
    {
        return db_query(
            "SELECT f.*, u.username, u.avatar_path
             FROM friendships f
             JOIN users u ON u.id = f.requester_id
             WHERE f.addressee_id = ? AND f.status = 'pending'
             ORDER BY f.created_at DESC",
            [$userId]
        );
    }

    /**
     * Return rows of pending outgoing requests from $userId.
     */
    public static function getSentRequests(int $userId): array
    {
        return db_query(
            "SELECT f.*, u.username, u.avatar_path
             FROM friendships f
             JOIN users u ON u.id = f.addressee_id
             WHERE f.requester_id = ? AND f.status = 'pending'
             ORDER BY f.created_at DESC",
            [$userId]
        );
    }

    /**
     * Count incoming pending friend requests (for header badge).
     */
    public static function countPendingRequests(int $userId): int
    {
        try {
            return (int) db_val(
                "SELECT COUNT(*) FROM friendships WHERE addressee_id = ? AND status = 'pending'",
                [$userId]
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
