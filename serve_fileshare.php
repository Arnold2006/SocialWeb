<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * serve_fileshare.php — Authenticated file-share download handler
 *
 * Serves uploaded file-share files for download.  Only logged-in members
 * may download.  Files are stored in uploads/files/ with random hex names;
 * this script maps the database ID to the stored path and sets the correct
 * Content-Disposition header so the user receives the original filename.
 *
 * Usage:  /serve_fileshare.php?id=<file_id>
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    http_response_code(403);
    exit;
}

$fileId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($fileId <= 0) {
    http_response_code(400);
    exit;
}

$row = db_row(
    'SELECT id, filename, original_name, size FROM file_share_files WHERE id = ?',
    [$fileId]
);

if ($row === null) {
    http_response_code(404);
    exit;
}

// Resolve and validate the physical path
$filesDir  = UPLOADS_DIR . DIRECTORY_SEPARATOR . 'files';
$filesReal = realpath($filesDir);
if ($filesReal === false) {
    http_response_code(500);
    exit;
}

$filePath = realpath($filesReal . DIRECTORY_SEPARATOR . $row['filename']);
if (
    $filePath === false ||
    !str_starts_with($filePath, $filesReal . DIRECTORY_SEPARATOR) ||
    !is_file($filePath)
) {
    http_response_code(404);
    exit;
}

// Build a safe ASCII-only filename for the Content-Disposition header
$safeOriginal = preg_replace('/[^\w.\-]/', '_', $row['original_name']) ?? 'file';
// Use RFC 5987 encoding for the filename* parameter so non-ASCII names work too
$encodedName  = rawurlencode($row['original_name']);

$fileSize = filesize($filePath);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $safeOriginal . '"; filename*=UTF-8\'\'' . $encodedName);
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

// Stream the file in chunks to keep memory usage low for large files
$fp = fopen($filePath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}

while (!feof($fp)) {
    echo fread($fp, 65536);
    flush();
}
fclose($fp);
exit;
