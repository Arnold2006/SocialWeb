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

    // Ensure upload directories exist before writing
    $videoDirs = [
        UPLOADS_DIR . '/videos/original',
        UPLOADS_DIR . '/videos/processed',
        UPLOADS_DIR . '/videos/thumbnails',
    ];
    foreach ($videoDirs as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'Could not create upload directory.', 'media_id' => 0];
        }
    }

    // Derive the correct file extension from the validated MIME type
    $ext = match ($mimeType) {
        'video/webm' => 'webm',
        'video/ogg'  => 'ogv',
        default      => 'mp4',
    };

    $baseName  = bin2hex(random_bytes(16));
    $origPath  = UPLOADS_DIR . '/videos/original/' . $baseName . '.' . $ext;
    $thumbPath = UPLOADS_DIR . '/videos/thumbnails/' . $baseName . '.jpg';

    if (!move_uploaded_file($file['tmp_name'], $origPath)) {
        return ['ok' => false, 'error' => 'Could not save video.', 'media_id' => 0];
    }

    // Insert the DB record immediately after saving the file so the video
    // always appears in the album even if FFmpeg processing below fails or
    // times out (leaving the file in original/ without a DB record otherwise).
    $mediaId = db_insert(
        'INSERT INTO media (user_id, album_id, type, file_hash, storage_path, thumbnail_path,
                            size, mime_type, original_name, duration)
         VALUES (?, ?, "video", ?, ?, ?, ?, ?, ?, ?)',
        [
            $userId, $albumId ?: null,
            $hash, $origPath, null,
            $file['size'], $mimeType, $file['name'] ?? null, null,
        ]
    );

    // Strip metadata and validate using ffmpeg (optional — if available)
    $duration  = null;
    $finalPath = $origPath;
    $ffprobe   = defined('FFPROBE_BIN') ? FFPROBE_BIN : '/usr/bin/ffprobe';
    $ffmpeg    = defined('FFMPEG_BIN')  ? FFMPEG_BIN  : '/usr/bin/ffmpeg';
    if (is_executable($ffprobe) && is_executable($ffmpeg)) {
        $cmd      = escapeshellarg($ffprobe) . ' -v error -show_entries format=duration -of csv=p=0 ' . escapeshellarg($origPath);
        $output   = shell_exec($cmd);
        $duration = $output ? (int) round((float) trim($output)) : null;

        if ($duration !== null && $duration > MAX_VIDEO_DURATION) {
            @unlink($origPath);
            db_exec('UPDATE media SET is_deleted = 1 WHERE id = ?', [(int) $mediaId]);
            return ['ok' => false, 'error' => 'Video exceeds maximum duration of ' . MAX_VIDEO_DURATION . ' seconds.', 'media_id' => 0];
        }

        // Generate thumbnail at 1s
        shell_exec(
            escapeshellarg($ffmpeg) . ' -y -ss 00:00:01 -i ' . escapeshellarg($origPath) .
            ' -vframes 1 -vf ' . escapeshellarg('scale=300:-1') . ' ' . escapeshellarg($thumbPath) . ' 2>/dev/null'
        );

        // Strip metadata: re-encode without metadata stream
        $processedPath = UPLOADS_DIR . '/videos/processed/' . $baseName . '.' . $ext;
        shell_exec(
            escapeshellarg($ffmpeg) . ' -y -i ' . escapeshellarg($origPath) .
            ' -map_metadata -1 -c:v copy -c:a copy ' . escapeshellarg($processedPath) . ' 2>/dev/null'
        );
        if (file_exists($processedPath)) {
            @unlink($origPath);
            $finalPath = $processedPath;
        }
    }

    // Update the record with the final storage path, thumbnail and duration
    // (these may differ from the initial insert if FFmpeg ran successfully).
    $thumbExists  = file_exists($thumbPath);
    $finalSize    = filesize($finalPath);
    db_exec(
        'UPDATE media SET storage_path = ?, thumbnail_path = ?, size = ?, duration = ? WHERE id = ?',
        [
            $finalPath,
            $thumbExists ? $thumbPath : null,
            $finalSize !== false ? $finalSize : $file['size'],
            $duration,
            (int) $mediaId,
        ]
    );

    return ['ok' => true, 'error' => '', 'media_id' => (int) $mediaId];
}
