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
 * download_album.php — Stream a ZIP archive of all media in a single album.
 *
 * Only the album owner may download.  A valid authenticated session is
 * sufficient; no CSRF token is required because this is a read-only GET
 * operation that produces no side-effects.
 *
 * Query parameters:
 *   album_id  (int, required)  — the album to download
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_login();

// Allow more time for large albums
set_time_limit(300);

$user   = current_user();
$userId = (int) $user['id'];

$albumId = sanitise_int($_GET['album_id'] ?? 0);
if ($albumId <= 0) {
    http_response_code(400);
    flash_set('error', 'Invalid album.');
    redirect(SITE_URL . '/pages/gallery.php?user_id=' . $userId);
}

// Verify the album exists and belongs to the current user
$album = db_row(
    'SELECT id, title FROM albums WHERE id = ? AND user_id = ? AND is_deleted = 0',
    [$albumId, $userId]
);
if (!$album) {
    http_response_code(403);
    flash_set('error', 'Album not found or access denied.');
    redirect(SITE_URL . '/pages/gallery.php?user_id=' . $userId);
}

// ── Collect media files ───────────────────────────────────────────────────────

$uploadsReal = realpath(UPLOADS_DIR);

/**
 * Safely resolve a stored path to an absolute filesystem path and verify
 * it lives inside UPLOADS_DIR.  Returns null on failure (prevents
 * path-traversal attacks).
 *
 * @param string $path  SITE_ROOT-relative path (starts with /)
 */
$resolvePath = function (string $path) use ($uploadsReal): ?string {
    if (!str_starts_with($path, '/')) {
        return null;
    }

    $abs  = str_starts_with($path, SITE_ROOT) ? $path : SITE_ROOT . $path;
    $real = realpath($abs);

    if ($real === false || !file_exists($real)) {
        return null;
    }

    if (!str_starts_with($real, $uploadsReal . DIRECTORY_SEPARATOR)
        && $real !== $uploadsReal) {
        return null;
    }

    return $real;
};

$mediaRows = db_query(
    'SELECT id, type, storage_path, original_name
     FROM media
     WHERE album_id = ? AND user_id = ? AND is_deleted = 0
     ORDER BY created_at ASC',
    [$albumId, $userId]
);

/** @var array<array{abs: string, name: string}> $entries */
$entries = [];

foreach ($mediaRows as $row) {
    $abs = $resolvePath($row['storage_path']);
    if ($abs === null) {
        continue;
    }

    $ext    = pathinfo($abs, PATHINFO_EXTENSION);
    $folder = $row['type'] === 'video' ? 'videos' : 'images';

    // Prefer the original filename the user uploaded; fall back to the hash-based basename
    $label = !empty($row['original_name'])
        ? pathinfo($row['original_name'], PATHINFO_FILENAME)
        : pathinfo($abs, PATHINFO_FILENAME);

    // Sanitise label — keep alphanumeric, dashes, underscores only (extension added separately)
    $label = preg_replace('/[^\w\-]/', '_', $label);
    $label = substr($label, 0, 100);

    // Append media ID to guarantee uniqueness inside the archive
    $zipName = $folder . '/' . $label . '_' . (int) $row['id'] . '.' . $ext;

    $entries[] = ['abs' => $abs, 'name' => $zipName];
}

// ── Build ZIP in a temporary file ────────────────────────────────────────────

$tmpFile = tempnam(sys_get_temp_dir(), 'socialweb_album_');
if ($tmpFile === false) {
    http_response_code(500);
    flash_set('error', 'Could not create album archive. Please try again.');
    redirect(SITE_URL . '/pages/gallery.php?user_id=' . $userId . '&album=' . $albumId);
}

$zip    = new ZipArchive();
$opened = $zip->open($tmpFile, ZipArchive::OVERWRITE);
if ($opened !== true) {
    @unlink($tmpFile);
    http_response_code(500);
    flash_set('error', 'Could not create album archive. Please try again.');
    redirect(SITE_URL . '/pages/gallery.php?user_id=' . $userId . '&album=' . $albumId);
}

foreach ($entries as $entry) {
    $zip->addFile($entry['abs'], $entry['name']);
}

// Add a README so the recipient knows the contents
$albumTitle = $album['title'];
$readme  = SITE_NAME . ' — Album Export' . PHP_EOL;
$readme .= 'Album: ' . $albumTitle . PHP_EOL;
$readme .= 'Owner: ' . $user['username'] . PHP_EOL;
$readme .= 'Exported: ' . gmdate('Y-m-d H:i:s') . ' UTC' . PHP_EOL . PHP_EOL;
$readme .= 'Contents:' . PHP_EOL;
$readme .= '  images/  — photos (original resolution, EXIF stripped)' . PHP_EOL;
$readme .= '  videos/  — videos' . PHP_EOL;
$zip->addFromString('README.txt', $readme);

$zip->close();

// ── Stream the ZIP to the browser ────────────────────────────────────────────

$siteSlug   = preg_replace('/[^\w]/', '_', SITE_NAME);
$albumSlug  = preg_replace('/[^\w]/', '_', $albumTitle);
$filename   = $siteSlug . '_album_' . $albumSlug . '_' . gmdate('Ymd') . '.zip';
$size       = filesize($tmpFile);

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . $size);
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($tmpFile);

@unlink($tmpFile);
exit;
