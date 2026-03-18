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
 * media_processor.php — Media processing module loader
 *
 * Shared helpers (upload validation, hashing, deduplication, file deletion,
 * mosaic generation, and album wall posts) live in this file.  Processing
 * pipelines are split by media type:
 *   media/image_processor.php – process_image_upload(), process_avatar_upload(),
 *                                process_cover_crop(), image_create_from_upload(),
 *                                image_resize_and_save()
 *   media/video_processor.php – process_video_upload()
 */

declare(strict_types=1);

// Allowed image MIME types
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
// Allowed video MIME types
const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/ogg'];

// ── Upload helpers ────────────────────────────────────────────────────────────

/**
 * Map a PHP upload error code to a human-readable message.
 *
 * @param int $code  One of the UPLOAD_ERR_* constants
 * @return string
 */
function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        default               => 'Unknown upload error (code ' . $code . ').',
    };
}

// ── Hashing / deduplication ───────────────────────────────────────────────────

/**
 * Calculate SHA256 hash of a file.
 */
function media_hash(string $filePath): string
{
    return hash_file('sha256', $filePath);
}

/**
 * Check the database for an existing media entry with the same hash.
 *
 * @return array|null  Existing media row, or null if not found
 */
function media_find_duplicate(string $hash): ?array
{
    // Deduplication: check across all users (disk-space savings)
    return db_row(
        'SELECT * FROM media WHERE file_hash = ? AND is_deleted = 0 LIMIT 1',
        [$hash]
    );
}

// ── Load media-type-specific processors ──────────────────────────────────────

require_once __DIR__ . '/media/image_processor.php';
require_once __DIR__ . '/media/video_processor.php';

// ── URL helper ────────────────────────────────────────────────────────────────

/**
 * Return the web-accessible URL for a media storage path.
 */
function media_url(string $storagePath, string $size = 'medium'): string
{
    // Convert absolute path to relative URL
    $relative = str_replace(SITE_ROOT, '', $storagePath);
    return SITE_URL . str_replace('\\', '/', $relative);
}

// ── File deletion helpers ─────────────────────────────────────────────────────

/**
 * Delete physical files for a media record only if no other active media
 * record references the same storage path (deduplication-safe).
 *
 * @param array $media  Row from the media table
 */
function media_delete_files(array $media): void
{
    $pathFields = ['storage_path', 'large_path', 'medium_path', 'thumb_path', 'thumbnail_path'];

    foreach ($pathFields as $field) {
        if (empty($media[$field])) {
            continue;
        }
        $path = $media[$field];
        // Only delete if no other non-deleted media record uses this file path
        $refCount = (int) db_val(
            'SELECT COUNT(*) FROM media WHERE (storage_path = ? OR large_path = ? OR medium_path = ? OR thumb_path = ? OR thumbnail_path = ?) AND is_deleted = 0',
            [$path, $path, $path, $path, $path]
        );
        if ($refCount === 0 && file_exists($path)) {
            @unlink($path);
        }
    }
}

/**
 * Delete avatar files for all size variants of a relative avatar path.
 *
 * @param string $relPath  e.g. /uploads/avatars/large/avatar_1_1234.webp
 */
function avatar_delete_files(string $relPath): void
{
    if (empty($relPath)) {
        return;
    }
    $basename = basename($relPath);
    $sizes = ['large', 'medium', 'small'];
    foreach ($sizes as $size) {
        $absPath = SITE_ROOT . '/uploads/avatars/' . $size . '/' . $basename;
        if (file_exists($absPath)) {
            @unlink($absPath);
        }
    }
}

/**
 * Delete a cover image file given its relative URL path.
 *
 * @param string $relPath  e.g. /uploads/images/covers/cover_XXXX.webp
 */
function cover_delete_file(string $relPath): void
{
    if (empty($relPath)) {
        return;
    }
    $absPath = SITE_ROOT . $relPath;
    if (file_exists($absPath)) {
        @unlink($absPath);
    }
}

// ── Album upload wall post ────────────────────────────────────────────────────

/**
 * Generate a mosaic composite image from up to 4 image media items.
 *
 * Creates a 600×600 JPEG with a 2×2 grid; each cell is filled by centre-cropping
 * the corresponding thumbnail.  Empty cells are left as a neutral background.
 *
 * @param array $mediaItems  Rows from the media table (type=image).
 * @return array{ok: bool, error: string, mosaic_path: string}
 */
