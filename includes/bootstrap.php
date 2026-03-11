<?php
/**
 * Bootstrap file — loaded at the top of every page.
 *
 * Sets up:
 *  - Error reporting
 *  - Session
 *  - Core files
 *  - Security headers
 *
 * Configuration notes
 * -------------------
 * core/config.php   – default/committed configuration (safe defaults, no real creds).
 * core/config.local.php – your local overrides (DB password, SITE_URL, SITE_DEBUG, etc.).
 *                         This file is gitignored so credentials are never committed.
 *                         Create it by copying config.php and editing the values.
 * To enable verbose error output set  define('SITE_DEBUG', true);  in config.local.php.
 */

declare(strict_types=1);

// ── Path helper ───────────────────────────────────────────────────────────────
define('APP_ROOT', dirname(__DIR__));

// ── Load config first so SITE_DEBUG is available immediately ─────────────────
require_once APP_ROOT . '/core/config.php';

// ── Timezone ──────────────────────────────────────────────────────────────────
// Set PHP to UTC so that strtotime() / time() and MySQL CURRENT_TIMESTAMP
// (also set to UTC in db.php) agree on the same reference clock, preventing
// "N hours ago" drift caused by a server/PHP timezone mismatch.
date_default_timezone_set('UTC');

// ── Error reporting (configured before any other file is loaded so that
//    errors that occur during bootstrap itself are visible) ───────────────────
if (SITE_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);   // log all errors but don't display them (display_errors=0 above)
}

// ── Global exception handler ──────────────────────────────────────────────────
// Catches any Throwable not caught by page-level try/catch blocks.
set_exception_handler(function (Throwable $e): void {
    error_log(sprintf(
        'Uncaught %s: %s in %s on line %d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    if (SITE_DEBUG) {
        echo '<pre style="background:#fff;color:#900;padding:1rem;border:2px solid #900;margin:1rem;font-size:.9rem">'
            . '<strong>' . htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8') . '</strong>: '
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            . "\nin " . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ':' . $e->getLine() . "\n\n"
            . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8')
            . '</pre>';
    } else {
        echo '<p>An unexpected error occurred. Please try again later.</p>';
    }
    exit(1);
});

// ── Load remaining core files ─────────────────────────────────────────────────
// compat.php must come first — it provides mb_* polyfills when the mbstring
// PHP extension is absent and sets mb_internal_encoding('UTF-8') when present.
require_once APP_ROOT . '/core/compat.php';

if (!extension_loaded('mbstring')) {
    error_log(
        'SocialWeb: the mbstring PHP extension is not loaded. ' .
        'Install php-mbstring for correct multibyte (UTF-8) string handling. ' .
        'Built-in polyfills are active but full UTF-8 accuracy is not guaranteed.'
    );
}

require_once APP_ROOT . '/core/db.php';
require_once APP_ROOT . '/core/security.php';
require_once APP_ROOT . '/core/auth.php';
require_once APP_ROOT . '/core/cache.php';
require_once APP_ROOT . '/core/media_processor.php';
require_once APP_ROOT . '/core/plugin_loader.php';
require_once APP_ROOT . '/includes/functions.php';

// ── Session ────────────────────────────────────────────────────────────────────
session_start_secure();

// ── Security headers ───────────────────────────────────────────────────────────
send_security_headers();

// ── Activity tracking ─────────────────────────────────────────────────────────
// Keep last_seen current for every logged-in user so that the "Online Now"
// widget reflects actual browsing activity rather than just login time.
// We throttle the write to at most once per minute (stored in the session)
// to avoid a DB round-trip on every single request.
if (!empty($_SESSION['user_id'])) {
    $now = time();
    if (empty($_SESSION['last_seen_at']) || ($now - $_SESSION['last_seen_at']) >= 60) {
        db_exec(
            'UPDATE users SET last_seen = NOW() WHERE id = ?',
            [(int) $_SESSION['user_id']]
        );
        $_SESSION['last_seen_at'] = $now;
    }
}
