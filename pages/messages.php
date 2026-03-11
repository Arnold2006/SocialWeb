<?php
/**
 * messages.php — Private messaging (inbox + conversation threads)
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pageTitle   = 'Messages';
$currentUser = current_user();
$withUserId  = sanitise_int($_GET['with'] ?? 0);

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $receiverId = sanitise_int($_POST['receiver_id'] ?? 0);
    $content    = sanitise_string($_POST['content'] ?? '', 5000);

    if ($receiverId > 0 && !empty($content) && $receiverId !== (int)$currentUser['id']) {
        $receiver = db_row('SELECT id FROM users WHERE id = ? AND is_banned = 0', [$receiverId]);
        if ($receiver) {
            db_insert(
                'INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)',
                [(int)$currentUser['id'], $receiverId, $content]
            );

            // Notify receiver
            db_insert(
                'INSERT INTO notifications (user_id, type, from_user_id) VALUES (?, "message", ?)',
                [$receiverId, (int)$currentUser['id']]
            );

            flash_set('success', 'Message sent.');
        }
    }
    redirect(SITE_URL . '/pages/messages.php?with=' . $receiverId);
}

// Mark messages as read in current conversation
if ($withUserId > 0) {
    db_exec(
        'UPDATE messages SET is_read = 1
         WHERE sender_id = ? AND receiver_id = ? AND is_read = 0',
        [$withUserId, (int)$currentUser['id']]
    );
}

// Load conversations (unique users I've messaged or received from)
$conversations = db_query(
    'SELECT
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS other_user_id,
        MAX(m.created_at) AS last_message_at,
        SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread
     FROM messages m
     WHERE (m.sender_id = ? AND m.is_deleted_sender = 0)
        OR (m.receiver_id = ? AND m.is_deleted_receiver = 0)
     GROUP BY other_user_id
     ORDER BY last_message_at DESC',
    [(int)$currentUser['id'], (int)$currentUser['id'], (int)$currentUser['id'], (int)$currentUser['id']]
);

// Load selected conversation messages
$convoMessages  = [];
$withUser       = null;

if ($withUserId > 0) {
    $withUser = db_row('SELECT id, username, avatar_path FROM users WHERE id = ? AND is_banned = 0', [$withUserId]);
    if ($withUser) {
        $convoMessages = db_query(
            'SELECT m.*, u.username AS sender_username, u.avatar_path AS sender_avatar
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE ((m.sender_id = ? AND m.receiver_id = ? AND m.is_deleted_sender = 0)
                 OR (m.sender_id = ? AND m.receiver_id = ? AND m.is_deleted_receiver = 0))
             ORDER BY m.created_at ASC',
            [
                (int)$currentUser['id'], $withUserId,
                $withUserId, (int)$currentUser['id'],
            ]
        );
    }
}

include SITE_ROOT . '/includes/header.php';
?>

<div class="messages-layout">

    <!-- Inbox sidebar -->
    <aside class="messages-sidebar">
        <h2>Inbox</h2>
        <?php if (empty($conversations)): ?>
        <p class="empty-state">No messages yet.</p>
        <?php else: ?>
        <ul class="conversation-list">
            <?php foreach ($conversations as $conv):
                $other = db_row('SELECT id, username, avatar_path FROM users WHERE id = ?', [(int)$conv['other_user_id']]);
                if (!$other) continue;
                $active = ((int)$conv['other_user_id'] === $withUserId) ? 'active' : '';
            ?>
            <li class="conversation-item <?= $active ?>">
                <a href="<?= e(SITE_URL . '/pages/messages.php?with=' . (int)$conv['other_user_id']) ?>">
                    <img src="<?= e(avatar_url($other, 'small')) ?>"
                         alt="<?= e($other['username']) ?>"
                         class="avatar avatar-small" width="36" height="36" loading="lazy">
                    <span class="conv-username"><?= e($other['username']) ?></span>
                    <?php if ($conv['unread'] > 0): ?>
                    <span class="badge"><?= (int)$conv['unread'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </aside>

    <!-- Conversation thread -->
    <main class="messages-main">
        <?php if ($withUser): ?>
        <div class="conversation-header">
            <img src="<?= e(avatar_url($withUser, 'small')) ?>" alt="" width="36" height="36" class="avatar avatar-small">
            <h2><?= e($withUser['username']) ?></h2>
        </div>

        <div class="message-thread" id="message-thread">
            <?php foreach ($convoMessages as $msg): ?>
            <div class="message-item <?= ((int)$msg['sender_id'] === (int)$currentUser['id']) ? 'sent' : 'received' ?>">
                <img src="<?= e(avatar_url(['avatar_path' => $msg['sender_avatar']], 'small')) ?>"
                     alt="<?= e($msg['sender_username']) ?>"
                     class="avatar avatar-small" width="28" height="28" loading="lazy">
                <div class="message-bubble">
                    <p><?= nl2br(e($msg['content'])) ?></p>
                    <time><?= e(time_ago($msg['created_at'])) ?></time>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" class="message-compose">
            <?= csrf_field() ?>
            <input type="hidden" name="receiver_id" value="<?= (int)$withUser['id'] ?>">
            <textarea name="content" placeholder="Write a message…" rows="3" maxlength="5000" required></textarea>
            <button type="submit" class="btn btn-primary">Send</button>
        </form>

        <?php else: ?>
        <p class="empty-state">Select a conversation to view messages, or find a member to start a conversation.</p>
        <?php endif; ?>
    </main>

</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