function generate_album_mosaic(array $mediaItems): array
{
    $mosaicDir = UPLOADS_DIR . '/images/mosaics';
    if (!is_dir($mosaicDir)) {
        mkdir($mosaicDir, 0755, true);
    }

    $items = array_slice($mediaItems, 0, 4);
    if (empty($items)) {
        return ['ok' => false, 'error' => 'No images provided.', 'mosaic_path' => ''];
    }

    // 600×600 canvas with a dark neutral background
    $canvas  = imagecreatetruecolor(600, 600);
    $bgColor = imagecolorallocate($canvas, 40, 40, 40);
    imagefill($canvas, 0, 0, $bgColor);

    // Cell positions [dst_x, dst_y, dst_w, dst_h] in a 2×2 grid
    $positions = [
        [0,   0,   300, 300],
        [300, 0,   300, 300],
        [0,   300, 300, 300],
        [300, 300, 300, 300],
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($items as $i => $item) {
        $thumbPath = $item['thumb_path'] ?? '';
        if (empty($thumbPath) || !file_exists($thumbPath)) {
            continue;
        }

        $mime = $finfo->file($thumbPath);
        $src  = image_create_from_upload($thumbPath, $mime);
        if ($src === false) {
            continue;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);
            continue;
        }

        [$dx, $dy, $dw, $dh] = $positions[$i];

        // Centre-crop: scale so the shorter dimension fills the cell
        $scale = max($dw / max(1, $srcW), $dh / max(1, $srcH));
        $srcCW = (int) round($dw / $scale);
        $srcCH = (int) round($dh / $scale);
        $srcX  = (int) round(($srcW - $srcCW) / 2);
        $srcY  = (int) round(($srcH - $srcCH) / 2);

        imagecopyresampled($canvas, $src, $dx, $dy, $srcX, $srcY, $dw, $dh, $srcCW, $srcCH);
        imagedestroy($src);
    }

    $baseName = 'mosaic_' . bin2hex(random_bytes(8));
    $destPath = $mosaicDir . '/' . $baseName . '.webp';

    $saved = imagewebp($canvas, $destPath, 85);
    imagedestroy($canvas);

    if (!$saved) {
        return ['ok' => false, 'error' => 'Could not save mosaic image.', 'mosaic_path' => ''];
    }

    return ['ok' => true, 'error' => '', 'mosaic_path' => $destPath];
}

/**
 * Create a system wall post announcing an album upload.
 *
 * Generates a mosaic thumbnail from up to 4 of the uploaded images and inserts
 * a post of type 'album_upload' into the posts table.
 *
 * @param int    $userId       ID of the uploading user.
 * @param int    $albumId      ID of the target album.
 * @param string $albumTitle   Human-readable album title.
 * @param int    $uploadCount  Total number of files uploaded (images + videos).
 * @param int[]  $mediaIds     IDs of the newly-inserted media records.
 */
function create_album_upload_post(
    int    $userId,
    int    $albumId,
    string $albumTitle,
    int    $uploadCount,
    array  $mediaIds
): void {
    if (empty($mediaIds)) {
        return;
    }

    // Count image records from the batch to determine content type
    $placeholders = implode(',', array_fill(0, count($mediaIds), '?'));
    $imageCount   = (int) db_val(
        "SELECT COUNT(*) FROM media
         WHERE id IN ($placeholders) AND type = 'image' AND is_deleted = 0",
        $mediaIds
    );

    $videoCount = $uploadCount - $imageCount;

    if ($imageCount > 0 && $videoCount > 0) {
        $content = sprintf(
            '📷 Added %d file%s to album "%s"',
            $uploadCount,
            $uploadCount !== 1 ? 's' : '',
            $albumTitle
        );
    } elseif ($videoCount > 0) {
        $content = sprintf(
            '🎬 Added %d video%s to album "%s"',
            $videoCount,
            $videoCount !== 1 ? 's' : '',
            $albumTitle
        );
    } else {
        $content = sprintf(
            '📷 Added %d photo%s to album "%s"',
            $imageCount,
            $imageCount !== 1 ? 's' : '',
            $albumTitle
        );
    }

    db_insert(
        'INSERT INTO posts (user_id, content, media_id, post_type, album_id)
         VALUES (?, ?, NULL, "album_upload", ?)',
        [$userId, $content, $albumId]
    );

    cache_invalidate_wall();
}
