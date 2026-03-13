<?php
/**
 * media_processor.php — Image & video processing pipeline
 *
 * Handles:
 *  - EXIF/GPS metadata stripping
 *  - Image re-encoding via GD
 *  - Generating multiple sizes (avatar, photo)
 *  - SHA256 deduplication
 *  - Video thumbnail generation (requires ffmpeg)
 */

declare(strict_types=1);

// Allowed image MIME types
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
// Allowed video MIME types
const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/ogg'];

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
function media_find_duplicate(string $hash, int $userId): ?array
{
    // Deduplication: check across all users (disk-space savings)
    return db_row(
        'SELECT * FROM media WHERE file_hash = ? AND is_deleted = 0 LIMIT 1',
        [$hash]
    );
}

// ── Image processing ──────────────────────────────────────────────────────────

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
        return ['ok' => false, 'error' => 'Upload error code: ' . $file['error'], 'media_id' => 0];
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
    $dupe = media_find_duplicate($hash, $userId);

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
    $baseName  = bin2hex(random_bytes(16));
    $extension = 'jpg'; // always re-encode as JPEG to strip metadata

    $paths = [
        'original' => UPLOADS_DIR . '/images/original/' . $baseName . '.' . $extension,
        'large'    => UPLOADS_DIR . '/images/large/'    . $baseName . '.' . $extension,
        'medium'   => UPLOADS_DIR . '/images/medium/'   . $baseName . '.' . $extension,
        'thumb'    => UPLOADS_DIR . '/images/thumbs/'   . $baseName . '.' . $extension,
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
 * Resize $gd to fit within $maxDim and save as JPEG.
 *
 * @param \GdImage $gd
 */
function image_resize_and_save(\GdImage $gd, int $origW, int $origH, string $destPath, int $maxDim): bool
{
    if ($origW <= $maxDim && $origH <= $maxDim) {
        // No resize needed, just re-save
        return imagejpeg($gd, $destPath, 85);
    }

    $ratio  = min($maxDim / $origW, $maxDim / $origH);
    $newW   = max(1, (int) round($origW * $ratio));
    $newH   = max(1, (int) round($origH * $ratio));

    $resized = imagecreatetruecolor($newW, $newH);

    // Preserve transparency for PNG-sourced content
    imagealphablending($resized, false);
    imagesavealpha($resized, true);

    imagecopyresampled($resized, $gd, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    $result = imagejpeg($resized, $destPath, 85);
    imagedestroy($resized);

    return $result;
}

// ── Avatar processing ─────────────────────────────────────────────────────────

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
        return ['ok' => false, 'error' => 'Upload error.', 'paths' => []];
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
        $path = UPLOADS_DIR . '/avatars/' . $sizeName . '/' . $baseName . '.jpg';
        imagejpeg($resized, $path, 90);
        imagedestroy($resized);
        $paths[$sizeName] = $path;
    }

    imagedestroy($gd);

    // Store avatar path in users table (relative URL path)
    $relPath = '/uploads/avatars/large/' . $baseName . '.jpg';

    // Delete old avatar files before updating the record
    $oldUser = db_row('SELECT avatar_path FROM users WHERE id = ?', [$userId]);
    if ($oldUser && !empty($oldUser['avatar_path'])) {
        avatar_delete_files($oldUser['avatar_path']);
    }

    db_exec('UPDATE users SET avatar_path = ? WHERE id = ?', [$relPath, $userId]);

    return ['ok' => true, 'error' => '', 'paths' => $paths];
}

// ── Cover image processing ────────────────────────────────────────────────────

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
    $destPath  = $coverDir . '/' . $baseName . '.jpg';

    // Create a 400×400 square cover
    $cover = imagecreatetruecolor(400, 400);
    imagecopyresampled($cover, $gd, 0, 0, $cx, $cy, 400, 400, $cw, $ch);
    $saved = imagejpeg($cover, $destPath, 90);
    imagedestroy($cover);
    imagedestroy($gd);

    if (!$saved) {
        return ['ok' => false, 'error' => 'Could not save cover image.', 'cover_path' => ''];
    }

    // Return as a relative URL path (like avatar_path)
    $relPath = '/uploads/images/covers/' . $baseName . '.jpg';
    return ['ok' => true, 'error' => '', 'cover_path' => $relPath];
}

// ── Video processing ──────────────────────────────────────────────────────────

/**
 * Process an uploaded video using ffprobe/ffmpeg (if available).
 *
 * @return array{ok: bool, error: string, media_id: int}
 */
