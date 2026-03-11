<?php
/**
 * orphans.php — Admin orphan-file cleanup
 *
 * Scans the uploads directory for files that are no longer referenced
 * by any database record (media, users avatars, album covers, chat images,
 * or the site banner) and lets the admin delete them.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$pageTitle = 'Admin – Orphan Cleanup';

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Collect every upload file path that is currently referenced in the database.
 * Returns a set of normalised absolute filesystem paths (keys = paths, values = true).
 *
 * Uses unbuffered/streaming PDO fetches so that large tables do not cause
 * memory exhaustion.
 */
function collect_referenced_paths(): array
{
    $refs = [];
    $pdo  = db();

    // ── media table (all 5 path columns, active + soft-deleted)
    // Include ALL rows so we never mark a deduplicated shared file as orphaned.
    $stmt = $pdo->query(
        'SELECT storage_path, large_path, medium_path, thumb_path, thumbnail_path FROM media'
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        foreach (['storage_path', 'large_path', 'medium_path', 'thumb_path', 'thumbnail_path'] as $col) {
            if (!empty($row[$col])) {
                $refs[realpath($row[$col]) ?: $row[$col]] = true;
            }
        }
    }

    // ── users.avatar_path  (relative URL: /uploads/avatars/large/xxx.jpg)
    // Derive all three size variants from the single large path stored in the DB.
    $stmt = $pdo->query('SELECT avatar_path FROM users WHERE avatar_path IS NOT NULL');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (empty($row['avatar_path'])) {
            continue;
        }
        $basename = basename($row['avatar_path']);
        foreach (['large', 'medium', 'small'] as $size) {
            $abs = SITE_ROOT . '/uploads/avatars/' . $size . '/' . $basename;
            $refs[realpath($abs) ?: $abs] = true;
        }
    }

    // ── albums.cover_path  (relative URL: /uploads/images/covers/xxx.jpg)
    $stmt = $pdo->query('SELECT cover_path FROM albums WHERE cover_path IS NOT NULL');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['cover_path'])) {
            $abs = SITE_ROOT . $row['cover_path'];
            $refs[realpath($abs) ?: $abs] = true;
        }
    }

    // ── chat_messages.image_path  (relative, no leading slash: uploads/chat/xxx.jpg)
    $stmt = $pdo->query('SELECT image_path FROM chat_messages WHERE image_path IS NOT NULL');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['image_path'])) {
            $abs = SITE_ROOT . '/' . $row['image_path'];
            $refs[realpath($abs) ?: $abs] = true;
        }
    }

    // ── site_settings  banner_image  (relative URL: /uploads/banner/xxx.jpg)
    $banner = db_val("SELECT value FROM site_settings WHERE `key` = 'banner_image'");
    if (!empty($banner)) {
        $abs = SITE_ROOT . $banner;
        $refs[realpath($abs) ?: $abs] = true;
    }

    return $refs;
}

/**
 * Recursively scan a directory and return all regular file paths.
 *
 * @return string[]  Absolute filesystem paths
 */
function scan_upload_files(string $dir): array
{
    $files = [];
    if (!is_dir($dir)) {
        return $files;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile()) {
            $name = $file->getFilename();
            // Skip placeholder and VCS meta-files that should never be treated as orphans
            if ($name === 'dummy.txt' || str_starts_with($name, '.git')) {
                continue;
            }
            $real = $file->getRealPath();
            if ($real !== false) {
                $files[] = $real;
            }
        }
    }
    return $files;
}

/**
 * Find all orphaned files: on-disk files with no database reference.
 *
 * @return array[]  Each entry: ['path' => string, 'size' => int, 'mtime' => int]
 */
function find_orphans(): array
{
    $referenced = collect_referenced_paths();
    $onDisk     = scan_upload_files(UPLOADS_DIR);
    $orphans    = [];

    foreach ($onDisk as $absPath) {
        if (!isset($referenced[$absPath])) {
            $orphans[] = [
                'path'  => $absPath,
                'size'  => (int) @filesize($absPath),
                'mtime' => (int) @filemtime($absPath),
            ];
        }
    }

    // Sort by path for a stable display order
    usort($orphans, fn($a, $b) => strcmp($a['path'], $b['path']));

    return $orphans;
}

/**
 * Validate that a path is inside UPLOADS_DIR (prevents directory traversal).
 */
