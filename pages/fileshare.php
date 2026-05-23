<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * fileshare.php — Community file share
 *
 * Members can upload files for the community to download.
 * Allowed types: zip, rar, 7z, safetensors, json, png, pth
 * Maximum file size: 500 MB
 *
 * Files can optionally be placed in a folder (folders are managed by admins).
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$currentUser = current_user();
$pageTitle   = 'File Share';

/** Maximum upload size: 500 MB in bytes */
const MAX_FILESHARE_BYTES = 500 * 1024 * 1024;

/** Allowed extensions and their accepted MIME types */
const FILESHARE_ALLOWED = [
    'zip'          => ['application/zip', 'application/x-zip', 'application/x-zip-compressed',
                       'application/octet-stream'],
    'rar'          => ['application/x-rar-compressed', 'application/vnd.rar',
                       'application/octet-stream'],
    '7z'           => ['application/x-7z-compressed', 'application/octet-stream'],
    'safetensors'  => ['application/octet-stream', 'binary/octet-stream'],
    'json'         => ['application/json', 'text/plain', 'text/json', 'application/octet-stream'],
    'png'          => ['image/png', 'application/octet-stream'],
    'pth'          => ['application/octet-stream', 'binary/octet-stream'],
];

/**
 * Format a byte count as a human-readable string.
 */
function fileshare_format_size(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Ensure the uploads/files directory exists.
 */
function ensure_files_dir(): bool
{
    $dir = UPLOADS_DIR . DIRECTORY_SEPARATOR . 'files';
    if (!is_dir($dir)) {
        return mkdir($dir, 0755, true);
    }
    return true;
}

// ── Handle POST: upload a file ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guard against silent POST truncation when body exceeds post_max_size
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
        flash_set('error', 'Upload too large – the file exceeds the server\'s maximum upload limit.');
        redirect(SITE_URL . '/pages/fileshare.php');
    }

    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $folderId = isset($_POST['folder_id']) && $_POST['folder_id'] !== ''
            ? (int) $_POST['folder_id']
            : null;

        if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            flash_set('error', 'No file selected.');
            redirect(SITE_URL . '/pages/fileshare.php');
        }

        $upload = $_FILES['file'];

        // PHP upload error check
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $phpErrors = [
                UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server\'s upload size limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form\'s size limit.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory available.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            ];
            flash_set('error', $phpErrors[$upload['error']] ?? 'Upload error ' . $upload['error'] . '.');
            redirect(SITE_URL . '/pages/fileshare.php');
        }

        // File size check
        if ($upload['size'] > MAX_FILESHARE_BYTES) {
            flash_set('error', 'File is too large. Maximum size is 500 MB.');
            redirect(SITE_URL . '/pages/fileshare.php');
        }

        // Extension check
        $originalName = $upload['name'];
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, FILESHARE_ALLOWED)) {
            flash_set('error', 'File type not allowed. Allowed types: ' . implode(', ', array_keys(FILESHARE_ALLOWED)) . '.');
            redirect(SITE_URL . '/pages/fileshare.php');
        }

        // MIME type check via finfo (defence-in-depth)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($upload['tmp_name']);
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }
        if (!in_array($mimeType, FILESHARE_ALLOWED[$ext], true)) {
            flash_set('error', 'File content does not match the expected type for .' . $ext . ' files.');
            redirect(SITE_URL . '/pages/fileshare.php');
        }

        // Validate folder_id if provided
        if ($folderId !== null) {
            $folder = db_row('SELECT id FROM file_share_folders WHERE id = ?', [$folderId]);
            if (!$folder) {
                $folderId = null;
            }
        }

        // Ensure storage directory exists
        if (!ensure_files_dir()) {
            flash_set('error', 'Storage directory could not be created. Please contact an administrator.');
            redirect(SITE_URL . '/pages/fileshare.php');
        }

        // Generate a random stored filename preserving the extension
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath   = UPLOADS_DIR . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($upload['tmp_name'], $destPath)) {
            flash_set('error', 'Failed to save the uploaded file. Please try again.');
            redirect(SITE_URL . '/pages/fileshare.php');
        }

        // Sanitise the original filename for display
        $safeOriginalName = sanitise_string($originalName, 255);
        if ($safeOriginalName === '') {
            $safeOriginalName = 'file.' . $ext;
        }

        db_insert(
            'INSERT INTO file_share_files (folder_id, user_id, filename, original_name, size)
             VALUES (?, ?, ?, ?, ?)',
            [$folderId, (int) $currentUser['id'], $storedName, $safeOriginalName, (int) $upload['size']]
        );

        flash_set('success', 'File uploaded successfully.');
        $redirectUrl = SITE_URL . '/pages/fileshare.php';
        if ($folderId !== null) {
            $redirectUrl .= '?folder=' . $folderId;
        }
        redirect($redirectUrl);
    }
}