function process_video_upload(array $file, int $userId, int $albumId = 0): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload error.', 'media_id' => 0];
    }

    if ($file['size'] > MAX_VIDEO_BYTES) {
        return ['ok' => false, 'error' => 'Video file too large.', 'media_id' => 0];
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_VIDEO_TYPES, true)) {
        return ['ok' => false, 'error' => 'Invalid video type.', 'media_id' => 0];
    }

    $hash = media_hash($file['tmp_name']);
    $dupe = media_find_duplicate($hash, $userId);

    if ($dupe !== null) {
        $newId = db_insert(
            'INSERT INTO media (user_id, album_id, type, file_hash, storage_path, thumbnail_path,
                                size, mime_type, original_name, duration)
             VALUES (?, ?, "video", ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId, $albumId ?: null,
                $dupe['file_hash'], $dupe['storage_path'], $dupe['thumbnail_path'],
                $dupe['size'], $dupe['mime_type'], $file['name'] ?? null, $dupe['duration'],
            ]
        );
        return ['ok' => true, 'error' => '', 'media_id' => (int) $newId];
    }

    $baseName  = bin2hex(random_bytes(16));
    $origPath  = UPLOADS_DIR . '/videos/original/' . $baseName . '.mp4';
    $thumbPath = UPLOADS_DIR . '/videos/thumbnails/' . $baseName . '.jpg';

    if (!move_uploaded_file($file['tmp_name'], $origPath)) {
        return ['ok' => false, 'error' => 'Could not save video.', 'media_id' => 0];
    }

    // Strip metadata and validate using ffmpeg (optional — if available)
    $duration = null;
    if (is_executable('/usr/bin/ffprobe')) {
        $cmd      = '/usr/bin/ffprobe -v error -show_entries format=duration -of csv=p=0 ' . escapeshellarg($origPath);
        $output   = shell_exec($cmd);
        $duration = $output ? (int) round((float) trim($output)) : null;

        if ($duration !== null && $duration > MAX_VIDEO_DURATION) {
            @unlink($origPath);
            return ['ok' => false, 'error' => 'Video exceeds maximum duration of ' . MAX_VIDEO_DURATION . ' seconds.', 'media_id' => 0];
        }

        // Generate thumbnail at 1s
        shell_exec(
            '/usr/bin/ffmpeg -y -i ' . escapeshellarg($origPath) .
            ' -ss 00:00:01 -vframes 1 -vf "scale=300:-1" ' . escapeshellarg($thumbPath) . ' 2>/dev/null'
        );

        // Strip metadata: re-encode without metadata stream
        $processedPath = UPLOADS_DIR . '/videos/processed/' . $baseName . '.mp4';
        shell_exec(
            '/usr/bin/ffmpeg -y -i ' . escapeshellarg($origPath) .
            ' -map_metadata -1 -c:v copy -c:a copy ' . escapeshellarg($processedPath) . ' 2>/dev/null'
        );
        if (file_exists($processedPath)) {
            @unlink($origPath);
            $origPath = $processedPath;
        }
    }

    $mediaId = db_insert(
        'INSERT INTO media (user_id, album_id, type, file_hash, storage_path, thumbnail_path,
                            size, mime_type, original_name, duration)
         VALUES (?, ?, "video", ?, ?, ?, ?, ?, ?, ?)',
        [
            $userId, $albumId ?: null,
            $hash, $origPath, file_exists($thumbPath) ? $thumbPath : null,
            filesize($origPath), 'video/mp4', $file['name'] ?? null, $duration,
        ]
    );

    return ['ok' => true, 'error' => '', 'media_id' => (int) $mediaId];
}

/**
 * Return the web-accessible URL for a media storage path.
 */
function media_url(string $storagePath, string $size = 'medium'): string
{
    // Convert absolute path to relative URL
    $relative = str_replace(SITE_ROOT, '', $storagePath);
    return SITE_URL . str_replace('\\', '/', $relative);
}

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
 * @param string $relPath  e.g. /uploads/avatars/large/avatar_1_1234.jpg
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
 * @param string $relPath  e.g. /uploads/images/covers/cover_XXXX.jpg
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
    $destPath = $mosaicDir . '/' . $baseName . '.jpg';

    $saved = imagejpeg($canvas, $destPath, 85);
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

    // Fetch up to 4 image records from the batch for mosaic generation
    $placeholders = implode(',', array_fill(0, count($mediaIds), '?'));
    $imageMedia   = db_query(
        "SELECT * FROM media
         WHERE id IN ($placeholders) AND type = 'image' AND is_deleted = 0
         LIMIT 4",
        $mediaIds
    );

    $mosaicMediaId = null;
    if (!empty($imageMedia)) {
        $mosaicResult = generate_album_mosaic($imageMedia);
        if ($mosaicResult['ok']) {
            $mosaicPath    = $mosaicResult['mosaic_path'];
            $mosaicMediaId = (int) db_insert(
                'INSERT INTO media
                    (user_id, album_id, type, file_hash, storage_path,
                     large_path, medium_path, thumb_path, size, mime_type)
                 VALUES (?, ?, "image", ?, ?, ?, ?, ?, ?, "image/jpeg")',
                [
                    $userId,
                    $albumId,
                    hash_file('sha256', $mosaicPath),
                    $mosaicPath,
                    $mosaicPath,
                    $mosaicPath,
                    $mosaicPath,
                    filesize($mosaicPath),
                ]
            );
        }
    }

    $imageCount = count($imageMedia);
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
         VALUES (?, ?, ?, "album_upload", ?)',
        [$userId, $content, $mosaicMediaId, $albumId]
    );

    cache_invalidate_wall();
}
