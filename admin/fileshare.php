<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Licensed under the MIT License.
 * See the LICENSE file for details.
 */
/**
 * fileshare.php — Admin file share management
 *
 * Allows admins to:
 *  - Create and delete folders
 *  - Move files to a different folder (or to root)
 *  - Delete files
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$pageTitle = 'Admin – File Share';

// ── POST: handle actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // Create a new folder
    if ($action === 'create_folder') {
        $name = sanitise_string($_POST['folder_name'] ?? '', 100);
        if ($name === '') {
            flash_set('error', 'Folder name cannot be empty.');
        } elseif (db_val('SELECT COUNT(*) FROM file_share_folders WHERE name = ?', [$name]) > 0) {
            flash_set('error', 'A folder with that name already exists.');
        } else {
            db_insert(
                'INSERT INTO file_share_folders (name, created_by) VALUES (?, ?)',
                [$name, (int) current_user()['id']]
            );
            flash_set('success', 'Folder "' . $name . '" created.');
        }
        redirect(SITE_URL . '/admin/fileshare.php');
    }

    // Delete a folder (and optionally move its files to root)
    if ($action === 'delete_folder') {
        $folderId = (int) ($_POST['folder_id'] ?? 0);
        if ($folderId > 0) {
            $folder = db_row('SELECT id, name FROM file_share_folders WHERE id = ?', [$folderId]);
            if ($folder) {
                // Move all files in this folder to root
                db_exec(
                    'UPDATE file_share_files SET folder_id = NULL WHERE folder_id = ?',
                    [$folderId]
                );
                db_exec('DELETE FROM file_share_folders WHERE id = ?', [$folderId]);
                flash_set('success', 'Folder "' . $folder['name'] . '" deleted; its files have been moved to the root.');
            }
        }
        redirect(SITE_URL . '/admin/fileshare.php');
    }

    // Move a file to a different folder (or root)
    if ($action === 'move_file') {
        $fileId   = (int) ($_POST['file_id'] ?? 0);
        $newFolder = ($_POST['new_folder_id'] ?? '') !== ''
            ? (int) $_POST['new_folder_id']
            : null;

        if ($fileId > 0) {
            $file = db_row('SELECT id FROM file_share_files WHERE id = ?', [$fileId]);
            if ($file) {
                // Validate destination folder if provided
                if ($newFolder !== null) {
                    $dest = db_row('SELECT id FROM file_share_folders WHERE id = ?', [$newFolder]);
                    if (!$dest) {
                        $newFolder = null;
                    }
                }
                db_exec(
                    'UPDATE file_share_files SET folder_id = ? WHERE id = ?',
                    [$newFolder, $fileId]
                );
                flash_set('success', 'File moved.');
            }
        }
        redirect(SITE_URL . '/admin/fileshare.php');
    }

    // Delete a file
    if ($action === 'delete_file') {
        $fileId = (int) ($_POST['file_id'] ?? 0);
        if ($fileId > 0) {
            $file = db_row('SELECT id, filename, original_name FROM file_share_files WHERE id = ?', [$fileId]);
            if ($file) {
                // Delete the physical file
                $filesDir  = UPLOADS_DIR . DIRECTORY_SEPARATOR . 'files';
                $filesReal = realpath($filesDir);
                if ($filesReal !== false) {
                    $filePath = realpath($filesReal . DIRECTORY_SEPARATOR . $file['filename']);
                    if (
                        $filePath !== false &&
                        str_starts_with($filePath, $filesReal . DIRECTORY_SEPARATOR) &&
                        is_file($filePath)
                    ) {
                        @unlink($filePath);
                    }
                }
                db_exec('DELETE FROM file_share_files WHERE id = ?', [$fileId]);
                flash_set('success', 'File "' . $file['original_name'] . '" deleted.');
            }
        }
        redirect(SITE_URL . '/admin/fileshare.php');
    }

    redirect(SITE_URL . '/admin/fileshare.php');
}

// ── GET: fetch data ───────────────────────────────────────────────────────────
try {
    $folders = db_query(
        'SELECT f.id, f.name, f.created_at,
                u.username AS created_by_username,
                COUNT(ff.id) AS file_count
         FROM file_share_folders f
         JOIN users u ON u.id = f.created_by
         LEFT JOIN file_share_files ff ON ff.folder_id = f.id
         GROUP BY f.id
         ORDER BY f.name ASC'
    );
} catch (\Throwable $e) {
    $folders = [];
}

try {
    $files = db_query(
        'SELECT ff.id, ff.folder_id, ff.original_name, ff.description, ff.size, ff.created_at,
                u.username AS uploader,
                fo.name AS folder_name
         FROM file_share_files ff
         JOIN users u ON u.id = ff.user_id
         LEFT JOIN file_share_folders fo ON fo.id = ff.folder_id
         ORDER BY ff.created_at DESC'
    );
} catch (\Throwable $e) {
    $files = [];
}

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <nav class="admin-nav">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?= SITE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php">Users</a></li>
            <li><a href="<?= SITE_URL ?>/admin/invites.php">Invites</a></li>
            <li><a href="<?= SITE_URL ?>/admin/moderation.php">Moderation</a></li>
            <li><a href="<?= SITE_URL ?>/admin/media.php">Media</a></li>
            <li><a href="<?= SITE_URL ?>/admin/fileshare.php" class="active">File Share</a></li>
            <li><a href="<?= SITE_URL ?>/admin/plugins.php">Plugins</a></li>
            <li><a href="<?= SITE_URL ?>/admin/settings.php">Site Settings</a></li>
            <li><a href="<?= SITE_URL ?>/admin/orphans.php">Orphan Cleanup</a></li>
            <li><a href="<?= SITE_URL ?>/upgrade.php">Database Upgrade</a></li>
            <li><a href="<?= SITE_URL ?>/admin/forum/index.php">Forum Administration</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1>File Share Management</h1>

        <!-- ── Create folder ──────────────────────────────────────── -->
        <section class="admin-section">
            <h2>Create Folder</h2>
            <form method="POST" class="fileshare-admin-create-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_folder">
                <div class="fileshare-admin-create-row">
                    <input type="text" name="folder_name" placeholder="Folder name"
                           maxlength="100" required class="fileshare-admin-folder-input">
                    <button type="submit" class="btn btn-primary btn-sm">Create Folder</button>
                </div>
            </form>
        </section>

        <!-- ── Folder list ────────────────────────────────────────── -->
        <section class="admin-section">
            <h2>Folders</h2>
            <?php if (empty($folders)): ?>
                <p class="text-muted">No folders created yet.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Files</th>
                            <th>Created by</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($folders as $folder): ?>
                        <tr>
                            <td><?= e($folder['name']) ?></td>
                            <td><?= (int)$folder['file_count'] ?></td>
                            <td><?= e($folder['created_by_username']) ?></td>
                            <td><?= e(date('Y-m-d', strtotime($folder['created_at']))) ?></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_folder">
                                    <input type="hidden" name="folder_id" value="<?= (int)$folder['id'] ?>">
                                    <button class="btn btn-xs btn-danger"
                                            data-confirm="Delete folder &quot;<?= e($folder['name']) ?>&quot;? Its files will be moved to the root.">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- ── File list ──────────────────────────────────────────── -->
        <section class="admin-section">
            <h2>All Files <span class="fileshare-admin-count">(<?= count($files) ?>)</span></h2>
            <?php if (empty($files)): ?>
                <p class="text-muted">No files uploaded yet.</p>
            <?php else: ?>
                <div class="fileshare-admin-table-wrap">
                    <table class="admin-table fileshare-admin-files-table">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Description</th>
                                <th>Folder</th>
                                <th>Size</th>
                                <th>Uploaded by</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                            <tr>
                                <td class="fileshare-admin-filename"><?= e($file['original_name']) ?></td>
                                <td class="fileshare-admin-description">
                                    <?= !empty($file['description']) ? e($file['description']) : '<em>—</em>' ?>
                                </td>
                                <td><?= $file['folder_name'] !== null ? e($file['folder_name']) : '<em>Root</em>' ?></td>
                                <td><?= e(format_file_size((int)$file['size'])) ?></td>
                                <td><?= e($file['uploader']) ?></td>
                                <td><?= e(date('Y-m-d', strtotime($file['created_at']))) ?></td>
                                <td class="fileshare-admin-actions">
                                    <!-- Move form -->
                                    <form method="POST" class="fileshare-admin-move-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="move_file">
                                        <input type="hidden" name="file_id" value="<?= (int)$file['id'] ?>">
                                        <select name="new_folder_id" class="fileshare-admin-move-select">
                                            <option value="">— Root —</option>
                                            <?php foreach ($folders as $fo): ?>
                                            <option value="<?= (int)$fo['id'] ?>"
                                                <?= (int)$file['folder_id'] === (int)$fo['id'] ? 'selected' : '' ?>>
                                                <?= e($fo['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-xs btn-secondary">Move</button>
                                    </form>
                                    <!-- Delete form -->
                                    <form method="POST" style="display:inline-block">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_file">
                                        <input type="hidden" name="file_id" value="<?= (int)$file['id'] ?>">
                                        <button class="btn btn-xs btn-danger"
                                                data-confirm="Delete &quot;<?= e($file['original_name']) ?>&quot;?">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
