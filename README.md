# SocialWeb

An invite-only social network platform built with PHP 8.3, MySQL/MariaDB, and vanilla JavaScript. Self-hosted, no external dependencies.

## Features

- **Invite-only registration** — Admins generate invite codes with expiry dates and usage limits
- **Wall / News Feed** — Posts with text and media, likes, comments (with optional inline image), @mention support, cached HTML feed
- **User Profiles** — Avatar (with crop tool), full name, bio, recent posts, media download
- **Private Messaging** — Inbox, threaded reply chains with subjects, draft messages, image/file attachments, unread indicators
- **Real-time Chat** — One-on-one WebSocket-style AJAX chat with image sharing and unread counters
- **Gallery** — Albums organised into categories, image/video uploads, lightbox viewer with likes & comments, progressive loading, masonry layout, album mosaic previews
- **Videos** — Community video hub with upload, descriptions, per-video playback page, and feed integration
- **Shoutbox** — Real-time AJAX polling in the sidebar
- **Blog** — Personal blogs with a rich-text editor, drag-and-drop image uploads, comments, likes, and activity-feed integration
- **Forum** — Threaded discussion boards with categories, forums, thread locking, unread tracking, rich-text post editor, image attachments, and edit history
- **Friends & Connections** — Send/accept/decline friend requests; view any user's friend list; friend status shown on profiles
- **Privacy controls** — Per-user visibility settings for profile, wall, photos, videos, blog, and messaging; per-album privacy; four levels: Everybody, Members only, Friends only, Only me
- **Members directory** — Paginated, searchable list of all members
- **Notifications** — Likes, comments, messages, blog comments, blog likes, photo likes, photo comments, friend requests/accepts, @mention in wall comments and blog comments
- **Admin Panel** — User management, invite management, content moderation, media management, site settings (banner, description, theme, custom fonts), orphan-file cleanup
- **Plugin System** — Drop-in plugins can add sidebar widgets, wall widgets, menu items, and profile extensions
- **Multiple colour themes** — Six built-in dark themes (blue-red, gray-orange, purple-red, green-teal, dark-gold, navy-cyan), selectable from the admin panel; per-user light/dark mode toggle
- **Security** — CSRF tokens, prepared statements, session hardening, rate limiting, security headers, whitelist HTML sanitiser with smart internal/external link handling
- **Media Processing** — EXIF stripping, multi-size image generation, video thumbnail generation, SHA256 deduplication, deduplication-safe file deletion
- **Performance** — File-based HTML cache, progressive image loading, IntersectionObserver lazy loading
- **Modular architecture** — Security, media, and utility helpers are split into focused sub-modules; centralised type-safe request validation via `RequestValidator`

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

