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
 * messages.php — Webmail-style private messaging interface
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pageTitle   = 'Messages';
$currentUser = current_user();
$uid         = (int)$currentUser['id'];

// ── POST actions ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $receiverId = sanitise_int($_POST['receiver_id'] ?? 0);
        $subject    = sanitise_string($_POST['subject'] ?? '', 255);
        $content    = sanitise_string($_POST['content'] ?? '', 5000);

        if (empty($subject)) {
            $subject = '(no subject)';
        }

        if ($receiverId > 0 && !empty($content) && $receiverId !== $uid) {
            $receiver = db_row('SELECT id FROM users WHERE id = ? AND is_banned = 0', [$receiverId]);
            if ($receiver) {
                $newMsgId = db_insert(
                    'INSERT INTO messages (sender_id, receiver_id, subject, content) VALUES (?, ?, ?, ?)',
                    [$uid, $receiverId, $subject, $content]
                );
                db_insert(
                    'INSERT INTO notifications (user_id, type, from_user_id, ref_id) VALUES (?, "message", ?, ?)',
                    [$receiverId, $uid, $newMsgId]
                );
                flash_set('success', 'Message sent.');
            }
        }
        redirect(SITE_URL . '/pages/messages.php?folder=sent');
    }

    if ($action === 'delete') {
        $delId = sanitise_int($_POST['msg_id'] ?? 0);
        if ($delId > 0) {
            db_exec(
                'UPDATE messages SET is_deleted_sender = 1 WHERE id = ? AND sender_id = ?',
                [$delId, $uid]
            );
            db_exec(
                'UPDATE messages SET is_deleted_receiver = 1 WHERE id = ? AND receiver_id = ?',
                [$delId, $uid]
            );
            flash_set('success', 'Message deleted.');
        }
        redirect(SITE_URL . '/pages/messages.php');
    }
}

// ── GET parameters ────────────────────────────────────────────────
$folder    = ($_GET['folder'] ?? '') === 'sent' ? 'sent' : 'inbox';
$compose   = isset($_GET['compose']);
$replyToId = sanitise_int($_GET['reply_to'] ?? 0);
$msgId     = sanitise_int($_GET['msg'] ?? 0);

// Mark selected inbox message as read
if ($msgId > 0 && $folder === 'inbox') {
    db_exec(
        'UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ? AND is_read = 0',
        [$msgId, $uid]
    );
}

// ── Load mailbox list ─────────────────────────────────────────────
if ($folder === 'sent') {
    $mailbox = db_query(
        'SELECT m.id, m.subject, m.created_at, m.is_read,
                u.id AS other_id, u.username AS other_username, u.avatar_path AS other_avatar
         FROM messages m
         JOIN users u ON u.id = m.receiver_id
         WHERE m.sender_id = ? AND m.is_deleted_sender = 0
         ORDER BY m.created_at DESC',
        [$uid]
    );
} else {
    $mailbox = db_query(
        'SELECT m.id, m.subject, m.created_at, m.is_read,
                u.id AS other_id, u.username AS other_username, u.avatar_path AS other_avatar
         FROM messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.receiver_id = ? AND m.is_deleted_receiver = 0
         ORDER BY m.created_at DESC',
        [$uid]
    );
}

// ── Load selected message ─────────────────────────────────────────
$selectedMsg = null;
if ($msgId > 0) {
    $selectedMsg = db_row(
        'SELECT m.*,
                s.username AS sender_username, s.avatar_path AS sender_avatar,
                r.username AS receiver_username
         FROM messages m
         JOIN users s ON s.id = m.sender_id
         JOIN users r ON r.id = m.receiver_id
         WHERE m.id = ?
           AND ((m.sender_id = ? AND m.is_deleted_sender = 0)
             OR (m.receiver_id = ? AND m.is_deleted_receiver = 0))',
        [$msgId, $uid, $uid]
    );
}

// ── Load reply-to message ─────────────────────────────────────────
$replyToMsg = null;
if ($compose && $replyToId > 0) {
    $replyToMsg = db_row(
        'SELECT m.*, s.id AS sender_user_id, s.username AS sender_username
         FROM messages m
         JOIN users s ON s.id = m.sender_id
         WHERE m.id = ? AND m.receiver_id = ?',
        [$replyToId, $uid]
    );
}

// ── Members list for compose recipient dropdown ───────────────────
$members = db_query(
    'SELECT id, username FROM users WHERE id != ? AND is_banned = 0 ORDER BY username',
    [$uid]
);

include SITE_ROOT . '/includes/header.php';
?>

