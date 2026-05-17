<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * security/headers.php — Security-oriented HTTP response headers
 */

declare(strict_types=1);

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
