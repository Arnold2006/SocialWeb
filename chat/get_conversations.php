<?php
/**
 * get_conversations.php — Return JSON list of conversations for the current user.
 *
 * GET /chat/get_conversations.php
 *
 * Response:
 *   { ok: true, conversations: [ { id, other_user: {id, username, avatar_url}, last_message,
 *                                   last_time, unread_count } ] }
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$user = current_user();
$uid  = (int) $user['id'];

// Single query: conversations + other-user info + latest message + unread count.
// user1_id is always the smaller ID so IF() resolves the "other" user in both directions.
$conversations = db_query(
    'SELECT
         c.id,
         c.last_message_time,
         IF(c.user1_id = ?, c.user2_id, c.user1_id) AS other_user_id,
         ou.username    AS other_username,
         ou.avatar_path AS other_avatar_path,
         lm.message_text AS last_msg_text,
         lm.image_path   AS last_msg_image,
         COALESCE(uc.unread_count, 0) AS unread_count
     FROM  conversations c
     JOIN  users ou ON ou.id = IF(c.user1_id = ?, c.user2_id, c.user1_id)
                   AND ou.is_banned = 0
     LEFT JOIN chat_messages lm
           ON lm.id = (
               SELECT cm2.id
               FROM   chat_messages cm2
               WHERE  cm2.conversation_id = c.id
               ORDER  BY cm2.id DESC
               LIMIT  1
           )
     LEFT JOIN (
         SELECT cm3.conversation_id, COUNT(*) AS unread_count
         FROM   chat_messages cm3
         WHERE  cm3.sender_id != ? AND cm3.is_read = 0
         GROUP  BY cm3.conversation_id
     ) uc ON uc.conversation_id = c.id
     WHERE  c.user1_id = ? OR c.user2_id = ?
     ORDER  BY c.last_message_time DESC',
    [$uid, $uid, $uid, $uid, $uid]
);

$result = [];
foreach ($conversations as $conv) {
    // Build last-message preview text
    $preview = '';
    if ($conv['last_msg_text'] !== null && $conv['last_msg_text'] !== '') {
        $preview = mb_substr($conv['last_msg_text'], 0, 60);
    } elseif ($conv['last_msg_image'] !== null) {
        $preview = '📷 Image';
    }

    $other = ['avatar_path' => $conv['other_avatar_path']];

    $result[] = [
        'id'           => (int) $conv['id'],
        'other_user'   => [
            'id'         => (int) $conv['other_user_id'],
            'username'   => $conv['other_username'],
            'avatar_url' => avatar_url($other, 'small'),
        ],
        'last_message' => $preview,
        'last_time'    => $conv['last_message_time'] ? time_ago($conv['last_message_time']) : '',
        'unread_count' => (int) $conv['unread_count'],
    ];
}

echo json_encode(['ok' => true, 'conversations' => $result]);