<div class="mail-layout">

    <!-- ── Top Toolbar ──────────────────────────────────────────── -->
    <div class="mail-toolbar">
        <div class="mail-toolbar-actions">
            <a href="<?= e(SITE_URL . '/pages/messages.php?compose=1') ?>"
               class="btn btn-primary btn-sm">&#9998; New</a>

            <?php if ($selectedMsg): ?>
            <a href="<?= e(SITE_URL . '/pages/messages.php?compose=1&reply_to=' . (int)$selectedMsg['id']) ?>"
               class="btn btn-secondary btn-sm">&#8617; Reply</a>
            <form method="POST" class="mail-delete-form"
                  onsubmit="return confirm('Delete this message?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="msg_id" value="<?= (int)$selectedMsg['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">&#128465; Delete</button>
            </form>
            <?php endif; ?>

            <a href="<?= e(SITE_URL . '/pages/messages.php?folder=' . $folder) ?>"
               class="btn btn-secondary btn-sm">&#8635; Refresh</a>
        </div>

        <div class="mail-folder-tabs">
            <a href="<?= e(SITE_URL . '/pages/messages.php') ?>"
               class="btn btn-sm <?= $folder === 'inbox' ? 'btn-primary' : 'btn-secondary' ?>">&#128229; Inbox</a>
            <a href="<?= e(SITE_URL . '/pages/messages.php?folder=sent') ?>"
               class="btn btn-sm <?= $folder === 'sent'  ? 'btn-primary' : 'btn-secondary' ?>">&#128228; Sent</a>
        </div>
    </div>

    <!-- ── Left Column – Mailbox List ───────────────────────────── -->
    <aside class="mail-sidebar">
        <?php if (empty($mailbox)): ?>
        <p class="empty-state">No messages.</p>
        <?php else: ?>
        <ul class="mail-list">
            <?php foreach ($mailbox as $item):
                $isActive = ((int)$item['id'] === $msgId);
                $isUnread = ($folder === 'inbox' && !(int)$item['is_read']);
                $itemUrl  = SITE_URL . '/pages/messages.php?msg=' . (int)$item['id'] . '&folder=' . $folder;
            ?>
            <li class="mail-item<?= $isActive ? ' active' : '' ?><?= $isUnread ? ' unread' : '' ?>">
                <a href="<?= e($itemUrl) ?>">
                    <div class="mail-item-head">
                        <img src="<?= e(avatar_url(['avatar_path' => $item['other_avatar']], 'small')) ?>"
                             alt="" class="avatar avatar-small" width="28" height="28" loading="lazy">
                        <span class="mail-item-from"><?= e($item['other_username']) ?></span>
                        <?php if ($isUnread): ?>
                        <span class="mail-unread-dot" title="Unread"></span>
                        <?php endif; ?>
                    </div>
                    <div class="mail-item-subject"><?= e($item['subject'] ?: '(no subject)') ?></div>
                    <div class="mail-item-date"><?= e(time_ago($item['created_at'])) ?></div>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </aside>

    <!-- ── Right Column – Message Viewer / Compose ──────────────── -->
    <main class="mail-viewer">
        <?php if ($compose): ?>

        <!-- Compose Form -->
        <div class="mail-compose-wrap">
            <h2 class="mail-compose-title"><?= $replyToMsg ? 'Reply' : 'New Message' ?></h2>
            <form method="POST" class="mail-compose-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send">

                <div class="form-group">
                    <label for="compose-to">To</label>
                    <select id="compose-to" name="receiver_id" required>
                        <option value="">— Select recipient —</option>
                        <?php foreach ($members as $m):
                            $sel = ($replyToMsg && (int)$replyToMsg['sender_user_id'] === (int)$m['id']) ? ' selected' : '';
                        ?>
                        <option value="<?= (int)$m['id'] ?>"<?= $sel ?>><?= e($m['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="compose-subject">Subject</label>
                    <input type="text" id="compose-subject" name="subject" maxlength="255"
                           value="<?= $replyToMsg ? e('Re: ' . preg_replace('/^(Re:\s+)+/i', '', $replyToMsg['subject'])) : '' ?>"
                           placeholder="Subject">
                </div>

                <div class="form-group">
                    <label for="compose-body">Message</label>
                    <textarea id="compose-body" name="content" rows="12" maxlength="5000"
                              required placeholder="Write your message…"><?php
                        if ($replyToMsg):
                            echo "\n\n--- Original message from " . e($replyToMsg['sender_username']) . " ---\n";
                            echo e($replyToMsg['content']);
                        endif;
                    ?></textarea>
                </div>

                <div class="mail-compose-actions">
                    <button type="submit" class="btn btn-primary">&#9993; Send</button>
                    <a href="<?= e(SITE_URL . '/pages/messages.php') ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <?php elseif ($selectedMsg): ?>

        <!-- Message Viewer -->
        <div class="mail-msg-wrap">
            <div class="mail-msg-header">
                <h2 class="mail-msg-subject"><?= e($selectedMsg['subject'] ?: '(no subject)') ?></h2>
                <table class="mail-msg-meta">
                    <tr>
                        <th>From</th>
                        <td>
                            <img src="<?= e(avatar_url(['avatar_path' => $selectedMsg['sender_avatar']], 'small')) ?>"
                                 alt="" class="avatar avatar-small" width="22" height="22" loading="lazy">
                            <?= e($selectedMsg['sender_username']) ?>
                        </td>
                    </tr>
                    <tr>
                        <th>To</th>
                        <td><?= e($selectedMsg['receiver_username']) ?></td>
                    </tr>
                    <tr>
                        <th>Date</th>
                        <td><?= e(date('D, j M Y H:i', strtotime($selectedMsg['created_at']))) ?></td>
                    </tr>
                </table>
            </div>
            <div class="mail-msg-body">
                <?= nl2br(linkify($selectedMsg['content'])) ?>
            </div>
        </div>

        <?php else: ?>

        <!-- Empty state -->
        <div class="mail-empty">
            <p>Select a message from the list, or click <strong>New</strong> to compose.</p>
        </div>

        <?php endif; ?>
    </main>

</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
