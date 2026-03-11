# SocialWeb

An invite-only social network platform built with PHP 8.3, MySQL/MariaDB, and vanilla JavaScript. Self-hosted, no external dependencies.

## Features

- **Invite-only registration** — Admins generate invite codes with expiry dates and usage limits
- **Wall / News Feed** — Posts with text and media, likes, comments, cached HTML feed
- **User Profiles** — Avatar (with crop tool), bio, friend list, recent posts
- **Friend System** — Send/accept/remove friend requests with notifications
- **Private Messaging** — Inbox, threaded conversations, unread indicators
- **Gallery** — Albums, image/video uploads, lightbox viewer, progressive loading
- **Shoutbox** — Real-time AJAX polling in the sidebar
- **Notifications** — Likes, comments, friend requests, messages
- **Admin Panel** — User management, invite management, content moderation, media management
- **Plugin System** — Drop-in plugins can add sidebar widgets, wall widgets, menu items, and profile extensions
- **Dark Theme** — Minimalist Oxwall-inspired dark UI using system fonts only
- **Security** — CSRF tokens, prepared statements, session hardening, rate limiting, security headers
- **Media Processing** — EXIF stripping, multi-size image generation, video thumbnail generation, SHA256 deduplication
- **Performance** — File-based HTML cache, progressive image loading, IntersectionObserver lazy loading

## Requirements

- PHP 8.3+
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite (or Nginx with equivalent config)

### Required PHP extensions

| Extension | Purpose |
|-----------|---------|
| `pdo` + `pdo_mysql` | Database connectivity |
| `gd` | Image resizing, resampling, EXIF stripping |
| `fileinfo` | MIME type detection for uploads |
| `mbstring` | Multibyte / UTF-8 string handling |
| `json` | API responses and file-based cache (bundled in PHP 8) |
| `session` | User session management (bundled) |
| `filter` | Email validation / input sanitisation (bundled) |
| `hash` | Password hashing, CSRF tokens, SHA-256 deduplication (bundled) |
| `pcre` | Regular expressions (bundled) |

> **Note:** Extensions marked *bundled* are compiled into PHP by default on most distributions and do not need a separate install step.

### Recommended PHP extensions

| Extension | Purpose |
|-----------|---------|
| `iconv` | Fallback multibyte helpers when `mbstring` is unavailable (usually bundled) |
| `opcache` | Bytecode cache for better performance |

### Optional external tools

| Tool | Purpose |
|------|---------|
| `ffmpeg` / `ffprobe` | Video thumbnail generation and metadata stripping |

### Installing extensions (Debian / Ubuntu)

```bash
sudo apt install php8.3-pdo php8.3-mysql php8.3-gd php8.3-mbstring php8.3-fileinfo
# Restart your web server after installing:
sudo systemctl restart apache2   # or nginx
```

### Installing extensions (RHEL / CentOS / AlmaLinux)

```bash
sudo dnf install php-pdo php-mysqlnd php-gd php-mbstring php-fileinfo
sudo systemctl restart php-fpm
```

## Installation

1. **Clone / extract** the project into your web server's document root (e.g. `/var/www/html/SocialWeb`)

2. **Create the database**:
   ```sql
   CREATE DATABASE socialweb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'socialweb_user'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON socialweb.* TO 'socialweb_user'@'localhost';
   ```

3. **Configure the application** — copy and edit:
   ```bash
   cp core/config.php core/config.local.php
   # Edit core/config.local.php with your DB credentials and SITE_URL
   ```

4. **Set permissions**:
   ```bash
   chmod 755 uploads/ cache/
   chmod -R 755 uploads/
   ```

5. **Run the setup wizard** — visit `http://yoursite/setup.php` in your browser to:
   - Create the database tables
   - Create the first admin user
   - Generate the first invite code

6. **Delete setup.php** after installation is complete.

7. **Invite users** — log in as admin, go to Admin → Invites, generate invite codes and share them.

## File Structure

```
/
├── admin/              Admin panel pages
├── assets/
│   ├── css/style.css   Dark theme CSS
│   ├── js/             Vanilla JS modules
│   └── images/         Static assets (SVG icons)
├── cache/              HTML cache files (auto-managed)
├── core/               Framework files (db, auth, security, etc.)
├── database/           SQL schema
├── includes/           Shared PHP includes (header, footer, functions)
├── modules/            Feature modules (wall, profile, gallery, etc.)
├── pages/              Public-facing pages
├── plugins/            Drop-in plugin directory
├── uploads/            User-uploaded content
│   ├── avatars/        Avatar sizes: small/medium/large
│   ├── images/         Photo sizes: original/large/medium/thumbs
│   └── videos/         Videos: original/processed/thumbnails
├── index.php           Entry point (redirects to login or wall)
└── setup.php           One-time installation wizard
```

## Security

- All user input is sanitised and validated
- All database queries use prepared statements
- CSRF tokens on all forms
- Passwords hashed with `password_hash()` (bcrypt)
- Session fixation protection (`session_regenerate_id()`)
- Security headers: CSP, X-Frame-Options, X-Content-Type-Options
- Rate limiting on login and registration
- Media: MIME type validated with `finfo`, EXIF stripped via GD re-encoding
- Uploads directory protected by `.htaccess`

## Plugin Development

Create a directory in `/plugins/my_plugin/` containing `plugin.php`:

```php
<?php
function plugin_register_my_plugin(array &$registry): void
{
    $registry['sidebar_widgets'][] = function() {
        echo '<div class="widget"><h3 class="widget-title">My Widget</h3></div>';
    };
}
```

Then enable it in the database:
```sql
INSERT INTO plugins (name, slug, version, is_enabled)
VALUES ('My Plugin', 'my_plugin', '1.0.0', 1);
```