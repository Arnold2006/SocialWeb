<?php
/**
 * logout.php — End the user's session
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_logged_in()) {
    logout();
}

redirect(SITE_URL . '/pages/login.php');
