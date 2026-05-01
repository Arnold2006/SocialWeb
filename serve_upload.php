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
 * serve_upload.php — Authenticated upload file server
 *
 * All requests to /uploads/ are routed here by the root .htaccess
 * (mod_rewrite) so that only logged-in members can access uploaded files.
 *
 * Supports HTTP Range requests so that video seeking works correctly
 * in the browser's native <video> element.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// ── Authentication ────────────────────────────────────────────────────────────
if (!is_logged_in()) {
    http_response_code(403);
    exit;
}

// ── Resolve and validate the requested path ───────────────────────────────────
$file = $_GET['file'] ?? '';

// Reject obviously malformed input early
if ($file === '' || str_contains($file, "\0")) {
    http_response_code(400);
    exit;
}

// Must not be an absolute path or start with a path separator
if ($file[0] === '/' || $file[0] === '\\') {
    http_response_code(400);
    exit;
}

$uploadsReal = realpath(UPLOADS_DIR);
if ($uploadsReal === false) {
    http_response_code(500);
    exit;
}

// realpath() resolves ".." components and symlinks, ensuring the final path
// is truly within the uploads directory.
$filePath = realpath($uploadsReal . DIRECTORY_SEPARATOR . $file);

if (
    $filePath === false ||
    !str_starts_with($filePath, $uploadsReal . DIRECTORY_SEPARATOR) ||
    !is_file($filePath)
) {
    http_response_code(404);
    exit;
}

// ── MIME-type detection and allow-list ────────────────────────────────────────
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($filePath);
if ($mime === false) {
    $mime = 'application/octet-stream';
}

// Restrict to file types the application actually stores in uploads/.
// Anything else (PHP, HTML, JS, …) is refused — defence-in-depth against
// a stored-file exploit even if upload validation were ever bypassed.
$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'video/mp4',
    'video/webm',
    'video/ogg',
    'video/x-matroska',
    // Custom fonts uploaded for the site-name overlay
    'font/woff2',
    'font/woff',
    'font/ttf',
    'font/otf',
    // Legacy MIME types reported by older libmagic / OS versions
    'application/font-woff2',
    'application/font-woff',
    'application/x-font-woff',
    'application/x-font-ttf',
    'application/x-font-opentype',
    'application/vnd.ms-opentype',
];

if (!in_array($mime, $allowedMimeTypes, true)) {
    http_response_code(403);
    exit;
}

// ── Send the file ─────────────────────────────────────────────────────────────
$fileSize = filesize($filePath);

// Common headers
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
// Sanitise the filename to ASCII-safe characters only (files are stored with
// hash-based names so this never loses meaningful information) and embed it
// in the Content-Disposition header without risk of header injection.
$safeBasename = preg_replace('/[^\w.\-]/', '_', basename($filePath)) ?? 'file';
header('Content-Disposition: inline; filename="' . $safeBasename . '"');

// ── HTTP Range support (required for in-browser video seeking) ────────────────
$rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';

if ($rangeHeader === '') {
    // Full file
    header('Content-Length: ' . $fileSize);
    header('Accept-Ranges: bytes');
    readfile($filePath);
    exit;
}

// Parse the Range header (only the first range is honoured)
if (!preg_match('/^bytes=(\d*)-(\d*)$/', $rangeHeader, $m)) {
    http_response_code(416); // Range Not Satisfiable
    header('Content-Range: bytes */' . $fileSize);
    exit;
}

$start = $m[1] !== '' ? (int) $m[1] : 0;
$end   = $m[2] !== '' ? (int) $m[2] : $fileSize - 1;

// Clamp to valid range
if ($start > $end || $end >= $fileSize) {
    http_response_code(416);
    header('Content-Range: bytes */' . $fileSize);
    exit;
}

$length = $end - $start + 1;

http_response_code(206); // Partial Content
header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
header('Content-Length: ' . $length);
header('Accept-Ranges: bytes');

$fp = fopen($filePath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}

fseek($fp, $start);

// stream_copy_to_stream respects the $length limit and avoids buffering the
// entire range in memory.
$out = fopen('php://output', 'wb');
stream_copy_to_stream($fp, $out, $length);
fclose($out);
fclose($fp);
exit;
