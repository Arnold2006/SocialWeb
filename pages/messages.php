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
 *
 * Folders : Inbox | Sent | Drafts
 * Features: threaded conversation view, image attachments, draft save/edit,
 *           quick reply from thread view, per-folder pagination.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pageTitle   = 'Messages';
$currentUser = current_user();
$uid         = (int) $currentUser['id'];

const MAIL_PER_PAGE = 25;

// ── POST actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    // ── Send or save draft ──────────────────────────────────────────────────
    if ($action === 'send' || $action === 'draft') {
        $receiverId    = sanitise_int($_POST['receiver_id'] ?? 0);
        $subject       = sanitise_string($_POST['subject'] ?? '', 255);
        $content       = sanitise_string($_POST['content'] ?? '', 5000);
        $threadId      = sanitise_int($_POST['thread_id'] ?? 0);
        $draftId       = sanitise_int($_POST['draft_id'] ?? 0);
        $attachmentIds = array_filter(array_map('intval', (array) ($_POST['attachment_ids'] ?? [])));
        $isDraft       = ($action === 'draft') ? 1 : 0;

        if ($subject === '') {
            $subject = '(no subject)';
        }

        // Sending requires a valid, non-self recipient
        if ($action === 'send') {
            if ($receiverId < 1 || $receiverId === $uid) {
                flash_set('error', 'Please select a valid recipient.');
                redirect(SITE_URL . '/pages/messages.php?compose=1');
            }
            $receiver = db_row('SELECT id FROM users WHERE id = ? AND is_banned = 0', [$receiverId]);
            if (!$receiver) {
                flash_set('error', 'Recipient not found.');
                redirect(SITE_URL . '/pages/messages.php?compose=1');
            }
        }

        // Validate thread ownership before attaching a reply to it
        $finalThreadId = null;
        if ($threadId > 0) {
            $threadRoot = db_row(
                'SELECT id FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)',
                [$threadId, $uid, $uid]
            );
            if ($threadRoot) {
                $finalThreadId = $threadId;
            }
        }

        // Update existing draft OR insert new row
        if ($draftId > 0) {
            $draft = db_row(
                'SELECT id FROM messages WHERE id = ? AND sender_id = ? AND is_draft = 1',
                [$draftId, $uid]
            );
            if ($draft) {
                db_exec(
                    'UPDATE messages
                     SET receiver_id = ?, subject = ?, content = ?, is_draft = ?, thread_id = ?
                     WHERE id = ?',
                    [$receiverId > 0 ? $receiverId : null, $subject, $content, $isDraft, $finalThreadId, $draftId]
                );
                $newMsgId = $draftId;
            } else {
                $newMsgId = 0;
            }
        } else {
            $newMsgId = (int) db_insert(
                'INSERT INTO messages (sender_id, receiver_id, subject, content, is_draft, thread_id)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$uid, $receiverId > 0 ? $receiverId : null, $subject, $content, $isDraft, $finalThreadId]
            );
        }

        // Link uploaded attachments to the now-known message id
        if ($newMsgId > 0 && !empty($attachmentIds)) {
            $ph = implode(',', array_fill(0, count($attachmentIds), '?'));
            db_exec(
                "UPDATE message_attachments
                 SET message_id = ?
                 WHERE id IN ($ph) AND sender_id = ? AND message_id IS NULL",
                array_merge([$newMsgId], $attachmentIds, [$uid])
            );
        }

        if ($action === 'send' && $newMsgId > 0 && $receiverId > 0) {
            notify_user($receiverId, 'message', $uid, $newMsgId);
            flash_set('success', 'Message sent.');
            redirect(SITE_URL . '/pages/messages.php?folder=sent');
        } else {
            flash_set('success', 'Draft saved.');
            redirect(SITE_URL . '/pages/messages.php?folder=drafts');
        }
    }

    // ── Delete a single message ─────────────────────────────────────────────
    if ($action === 'delete') {
        $delId = sanitise_int($_POST['msg_id'] ?? 0);
        if ($delId > 0) {
            db_exec('UPDATE messages SET is_deleted_sender   = 1 WHERE id = ? AND sender_id   = ?', [$delId, $uid]);
            db_exec('UPDATE messages SET is_deleted_receiver = 1 WHERE id = ? AND receiver_id = ?', [$delId, $uid]);
            flash_set('success', 'Message deleted.');
        }
        redirect(SITE_URL . '/pages/messages.php');
    }

    // ── Delete entire thread ────────────────────────────────────────────────
    if ($action === 'delete_thread') {
        $rootId = sanitise_int($_POST['root_id'] ?? 0);
        if ($rootId > 0) {
            db_exec(
                'UPDATE messages SET is_deleted_receiver = 1
                 WHERE COALESCE(thread_id, id) = ? AND receiver_id = ?',
                [$rootId, $uid]
            );
            db_exec(
                'UPDATE messages SET is_deleted_sender = 1
                 WHERE COALESCE(thread_id, id) = ? AND sender_id = ?',
                [$rootId, $uid]
            );
            flash_set('success', 'Conversation deleted.');
        }
        redirect(SITE_URL . '/pages/messages.php');
    }
}

