<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Category Management (CRUD with Foreign Key Safety & Auditing)
 * 
 * Features:
 * - List categories with active dish counters and ordering
 * - Add and edit categories with icons/emojis and descriptions
 * - Delete guard preventing deletion of categories containing active dishes (Referential Integrity)
 * - Audited via Component A's `log_action()`
 * - Role-guarded for kitchen_staff and admin
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

// Route Guard
require_role(['kitchen_staff', 'admin']);

$currentUser = current_user();
$userId = (int)$currentUser['id'];

// Helper to generate slug
function cat_slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'cat-' . time() : $text;
}

// -------------------------------------------------------------------------
// Handle POST Actions
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token expired. Please try again.');
        redirect(url('menu/manage_categories.php'));
    }

    $action = $_POST['action'] ?? '';

    // ACTION 1: CREATE CATEGORY
    if ($action === 'create_category') {
        $name        = trim((string)($_POST['name'] ?? ''));
        $icon        = trim((string)($_POST['icon'] ?? '🍲'));
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            set_flash('error', 'Category name cannot be empty.');
            redirect(url('menu/manage_categories.php'));
        }

        $slug = cat_slugify($name);

        try {
            // Check uniqueness
            $chk = $pdo->prepare("SELECT id FROM categories WHERE name = :n OR slug = :s LIMIT 1");
            $chk->execute([':n' => $name, ':s' => $slug]);
            if ($chk->fetch()) {
                set_flash('error', "A category named '{$name}' already exists.");
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO categories (name, slug, icon, display_order, description, is_active, created_at)
                     VALUES (:name, :slug, :icon, :order, :desc, 1, NOW())"
                );
                $ins->execute([
                    ':name'  => $name,
                    ':slug'  => $slug,
                    ':icon'  => $icon ?: '🍲',
                    ':order' => $displayOrder,
                    ':desc'  => $description,
                ]);

                $newId = (int)$pdo->lastInsertId();
                log_action($pdo, $userId, 'CATEGORY_CREATE', "Created category '{$name}' (ID: {$newId})");
                set_flash('success', "Category '{$name}' created successfully.");
            }
        } catch (PDOException $e) {
            error_log("[Create Category Error] " . $e->getMessage());
            set_flash('error', 'Database error creating category: ' . $e->getMessage());
        }

        redirect(url('menu/manage_categories.php'));
    }

    // ACTION 2: UPDATE CATEGORY
    if ($action === 'update_category') {
        $catId        = (int)($_POST['category_id'] ?? 0);
        $name         = trim((string)($_POST['name'] ?? ''));
        $icon         = trim((string)($_POST['icon'] ?? '🍲'));
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $description  = trim((string)($_POST['description'] ?? ''));
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        if ($catId <= 0 || $name === '') {
            set_flash('error', 'Invalid category fields.');
            redirect(url('menu/manage_categories.php'));
        }

        try {
            $upd = $pdo->prepare(
                "UPDATE categories 
                 SET name = :name, icon = :icon, display_order = :order, description = :desc, is_active = :active, updated_at = NOW()
                 WHERE id = :id"
            );
            $upd->execute([
                ':name'   => $name,
                ':icon'   => $icon ?: '🍲',
                ':order'  => $displayOrder,
                ':desc'   => $description,
                ':active' => $isActive,
                ':id'     => $catId,
            ]);

            log_action($pdo, $userId, 'CATEGORY_UPDATE', "Updated category '{$name}' (ID: {$catId})");
            set_flash('success', "Category '{$name}' updated successfully.");
        } catch (PDOException $e) {
            error_log("[Update Category Error] " . $e->getMessage());
            set_flash('error', 'Database error updating category: ' . $e->getMessage());
        }

        redirect(url('menu/manage_categories.php'));
    }

    // ACTION 3: DELETE / TOGGLE CATEGORY WITH REFERENTIAL CHECK
    if ($action === 'delete_category') {
        $catId = (int)($_POST['category_id'] ?? 0);

        try {
            // Check foreign key dependencies
            $dishCountStmt = $pdo->prepare("SELECT COUNT(*) FROM dishes WHERE category_id = :id");
            $dishCountStmt->execute([':id' => $catId]);
            $associatedDishes = (int)$dishCountStmt->fetchColumn();

            if ($associatedDishes > 0) {
                set_flash('error', "Cannot delete this category because {$associatedDishes} dish(es) are assigned to it. Please reassign or delete those dishes first to preserve data integrity.");
            } else {
                $delStmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
                $delStmt->execute([':id' => $catId]);

                log_action($pdo, $userId, 'CATEGORY_DELETE', "Deleted category ID {$catId}");
                set_flash('success', "Category deleted successfully.");
            }
        } catch (PDOException $e) {
            error_log("[Delete Category Error] " . $e->getMessage());
            set_flash('error', 'Cannot delete category due to database constraint: ' . $e->getMessage());
        }

        redirect(url('menu/manage_categories.php'));
    }
}

