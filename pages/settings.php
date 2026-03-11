<?php
/**
 * settings.php — Redirects to the user's own profile page where settings now live.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$user = current_user();
redirect(SITE_URL . '/pages/profile.php?id=' . (int)$user['id']);
