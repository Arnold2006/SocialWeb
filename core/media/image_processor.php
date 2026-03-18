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
 * media/image_processor.php — Image upload, resize, avatar, and cover processing
 */

declare(strict_types=1);

/**
 * Process an uploaded image:
 *  1. Validate type & size
 *  2. Calculate hash & check deduplication
 *  3. Strip EXIF by re-encoding via GD
 *  4. Generate multiple size variants
 *  5. Save DB record
 *
 * @param array $file     $_FILES['field'] entry
 * @param int   $userId
 * @param int   $albumId
 * @return array{ok: bool, error: string, media_id: int}
 */
function process_image_upload(array $file, int $userId, int $albumId = 0): array
{
    // Basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message($file['error']), 'media_id' => 0];
    }

    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'File too large. Maximum: ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB', 'media_id' => 0];
    }

    // Validate MIME via finfo (not user-supplied)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        return ['ok' => false, 'error' => 'Invalid image type.', 'media_id' => 0];
    }

    // Hash for deduplication
    $hash = media_hash($file['tmp_name']);
    $dupe = media_find_duplicate($hash);

    if ($dupe !== null) {
        // Reference existing record instead of re-storing file
        $newId = db_insert(
            'INSERT INTO media (user_id, album_id, type, file_hash, storage_path, large_path, medium_path,
                                thumb_path, size, mime_type, original_name, width, height)
             VALUES (?, ?, "image", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $albumId ?: null,
                $dupe['file_hash'],
                $dupe['storage_path'],
                $dupe['large_path'],
                $dupe['medium_path'],
                $dupe['thumb_path'],
                $dupe['size'],
                $dupe['mime_type'],
                $file['name'] ?? null,
                $dupe['width'],
                $dupe['height'],
            ]
        );
        return ['ok' => true, 'error' => '', 'media_id' => (int) $newId];
    }

    // Load image via GD (strips EXIF automatically)
    $gd = image_create_from_upload($file['tmp_name'], $mimeType);
    if ($gd === false) {
        return ['ok' => false, 'error' => 'Could not process image.', 'media_id' => 0];
    }

    $origW = imagesx($gd);
    $origH = imagesy($gd);

    // Build storage paths
    $baseName = bin2hex(random_bytes(16));

    $paths = [
        'original' => UPLOADS_DIR . '/images/original/' . $baseName . '.jpg',   // JPEG — EXIF-stripped original
        'large'    => UPLOADS_DIR . '/images/large/'    . $baseName . '.webp',   // WebP — bandwidth/storage savings
        'medium'   => UPLOADS_DIR . '/images/medium/'   . $baseName . '.webp',
        'thumb'    => UPLOADS_DIR . '/images/thumbs/'   . $baseName . '.webp',
    ];

    // Save original (re-encoded, EXIF stripped)
    if (!imagejpeg($gd, $paths['original'], 90)) {
        imagedestroy($gd);
        return ['ok' => false, 'error' => 'Could not save image.', 'media_id' => 0];
    }

    // Generate resized variants
    image_resize_and_save($gd, $origW, $origH, $paths['large'],  1600);
    image_resize_and_save($gd, $origW, $origH, $paths['medium'],  800);
    image_resize_and_save($gd, $origW, $origH, $paths['thumb'],   300);

    imagedestroy($gd);

    // Store record
    $size   = filesize($paths['original']);
    $mediaId = db_insert(
        'INSERT INTO media (user_id, album_id, type, file_hash, storage_path, large_path, medium_path,
                            thumb_path, size, mime_type, original_name, width, height)
         VALUES (?, ?, "image", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $userId,
            $albumId ?: null,
            $hash,
            $paths['original'],
            $paths['large'],
            $paths['medium'],
            $paths['thumb'],
            $size,
            'image/jpeg',
            $file['name'] ?? null,
            $origW,
            $origH,
        ]
    );

    return ['ok' => true, 'error' => '', 'media_id' => (int) $mediaId];
}

/**
 * Load GD image from various source types.
 *
 * @return \GdImage|false
 */
function image_create_from_upload(string $path, string $mimeType): \GdImage|false
{
    return match ($mimeType) {
        'image/jpeg' => imagecreatefromjpeg($path),
        'image/png'  => imagecreatefrompng($path),
        'image/gif'  => imagecreatefromgif($path),
        'image/webp' => imagecreatefromwebp($path),
        default      => false,
    };
}

/**
 * Resize $gd to fit within $maxDim and save as WebP.
 *
 * @param \GdImage $gd
 */