// ── GET parameters ────────────────────────────────────────────────────────────
$folder    = match ($_GET['folder'] ?? '') { 'sent' => 'sent', 'drafts' => 'drafts', default => 'inbox' };
$compose   = isset($_GET['compose']);
$replyToId = sanitise_int($_GET['reply_to'] ?? 0);
$msgId     = sanitise_int($_GET['msg'] ?? 0);
$draftEdit = sanitise_int($_GET['draft'] ?? 0);
$page      = max(1, sanitise_int($_GET['p'] ?? 1));
$offset    = ($page - 1) * MAIL_PER_PAGE;

// Draft-edit mode: treat as compose with pre-filled data
$editDraft = null;
if ($draftEdit > 0 && !$compose) {
    $editDraft = db_row(
        'SELECT * FROM messages WHERE id = ? AND sender_id = ? AND is_draft = 1',
        [$draftEdit, $uid]
    );
    if ($editDraft) {
        $compose = true;
    }
}

// ── Thread resolution ─────────────────────────────────────────────────────────
// Determine the thread root ID from the selected message, then load all messages
// in that thread visible to the current user.

$threadRootId     = 0;
$threadMessages   = [];
$threadSubject    = '';
$otherParticipant = null;
$attachMap        = [];

if ($msgId > 0 && !$compose) {
    $msgRef = db_row(
        'SELECT id, thread_id FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)',
        [$msgId, $uid, $uid]
    );
    if ($msgRef) {
        $threadRootId = $msgRef['thread_id'] !== null
            ? (int) $msgRef['thread_id']
            : (int) $msgRef['id'];
    }
}

// Mark all unread messages in this thread as read
if ($threadRootId > 0) {
    db_exec(
        'UPDATE messages SET is_read = 1
         WHERE COALESCE(thread_id, id) = ? AND receiver_id = ? AND is_read = 0 AND is_draft = 0',
        [$threadRootId, $uid]
    );
}

// Load all messages in the thread chronologically
if ($threadRootId > 0) {
    $threadMessages = db_query(
        'SELECT m.*,
                s.id AS sender_id, s.username AS sender_username, s.avatar_path AS sender_avatar,
                r.id AS receiver_id_col, r.username AS receiver_username, r.avatar_path AS receiver_avatar
         FROM messages m
         JOIN users s ON s.id = m.sender_id
         JOIN users r ON r.id = m.receiver_id
         WHERE COALESCE(m.thread_id, m.id) = ?
           AND m.is_draft = 0
           AND ((m.sender_id   = ? AND m.is_deleted_sender   = 0)
             OR (m.receiver_id = ? AND m.is_deleted_receiver = 0))
         ORDER BY m.id ASC',
        [$threadRootId, $uid, $uid]
    );

    if (!empty($threadMessages)) {
        $threadSubject = $threadMessages[0]['subject'];

        // Find the other participant (first person who is not the current user)
        foreach ($threadMessages as $tm) {
            if ((int) $tm['sender_id'] !== $uid) {
                $otherParticipant = ['id' => (int) $tm['sender_id'], 'username' => $tm['sender_username']];
                break;
            }
            if ((int) $tm['receiver_id_col'] !== $uid) {
                $otherParticipant = ['id' => (int) $tm['receiver_id_col'], 'username' => $tm['receiver_username']];
                break;
            }
        }

        // Load attachments for all messages in the thread
        $threadMsgIds = array_column($threadMessages, 'id');
        $ph           = implode(',', array_fill(0, count($threadMsgIds), '?'));
        $attachRows   = db_query(
            "SELECT * FROM message_attachments WHERE message_id IN ($ph) ORDER BY id ASC",
            $threadMsgIds
        );
        foreach ($attachRows as $att) {
            $attachMap[(int) $att['message_id']][] = $att;
        }
    }
}