function path_is_in_uploads(string $absPath): bool
{
    $uploadsReal = realpath(UPLOADS_DIR);
    if ($uploadsReal === false) {
        return false;
    }
    // Normalise $absPath as well
    $real = realpath($absPath);
    if ($real === false) {
        return false;
    }
    return str_starts_with($real, $uploadsReal . DIRECTORY_SEPARATOR);
}

// ── POST actions ──────────────────────────────────────────────────────────────

$actionResult = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'delete_all') {
        $orphans  = find_orphans();
        $deleted  = 0;
        $failed   = 0;
        foreach ($orphans as $o) {
            if (path_is_in_uploads($o['path']) && @unlink($o['path'])) {
                $deleted++;
            } else {
                $failed++;
            }
        }
        flash_set('success', "Deleted {$deleted} orphan file(s)." . ($failed > 0 ? " {$failed} could not be deleted." : ''));
        redirect(SITE_URL . '/admin/orphans.php');
    }

    if ($action === 'delete_one') {
        $rawPath = $_POST['file_path'] ?? '';
        // Resolve and verify the path is inside uploads before deleting
        if ($rawPath !== '' && path_is_in_uploads($rawPath)) {
            if (@unlink($rawPath)) {
                flash_set('success', 'File deleted.');
            } else {
                flash_set('error', 'Could not delete file.');
            }
        } else {
            flash_set('error', 'Invalid file path.');
        }
        redirect(SITE_URL . '/admin/orphans.php');
    }
}

// ── Scan ──────────────────────────────────────────────────────────────────────

$orphans      = find_orphans();
$totalSize    = array_sum(array_column($orphans, 'size'));
$uploadsReal  = realpath(UPLOADS_DIR) ?: UPLOADS_DIR;

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <nav class="admin-nav">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?= SITE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php">Users</a></li>
            <li><a href="<?= SITE_URL ?>/admin/invites.php">Invites</a></li>
            <li><a href="<?= SITE_URL ?>/admin/moderation.php">Moderation</a></li>
            <li><a href="<?= SITE_URL ?>/admin/media.php">Media</a></li>
            <li><a href="<?= SITE_URL ?>/admin/settings.php">Site Settings</a></li>
            <li><a href="<?= SITE_URL ?>/admin/orphans.php" class="active">Orphan Cleanup</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>Orphan File Cleanup</h1>

        <p class="muted">
            Orphan files are upload files that exist on disk but are no longer referenced
            by any database record (media, avatars, album covers, chat images, or the site banner).
            They may accumulate when users or admins delete content without the corresponding
            filesystem cleanup completing.
        </p>

        <?= flash_render() ?>

        <div class="stats-grid" style="margin-bottom:1.5rem">
            <div class="stat-card">
                <span class="stat-value"><?= count($orphans) ?></span>
                <span class="stat-label">Orphan Files Found</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $totalSize >= 1048576
                    ? number_format($totalSize / 1048576, 1) . ' MB'
                    : number_format($totalSize / 1024, 1) . ' KB' ?></span>
                <span class="stat-label">Disk Space Recoverable</span>
            </div>
        </div>

        <?php if (!empty($orphans)): ?>
        <form method="POST" onsubmit="return confirm('Delete all <?= count($orphans) ?> orphan file(s)? This cannot be undone.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_all">
            <button class="btn btn-danger" style="margin-bottom:1.5rem">
                🗑 Delete All <?= count($orphans) ?> Orphan File(s)
            </button>
        </form>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>File Path</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orphans as $o): ?>
                <?php
                    // Display a path relative to uploads dir for readability
                    $displayPath = ltrim(str_replace($uploadsReal, '', $o['path']), DIRECTORY_SEPARATOR . '/');
                ?>
                <tr>
                    <td><code><?= e($displayPath) ?></code></td>
                    <td><?= $o['size'] >= 1048576
                        ? number_format($o['size'] / 1048576, 1) . ' MB'
                        : number_format($o['size'] / 1024, 1) . ' KB' ?></td>
                    <td><?= e(date('Y-m-d H:i', $o['mtime'])) ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Delete this orphan file?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_one">
                            <input type="hidden" name="file_path" value="<?= e($o['path']) ?>">
                            <button class="btn btn-xs btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php else: ?>
        <p class="alert alert-success">✅ No orphan files found. Your uploads directory is clean.</p>
        <?php endif; ?>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
