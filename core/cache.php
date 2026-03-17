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
 * cache.php — Simple file-based HTML cache
 *
 * Usage:
 *   $cached = cache_get('wall_feed_page1');
 *   if ($cached !== null) { echo $cached; exit; }
 *   ob_start();
 *   // ... generate content ...
 *   $html = ob_get_clean();
 *   cache_set('wall_feed_page1', $html);
 *   echo $html;
 */

declare(strict_types=1);

/**
 * Build the full path to a cache file from its key.
 */
function cache_path(string $key): string
{
    return CACHE_DIR . '/' . md5($key) . '.cache';
}

/**
 * Retrieve a cached value; returns null if missing or expired.
 *
 * @param string $key
 * @param int    $ttl  Seconds; 0 = use CACHE_TTL constant
 * @return string|null
 */
function cache_get(string $key, int $ttl = 0): ?string
{
    $ttl  = $ttl > 0 ? $ttl : CACHE_TTL;
    $file = cache_path($key);

    if (!file_exists($file)) {
        return null;
    }

    if ((time() - filemtime($file)) > $ttl) {
        @unlink($file);
        return null;
    }

    $data = file_get_contents($file);
    return $data !== false ? $data : null;
}

/**
 * Store a value in the cache.
 *
 * @param string $key
 * @param string $value
 */
function cache_set(string $key, string $value): void
{
    $file = cache_path($key);
    file_put_contents($file, $value, LOCK_EX);
}

/**
 * Delete a single cache entry.
 *
 * @param string $key
 */
function cache_delete(string $key): void
{
    $file = cache_path($key);
    if (file_exists($file)) {
        @unlink($file);
    }
}

/**
 * Invalidate all cache entries whose keys match a prefix pattern.
 * Useful to bust "wall_feed_page*" when a new post is created.
 *
 * @param string $prefix  Beginning of the md5-hashed keys won't match, so
 *                        pass a raw string glob pattern for the file, e.g. '*.cache'
 *                        to flush everything, or use cache_delete_pattern().
 */
function cache_flush(): void
{
    $files = glob(CACHE_DIR . '/*.cache');
    if ($files === false) {
        return;
    }
    foreach ($files as $file) {
        @unlink($file);
    }
}

/**
 * Flush all wall-feed caches (called when posts/comments/likes change).
 */
function cache_invalidate_wall(): void
{
    cache_flush();
}
