<?php
/**
 * functions.php — Shared utility functions
 */

declare(strict_types=1);

/**
 * Redirect to a URL.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Return paginated results.
 *
 * @param string $sql     Base SQL without LIMIT/OFFSET
 * @param array  $params
 * @param int    $page
 * @param int    $perPage
 * @return array{rows: array, total: int, pages: int, page: int}
 */
function paginate(string $sql, array $params, int $page = 1, int $perPage = 20): array
{
    $page    = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset  = ($page - 1) * $perPage;

    $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _count_q';
    $total    = (int) db_val($countSql, $params);
    $pages    = (int) ceil($total / $perPage);

    $rows = db_query($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);

    return compact('rows', 'total', 'pages', 'page');
}

/**
 * Render pagination links.
 */
function pagination_links(int $current, int $total, string $baseUrl): string
{
    if ($total <= 1) {
        return '';
    }

    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $total; $i++) {
        $active = ($i === $current) ? ' active' : '';
        $sep    = str_contains($baseUrl, '?') ? '&' : '?';
        $html  .= '<a href="' . e($baseUrl . $sep . 'page=' . $i) . '" class="page-link' . $active . '">' . $i . '</a> ';
    }
    $html .= '</nav>';
    return $html;
}

/**
 * Format a datetime string to a human-readable relative time.
 */
function time_ago(string $datetime): string
{
    $time = strtotime($datetime);
    if ($time === false) {
        return $datetime;
    }
    $diff = time() - $time;

    if ($diff < 60)        return 'just now';
    if ($diff < 3600)      return (int)($diff / 60) . ' min ago';
    if ($diff < 86400)     return (int)($diff / 3600) . ' hr ago';
    if ($diff < 604800)    return (int)($diff / 86400) . ' days ago';
    if ($diff < 2592000)   return (int)($diff / 604800) . ' weeks ago';
    return date('M j, Y', $time);
}

/**
 * Generate the URL for an avatar given a user row.
 *
 * @param array  $user
 * @param string $size  small | medium | large
 */
function avatar_url(array $user, string $size = 'medium'): string
{
    if (!empty($user['avatar_path'])) {
        // Replace /large/ with the requested size
        $path = str_replace('/avatars/large/', '/avatars/' . $size . '/', $user['avatar_path']);
        return SITE_URL . $path;
    }
    return SITE_URL . '/assets/images/default_avatar.svg';
}

/**
 * Generate the URL for a media item by size.
 *
 * @param array  $media  row from media table
 * @param string $size   thumb | medium | large | original
 */
function get_media_url(array $media, string $size = 'medium'): string
{
    $field = match ($size) {
        'thumb'    => 'thumb_path',
        'large'    => 'large_path',
        'original' => 'storage_path',
        default    => 'medium_path',
    };

    $path = $media[$field] ?? $media['storage_path'] ?? '';
    if (empty($path)) {
        return SITE_URL . '/assets/images/placeholder.svg';
    }

    // Convert absolute path to URL
    $relative = str_replace(SITE_ROOT, '', $path);
    return SITE_URL . str_replace('\\', '/', $relative);
}

/**
 * Flash messages stored in session.
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    $messages = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $messages;
}

function flash_render(): string
{
    $messages = flash_get();
    if (empty($messages)) {
        return '';
    }
    $html = '';
    foreach ($messages as $msg) {
        $type = in_array($msg['type'], ['success', 'error', 'info', 'warning'], true) ? $msg['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . '">' . e($msg['message']) . '</div>';
    }
    return $html;
}

/**
 * Count unread notifications for the current user.
 */
function unread_notifications_count(): int
{
    $user = current_user();
    if (!$user) return 0;
    return (int) db_val(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
        [$user['id']]
    );
}

/**
 * Count unread messages for the current user.
 */
function unread_messages_count(): int
{
    $user = current_user();
    if (!$user) return 0;
    return (int) db_val(
        'SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0 AND is_deleted_receiver = 0',
        [$user['id']]
    );
}

/**
 * Count unread chat messages (from the real-time chat system) for the current user.
 */
function unread_chat_count(): int
{
    $user = current_user();
    if (!$user) return 0;
    // Count messages in conversations where the current user is NOT the sender
    return (int) db_val(
        'SELECT COUNT(*)
         FROM   chat_messages cm
         JOIN   conversations c ON c.id = cm.conversation_id
         WHERE  (c.user1_id = ? OR c.user2_id = ?)
           AND  cm.sender_id != ?
           AND  cm.is_read   = 0',
        [$user['id'], $user['id'], $user['id']]
    );
}

/**
 * Get site setting from DB.
 */
function site_setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $val = db_val('SELECT value FROM site_settings WHERE `key` = ? LIMIT 1', [$key]);
        $cache[$key] = $val !== null ? (string) $val : $default;
    }
    return $cache[$key];
}

/**
 * Return the list of valid colour theme slugs.
 */
function valid_themes(): array
{
    return ['blue-red', 'gray-orange', 'purple-red', 'green-teal', 'dark-gold', 'navy-cyan'];
}

/**
 * Return the active site theme slug, falling back to 'blue-red'.
 */
function active_theme(): string
{
    $theme = site_setting('site_theme', 'blue-red');
    return in_array($theme, valid_themes(), true) ? $theme : 'blue-red';
}
