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
 * admin/forum/forums.php — Manage forums inside categories
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_admin();

$pageTitle = 'Manage Forums — Forum Admin';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        if ($categoryId > 0 && $title !== '') {
            db_insert(
                'INSERT INTO forum_forums (category_id, title, description, sort_order) VALUES (?, ?, ?, ?)',
                [$categoryId, $title, $description ?: null, $sortOrder]
            );
            flash_set('success', 'Forum created.');
        } else {
            flash_set('error', 'Category and title are required.');
        }
    } elseif ($action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        if ($id > 0 && $categoryId > 0 && $title !== '') {
            db_exec(
                'UPDATE forum_forums SET category_id = ?, title = ?, description = ?, sort_order = ? WHERE id = ?',
                [$categoryId, $title, $description ?: null, $sortOrder, $id]
            );
            flash_set('success', 'Forum updated.');
        } else {
            flash_set('error', 'Invalid data.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Cascade: delete threads -> posts
            $threads = db_query('SELECT id FROM forum_threads WHERE forum_id = ?', [$id]);
            foreach ($threads as $t) {
                db_exec('DELETE FROM forum_posts   WHERE thread_id = ?', [$t['id']]);
            }
            db_exec('DELETE FROM forum_threads WHERE forum_id = ?', [$id]);
            db_exec('DELETE FROM forum_forums  WHERE id = ?',       [$id]);
            flash_set('success', 'Forum and all its contents deleted.');
        }
    }

    redirect(SITE_URL . '/admin/forum/forums.php');
}

$categories = db_query('SELECT id, title FROM forum_categories ORDER BY sort_order ASC, id ASC');

$forums = db_query(
    'SELECT f.id, f.title, f.description, f.sort_order, f.category_id,
            c.title AS category_title,
            COUNT(DISTINCT t.id) AS thread_count
     FROM   forum_forums f
     JOIN   forum_categories c ON c.id = f.category_id
     LEFT   JOIN forum_threads t ON t.forum_id = f.id AND t.is_deleted = 0
     GROUP  BY f.id, f.title, f.description, f.sort_order, f.category_id, c.title
     ORDER  BY c.sort_order ASC, c.id ASC, f.sort_order ASC, f.id ASC'
);

// Editing a specific forum?
$editing = null;
if (isset($_GET['edit'])) {
    $editId  = (int)$_GET['edit'];
    $editing = db_row('SELECT * FROM forum_forums WHERE id = ?', [$editId]);
}

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Manage Forums</h1>

        <!-- Create / Edit form -->
        <section class="admin-section">
            <h2><?= $editing ? 'Edit Forum' : 'Create Forum' ?></h2>
            <form method="post" action="<?= SITE_URL ?>/admin/forum/forums.php" class="forum-admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'create' ?>">
                <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">— Select —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"
                                    <?= $editing && (int)$editing['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['title']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" maxlength="100" required
                               value="<?= $editing ? e($editing['title']) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="sort_order">Sort Order</label>
                        <input type="number" id="sort_order" name="sort_order"
                               value="<?= $editing ? (int)$editing['sort_order'] : 0 ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="2"><?= $editing ? e($editing['description'] ?? '') : '' ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $editing ? 'Update Forum' : 'Create Forum' ?></button>
                    <?php if ($editing): ?>
                    <a href="<?= SITE_URL ?>/admin/forum/forums.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- Forum list -->
        <section class="admin-section">
            <h2>All Forums</h2>
            <?php if (empty($forums)): ?>
                <p class="muted">No forums yet.</p>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Threads</th>
                        <th>Sort</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forums as $f): ?>
                    <tr>
                        <td><?= (int)$f['id'] ?></td>
                        <td><a href="<?= e(SITE_URL . '/forum/forum.php?id=' . (int)$f['id']) ?>"><?= e($f['title']) ?></a></td>
                        <td><?= e($f['category_title']) ?></td>
                        <td><?= (int)$f['thread_count'] ?></td>
                        <td><?= (int)$f['sort_order'] ?></td>
                        <td>
                            <a href="<?= e(SITE_URL . '/admin/forum/forums.php?edit=' . (int)$f['id']) ?>"
                               class="btn btn-xs btn-secondary">Edit</a>
                            <form method="post" action="<?= SITE_URL ?>/admin/forum/forums.php" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger"
                                        data-confirm="Delete this forum and ALL its contents?">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