// ── Mailbox list ──────────────────────────────────────────────────────────────
$totalThreads = 0;
$mailbox      = [];

if ($folder === 'sent') {
    $totalThreads = (int) db_val(
        'SELECT COUNT(DISTINCT COALESCE(thread_id, id))
         FROM messages WHERE sender_id = ? AND is_deleted_sender = 0 AND is_draft = 0',
        [$uid]
    );
    $mailbox = db_query(
        'SELECT m.id, m.subject, m.created_at,
                COALESCE(m.thread_id, m.id) AS root_id,
                ts.thread_count,
                u.id AS other_id, u.username AS other_username, u.avatar_path AS other_avatar
         FROM (
             SELECT COALESCE(thread_id, id) AS root_id,
                    MAX(id)                 AS latest_id,
                    COUNT(*)                AS thread_count
             FROM messages
             WHERE sender_id = ? AND is_deleted_sender = 0 AND is_draft = 0
             GROUP BY COALESCE(thread_id, id)
         ) AS ts
         JOIN messages m ON m.id = ts.latest_id
         JOIN users    u ON u.id = m.receiver_id
         ORDER BY m.created_at DESC
         LIMIT ? OFFSET ?',
        [$uid, MAIL_PER_PAGE, $offset]
    );
} elseif ($folder === 'drafts') {
    $totalThreads = (int) db_val(
        'SELECT COUNT(*) FROM messages WHERE sender_id = ? AND is_draft = 1 AND is_deleted_sender = 0',
        [$uid]
    );
    $mailbox = db_query(
        'SELECT m.id, m.subject, m.created_at,
                m.id AS root_id,
                1    AS thread_count,
                0    AS unread_count,
                u.id AS other_id, u.username AS other_username, u.avatar_path AS other_avatar
         FROM messages m
         LEFT JOIN users u ON u.id = m.receiver_id
         WHERE m.sender_id = ? AND m.is_draft = 1 AND m.is_deleted_sender = 0
         ORDER BY m.created_at DESC
         LIMIT ? OFFSET ?',
        [$uid, MAIL_PER_PAGE, $offset]
    );
} else {
    // Inbox: one row per thread, showing the latest received message
    $totalThreads = (int) db_val(
        'SELECT COUNT(DISTINCT COALESCE(thread_id, id))
         FROM messages WHERE receiver_id = ? AND is_deleted_receiver = 0 AND is_draft = 0',
        [$uid]
    );
    $mailbox = db_query(
        'SELECT m.id, m.subject, m.created_at, m.is_read,
                COALESCE(m.thread_id, m.id) AS root_id,
                ts.thread_count,
                ts.unread_count,
                u.id AS other_id, u.username AS other_username, u.avatar_path AS other_avatar
         FROM (
             SELECT COALESCE(thread_id, id) AS root_id,
                    MAX(id)                 AS latest_id,
                    COUNT(*)                AS thread_count,
                    SUM(is_read = 0)        AS unread_count
             FROM messages
             WHERE receiver_id = ? AND is_deleted_receiver = 0 AND is_draft = 0
             GROUP BY COALESCE(thread_id, id)
         ) AS ts
         JOIN messages m ON m.id = ts.latest_id
         JOIN users    u ON u.id = m.sender_id
         ORDER BY m.created_at DESC
         LIMIT ? OFFSET ?',
        [$uid, MAIL_PER_PAGE, $offset]
    );
}

$totalPages  = max(1, (int) ceil($totalThreads / MAIL_PER_PAGE));
$baseMailUrl = SITE_URL . '/pages/messages.php?folder=' . $folder;

