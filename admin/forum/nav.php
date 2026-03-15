<?php
/*
 * Shared forum admin navigation sidebar.
 * Included by all admin/forum/* pages.
 */
declare(strict_types=1);

$currentFile = basename($_SERVER['PHP_SELF'] ?? '');
?>
<nav class="admin-nav">
    <h3>Forum Admin</h3>
    <ul>
        <li><a href="<?= SITE_URL ?>/admin/forum/index.php"
               class="<?= $currentFile === 'index.php' ? 'active' : '' ?>">Dashboard</a></li>
        <li><a href="<?= SITE_URL ?>/admin/forum/categories.php"
               class="<?= $currentFile === 'categories.php' ? 'active' : '' ?>">Categories</a></li>
        <li><a href="<?= SITE_URL ?>/admin/forum/forums.php"
               class="<?= $currentFile === 'forums.php' ? 'active' : '' ?>">Forums</a></li>
        <li><a href="<?= SITE_URL ?>/admin/forum/moderation.php"
               class="<?= $currentFile === 'moderation.php' ? 'active' : '' ?>">Moderation</a></li>
    </ul>
    <hr style="border-color:var(--color-border);margin:0.75rem 0">
    <ul>
        <li><a href="<?= SITE_URL ?>/admin/dashboard.php">← Main Admin</a></li>
        <li><a href="<?= SITE_URL ?>/forum/index.php">View Forum →</a></li>
    </ul>
</nav>
