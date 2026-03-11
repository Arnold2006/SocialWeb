<?php
/**
 * footer.php — Global site footer
 */

declare(strict_types=1);
?>
</div><!-- /.container -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> &mdash; All rights reserved.</p>
</footer>

<!-- JavaScript modules (vanilla JS, local only) -->
<script src="<?= ASSETS_URL ?>/js/app.js"></script>
<script src="<?= ASSETS_URL ?>/js/shoutbox.js"></script>
<script src="<?= ASSETS_URL ?>/js/lightbox.js"></script>
<script src="<?= ASSETS_URL ?>/js/progressive_loader.js"></script>
<script src="<?= ASSETS_URL ?>/js/notifications.js"></script>

</body>
</html>
