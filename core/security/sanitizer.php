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
 * security/sanitizer.php — HTML and input sanitisation helpers
 */

declare(strict_types=1);

/**
 * Escape a value for safe HTML output.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitise a plain-text string (strip tags, trim).
 *
 * Uses a regex to remove HTML/XML tags (angle-bracket sequences whose first
 * character is a letter, slash, or exclamation mark) while preserving
 * non-tag sequences such as the <3 emoticon.  strip_tags() is intentionally
 * avoided because it treats <3 as an unclosed tag and silently discards it.
 */
function sanitise_string(string $input, int $maxLength = 0): string
{
    $value = trim(preg_replace('/<[a-zA-Z\/!][^>]*>/u', '', $input) ?? '');
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

    $siteHost = strtolower(parse_url(SITE_URL, PHP_URL_HOST) ?? '');

    _sanitise_html_node($body, $dom, $allowedTags, $allowedAttrs, $siteHost);

    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }

    return trim($out);
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
    array $allowedAttrs,
    string $siteHost
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
                    _sanitise_html_node($child, $dom, $allowedTags, $allowedAttrs, $siteHost);
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
            if (preg_match('/^https?:\/\//i', $href)) {
                // Absolute URL — check whether it is on the same site.
                $hrefHost = strtolower(parse_url($href, PHP_URL_HOST) ?? '');
                if ($siteHost !== '' && $hrefHost === $siteHost) {
                    // Same-site absolute URL — treat as internal.
                    $node->setAttribute('rel', 'noopener');
                } else {
                    // External URL — open in a new tab with full safety attributes.
                    $node->setAttribute('rel', 'noopener noreferrer nofollow');
                    $node->setAttribute('target', '_blank');
                }
            } elseif ((preg_match('/^\/[^\/]/u', $href) || $href === '/')
                      && strpos($href, '..') === false) {
                // Relative path on the same site (no traversal) — safe internal link.
                $node->setAttribute('rel', 'noopener');
            } else {
                // Anything else (javascript:, data:, empty, etc.) is unsafe.
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
        _sanitise_html_node($node, $dom, $allowedTags, $allowedAttrs, $siteHost);
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
 * ftp:, etc. are never turned into links. External links receive
 * rel="noopener noreferrer nofollow" and target="_blank". Internal links
 * (those starting with SITE_URL) receive only rel="noopener" and open in the
 * same tab. URLs are always rendered as text anchors; embedded content
 * (images, audio, video) is never created regardless of the URL's file
 * extension.
 *
 * @param string $rawText Raw (unescaped) user text
 * @return string HTML-safe text with http/https URLs wrapped in <a> tags
 */
function linkify(string $rawText): string
{
    $urlParts = preg_split('/(\bhttps?:\/\/\S+)/u', $rawText, -1, PREG_SPLIT_DELIM_CAPTURE);
    // Normalise: strip trailing slash so "SITE_URL/" and "SITE_URL/path" both match,
    // but "SITE_URLOther" does not (we check for the slash boundary below).
    $siteBase = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';

    // Pre-collect all @mention candidates from plain-text segments for a single batch lookup.
    $mentionCandidates = [];
    foreach ($urlParts as $i => $part) {
        if ($i % 2 === 0) {
            preg_match_all('/@([a-zA-Z0-9_\-]+)/u', $part, $m);
            foreach ($m[1] as $u) {
                $mentionCandidates[] = $u;
            }
        }
    }

    // Resolve candidates to user IDs in one query.
    $mentionMap = [];
    if (!empty($mentionCandidates) && function_exists('db_query')) {
        $unique = array_values(array_unique($mentionCandidates));
        $phs    = implode(',', array_fill(0, count($unique), '?'));
        try {
            $rows = db_query(
                "SELECT id, username FROM users WHERE username IN ($phs) AND is_banned = 0",
                $unique
            );
            foreach ($rows as $row) {
                $mentionMap[$row['username']] = (int)$row['id'];
            }
        } catch (\Throwable $e) {
            // Non-fatal: mentions simply won't be linked.
        }
    }

    $profileBase = (defined('SITE_URL') ? SITE_URL : '') . '/pages/profile.php?id=';

    $result = '';
    foreach ($urlParts as $i => $part) {
        if ($i % 2 === 0) {
            // Plain-text segment — process @mentions, then HTML-escape the rest.
            $textParts = preg_split('/(@[a-zA-Z0-9_\-]+)/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($textParts as $j => $tp) {
                if ($j % 2 === 0) {
                    $result .= e($tp);
                } else {
                    $username = substr($tp, 1);
                    if (isset($mentionMap[$username])) {
                        $uid = $mentionMap[$username];
                        $result .= '<a href="' . e($profileBase . $uid) . '" class="mention">@' . e($username) . '</a>';
                    } else {
                        $result .= e($tp);
                    }
                }
            }
        } else {
            // URL segment — strip trailing punctuation, then HTML-escape
            $url      = rtrim($part, '.,;:!?)\'"');
            $trailing = e(mb_substr($part, mb_strlen($url)));
            $escaped  = e($url);
            // Internal: URL is exactly the site root or starts with "SITE_URL/"
            $isInternal = $siteBase !== ''
                && (str_starts_with($url, $siteBase . '/') || $url === $siteBase);
            if ($isInternal) {
                $result .= '<a href="' . $escaped . '" rel="noopener">'
                    . $escaped . '</a>' . $trailing;
            } else {
                $result .= '<a href="' . $escaped . '" rel="noopener noreferrer nofollow" target="_blank">'
                    . $escaped . '</a>' . $trailing;
            }
        }
    }
    return $result;
}

/**
 * Replace common text emoticons with Unicode emoji.
 *
 * Smileys are recognised only when surrounded by whitespace or located at the
 * start/end of the string, so they are never accidentally matched inside a
 * word or URL.  Longer patterns are listed first so they take priority over
 * their shorter sub-strings (e.g. ':-)'  beats  ':)').
 *
 * Call this on raw (unescaped) user text *before* passing the result to
 * linkify() so that the Unicode emoji characters are safely HTML-escaped as
 * part of the normal rendering pipeline.
 *
 * @param string $text Raw (unescaped) user text
 * @return string Text with emoticons replaced by Unicode emoji
 */
function smilify(string $text): string
{
    static $map = [
        // 4-char and longer patterns first to avoid partial matches
        'O:-)' => '😇', 'O:)'  => '😇',
        '>:-)' => '😈', '>:)'  => '😈',
        '>:-(' => '😠', '>:('  => '😠',
        "B-)"  => '😎',
        ":-)"  => '😊', ":-D"  => '😀', ":-("  => '😞',
        ";-)"  => '😉', ":-P"  => '😛', ":-p"  => '😛',
        ":-O"  => '😮', ":-o"  => '😮', ":-*"  => '😘',
        ":-/"  => '😕', ":-|"  => '😐',
        ":'-(" => '😢', ":'("  => '😢',
        // 2-char patterns
        ':)'   => '😊', ':D'   => '😀', ':('   => '😞',
        ';)'   => '😉', ':P'   => '😛', ':p'   => '😛',
        ':O'   => '😮', ':o'   => '😮', ':*'   => '😘',
        ':/'   => '😕', ':|'   => '😐',
        'B)'   => '😎', '<3'   => '❤️',
    ];
    static $pattern = null;

    if ($pattern === null) {
        $parts   = array_map(fn($s) => preg_quote($s, '/'), array_keys($map));
        $pattern = '/(?<!\S)(' . implode('|', $parts) . ')(?!\S)/u';
    }

    return preg_replace_callback($pattern, fn($m) => $map[$m[1]] ?? $m[1], $text) ?? $text;
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