// ── GET: fetch folders and files ──────────────────────────────────────────────
$folders = [];
try {
    $folders = db_query(
        'SELECT f.id, f.name,
                COUNT(ff.id) AS file_count
         FROM file_share_folders f
         LEFT JOIN file_share_files ff ON ff.folder_id = f.id
         GROUP BY f.id
         ORDER BY f.name ASC'
    );
} catch (\Throwable $e) {
    // Table may not exist before migration is applied
    $folders = [];
}

$currentFolderId = isset($_GET['folder']) && $_GET['folder'] !== ''
    ? (int) $_GET['folder']
    : null;

$currentFolder = null;
if ($currentFolderId !== null) {
    $currentFolder = db_row('SELECT id, name FROM file_share_folders WHERE id = ?', [$currentFolderId]);
    if (!$currentFolder) {
        $currentFolderId = null;
    }
}

// Pagination
$perPage = 30;
$page    = max(1, isset($_GET['page']) ? (int) $_GET['page'] : 1);
$offset  = ($page - 1) * $perPage;

try {
    if ($currentFolderId !== null) {
        $total = (int) db_val(
            'SELECT COUNT(*) FROM file_share_files WHERE folder_id = ?',
            [$currentFolderId]
        );
        $files = db_query(
            'SELECT f.id, f.folder_id, f.original_name, f.size, f.created_at,
                    u.id AS uploader_id, u.username AS uploader
             FROM file_share_files f
             JOIN users u ON u.id = f.user_id
             WHERE f.folder_id = ?
             ORDER BY f.created_at DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            [$currentFolderId]
        );
    } else {
        // Show all files (no folder filter)
        $total = (int) db_val('SELECT COUNT(*) FROM file_share_files');
        $files = db_query(
            'SELECT f.id, f.folder_id, f.original_name, f.size, f.created_at,
                    u.id AS uploader_id, u.username AS uploader,
                    fo.name AS folder_name
             FROM file_share_files f
             JOIN users u ON u.id = f.user_id
             LEFT JOIN file_share_folders fo ON fo.id = f.folder_id
             ORDER BY f.created_at DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
    }
} catch (\Throwable $e) {
    $total = 0;
    $files = [];
}

$totalPages = max(1, (int) ceil($total / $perPage));

// Base URL for pagination links
$paginationBase = SITE_URL . '/pages/fileshare.php';
if ($currentFolderId !== null) {
    $paginationBase .= '?folder=' . $currentFolderId . '&';
} else {
    $paginationBase .= '?';
}

include SITE_ROOT . '/includes/header.php';
?>

<div class="fileshare-layout">

    <!-- ── Sidebar: folders ──────────────────────────────────────── -->
    <aside class="fileshare-sidebar">
        <h3 class="fileshare-sidebar-title">Folders</h3>
        <ul class="fileshare-folder-list">
            <li>
                <a href="<?= SITE_URL ?>/pages/fileshare.php"
                   class="fileshare-folder-link<?= $currentFolderId === null ? ' active' : '' ?>">
                    📁 All Files
                    <span class="fileshare-folder-count"><?= $currentFolderId === null ? $total : '' ?></span>
                </a>
            </li>
            <?php foreach ($folders as $folder): ?>
            <li>
                <a href="<?= SITE_URL ?>/pages/fileshare.php?folder=<?= (int)$folder['id'] ?>"
                   class="fileshare-folder-link<?= $currentFolderId === (int)$folder['id'] ? ' active' : '' ?>">
                    📂 <?= e($folder['name']) ?>
                    <span class="fileshare-folder-count"><?= (int)$folder['file_count'] ?></span>
                </a>
            </li>
            <?php endforeach; ?>
            <?php if (empty($folders)): ?>
            <li class="fileshare-no-folders">No folders yet</li>
            <?php endif; ?>
        </ul>
    </aside>

    <!-- ── Main content ──────────────────────────────────────────── -->
    <main class="fileshare-main">
        <div class="fileshare-header">
            <h1>
                <?php if ($currentFolder): ?>
                    📂 <?= e($currentFolder['name']) ?>
                <?php else: ?>
                    File Share
                <?php endif; ?>
            </h1>
        </div>

        <!-- ── Upload form ──────────────────────────────────────── -->
        <section class="fileshare-upload-section">
            <h2>Upload a File</h2>
            <form method="POST" enctype="multipart/form-data" class="fileshare-upload-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <div class="fileshare-upload-row">
                    <div class="fileshare-upload-field">
                        <label for="fileshare-file">File
                            <span class="fileshare-hint">(zip, rar, 7z, safetensors, json, png, pth — max 500 MB)</span>
                        </label>
                        <input type="file" id="fileshare-file" name="file"
                               accept=".zip,.rar,.7z,.safetensors,.json,.png,.pth"
                               required class="fileshare-file-input">
                    </div>
                    <?php if (!empty($folders)): ?>
                    <div class="fileshare-upload-field">
                        <label for="fileshare-folder">Folder <span class="fileshare-hint">(optional)</span></label>
                        <select id="fileshare-folder" name="folder_id" class="fileshare-select">
                            <option value="">— None (root) —</option>
                            <?php foreach ($folders as $f): ?>
                            <option value="<?= (int)$f['id'] ?>"
                                <?= $currentFolderId === (int)$f['id'] ? 'selected' : '' ?>>
                                <?= e($f['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="fileshare-upload-field fileshare-upload-submit">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </div>
            </form>
        </section>

        <!-- ── File list ─────────────────────────────────────────── -->
        <section class="fileshare-files-section">
            <h2>
                <?php if ($currentFolder): ?>
                    Files in <?= e($currentFolder['name']) ?>
                <?php else: ?>
                    All Files
                <?php endif; ?>
                <span class="fileshare-total">(<?= $total ?>)</span>
            </h2>

            <?php if (empty($files)): ?>
                <p class="fileshare-empty">No files have been uploaded yet.</p>
            <?php else: ?>
                <div class="fileshare-table-wrap">
                    <table class="fileshare-table">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <?php if ($currentFolderId === null): ?><th>Folder</th><?php endif; ?>
                                <th>Size</th>
                                <th>Uploaded by</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                            <?php
                                $ext     = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                                $extIcons = [
                                    'zip' => '🗜️', 'rar' => '🗜️', '7z' => '🗜️',
                                    'safetensors' => '🧠', 'pth' => '🧠',
                                    'json' => '📄', 'png' => '🖼️',
                                ];
                                $icon = $extIcons[$ext] ?? '📦';
                            ?>
                            <tr>
                                <td class="fileshare-filename">
                                    <?= $icon ?> <?= e($file['original_name']) ?>
                                </td>
                                <?php if ($currentFolderId === null): ?>
                                <td>
                                    <?php if (!empty($file['folder_name'])): ?>
                                        <a href="<?= SITE_URL ?>/pages/fileshare.php?folder=<?= (int)$file['folder_id'] ?>">
                                            <?= e($file['folder_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="fileshare-no-folder">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td class="fileshare-size"><?= e(fileshare_format_size((int)$file['size'])) ?></td>
                                <td>
                                    <a href="<?= SITE_URL ?>/pages/profile.php?id=<?= (int)$file['uploader_id'] ?>">
                                        <?= e($file['uploader']) ?>
                                    </a>
                                </td>
                                <td class="fileshare-date">
                                    <?= e(date('Y-m-d', strtotime($file['created_at']))) ?>
                                </td>
                                <td>
                                    <a href="<?= SITE_URL ?>/serve_fileshare.php?id=<?= (int)$file['id'] ?>"
                                       class="btn btn-sm btn-secondary fileshare-download-btn">⬇ Download</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="page-current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= e($paginationBase . 'page=' . $p) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
