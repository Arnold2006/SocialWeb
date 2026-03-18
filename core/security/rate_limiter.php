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
 * security/rate_limiter.php — File-based rate limiting
 */

declare(strict_types=1);

/**
 * Simple file-based rate limiter.
 *
 * @param string $key       Unique key (e.g. 'login_' . $ip)
 * @param int    $maxHits   Allowed attempts
 * @param int    $windowSec Time window in seconds
 * @return bool  true = allowed, false = rate limited
 */
function rate_limit(string $key, int $maxHits = 5, int $windowSec = 300): bool
{
    $cacheFile = CACHE_DIR . '/rl_' . md5($key) . '.json';
    $now       = time();

    $data = ['hits' => [], 'blocked_until' => 0];

    if (file_exists($cacheFile)) {
        $raw = file_get_contents($cacheFile);
        if ($raw !== false) {
            try {
                $decoded = json_decode($raw, true, 3, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            } catch (\JsonException $e) {
                // Corrupt cache file — start fresh
                @unlink($cacheFile);
            }
        }
    }

    // Blocked?
    if ($data['blocked_until'] > $now) {
        return false;
    }

    // Remove expired hits
    $data['hits'] = array_filter($data['hits'], fn($t) => $t > ($now - $windowSec));

    $data['hits'][] = $now;

    if (count($data['hits']) > $maxHits) {
        $data['blocked_until'] = $now + $windowSec;
        $data['hits']          = [];
        file_put_contents($cacheFile, json_encode($data), LOCK_EX);
        return false;
    }

    file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    return true;
}
