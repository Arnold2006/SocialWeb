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
 * Sanitise HTML from a rich-text editor using a whitelist approach.
 *
 * Allowed tags: p, br, b, strong, i, em, u, s, del, h2, h3, h4,
 *               ul, ol, li, blockquote, a, img, div, span.
 * Allowed attributes are per-tag (see $allowedAttrs below).
 * - <a href> must be http/https; rel/target are added automatically.
 * - <img src> must point within SITE_URL/uploads/ (local only).
 * All other tags are unwrapped (children kept, tag removed).
 * All other attributes are stripped.
 *
 * @param string $html     Raw HTML from a contenteditable/rich-text field
 * @param int    $maxBytes Byte limit before processing (0 = no limit)
 * @return string          Clean, safe HTML
 */
function sanitise_html(string $html, int $maxBytes = 0): string
{
    if ($html === '') {
        return '';
    }

    if ($maxBytes > 0 && strlen($html) > $maxBytes) {
        $html = mb_substr($html, 0, $maxBytes);
    }

    $allowedTags = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'del', 'strike',
        'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote',
        'a', 'img', 'div', 'span',
    ];

    $allowedAttrs = [
        'a'   => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
    ];

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
        . $html
        . '</body></html>'
    );
    libxml_clear_errors();

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return '';
    }

    _sanitise_html_node($body, $dom, $allowedTags, $allowedAttrs);

    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }

    return trim($out);
}

/**
 * Return true when a DOMElement's sole meaningful child is an <img> element.
 * Pure-whitespace text nodes are ignored.  Used to distinguish image links
 * (which should remain active) from plain text links (which should not).
 *
 * @internal
 */
function _sanitise_is_image_only_link(DOMNode $node): bool
{
    $meaningfulChild = null;
    $meaningfulCount = 0;
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue) === '') {
            continue;
        }
        $meaningfulChild = $child;
        $meaningfulCount++;
    }
    return $meaningfulCount === 1
        && $meaningfulChild !== null
        && $meaningfulChild->nodeType === XML_ELEMENT_NODE
        && strtolower($meaningfulChild->nodeName) === 'img';
}

/**
 * Recursively sanitise a DOM node's children (internal helper).
 *
 * @internal
 */
function _sanitise_html_node(
    DOMNode $parent,
    DOMDocument $dom,
    array $allowedTags,
    array $allowedAttrs
): void {
    $toProcess = [];
    foreach ($parent->childNodes as $node) {
        $toProcess[] = $node;
    }

    foreach ($toProcess as $node) {
        if ($node->nodeType === XML_TEXT_NODE
            || $node->nodeType === XML_CDATA_SECTION_NODE
        ) {
            continue; // text nodes are safe
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            // Remove comments, processing instructions, etc.
            if ($node->parentNode === $parent) {
                $parent->removeChild($node);
            }
            continue;
        }

        $tag = strtolower($node->nodeName);

        if (!in_array($tag, $allowedTags, true)) {
            // Unwrap: keep children, remove the tag itself
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }
            foreach ($children as $child) {
                $parent->insertBefore($child, $node);
            }
            if ($node->parentNode === $parent) {
                $parent->removeChild($node);
            }
            // Recurse into the now-inlined children
            foreach ($children as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    _sanitise_html_node($child, $dom, $allowedTags, $allowedAttrs);
                }
            }
            continue;
        }

        // Strip disallowed attributes
        $attrNames = [];
        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $attrNames[] = $attr->nodeName;
            }
        }
        $permitted = $allowedAttrs[$tag] ?? [];
        foreach ($attrNames as $attrName) {
            if (!in_array($attrName, $permitted, true)) {
                $node->removeAttribute($attrName);
            }
        }

        // Validate and harden <a href>
        if ($tag === 'a') {
            $href = $node->getAttribute('href');
            if (!preg_match('/^https?:\/\//i', $href)) {
                $node->removeAttribute('href');
            } else {
                $node->setAttribute('rel', 'noopener noreferrer nofollow');
                $node->setAttribute('target', '_blank');
            }

            // Inserted text links must not be active: only <a> tags whose sole
            // non-empty child is a local <img> (i.e. image links to the original)
            // retain their href.  All other links have href stripped.
            if ($node->hasAttribute('href') && !_sanitise_is_image_only_link($node)) {
                $node->removeAttribute('href');
            }
        }

        // Validate <img src> — only allow local uploads via parse_url
        if ($tag === 'img') {
            $src      = $node->getAttribute('src');
            $parsed   = parse_url($src);
            $siteBase = parse_url(SITE_URL);

            $srcScheme = strtolower($parsed['scheme']  ?? '');
            $srcHost   = strtolower($parsed['host']    ?? '');
            $srcPath   = $parsed['path'] ?? '';
            $siteHost  = strtolower($siteBase['host']  ?? '');
            $sitePath  = rtrim($siteBase['path'] ?? '', '/');

            $uploadsPath = $sitePath . '/uploads/';
            $isLocal     = in_array($srcScheme, ['http', 'https'], true)
                        && $srcHost === $siteHost
                        && str_starts_with($srcPath, $uploadsPath);

            if (!$isLocal) {
                // Remove the element entirely
                if ($node->parentNode === $parent) {
                    $parent->removeChild($node);
                }
                continue;
            }
        }

        // Recurse into allowed element
        _sanitise_html_node($node, $dom, $allowedTags, $allowedAttrs);
    }
}

/**
 * Convert http/https URLs in raw text into safe clickable links, while also
 * HTML-escaping all content.
 *
 * This function replaces the combination of e() + manual linkification. It
 * splits the raw text on http/https URLs, HTML-escapes plain-text segments,
 * and wraps each URL in an <a> tag whose href is also properly HTML-escaped.
 * Only http:// and https:// schemes are linkified — javascript:, data:,
 * ftp:, etc. are never turned into links. Each link receives
 * rel="noopener noreferrer nofollow" (prevents tabnabbing and referrer
 * leakage) and target="_blank". URLs are always rendered as text anchors;
 * embedded content (images, audio, video) is never created regardless of the
 * URL's file extension.
 *
 * @param string $rawText Raw (unescaped) user text
 * @return string HTML-safe text with http/https URLs wrapped in <a> tags
 */
function linkify(string $rawText): string
{
    $parts  = preg_split('/(\bhttps?:\/\/\S+)/u', $rawText, -1, PREG_SPLIT_DELIM_CAPTURE);
    $result = '';
    foreach ($parts as $i => $part) {
        if ($i % 2 === 0) {
            // Plain text segment — HTML-escape it
            $result .= e($part);
        } else {
            // URL segment — strip trailing punctuation, then HTML-escape
            $url      = rtrim($part, '.,;:!?)\'"');
            $trailing = e(mb_substr($part, mb_strlen($url)));
            $escaped  = e($url);
            $result  .= '<a href="' . $escaped . '" rel="noopener noreferrer nofollow" target="_blank">'
                . $escaped . '</a>' . $trailing;
        }
    }
    return $result;
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
