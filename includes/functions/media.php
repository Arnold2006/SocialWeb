<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * functions/media.php — Media URL and formatting helpers
 */

declare(strict_types=1);

/**
 * Format a datetime string to a human-readable relative time.
 */
function time_ago(string $datetime): string
{
    $time = strtotime($datetime);
    if ($time === false) {
        return $datetime;
    }
    $diff = time() - $time;

    if ($diff < 60)        return 'just now';
    if ($diff < 3600)      return (int)($diff / 60) . ' min ago';
    if ($diff < 86400)     return (int)($diff / 3600) . ' hr ago';
    if ($diff < 604800)    return (int)($diff / 86400) . ' days ago';
    if ($diff < 2592000)   return (int)($diff / 604800) . ' weeks ago';
    return date('M j, Y', $time);
}

/**
 * Generate the URL for an avatar given a user row.
 *
 * @param array  $user
 * @param string $size  small | medium | large
 */
function avatar_url(array $user, string $size = 'medium'): string
{
    if (!empty($user['avatar_path'])) {
        // Replace /large/ with the requested size
        $path = str_replace('/avatars/large/', '/avatars/' . $size . '/', $user['avatar_path']);
        return SITE_URL . $path;
    }
    return SITE_URL . '/assets/images/default_avatar.svg';
}

/**
 * Generate the URL for a media item by size.
 *
 * @param array  $media  row from media table
 * @param string $size   thumb | medium | large | original
 */
function get_media_url(array $media, string $size = 'medium'): string
{
    $field = match ($size) {
        'thumb'     => 'thumb_path',
        'thumbnail' => 'thumbnail_path',
        'large'     => 'large_path',
        'original'  => 'storage_path',
        default     => 'medium_path',
    };

    $path = $media[$field] ?? $media['storage_path'] ?? '';
    if (empty($path)) {
        return SITE_URL . '/assets/images/placeholder.svg';
    }

    // Convert absolute path to URL
    $relative = str_replace(SITE_ROOT, '', $path);
    return SITE_URL . str_replace('\\', '/', $relative);
}

/**
 * Format a user's last_seen timestamp for display.
 *
 * Returns 'Never' when no timestamp is available, the relative time string
 * (e.g. "5 min ago") when the timestamp is within the last 24 hours, or
 * an absolute date (e.g. "Jan 3, 2026") for older timestamps.
 *
 * @param string|null $lastSeen  Value from users.last_seen (MySQL DATETIME or null)
 * @return string
 */
function format_last_seen(?string $lastSeen): string
{
    if (empty($lastSeen)) {
        return 'Never';
    }

    $ts = strtotime($lastSeen);
    if ($ts === false) {
        return 'Never';
    }

    if ((time() - $ts) > 86400) {
        return date('M j, Y', $ts);
    }

    return time_ago($lastSeen);
}