function image_resize_and_save(\GdImage $gd, int $origW, int $origH, string $destPath, int $maxDim): bool
{
    if ($origW <= $maxDim && $origH <= $maxDim) {
        // No resize needed, just re-save
        return imagewebp($gd, $destPath, 85);
    }

    $ratio  = min($maxDim / $origW, $maxDim / $origH);
    $newW   = max(1, (int) round($origW * $ratio));
    $newH   = max(1, (int) round($origH * $ratio));

    $resized = imagecreatetruecolor($newW, $newH);

    // Preserve transparency for PNG-sourced content
    imagealphablending($resized, false);
    imagesavealpha($resized, true);

    imagecopyresampled($resized, $gd, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    $result = imagewebp($resized, $destPath, 85);
    imagedestroy($resized);

    return $result;
}

/**
 * Process an uploaded avatar image.
 * Crops to square and generates all avatar size variants.
 *
 * @param array $file      $_FILES entry
 * @param int   $userId
 * @param array $crop      [x, y, w, h] crop coordinates (optional)
 * @return array{ok: bool, error: string, paths: array}
 */
function process_avatar_upload(array $file, int $userId, array $crop = []): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message($file['error']), 'paths' => []];
    }

    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'File too large.', 'paths' => []];
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        return ['ok' => false, 'error' => 'Invalid image type.', 'paths' => []];
    }

    $gd = image_create_from_upload($file['tmp_name'], $mimeType);
    if ($gd === false) {
        return ['ok' => false, 'error' => 'Could not process image.', 'paths' => []];
    }

    $origW = imagesx($gd);
    $origH = imagesy($gd);

    // Apply crop if provided
    if (!empty($crop) && isset($crop['x'], $crop['y'], $crop['w'], $crop['h'])) {
        $cx = max(0, (int) $crop['x']);
        $cy = max(0, (int) $crop['y']);
        $cw = max(1, (int) $crop['w']);
        $ch = max(1, (int) $crop['h']);

        // Clamp to image boundaries
        $cw = min($cw, $origW - $cx);
        $ch = min($ch, $origH - $cy);

        $cropped = imagecreatetruecolor($cw, $ch);
        imagecopy($cropped, $gd, 0, 0, $cx, $cy, $cw, $ch);
        imagedestroy($gd);
        $gd    = $cropped;
        $origW = $cw;
        $origH = $ch;
    }

    // Auto-square crop if not square
    if ($origW !== $origH) {
        $side    = min($origW, $origH);
        $offsetX = (int) (($origW - $side) / 2);
        $offsetY = (int) (($origH - $side) / 2);
        $square  = imagecreatetruecolor($side, $side);
        imagecopy($square, $gd, 0, 0, $offsetX, $offsetY, $side, $side);
        imagedestroy($gd);
        $gd    = $square;
        $origW = $side;
        $origH = $side;
    }

    $baseName = 'avatar_' . $userId . '_' . time();

    $sizes = [
        'large'  => AVATAR_SIZE_LARGE,
        'medium' => AVATAR_SIZE_MEDIUM,
        'small'  => AVATAR_SIZE_SMALL,
    ];

    $paths = [];

    foreach ($sizes as $sizeName => $px) {
        $resized = imagecreatetruecolor($px, $px);
        imagecopyresampled($resized, $gd, 0, 0, 0, 0, $px, $px, $origW, $origH);
        $path = UPLOADS_DIR . '/avatars/' . $sizeName . '/' . $baseName . '.webp';
        imagewebp($resized, $path, 90);
        imagedestroy($resized);
        $paths[$sizeName] = $path;
    }

    imagedestroy($gd);

    // Store avatar path in users table (relative URL path)
    $relPath = '/uploads/avatars/large/' . $baseName . '.webp';

    // Delete old avatar files before updating the record
    $oldUser = db_row('SELECT avatar_path FROM users WHERE id = ?', [$userId]);
    if ($oldUser && !empty($oldUser['avatar_path'])) {
        avatar_delete_files($oldUser['avatar_path']);
    }

    db_exec('UPDATE users SET avatar_path = ? WHERE id = ?', [$relPath, $userId]);

    return ['ok' => true, 'error' => '', 'paths' => $paths];
}

/**
 * Crop an existing media image or video thumbnail to produce a square album cover.
 *
 * Coordinates are in the source image's pixel space. For video media the crop
 * is applied to the pre-generated video thumbnail.
 *
 * @param array $media  Row from the media table (type=image or type=video)
 * @param array $crop   [x, y, w, h] in source-image pixels
 * @return array{ok: bool, error: string, cover_path: string}
 */
function process_cover_crop(array $media, array $crop): array
{
    // For videos, use the generated thumbnail as the cover source image
    if (($media['type'] ?? '') === 'video') {
        $srcPath = $media['thumbnail_path'] ?? '';
    } else {
        $srcPath = $media['storage_path'] ?? '';
    }
    if (empty($srcPath) || !file_exists($srcPath)) {
        return ['ok' => false, 'error' => 'Cover source not found.', 'cover_path' => ''];
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($srcPath);

    $gd = image_create_from_upload($srcPath, $mimeType);
    if ($gd === false) {
        return ['ok' => false, 'error' => 'Could not load source image.', 'cover_path' => ''];
    }

    $origW = imagesx($gd);
    $origH = imagesy($gd);

    $cx = max(0, (int) $crop['x']);
    $cy = max(0, (int) $crop['y']);
    $cw = max(1, (int) $crop['w']);
    $ch = max(1, (int) $crop['h']);

    // Clamp to image boundaries
    $cw = min($cw, $origW - $cx);
    $ch = min($ch, $origH - $cy);

    if ($cw < 1 || $ch < 1) {
        imagedestroy($gd);
        return ['ok' => false, 'error' => 'Invalid crop dimensions.', 'cover_path' => ''];
    }

    // Ensure covers directory exists
    $coverDir = UPLOADS_DIR . '/images/covers';
    if (!is_dir($coverDir)) {
        mkdir($coverDir, 0755, true);
    }

    $baseName  = 'cover_' . bin2hex(random_bytes(8));
    $destPath  = $coverDir . '/' . $baseName . '.webp';

    // Create a 400×400 square cover
    $cover = imagecreatetruecolor(400, 400);
    imagecopyresampled($cover, $gd, 0, 0, $cx, $cy, 400, 400, $cw, $ch);
    $saved = imagewebp($cover, $destPath, 90);
    imagedestroy($cover);
    imagedestroy($gd);

    if (!$saved) {
        return ['ok' => false, 'error' => 'Could not save cover image.', 'cover_path' => ''];
    }

    // Return as a relative URL path (like avatar_path)
    $relPath = '/uploads/images/covers/' . $baseName . '.webp';
    return ['ok' => true, 'error' => '', 'cover_path' => $relPath];
}
