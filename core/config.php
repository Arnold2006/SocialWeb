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
 * config.php — Application configuration
 *
 * Copy this file to config.local.php and set real credentials there.
 * config.local.php is gitignored and never committed.
 */

declare(strict_types=1);

// ── Load local overrides first so they take precedence over defaults ──────────
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

// ── Site ────────────────────────────────────────────────────────────────────
if (!defined('SITE_NAME'))  define('SITE_NAME',  'SocialWeb');
if (!defined('SITE_URL'))   define('SITE_URL',   'http://localhost/SocialWeb');  // no trailing slash
if (!defined('SITE_ROOT'))  define('SITE_ROOT',  dirname(__DIR__));               // /path/to/social-network
if (!defined('SITE_DEBUG')) define('SITE_DEBUG', false);                          // set true only in dev

// ── Database ────────────────────────────────────────────────────────────────
if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_PORT'))    define('DB_PORT',    3306);
if (!defined('DB_NAME'))    define('DB_NAME',    'socialweb');
if (!defined('DB_USER'))    define('DB_USER',    'socialweb_user');
if (!defined('DB_PASS'))    define('DB_PASS',    'change_me_in_config_local');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ── Paths (derived from SITE_ROOT / SITE_URL — override above to customise) ──
if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', SITE_ROOT . '/uploads');
if (!defined('CACHE_DIR'))   define('CACHE_DIR',   SITE_ROOT . '/cache');
if (!defined('PLUGINS_DIR')) define('PLUGINS_DIR', SITE_ROOT . '/plugins');
if (!defined('ASSETS_URL'))  define('ASSETS_URL',  SITE_URL  . '/assets');

// ── Upload limits ───────────────────────────────────────────────────────────
if (!defined('MAX_UPLOAD_BYTES'))   define('MAX_UPLOAD_BYTES',   10 * 1024 * 1024);  // 10 MB images
if (!defined('MAX_VIDEO_BYTES'))    define('MAX_VIDEO_BYTES',    50 * 1024 * 1024);  // 50 MB videos
if (!defined('MAX_VIDEO_DURATION')) define('MAX_VIDEO_DURATION', 300);               // 300 seconds
if (!defined('MAX_UPLOAD_FILES'))   define('MAX_UPLOAD_FILES',   20);                // files per request (PHP max_file_uploads)
if (!defined('AVATAR_SIZE_LARGE'))  define('AVATAR_SIZE_LARGE',  256);
if (!defined('AVATAR_SIZE_MEDIUM')) define('AVATAR_SIZE_MEDIUM', 128);
if (!defined('AVATAR_SIZE_SMALL'))  define('AVATAR_SIZE_SMALL',   64);

// ── Cache ───────────────────────────────────────────────────────────────────
if (!defined('CACHE_TTL')) define('CACHE_TTL', 30);   // seconds

// ── Session ──────────────────────────────────────────────────────────────────
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 86400);   // 24 hours

// ── Security ─────────────────────────────────────────────────────────────────
if (!defined('CSRF_TOKEN_LENGTH')) define('CSRF_TOKEN_LENGTH', 32);
