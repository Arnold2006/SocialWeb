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
 * media/video_processor.php — Video upload and processing
 */

declare(strict_types=1);

/**
 * Process an uploaded video using ffprobe/ffmpeg (if available).
 *
 * @return array{ok: bool, error: string, media_id: int}
 */
function process_video_upload(array $file, int $userId, int $albumId = 0): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message($file['error']), 'media_id' => 0];
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
    $dupe = media_find_duplicate($hash);

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
