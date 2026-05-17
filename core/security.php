<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * security.php — Security module loader
 *
 * This file loads the individual security sub-modules:
 *   security/csrf.php         – csrf_token(), csrf_field(), csrf_verify()
 *   security/sanitizer.php    – e(), sanitise_*(), linkify(), smilify()
 *   security/session.php      – session_start_secure()
 *   security/rate_limiter.php – rate_limit()
 *   security/headers.php      – send_security_headers()
 */

declare(strict_types=1);

require_once __DIR__ . '/security/csrf.php';
require_once __DIR__ . '/security/sanitizer.php';
require_once __DIR__ . '/security/session.php';
require_once __DIR__ . '/security/rate_limiter.php';
require_once __DIR__ . '/security/headers.php';
