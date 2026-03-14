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
 * compat.php — PHP extension compatibility checks and polyfills.
 *
 * Ensures the application degrades gracefully when the mbstring extension
 * is absent by providing lightweight polyfills via iconv (when available)
 * or plain byte-string functions as a last resort.
 *
 * Users should install the mbstring PHP extension for full UTF-8 support.
 */

declare(strict_types=1);

if (extension_loaded('mbstring')) {
    mb_internal_encoding('UTF-8');
} else {
    // ── mb_substr ────────────────────────────────────────────────────────────
    if (!function_exists('mb_substr')) {
        function mb_substr(string $string, int $start, ?int $length = null, string $encoding = 'UTF-8'): string
        {
            if (extension_loaded('iconv')) {
                if ($length === null) {
                    $fullLen = iconv_strlen($string, $encoding);
                    $len = ($fullLen === false) ? strlen($string) : $fullLen;
                } else {
                    $len = $length;
                }
                $result = iconv_substr($string, $start, $len, $encoding);
                return $result === false ? '' : $result;
            }
            // Byte-level fallback — may split multi-byte sequences on non-ASCII input
            return $length === null ? substr($string, $start) : (substr($string, $start, $length) ?: '');
        }
    }

    // ── mb_strlen ────────────────────────────────────────────────────────────
    if (!function_exists('mb_strlen')) {
        function mb_strlen(string $string, ?string $encoding = null): int
        {
            if (extension_loaded('iconv')) {
                $result = iconv_strlen($string, $encoding ?? 'UTF-8');
                return $result === false ? strlen($string) : $result;
            }
            return strlen($string);
        }
    }

    // ── mb_strtolower ────────────────────────────────────────────────────────
    if (!function_exists('mb_strtolower')) {
        // Without mbstring, case-folding is ASCII-only (A-Z → a-z).
        function mb_strtolower(string $string, ?string $encoding = null): string
        {
            return strtolower($string);
        }
    }

    // ── mb_strtoupper ────────────────────────────────────────────────────────
    if (!function_exists('mb_strtoupper')) {
        // Without mbstring, case-folding is ASCII-only (a-z → A-Z).
        function mb_strtoupper(string $string, ?string $encoding = null): string
        {
            return strtoupper($string);
        }
    }

    // ── mb_convert_encoding ──────────────────────────────────────────────────
    if (!function_exists('mb_convert_encoding')) {
        /**
         * @param string|array<mixed> $string
         * @return string|array<mixed>|false
         */
        function mb_convert_encoding(string|array $string, string $toEncoding, string|array|null $fromEncoding = null): string|array|false
        {
            if (is_array($string)) {
                return array_map(
                    fn($s) => mb_convert_encoding((string) $s, $toEncoding, $fromEncoding),
                    $string
                );
            }
            if (extension_loaded('iconv')) {
                $from   = is_array($fromEncoding) ? implode(',', $fromEncoding) : ($fromEncoding ?? 'UTF-8');
                $result = iconv($from, $toEncoding . '//TRANSLIT//IGNORE', $string);
                return $result !== false ? $result : $string;
            }
            return $string;
        }
    }

    // ── mb_detect_encoding ───────────────────────────────────────────────────
    if (!function_exists('mb_detect_encoding')) {
        /**
         * @param string|array<string>|null $encodings
         * @return string|false
         */
        function mb_detect_encoding(string $string, string|array|null $encodings = null, bool $strict = false): string|false
        {
            // preg_match with the /u modifier returns false when the string
            // contains invalid UTF-8 byte sequences.
            if (preg_match('//u', $string) !== false && preg_last_error() === PREG_NO_ERROR) {
                return 'UTF-8';
            }
            return 'ISO-8859-1';
        }
    }
}
