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
 * download_media.php — Stream a ZIP archive of the current user's original media.
 *
 * Includes:
 *  - All original uploaded images  (images/)
 *  - All original uploaded videos  (videos/)
 *  - Avatar image                  (avatar/)
 *  - Chat message images           (chat_images/)
 *
 * This is a read-only operation so no CSRF token is required; a valid
 * authenticated session is sufficient.
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_login();

// Allow more time for large collections without killing the request
set_time_limit(300);

$user   = current_user();
$userId = (int) $user['id'];

// ── Collect files to add to the archive ──────────────────────────────────────

/**
 * Safely resolve a path to an absolute file path and verify it lives inside
 * the uploads directory.  Returns null if the file is missing or outside
 * UPLOADS_DIR (prevents path-traversal attacks).
 *
 * @param string $path  Absolute OR SITE_ROOT-relative path (starts with /)
 */
$uploadsReal = realpath(UPLOADS_DIR);

$resolvePath = function (string $path) use ($uploadsReal): ?string {
    // If the path doesn't look absolute, treat it as relative to SITE_ROOT
    if (!str_starts_with($path, '/')) {
        return null;
    }

    // Paths stored as SITE_ROOT-relative (e.g. /uploads/avatars/large/...)
    $abs = (str_starts_with($path, SITE_ROOT)) ? $path : SITE_ROOT . $path;

    $real = realpath($abs);
    if ($real === false || !file_exists($real)) {
        return null;
    }

    // Ensure the file is inside the uploads directory
    if (!str_starts_with($real, $uploadsReal . DIRECTORY_SEPARATOR)
        && $real !== $uploadsReal) {
        return null;
    }

    return $real;
};

/** @var array<array{abs: string, name: string}> $entries */
$entries = [];

// 1. Uploaded images & videos
$mediaRows = db_query(
    'SELECT storage_path, original_name, type, id FROM media
     WHERE user_id = ? AND is_deleted = 0 ORDER BY id ASC',
    [$userId]
);

foreach ($mediaRows as $row) {
    $abs = $resolvePath($row['storage_path']);
    if ($abs === null) {
        continue;
    }

    $folder = $row['type'] === 'video' ? 'videos' : 'images';
    $ext    = pathinfo($abs, PATHINFO_EXTENSION);

    // Prefer the original filename the user uploaded; fall back to the hashed basename
    $label = !empty($row['original_name'])
        ? pathinfo($row['original_name'], PATHINFO_FILENAME)
        : pathinfo($abs, PATHINFO_FILENAME);

    // Sanitise label — keep alphanumeric, dashes, underscores, dots
    $label = preg_replace('/[^\w\-.]/', '_', $label);
    $label = substr($label, 0, 100);

    // Append media ID to guarantee uniqueness inside the archive
    $zipName = $folder . '/' . $label . '_' . (int) $row['id'] . '.' . $ext;

    $entries[] = ['abs' => $abs, 'name' => $zipName];
}

// 2. Avatar
if (!empty($user['avatar_path'])) {
    $abs = $resolvePath($user['avatar_path']);
    if ($abs !== null) {
        $ext     = pathinfo($abs, PATHINFO_EXTENSION);
        $entries[] = ['abs' => $abs, 'name' => 'avatar/avatar.' . $ext];
    }
}

// 3. Chat message images sent by this user
$chatRows = db_query(
    'SELECT image_path FROM chat_messages
     WHERE sender_id = ? AND image_path IS NOT NULL',
    [$userId]
);

$chatIdx = 1;
foreach ($chatRows as $row) {
    $abs = $resolvePath($row['image_path']);
    if ($abs === null) {
        continue;
    }
    $ext     = pathinfo($abs, PATHINFO_EXTENSION);
    $entries[] = ['abs' => $abs, 'name' => 'chat_images/chat_' . $chatIdx . '.' . $ext];
    $chatIdx++;
}

// ── Build ZIP in a temporary file ────────────────────────────────────────────

$tmpFile = tempnam(sys_get_temp_dir(), 'socialweb_export_');
if ($tmpFile === false) {
    http_response_code(500);
    flash_set('error', 'Could not create export archive. Please try again.');
    redirect(SITE_URL . '/pages/profile.php?id=' . $userId);
}

$zip = new ZipArchive();
$opened = $zip->open($tmpFile, ZipArchive::OVERWRITE);
if ($opened !== true) {
    @unlink($tmpFile);
    http_response_code(500);
    flash_set('error', 'Could not create export archive. Please try again.');
    redirect(SITE_URL . '/pages/profile.php?id=' . $userId);
}

foreach ($entries as $entry) {
    $zip->addFile($entry['abs'], $entry['name']);
}

// Add a README so the user knows what they downloaded
$readme  = 'SocialWeb — Media Export' . PHP_EOL;
$readme .= 'User: ' . $user['username'] . PHP_EOL;
$readme .= 'Exported: ' . gmdate('Y-m-d H:i:s') . ' UTC' . PHP_EOL . PHP_EOL;
$readme .= 'Contents:' . PHP_EOL;
$readme .= '  images/        — uploaded photos (original resolution, EXIF stripped)' . PHP_EOL;
$readme .= '  videos/        — uploaded videos' . PHP_EOL;
$readme .= '  avatar/        — profile avatar' . PHP_EOL;
$readme .= '  chat_images/   — images sent in chat' . PHP_EOL;
$zip->addFromString('README.txt', $readme);

$zip->close();

// ── Stream the ZIP to the browser ────────────────────────────────────────────

$filename = 'socialweb_export_' . preg_replace('/[^\w]/', '_', $user['username']) . '_' . gmdate('Ymd') . '.zip';
$size     = filesize($tmpFile);

// Disable output buffering so the file streams directly
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/zip');
// Use both the legacy filename= (ASCII safe) and filename*= (RFC 5987) parameters
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . $size);
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($tmpFile);

// Clean up temp file after streaming
@unlink($tmpFile);
exit;
