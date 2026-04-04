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
 * footer.php — Global site footer
 */

declare(strict_types=1);

$_footerUser = current_user();
?>
</div><!-- /.container -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> &mdash; All rights reserved.</p>
</footer>

<!-- ── Back to top button ──────────────────────────────────── -->
<button id="back-to-top" class="back-to-top-btn" aria-label="Back to top" title="Back to top">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 12l1.41 1.41L11 7.83V20h2V7.83l5.58 5.59L20 12l-8-8-8 8z"/>
    </svg>
</button>

<?php if ($_footerUser): ?>
<!-- ── Chat widget (Oxwall-style) ──────────────────────────── -->
<div id="chat-widget" data-user-id="<?= (int) $_footerUser['id'] ?>">

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

<!-- ── Welcome modal ────────────────────────────────────────────── -->
<div id="welcome-modal" class="info-modal" style="display:none"
     role="dialog" aria-modal="true" aria-labelledby="welcome-modal-title">
    <div class="info-modal-inner">
        <button type="button" class="info-modal-close" aria-label="Close">&times;</button>
        <h2 id="welcome-modal-title">Welcome to SocialWeb</h2>
        <p class="info-modal-subtitle">YOUR PRIVATE MEDIA &amp; SOCIAL SANCTUARY</p>
        <div class="info-modal-grid">
            <div>
                <h4>📋 The Wall (Newsfeed)</h4>
                <p>The Wall is the heart of the community. Share your thoughts, photos, or videos here.</p>
                <blockquote class="info-modal-tip"><strong>Pro Tip:</strong> This Wall features &ldquo;Bumping.&rdquo; Every time a post receives a new comment or a like, it automatically jumps to the very top of the feed!</blockquote>
            </div>
            <div>
                <h4>🎑 Immersive Viewing</h4>
                <p>Click any photo in an album to enter the <strong>Lightbox</strong>. Use your <strong>Left/Right arrow keys</strong> to flip through the entire album without leaving the page.</p>
            </div>
            <div>
                <h4>🌟 Smart Gallery</h4>
                <p>Create custom albums to organize your life. You can drag and drop multiple files directly from your computer into an album to start an upload.</p>
            </div>
            <div>
                <h4>💬 Multi-Chat Dock</h4>
                <p>Open the Chat at the bottom right. You can select multiple members to talk to; each conversation will spawn its own private window, allowing you to multitask side-by-side.</p>
            </div>
            <div>
                <h4>✍️ Personal Blog</h4>
                <p>Every member gets their own blog. Write long-form posts with the <strong>rich-text editor</strong> &mdash; add headings, links, lists, and images. Blog posts also appear in the newsfeed so your community never misses a story.</p>
            </div>
        </div>
        <div class="info-modal-footer-box">
            <p class="info-modal-footer-title">🛡️ Your Privacy is Hardcoded</p>
            <p>Every time you upload an image or video, our server acts as a digital shredder. It re-renders the file to physically destroy <strong>GPS coordinates, device serial numbers</strong>, and <strong>timestamps</strong>. You share the moment; we hide the location.</p>
        </div>
    </div>
</div>

