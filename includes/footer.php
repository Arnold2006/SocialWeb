<?php
/**
 * footer.php — Global site footer
 */

declare(strict_types=1);

$_footerUser = current_user();
?>
</div><!-- /.container -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> &mdash; All rights reserved.</p>
</footer>

<?php if ($_footerUser): ?>
<!-- ── Chat widget (Oxwall-style) ──────────────────────────── -->
<div id="chat-widget">

    <!-- CSRF token for AJAX requests -->
    <input type="hidden" id="chat-csrf" value="<?= e(csrf_token()) ?>">

    <!-- Chat windows grow to the left of the contact sidebar -->
    <div id="chat-windows-container"></div>

    <!-- Contact sidebar -->
    <div id="chat-sidebar" style="display:none" role="complementary" aria-label="Chat contacts">
        <div class="chat-sidebar-header">
            <svg class="chat-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
            </svg>
            <span class="chat-sidebar-title">Chat</span>
            <span id="chat-badge" class="badge" style="display:none" aria-live="polite" aria-label="Unread messages">0</span>
            <button id="chat-sidebar-close" class="chat-sidebar-close-btn" aria-label="Close chat">&#x2715;</button>
        </div>
        <div class="chat-sidebar-search">
            <input type="text" id="chat-user-search" placeholder="Find Contact"
                   autocomplete="off" aria-label="Search contacts">
        </div>
        <div id="chat-users-list" class="chat-users-list" role="list"></div>
    </div>

    <!-- Toggle button (shown when sidebar is closed) -->
    <button id="chat-toggle" class="chat-toggle-btn" aria-label="Open chat">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
        </svg>
        <span id="chat-badge-toggle" class="badge chat-toggle-badge" style="display:none" aria-live="polite" aria-label="Unread messages">0</span>
    </button>

</div><!-- /#chat-widget -->
<?php endif; ?>

<!-- JavaScript modules (vanilla JS, local only) -->
<script src="<?= ASSETS_URL ?>/js/app.js"></script>
<script src="<?= ASSETS_URL ?>/js/shoutbox.js"></script>
<script src="<?= ASSETS_URL ?>/js/lightbox.js"></script>
<script src="<?= ASSETS_URL ?>/js/progressive_loader.js"></script>
<script src="<?= ASSETS_URL ?>/js/notifications.js"></script>
<?php if ($_footerUser): ?>
<script src="<?= ASSETS_URL ?>/js/chat.js"></script>
<?php endif; ?>

</body>
</html>
