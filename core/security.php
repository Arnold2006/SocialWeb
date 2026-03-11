<?php
/**
 * security.php — CSRF tokens, input sanitisation, rate-limiting helpers
 */

declare(strict_types=1);

// ── CSRF ─────────────────────────────────────────────────────────────────────

/**
 * Generate (or retrieve existing) CSRF token for the current session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify the submitted CSRF token.
 * Exits with 403 on failure.
 */
function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        die('CSRF token validation failed.');
    }
}

// ── Input sanitisation ────────────────────────────────────────────────────────

/**
 * Escape a value for safe HTML output.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitise a plain-text string (strip tags, trim).
 */
function sanitise_string(string $input, int $maxLength = 0): string
{
    $value = trim(strip_tags($input));
    if ($maxLength > 0) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

/**
 * Sanitise an email address.
 * Returns empty string if invalid.
 */
function sanitise_email(string $input): string
{
    $email = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? (string)$email : '';
}

/**
 * Sanitise a username: allow only alphanumerics, underscores, hyphens.
 */
function sanitise_username(string $input): string
{
    return preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($input));
}

/**
 * Cast to unsigned int — safe for IDs.
 */
function sanitise_int(mixed $input): int
{
    return max(0, (int) $input);
}

// ── Session hardening ─────────────────────────────────────────────────────────

/**
 * Start session with hardened settings.
 */
function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode',    '1');
    ini_set('session.use_only_cookies',   '1');
    ini_set('session.cookie_httponly',    '1');
    ini_set('session.cookie_samesite',    'Lax');
    ini_set('session.gc_maxlifetime',     (string) SESSION_LIFETIME);

    // Use HTTPS cookie flag in production
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    session_start([
        'name'            => 'TORSOCIAL_SESS',
        'gc_maxlifetime'  => SESSION_LIFETIME,
        'cookie_lifetime' => SESSION_LIFETIME,
    ]);

    // Session fixation protection: regenerate id after login (handled in auth.php)
}

// ── Rate limiting ─────────────────────────────────────────────────────────────

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
        $decoded = json_decode(file_get_contents($cacheFile), true);
        if (is_array($decoded)) {
            $data = $decoded;
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

// ── Security headers ──────────────────────────────────────────────────────────

/**
 * Send security-oriented HTTP headers.
 */
function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'");
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
}