<!-- ── How it Works modal ────────────────────────────────────── -->
<div id="how-it-works-modal" class="info-modal" style="display:none"
     role="dialog" aria-modal="true" aria-labelledby="how-it-works-modal-title">
    <div class="info-modal-inner">
        <button type="button" class="info-modal-close" aria-label="Close">&times;</button>
        <h2 id="how-it-works-modal-title" class="info-modal-tech-title">TECHNICAL SPECIFICATIONS &amp; PRIVACY PROTOCOLS</h2>
        <div class="info-modal-grid">
            <div>
                <h4>🌐 Network Encapsulation</h4>
                <p>The application is strictly bound to <code>127.0.0.1:81</code>. By disabling public listeners on all network interfaces (0.0.0.0), we eliminate &ldquo;Side-Channel Leaks.&rdquo; The server is invisible to the LAN; the only entry point is an ncrypted Firewall circuit.</p>
            </div>
            <div>
                <h4>🛡️ Forensic Media Scrubbing</h4>
                <p>Unlike standard sites that simply &ldquo;hide&rdquo; metadata, our media engine (PHP-GD &amp; FFmpeg) performs a <strong>pixel-level re-render</strong> of every upload. By creating a brand-new canvas and copying only the raw visual data, we physically destroy the EXIF, IPTC, and XMP headers that contain GPS and device forensics.</p>
            </div>
            <div>
                <h4>🔑 Session Integrity</h4>
                <p>We utilize strictly typed <strong>HttpOnly</strong> and <strong>SameSite</strong> cookie attributes. This prevents client-side scripts from accessing session identifiers. Furthermore, every sensitive action (deletion, password changes) is protected by a <strong>Cryptographic CSRF Token</strong> unique to your current session.</p>
            </div>
            <div>
                <h4>🚫 Zero-External Dependencies</h4>
                <p>SocialWeb is a <strong>Closed-Loop Ecosystem</strong>. We host every font, script, and icon locally. By refusing to call external CDNs (like Google or Cloudflare), we ensure your browser never makes a request to the clear-web, preventing IP leaks.</p>
            </div>
        </div>
        <div class="info-modal-footer-box">
            <p class="info-modal-footer-title">💾 Data Sovereignty &amp; Disposal</p>
            <p>Our deletion protocol is <strong>Absolute</strong>. When a user deletes a post, image, or account, the server doesn&rsquo;t just remove a database entry&mdash;it actively &ldquo;unlinks&rdquo; the physical files from the storage clusters. Our Admin Disk Scrubber runs heuristics to find and shred any unreferenced data fragments, maintaining a zero-residual footprint.</p>
        </div>
        <div class="info-modal-footer-box">
            <p class="info-modal-footer-title">🔒 Private, Encrypted &amp; Self-Hosted &mdash; We Trust No One Else With Your Data</p>
            <p>This site runs on a <strong>privately owned, fully encrypted server</strong> that we manage ourselves, from hardware to software. All data at rest is encrypted, and a separate <strong>encrypted backup</strong> runs on an isolated machine under our direct control. We do <strong>not</strong> use big-tech hosting providers or cloud storage platforms&mdash;not because we can&rsquo;t, but because <strong>trust is something that must be earned</strong>. Handing your conversations and photos to a corporation whose business model is data harvesting defeats the entire purpose of a private network. By self-hosting every component, we eliminate third-party access, government back-door clauses buried in Terms of Service, and the ever-present risk of a vendor selling out. <em>Your data lives on our hardware, under our lock and key, and no one else&rsquo;s.</em></p>
        </div>
    </div>
</div>

<!-- ── Terms of Use modal ─────────────────────────────────────── -->
<div id="terms-modal" class="info-modal" style="display:none"
     role="dialog" aria-modal="true" aria-labelledby="terms-modal-title">
    <div class="info-modal-inner">
        <button type="button" class="info-modal-close" aria-label="Close">&times;</button>
        <h2 id="terms-modal-title">Terms of Use</h2>
        <p>By accessing or using this site, you agree to the following terms.</p>
        <div class="info-modal-grid">
            <div>
                <h4>1. Invite-Only Access</h4>
                <p>This website is a private, invite-only community. Only users who have been invited may create an account or access the platform. Access may be revoked at any time by the site administrators.</p>
            </div>
            <div>
                <h4>2. User Responsibility</h4>
                <p>All content posted on this website, including text, comments, images, videos, or other media, is the sole responsibility of the user who posted it.</p>
                <p>The website and its administrators are not responsible or liable for any user-generated content.</p>
            </div>
            <div>
                <h4>3. Artistic Content</h4>
                <p>This website is an art community. Artistic nudity in images or videos is allowed. Users should understand that such content may appear on the platform.</p>
            </div>
            <div>
                <h4>4. Behavior</h4>
                <p>Users are expected to behave in a civilized and respectful manner. Harassment, illegal activity, or abusive behavior may result in removal of content or termination of access.</p>
            </div>
            <div>
                <h4>5. Administration Rights</h4>
                <p>Because this is a private community, the administrators may remove content or revoke access at their discretion.</p>
            </div>
            <div>
                <h4>6. Acceptance</h4>
                <p>By using this website, you acknowledge and accept these terms.</p>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript modules (vanilla JS, local only) -->
<script src="<?= ASSETS_URL ?>/js/app.js"></script>
<script src="<?= ASSETS_URL ?>/js/shoutbox.js"></script>
<script src="<?= ASSETS_URL ?>/js/lightbox.js"></script>
<script src="<?= ASSETS_URL ?>/js/progressive_loader.js"></script>
<script src="<?= ASSETS_URL ?>/js/notifications.js"></script>
<?php if ($_footerUser): ?>
<script src="<?= ASSETS_URL ?>/js/chat.js"></script>
<?php endif; ?>
<?php if (!empty($pageScript ?? '')): ?>
<script src="<?= e($pageScript) ?>"></script>
<?php endif; ?>

</body>
</html>
