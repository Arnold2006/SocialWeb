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
 * RequestValidator.php — Centralised, type-safe HTTP request parameter extraction
 *
 * Provides a single place for validating and coercing request inputs instead
 * of calling sanitise_int(), sanitise_string(), etc. ad-hoc across pages.
 *
 * Usage:
 *   $v = new RequestValidator($_GET);
 *   $id   = $v->int('id');           // unsigned int, defaults to 0
 *   $page = $v->int('page', 1);      // with custom default
 *   $name = $v->string('name', 50);  // trimmed/stripped, max 50 chars
 *   $email = $v->email('email');     // validated email or ''
 *   $user  = $v->username('user');   // alphanumeric + _-
 *   $q    = $v->raw('q');            // raw string, no sanitisation
 */

declare(strict_types=1);

class RequestValidator
{
    /** @var array<string, mixed> The input array ($_GET, $_POST, or custom) */
    private array $input;

    /**
     * @param array<string, mixed> $input  Typically $_GET or $_POST
     */
    public function __construct(array $input)
    {
        $this->input = $input;
    }

    /**
     * Extract an unsigned integer parameter (safe for database IDs).
     *
     * @param string $key     Parameter name
     * @param int    $default Returned when the key is absent or non-numeric
     * @return int            Value clamped to >= 0
     */
    public function int(string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $this->input)) {
            return $default;
        }
        return max(0, (int) $this->input[$key]);
    }

    /**
     * Extract a plain-text string parameter (tags stripped, trimmed).
     *
     * @param string $key       Parameter name
     * @param int    $maxLength Maximum character length (0 = unlimited)
     * @param string $default   Returned when the key is absent
     * @return string
     */
    public function string(string $key, int $maxLength = 0, string $default = ''): string
    {
        if (!array_key_exists($key, $this->input)) {
            return $default;
        }
        return sanitise_string((string) $this->input[$key], $maxLength);
    }

    /**
     * Extract and validate an email address parameter.
     *
     * @param string $key     Parameter name
     * @param string $default Returned when the key is absent or the value is invalid
     * @return string         Valid email address, or $default
     */
    public function email(string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $this->input)) {
            return $default;
        }
        $result = sanitise_email((string) $this->input[$key]);
        return $result !== '' ? $result : $default;
    }

    /**
     * Extract and sanitise a username parameter (alphanumerics, underscores, hyphens).
     *
     * @param string $key     Parameter name
     * @param string $default Returned when the key is absent
     * @return string
     */
    public function username(string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $this->input)) {
            return $default;
        }
        return sanitise_username((string) $this->input[$key]) ?: $default;
    }

    /**
     * Extract a raw string parameter with no sanitisation applied.
     *
     * Use only when you intend to apply your own sanitisation downstream
     * (e.g. when passing the value to sanitise_html()).
     *
     * @param string $key     Parameter name
     * @param string $default Returned when the key is absent
     * @return string
     */
    public function raw(string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $this->input)) {
            return $default;
        }
        return (string) $this->input[$key];
    }

    /**
     * Check whether a key is present in the input (regardless of its value).
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->input);
    }
}
