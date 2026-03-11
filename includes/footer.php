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
<!-- ── Chat widget ──────────────────────────────────────── -->
<div id="chat-widget">

    <!-- CSRF token for AJAX requests -->
    <input type="hidden" id="chat-csrf" value="<?= e(csrf_token()) ?>">

    <!-- Floating toggle button -->
    <button id="chat-toggle" class="chat-toggle-btn" aria-label="Open chat">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
        </svg>
        <span id="chat-badge" class="badge chat-toggle-badge" style="display:none" aria-live="polite" aria-label="Unread messages">0</span>
    </button>

    <!-- Chat panel -->
    <div id="chat-panel" class="chat-panel" style="display:none" role="dialog" aria-label="Chat">

        <!-- ── Conversations list ── -->
        <div id="chat-convs-panel" class="chat-convs-panel" style="display:none">
            <div class="chat-panel-header">
                <h3>Messages</h3>
                <button id="chat-close-btn" class="chat-close-btn" aria-label="Close chat">&#x2715;</button>
            </div>
            <div id="chat-convs-list" class="chat-convs-list" role="list"></div>
        </div>

        <!-- ── Individual conversation window ── -->
        <div id="chat-window" style="display:none">
            <div class="chat-window-header">
                <button id="chat-back-btn" class="chat-back-btn" aria-label="Back to conversations">&#8592;</button>
                <img id="chat-window-avatar" src="" alt="" width="30" height="30">
                <span id="chat-window-name"></span>
                <button class="chat-close-btn" id="chat-win-close-btn" aria-label="Close chat">&#x2715;</button>
            </div>

            <!-- Message list (also the drag-and-drop target) -->
            <div id="chat-messages" class="chat-messages" role="log" aria-live="polite" aria-label="Messages"></div>

            <!-- Compose bar -->
            <div class="chat-compose">
                <label class="chat-upload-label" title="Upload image (JPG, PNG, WEBP, GIF — max 10 MB)" aria-label="Attach image">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.41 17.41a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                    </svg>
                    <input type="file" id="chat-image-input"
                           accept="image/jpeg,image/png,image/webp,image/gif"
                           style="display:none" aria-hidden="true">
                </label>
                <input type="text" id="chat-input" class="chat-input"
                       placeholder="Type a message…" maxlength="5000" autocomplete="off"
                       aria-label="Message text">
                <button id="chat-send-btn" class="chat-send-btn">Send</button>
            </div>
        </div>

    </div><!-- /.chat-panel -->
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
