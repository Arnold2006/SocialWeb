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
 * admin/forum/categories.php — Manage forum categories
 */

declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_admin();

$pageTitle = 'Manage Categories — Forum Admin';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        if ($title !== '') {
            db_insert(
                'INSERT INTO forum_categories (title, description, sort_order) VALUES (?, ?, ?)',
                [$title, $description ?: null, $sortOrder]
            );
            flash_set('success', 'Category created.');
        } else {
            flash_set('error', 'Title is required.');
        }
    } elseif ($action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        if ($id > 0 && $title !== '') {
            db_exec(
                'UPDATE forum_categories SET title = ?, description = ?, sort_order = ? WHERE id = ?',
                [$title, $description ?: null, $sortOrder, $id]
            );
            flash_set('success', 'Category updated.');
        } else {
            flash_set('error', 'Invalid data.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Cascade: delete forums -> threads -> posts
            $forums = db_query('SELECT id FROM forum_forums WHERE category_id = ?', [$id]);
            foreach ($forums as $f) {
                $threads = db_query('SELECT id FROM forum_threads WHERE forum_id = ?', [$f['id']]);
                foreach ($threads as $t) {
                    db_exec('DELETE FROM forum_posts   WHERE thread_id = ?', [$t['id']]);
                }
                db_exec('DELETE FROM forum_threads WHERE forum_id = ?', [$f['id']]);
            }
            db_exec('DELETE FROM forum_forums     WHERE category_id = ?', [$id]);
            db_exec('DELETE FROM forum_categories WHERE id = ?',          [$id]);
            flash_set('success', 'Category and all its contents deleted.');
        }
    }

    redirect(SITE_URL . '/admin/forum/categories.php');
}

$categories = db_query(
    'SELECT c.id, c.title, c.description, c.sort_order,
            COUNT(f.id) AS forum_count
     FROM   forum_categories c
     LEFT   JOIN forum_forums f ON f.category_id = c.id
     GROUP  BY c.id, c.title, c.description, c.sort_order
     ORDER  BY c.sort_order ASC, c.id ASC'
);

// Editing a specific category?
$editing = null;
if (isset($_GET['edit'])) {
    $editId  = (int)$_GET['edit'];
    $editing = db_row('SELECT * FROM forum_categories WHERE id = ?', [$editId]);
}

include SITE_ROOT . '/includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Manage Categories</h1>

        <!-- Create / Edit form -->
        <section class="admin-section">
            <h2><?= $editing ? 'Edit Category' : 'Create Category' ?></h2>
            <form method="post" action="<?= SITE_URL ?>/admin/forum/categories.php" class="forum-admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'create' ?>">
                <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
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
                    <button type="submit" class="btn btn-primary"><?= $editing ? 'Update Category' : 'Create Category' ?></button>
                    <?php if ($editing): ?>
                    <a href="<?= SITE_URL ?>/admin/forum/categories.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- Category list -->
        <section class="admin-section">
            <h2>All Categories</h2>
            <?php if (empty($categories)): ?>
                <p class="muted">No categories yet.</p>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Forums</th>
                        <th>Sort</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?= (int)$cat['id'] ?></td>
                        <td><?= e($cat['title']) ?></td>
                        <td><?= e($cat['description'] ?? '') ?></td>
                        <td><?= (int)$cat['forum_count'] ?></td>
                        <td><?= (int)$cat['sort_order'] ?></td>
                        <td>
                            <a href="<?= e(SITE_URL . '/admin/forum/categories.php?edit=' . (int)$cat['id']) ?>"
                               class="btn btn-xs btn-secondary">Edit</a>
                            <form method="post" action="<?= SITE_URL ?>/admin/forum/categories.php" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger"
                                        onclick="return confirm('Delete this category and ALL its contents?')">Delete</button>
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
