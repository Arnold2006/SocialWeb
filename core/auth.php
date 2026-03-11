<?php
/**
 * auth.php — Authentication helpers (login, logout, guards)
 */

declare(strict_types=1);

/**
 * Return current logged-in user row or null.
 */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $user = db_row(
        'SELECT id, username, email, role, avatar_path, bio, is_banned, created_at
         FROM users WHERE id = ? AND is_banned = 0 LIMIT 1',
        [(int) $_SESSION['user_id']]
    );

    $cached = $user ?: null;
    return $cached;
}

/**
 * Check if a user is logged in.
 */
function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Check if the current user has the admin role.
 */
function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/**
 * Redirect to login if not authenticated.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . SITE_URL . '/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
}

/**
 * Require admin role; redirect to wall on failure.
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: ' . SITE_URL . '/pages/index.php');
        exit;
    }
}

/**
 * Attempt to log a user in.
 *
 * @param string $usernameOrEmail
 * @param string $password
 * @return array|null  User row on success, null on failure
 */
function login(string $usernameOrEmail, string $password): ?array
{
    // Fetch by username or email
    $user = db_row(
        'SELECT * FROM users WHERE (username = ? OR email = ?) AND is_banned = 0 LIMIT 1',
        [$usernameOrEmail, $usernameOrEmail]
    );

    if ($user === null) {
        return null;
    }

    if (!password_verify($password, $user['password'])) {
        return null;
    }

    // Rehash if needed (e.g. cost change)
    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        db_exec('UPDATE users SET password = ? WHERE id = ?', [$newHash, $user['id']]);
    }

    // Establish session
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];

    // Update last login timestamp
    db_exec('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);

    return $user;
}

/**
 * Destroy the current session (logout).
 */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Register a new user using an invite code.
 *
 * @param string $username
 * @param string $email
 * @param string $password   Plain-text, will be hashed
 * @param string $inviteCode
 * @return array{ok: bool, error: string}
 */
function register_user(string $username, string $email, string $password, string $inviteCode): array
{
    // Validate invite
    $invite = db_row(
        'SELECT * FROM invites WHERE code = ? AND is_disabled = 0
         AND (expires_at IS NULL OR expires_at > NOW())
         AND uses < max_uses LIMIT 1',
        [$inviteCode]
    );

    if ($invite === null) {
        return ['ok' => false, 'error' => 'Invalid or expired invite code.'];
    }

    // Check uniqueness
    if (db_val('SELECT COUNT(*) FROM users WHERE username = ?', [$username]) > 0) {
        return ['ok' => false, 'error' => 'Username already taken.'];
    }

    if (db_val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
        return ['ok' => false, 'error' => 'Email already registered.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $userId = db_insert(
        'INSERT INTO users (username, email, password, invite_id) VALUES (?, ?, ?, ?)',
        [$username, $email, $hash, $invite['id']]
    );

    // Increment invite usage
    db_exec('UPDATE invites SET uses = uses + 1 WHERE id = ?', [$invite['id']]);

    return ['ok' => true, 'error' => '', 'user_id' => (int) $userId];
}