4. **Set ownership and permissions** — the web server must be able to write to
   `cache/`, `uploads/`, and `database/migrations/` (PHP deletes migration files
   after applying them).  Run the following as root, replacing `www` with the
   user your web server runs as (`www-data` on Debian/Ubuntu, `apache` on
   RHEL/CentOS):
   ```bash
   # Transfer ownership of writable directories to the web-server user
   sudo chown -R www:www cache/ uploads/ database/migrations/

   # Set directory permissions
   sudo chmod -R 755 cache/ database/migrations/
   sudo chmod -R 755 uploads/
   ```

   > **Tip:** On subsequent deployments use the included `deploy.sh` script
   > (see [Deployment](#deployment) below), which runs `git pull` and resets
   > permissions in one step.

5. **Run the setup wizard** — visit `http://yoursite/setup.php` in your browser to:
   - Create the database tables
   - Create the first admin user
   - Generate the first invite code

6. **Delete setup.php** after installation is complete.

7. **Invite users** — log in as admin, go to Admin → Invites, generate invite codes and share them.

### Web server configuration

#### Apache

Enable `AllowOverride All` (or at least `AllowOverride FileInfo Options`) for the
project directory so that the included `.htaccess` files are honoured:

```apache
<Directory /var/www/html/SocialWeb>
    AllowOverride All
</Directory>
```

The root `.htaccess` routes every request for a file inside `uploads/` through
`serve_upload.php`, which checks that the visitor is logged in before serving
the file.  The `uploads/.htaccess` blocks direct access as a fallback if
`mod_rewrite` is unavailable.

#### Nginx

Nginx does not read `.htaccess` files.  Add the following `location` blocks to
your server block instead:

```nginx
# Route all upload requests through the authenticated file server
location ~ ^/uploads/(.+)$ {
    rewrite ^/uploads/(.+)$ /serve_upload.php?file=$1 last;
}

# Deny any request that still reaches the uploads directory directly
location ^~ /uploads/ {
    deny all;
}
```

## File Structure

```
/
├── admin/                  Admin panel pages
│   ├── forum/              Forum category/forum/moderation management
│   ├── dashboard.php       Admin overview
│   ├── invites.php         Invite code management
│   ├── media.php           Media management
│   ├── moderation.php      Content moderation
│   ├── orphans.php         Orphan-file scanner and cleanup
│   ├── settings.php        Site settings (banner, description, theme, fonts)
│   └── users.php           User management
├── assets/
│   ├── css/style.css       Multi-theme dark UI CSS
│   ├── js/                 Vanilla JS modules (lightbox, masonry, chat, forum, blog editor, …)
│   └── images/             Static assets (SVG icons, placeholders)
├── cache/                  HTML cache files (auto-managed)
├── chat/                   AJAX/JSON API endpoints for the real-time chat system
├── core/                   Framework files
│   ├── security/           Security sub-modules (csrf, headers, rate_limiter, sanitizer, session)
│   ├── media/              Media-processing sub-modules (image_processor, video_processor)
│   ├── auth.php            Authentication helpers
│   ├── cache.php           File-based HTML cache
│   ├── compat.php          mbstring / iconv polyfills
│   ├── config.php          Configuration constants
│   ├── db.php              PDO database helpers
│   ├── media_processor.php Media module loader
│   ├── plugin_loader.php   Plugin discovery and registration
│   ├── privacy.php         PrivacyService — per-user content visibility controls
│   ├── RequestValidator.php Centralised type-safe request parameter extraction
│   ├── router.php          Lightweight front-controller / URL dispatcher
│   └── security.php        Security module loader
├── database/
│   ├── schema.sql          Full database schema
│   └── migrations/         Incremental SQL migration scripts (001–032)
├── forum/                  Forum pages (index, forum view, thread view, new thread, reply, edit)
├── includes/               Shared PHP includes
│   ├── functions/          Helper sub-modules (cache, media, notifications, pagination, theme)
│   ├── bootstrap.php       Application bootstrap
│   ├── footer.php          Page footer
│   ├── functions.php       Functions module loader
│   ├── header.php          Page header
│   ├── overlay_maps.php    JS overlay helpers
│   └── sidebar_widgets.php Sidebar rendering
├── modules/                Feature modules
│   ├── blog/               Blog AJAX endpoints
│   ├── forum/              Forum AJAX endpoint (user image picker)
│   ├── friends/            Friends AJAX endpoints (request, accept, decline, cancel) + sidebar widget
│   ├── gallery/            Gallery AJAX endpoints (comments, likes, media item)
│   ├── notifications/      Notifications AJAX endpoint
│   ├── profile/            Profile AJAX endpoints (avatar, media download, account deletion)
│   ├── shoutbox/           Shoutbox AJAX endpoint
│   └── wall/               Wall AJAX endpoints (posts, comments, comment editing, likes)
├── pages/                  Public-facing pages
│   ├── blog.php            Personal blog viewer
│   ├── friends.php         Friends list, incoming requests, and sent requests
│   ├── gallery.php         User gallery / album browser
│   ├── members.php         Members directory with search and pagination
│   ├── messages.php        Private messaging inbox and threads
│   ├── notifications.php   Notification centre
│   ├── photos.php          Photos hub (members grid + own albums)
│   ├── profile.php         User profile page
│   ├── settings.php        (Redirects to profile settings)
│   ├── video.php           Community video hub
│   └── video_play.php      Single video playback page
├── plugins/                Drop-in plugin directory
├── uploads/                User-uploaded content
│   ├── avatars/            Avatar sizes: small/medium/large
│   ├── images/             Photo sizes: original/large/medium/thumbs + mosaics
│   └── videos/             Videos: original/processed/thumbnails
├── deploy.sh               Deployment helper (git pull + ownership / permissions)
├── index.php               Entry point (redirects to login or wall)
├── setup.php               One-time installation wizard
└── upgrade.php             Database migration runner (run after updates)
```

## Security

- All user input is sanitised and validated via `RequestValidator` or dedicated helpers
- All database queries use prepared statements
- CSRF tokens on all forms
- Passwords hashed with `password_hash()` (bcrypt)
- Session fixation protection (`session_regenerate_id()`)
- Security headers: CSP, X-Frame-Options, X-Content-Type-Options
- Rate limiting on login and registration
- Media: MIME type validated with `finfo`, EXIF stripped via GD re-encoding
- Uploads directory protected by `.htaccess` — all requests to `uploads/` are routed through `serve_upload.php`, which enforces login before serving any file; direct access is also blocked by a deny-all `uploads/.htaccess` as defence-in-depth
- HTML sanitiser (`sanitise_html()`) uses a DOM-based whitelist approach; distinguishes internal links (relative paths and same-host absolute URLs) from external links — only external links receive `target="_blank"` and `rel="noopener noreferrer nofollow"`
- `linkify()` applies the same internal/external distinction when converting plain-text URLs to clickable links
- Security logic is split into focused sub-modules under `core/security/`: `csrf.php`, `headers.php`, `rate_limiter.php`, `sanitizer.php`, `session.php`

## Blog

Each user has a personal blog accessible from their profile page.

### Features

- **Rich-text editor** — Formatting toolbar supports bold, italic, underline, strikethrough, headings (H2/H3), ordered/unordered lists, blockquotes, and hyperlink insertion
- **Image uploads** — Insert images via drag-and-drop or the toolbar button; images are EXIF-stripped and resized server-side, then stored in a dedicated *Blog* gallery album
- **Comments** — Readers can leave comments on blog posts; authors and admins can delete comments
- **Likes** — Blog posts can be liked; likes generate a notification for the post author
- **Activity-feed integration** — Publishing a new post creates a wall entry of type `blog_post` so it appears in the news feed for followers
- **Edit & delete** — Authors can edit or soft-delete their own posts; admins can delete any post
- **Pagination** — Posts are listed newest-first, 10 per page

### Accessing the blog

| URL | Description |
|-----|-------------|
| `/pages/blog.php` | Your own blog (defaults to the logged-in user) |
| `/pages/blog.php?user_id=N` | Blog of user with ID *N* |
| `/pages/blog.php?user_id=N&page=P` | Page *P* of user *N*'s blog |

A **View Blog** button also appears on every user profile page.

### Database schema

The blog uses the `blog_posts` table created by migration `009_add_blog.sql`:

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT UNSIGNED` | Primary key |
| `user_id` | `INT UNSIGNED` | Author |
| `title` | `VARCHAR(255)` | Post title |
| `content` | `MEDIUMTEXT` | Sanitised HTML content |
| `created_at` | `DATETIME` | Publication timestamp |
| `updated_at` | `DATETIME` | Last-edited timestamp (auto-updated) |
| `is_deleted` | `TINYINT(1)` | Soft-delete flag |

## Forum

A threaded discussion board accessible from the main navigation.

### Features

- **Categories & forums** — Admins organise forums into top-level categories; each category holds one or more named forums
- **Threads & replies** — Logged-in users can start new threads and post replies; posts are written in a **rich-text editor** (bold, italic, underline, strikethrough, lists, blockquotes, hyperlinks); legacy plain-text posts are auto-linked and rendered with smiley conversion
- **Thread locking** — Admins can lock threads to prevent further replies (🔒 icon shown)
- **Unread tracking** — The forum index displays an unread counter (🔵) per forum; threads are automatically marked as read when opened
- **Edit & delete** — Post authors and admins can edit or soft-delete threads and individual posts; edited posts show an "(edited)" indicator and timestamp
- **Image attachments** — Users can attach an image from their gallery to any post
- **Pagination** — Thread lists and post lists are paginated (20 items per page)
- **Admin panel** — Full category/forum management and a moderation dashboard (delete, restore, lock/unlock threads and posts)

### Accessing the forum

| URL | Description |
|-----|-------------|
| `/forum/` | Forum index — all categories and forums with unread counters |
| `/forum/forum.php?id=N` | Thread list for forum *N* |
| `/forum/thread.php?id=N` | Posts in thread *N* |
| `/forum/new_thread.php` | Create a new thread (login required) |
| `/forum/edit_thread.php?id=N` | Edit thread title and opening post (owner or admin) |

### Admin panel routes

| URL | Description |
|-----|-------------|
| `/admin/forum/` | Forum admin dashboard with recent-activity stats |
| `/admin/forum/categories.php` | Create, edit, and delete categories |
| `/admin/forum/forums.php` | Create, edit, and delete forums |
| `/admin/forum/moderation.php` | Delete/restore threads and posts; lock/unlock threads |

### Database schema

The forum uses five tables created by migration `012_add_forum.sql` (with additions in `013_add_forum_post_media.sql`, `014_add_forum_reads.sql`, and `015_add_forum_post_edited_at.sql`):

**`forum_categories`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT UNSIGNED` | Primary key |
| `title` | `VARCHAR(100)` | Category name |
| `description` | `TEXT` | Optional description |
| `sort_order` | `INT` | Display order |

**`forum_forums`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT UNSIGNED` | Primary key |
| `category_id` | `INT UNSIGNED` | Parent category |
| `title` | `VARCHAR(100)` | Forum name |
| `description` | `TEXT` | Optional description |
| `sort_order` | `INT` | Display order within category |

**`forum_threads`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT UNSIGNED` | Primary key |
| `forum_id` | `INT UNSIGNED` | Parent forum |
| `user_id` | `INT UNSIGNED` | Thread author |
| `title` | `VARCHAR(200)` | Thread title |
| `is_locked` | `TINYINT(1)` | Lock flag (1 = no new replies) |
| `is_deleted` | `TINYINT(1)` | Soft-delete flag |
| `created_at` | `DATETIME` | Creation timestamp |
| `last_post_at` | `DATETIME` | Timestamp of most recent reply |
| `reply_count` | `INT UNSIGNED` | Number of replies |

**`forum_posts`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT UNSIGNED` | Primary key |
| `thread_id` | `INT UNSIGNED` | Parent thread |
| `user_id` | `INT UNSIGNED` | Post author |
| `content` | `TEXT` | Post body (HTML from rich editor or legacy plain text) |
| `media_id` | `INT UNSIGNED` | Optional attached image (from gallery) |
| `is_deleted` | `TINYINT(1)` | Soft-delete flag |
| `created_at` | `DATETIME` | Creation timestamp |
| `edited_at` | `DATETIME` | Last-edited timestamp (NULL if never edited) |

**`forum_reads`**

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | `INT UNSIGNED` | Reader (composite PK with `thread_id`) |
| `thread_id` | `INT UNSIGNED` | Thread that was read |
| `read_at` | `DATETIME` | When the thread was last opened |

## Private Messaging

A traditional inbox for longer, subject-based conversations, separate from the real-time chat system.

### Features

- **Inbox** — All received (non-draft) messages listed with subject, sender, and unread indicator
- **Threaded reply chains** — Each message can have a `thread_id` pointing to the root message of the reply chain
- **Draft messages** — Messages can be saved as drafts (`is_draft = 1`) before sending; drafts are only visible to the sender
- **Image / file attachments** — Attach images or files to messages; stored in `message_attachments` with orphan-cleanup support
- **Unread counters** — Navigation bar badge tracks unread messages independently of chat unread counts

### Accessing messages

| URL | Description |
|-----|-------------|
| `/pages/messages.php` | Inbox |
| `/pages/messages.php?thread_id=N` | View a message thread |

### Database schema

The messaging system uses the `messages` table (extended by migration `027_message_improvements.sql`) and the `message_attachments` table:

**`messages`** (relevant columns added by migration 027)

| Column | Type | Description |
|--------|------|-------------|
| `thread_id` | `INT UNSIGNED` | Root message ID of the reply chain (`NULL` = root/standalone) |
| `is_draft` | `TINYINT(1)` | `1` = draft (sender-only); `0` = sent |
| `receiver_id` | `INT UNSIGNED` | Nullable — allows saving drafts before choosing a recipient |

**`message_attachments`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT UNSIGNED` | Primary key |
| `message_id` | `INT UNSIGNED` | Associated message (`NULL` until sent) |
| `sender_id` | `INT UNSIGNED` | Uploader (used for orphan cleanup) |
| `file_path` | `VARCHAR(500)` | Server path to the attachment |
| `original_name` | `VARCHAR(255)` | Original file name |
| `mime_type` | `VARCHAR(100)` | Detected MIME type |
| `file_size` | `INT UNSIGNED` | File size in bytes |
| `created_at` | `DATETIME` | Upload timestamp |

## Chat

A real-time one-on-one chat system separate from the private-messaging inbox.

### Features

- **Conversations** — Each pair of users shares a single conversation thread; starting a new chat with someone you have already messaged opens the existing thread
- **Image sharing** — Users can send inline images alongside text messages
- **Unread counters** — The navigation bar badge counts unread chat messages independently of private-message unread counts
- **Activity tracking** — The `chat_activity` table records the last time each user was active in chat, enabling "online" indicators

### Accessing chat

| URL | Description |
|-----|-------------|
| `/chat/` | Chat inbox — list of all active conversations |
| `/chat/?user_id=N` | Open (or start) a conversation with user *N* |

## Gallery

A full-featured media gallery for photos and videos.

### Features

- **Categories** — Each user's gallery is organised into categories (e.g. "Main", "Travel"); albums live inside categories
- **Albums** — Users create named albums with optional descriptions and cover images; albums can be moved between categories; each album has its own privacy level (everybody / members / friends only / only me)
- **Multi-upload** — Drag-and-drop or file-picker upload of multiple images or videos in one batch
- **Lightbox** — Click any photo or video thumbnail to open a full-screen lightbox; supports keyboard navigation (← →, Esc) and closing by clicking the overlay
- **Likes & comments in lightbox** — The lightbox panel shows a per-media like button and live comment thread without leaving the page
- **Masonry layout** — Photos are displayed in a responsive masonry grid
- **Progressive loading** — Images are loaded lazily as they scroll into view via `IntersectionObserver`
- **Album mosaic** — When photos are uploaded, a 2×2 mosaic composite thumbnail is generated automatically and used as the wall-feed preview
- **SHA256 deduplication** — Duplicate uploads are detected by file hash; the existing record is reused instead of storing the file twice

### Accessing the gallery

| URL | Description |
|-----|-------------|
| `/pages/photos.php` | Photos hub: members grid and your own albums |
| `/pages/photos.php?tab=my_albums` | Your own albums directly |
| `/pages/gallery.php?user_id=N` | Gallery for user *N* |
| `/pages/gallery.php?user_id=N&album=A` | Album *A* of user *N* |

## Videos

A community-wide video hub for uploading and watching videos.

### Features

- **Upload** — Videos (MP4, WebM, OGG) are accepted; thumbnails are generated via `ffmpeg` if available, otherwise a placeholder is used
- **Description** — Each video has an optional caption/description that the owner can edit
- **Community grid** — All community videos are shown in a grid on the Videos page with thumbnails and titles
- **Playback page** — Each video has its own page for full-screen playback, description display, and owner management (edit description, delete)
- **Feed integration** — Uploading videos through an album triggers an `album_upload` wall post, keeping the news feed up to date

### Accessing videos

| URL | Description |
|-----|-------------|
| `/pages/video.php` | Community video hub |
| `/pages/video_play.php?id=N` | Play video with ID *N* |

## Members Directory

A paginated, searchable directory of all registered members.

| URL | Description |
|-----|-------------|
| `/pages/members.php` | Members list (first page) |
| `/pages/members.php?search=alice` | Search members by username or name |
| `/pages/members.php?page=N` | Page *N* of results |

## Friends & Connections

Users can connect with each other through a friend-request system.

### Features

- **Friend requests** — Send a friend request from any profile page; the recipient is notified and can accept or decline
- **Pending & sent requests** — View incoming friend requests and track sent ones from the Friends page
- **Friends list** — View your own or any other user's public friend list, with links to profiles
- **Notifications** — `friend_request` and `friend_accept` notification types; both parties are notified on acceptance

### Accessing friends

| URL | Description |
|-----|-------------|
| `/pages/friends.php` | Your own friends list, incoming requests, and sent requests |
| `/pages/friends.php?user_id=N` | Friends list for user *N* (read-only) |

### Database schema

The friends system uses the `friendships` table created by migration `029_friends_privacy.sql` (the same migration that creates the `user_privacy_settings` table for privacy controls):

| Column | Type | Description |
|--------|------|-------------|
| `id` | `INT UNSIGNED` | Primary key |
| `requester_id` | `INT UNSIGNED` | User who sent the request |
| `addressee_id` | `INT UNSIGNED` | User who received the request |
| `status` | `ENUM('pending','accepted','declined')` | Current request state |
| `created_at` | `DATETIME` | When the request was sent |
| `updated_at` | `DATETIME` | Last status change (auto-updated) |

## Privacy Controls

Each user can independently control who sees their content via per-action privacy settings managed from their profile page.

### Privacy levels

| Level | Who can see |
|-------|-------------|
| `everybody` | All visitors (including guests if applicable) |
| `members` | Any logged-in member (default) |
| `friends_only` | Only confirmed friends |
| `only_me` | Only the owner |

### Controllable actions

| Action key | Controls access to |
|------------|--------------------|
| `view_profile` | Profile page |
| `view_wall` | Wall posts |
| `view_photos` | Photo gallery |
| `view_videos` | Videos |
| `view_blog` | Blog |
| `send_message` | Ability to send a private message |

### Album privacy

Individual albums can have their own privacy level (set when creating or editing an album), overriding the user-wide `view_photos` setting for that album's content.

### Database schema

Privacy settings are stored in the `user_privacy_settings` table, created alongside the `friendships` table by migration `029_friends_privacy.sql`:

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | `INT UNSIGNED` | Owner (composite PK with `action_key`) |
| `action_key` | `VARCHAR(64)` | One of the action keys above |
| `value` | `ENUM(...)` | Chosen privacy level |

Per-album privacy is added by migration `030_add_album_privacy.sql`, which adds a `privacy` column to the `albums` table.

## Admin Panel

The admin panel is accessible at `/admin/` and is restricted to users with the `admin` role.

### Pages

| URL | Description |
|-----|-------------|
| `/admin/dashboard.php` | Overview statistics |
| `/admin/users.php` | Ban, unban, promote, or delete users |
| `/admin/invites.php` | Generate and manage invite codes |
| `/admin/moderation.php` | Review and remove flagged content |
| `/admin/media.php` | Browse and delete uploaded media |
| `/admin/settings.php` | Site banner, description, colour theme, custom fonts |
| `/admin/orphans.php` | Scan uploads for unreferenced files and delete them |
| `/admin/forum/` | Forum administration (categories, forums, moderation) |

### Site settings

- **Banner image** — Upload a JPEG/PNG/WebP banner shown at the top of every page
- **Site description** — Short tagline displayed in the header
- **Colour theme** — Choose one of six built-in dark themes: `blue-red`, `gray-orange`, `purple-red`, `green-teal`, `dark-gold`, `navy-cyan`
- **Custom fonts** — Upload WOFF2/WOFF/TTF/OTF fonts to replace the default system-font stack

### Orphan cleanup

`/admin/orphans.php` scans `uploads/` and cross-references every file against the database (media records, user avatars, album covers, chat images, site banner, and custom fonts). Files with no database reference are listed and can be bulk-deleted to reclaim disk space.

## Architecture

SocialWeb uses a modular flat-file architecture — no framework, no Composer dependencies.

### Module layout

| Path | Contents |
|------|----------|
| `core/security/` | `csrf.php`, `headers.php`, `rate_limiter.php`, `sanitizer.php`, `session.php` |
| `core/media/` | `image_processor.php`, `video_processor.php` |
| `modules/friends/` | `FriendshipService.php`, AJAX endpoints (`ajax_request.php`, `ajax_accept.php`, `ajax_decline.php`, `ajax_cancel.php`), `friend_button.php`, `widget_friends.php` |
| `includes/functions/` | `cache.php`, `media.php`, `notifications.php`, `pagination.php`, `theme.php` |
| `core/security.php` | Loader — `require_once`s all security sub-modules |
| `core/media_processor.php` | Loader — shared helpers + loads media sub-modules |
| `core/privacy.php` | `PrivacyService` — per-user visibility controls used by feed and listing queries |
| `includes/functions.php` | Loader — loads all function sub-modules |

### RequestValidator

`core/RequestValidator.php` provides a centralised, type-safe way to extract and coerce HTTP request parameters instead of calling `sanitise_int()`, `sanitise_string()`, etc. ad-hoc across pages:

```php
$v     = new RequestValidator($_GET);
$id    = $v->int('id');            // unsigned int, default 0
$page  = $v->int('page', 1);       // with custom default
$name  = $v->string('name', 50);   // trimmed/stripped, max 50 chars
$email = $v->email('email');       // validated email or ''
$q     = $v->raw('q');             // raw string for downstream sanitisation
```

### Compat layer

`core/compat.php` provides lightweight polyfills for `mb_substr`, `mb_strlen`, `mb_strtolower`, `mb_strtoupper`, and `mb_convert_encoding` using `iconv` (when available) or byte-string functions as a last resort, so the application degrades gracefully on servers without the `mbstring` extension.

### Router

`core/router.php` provides a minimal front-controller with `route()` / `dispatch()` helpers used by AJAX endpoints. Routes support `{param}` placeholders that are injected into `$_GET` on match.

## Deployment

When pulling updates to a running server, use the included `deploy.sh` script
instead of running `sudo git pull` on its own.  Running `git pull` as root
leaves all newly-added files (including migration scripts) owned by `root`, so
the web server process cannot delete them after applying migrations, resulting
in *permission denied* errors.

`deploy.sh` pulls the latest code **and** resets ownership and permissions on
all writable directories in a single step:

```bash
# Default web-server user is 'www'.  Override with WEB_USER= if needed.
sudo bash deploy.sh

# Debian / Ubuntu (web-server user is www-data):
sudo WEB_USER=www-data bash deploy.sh

# RHEL / CentOS / AlmaLinux (web-server user is apache):
sudo WEB_USER=apache bash deploy.sh
```

After the script completes, visit `http://yoursite/upgrade.php` (or run
`php upgrade.php` from the CLI) to apply any pending database migrations.

> **Why only those three directories?**  Only `cache/`, `uploads/`, and
> `database/migrations/` need web-server write access.  Source files (PHP,
> CSS, JS) are owned by the deployment user and are read-only to the web
> server, which limits the damage if the application is ever compromised.

## Upgrading

When deploying a new version, apply any pending database migrations using the built-in migration runner:

1. Deploy the new files to your server using `deploy.sh` (see [Deployment](#deployment)), which resets file ownership so PHP can delete migration files after applying them.
2. Log in as an admin and visit `http://yoursite/upgrade.php` in your browser, **or** run it from the CLI:
   ```bash
   php upgrade.php
   ```
3. Click **Apply Migrations** to run all pending SQL scripts from `database/migrations/`.
4. **Delete or restrict access to `upgrade.php`** once all migrations have been applied.

> Migrations are tracked in the `db_migrations` table and are never applied twice.

## Plugin Development

Create a directory in `/plugins/my_plugin/` containing `plugin.php`:

```php
<?php
function plugin_register_my_plugin(array &$registry): void
{
    // Sidebar widget — rendered in the right-hand sidebar on every page
    $registry['sidebar_widgets'][] = function () {
        echo '<div class="widget"><h3 class="widget-title">My Widget</h3></div>';
    };

    // Wall widget — rendered inside the news-feed / wall page
    $registry['wall_widgets'][] = function () {
        echo '<div class="widget"><p>Wall content here.</p></div>';
    };

    // Menu item — appended to the main navigation bar
    $registry['menu_items'][] = [
        'label' => '🔗 My Page',
        'url'   => SITE_URL . '/pages/my_page.php',
    ];

    // Profile extension — rendered on user profile pages; receives the profile user's ID
    $registry['profile_extensions'][] = function (int $userId) {
        echo '<div class="widget"><p>Extra info for user ' . (int)$userId . '</p></div>';
    };
}
```

### Plugin hook summary

| Hook | Type | Description |
|------|------|-------------|
| `sidebar_widgets` | `callable` | Rendered in the sidebar on every page |
| `wall_widgets` | `callable` | Rendered in the news-feed / wall |
| `menu_items` | `array{label, url}` | Appended to the main navigation |
| `profile_extensions` | `callable(int $userId)` | Rendered on user profile pages |

Then enable it in the database:
```sql
INSERT INTO plugins (name, slug, version, is_enabled)
VALUES ('My Plugin', 'my_plugin', '1.0.0', 1);
```

## License

This project is free to use, modify, fork and distribute.

You may **NOT** sell this software or redistribute it for profit.

See [LICENSE](LICENSE) for full terms.
