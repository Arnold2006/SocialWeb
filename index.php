<?php
/**
 * Root index.php — Bootstrap and redirect
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    header('Location: ' . SITE_URL . '/pages/index.php');
} else {
    header('Location: ' . SITE_URL . '/pages/login.php');
}
exit;