// -------------------------------------------------------------------------
// Edit Category Lookup
// -------------------------------------------------------------------------
$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editStmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
    $editStmt->execute([':id' => $editId]);
    $editCategory = $editStmt->fetch();
}

// -------------------------------------------------------------------------
// Fetch All Categories with Dish Counts
// -------------------------------------------------------------------------
try {
    $stmt = $pdo->query(
        "SELECT c.*, COUNT(d.id) AS dish_count,
                SUM(CASE WHEN d.is_active = 1 THEN 1 ELSE 0 END) AS active_dish_count
         FROM categories c
         LEFT JOIN dishes d ON d.category_id = c.id
         GROUP BY c.id
         ORDER BY c.display_order ASC, c.name ASC"
    );
    $allCategories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("[Manage Categories] Listing failed: " . $e->getMessage());
    $allCategories = [];
}

$pageTitle = 'Category Management';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span style="font-size: 1.5rem;">📁</span>
            <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--primary-600);">
                Component B &bull; Menu Taxonomy
            </span>
        </div>
        <h1 style="font-size: 2.1rem; font-weight: 800; color: var(--ink-900); letter-spacing: -0.02em;">
            Menu Categories
        </h1>
        <p style="color: var(--ink-500); font-size: 0.92rem;">
            Configure customer menu navigation tabs, icons, display priority, and descriptions.
        </p>
    </div>

    <div style="display: flex; gap: 0.75rem;">
        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('create-cat-modal').classList.add('active')">
            + Add New Category
        </button>
        <a href="<?= e(url('menu/manage_dishes.php')); ?>" class="btn btn-secondary btn-sm">
            Dishes Management
        </a>
        <a href="<?= e(url('menu/index.php')); ?>" class="btn btn-outline btn-sm">
            View Public Menu &rarr;
        </a>
    </div>
</div>