// ── Reply-to message (full compose only) ─────────────────────────────────────
$replyToMsg = null;
if ($compose && $replyToId > 0 && !$editDraft) {
    $replyToMsg = db_row(
        'SELECT m.*, s.id AS sender_user_id, s.username AS sender_username,
                COALESCE(m.thread_id, m.id) AS root_id
         FROM messages m
         JOIN users s ON s.id = m.sender_id
         WHERE m.id = ? AND (m.receiver_id = ? OR m.sender_id = ?)',
        [$replyToId, $uid, $uid]
    );
}

// ── Members list for compose recipient dropdown ───────────────────────────────
$members = db_query(
    'SELECT id, username FROM users WHERE id != ? AND is_banned = 0 ORDER BY username',
    [$uid]
);

$pageScript = ASSETS_URL . '/js/messages.js';
include SITE_ROOT . '/includes/header.php';
?>

<div class="mail-layout">

    <!-- ── Top Toolbar ────────────────────────────────────────────────────── -->
    <div class="mail-toolbar">
        <div class="mail-toolbar-actions">
            <a href="<?= e(SITE_URL . '/pages/messages.php?compose=1') ?>"
               class="btn btn-primary btn-sm">&#9998; New</a>

            <?php if (!$compose && $threadRootId > 0): ?>
            <form method="POST" class="mail-delete-form"
                  onsubmit="return confirm('Delete this entire conversation?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action"  value="delete_thread">
                <input type="hidden" name="root_id" value="<?= $threadRootId ?>">
                <button type="submit" class="btn btn-danger btn-sm">&#128465; Delete</button>
            </form>
            <?php endif; ?>

            <a href="<?= e($baseMailUrl) ?>"
               class="btn btn-secondary btn-sm">&#8635; Refresh</a>
        </div>

        <div class="mail-folder-tabs">
            <a href="<?= e(SITE_URL . '/pages/messages.php') ?>"
               class="btn btn-sm <?= $folder === 'inbox'  ? 'btn-primary' : 'btn-secondary' ?>">&#128229; Inbox</a>
            <a href="<?= e(SITE_URL . '/pages/messages.php?folder=sent') ?>"
               class="btn btn-sm <?= $folder === 'sent'   ? 'btn-primary' : 'btn-secondary' ?>">&#128228; Sent</a>
            <a href="<?= e(SITE_URL . '/pages/messages.php?folder=drafts') ?>"
               class="btn btn-sm <?= $folder === 'drafts' ? 'btn-primary' : 'btn-secondary' ?>">&#128196; Drafts</a>
        </div>
    </div>

    <!-- ── Left Column – Mailbox List ─────────────────────────────────────── -->
    <aside class="mail-sidebar">
        <?php if (empty($mailbox)): ?>
        <p class="empty-state">No messages.</p>
        <?php else: ?>
        <ul class="mail-list">
            <?php foreach ($mailbox as $item):
                $itemRootId = (int) $item['root_id'];
                $isActive   = ($itemRootId === $threadRootId && !$compose);
                $isUnread   = ($folder === 'inbox' && (int) ($item['unread_count'] ?? 0) > 0);
                $isDraftItem = ($folder === 'drafts');
                $itemUrl    = $isDraftItem
                    ? SITE_URL . '/pages/messages.php?draft=' . (int)$item['id']
                    : SITE_URL . '/pages/messages.php?msg=' . (int)$item['id'] . '&folder=' . $folder . '&p=' . $page;
                $threadCount = (int) ($item['thread_count'] ?? 1);
                $otherName   = $item['other_username'] ?? null;
            ?>
            <li class="mail-item<?= $isActive ? ' active' : '' ?><?= $isUnread ? ' unread' : '' ?><?= $isDraftItem ? ' draft' : '' ?>">
                <a href="<?= e($itemUrl) ?>">
                    <div class="mail-item-head">
                        <?php if ($otherName): ?>
                        <img src="<?= e(avatar_url(['avatar_path' => $item['other_avatar'] ?? null], 'small')) ?>"
                             alt="" class="avatar avatar-small" width="28" height="28" loading="lazy">
                        <?php endif; ?>
                        <span class="mail-item-from">
                            <?= $otherName ? e($otherName) : '<em>No recipient</em>' ?>
                        </span>
                        <?php if ($isUnread): ?>
                        <span class="mail-unread-dot" title="Unread"></span>
                        <?php endif; ?>
                        <?php if ($threadCount > 1): ?>
                        <span class="mail-thread-count" title="<?= $threadCount ?> messages in conversation"><?= $threadCount ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mail-item-subject">
                        <?= $isDraftItem ? '<span class="mail-draft-badge">DRAFT</span> ' : '' ?><?= e($item['subject'] ?: '(no subject)') ?>
                    </div>
                    <div class="mail-item-date"><?= e(time_ago($item['created_at'])) ?></div>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($totalPages > 1): ?>
        <div class="mail-pagination">
            <?php if ($page > 1): ?>
            <a href="<?= e($baseMailUrl . '&p=' . ($page - 1)) ?>" class="btn btn-secondary btn-xs">&#8592; Prev</a>
            <?php endif; ?>
            <span class="mail-page-info"><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
            <a href="<?= e($baseMailUrl . '&p=' . ($page + 1)) ?>" class="btn btn-secondary btn-xs">Next &#8594;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </aside>

    <!-- ── Right Column – Thread Viewer / Compose / Empty ─────────────────── -->
    <main class="mail-viewer">

        <?php if ($compose): /* ── Compose / Edit Draft ── */ ?>

        <div class="mail-compose-wrap">
            <?php if ($editDraft): ?>
            <h2 class="mail-compose-title">&#128196; Edit Draft</h2>
            <?php elseif ($replyToMsg): ?>
            <h2 class="mail-compose-title">&#8617; Reply</h2>
            <?php else: ?>
            <h2 class="mail-compose-title">&#9998; New Message</h2>
            <?php endif; ?>

            <!-- CSRF token exposed for JS upload requests -->
            <input type="hidden" id="compose-csrf" value="<?= e(csrf_token()) ?>">

            <form method="POST" id="compose-form" class="mail-compose-form">
                <?= csrf_field() ?>
                <?php if ($editDraft): ?>
                <input type="hidden" name="draft_id" value="<?= (int)$editDraft['id'] ?>">
                <?php endif; ?>
                <?php if ($replyToMsg): ?>
                <input type="hidden" name="thread_id" value="<?= (int)$replyToMsg['root_id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="compose-to">To</label>
                    <select id="compose-to" name="receiver_id" required>
                        <option value="">— Select recipient —</option>
                        <?php
                        // Determine pre-selected recipient
                        $preselect = 0;
                        if ($editDraft && $editDraft['receiver_id']) {
                            $preselect = (int) $editDraft['receiver_id'];
                        } elseif ($replyToMsg) {
                            // Reply goes to whoever is NOT the current user
                            $preselect = ((int)$replyToMsg['sender_user_id'] !== $uid)
                                ? (int)$replyToMsg['sender_user_id']
                                : (int)$replyToMsg['receiver_id'];
                        }
                        foreach ($members as $m):
                            $sel = ($preselect === (int)$m['id']) ? ' selected' : '';
                        ?>
                        <option value="<?= (int)$m['id'] ?>"<?= $sel ?>><?= e($m['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="compose-subject">Subject</label>
                    <input type="text" id="compose-subject" name="subject" maxlength="255"
                           value="<?php
                               if ($editDraft) {
                                   echo e($editDraft['subject']);
                               } elseif ($replyToMsg) {
                                   echo e('Re: ' . preg_replace('/^(Re:\s+)+/i', '', $replyToMsg['subject']));
                               }
                           ?>"
                           placeholder="Subject">
                </div>

                <div class="form-group">
                    <label for="compose-body">Message</label>
                    <textarea id="compose-body" name="content" rows="12" maxlength="5000"
                              required placeholder="Write your message…"><?php
                        if ($editDraft) {
                            echo e($editDraft['content']);
                        } elseif ($replyToMsg) {
                            echo "\n\n--- Original message from " . e($replyToMsg['sender_username']) . " ---\n";
                            echo e($replyToMsg['content']);
                        }
                    ?></textarea>
                </div>

                <!-- Attachment area -->
                <div class="form-group mail-attach-area">
                    <div id="attach-preview-list" class="attach-preview-list"></div>
                    <div id="attach-ids-container"></div>
                    <button type="button" id="compose-attach-btn" class="btn btn-secondary btn-sm">
                        &#128206; Attach image
                    </button>
                    <input type="file" id="compose-attach-input"
                           accept="image/jpeg,image/png,image/webp,image/gif"
                           style="display:none" aria-hidden="true">
                    <span class="mail-attach-hint">JPG, PNG, WEBP or GIF &bull; max 10 MB</span>
                </div>

                <div class="mail-compose-actions">
                    <button type="submit" name="action" value="send"
                            class="btn btn-primary">&#9993; Send</button>
                    <button type="submit" name="action" value="draft"
                            class="btn btn-secondary">&#128190; Save Draft</button>
                    <a href="<?= e(SITE_URL . '/pages/messages.php?folder=' . $folder) ?>"
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <?php elseif (!empty($threadMessages)): /* ── Thread Viewer ── */ ?>

        <div class="mail-thread-wrap">
            <h2 class="mail-thread-subject"><?= e($threadSubject ?: '(no subject)') ?></h2>

            <?php foreach ($threadMessages as $tm):
                $isMine  = ((int)$tm['sender_id'] === $uid);
                $msgAtts = $attachMap[(int)$tm['id']] ?? [];
            ?>
            <div class="mail-thread-msg<?= $isMine ? ' mail-thread-msg--mine' : ' mail-thread-msg--theirs' ?>">
                <div class="mail-thread-msg-header">
                    <img src="<?= e(avatar_url(['avatar_path' => $tm['sender_avatar']], 'small')) ?>"
                         alt="" class="avatar avatar-small" width="28" height="28" loading="lazy">
                    <span class="mail-thread-msg-from"><?= e($tm['sender_username']) ?></span>
                    <span class="mail-thread-msg-to">&#8594; <?= e($tm['receiver_username']) ?></span>
                    <time class="mail-thread-msg-date"
                          datetime="<?= e($tm['created_at']) ?>"
                          title="<?= e(date('D, j M Y H:i', strtotime($tm['created_at']))) ?>">
                        <?= e(time_ago($tm['created_at'])) ?>
                    </time>
                </div>
                <div class="mail-thread-msg-body"><?= nl2br(linkify(smilify($tm['content']))) ?></div>
                <?php if (!empty($msgAtts)): ?>
                <div class="mail-thread-attachments">
                    <?php foreach ($msgAtts as $att): ?>
                    <div class="mail-attachment-item">
                        <a href="<?= e(SITE_URL . '/' . $att['file_path']) ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="mail-attachment-link"
                           data-img-url="<?= e(SITE_URL . '/' . $att['file_path']) ?>">
                            <img src="<?= e(SITE_URL . '/' . $att['file_path']) ?>"
                                 alt="<?= e($att['original_name']) ?>"
                                 class="mail-attachment-thumb" loading="lazy">
                        </a>
                        <span class="mail-attachment-name"><?= e($att['original_name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Quick reply -->
            <?php if ($otherParticipant): ?>
            <div class="mail-quick-reply">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"      value="send">
                    <input type="hidden" name="receiver_id" value="<?= (int)$otherParticipant['id'] ?>">
                    <input type="hidden" name="thread_id"   value="<?= $threadRootId ?>">
                    <input type="hidden" name="subject"
                           value="Re: <?= e(preg_replace('/^(Re:\s+)+/i', '', $threadSubject)) ?>">
                    <textarea name="content" rows="3" maxlength="5000"
                              class="mail-quick-reply-input"
                              placeholder="Reply to <?= e($otherParticipant['username']) ?>…"
                              required></textarea>
                    <div class="mail-quick-reply-actions">
                        <button type="submit" class="btn btn-primary btn-sm">&#9993; Send Reply</button>
                        <a href="<?= e(SITE_URL . '/pages/messages.php?compose=1&reply_to=' . $msgId) ?>"
                           class="btn btn-secondary btn-sm">&#128206; Add attachment</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php else: /* ── Empty state ── */ ?>

        <div class="mail-empty">
            <p>Select a conversation, or click <strong>New</strong> to compose.</p>
        </div>

        <?php endif; ?>
    </main>

</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