<!-- Edit Form (if editing) -->
<?php if ($editCategory): ?>
    <div class="card" style="border: 2px solid var(--primary-500); margin-bottom: 2.5rem;">
        <div class="card-header">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--ink-900);">
                    Edit Category: <?= e($editCategory['name']); ?>
                </h3>
            </div>
            <a href="<?= e(url('menu/manage_categories.php')); ?>" class="btn btn-secondary btn-sm">
                Cancel
            </a>
        </div>

        <form method="POST" action="<?= e(url('menu/manage_categories.php')); ?>">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="category_id" value="<?= e((string)$editCategory['id']); ?>">

            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label for="edit-cat-name">Category Name <span class="required">*</span></label>
                    <input type="text" id="edit-cat-name" name="name" class="form-control" value="<?= e($editCategory['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="edit-cat-icon">Icon Emoji</label>
                    <input type="text" id="edit-cat-icon" name="icon" class="form-control" value="<?= e($editCategory['icon'] ?: '🍲'); ?>" placeholder="e.g. 🍛">
                </div>
                <div class="form-group">
                    <label for="edit-cat-order">Display Order</label>
                    <input type="number" id="edit-cat-order" name="display_order" class="form-control" value="<?= e((string)$editCategory['display_order']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="edit-cat-desc">Description</label>
                <textarea id="edit-cat-desc" name="description" class="form-control" rows="2"><?= e($editCategory['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= (int)$editCategory['is_active'] === 1 ? 'checked' : ''; ?>>
                    <span>Category is Active (Visible on public menu tabs)</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <a href="<?= e(url('menu/manage_categories.php')); ?>" class="btn btn-secondary btn-sm">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary btn-sm">
                    Save Changes &rarr;
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Categories Table -->
<div class="table-card">
    <div style="padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(234, 223, 211, 0.6);">
        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--ink-900);">
            Active Menu Departments (<?= e((string)count($allCategories)); ?>)
        </h3>
        <span style="font-size: 0.85rem; color: var(--ink-500);">
            Referential safety prevents deletion of categories with attached dishes.
        </span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 50px;">Icon</th>
                <th>Category Name</th>
                <th>URL Slug</th>
                <th>Order</th>
                <th>Dishes Count</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($allCategories)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--ink-500);">
                        No categories found. Click "+ Add New Category" to create one.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($allCategories as $c): 
                    $isActive = (int)$c['is_active'] === 1;
                    $dishCount = (int)$c['dish_count'];
                    $activeDishes = (int)($c['active_dish_count'] ?? 0);
                ?>
                    <tr>
                        <td style="font-size: 1.6rem; text-align: center;">
                            <?= e($c['icon'] ?: '🍲'); ?>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: var(--ink-900); font-size: 0.96rem;">
                                <?= e($c['name']); ?>
                            </div>
                            <?php if (!empty($c['description'])): ?>
                                <div style="font-size: 0.82rem; color: var(--ink-500); margin-top: 0.2rem;">
                                    <?= e($c['description']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-family: monospace; font-size: 0.85rem; color: var(--ink-500);">
                            <?= e($c['slug']); ?>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: var(--ink-700);"><?= e((string)$c['display_order']); ?></span>
                        </td>
                        <td>
                            <span class="role-pill">
                                <?= e((string)$dishCount); ?> dishes (<?= e((string)$activeDishes); ?> active)
                            </span>
                        </td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="status-pill status-active"><span class="dot"></span>Active</span>
                            <?php else: ?>
                                <span class="status-pill status-inactive"><span class="dot"></span>Hidden</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 0.45rem;">
                                <a href="<?= e(url('menu/manage_categories.php?edit=' . $c['id'])); ?>" class="btn btn-secondary btn-sm">
                                    Edit
                                </a>
                                <form method="POST" action="<?= e(url('menu/manage_categories.php')); ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category? (Will fail if dishes are assigned)');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="category_id" value="<?= e((string)$c['id']); ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger-500);">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Category Modal -->
<div class="modal-overlay" id="create-cat-modal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title">+ Add New Category</h3>
            <button type="button" class="modal-close-btn" onclick="document.getElementById('create-cat-modal').classList.remove('active')">&times;</button>
        </div>

        <form method="POST" action="<?= e(url('menu/manage_categories.php')); ?>">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="create_category">

            <div class="form-group">
                <label for="new-cat-name">Category Name <span class="required">*</span></label>
                <input type="text" id="new-cat-name" name="name" class="form-control" placeholder="e.g. Clay Pot Curries" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="new-cat-icon">Icon Emoji</label>
                    <input type="text" id="new-cat-icon" name="icon" class="form-control" value="🍲" placeholder="e.g. 🍛">
                </div>
                <div class="form-group">
                    <label for="new-cat-order">Display Order</label>
                    <input type="number" id="new-cat-order" name="display_order" class="form-control" value="1">
                </div>
            </div>

            <div class="form-group">
                <label for="new-cat-desc">Description</label>
                <textarea id="new-cat-desc" name="description" class="form-control" rows="2" placeholder="Brief summary of category flavors..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('create-cat-modal').classList.remove('active')">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                    Create Category &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
